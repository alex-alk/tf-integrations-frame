<?php

namespace Tests\Services;

use HttpClient\HttpClient;
use HttpClient\Message\Request;
use HttpClient\Message\Stream;
use HttpClient\Message\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RequestHandler\ServerRequest;
use Services\Megatec\MegatecApiService;
use Utils\Utils;

use function PHPUnit\Framework\exactly;

class MegatecApiServiceTest extends TestCase
{
    private ServerRequest $serverRequest;

    public function setUp(): void
    {
        $body = json_encode([
            'to' => [
                'Handle' => 'test',
                'ApiUsername' => 'test',
                'ApiPassword' => 'test',
                'ApiUrl' => 'test'
            ]
        ]);
        $this->serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));
    
        Utils::deleteDirectory(Utils::getCachePath() . '/test');
    }

    public function test_apiGetCountries(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $mockResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <GetCountriesResponse xmlns="http://www.megatec.ru/">
                        <GetCountriesResult>
                            <Country><Name>ROMANIA</Name><ID>1</ID><IsIncoming>false</IsIncoming></Country>
                            <Country><Name>ROMANI</Name><ID>1</ID><IsIncoming>false</IsIncoming></Country>
                            <Country><Name>BULGARIA</Name><ID>4</ID><IsIncoming>false</IsIncoming></Country>
                        </GetCountriesResult>
                    </GetCountriesResponse>
                </soap:Body>
                </soap:Envelope>'
            ));

        $mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);
        $countries = $service->apiGetCountries();

        $this->assertTrue(count($countries) > 0);
    }

    public function test_apiGetRegions(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockCountriesResponse = $this->createMock(ResponseInterface::class);
        $mockRegionsResponse = $this->createMock(ResponseInterface::class);

        $mockCountriesResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <GetCountriesResponse xmlns="http://www.megatec.ru/">
                        <GetCountriesResult>
                            <Country><Name>ROMANIA</Name><ID>1</ID><IsIncoming>false</IsIncoming></Country>
                        </GetCountriesResult>
                    </GetCountriesResponse>
                </soap:Body>
                </soap:Envelope>'
            ));


        $mockRegionsResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetRegionsResponse
                            xmlns="http://www.megatec.ru/">
                            <GetRegionsResult>
                                <Region>
                                    <Name>All</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>ALL</Code>
                                    <CountryID>4</CountryID>
                                </Region>
                            </GetRegionsResult>
                        </GetRegionsResponse>
                    </soap:Body>
                </soap:Envelope>'
            ));

        $invokedCount = $this->exactly(2);

        $mockResponses = [$mockCountriesResponse, $mockRegionsResponse];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
            });

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);
        $regions = $service->apiGetRegions();

        $this->assertTrue(count($regions) > 0);
    }

    public function test_apiGetCities(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockCountriesResponse = $this->createMock(ResponseInterface::class);
        $mockRegionsResponse = $this->createMock(ResponseInterface::class);
        $mockCitiesResponse = $this->createMock(ResponseInterface::class);

        $mockCountriesResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <GetCountriesResponse xmlns="http://www.megatec.ru/">
                        <GetCountriesResult>
                            <Country><Name>BULGARIA</Name><ID>4</ID><IsIncoming>false</IsIncoming></Country>
                        </GetCountriesResult>
                    </GetCountriesResponse>
                </soap:Body>
                </soap:Envelope>'
            ));

        $mockRegionsResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetRegionsResponse
                            xmlns="http://www.megatec.ru/">
                            <GetRegionsResult>
                                <Region>
                                    <Name>All</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>ALL</Code>
                                    <CountryID>4</CountryID>
                                </Region>
                            </GetRegionsResult>
                        </GetRegionsResponse>
                    </soap:Body>
                </soap:Envelope>'
        ));

        $mockCitiesResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetCitiesResponse
                            xmlns="http://www.megatec.ru/">
                            <GetCitiesResult>
                                <City>
                                    <Name>Borovets</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>jlj</Code>
                                    <CountryID>4</CountryID>
                                    <RegionID>6</RegionID>
                                </City>
                                <City>
                                    <Name>Borovets</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>jlj</Code>
                                    <CountryID>40</CountryID>
                                    <RegionID>6</RegionID>
                                </City>
                            </GetCitiesResult>
                        </GetCitiesResponse>
                    </soap:Body>
                </soap:Envelope>'
            ));

        $invokedCount = $this->exactly(4);

        $mockResponses = [
            // countries
            $mockCountriesResponse,
            
            // regions
            $mockCountriesResponse, 
            $mockRegionsResponse, 
            
            // cities
            $mockCitiesResponse
        ];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
                if ($invokedCount->numberOfInvocations() === 3) {
                    return $mockResponses[2];
                }
                if ($invokedCount->numberOfInvocations() === 4) {
                    return $mockResponses[3];
                }
            });

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);
        $cities = $service->apiGetCities();

        $this->assertTrue(count($cities) > 0);
    }

    public function test_apiGetHotels(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockCountriesResponse = $this->createMock(ResponseInterface::class);
        $mockRegionsResponse = $this->createMock(ResponseInterface::class);
        $mockCitiesResponse = $this->createMock(ResponseInterface::class);
        $mockHotelsResponse = $this->createMock(ResponseInterface::class);

        $mockCountriesResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <GetCountriesResponse xmlns="http://www.megatec.ru/">
                        <GetCountriesResult>
                            <Country><Name>BULGARIA</Name><ID>4</ID><IsIncoming>false</IsIncoming></Country>
                        </GetCountriesResult>
                    </GetCountriesResponse>
                </soap:Body>
                </soap:Envelope>'
            ));

        $mockRegionsResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetRegionsResponse
                            xmlns="http://www.megatec.ru/">
                            <GetRegionsResult>
                                <Region>
                                    <Name>All</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>ALL</Code>
                                    <CountryID>4</CountryID>
                                </Region>
                            </GetRegionsResult>
                        </GetRegionsResponse>
                    </soap:Body>
                </soap:Envelope>'
        ));

        $mockCitiesResponse
            ->method('getBody')
            ->willReturn(new Stream('<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetCitiesResponse
                            xmlns="http://www.megatec.ru/">
                            <GetCitiesResult>
                                <City>
                                    <Name>Borovets</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>jlj</Code>
                                    <CountryID>4</CountryID>
                                    <RegionID>6</RegionID>
                                </City>
                                <City>
                                    <Name>Borovets</Name>
                                    <ID>6</ID>
                                    <Description>BULGARIA</Description>
                                    <Code>jlj</Code>
                                    <CountryID>40</CountryID>
                                    <RegionID>6</RegionID>
                                </City>
                            </GetCitiesResult>
                        </GetCitiesResponse>
                    </soap:Body>
                </soap:Envelope>'
            ));

        $mockHotelsResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "limit": 1,
                "pages": 1498,
                "data": [
                    {
                    "id": "7866",
                    "il_id": "7866",
                    "il_hotelname": "Carnival",
                    "lat": "43.283181090803910",
                    "lon": "28.040697438046077",
                    "city": {
                        "id": "33",
                        "name": "Golden Sands"
                    },
                    "region": {
                        "id": "Varna",
                        "name": "2"
                    },
                    "country": {
                        "id": "4",
                        "name": "BULGARIA"
                    },
                    "created_at": null,
                    "updated_at": "2025-05-13 15:26:46",
                    "i18n_locale": "ro-RO",
                    "name": "Carnival",
                    "code": "GS004",
                    "description": "abc",
                    "il_description": "3*  (\\\\Golden Sands)",
                    "meta_title": "hotel Carnival Golden Sands ",
                    "meta_keywords": "hotel Carnival Golden Sands  Varna 3*",
                    "meta_description": "<p>hotel Carnival 3* (\\\\Golden Sands) Golden Sands Varna 3*</p>",
                    "video": [],
                    "conference_rooms": [],
                    "notes": [
                        {
                        "id": "39574",
                        "title": "SPORT & AGREMENT:",
                        "descripion": "def"
                        }
                    ],
                    "hotels_tags": null,
                    "image": {
                        "file_path": "/web/files/hotels/7866/hotel_main_image/",
                        "filename": "2.jpg",
                        "url": "https://b2b.solvex.bg/web/files/hotels/7866/hotel_main_image/2.jpg"
                    },
                    "images": [
                        {
                        "file_path": "/web/files/hotels/7866/hotel_images/",
                        "filename": "13.jpg",
                        "url": "https://b2b.solvex.bg/web/files/hotels/7866/hotel_images/13.jpg"
                        }
                    ],
                    "image_thumbs": {
                        "76x57": "thumb_76x57_"
                    }
                    }
                ]
                }'
            ));

        $invokedCount = $this->exactly(5);

        $mockResponses = [
            // countries
            $mockCountriesResponse,
            
            // regions
            $mockCountriesResponse, 
            $mockRegionsResponse, 
            
            // cities
            $mockCitiesResponse,

            $mockHotelsResponse
        ];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
                if ($invokedCount->numberOfInvocations() === 3) {
                    return $mockResponses[2];
                }
                if ($invokedCount->numberOfInvocations() === 4) {
                    return $mockResponses[3];
                }
                if ($invokedCount->numberOfInvocations() === 5) {
                    return $mockResponses[4];
                }
            });

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);
        $data = $service->apiGetHotels();

        $this->assertTrue(count($data) > 0);
    }

    public function test_apiGetOffers(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);

        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockOffersResponse = $this->createMock(ResponseInterface::class);
        $mockMealsResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <ConnectResponse xmlns="http://www.megatec.ru/">
                            <ConnectResult>a2e173c7-e984-46f8-8144-a05221e0a4d1</ConnectResult>
                        </ConnectResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $mockOffersResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope
                xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <SearchHotelServicesResponse
                        xmlns="http://www.megatec.ru/">
                        <SearchHotelServicesResult Message="Ok" Count="0">
                            <Data>
                                <DataRequestResult>
                                    <ResultTable>
                                        <diffgr:diffgram
                                            xmlns:msdata="urn:schemas-microsoft-com:xml-msdata"
                                            xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1">
                                            <DocumentElement
                                                xmlns="">
                                                <HotelServices diffgr:id="HotelServices1" msdata:rowOrder="0" diffgr:hasChanges="inserted">
                                                    <HotelName xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">Aspen Aparthotel (Bansko) 3*</HotelName>
                                                    <HotelKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">4426</HotelKey><RtCode xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">APT</RtCode><RtKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">58</RtKey><RcName xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">1 bedroom APPART</RcName><RcKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">65</RcKey><RdName xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">APT 1 bedroom APPART</RdName><RdKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">123</RdKey><AcName xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">2Ad</AcName><AcKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">2220</AcKey><PnCode xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">BB</PnCode><PnKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">23</PnKey>
                                                    <TotalCost xsi:type="xs:decimal"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">82.4000</TotalCost>
                                                    <Cost xsi:type="xs:decimal"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">78.00</Cost>
                                                    <AddHotsCost xsi:type="xs:decimal"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">4.40</AddHotsCost>
                                                    <AddHotsWithCosts xsi:type="xs:decimal"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">0.00</AddHotsWithCosts>
                                                    <DetailBrutto xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">(78.00[23-24_Ordinary_s20-29.12.23_05.01-20.04.24_AspenAH]*1) * 1 room = 78.00
                                                    </DetailBrutto>
                                                    <QuoteType xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">0</QuoteType>
                                                    <CountryKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">4</CountryKey>
                                                    <CityKey xsi:type="xs:int"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">9</CityKey>
                                                    <CityName xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema">Bansko
                                                    </CityName>
                                                    <HotelWebSite xsi:type="xs:string"
                                                        xmlns:xs="http://www.w3.org/2001/XMLSchema" />
                                                        <TariffId xsi:type="xs:int"
                                                            xmlns:xs="http://www.w3.org/2001/XMLSchema">0</TariffId>
                                                        <TariffName xsi:type="xs:string"
                                                            xmlns:xs="http://www.w3.org/2001/XMLSchema">Ordinary</TariffName>
                                                        <TariffDescription xsi:type="xs:string"
                                                            xmlns:xs="http://www.w3.org/2001/XMLSchema" />
                                                        <AddHots xsi:type="xs:string"
                                                                xmlns:xs="http://www.w3.org/2001/XMLSchema">0,-1,-2
                                                        </AddHots>
                                                        <ContractPrKey xsi:type="xs:int"
                                                                xmlns:xs="http://www.w3.org/2001/XMLSchema">4427
                                                        </ContractPrKey>
                                                        <Rate xsi:type="xs:string"
                                                            xmlns:xs="http://www.w3.org/2001/XMLSchema">EU</Rate>
                                                        <AddHotsWithCostID xsi:type="xs:string"
                                                            xmlns:xs="http://www.w3.org/2001/XMLSchema" />
                                                </HotelServices>
                                            </DocumentElement>
                                            </diffgr:diffgram>
                                        </ResultTable>
                                    </DataRequestResult>
                                </Data>
                            </SearchHotelServicesResult>
                        </SearchHotelServicesResponse>
                    </soap:Body>
                </soap:Envelope>'
                )
            );

        $mockMealsResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body>
                    <GetPansionsResponse xmlns="http://www.megatec.ru/">
                        <GetPansionsResult>
                            <Pansion>
                                <Name>&lt;Not defined&gt;</Name>
                                <ID>0</ID>
                                <Code>ND</Code>
                            </Pansion>
                            <Pansion>
                                <Name>Bed and breakfast</Name>
                                <ID>23</ID>
                                <Code>BB</Code>
                            </Pansion>
                        </GetPansionsResult>
                    </GetPansionsResponse>
                </soap:Body>
            </soap:Envelope>'
            )
        );

        $invokedCount = $this->exactly(3);

        $mockResponses = [
            $mockTokenResponse,
            $mockOffersResponse,
            $mockMealsResponse
        ];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
                if ($invokedCount->numberOfInvocations() === 3) {
                    return $mockResponses[2];
                }
            });

        $body = json_encode([
            'to' => [
                'Handle' => 'test',
                'ApiUsername' => 'test',
                'ApiPassword' => 'test',
                'ApiUrl' => 'test'
            ],
            'args' => [
                [
                    'checkIn' => '2024-01-10',
                    'checkOut' => '2024-01-11',
                    'showHotelName' => true,
                    'serviceTypes' => [
                        'hotel'
                    ],
                    'cityId' => '1',
                    'countryId' => '1',
                    'days' => '1',
                    'rooms' => [
                        [
                            'adults' => 2,
                            'children' => 2,
                            'childrenAges' => [
                                0,
                                5
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $this->serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);

        $resp = $service->apiGetOffers();

        $this->assertTrue(count($resp) > 0);
        
    }

    public function test_apiDoBooking(): void
    {

        $mockHttpClient = $this->createMock(HttpClient::class);

        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockReservationResponse = $this->createMock(ResponseInterface::class);
        $mockRatesResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <ConnectResponse xmlns="http://www.megatec.ru/">
                            <ConnectResult>a2e173c7-e984-46f8-8144-a05221e0a4d1</ConnectResult>
                        </ConnectResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $mockReservationResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <soap:Body><CreateReservationResponse xmlns="http://www.megatec.ru/">
                    <CreateReservationResult HasInvoices="false">
                        <Rate><Name>EU</Name><ID>1</ID><IsMain>false</IsMain><IsNational>false</IsNational></Rate>
                        <TouristServices>
                            <TouristService>
                                <ID>8387693</ID><ServiceID>3199711</ServiceID><TouristID>1917396</TouristID>
                            </TouristService>
                            <TouristService><ID>8387694</ID><ServiceID>3199711</ServiceID><TouristID>1917397</TouristID></TouristService><TouristService><ID>8387697</ID><ServiceID>3199709</ServiceID><TouristID>1917396</TouristID></TouristService>
                            <TouristService><ID>8387698</ID><ServiceID>3199709</ServiceID><TouristID>1917397</TouristID></TouristService>
                            <TouristService><ID>8387699</ID><ServiceID>3199710</ServiceID><TouristID>1917396</TouristID></TouristService><TouristService><ID>8387700</ID><ServiceID>3199710</ServiceID><TouristID>1917397</TouristID></TouristService>
                            <TouristService><ID>8387695</ID><ServiceID>3199708</ServiceID><TouristID>1917396</TouristID></TouristService><TouristService><ID>8387696</ID><ServiceID>3199708</ServiceID><TouristID>1917397</TouristID></TouristService></TouristServices>
                            <Services>
                                <Service xsi:type="HotelService">
                                    <ExternalID>-1</ExternalID>
                                    <Price>34</Price><NMen>1</NMen>
                                    <Partner>
                                        <Name>TRAVELFUSE TEST</Name>
                                        <ID>6410</ID>
                                        <PartnersGroupID>4391</PartnersGroupID>
                                        <FullName>TRAVELFUSE TEST</FullName></Partner>
                                        <PartnerID>6410</PartnerID>
                                        <Quota>NotChecked</Quota><PacketKey>0</PacketKey>
                                        <AdditionalParams><ParameterPair Key="ContractPrKey">
                                        <Value xsi:type="xsd:int">5172</Value></ParameterPair>
                                        <ParameterPair Key="CancellationPolicy">
                                            <Value xsi:type="ArrayOfPenaltyCost">
                                            <PenaltyCost><PolicyKey>-1</PolicyKey><DateFrom xsi:nil="true" />
                                            <DateTo>2024-05-28T23:59:59</DateTo><PenaltyValue>0</PenaltyValue>
                                            <IsPercent>false</IsPercent><TotalPenalty>0</TotalPenalty>
                                            <Description>If canceled before 28.05.2024, no penalty Penalty value is 0.00 EU</Description>
                                            </PenaltyCost><PenaltyCost><PolicyKey>16124</PolicyKey><DateFrom>2024-05-29T00:00:00</DateFrom>
                                            <DateTo>2024-05-31T23:59:59</DateTo><PenaltyValue>1</PenaltyValue><IsPercent>false</IsPercent>
                                            <TotalPenalty>34</TotalPenalty><Description>If canceled in period 29.05.2024 - 31.05.2024, the penalty will be  1 night(s) Penalty value is 34.00 EU</Description></PenaltyCost><PenaltyCost><PolicyKey>16122</PolicyKey><DateFrom>2024-06-01T00:00:00</DateFrom><DateTo xsi:nil="true" /><PenaltyValue>2</PenaltyValue><IsPercent>false</IsPercent>
                                            <TotalPenalty>34</TotalPenalty><Description>If canceled from 01.06.2024, the penalty will be 2 night(s) Penalty value is 34.00 EU</Description></PenaltyCost></Value></ParameterPair></AdditionalParams><DetailBrutto>(34.00[23/24_Ordinary_s27.12-30.09_VienGH]*1) * 1 room = 34.00</DetailBrutto><Notes /><Name>HTL::Bansko/Vien Guest house/DBL/2Ad/Standard/BB</Name><StartDate>2024-06-01T00:00:00</StartDate><StartDay xsi:nil="true" /><Duration>2</Duration><RateBrutto>EU</RateBrutto><Brutto>34</Brutto><ServiceClassID>0</ServiceClassID><TouristCount>2</TouristCount><ID>3199711</ID><Status><Name>HTL::Bansko/Vien Guest house/DBL/2Ad/Standard/BB</Name><ID>2</ID></Status><Hotel><Name>Vien Guest house</Name><ID>5169</ID><Description>3*  (BULGARIA\Bansko\Bansko)</Description><Code>VIE1</Code><City><Name>Bansko</Name><ID>9</ID><Code>BAN</Code><Country><Name>BULGARIA</Name><ID>4</ID><IsIncoming>true</IsIncoming></Country><CountryID>4</CountryID><RegionID>21</RegionID></City><RegionID>21</RegionID><PriceType>None</PriceType><CountCosts xsi:nil="true" /><CityID>9</CityID><HotelCategoryID>4</HotelCategoryID></Hotel><Room><RoomType><Name /><ID>56</ID><Code>DBL</Code><Places>2</Places><ExPlaces>4</ExPlaces><PrintOrder>1</PrintOrder></RoomType><RoomTypeID>56</RoomTypeID><RoomCategory><Name>Standard</Name><ID>44</ID><MainPlaces>0</MainPlaces><ExtraPlaces>0</ExtraPlaces><IsMain>false</IsMain></RoomCategory><RoomCategoryID>44</RoomCategoryID><RoomAccomodation><Name>2Ad</Name><ID>2220</ID><PerRoom>false</PerRoom><AdultMainPalces>0</AdultMainPalces><ChildMainPalces>0</ChildMainPalces><AdultExtraPalces>0</AdultExtraPalces><ChildExtraPalces>0</ChildExtraPalces><MainPlaces>2</MainPlaces><ExtraPlaces>0</ExtraPlaces><IsMain>true</IsMain><AgeFrom>0</AgeFrom><AgeTo>0</AgeTo><Age2From>0</Age2From><Age2To>0</Age2To></RoomAccomodation><RoomAccomodationID>2220</RoomAccomodationID><ID>0</ID><Name /></Room><RoomID>0</RoomID><PansionID>23</PansionID></Service><Service xsi:type="ExtraService"><ExternalID xsi:nil="true" /><Price>0</Price><NMen>1</NMen><PartnerID>6410</PartnerID><Quota>NotChecked</Quota><PacketKey>0</PacketKey><AdditionalParams><ParameterPair Key="HotelDlKey"><Value xsi:type="xsd:int">3199711</Value></ParameterPair><ParameterPair Key="ContractPrKey"><Value xsi:type="xsd:int">2659</Value></ParameterPair></AdditionalParams><DetailBrutto>(0.00(0-999)[Ord_S_2024_per stay] * 2 pax * 2 days) = 0.00</DetailBrutto><Notes /><Name>EX::representative service_mountain/Vien Guest house/DBL/2Ad/Standard (Hard link) (Bansko)</Name><StartDate>2024-06-01T00:00:00</StartDate><StartDay xsi:nil="true" /><Duration>2</Duration><RateBrutto>EU</RateBrutto><Brutto>0</Brutto><ServiceClassID>79</ServiceClassID><TouristCount>2</TouristCount><ID>3199709</ID><Status><Name>EX::representative service_mountain/Vien Guest house/DBL/2Ad/Standard (Hard link) (Bansko)</Name><ID>2</ID></Status><CityKey>9</CityKey><IsPackage>false</IsPackage><Code>2329</Code><HasDuration>false</HasDuration></Service><Service xsi:type="ExtraService"><ExternalID xsi:nil="true" /><Price>0</Price><NMen>1</NMen><PartnerID>6410</PartnerID><Quota>NotChecked</Quota><PacketKey>0</PacketKey><AdditionalParams><ParameterPair Key="HotelDlKey"><Value xsi:type="xsd:int">3199711</Value></ParameterPair><ParameterPair Key="ContractPrKey"><Value xsi:type="xsd:int">2695</Value></ParameterPair></AdditionalParams><DetailBrutto>(0.00(0-999)[Ord_S_2024_per day] * 2 pax * 2 days) = 0.00</DetailBrutto><Notes /><Name>EX::communal fee_mountain/Vien Guest house/DBL/2Ad/Standard (Hard link) (Bansko)</Name><StartDate>2024-06-01T00:00:00</StartDate><StartDay xsi:nil="true" /><Duration>2</Duration><RateBrutto>EU</RateBrutto><Brutto>0</Brutto><ServiceClassID>96</ServiceClassID><TouristCount>2</TouristCount><ID>3199710</ID><Status><Name>EX::communal fee_mountain/Vien Guest house/DBL/2Ad/Standard (Hard link) (Bansko)</Name><ID>2</ID></Status><CityKey>9</CityKey><IsPackage>false</IsPackage><Code>2403</Code><HasDuration>false</HasDuration></Service><Service xsi:type="ExtraService"><ExternalID xsi:nil="true" /><Price>1.2</Price><NMen>1</NMen>
                                            <PartnerID>6410</PartnerID><Quota>NotChecked</Quota><PacketKey>0</PacketKey><AdditionalParams><ParameterPair Key="HotelDlKey"><Value xsi:type="xsd:int">3199711</Value></ParameterPair><ParameterPair Key="ContractPrKey"><Value xsi:type="xsd:int">2642</Value></ParameterPair></AdditionalParams><DetailBrutto>(0.00(0-999)[Ord_S_2024_per day] * 2 pax * 2 days) = 0.00</DetailBrutto><Notes />
                                            <Name>EX::handling fee_mountain/Vien Guest house/DBL/2Ad/Standard (Hard link) (Bansko)</Name><StartDate>2024-06-01T00:00:00</StartDate><StartDay xsi:nil="true" /><Duration>2</Duration><RateBrutto>EU</RateBrutto><Brutto>1.2</Brutto><ServiceClassID>77</ServiceClassID><TouristCount>2</TouristCount><ID>3199708</ID><Status><Name>EX::handling fee_mountain/Vien Guest house/DBL/2Ad/Standard (Hard link) (Bansko)</Name><ID>2</ID></Status><CityKey>9</CityKey><IsPackage>false</IsPackage><Code>2327</Code><HasDuration>false</HasDuration></Service></Services><ID>-1</ID><Name>2191910</Name><Brutto>82.4</Brutto>
                                            <CountryID>4</CountryID><CityID>9</CityID><PartnerID>6410</PartnerID><Status>Confirmed</Status><StartDate>2024-06-01T00:00:00</StartDate><EndDate>2024-06-02T00:00:00</EndDate><Duration>1</Duration><CreationDate>2024-01-29T12:49:10.963</CreationDate><CreatorID>8</CreatorID><Tourists><Tourist Sex="Male" BirthDate="2000-01-01T00:00:00" FirstNameLat="TestF" SurNameLat="TestL" AgeType="Adult" IsMain="false" ID="1917396" Phone="" Email=""><ForeignPassport Serie="" Number="" EndDate="0001-01-01T00:00:00" /><ExternalID>0</ExternalID></Tourist><Tourist Sex="Male" BirthDate="2023-01-28T00:00:00" FirstNameLat="Test2Fn" SurNameLat="Test2Ln" AgeType="Adult" IsMain="false" ID="1917397" Phone="" Email=""><ForeignPassport Serie="" Number="" EndDate="0001-01-01T00:00:00" /><ExternalID>0</ExternalID></Tourist></Tourists><OwnerID xsi:nil="true" /><TourOperatorID>-1</TourOperatorID><TourOperatorCode /><ExternalID>582237</ExternalID><AdditionalParams><ParameterPair Key="IsIntegrationServiceReservation"><Value xsi:type="xsd:boolean">true</Value></ParameterPair><ParameterPair Key="ReservationCost"><Value xsi:type="xsd:double">35.2</Value></ParameterPair></AdditionalParams>
                    </CreateReservationResult>
                    </CreateReservationResponse>
                </soap:Body>
            </soap:Envelope>'
            )
        );

        $mockRatesResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body><GetRatesResponse xmlns="http://www.megatec.ru/">
                        <GetRatesResult>
                            <Rate><Name>Euro</Name><ID>1</ID><Code>EU</Code><Unicode>EUR</Unicode><IsMain>false</IsMain><IsNational>false</IsNational></Rate>
                        </GetRatesResult>
                    </GetRatesResponse>
                </soap:Body></soap:Envelope>'
                )
            );

        $invokedCount = $this->exactly(3);

        $mockResponses = [
            $mockTokenResponse,
            $mockReservationResponse,
            $mockRatesResponse
        ];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
                if ($invokedCount->numberOfInvocations() === 3) {
                    return $mockResponses[2];
                }
            });

        $body = json_encode([
            'to' => [
                'Handle' => 'test',
                'ApiUsername' => 'test',
                'ApiPassword' => 'test',
                'ApiUrl' => 'test'
            ],
            'args' => [[
                'Items' => [
                    [
                        'Hotel' => [
                            'InTourOperatorId' => 'ABC1'
                        ],
                        'Offer_InitialData' => '65',
                        'Room_Type_InTourOperatorId' => '1',
                        'Offer_roomCombinationId' => '1',
                        'roomCombinationId' => '2220',
                        'Offer_bookingDataJson' => '{"tariffId":"0","roomCategoryKey":"65","roomAccomodationKey":"2220", "bookingPrice" : 82.4}',
                        //'Offer_bookingPrice' => '82.4',
                        'Board_Def_InTourOperatorId' => 1,
                        'Offer_Days' => 1,
                        'Room_CheckinAfter' => '2022-01-02',
                        'Room_CheckinBefore' => '2022-01-02',
                        'Passengers' => [[
                            'Firstname' => 'Test1',
                            'Lastname' => 'Test2',
                            'IsAdult' => 1,
                            'Gender' => 'male',
                            'BirthDate' => '2000-01-01'
                        ]]
                    ]
                ],
                'Params' => [
                    'Adults' => [1]
                ]
            ]]
        ]);
        $this->serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);

        $resp = $service->apiDoBooking();

        $this->assertTrue(count($resp) > 0);
    }
    
    public function test_testConnection(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);

        $mockTokenResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <ConnectResponse xmlns="http://www.megatec.ru/">
                            <ConnectResult>a2e173c7-e984-46f8-8144-a05221e0a4d1</ConnectResult>
                        </ConnectResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $invokedCount = exactly(1);

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockTokenResponse) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockTokenResponse;
                }
            });

        $body = json_encode([
            'to' => [
                'Handle' => 'test',
                'ApiUsername' => 'test',
                'ApiPassword' => 'test',
                'ApiUrl' => 'test'
            ]
        ]);
        $this->serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);

        $resp = $service->apiTestConnection();

        $this->assertTrue($resp);

    }

    public function test_apiGetOfferCancelFees(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);

        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockPolicyResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <ConnectResponse xmlns="http://www.megatec.ru/">
                            <ConnectResult>a2e173c7-e984-46f8-8144-a05221e0a4d1</ConnectResult>
                        </ConnectResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $mockPolicyResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetCancellationPolicyInfoWithPenaltyResponse xmlns="http://www.megatec.ru/">
                            <GetCancellationPolicyInfoWithPenaltyResult Message="Ok" Count="0">
                                <Data>
                                    <CancellationPolicyInfoWithPenalty>
                                        <HotelKey>2832</HotelKey>
                                        <HotelName>Nessebar Fort Club Apartmets_FORT NokS (Sunny Beach) 3*</HotelName>
                                        <AccomodatioKey>2220</AccomodatioKey>
                                        <AccomodatioName>2Ad</AccomodatioName>
                                        <RoomTypeKey>58</RoomTypeKey>
                                        <RoomTypeName>APT</RoomTypeName>
                                        <RoomCategoryKey>65</RoomCategoryKey>
                                        <RoomCategoryName>1 bedroom APPART</RoomCategoryName>
                                        <PansionKey>29</PansionKey>
                                        <PansionName>NM</PansionName>
                                        <PolicyDescripion>
                                            <string>If canceled in period 14.04.2025 - 18.06.2025, the penalty will be 100.00 % of the cost of accommodation. Penalty value is 277.02 EU</string>
                                            <string>If canceled from 18.06.2025, the penalty will be 100.00 % of the cost of accommodation. Penalty value is 277.02 EU</string>
                                        </PolicyDescripion>
                                        <CancellationDate>2025-04-14T00:00:00+03:00</CancellationDate>
                                        <PolicyData>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23508</PolicyKey>
                                                <DateFrom>2025-04-14T00:00:00+03:00</DateFrom>
                                                <DateTo>2025-06-18T23:59:59</DateTo>
                                                <PenaltyValue>100</PenaltyValue>
                                                <IsPercent>true</IsPercent>
                                                <TariffId>1993</TariffId>
                                                <TariffName>non refundable</TariffName>
                                                <PenaltyTotal>277.02</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23508</PolicyKey>
                                                <DateFrom>2025-06-18T00:00:00</DateFrom>
                                                <DateTo xsi:nil="true" />
                                                <PenaltyValue>100</PenaltyValue>
                                                <IsPercent>true</IsPercent>
                                                <TariffId>1993</TariffId>
                                                <TariffName>non refundable</TariffName>
                                                <PenaltyTotal>277.02</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                        </PolicyData>
                                    </CancellationPolicyInfoWithPenalty>
                                    <CancellationPolicyInfoWithPenalty>
                                        <HotelKey>2832</HotelKey>
                                        <HotelName>Nessebar Fort Club Apartmets_FORT NokS (Sunny Beach) 3*</HotelName>
                                        <AccomodatioKey>2220</AccomodatioKey>
                                        <AccomodatioName>2Ad</AccomodatioName>
                                        <RoomTypeKey>58</RoomTypeKey>
                                        <RoomTypeName>APT</RoomTypeName>
                                        <RoomCategoryKey>65</RoomCategoryKey>
                                        <RoomCategoryName>1 bedroom APPART</RoomCategoryName>
                                        <PansionKey>29</PansionKey>
                                        <PansionName>NM</PansionName>
                                        <PolicyDescripion>
                                            <string>If canceled before 14.06.2025, no penalty</string>
                                            <string>If canceled in period 15.06.2025 - 17.06.2025, the penalty will be 2 night(s). Penalty value is 97.20 EU</string>
                                            <string>If canceled from 18.06.2025, the penalty will be 2 night(s). Penalty value is 97.20 EU</string>
                                        </PolicyDescripion>
                                        <CancellationDate>2025-04-14T00:00:00+03:00</CancellationDate>
                                        <PolicyData>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>-1</PolicyKey>
                                                <DateFrom xsi:nil="true" />
                                                <DateTo>2025-06-14T23:59:59</DateTo>
                                                <PenaltyValue>0</PenaltyValue>
                                                <IsPercent>false</IsPercent>
                                                <TariffId>0</TariffId>
                                                <TariffName>Ordinary</TariffName>
                                                <PenaltyTotal>0</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23506</PolicyKey>
                                                <DateFrom>2025-06-15T00:00:00</DateFrom>
                                                <DateTo>2025-06-17T23:59:59</DateTo>
                                                <PenaltyValue>2</PenaltyValue>
                                                <IsPercent>false</IsPercent>
                                                <TariffId>0</TariffId>
                                                <TariffName>Ordinary</TariffName>
                                                <PenaltyTotal>97.2</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23507</PolicyKey>
                                                <DateFrom>2025-06-18T00:00:00</DateFrom>
                                                <DateTo xsi:nil="true" />
                                                <PenaltyValue>2</PenaltyValue>
                                                <IsPercent>false</IsPercent>
                                                <TariffId>0</TariffId>
                                                <TariffName>Ordinary</TariffName>
                                                <PenaltyTotal>97.2</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                        </PolicyData>
                                    </CancellationPolicyInfoWithPenalty>
                                </Data>
                            </GetCancellationPolicyInfoWithPenaltyResult>
                        </GetCancellationPolicyInfoWithPenaltyResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $invokedCount = exactly(2);

        $mockResponses = [$mockTokenResponse, $mockPolicyResponse];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
            });
        
        $body = json_encode([
            'to' => [
                'Handle' => 'test',
                'ApiUsername' => 'test',
                'ApiPassword' => 'test',
                'ApiUrl' => 'test'
            ],
            'args' => [[
                'CheckIn' => '2025-06-18',
                'CheckOut' => '2025-06-24',
                'Hotel' => [
                    'InTourOperatorId' => 1
                ],
                'SuppliedPrice' => 100,
                'Duration' => 6,
                'OriginalOffer' => [
                    'Rooms' => [[
                        'Id' => 1
                    ]],
                    'MealItem' => [
                        'Merch' => [
                            'Id' => 1
                        ]
                    ],
                    'bookingDataJson' => '{"tariffId":"0","roomCategoryKey":"65","roomAccomodationKey":"2220"}'
                ],
                'Rooms' => [
                    [
                        'adults' => 2,
                        'children' => 1,
                        'childrenAges' => [0]
                    ]
                ]
            ]]
        ]);
        $this->serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);

        $resp = $service->apiGetOfferCancelFees();

        $this->assertTrue(count($resp) > 0);
    }

    public function test_apiGetOfferPaymentPlans(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);

        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockPolicyResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope 
                    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <ConnectResponse xmlns="http://www.megatec.ru/">
                            <ConnectResult>a2e173c7-e984-46f8-8144-a05221e0a4d1</ConnectResult>
                        </ConnectResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $mockPolicyResponse
            ->method('getBody')
            ->willReturn(
                new Stream(
                    '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <soap:Body>
                        <GetCancellationPolicyInfoWithPenaltyResponse xmlns="http://www.megatec.ru/">
                            <GetCancellationPolicyInfoWithPenaltyResult Message="Ok" Count="0">
                                <Data>
                                    <CancellationPolicyInfoWithPenalty>
                                        <HotelKey>2832</HotelKey>
                                        <HotelName>Nessebar Fort Club Apartmets_FORT NokS (Sunny Beach) 3*</HotelName>
                                        <AccomodatioKey>2220</AccomodatioKey>
                                        <AccomodatioName>2Ad</AccomodatioName>
                                        <RoomTypeKey>58</RoomTypeKey>
                                        <RoomTypeName>APT</RoomTypeName>
                                        <RoomCategoryKey>65</RoomCategoryKey>
                                        <RoomCategoryName>1 bedroom APPART</RoomCategoryName>
                                        <PansionKey>29</PansionKey>
                                        <PansionName>NM</PansionName>
                                        <PolicyDescripion>
                                            <string>If canceled in period 14.04.2025 - 18.06.2025, the penalty will be 100.00 % of the cost of accommodation. Penalty value is 277.02 EU</string>
                                            <string>If canceled from 18.06.2025, the penalty will be 100.00 % of the cost of accommodation. Penalty value is 277.02 EU</string>
                                        </PolicyDescripion>
                                        <CancellationDate>2025-04-14T00:00:00+03:00</CancellationDate>
                                        <PolicyData>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23508</PolicyKey>
                                                <DateFrom>2025-04-14T00:00:00+03:00</DateFrom>
                                                <DateTo>2025-06-18T23:59:59</DateTo>
                                                <PenaltyValue>100</PenaltyValue>
                                                <IsPercent>true</IsPercent>
                                                <TariffId>1993</TariffId>
                                                <TariffName>non refundable</TariffName>
                                                <PenaltyTotal>277.02</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23508</PolicyKey>
                                                <DateFrom>2025-06-18T00:00:00</DateFrom>
                                                <DateTo xsi:nil="true" />
                                                <PenaltyValue>100</PenaltyValue>
                                                <IsPercent>true</IsPercent>
                                                <TariffId>1993</TariffId>
                                                <TariffName>non refundable</TariffName>
                                                <PenaltyTotal>277.02</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                        </PolicyData>
                                    </CancellationPolicyInfoWithPenalty>
                                    <CancellationPolicyInfoWithPenalty>
                                        <HotelKey>2832</HotelKey>
                                        <HotelName>Nessebar Fort Club Apartmets_FORT NokS (Sunny Beach) 3*</HotelName>
                                        <AccomodatioKey>2220</AccomodatioKey>
                                        <AccomodatioName>2Ad</AccomodatioName>
                                        <RoomTypeKey>58</RoomTypeKey>
                                        <RoomTypeName>APT</RoomTypeName>
                                        <RoomCategoryKey>65</RoomCategoryKey>
                                        <RoomCategoryName>1 bedroom APPART</RoomCategoryName>
                                        <PansionKey>29</PansionKey>
                                        <PansionName>NM</PansionName>
                                        <PolicyDescripion>
                                            <string>If canceled before 14.06.2025, no penalty</string>
                                            <string>If canceled in period 15.06.2025 - 17.06.2025, the penalty will be 2 night(s). Penalty value is 97.20 EU</string>
                                            <string>If canceled from 18.06.2025, the penalty will be 2 night(s). Penalty value is 97.20 EU</string>
                                        </PolicyDescripion>
                                        <CancellationDate>2025-04-14T00:00:00+03:00</CancellationDate>
                                        <PolicyData>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>-1</PolicyKey>
                                                <DateFrom xsi:nil="true" />
                                                <DateTo>2025-06-14T23:59:59</DateTo>
                                                <PenaltyValue>0</PenaltyValue>
                                                <IsPercent>false</IsPercent>
                                                <TariffId>0</TariffId>
                                                <TariffName>Ordinary</TariffName>
                                                <PenaltyTotal>0</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23506</PolicyKey>
                                                <DateFrom>2025-06-15T00:00:00</DateFrom>
                                                <DateTo>2025-06-17T23:59:59</DateTo>
                                                <PenaltyValue>2</PenaltyValue>
                                                <IsPercent>false</IsPercent>
                                                <TariffId>0</TariffId>
                                                <TariffName>Ordinary</TariffName>
                                                <PenaltyTotal>97.2</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                            <CancellationPolicyWithPenaltyValue>
                                                <PolicyKey>23507</PolicyKey>
                                                <DateFrom>2025-06-18T00:00:00</DateFrom>
                                                <DateTo xsi:nil="true" />
                                                <PenaltyValue>2</PenaltyValue>
                                                <IsPercent>false</IsPercent>
                                                <TariffId>0</TariffId>
                                                <TariffName>Ordinary</TariffName>
                                                <PenaltyTotal>97.2</PenaltyTotal>
                                                <Currency>EU</Currency>
                                            </CancellationPolicyWithPenaltyValue>
                                        </PolicyData>
                                    </CancellationPolicyInfoWithPenalty>
                                </Data>
                            </GetCancellationPolicyInfoWithPenaltyResult>
                        </GetCancellationPolicyInfoWithPenaltyResponse>
                    </soap:Body>
                </soap:Envelope>'
            )
        );

        $invokedCount = exactly(2);

        $mockResponses = [$mockTokenResponse, $mockPolicyResponse];

        $mockHttpClient
            ->expects($invokedCount)
            ->method('request')
            ->willReturnCallback(function ($method, $uri) use ($invokedCount, $mockResponses) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    return $mockResponses[0];
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    return $mockResponses[1];
                }
            });
        
        $body = json_encode([
            'to' => [
                'Handle' => 'test',
                'ApiUsername' => 'test',
                'ApiPassword' => 'test',
                'ApiUrl' => 'test'
            ],
            'args' => [[
                'CheckIn' => '2025-06-18',
                'CheckOut' => '2025-06-24',
                'Hotel' => [
                    'InTourOperatorId' => 1
                ],
                'SuppliedPrice' => 100,
                'Duration' => 6,
                'OriginalOffer' => [
                    'Rooms' => [[
                        'Id' => 1
                    ]],
                    'MealItem' => [
                        'Merch' => [
                            'Id' => 1
                        ]
                    ],
                    'bookingDataJson' => '{"tariffId":"0","roomCategoryKey":"65","roomAccomodationKey":"2220"}'
                ],
                'Rooms' => [
                    [
                        'adults' => 2,
                        'children' => 1,
                        'childrenAges' => [0]
                    ]
                ]
            ]]
        ]);
        $this->serverRequest = new ServerRequest(Request::METHOD_POST, new Uri('test'), new Stream($body));

        $service = new MegatecApiService($this->serverRequest, $mockHttpClient);

        $resp = $service->apiGetOfferPaymentsPlan();

        $this->assertTrue(count($resp) > 0);
    }
}
