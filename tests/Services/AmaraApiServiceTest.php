<?php

namespace Tests;

use HttpClient\HttpClient;
use HttpClient\Message\Request;
use HttpClient\Message\Stream;
use HttpClient\Message\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RequestHandler\ServerRequest;
use Services\Amara\AmaraApiService;
use Utils\Utils;

class AmaraApiServiceTest extends TestCase
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
            ->willReturn(new Stream('--uuid:63e2bfdf-b462-475f-8784-870b8335e76c+id=79904
Content-ID: 
<http://tempuri.org/0>
Content-Transfer-Encoding: 8bit
Content-Type: application/xop+xml;charset=utf-8;type="application/soap+xml"


    <s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://www.w3.org/2005/08/addressing">
        <s:Header>
            <a:Action s:mustUnderstand="1">http://tempuri.org/IOffer/GetRoutesInfoResponse</a:Action>
        </s:Header>
        <s:Body>
            <GetRoutesInfoResponse xmlns="http://tempuri.org/">
                <GetRoutesInfoResult xmlns:b="http://schemas.datacontract.org/2004/07/WebAPI.Model" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
                    <b:DestinationHotels xmlns:c="http://schemas.datacontract.org/2004/07/KartagoBL.ExportXML.Oferte">
                        <c:DestinationHotelsInfo>
                            <c:DestinationCode>HRG</c:DestinationCode>
                            <c:Hotels>
                                <c:HotelInfo>
                                    <c:Classification>5*</c:Classification>
                                    <c:CountryName>EGIPT</c:CountryName>
                                    <c:Description>&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt;&lt;span style="background-color: #ffffff;"&gt;Centru Wellness &amp;amp; Spa&lt;/span&gt;&lt;/p&gt;&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt;&lt;span style="background-color: #ffffff;"&gt;Centru de diving&lt;/span&gt;&lt;/p&gt;&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt;&lt;span style="background-color: #ffffff;"&gt;Acces internet Wi-Fi&lt;/span&gt;&lt;/p&gt;</c:Description>
                                    <c:HotelCode>3-XANMAK-MBSSV-H-CLS</c:HotelCode>
                                    <c:HotelName>XANADU MAKADI BAY  </c:HotelName>
                                    <c:Latitude>27.005291</c:Latitude>
                                    <c:Location>MAKADI BAY</c:Location>
                                    <c:Longitude>33.892058</c:Longitude>
                                    <c:MapPath/>
                                    <c:Paragraphs>
                                        <c:ParagraphInfo>
                                            <c:Description>&lt;p style="text-align: left; margin: 0pt;"&gt;Xanadu Makadi Bay este situat&amp;nbsp; în imediata apropiere a numeroaselor atracții istorice și turistice din Hurghada, cu o suprafata totala de 500.000&amp;nbsp;de metri pătrați, hotelul oferă oaspeților servicii de primă clasă, combinând confortul și luxul pentru o experiență de vacanță de neuitat.&lt;/p&gt;&lt;p style="text-align: left; margin: 0pt;"&gt;&amp;nbsp;&lt;/p&gt;&lt;p style="text-align: left; margin: 0pt;"&gt;Datorită arhitecturii sale speciale, Xanadu Makadi Bay își transpune oaspeții într-o atmosferă extraordinară: 8 restaurante, amfiteatru -&amp;nbsp; centrul de divertisment cu animatie pe intreg parcursul zilei, club pentru copii Fancyland cu suprafata de 1.500 de metri pătrați,&amp;nbsp;centru SPA ultramodern,&amp;nbsp;&amp;nbsp;Xanadu Makadi Bay promite o vacanță de neuitat.&lt;/p&gt;&lt;p style="text-align: left; margin: 0pt;"&gt;&lt;br /&gt;&lt;/p&gt;&lt;div&gt;Nota:&lt;/div&gt;&lt;div&gt;Hotelul Xanadu Makadi Bay 5* deschis incepand din 14.04.2022, are parte de un Soft Opening, fiind functionale integral zonele Dune si Lagoon (cea mai pare parte a teritoriului hotelului), situate in apropiere de plaja. &lt;/div&gt;&lt;div&gt;&lt;br /&gt;&lt;/div&gt;&lt;div&gt;Serviciile pt servirea mesei si a bauturilor (High Class All inclusive) sunt disponibile 24H, turistii vor servi mesele principale in restaurantul AGORA, situat in imediata vecinatate a plajei.&lt;/div&gt;&lt;div&gt;&lt;br /&gt;&lt;/div&gt;&lt;div&gt; Lagoon Lounge, barul - restaurant situat langa piscina zonei Lagoon este de asemenea disponibil 24H. Turistii beneficiaza gratuit de 3 restaurante A la Carte: restaurant cu specific italian si unul cu specific mediteraneean (peste si fructe de mare), alaturi de optiunea Breakfast A la Carte.&lt;/div&gt;&lt;div&gt;&lt;br /&gt;&lt;/div&gt;&lt;div&gt;Piscina principala a zonei Lagoon este functionala, in plus turistii beneficiaza de acces gratuit la Aquapark-ul hotelului. Hotelul ofera animatie activa atat pe timpul zilei cat si programe de divertisment de seara.&lt;/div&gt;&lt;div&gt;Kids Club Fancyland isi intampina micii oaspeti pe parcursul zilei, organizand diverse activități educative si distractive, cinematograf si mini discoteca.&lt;/div&gt;&lt;div&gt;      Cladirea principala (Main Building) si facilitatile acesteia vor fi active incepand cu 01.07.2022.&lt;/div&gt;&lt;div&gt;&lt;br /&gt;&lt;/div&gt;&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt; &lt;/p&gt;</c:Description>
                                            <c:Name>Descriere hotel</c:Name>
                                        </c:ParagraphInfo>
                                        <c:ParagraphInfo>
                                            <c:Description>&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt;&lt;span style="background-color: #ffffff;"&gt;Centru Wellness &amp;amp; Spa&lt;/span&gt;&lt;/p&gt;&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt;&lt;span style="background-color: #ffffff;"&gt;Centru de diving&lt;/span&gt;&lt;/p&gt;&lt;p style="text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;"&gt;&lt;span style="background-color: #ffffff;"&gt;Acces internet Wi-Fi&lt;/span&gt;&lt;/p&gt;</c:Description>
                                            <c:Name>Principalele puncte de atracție</c:Name>
                                        </c:ParagraphInfo>
                                    </c:Paragraphs>
                                    <c:PictureResourcesFileNames xmlns:d="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                                        <d:string>2022111112592031 owerview.jpg</d:string>
                                        <d:string>202211111312532 lobby.jpg</d:string>
                                    </c:PictureResourcesFileNames>
                                    <c:UnificationCode>eyJUIjowLCJDIjoiMjAzMjcifQ==</c:UnificationCode>
                                    <c:VideoPath/>
                                </c:HotelInfo>
                            </c:Hotels>
                        </c:DestinationHotelsInfo>
                    </b:DestinationHotels>
                        <b:ResultCode>0</b:ResultCode>
                        <b:ResultMessage>OK</b:ResultMessage>
                    <b:Routes xmlns:c="http://schemas.datacontract.org/2004/07/KartagoBL.ExportXML.Oferte">
                        <c:RouteInfo>
                            <c:Departures>
                                <c:DepartureInfo>
                                    <c:DepartureDate>30.11.2024 11:20</c:DepartureDate>
                                    <c:DepatureArrival>30.11.2024 14:30</c:DepatureArrival>
                                    <c:FromCode>CLJ</c:FromCode>
                                    <c:ReturnArrival>08.12.2024 03:10</c:ReturnArrival>
                                    <c:ReturnDate>07.12.2024 23:40</c:ReturnDate>
                                    <c:ReturnTransportNumber>H4 7614</c:ReturnTransportNumber>
                                    <c:SeasonTransportTimeTableID>153092</c:SeasonTransportTimeTableID>
                                    <c:ToCode>HRG</c:ToCode>
                                    <c:TourPeriod>7</c:TourPeriod>
                                    <c:TransportDuration>190</c:TransportDuration>
                                    <c:TransportNumber>H4 7613</c:TransportNumber>
                                </c:DepartureInfo>
                            </c:Departures>
                            <c:From>CLUJ-NAPOCA</c:From>
                            <c:FromCode>CLJ</c:FromCode>
                            <c:To>HURGHADA</c:To>
                            <c:ToCode>HRG</c:ToCode>
                            <c:ToCountry>EGIPT</c:ToCountry>
                            <c:ToCountryCode>EG</c:ToCountryCode>
                        </c:RouteInfo>
                    </b:Routes>
                </GetRoutesInfoResult>
            </GetRoutesInfoResponse>
        </s:Body>
    </s:Envelope>
--uuid:63e2bfdf-b462-475f-8784-870b8335e76c+id=79904--'
            ));

        $mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $service = new AmaraApiService($this->serverRequest, $mockHttpClient);
        $countries = $service->apiGetCountries();

        $this->assertTrue(count($countries) > 0);
    }

    /*
    public function test_getCities_whenInputIsValid_receiveCities(): void
    {
        self::$body['method'] = self::$api_getCities;
        $content = $this->getResponseJson(self::$body);

        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": {
                    "CLJ": {
                        "Id": "CLJ",
                        "Name": "CLUJ-NAPOCA",
                        "Country": {
                            "Id": "RO",
                            "Code": "RO",
                            "Name": "Romania"
                        }
                    },
                    "HRG": {
                        "Id": "HRG",
                        "Name": "HURGHADA",
                        "Country": {
                            "Id": "EG",
                            "Code": "EG",
                            "Name": "EGIPT"
                        }
                    }
                }
            }'
        );
    }
    
    
    public function test_getHotels_whenInputIsValid_receiveHotels(): void
    {
        self::$body['method'] = self::$api_getHotels;
        $content = $this->getResponseJson(self::$body);

        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": {
                    "eyJUIjowLCJDIjoiMjAzMjcifQ==": {
                        "Id": "eyJUIjowLCJDIjoiMjAzMjcifQ==",
                        "Name": "XANADU MAKADI BAY  ",
                        "Stars": 5,
                        "WebAddress": null,
                        "Content": {
                            "Content": "<p style=\"text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;\"><span style=\"background-color: #ffffff;\">Centru Wellness &amp; Spa</span></p><p style=\"text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;\"><span style=\"background-color: #ffffff;\">Centru de diving</span></p><p style=\"text-align: justify; margin: 0pt 0pt 10pt; line-height: 1.15;\"><span style=\"background-color: #ffffff;\">Acces internet Wi-Fi</span></p>",
                            "ImageGallery": {
                                "Items": [
                                    {
                                        "RemoteUrl": "http://localhost/travelfuse-integrations/Storage/Downloads/localhost-amara_v2/images/eyJUIjowLCJDIjoiMjAzMjcifQ==/2022111112592031 owerview.jpg",
                                        "Alt": null
                                    }
                                ]
                            }
                        },
                        "Address": {
                            "Latitude": "27.005291",
                            "Longitude": "33.892058",
                            "Details": null,
                            "City": {
                                "Id": "HRG",
                                "Name": "HURGHADA",
                                "Country": {
                                    "Id": "EG",
                                    "Code": "EG",
                                    "Name": "EGIPT"
                                }
                            }
                        }
                    }
                }
            }'
        );
    }

    public function test_apiGetAvailabilityDates_whenInputIsValid_receiveData(): void
    {
        self::$body['method'] = self::$api_getAvailabilityDates;
        self::$body['args'] = [
            [
                'type' => 'charter'
            ]
        ];
        $content = $this->getResponseJson(self::$body);

        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": {
                    "plane~city|CLJ~city|HRG": {
                        "Id": "plane~city|CLJ~city|HRG",
                        "Content": {
                            "Active": true
                        },
                        "From": {
                            "City": {
                                "Id": "CLJ",
                                "Name": "CLUJ-NAPOCA",
                                "Country": {
                                    "Id": "RO",
                                    "Code": "RO",
                                    "Name": "Romania"
                                }
                            }
                        },
                        "To": {
                            "City": {
                                "Id": "HRG",
                                "Name": "HURGHADA",
                                "Country": {
                                    "Id": "EG",
                                    "Code": "EG",
                                    "Name": "EGIPT"
                                }
                            }
                        },
                        "TransportType": "plane",
                        "Dates": {
                            "2024-11-30": {
                                "Date": "2024-11-30",
                                "Nights": [
                                    {
                                        "Nights": 7
                                    }
                                ]
                            }
                        }
                    }
                }
            }'
        );
    }

    public function test_apiGetOffers_whenInputIsValid_receiveOffers(): void
    {
        $filterArr = [
            'checkIn' => '2024-11-30',
            'serviceTypes' => [
                'charter'
            ],
            'transportTypes' => [
                'plane'
            ],
            'cityId' => 'HRG',
            'departureCity' => 'CLJ',
            'rooms' => [
                [
                    'adults' => 1,
                    'children' => 2,
                    'childrenAges' => ['1', '5']
                ]
            ],
            'days' => 7
        ];
        $todayDt = new DateTimeImmutable();
        $today = $todayDt->format('Y-m-d');
        $today3Dt = $todayDt->modify('+3 days');
        $today4Dt = $todayDt->modify('+4 days');
        $today3 = $today3Dt->format('Y-m-d');
        $today4 = $today4Dt->format('Y-m-d');
        
        self::$body['method'] = self::$api_getOffers;
        self::$body['args'] = [$filterArr];
        $content = $this->getResponseJson(self::$body);

        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": {
                    "eyJUIjowLCJDIjoiMjA5MjQifQ==": {
                        "Id": "eyJUIjowLCJDIjoiMjA5MjQifQ==",
                        "Offers": {
                            "eyJUIjowLCJDIjoiMjA5MjQifQ==~1DBL~ULALL~2024-11-30~7~1828~1~1|5": {
                                "Code": "eyJUIjowLCJDIjoiMjA5MjQifQ==~1DBL~ULALL~2024-11-30~7~1828~1~1|5",
                                "CheckIn": "2025-06-08",
                                "Currency": {
                                    "Code": "EUR"
                                },
                                "Comission": 0,
                                "InitialPrice": 2978,
                                "Gross": 1828,
                                "Net": 1828,
                                "Availability": "yes",
                                "Days": "7",
                                "Rooms": [
                                    {
                                        "Id": "1DBL",
                                        "Merch": {
                                            "Id": "1DBL",
                                            "Title": " Deluxe Room",
                                            "Type": {
                                                "Id": "1DBL",
                                                "Title": " Deluxe Room"
                                            },
                                            "Code": "1DBL",
                                            "Name": " Deluxe Room"
                                        },
                                        "CheckinAfter": "2025-06-08",
                                        "CheckinBefore": "2025-06-15",
                                        "Currency": {
                                            "Code": "EUR"
                                        },
                                        "Quantity": 1,
                                        "Availability": "yes",
                                        "InfoDescription": "Oferta Limitata"
                                    }
                                ],
                                "Item": {
                                    "Id": "1DBL",
                                    "Merch": {
                                        "Id": "1DBL",
                                        "Title": " Deluxe Room",
                                        "Type": {
                                            "Id": "1DBL",
                                            "Title": " Deluxe Room"
                                        },
                                        "Code": "1DBL",
                                        "Name": " Deluxe Room"
                                    },
                                    "CheckinAfter": "2025-06-08",
                                    "CheckinBefore": "2025-06-15",
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "Availability": "yes",
                                    "InfoDescription": "Oferta Limitata"
                                },
                                "MealItem": {
                                    "Merch": {
                                        "Title": "UL ALL",
                                        "Type": {
                                            "Id": "ULALL",
                                            "Title": "UL ALL"
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
                                        "Title": "Dus: 08.06.2025",
                                        "Category": {
                                            "Code": "other-outbound"
                                        },
                                        "TransportType": "plane",
                                        "From": {
                                            "City": {
                                                "Id": "CLJ",
                                                "Name": "CLUJ-NAPOCA",
                                                "Country": {
                                                    "Id": "RO",
                                                    "Code": "RO",
                                                    "Name": "Romania"
                                                }
                                            }
                                        },
                                        "To": {
                                            "City": {
                                                "Id": "HRG",
                                                "Name": "HURGHADA",
                                                "Country": {
                                                    "Id": "EG",
                                                    "Code": "EG",
                                                    "Name": "EGIPT"
                                                }
                                            }
                                        },
                                        "DepartureTime": "2024-11-30 11:20",
                                        "ArrivalTime": "2024-11-30 14:30",
                                        "DepartureAirport": "CLJ",
                                        "ReturnAirport": "HRG"
                                    },
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "UnitPrice": 0,
                                    "Gross": 0,
                                    "Net": 0,
                                    "InitialPrice": 0,
                                    "DepartureDate": "2025-06-08",
                                    "ArrivalDate": "2025-06-08",
                                    "Return": {
                                        "Merch": {
                                            "Title": "Retur: 15.06.2025",
                                            "Category": {
                                                "Code": "other-inbound"
                                            },
                                            "TransportType": "plane",
                                            "From": {
                                                "City": {
                                                    "Id": "HRG",
                                                    "Name": "HURGHADA",
                                                    "Country": {
                                                        "Id": "EG",
                                                        "Code": "EG",
                                                        "Name": "EGIPT"
                                                    }
                                                }
                                            },
                                            "To": {
                                                "City": {
                                                    "Id": "CLJ",
                                                    "Name": "CLUJ-NAPOCA",
                                                    "Country": {
                                                        "Id": "RO",
                                                        "Code": "RO",
                                                        "Name": "Romania"
                                                    }
                                                }
                                            },
                                            "DepartureTime": "2024-12-07 23:40",
                                            "ArrivalTime": "2024-12-08 03:10",
                                            "DepartureAirport": "HRG",
                                            "ReturnAirport": "CLJ"
                                        },
                                        "Currency": {
                                            "Code": "EUR"
                                        },
                                        "Quantity": 1,
                                        "UnitPrice": 0,
                                        "Gross": 0,
                                        "Net": 0,
                                        "InitialPrice": 0,
                                        "DepartureDate": "2025-06-15",
                                        "ArrivalDate": "2025-06-15"
                                    }
                                },
                                "ReturnTransportItem": {
                                    "Merch": {
                                        "Title": "Retur: 15.06.2025",
                                        "Category": {
                                            "Code": "other-inbound"
                                        },
                                        "TransportType": "plane",
                                        "From": {
                                            "City": {
                                                "Id": "HRG",
                                                "Name": "HURGHADA",
                                                "Country": {
                                                    "Id": "EG",
                                                    "Code": "EG",
                                                    "Name": "EGIPT"
                                                }
                                            }
                                        },
                                        "To": {
                                            "City": {
                                                "Id": "CLJ",
                                                "Name": "CLUJ-NAPOCA",
                                                "Country": {
                                                    "Id": "RO",
                                                    "Code": "RO",
                                                    "Name": "Romania"
                                                }
                                            }
                                        },
                                        "DepartureTime": "2024-12-07 23:40",
                                        "ArrivalTime": "2024-12-08 03:10",
                                        "DepartureAirport": "HRG",
                                        "ReturnAirport": "CLJ"
                                    },
                                    "Currency": {
                                        "Code": "EUR"
                                    },
                                    "Quantity": 1,
                                    "UnitPrice": 0,
                                    "Gross": 0,
                                    "Net": 0,
                                    "InitialPrice": 0,
                                    "DepartureDate": "2025-06-15",
                                    "ArrivalDate": "2025-06-15"
                                },
                                "CancelFees": [
                                    {
                                        "DateStart": "'.$today4.'",
                                        "DateEnd": "2025-03-31",
                                        "Price": 274.2,
                                        "Currency": {
                                            "Code": "EUR"
                                        }
                                    },
                                    {
                                        "DateStart": "2025-04-01",
                                        "DateEnd": "2025-05-18",
                                        "Price": 548.4,
                                        "Currency": {
                                            "Code": "EUR"
                                        }
                                    },
                                    {
                                        "DateStart": "2025-05-19",
                                        "DateEnd": "2025-06-08",
                                        "Price": 1828,
                                        "Currency": {
                                            "Code": "EUR"
                                        }
                                    }
                                ],
                                "Payments": [
                                    {
                                        "PayAfter": "'.$today.'",
                                        "PayUntil": "'.$today3.'",
                                        "Amount": 274.2,
                                        "Currency": {
                                            "Code": "EUR"
                                        }
                                    },
                                    {
                                        "PayAfter": "'.$today4.'",
                                        "PayUntil": "2025-03-31",
                                        "Amount": 274.2,
                                        "Currency": {
                                            "Code": "EUR"
                                        }
                                    },
                                    {
                                        "PayAfter": "2025-04-01",
                                        "PayUntil": "2025-05-18",
                                        "Amount": 1279.6,
                                        "Currency": {
                                            "Code": "EUR"
                                        }
                                    }
                                ],
                                "bookingPrice": "1828",
                                "bookingCurrency": "EUR",
                                "roomCombinationId": "9684828947",
                                "roomCombinationPriceDescription": "eyJDSEQiOiIiLCJBbm8iOjIsIkJUaWQiOjUsIlJUaWQiOjQxNDAsIlNIaWQiOjUyNDc3LCJTVFRUaWQiOjE1MzU4NiwiU0RGaWQiOjEwMjgsIlNEVGlkIjoxMDI5LCJUQ1RpZCI6MSwiU2lkIjozNiwiREQiOiIyMDI1LTA2LTA4VDA1OjAwOjAwIiwiREhDIjoiIiwiRFJDIjoiIiwiRFJLIjoiIiwiREJDIjoiIiwiRE9QaWQiOiIifQ==",
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

    public function test_bookHotel_whenInputIsValid_receiveBookHotel(): void
    {
        $filterArr = [
            'Items' => [
                [
                    'Offer_bookingPrice' => '1',
                    'Offer_bookingCurrency' => 'EUR',
                    'Offer_roomCombinationPriceDescription' => 'abc',
                    'Offer_roomCombinationId' => 123,
                    'Hotel' => [
                        'InTourOperatorId' => 'ABC1'
                    ],
                    'Room_CheckinBefore' => '2022-01-01',
                    'Room_CheckinAfter' => '2022-01-02',
                    'Passengers' => [[
                        'Firstname' => 'Test1',
                        'Lastname' => 'Test2',
                        'IsAdult' => 1,
                        'Gender' => 'male',
                        'BirthDate' => '2022-02-02'
                    ]]
                ]
            ],
        ];

        self::$body['method'] = self::$api_doBooking;
        self::$body['args'] = [$filterArr];
        $content = $this->getResponseJson(self::$body);

        $this->assertJsonStringEqualsJsonString($content,
            '{
                "response": [
                    {
                        "Id": "1"
                    },
                    "--uuid:f81d294d-22c7-42aa-aaf2-5f1ab5a118a3+id=31181\r\nContent-ID: \r\n<http://tempuri.org/0>\r\nContent-Transfer-Encoding: 8bit\r\nContent-Type: application/xop+xml;charset=utf-8;type=\"application/soap+xml\"\r\n\r\n\r\n    <s:Envelope xmlns:s=\"http://www.w3.org/2003/05/soap-envelope\" xmlns:a=\"http://www.w3.org/2005/08/addressing\">\r\n        <s:Header>\r\n            <a:Action s:mustUnderstand=\"1\">http://tempuri.org/IReservations/ValidateBeforeReservation</a:Action>\r\n        </s:Header>\r\n        <s:Body>\r\n            <MakeReservationResponse xmlns=\"http://tempuri.org/\">\r\n                <MakeReservationResult xmlns:b=\"http://schemas.datacontract.org/2004/07/WebAPI.Model\" xmlns:i=\"http://www.w3.org/2001/XMLSchema-instance\">\r\n                    <b:ResultCode>0</b:ResultCode>\r\n                    <b:ResultMessage>OK</b:ResultMessage>\r\n                    <b:ReservationInfo>\r\n                        <b:ReservationNo>1</b:ReservationNo>\r\n                        <b:IsOnRequest></b:IsOnRequest>\r\n                        <b:TotalPrice>2</b:TotalPrice>\r\n                        <b:Commision>1</b:Commision>\r\n                    </b:ReservationInfo>\r\n                </MakeReservationResult>\r\n            </MakeReservationResponse>\r\n        </s:Body>\r\n    </s:Envelope>\r\n--uuid:f81d294d-22c7-42aa-aaf2-5f1ab5a118a3+id=31181--"
                ]
            }'
        );
    }*/
}
