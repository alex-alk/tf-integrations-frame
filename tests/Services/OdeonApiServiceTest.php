<?php

namespace Tests;

use HttpClient\HttpClient;
use HttpClient\Message\Request;
use HttpClient\Message\Stream;
use HttpClient\Message\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RequestHandler\ServerRequest;
use Services\Odeon\OdeonApiService;
use Utils\Utils;

use function PHPUnit\Framework\exactly;

class OdeonApiServiceTest extends TestCase
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
    
    public function test_getCountries(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockGeographyResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "Details": "{\"UserID\":19491,\"Username\":\"Integration\",\"Name\":\"Integration\",\"Email\":\"Integration\",\"UserTypes\":[{\"ID\":21,\"Name\":\"B2B\",\"AppID\":4},{\"ID\":20,\"Name\":\"INTEGRATION\",\"AppID\":4}],\"Language\":2,\"UserToken\":\"fcb34454-c935-4241-acf3-6214b1d3704d\",\"IP\":\"82.78.175.39\",\"AppUserID\":3220,\"UserHotel\":[],\"PasswordUpdatedOn\":null}",
                "Error": false,
                "Response": "SUCCESS",
                "Token": "fcb34454-c935-4241-acf3-6214b1d3704d",
                "ValidationError": false
                }'))
            ;

        $mockGeographyResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "Details": "[{\"CountryID\":1,\"CountryName\":\"Turkey\",\"CountryLName\":\"Turcia\",\"RegionID\":15014,\"RegionName\":\"Afyon\",\"RegionLName\":\"Afyon\",\"AreaID\":2,\"AreaName\":\"Afyon\",\"AreaLName\":\"Afyon\",\"PlaceID\":1,\"PlaceName\":\"Afyon\",\"PlaceLName\":\"Afyon\"}, {\"CountryID\":10,\"CountryName\":\"Romania\",\"CountryLName\":\"Romania\",\"RegionID\":5,\"RegionName\":\"Buc\",\"RegionLName\":\"Buc\",\"AreaID\":9254,\"AreaName\":\"buc\",\"AreaLName\":\"Buc\",\"PlaceID\":100,\"PlaceName\":\"Bucuresti\",\"PlaceLName\":\"Bucuresti\"}, {\"CountryID\":1,\"CountryName\":\"Turkey\",\"CountryLName\":\"Turcia\",\"RegionID\":15014,\"RegionName\":\"Antalya\",\"RegionLName\":\"Antalya\",\"AreaID\":5,\"AreaName\":\"Antalya\",\"AreaLName\":\"Antalya\",\"PlaceID\":79,\"PlaceName\":\"Antalya\",\"PlaceLName\":\"Antalya\"}]",
                "Error": false,
                "Response": "SUCCESS",
                "Token": "fcb34454-c935-4241-acf3-6214b1d3704d",
                "ValidationError": false
                }'))
            ;

        $mockResponses = [$mockTokenResponse, $mockGeographyResponse];

        $invokedCount = exactly(2);
        
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

        $service = new OdeonApiService($this->serverRequest, $mockHttpClient);
        $data = $service->apiGetCountries();

        $this->assertTrue(count($data) > 0);
    }

    public function test_getCities(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockGeographyResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "Details": "{\"UserID\":19491,\"Username\":\"Integration\",\"Name\":\"Integration\",\"Email\":\"Integration\",\"UserTypes\":[{\"ID\":21,\"Name\":\"B2B\",\"AppID\":4},{\"ID\":20,\"Name\":\"INTEGRATION\",\"AppID\":4}],\"Language\":2,\"UserToken\":\"fcb34454-c935-4241-acf3-6214b1d3704d\",\"IP\":\"82.78.175.39\",\"AppUserID\":3220,\"UserHotel\":[],\"PasswordUpdatedOn\":null}",
                "Error": false,
                "Response": "SUCCESS",
                "Token": "fcb34454-c935-4241-acf3-6214b1d3704d",
                "ValidationError": false
                }'))
            ;

        $mockGeographyResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "Details": "[{\"CountryID\":1,\"CountryName\":\"Turkey\",\"CountryLName\":\"Turcia\",\"RegionID\":15014,\"RegionName\":\"Afyon\",\"RegionLName\":\"Afyon\",\"AreaID\":2,\"AreaName\":\"Afyon\",\"AreaLName\":\"Afyon\",\"PlaceID\":1,\"PlaceName\":\"Afyon\",\"PlaceLName\":\"Afyon\"}, {\"CountryID\":10,\"CountryName\":\"Romania\",\"CountryLName\":\"Romania\",\"RegionID\":5,\"RegionName\":\"Buc\",\"RegionLName\":\"Buc\",\"AreaID\":9254,\"AreaName\":\"buc\",\"AreaLName\":\"Buc\",\"PlaceID\":100,\"PlaceName\":\"Bucuresti\",\"PlaceLName\":\"Bucuresti\"}, {\"CountryID\":1,\"CountryName\":\"Turkey\",\"CountryLName\":\"Turcia\",\"RegionID\":15014,\"RegionName\":\"Antalya\",\"RegionLName\":\"Antalya\",\"AreaID\":5,\"AreaName\":\"Antalya\",\"AreaLName\":\"Antalya\",\"PlaceID\":79,\"PlaceName\":\"Antalya\",\"PlaceLName\":\"Antalya\"}]",
                "Error": false,
                "Response": "SUCCESS",
                "Token": "fcb34454-c935-4241-acf3-6214b1d3704d",
                "ValidationError": false
                }'))
            ;

        $mockResponses = [$mockTokenResponse, $mockGeographyResponse];

        $invokedCount = exactly(2);
        
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

        $service = new OdeonApiService($this->serverRequest, $mockHttpClient);
        $data = $service->apiGetRegions();

        $this->assertTrue(count($data) > 0);
    }

    public function test_getRegions(): void
    {
        $mockHttpClient = $this->createMock(HttpClient::class);
        $mockTokenResponse = $this->createMock(ResponseInterface::class);
        $mockGeographyResponse = $this->createMock(ResponseInterface::class);

        $mockTokenResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "Details": "{\"UserID\":19491,\"Username\":\"Integration\",\"Name\":\"Integration\",\"Email\":\"Integration\",\"UserTypes\":[{\"ID\":21,\"Name\":\"B2B\",\"AppID\":4},{\"ID\":20,\"Name\":\"INTEGRATION\",\"AppID\":4}],\"Language\":2,\"UserToken\":\"fcb34454-c935-4241-acf3-6214b1d3704d\",\"IP\":\"82.78.175.39\",\"AppUserID\":3220,\"UserHotel\":[],\"PasswordUpdatedOn\":null}",
                "Error": false,
                "Response": "SUCCESS",
                "Token": "fcb34454-c935-4241-acf3-6214b1d3704d",
                "ValidationError": false
                }'))
            ;

        $mockGeographyResponse
            ->method('getBody')
            ->willReturn(new Stream('{
                "Details": "[{\"CountryID\":1,\"CountryName\":\"Turkey\",\"CountryLName\":\"Turcia\",\"RegionID\":15014,\"RegionName\":\"Afyon\",\"RegionLName\":\"Afyon\",\"AreaID\":2,\"AreaName\":\"Afyon\",\"AreaLName\":\"Afyon\",\"PlaceID\":1,\"PlaceName\":\"Afyon\",\"PlaceLName\":\"Afyon\"}, {\"CountryID\":10,\"CountryName\":\"Romania\",\"CountryLName\":\"Romania\",\"RegionID\":5,\"RegionName\":\"Buc\",\"RegionLName\":\"Buc\",\"AreaID\":9254,\"AreaName\":\"buc\",\"AreaLName\":\"Buc\",\"PlaceID\":100,\"PlaceName\":\"Bucuresti\",\"PlaceLName\":\"Bucuresti\"}, {\"CountryID\":1,\"CountryName\":\"Turkey\",\"CountryLName\":\"Turcia\",\"RegionID\":15014,\"RegionName\":\"Antalya\",\"RegionLName\":\"Antalya\",\"AreaID\":5,\"AreaName\":\"Antalya\",\"AreaLName\":\"Antalya\",\"PlaceID\":79,\"PlaceName\":\"Antalya\",\"PlaceLName\":\"Antalya\"}]",
                "Error": false,
                "Response": "SUCCESS",
                "Token": "fcb34454-c935-4241-acf3-6214b1d3704d",
                "ValidationError": false
                }'))
            ;

        $mockResponses = [$mockTokenResponse, $mockGeographyResponse];

        $invokedCount = exactly(2);
        
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

        $service = new OdeonApiService($this->serverRequest, $mockHttpClient);
        $data = $service->apiGetCities();

        $this->assertTrue(count($data) > 0);
    }
 /*
 
    public function test_getHotels_whenInputIsValid_receiveHotels(): void
    {
        self::$body['method'] = self::$api_getHotels;
        self::$options['body'] = json_encode(self::$body);

        $response = self::$httpClient->request(HttpClient::METHOD_POST, self::$proxyUrl, self::$options);
        $content = $response->getContent();
        $this->assertJsonStringEqualsJsonString($content, 
            '{
                "response": {
                    "2": {
                        "Id": "2",
                        "Name": "MOVENPICK RESORT & SPA EL GOUNA",
                        "Stars": 5,
                        "WebAddress": "www.movenpick-elgouna.com",
                        "Address": {
                            "Latitude": "0",
                            "Longitude": "0",
                            "Details": "El Gouna - Hurghada - Egypt",
                            "City": {
                                "Id": "1",
                                "Name": "Afyon",
                                "Country": {
                                    "Id": "1",
                                    "Code": "TR",
                                    "Name": "Turcia"
                                },
                                "County": {
                                    "Id": "2",
                                    "Name": "Afyon",
                                    "Country": {
                                        "Id": "1",
                                        "Code": "TR",
                                        "Name": "Turcia"
                                    }
                                }
                            }
                        }
                    }
                }
            }'
        );
    }

    public function test_apiGetHotelDetails_whenInputIsValid_receiveHotelDetails(): void
    {
        self::$body['method'] = self::$api_getHotelDetails;
        self::$body['args'] = [
            [
                'HotelId' => 2
            ]
        ];
        self::$options['body'] = json_encode(self::$body);
        
        $response = self::$httpClient->request(HttpClient::METHOD_POST, self::$proxyUrl, self::$options);
        $content = $response->getContent();
        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": {
                    "Id": "2",
                    "Name": "MOVENPICK RESORT & SPA EL GOUNA",
                    "Stars": 5,
                    "WebAddress": "http:s://movenpick.com/el-gouna",
                    "Content": {
                        "Content": "<p><b>Concept</b><br>Salon spa, Golf, Familie, Stimulent , Sporturi</p><p><b>Categorie</b><br>Four Star</p><p><b>Registrul oficial</b><br>4 stele</p><p><b>Certificate de hotel</b><br>Green Globe 21, Green Star Hotel Certificate â€“ Egypt</p><p><b>Număr de camere pentru persoane cu dezabilități</b><br>15</p><p><b>Numărul camerelor pentru nefumători</b><br>153</p><p><b>Anul de construcție</b><br>1996</p><p><b>Anul ultimei renovări</b><br>2010</p><p><b>Cod poștal</b><br>84513</p><p><b>Telefon</b><br>+(20) 0653544501</p><p><b>Fax</b><br>+(20) 0653545160</p><p><b>E-mail</b><br>resort.elgouna@movenpick.com</p><p><b>Pagină web</b><br>http:s://movenpick.com/el-gouna</p><p><b>Facebook</b><br>https://www.facebook.com/moevenpick.elgouna.resort/</p><p><b>Instagram</b><br>https://www.instagram.com/movenpickelgouna/</p><p><b>Latitudine</b><br>27.395045627198307</p><p><b>Longitudine</b><br>33.68519238649139</p><p><b>Oraș</b><br>Hurghada</p><p><b>Suprafața totala a hotelului (m2)</b><br>200000</p>",
                        "ImageGallery": {
                            "Items": [
                                {
                                    "RemoteUrl": "http://localhost/travelfuse-integrations/test-coraltravel/media/image/12/2/636473868271496421.jpg",
                                    "Alt": "Clădirea principala"
                                }
                            ]
                        }
                    },
                    "ContactPerson": {
                        "Email": "http:s://movenpick.com/el-gouna",
                        "Phone": "+(20) 0653544501",
                        "Fax": "+(20) 0653545160"
                    },
                    "Address": {
                        "Latitude": "27.395045627198307",
                        "Longitude": "33.68519238649139",
                        "Details": null,
                        "City": {
                            "Id": "1",
                            "Name": "Afyon",
                            "Country": {
                                "Id": "1",
                                "Code": "TR",
                                "Name": "Turcia"
                            },
                            "County": {
                                "Id": "2",
                                "Name": "Afyon",
                                "Country": {
                                    "Id": "1",
                                    "Code": "TR",
                                    "Name": "Turcia"
                                }
                            }
                        }
                    },
                    "Facilities": []
                }
            }'
        );
    }
    

    public function test_getOffers_whenInputIsValid_receiveOffers(): void
    {
        self::$body['method'] = self::$api_getOffers;
        self::$body['args'] = [
            [
                'checkIn' => '2024-06-18',
                'serviceTypes' => [
                    'charter'
                ],
                'transportTypes' => [
                    'plane'
                ],
                'countryId' => '1',
                'cityId' => '1',
                'departureCity' => '1',
                'rooms' => [
                    ['adults' => 2]
                ],
                'days' => 7
            ]
        ];
        self::$options['body'] = json_encode(self::$body);
        
        $response = self::$httpClient->request(HttpClient::METHOD_POST, self::$proxyUrl, self::$options);
        $content = $response->getContent();
        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": {
                    "36354": {
                        "Id": "36354",
                        "Offers": {
                            "36354~19468-19484~72295~422~2024-06-18~7~1087.9~2": {
                                "Code": "36354~19468-19484~72295~422~2024-06-18~7~1087.9~2",
                                "CheckIn": "2024-06-18",
                                "Currency": {
                                    "Code": "EUR"
                                },
                                "Comission": 0,
                                "InitialPrice": 0,
                                "Gross": 1087.9,
                                "Net": 1087.9,
                                "Availability": "yes",
                                "Days": "7",
                                "Rooms": [
                                    {
                                        "Id": "72295",
                                        "Merch": {
                                            "Id": "72295",
                                            "Title": "STANDARD ROOM",
                                            "Type": {
                                                "Id": "72295",
                                                "Title": "STANDARD ROOM"
                                            },
                                            "Code": "72295",
                                            "Name": "STANDARD ROOM"
                                        },
                                        "CheckinAfter": "2024-06-18",
                                        "CheckinBefore": "2024-06-25",
                                        "Currency": {
                                            "Code": "EUR"
                                        },
                                        "Quantity": 1,
                                        "Availability": "yes"
                                    }
                                ],
                                "Item": {
                                    "Id": "72295",
                                    "Merch": {
                                        "Id": "72295",
                                        "Title": "STANDARD ROOM",
                                        "Type": {
                                            "Id": "72295",
                                            "Title": "STANDARD ROOM"
                                        },
                                        "Code": "72295",
                                        "Name": "STANDARD ROOM"
                                    },
                                    "CheckinAfter": "2024-06-18",
                                    "CheckinBefore": "2024-06-25",
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "Availability": "yes"
                                },
                                "MealItem": {
                                    "Merch": {
                                        "Title": "Room Only",
                                        "Id": "422",
                                        "Type": {
                                            "Id": "422",
                                            "Title": "Room Only"
                                        }
                                    },
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "UnitPrice": 0,
                                    "Gross": 0,
                                    "Net": 0,
                                    "InitialPrice": 0
                                },
                                "DepartureTransportItem": {
                                    "Merch": {
                                        "Title": "Dus: 18.06.2024",
                                        "Category": {
                                            "Code": "other-outbound"
                                        },
                                        "TransportType": "plane",
                                        "From": {
                                            "City": {
                                                "Id": "1",
                                                "Name": "Afyon",
                                                "Country": {
                                                    "Id": "1",
                                                    "Code": "TR",
                                                    "Name": "Turcia"
                                                },
                                                "County": {
                                                    "Id": "2",
                                                    "Name": "Afyon",
                                                    "Country": {
                                                        "Id": "1",
                                                        "Code": "TR",
                                                        "Name": "Turcia"
                                                    }
                                                }
                                            }
                                        },
                                        "To": {
                                            "City": {
                                                "Id": "79",
                                                "Name": "Antalya",
                                                "Country": {
                                                    "Id": "1",
                                                    "Code": "TR",
                                                    "Name": "Turcia"
                                                },
                                                "County": {
                                                    "Id": "5",
                                                    "Name": "Antalya",
                                                    "Country": {
                                                        "Id": "1",
                                                        "Code": "TR",
                                                        "Name": "Turcia"
                                                    }
                                                }
                                            }
                                        },
                                        "DepartureTime": "2024-06-18 13:00",
                                        "ArrivalTime": "2024-06-18 15:00",
                                        "DepartureAirport": "BAY",
                                        "ReturnAirport": "AYT"
                                    },
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "UnitPrice": 0,
                                    "Gross": 0,
                                    "Net": 0,
                                    "InitialPrice": 0,
                                    "DepartureDate": "2024-06-18",
                                    "ArrivalDate": "2024-06-18",
                                    "Return": {
                                        "Merch": {
                                            "Title": "Retur: 25.06.2024",
                                            "Category": {
                                                "Code": "other-inbound"
                                            },
                                            "TransportType": "plane",
                                            "From": {
                                                "City": {
                                                    "Id": "79",
                                                    "Name": "Antalya",
                                                    "Country": {
                                                        "Id": "1",
                                                        "Code": "TR",
                                                        "Name": "Turcia"
                                                    },
                                                    "County": {
                                                        "Id": "5",
                                                        "Name": "Antalya",
                                                        "Country": {
                                                            "Id": "1",
                                                            "Code": "TR",
                                                            "Name": "Turcia"
                                                        }
                                                    }
                                                }
                                            },
                                            "To": {
                                                "City": {
                                                    "Id": "1",
                                                    "Name": "Afyon",
                                                    "Country": {
                                                        "Id": "1",
                                                        "Code": "TR",
                                                        "Name": "Turcia"
                                                    },
                                                    "County": {
                                                        "Id": "2",
                                                        "Name": "Afyon",
                                                        "Country": {
                                                            "Id": "1",
                                                            "Code": "TR",
                                                            "Name": "Turcia"
                                                        }
                                                    }
                                                }
                                            },
                                            "DepartureTime": "2024-06-25 10:00",
                                            "ArrivalTime": "2024-06-25 12:00",
                                            "DepartureAirport": "AYT",
                                            "ReturnAirport": "BAY"
                                        },
                                        "Currency": {
                                            "Code": "EUR"
                                        },
                                        "Quantity": 1,
                                        "UnitPrice": 0,
                                        "Gross": 0,
                                        "Net": 0,
                                        "InitialPrice": 0,
                                        "DepartureDate": "2024-06-25",
                                        "ArrivalDate": "2024-06-25"
                                    }
                                },
                                "ReturnTransportItem": {
                                    "Merch": {
                                        "Title": "Retur: 25.06.2024",
                                        "Category": {
                                            "Code": "other-inbound"
                                        },
                                        "TransportType": "plane",
                                        "From": {
                                            "City": {
                                                "Id": "79",
                                                "Name": "Antalya",
                                                "Country": {
                                                    "Id": "1",
                                                    "Code": "TR",
                                                    "Name": "Turcia"
                                                },
                                                "County": {
                                                    "Id": "5",
                                                    "Name": "Antalya",
                                                    "Country": {
                                                        "Id": "1",
                                                        "Code": "TR",
                                                        "Name": "Turcia"
                                                    }
                                                }
                                            }
                                        },
                                        "To": {
                                            "City": {
                                                "Id": "1",
                                                "Name": "Afyon",
                                                "Country": {
                                                    "Id": "1",
                                                    "Code": "TR",
                                                    "Name": "Turcia"
                                                },
                                                "County": {
                                                    "Id": "2",
                                                    "Name": "Afyon",
                                                    "Country": {
                                                        "Id": "1",
                                                        "Code": "TR",
                                                        "Name": "Turcia"
                                                    }
                                                }
                                            }
                                        },
                                        "DepartureTime": "2024-06-25 10:00",
                                        "ArrivalTime": "2024-06-25 12:00",
                                        "DepartureAirport": "AYT",
                                        "ReturnAirport": "BAY"
                                    },
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "UnitPrice": 0,
                                    "Gross": 0,
                                    "Net": 0,
                                    "InitialPrice": 0,
                                    "DepartureDate": "2024-06-25",
                                    "ArrivalDate": "2024-06-25"
                                },
                                "InitialData": "2",
                                "departureFlightId": "19468",
                                "returnFlightId": "19484",
                                "bookingDataJson": null,
                                "Items": [
                                    {
                                        "Merch": {
                                            "Code": "",
                                            "Category": {
                                                "Id": "6",
                                                "Code": "6"
                                            },
                                            "Title": "Transfer inclus"
                                        },
                                        "Currency": {
                                            "Code": "EUR"
                                        },
                                        "Quantity": 1,
                                        "UnitPrice": 0,
                                        "Availability": "yes",
                                        "Gross": 0,
                                        "Net": 0,
                                        "InitialPrice": 0
                                    },
                                    {
                                        "Merch": {
                                            "Code": "",
                                            "Category": {
                                                "Id": "7s",
                                                "Code": "7s"
                                            },
                                            "Title": "Taxe aeroport"
                                        },
                                        "Currency": {
                                            "Code": "EUR"
                                        },
                                        "Quantity": 1,
                                        "UnitPrice": 0,
                                        "Availability": "yes",
                                        "Gross": 0,
                                        "Net": 0,
                                        "InitialPrice": 0
                                    }
                                ]
                            }
                        }
                    }
                }
            }'
        );
    }

    public function test_getAvailabilityDates_whenInputIsValid_receiveAvailabilityDates(): void
    {
        self::$body['method'] = self::$api_getAvailabilityDates;
        self::$body['args'] = [
            [
                'type' => 'charter'
            ]
        ];
        self::$options['body'] = json_encode(self::$body);

        $response = self::$httpClient->request(HttpClient::METHOD_POST, self::$proxyUrl, self::$options);
        $content = $response->getContent();
        $this->assertJsonStringEqualsJsonString($content, 
            '{
                "response": {
                    "plane~city|79~city|1": {
                        "Id": "plane~city|79~city|1",
                        "Content": {
                            "Active": true
                        },
                        "From": {
                            "City": {
                                "Id": "79",
                                "Name": "Antalya",
                                "Country": {
                                    "Id": "1",
                                    "Code": "TR",
                                    "Name": "Turcia"
                                },
                                "County": {
                                    "Id": "5",
                                    "Name": "Antalya",
                                    "Country": {
                                        "Id": "1",
                                        "Code": "TR",
                                        "Name": "Turcia"
                                    }
                                }
                            }
                        },
                        "To": {
                            "City": {
                                "Id": "1",
                                "Name": "Afyon",
                                "Country": {
                                    "Id": "1",
                                    "Code": "TR",
                                    "Name": "Turcia"
                                },
                                "County": {
                                    "Id": "2",
                                    "Name": "Afyon",
                                    "Country": {
                                        "Id": "1",
                                        "Code": "TR",
                                        "Name": "Turcia"
                                    }
                                }
                            }
                        },
                        "TransportType": "plane",
                        "Dates": {
                            "2024-06-18": {
                                "Date": "2024-06-18",
                                "Nights": {
                                    "7": {
                                        "Nights": 7
                                    }
                                }
                            }
                        }
                    }
                }
            }'
        );
    }


    public function test_bookHotel_whenInputIsValid_receiveBookHotel(): void
    {
        self::$body['method'] = self::$api_doBooking;
        self::$body['args'] = [
            [
                'Items' => [
                    [
                        'Hotel' => [
                            'InTourOperatorId' => 123
                        ],
                        'Offer_departureFlightId' => 2319556,
                        'Offer_returnFlightId' => 2319825,
                        'Room_Type_InTourOperatorId' => 123,
                        'Board_Def_InTourOperatorId' => 123,
                        'Room_CheckinAfter' => '2023-06-11',
                        'Room_CheckinBefore' => '2023-06-21',
                        'Offer_Days' => 1,
                        'Offer_InitialData' => 1,
                        'Passengers' => [
                            [
                                'Firstname' => 'Test1',
                                'Lastname' => 'Test2',
                                'BirthDate' => '2022-01-01',
                                'Gender' => 'male',
                                'IsAdult' => 1
                            ]
                        ]
                    ]
                ],
                'Params' => [
                    'Adults' => [
                        1
                    ]
                ]
            ]
        ];
        self::$options['body'] = json_encode(self::$body);

        $response = self::$httpClient->request(HttpClient::METHOD_POST, self::$proxyUrl, self::$options);
        $content = $response->getContent();
        $this->assertJsonStringEqualsJsonString($content, 
            '{
                "response": [
                    {
                        "Id": "9811058"
                    },
                    "{\"hasPriceChanged\":false,\"newPrice\":0,\"allotmentChanged\":false,\"gdsBookingCode\":null,\"voucher\":9811058,\"totalPrice\":5761.54,\"saleCurrency\":{\"ID\":3,\"Name\":\"EUR\",\"LName\":null,\"SName\":\"EUR\",\"SLName\":\"EUR\"},\"voucherAllotmentStatus\":0}"
                ]
            }'
        );
    }
        */
}
