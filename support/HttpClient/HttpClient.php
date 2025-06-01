<?php

namespace HttpClient;

use HttpClient\Exception\RequestException;
use HttpClient\Message\Request;
use HttpClient\Message\Response;
use HttpClient\Wrapper\CurlWrapper;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * How to create parallel requests:
 * $r1 = new Request(...);
 * $r2 = new Request(...);
 * $responses = $client->sendRequests[$r1, $2];
 * foreach $responses as $response...
 */
class HttpClient implements ClientInterface
{
    public function __construct(private ?CurlWrapper $curl = null)
    {
        $this->curl ??= new CurlWrapper();
    }

    private array $extraOptions = [];

    /**
     * ex: [CURLOPT_SSL_VERIFYPEER => false]
     */
    public function setExtraOptions(array $extraOptions): void
    {
        $this->extraOptions = $extraOptions;
    }

    public function request(string $method, string $url, string $body = '', array $headers = []): ResponseInterface
    {
        $request = new Request($method, $url, $body, $headers);

        return $this->sendRequest($request);
    }

    /**
     * Send a large set of requests in parallel, in batches.
     *
     * @param RequestInterface[] $requests   Array of PSR-7 requests
     * @param int                $batchSize How many to dispatch concurrently per batch
     * @return ResponseInterface[] Responses in same order as $requests
     * @throws ClientExceptionInterface on any failure
     */
    public function sendRequests(array $requests, int $batchSize = 10): array
    {
        $allResponses = [];
        // chunk preserving original keys
        $chunks = array_chunk($requests, $batchSize, true);

        foreach ($chunks as $chunk) {
            $responses = $this->sendBatch($chunk);
            // merge preserving keys
            $allResponses += $responses;
        }

        // sort by original keys and reindex
        ksort($allResponses);
        return array_values($allResponses);
    }

    /**
     * Internal: send one batch (up to batchSize) in parallel.
     *
     * @param RequestInterface[] $requests
     * @return ResponseInterface[] keyed by original request array keys
     * @throws ClientExceptionInterface
     */
    private function sendBatch(array $requests): array
    {
        $multi       = $this->curl->multiInit();
        $handles     = [];
        $headerStore = [];

        // 1) init handles
        foreach ($requests as $key => $request) {
            $ch = $this->curl->init((string) $request->getUri());
            $this->curl->setoptArray($ch, $this->extraOptions + [
                CURLOPT_CUSTOMREQUEST  => $request->getMethod(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER         => false,
            ]);

            // headers
            $hdrs = [];
            foreach ($request->getHeaders() as $n => $vals) {
                foreach ($vals as $v) {
                    $hdrs[] = "$n: $v";
                }
            }
            if ($hdrs) {
                $this->curl->setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
            }
            // body
            $body = (string)$request->getBody();
            if ($body !== '') {
                $this->curl->setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            // header parser
            $headerStore[(int)$ch] = [];
            $this->curl->setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $line) use (&$headerStore) {
                $trim = trim($line);
                if ($trim === '' || strpos($trim, 'HTTP/') === 0) {
                    return strlen($line);
                }
                if (strpos($trim, ':') !== false) {
                    [$n, $v] = explode(':', $trim, 2);
                    $headerStore[(int) $ch][trim($n)][] = trim($v);
                }
                return strlen($line);
            });

            $this->curl->multiAddHandle($multi, $ch);
            $handles[$key] = $ch;
        }

        // 2) execute
        do {
            $status = $this->curl->multiExec($multi, $active);
            if ($status > CURLM_OK) {
                break;
            }
            $this->curl->multiSelect($multi);
        } while ($active);

        // 3) collect
        $responses = [];
        foreach ($handles as $key => $ch) {
            $statusCode = $this->curl->getinfo($ch, CURLINFO_HTTP_CODE);

            if (($err = $this->curl->error($ch)) !== '') {
                $this->curl->multiRemoveHandle($multi, $ch);
                $this->curl->close($ch);
                $this->curl->multiClose($multi);
                throw new RequestException($err, $request, $statusCode);
            }

            $body = $this->curl->multiGetContent($ch);
            
            $resp = new Response($body, $statusCode, $headerStore[(int) $ch]);

            $responses[$key] = $resp;

            $this->curl->multiRemoveHandle($multi, $ch);
            $this->curl->close($ch);
        }

        $this->curl->multiClose($multi);
        return $responses;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $ch = $this->curl->init();
        $this->curl->setopt($ch, CURLOPT_URL, (string)$request->getUri());
        $this->curl->setoptArray($ch, $this->extraOptions + [
            CURLOPT_CUSTOMREQUEST  => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER         => false,
        ]);

        // if (isset($settings['verify_peer'])) {
        //     $this->curl->setopt($ch, CURLOPT_SSL_VERIFYPEER, $settings['verify_peer']);
        // } else {
        //     $this->curl->setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // }

        // headers
        $hdrs = [];
        foreach ($request->getHeaders() as $name => $vals) {
            foreach ($vals as $v) {
                $hdrs[] = "$name: $v";
            }
        }
        if ($hdrs) {
            $this->curl->setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
        }

        // body
        $body = (string)$request->getBody();
        if ($body !== '') {
            $this->curl->setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // capture headers
        $responseHeaders = [];
        $this->curl->setopt(
            $ch,
            CURLOPT_HEADERFUNCTION,
            function ($ch, string $line) use (&$responseHeaders) {
                $trim = trim($line);
                if ($trim === '' || strpos($trim, 'HTTP/') === 0) {
                    return strlen($line);
                }
                if (strpos($trim, ':') !== false) {
                    [$n, $v] = explode(':', $trim, 2);
                    $responseHeaders[trim($n)][] = trim($v);
                }
                return strlen($line);
            }
        );

        // execute
        $respBody = $this->curl->exec($ch);
        $status = $this->curl->getinfo($ch, CURLINFO_HTTP_CODE);

        if ($respBody === false) {
            $err = $this->curl->error($ch);
            $this->curl->close($ch);
            throw new RequestException($err, $request, $status);
        }

        
        $this->curl->close($ch);

        $response = new Response($respBody, $status, $responseHeaders);

        return $response;
    }
}
