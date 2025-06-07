<?php

namespace Tests\Service;

use HttpClient\HttpClient;
use HttpClient\Message\Request;
use HttpClient\Message\Stream;
use HttpClient\Message\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RequestHandler\ServerRequest;
use Service\OneTourismo\OneTourismoApiService;

class OneTourismoApiServiceTest extends TestCase
{
    public function test_apiGetCountries(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $mockResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetCountriesResponse xmlns="http://www.megatec.ru/">
                            <GetCountriesResult>
                                <Country><Name>ROMANIA</Name><ID>1</ID><IsIncoming>false</IsIncoming></Country>
                                <Country><Name>ROMANI</Name><ID>1</ID><IsIncoming>false</IsIncoming></Country>
                                <Country><Name>BULGARIA</Name><ID>4</ID><IsIncoming>false</IsIncoming></Country>
                            </GetCountriesResult>
                        </GetCountriesResponse>
                    </soap:Body>
                </soap:Envelope>'))
        ;

        $mockHttpClient
            ->method('request')
            ->willReturn($mockResponse);

        $body = json_encode([
            'to' => ['handle']
        ]);

        $serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));

        $service = new OneTourismoApiService($serverRequest, $mockHttpClient);
        $countries = $service->apiGetCountries();

        $this->assertTrue(count($countries) > 0);
        $this->assertTrue(true);
    }
}