<?php

namespace Tests;

use HttpClient\Exception\RequestException;
use HttpClient\HttpClient;
use HttpClient\Message\Request;
use HttpClient\Wrapper\CurlWrapper;
use PHPUnit\Framework\TestCase;

// todo: testat cu un raspuns de 1 gb
class HttpClientTest extends TestCase
{
    public function test_request()
    {
        $curl = $this->createMock(CurlWrapper::class);
        $ch = curl_init();

        $curl->method('init')->willReturn($ch);
        $curl->method('exec')->willReturn("return body");
        $curl->method('getinfo')->willReturnMap([
            [$ch, CURLINFO_HTTP_CODE, 200]
        ]);

        $curl->method('setopt')
            ->willReturnCallback(function ($handle, $option, $value) use (&$headerLines) {
                if ($option === CURLOPT_HEADERFUNCTION && is_callable($value)) {
                    // Simulate headers being sent by curl
                    $headersToSend = [
                        "HTTP/1.1 200 OK\r\n",
                        "Header: value1\r\n",
                        "Header: value2\r\n"
                    ];
                    foreach ($headersToSend as $line) {
                        $value(null, $line);
                    }
                }
                return true;
            });

        $client = new HttpClient($curl);
        $client->setExtraOptions([CURLOPT_SSL_VERIFYPEER => false]);

        $headers = [
            'Content-Type' => 'application/json'
        ];

        $response = $client->request('method', 'url', 'request body', $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('return body', (string) $response->getBody());
        $this->assertEquals(['header' => ['value1', 'value2']], $response->getHeaders());

        // verifica raspunsul
        
    }

    public function test_requestWithEmptyResponse()
    {
        $curl = $this->createMock(CurlWrapper::class);

        $ch = curl_init();
        $curl->method('init')->willReturn($ch);
        $curl->method('exec')->willReturn(false);
        $curl->method('getinfo')->willReturnMap([
            [$ch, CURLINFO_HTTP_CODE, 0]
        ]);
        
        $client = new HttpClient($curl);

        $this->expectException(RequestException::class);
        try {
            $client->request('method', 'url');
        } catch (RequestException $e) {
            $e->getRequest();
            throw $e;
        }
    }

    public function test_sendRequests()
    {
        $curl = $this->createMock(CurlWrapper::class);
        $ch = curl_init();
        $multi = curl_multi_init();

        $curl->method('init')->willReturn($ch);
        $curl->method('multiInit')->willReturn($multi);
        $curl->method('multiGetContent')->willReturn('return body');
        $curl->method('getinfo')->willReturnMap([
            [$ch, CURLINFO_HTTP_CODE, 200]
        ]);

        $curl->method('setopt')
            ->willReturnCallback(function ($ch, $option, $value) use (&$headerLines) {
                if ($option === CURLOPT_HEADERFUNCTION && is_callable($value)) {
                    // Simulate headers being sent by curl
                    $headersToSend = [
                        "HTTP/1.1 200 OK\r\n",
                        "Header: value1\r\n",
                        "Header: value2\r\n"
                    ];
                    foreach ($headersToSend as $line) {
                        $value($ch, $line);
                    }
                }
                return true;
            });

        $client = new HttpClient($curl);
        $client->setExtraOptions([CURLOPT_SSL_VERIFYPEER => false]);

        $headers = [
            'Content-Type' => 'application/json'
        ];

        $request1 = new Request('method', 'url', 'request body', $headers);

        $responses = $client->sendRequests([$request1]);

        foreach ($responses as $response) {
            //print_r($response->getHeaders());
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('return body', (string) $response->getBody());
            $this->assertEquals(['header' => ['value1', 'value2']], $response->getHeaders());
        }
    }

    public function test_sendRequests_whenErrorIsReceived()
    {
        $curl = $this->createMock(CurlWrapper::class);
        $ch = curl_init();
        $multi = curl_multi_init();

        $curl->method('init')->willReturn($ch);
        $curl->method('multiInit')->willReturn($multi);
        $curl->method('multiGetContent')->willReturn('return body');
        $curl->method('error')->willReturn('error');
        $curl->method('getinfo')->willReturnMap([
            [$ch, CURLINFO_HTTP_CODE, 200]
        ]);

        $client = new HttpClient($curl);

        $request1 = new Request('method', 'url', 'request body');

        $this->expectException(RequestException::class);
        
        $client->sendRequests([$request1]);
    }

    public function test_sendRequests_withMultiExecStatus()
    {
         $curl = $this->createMock(CurlWrapper::class);
        $ch = curl_init();
        $multi = curl_multi_init();

        $curl->method('init')->willReturn($ch);
        $curl->method('multiInit')->willReturn($multi);
        $curl->method('multiGetContent')->willReturn('return body');
        $curl->method('error')->willReturn('error');
        $curl->method('multiExec')->willReturn(CURLM_INTERNAL_ERROR);

        $curl->method('getinfo')->willReturnMap([
            [$ch, CURLINFO_HTTP_CODE, 200]
        ]);

        $client = new HttpClient($curl);

        $request1 = new Request('method', 'url', 'request body');

        $this->expectException(RequestException::class);
        
        $client->sendRequests([$request1]);
    }
}