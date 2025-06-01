<?php

namespace Controllers;

use Psr\Http\Message\ServerRequestInterface;
use RequestHandler\ServerRequest;
use Router\View;
use Utils\Utils;

class WebController
{
    public function __construct(private ServerRequestInterface $request)
    {

    }

    public function index(): View
    {
        // $call = $this->request->getQueryParam('call');

        // if ($call == null) {
        //     return View::make('index');
        // }

        // $form = new Forms($call);

        return View::make('home', []);
    }

    public function post(): View
    {
        $post = $this->request->getPostParams();
        $call = $this->request->getQueryParam('call');
        $form = new Forms($call);

        $data = [];
        $error = null;
        $responses = null;

        $t0 = microtime(true);
        /*
        try {
            $method = $post['method'];
            $filter = $post['args'] ?? [];
            $service = $this->serviceGetter->getService($post);
            $responses = $service->getResponses();
            $data = $service->$method(...$filter);
        } catch (Throwable $ex) {
            $error = $ex->getMessage() . ' in ' 
                . $ex->getFile() . ' line ' 
                . $ex->getLine() . PHP_EOL
                . 'Stack trace:' . PHP_EOL
                . $ex->getTraceAsString();
            Log::error($error);
        }*/
        $client = HttpClient::create();
        $baseUrl = Utils::getBaseUrl();
        $proxyUrl = $baseUrl . '/api';

        $options['body'] = json_encode($post);
        $options['headers'] = [
            'Content-Type' => 'application/json'
        ];

        $response = $client->request(HttpClient::METHOD_POST, $proxyUrl, $options);
        $dataJson = $response->getContent(false);

        $time = microtime(true) - $t0;
        $executeTime = round($time, 3);
        $resp = json_decode($dataJson, true);

        if (!isset($resp['response']) && !isset($resp['error'])) {
            echo $dataJson;
        }

        $data = $resp['response'] ?? [];
        $shortJson = Utils::getShortJson($data, true);

        $responses = $resp['toRequestsAndResponses'] ?? [];
        $error = $resp['error'] ?? [];

        return View::make('view', [
            'header' => $form->getPageHeader(),
            'description' => $form->getPageDescription(),
            'list' => $data,
            'executeTime' => $executeTime,
            'error' => $error,
            'shortJson' => $shortJson,
            'form' => $form->getForm(),
            'responses' => $responses
        ]);
    }
}
