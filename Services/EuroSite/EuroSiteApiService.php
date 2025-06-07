<?php

namespace Integrations\EuroSite;

use App\Entities\Availability\AirportTaxesCategory;
use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\ReturnTransportItem;
use App\Entities\Availability\Room;
use App\Entities\Availability\RoomCollection;
use App\Entities\Availability\RoomMerch;
use App\Entities\Availability\RoomMerchType;
use App\Entities\Availability\TransferCategory;
use App\Entities\Availability\TransportMerch;
use App\Entities\Availability\TransportMerchCategory;
use App\Entities\Availability\TransportMerchLocation;
use App\Entities\AvailabilityDates\AvailabilityDates;
use App\Entities\AvailabilityDates\array;
use App\Entities\AvailabilityDates\DateNight;
use App\Entities\AvailabilityDates\DateNightCollection;
use App\Entities\AvailabilityDates\TransportCity;
use App\Entities\AvailabilityDates\TransportContent;
use App\Entities\AvailabilityDates\TransportDate;
use App\Entities\AvailabilityDates\TransportDateCollection;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Tours\Location;
use App\Entities\Tours\Stage;
use App\Entities\Tours\StageCollection;
use App\Entities\Tours\StageContent;
use App\Entities\Tours\Tour;
use App\Entities\Tours\TourCollection;
use App\Entities\Tours\TourContent;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\StringCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use Utils\Utils;

// eximtur_v2 has only hotels
class EuroSiteApiService extends AbstractApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function apiTestConnection(): bool
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $data = [
            'Request' => [
                '[RequestType]' => 'getCountryRequest',
                'AuditInfo' => [
                    'RequestId' => '001',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                ],
                'RequestDetails' => [
                    'getCountryRequest' => ''
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($data);
        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $tries = 1;
        while ($response->getStatusCode() !== 200) {
            sleep(5);
            $tries++;
            $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            if ($tries >= 10) {
                break;
            }
        }
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

        try {
            $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;
        } catch(Exception $e) {
            return false;
        }

        if (empty($responseXml->Errors->Error)) {
            return true;
        }
        return false;
    }

    public function apiGetCountries(): array
    {
        $file = 'countries';

        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

            Validator::make()->validateUsernameAndPassword($this->post);

            $data = [
                'Request' => [
                    '[RequestType]' => 'getCountryRequest',
                    'AuditInfo' => [
                        'RequestId' => '001',
                        'RequestUser' => $this->username,
                        'RequestPass' => $this->password,
                        'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                    ],
                    'RequestDetails' => [
                        'getCountryRequest' => ''
                    ]
                ]
            ];
            $requestXml = Utils::arrayToXmlString($data);
            $options = [
                'body' => $requestXml,
                'headers' => [
                    'Content-Type' => 'application/xml',
                ]
            ];
    
            $client = HttpClient::create();

            $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

            $tries = 1;
            while ($response->getStatusCode() !== 200) {
                sleep(5);
                $tries++;
                $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                if ($tries >= 10) {
                    break;
                }
            }
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

            $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

            if (!empty($responseXml->Errors->Error)) {
                throw new Exception(json_encode($responseXml->Errors->Error));
            }

            $response = $responseXml->getCountryResponse->Country;

            $countries = [];
            foreach ($response as $value) {
                $country = Country::create($value->CountryId, $value->CountryCode, $value->CountryName);
                $countries->put($country->Id, $country);
            }
            Utils::writeToCache($this, $file, json_encode($countries));
        } else {
            $countries = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }
        return $countries;
    }

    public function apiGetCities(?CitiesFilter $params = null): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $file = 'cities';
        $citiesJson = Utils::getFromCache($this, $file);
        if ($citiesJson === null) {
        
            $data = [
                'Request' => [
                    '[RequestType]' => 'getOwnCityRequest',
                    'AuditInfo' => [
                        'RequestId' => '002',
                        'RequestUser' => $this->username,
                        'RequestPass' => $this->password,
                        'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                    ],
                    'RequestDetails' => [
                        'getOwnCityRequest' => ''
                    ]
                ]
            ];

            $requestXml = Utils::arrayToXmlString($data);

            $options = [
                'body' => $requestXml,
                'headers' => [
                    'Content-Type' => 'application/xml',
                ]
            ];
    
            $client = HttpClient::create();

            $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

            $tries = 1;
            while ($response->getStatusCode() !== 200) {
                sleep(5);
                $tries++;
                $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                if ($tries >= 10) {
                    break;
                }
            }

            $countries = $this->apiGetCountries();
            $cities = [];

            $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

            if (!empty($responseXml->Errors->Error)) {
                throw new Exception(json_encode($responseXml->Errors->Error));
            }

            foreach ($responseXml->getOwnCityResponse->City as $cityResponse) {
                $country = $countries->first(fn(Country $countryResponse) => $countryResponse->Code === (string) $cityResponse->CountryCode);
                if ($country === null) {
                    continue;
                }

                $city = City::create($cityResponse->CityCode, $cityResponse->CityName, $country);
                $cities->put($city->Id, $city);
            }
            Utils::writeToCache($this, $file, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($citiesJson, true), array::class);
        }
        return $cities;
    }

    public function apiGetHotels(?HotelsFilter $filter = null): []
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $json = Utils::getFromCache($this, 'hotels');

        if ($json === null || !empty($filter->CityId)) {

            $hotels = [];
            $cities = $this->apiGetCities();

            $options['headers'] = [
                'Content-Type: application/xml',
            ];
            $httpClient = HttpClient::create();

            $responses = [];

            if (!empty($filter->CityId)) {
                $time = (new DateTime())->format('Y-m-d\TH:i:s');
                $city = $cities->get($filter->CityId);

                $data = [
                    'Request' => [
                        '[RequestType]' => 'getOwnHotelsRequest',
                        'AuditInfo' => [
                            'RequestId' => '003',
                            'RequestUser' => $this->username,
                            'RequestPass' => $this->password,
                            'RequestTime' => $time
                        ],
                        'RequestDetails' => [
                            'getOwnHotelsRequest' => [
                                'CityCode' => $filter->CityId
                            ]
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($data);

                $options = ['body' => $requestXml];

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options, $city];
            } else {
                
                foreach ($cities as $city) {
                    $time = (new DateTime())->format('Y-m-d\TH:i:s');
                
                    $data = [
                        'Request' => [
                            '[RequestType]' => 'getOwnHotelsRequest',
                            'AuditInfo' => [
                                'RequestId' => '003',
                                'RequestUser' => $this->username,
                                'RequestPass' => $this->password,
                                'RequestTime' => $time
                            ],
                            'RequestDetails' => [
                                'getOwnHotelsRequest' => [
                                    'CityCode' => $city->Id
                                ]
                            ]
                        ]
                    ];
                    $requestXml = Utils::arrayToXmlString($data);
    
                    $options = ['body' => $requestXml];
    
                    $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                    $responseObj->getBody();
                    $responses[] = [$responseObj, $options, $city];
                }
            }

            foreach ($responses as $responseArr) {
                $responseObj = $responseArr[0];
                $options = $responseArr[1];
                $city = $responseArr[2];

                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getBody(), $responseObj->getStatusCode());

                $response = simplexml_load_string($responseObj->getBody())->ResponseDetails;

                if (!empty($response->Errors->Error)) {
                    throw new Exception(json_encode($response->Errors->Error));
                }

                foreach ($response->getOwnHotelsResponse->Hotel as $hotelResponse) {

                    $hotel = new Hotel();

                    $images = new HotelImageGalleryItemCollection();

                    // $hotel->Content->ImageGallery
                    $imageGallery = new HotelImageGallery();
                    $imageGallery->Items = $images;

                    // $hotel->Content
                    $content = new HotelContent();
                    $content->ImageGallery = $imageGallery;
                    $content->Content = null;

                    // $hotel->Address
                    $address = new HotelAddress();
                    $address->Latitude = null;
                    $address->Longitude = null;
                    $address->Details = null;
                    $address->City = $city;

                    $hotel->Id = $hotelResponse->HotelCode;
                    $hotel->Name = $hotelResponse->HotelName;
                    $hotel->Stars = 0;
                    $hotel->Content = $content;
                    $hotel->Address = $address;
                    $hotel->WebAddress = null;

                    $hotels->put($hotel->Id, $hotel);
                }
            }
            if (empty($filter->CityId)) {
                $data = json_encode_pretty($hotels);
                Utils::writeToCache($this, 'hotels', $data);
            }
        } else {
            $arr = json_decode($json, true);
            $hotels = ResponseConverter::convertToCollection($arr, []::class);
        }
        return $hotels;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateHotelDetailsFilter($filter);

        $hotelId = $filter->hotelId;

        $tourOpCode = '';
        if ($this->handle === 'eximtur_v2' && empty($this->apiContext)) {
            $tourOpCode = 'EXM';
        } else {
            $tourOpCode = $this->apiContext;
        }

        $hotels = $this->apiGetHotels();

        $details = new Hotel();

        $hotel = $hotels->first(fn(Hotel $hotelResponse) => $hotelResponse->Id === $hotelId);

        if ($hotel === null) {
            return $details;
        }

        $time = (new DateTime())->format('Y-m-d\TH:i:s');
        
        $data = [
            'Request' => [
                '[RequestType]' => 'getProductInfoRequest',
                'AuditInfo' => [
                    'RequestId' => '004',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => $time
                ],
                'RequestDetails' => [
                    'getProductInfoRequest' => [
                        'CityCode' => $hotel->Address->City->Id,
                        'ProductType' => 'hotel',
                        'CountryCode' => $hotel->Address->City->Country->Code,
                        'TourOpCode' => $tourOpCode,
                        'ProductCode' => $hotelId
                    ]
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($data);

        $options['headers'] = [
            'Content-Type' =>  'application/xml',
        ];
        $options['body'] = $requestXml;

        $httpClient = HttpClient::create();
        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        $responseXml = simplexml_load_string($responseObj->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $response = $responseXml->getProductInfoResponse->Product;

        if ($response->ProductCode === null) {
            return $details;
        }

        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();

        if ($response->Pictures !== null && $response->Pictures->Picture !== null) {
            foreach ($response->Pictures->Picture as $imageResponse) {
                $image = new HotelImageGalleryItem();
                $image->RemoteUrl = (string) $imageResponse;
                $image->Alt = $imageResponse->attributes()->Name;
                $items->add($image);
            }
        }

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        // Content Address City
        $city = $hotel->Address->City;

        // Content Address
        $address = new HotelAddress();
        $address->City = $city;
        $address->Details = $response->Address;
        $address->Latitude = $response->Latitude;
        $address->Longitude = $response->Latitude;

        // Content ContactPerson
        $contactPerson = null;

        $facilities = new FacilityCollection();

        if ($response->Facilities!== null && $response->Facilities->Facility !== null) {
            foreach ($response->Facilities->Facility as $facilityResponse) {
                $facility = new Facility();
                $facility->Id = preg_replace('/\s+/', '', $facilityResponse);
                $facility->Name = $facilityResponse;
                $facilities->add($facility);
            }
        }

        // Content
        $content = new HotelContent();
        $content->Content = html_entity_decode($response->DescriptionDet);
        $content->ImageGallery = $imageGallery;

        $details->Id = $response->ProductCode;
        $details->Name = $response->ProductName;
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Content = $content;
        $details->WebAddress = null;

        return $details;
    }

    public function apiGetTours(): TourCollection
    {
        // get destinations
        Validator::make()->validateUsernameAndPassword($this->post);

        $cities = $this->apiGetCities();

        $client = HttpClient::create();

        $data = [
            'Request' => [
                '[RequestType]' => 'CircuitSearchCityRequest',
                'AuditInfo' => [
                    'RequestId' => '009',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                ],
                'RequestDetails' => [
                    'CircuitSearchCityRequest' => ''
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($data);
        $options = ['body' => $requestXml];
        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

        $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $responseDestinations = $responseXml->CircuitSearchCityResponse->Country;

        $tours = new TourCollection();

        $responses = [];
        foreach ($responseDestinations as $country) {
            foreach ($country->Cities as $citiesResp) {
                foreach ($citiesResp->City as $cityResp) {
                    
                    $cityRequest = $cities->get((string) $cityResp->CityCode);

                    if ($cityRequest === null) {
                        continue;
                    }
                    
                    $data = [
                        'Request' => [
                            '[RequestType]' => 'CircuitSearchRequest',
                            'AuditInfo' => [
                                'RequestId' => '010',
                                'RequestUser' => $this->username,
                                'RequestPass' => $this->password,
                                'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                            ],
                            'RequestDetails' => [
                                'CircuitSearchRequest' => [
                                    'CountryCode' => (string) $country->CountryCode,
                                    'CityCode' => (string) $cityResp->CityCode,
                                    'CurrencyCode' => 'EUR',
                                    'Year' => date('Y'),
                                    'Month' => 13,
                                    'Rooms' => [
                                        'Room' => [
                                            '[Code]' => 'DB',
                                            '[NoAdults]' => 2
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $requestXml = Utils::arrayToXmlString($data);
                    $options = ['body' => $requestXml];

                    $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                    $responses[] = [$response, $options];
                }
            }
        }

        foreach ($responses as $responseArr){
            $response = $responseArr[0];
            
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
            // $tries = 1;
            // while ($response->getStatusCode() !== 200) {
            //     sleep(5);
            //     $tries++;
            //     $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            //     if ($tries >= 10) {
            //         break;
            //     }
            // }
    
            $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;
    
            // if (!empty($responseXml->Errors->Error)) {
            //     throw new Exception(json_encode($responseXml->Errors->Error));
            // }
            $circuit = $responseXml->CircuitSearchResponse->Circuit;
            if ($circuit->CircuitId === null) {
                continue;
            }

            $tour = new Tour();
            $tour->Id = $circuit->CircuitId;
            $tour->Title = $circuit->Name;

            $destinations = [];
            $destinationCountries = [];

            foreach ($circuit->Destinations->CircuitDestination as $dest) {
                $city = $cities->get((string) $dest->CityCode);
                if ($city === null) {
                    continue;
                }

                $destinations->add($city);
                $destinationCountries->add($city->Country);
            }
            $tour->Destinations = $destinations;
            $tour->Destinations_Countries = $destinationCountries;
            $content = new TourContent();
            $content->Content = $circuit->Description;
            $tour->Content = $content;

            $servicesText = '';
            $transportTypes = new StringCollection();
            
            if (isset($circuit->Variants->Variant->Services->Service)) {
                foreach ($circuit->Variants->Variant as $variant) {
                    foreach ($variant->Services->Service as $service) {
                        $servicesText .= '<p>' . $service->Name . '</p>';
                        $transportType = $service->Transport;
                        if (isset($service->Transport)) {
                            $transportType = strtolower($transportType);
                            $transportTypes->put($transportType, $transportType);
                        }
                    }
                }
            }
            
            $tour->Period = (int) $circuit->Period;
            $tour->TransportTypes = $transportTypes;

            $location = new Location();
            $location->City = $destinations->first();
            $tour->Location = $location;

            $stages = new StageCollection();

            foreach ($circuit->DayDescriptions->DayDescription as $description) {
                $stage = new Stage();
                $content = new StageContent();
                $content->ShortDescription = $description;
                $stage->Content = $content;
                $stages->add($stage);
            }

            $tour->Stages = $stages;
            $tours->put($tour->Id, $tour);
        }
        return $tours;
    }

    private function getHotelOffers(AvailabilityFilter $filter): array
    {
        EuroSiteValidator::make()
            ->validateAllCredentials($this->post)
            ->validateAvailabilityFilter($filter);

        $countries = $this->apiGetCountries();

        if (empty($filter->checkOut)) {
            throw new Exception("checkOut is mandatory");
        }
        $ages = $post['args'][0]['rooms'][0]['childrenAges'];

        $time = (new DateTime())->format('Y-m-d\TH:i:s');

        $room = [
            '[Code]' => 'DB',
            '[NoAdults]' => $filter->rooms->first()->adults,
        ];

        if ((int) $post['args'][0]['rooms'][0]['children'] > 0) {
            $room['[NoChildren]'] = $post['args'][0]['rooms'][0]['children'] ?? 0;

            $children = [];
            foreach ($ages as $age) {
                if ($age !== '') {
                    $children[] = [
                        'Age' => $age
                    ];
                }
            }

            $room['Children'] = $children;
        }

        $tourOpCode = $this->apiContext;
        // if ($this->handle === 'eximtur_v2') {
        //     $tourOpCode = 'EXM';
        // }
        
        $data = [
            'Request' => [
                '[RequestType]' => 'getHotelPriceRequest',
                'AuditInfo' => [
                    'RequestId' => '005',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => $time
                ],
                'RequestDetails' => [
                    'getHotelPriceRequest' => [
                        'CountryCode' => $countries->get($filter->countryId)->Code,
                        'CityCode' => $filter->cityId,
                        'TourOpCode' => $tourOpCode,
                        'ProductCode' => $filter->hotelId,
                        'CurrencyCode' => $filter->RequestCurrency,
                        'PeriodOfStay' => [
                            'CheckIn' => $filter->checkIn,
                            'CheckOut' => $filter->checkOut,
                        ],
                        'Rooms' => [
                            [
                                'Room' => $room
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $requestXml = Utils::arrayToXmlString($data);

        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $responseObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        $responseXml = simplexml_load_string($responseObj->getBody())->ResponseDetails->getHotelPriceResponse;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $availabilities = [];

        if (isset($responseXml->Hotel)) {
            foreach ($responseXml->Hotel as $hotel) {
                $availability = new Availability();
                $availability->Id = $hotel->Product->ProductCode;

                if ($filter->showHotelName) {
                    $availability->Name = $hotel->Product->ProductName;
                }

                $offers = new OfferCollection();
                foreach ($hotel->Offers as $offerResponse) {
                    foreach ($offerResponse->Offer as $offerResponse) {
                        $offer = new Offer();

                        $availabilityStr = Offer::AVAILABILITY_NO;

                        if ((string) $offerResponse->Availability === 'Immediate') {
                            $availabilityStr = Offer::AVAILABILITY_YES;
                        } else if ((string) $offerResponse->Availability === 'OnRequest') {
                            $availabilityStr = Offer::AVAILABILITY_ASK;
                        }
                        $offer->Availability = $availabilityStr;

                        $offer->CheckIn = $offerResponse->PeriodOfStay->CheckIn;
                        $offer->InitialData = $offerResponse->PackageVariantId;

                        $currency = new Currency();
                        $currency->Code = $offerResponse->attributes()->CurrencyCode;
                        $offer->Currency = $currency;
                        $offer->bookingCurrency = $currency->Code;

                        // todo: de verificat si la ceilalti to
                        $bookingArr = [
                            'VariantId' => (string) $offerResponse->PackageVariantId
                        ];

                        $offer->bookingDataJson = json_encode($bookingArr);

                        $checkInDateTime = new DateTimeImmutable($offer->CheckIn);
                        $checkOutDateTime = new DateTimeImmutable($offerResponse->PeriodOfStay->CheckOut);
                        $dif = $checkOutDateTime->diff($checkInDateTime)->days;

                        $offer->Days = $dif;
                        
                        $offer->Net = (float) $offerResponse->ProductPrice;
                        $offer->Gross = (float) $offerResponse->Gross;
                        $offer->InitialPrice = (float) $offerResponse->PriceNoRedd;
                        $offer->Comission = (float) $offerResponse->Commission;

                        $roomId = $offerResponse->BookingRoomTypes->Room->attributes()->Code;

                        $hotelCheckIn = $offerResponse->PeriodOfStay->CheckIn;
                        $hotelCheckOut = $offerResponse->PeriodOfStay->CheckOut;

                        // Rooms
                        $room1 = new Room();
                        $room1->Id = $roomId;

                        $room1->CheckinBefore = $hotelCheckOut;
                        $room1->CheckinAfter = $hotelCheckIn;
                        $room1->Currency = $currency;
                        $room1->Availability = $offer->Availability;
                        $room1->InfoDescription = $offerResponse->OfferDescription;

                        $roomMerchType = new RoomMerchType();
                        $roomMerchType->Id = $roomId;
                        $roomMerchType->Title = $offerResponse->BookingRoomTypes->Room;

                        $merch = new RoomMerch();
                        $merch->Id = $roomId;
                        $merch->Title = $roomMerchType->Title;
                        $merch->Type = $roomMerchType;
                        $merch->Code = $roomId;
                        $merch->Name = $roomMerchType->Title;

                        $room1->Merch = $merch;
                        if (!empty($offerResponse->GrilaName)) {
                            $room1->InfoTitle = 'Grila: ' . $offerResponse->GrilaName;
                        }

                        $offer->Rooms = new RoomCollection([$room1]);

                        $offer->Item = $room1;

                        $mealItem = new MealItem();

                        $boardTypeName = $offerResponse->MealType;

                        if ($boardTypeName == 'SC') {
                            $boardTypeName = 'Ro';
                        }

                        // MealItem Merch
                        $boardMerch = new MealMerch();
                        $boardMerch->Title = $boardTypeName;

                        // MealItem
                        $mealItem->Merch = $boardMerch;
                        $mealItem->Currency = $currency;

                        $offer->MealItem = $mealItem;

                        $mealId = $offerResponse->MealType;
                        $offerCode = $hotel->Product->ProductCode . '~' . $roomId . '~' . $mealId . '~' . $offer->CheckIn . '~' . $offer->Days . '~' . $offer->Net . '~' . $filter->rooms->first()->adults . '~' . ($ages ? implode('|', $ages->toArray()) : '');
                        $offer->Code = $offerCode;

                        // DepartureTransportItem Merch
                        $departureTransportItemMerch = new TransportMerch();
                        $departureTransportItemMerch->Title = 'CheckIn: ' . $offer->CheckIn;

                        // DepartureTransportItem Return Merch
                        $departureTransportItemReturnMerch = new TransportMerch();
                        $departureTransportItemReturnMerch->Title = 'CheckOut: ' . $offerResponse->PeriodOfStay->CheckOut;

                        // DepartureTransportItem Return
                        $departureTransportItemReturn = new ReturnTransportItem();
                        $departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
                        $departureTransportItemReturn->Currency = $currency;
                        $departureTransportItemReturn->DepartureDate = $offerResponse->PeriodOfStay->CheckOut;
                        $departureTransportItemReturn->ArrivalDate = $offerResponse->PeriodOfStay->CheckOut;

                        // DepartureTransportItem
                        $departureTransportItem = new DepartureTransportItem();
                        $departureTransportItem->Merch = $departureTransportItemMerch;
                        $departureTransportItem->Currency = $currency;
                        $departureTransportItem->DepartureDate = $offer->CheckIn;
                        $departureTransportItem->ArrivalDate = $offer->CheckIn;
                        $departureTransportItem->Return = $departureTransportItemReturn;

                        $offer->DepartureTransportItem = $departureTransportItem;

                        $offer->ReturnTransportItem = $departureTransportItemReturn;


                        $offers->add($offer);
                    }
                }
                $availability->Offers = $offers;
                $availabilities->add($availability);
            }
        }
        return $availabilities;
    }

    private function getTourOffers(AvailabilityFilter $filter): array
    {
        EuroSiteValidator::make()
            ->validateAllCredentials($this->post)
            ->validateAvailabilityFilter($filter);

        $cities = $this->apiGetCities();
        //$hotels = $this->getHotels();

        $ages = $post['args'][0]['rooms'][0]['childrenAges'];
        $availabilities = [];

        $room = [
            '[Code]' => 'DB',
            '[NoAdults]' => $filter->rooms->first()->adults,
        ];

        if ((int) $post['args'][0]['rooms'][0]['children'] > 0) {
            $room['[NoChildren]'] = $post['args'][0]['rooms'][0]['children'] ?? 0;

            $children = [];
            foreach ($ages as $age) {
                if ($age !== '') {
                    $children[] = [
                        'Age' => $age
                    ];
                }
            }

            $room['Children'] = $children;
        }

        $data = [
            'Request' => [
                '[RequestType]' => 'CircuitSearchRequest',
                'AuditInfo' => [
                    'RequestId' => '010',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                ],
                'RequestDetails' => [
                    'CircuitSearchRequest' => [
                        'CountryCode' => $filter->countryId,
                        'CityCode' => $filter->cityId,
                        'CurrencyCode' => 'EUR',
                        'Year' => date('Y'),
                        'Month' => (new DateTime($filter->checkIn))->format('m'),
                        'Rooms' => [
                            'Room' => $room
                        ]
                    ]
                ]
            ]
        ];

        $requestXml = Utils::arrayToXmlString($data);
        
        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }
        $circuits = $responseXml->CircuitSearchResponse->Circuit;

        foreach ($circuits as $circuit) {

            if ((string)$circuit->TourOpCode !== $this->apiContext) {
                throw new Exception('TourOpCodes do not match!');
            }
            
            $availability = new Availability();
            $availability->Id = $circuit->CircuitId;

            $offers = new OfferCollection();

            foreach ($circuit->Variants->Variant as $variant) {
                $offer = new Offer();

                $availabilityStr = Offer::AVAILABILITY_NO;
        
                if ($variant->Availability === 'Immediate') {
                    $availabilityStr = Offer::AVAILABILITY_YES;
                } else if ($variant->Availability === 'OnRequest') {
                    $availabilityStr = Offer::AVAILABILITY_ASK;
                }
                $offer->Availability = $availabilityStr;
        
                $offer->CheckIn = $variant->InfoCharter->DepDate;

                $currency = new Currency();
                $currency->Code = $variant->CurrencyCode;
                $offer->Currency = $currency;
                //$offer->bookingCurrency = $currency->Code;

                // $checkInDateTime = new DateTimeImmutable($offer->CheckIn);
                // $checkOutDateTime = new DateTimeImmutable($offerResponse->PeriodOfStay->CheckOut);
                // $dif = $checkOutDateTime->diff($checkInDateTime)->days;

                $offer->Days = $circuit->Period;

                $offer->Net = (float) $variant->ProductPrice;
                $offer->Gross = (float) $variant->Gross;
                $offer->InitialPrice = (float) $variant->PriceNoRedd;
                $offer->Comission = (float) $variant->Commission;

                $rooms = 0;
                $roomResp = null;
                foreach ($variant->Services->Service as $service) {
                    if ((string) $service->Type === '11') { // room
                        $roomResp = $service;
                        $rooms++;
                        if ($rooms > 1) {
                            throw new Exception('Unexpected number of rooms');
                        }
                    }
                }

                $room1 = new Room();
                $room1->Id = $roomResp->Code;

                $room1->CheckinBefore = $roomResp->PeriodOfStay->CheckOut;
                $room1->CheckinAfter = $roomResp->PeriodOfStay->CheckIn;
                $room1->Currency = $currency;

                $availabilityStr = Offer::AVAILABILITY_NO;
        
                if ($roomResp->Availability === 'Immediate') {
                    $availabilityStr = Offer::AVAILABILITY_YES;
                } else if ($variant->Availability === 'OnRequest') {
                    $availabilityStr = Offer::AVAILABILITY_ASK;
                }

                $room1->Availability = $availabilityStr;

                $roomMerchType = new RoomMerchType();
                $roomMerchType->Id = $room1->Id;
                $roomMerchType->Title = $roomResp->Name;

                $merch = new RoomMerch();
                $merch->Id = $room1->Id;
                $merch->Title = $roomMerchType->Title;
                $merch->Type = $roomMerchType;
                $merch->Code = $room1->Id;
                $merch->Name = $roomMerchType->Title;

                $room1->Merch = $merch;

                $offer->Rooms = new RoomCollection([$room1]);

                $offer->Item = $room1;

                $mealItem = new MealItem();

                // todo: de facut
                $boardTypeName = '';

                // MealItem Merch
                $boardMerch = new MealMerch();
                $boardMerch->Title = $boardTypeName;

                // MealItem
                $mealItem->Merch = $boardMerch;
                $mealItem->Currency = $currency;

                $offer->MealItem = $mealItem;
                $mealId = '';

                $offerCode = $availability->Id . '~' . $room1->Id . '~' . $mealId . '~' . $offer->CheckIn . '~' . $offer->Days . '~' . $offer->Net . '~' . $filter->rooms->first()->adults . '~' . ($ages ? implode('|', $ages->toArray()) : '');
                $offer->Code = $offerCode;

                //$services = $variant->Services->Service;

                // $departureFlight = null;
                // $returnFlight = null;
                // foreach ($services as $service) {
                //     $departureDate = (new DateTime($service->PeriodOfStay->CheckIn))->format('Y-m-d');
                //     $arrivalDate = (new DateTime($service->PeriodOfStay->CheckOut))->format('Y-m-d');
                //     if ((string) $service->Type === '7' && (
                //         $departureDate === (string) $roomResp->PeriodOfStay->CheckIn
                //         || $arrivalDate === (string) $roomResp->PeriodOfStay->CheckIn
                //     )) {
                //         $departureFlight = $service;
                //     }
                //     if ((string) $service->Type === '7' && (
                //         $departureDate === (string) $roomResp->PeriodOfStay->CheckOut
                //         || $arrivalDate === (string) $roomResp->PeriodOfStay->CheckOut
                //     )) {
                //         $returnFlight = $service;
                //     }
                // }

                $departureFlightDate = $variant->InfoCharter->DepDate;
                $departureFlightDateArrival = $variant->InfoCharter->DepArrDate;
                $returnFlightDate = $variant->InfoCharter->RetDate;
                $returnFlightDateArrival = $variant->InfoCharter->RetArrDate;

                $departureDateTime = new DateTime($departureFlightDate);
                //$arrivalDateTime = new DateTime($departureFlightDateArrival);
                $returnDepartureDateTime = new DateTime($returnFlightDate);
                //$returnArrivalDateTime =  new DateTime($returnFlightDateArrival);

                // departure transport item merch
                $departureTransportMerch = new TransportMerch();
                $departureTransportMerch->Title = "Dus: " . $departureDateTime->format('d.m.Y');
                $departureTransportMerch->Category = new TransportMerchCategory();
                $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                $departureTransportMerch->TransportType = $filter->transportTypes->first();
                $departureTransportMerch->DepartureTime = $departureFlightDate;
                $departureTransportMerch->ArrivalTime = $departureFlightDateArrival;

                $departureTransportMerch->DepartureAirport = '';
                $departureTransportMerch->ReturnAirport = '';

                $departureCityCode = $variant->InfoCharter->DepArrCodLoc;
                $arrivalCityCode = $variant->InfoCharter->RetArrCodLoc;
                $departureCity = $cities->get($departureCityCode);

                $departureTransportMerch->From = new TransportMerchLocation();
                $departureTransportMerch->From->City = $departureCity;

                $arrivalCity = $cities->get($arrivalCityCode);

                $departureTransportMerch->To = new TransportMerchLocation();
                $departureTransportMerch->To->City = $arrivalCity;

                $departureTransportItem = new DepartureTransportItem();
                $departureTransportItem->Merch = $departureTransportMerch;
                $departureTransportItem->Currency = $offer->Currency;
                $departureTransportItem->DepartureDate = $departureFlightDate;
                $departureTransportItem->ArrivalDate = $departureFlightDateArrival;

                // return transport item
                $returnTransportMerch = new TransportMerch();
                $returnTransportMerch->Title = "Retur: " . $returnDepartureDateTime->format('d.m.Y');
                $returnTransportMerch->Category = new TransportMerchCategory();
                $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                $returnTransportMerch->TransportType = $filter->transportTypes->first();
                $returnTransportMerch->DepartureTime = $returnFlightDate;
                $returnTransportMerch->ArrivalTime = $returnFlightDateArrival;

                $returnTransportMerch->DepartureAirport = '';
                $returnTransportMerch->ReturnAirport = '';

                $returnTransportMerch->From = new TransportMerchLocation();
                $returnTransportMerch->From->City = $arrivalCity;

                $returnTransportMerch->To = new TransportMerchLocation();
                $returnTransportMerch->To->City = $departureCity;

                $returnTransportItem = new ReturnTransportItem();
                $returnTransportItem->Merch = $returnTransportMerch;
                $returnTransportItem->Currency = $offer->Currency;
                $returnTransportItem->DepartureDate = $returnFlightDate;
                $returnTransportItem->ArrivalDate = $returnFlightDateArrival;

                $departureTransportItem->Return = $returnTransportItem;

                $offer->Item = $room1;
                $offer->DepartureTransportItem = $departureTransportItem;
                $offer->ReturnTransportItem = $returnTransportItem;

                $offer->Items = [];
                $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory());
                $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory());

                $offers->add($offer);
                $availability->Offers = $offers;
            }
            $availabilities->add($availability);
        }

        return $availabilities;
    }

    private function getCharterOffers(AvailabilityFilter $filter): array
    {
        EuroSiteValidator::make()
            ->validateAllCredentials($this->post)
            ->validateAvailabilityFilter($filter);

        $cities = $this->apiGetCities();
        $countries = $this->apiGetCountries();

        $ages = $post['args'][0]['rooms'][0]['childrenAges'];

        $time = (new DateTime())->format('Y-m-d\TH:i:s');

        $checkOut = (new DateTime($filter->checkIn))->modify('+'.$filter->days.' days')->format('Y-m-d');

        $room = [
            '[Code]' => 'DB',
            '[NoAdults]' => $filter->rooms->first()->adults,
        ];

        if ((int) $post['args'][0]['rooms'][0]['children'] > 0) {
            $room['[NoChildren]'] = $post['args'][0]['rooms'][0]['children'] ?? 0;

            $children = [];
            foreach ($ages as $age) {
                if ($age !== '') {
                    $children[] = [
                        'Age' => $age
                    ];
                }
            }

            $room['Children'] = $children;
        }

        $tourOpCode = $this->apiContext;
        
        $data = [
            'Request' => [
                '[RequestType]' => 'getPackageNVPriceRequest',
                'AuditInfo' => [
                    'RequestId' => '008',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => $time
                ],
                'RequestDetails' => [
                    'getPackageNVPriceRequest' => [
                        'CountryCode' => $countries->get($filter->countryId)->Code,
                        'CityCode' => $filter->cityId,
                        'DepCountryCode' => 'RO',
                        'DepCityCode' => $filter->departureCity,
                        'Transport' => $filter->transportTypes->first(),

                        'TourOpCode' => $tourOpCode,
                        'ProductCode' => $filter->hotelId,
                        'CurrencyCode' => $filter->RequestCurrency,
                        'PeriodOfStay' => [
                            'CheckIn' => $filter->checkIn,
                            'CheckOut' => $checkOut,
                        ],
                        'Rooms' => [
                            [
                                'Room' => $room
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $requestXml = Utils::arrayToXmlString($data);
        
        $options = ['body' => $requestXml];

        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $responseObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        $responseXml = simplexml_load_string($responseObj->getBody())->ResponseDetails->getPackageNVPriceResponse;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $availabilities = [];

        if (isset($responseXml->Hotel)) {
            foreach ($responseXml->Hotel as $hotel) {
                $availability = new Availability();
                $availability->Id = $hotel->Product->ProductCode;
                
                $offers = new OfferCollection();
                foreach ($hotel->Offers as $offerResponse) {
                    foreach ($offerResponse->Offer as $offerResponse) {
                        $offer = new Offer();

                        $availabilityStr = Offer::AVAILABILITY_NO;

                        if ((string) $offerResponse->Availability === 'Immediate') {
                            $availabilityStr = Offer::AVAILABILITY_YES;
                        } else if ((string) $offerResponse->Availability === 'OnRequest') {
                            $availabilityStr = Offer::AVAILABILITY_ASK;
                        }
                        $offer->Availability = $availabilityStr;

                        $offer->CheckIn = $offerResponse->PeriodOfStay->CheckIn;
                        $offer->InitialData = $offerResponse->PackageVariantId;

                        $currency = new Currency();
                        $currency->Code = $offerResponse->attributes()->CurrencyCode;
                        $offer->Currency = $currency;
                        $offer->bookingCurrency = $currency->Code;

                        $checkInDateTime = new DateTimeImmutable($offer->CheckIn);
                        $checkOutDateTime = new DateTimeImmutable($offerResponse->PeriodOfStay->CheckOut);
                        $dif = $checkOutDateTime->diff($checkInDateTime)->days;

                        $offer->Days = $dif;
                        
                        $offer->Net = (float) $offerResponse->ProductPrice;
                        $offer->Gross = (float) $offerResponse->Gross;
                        $offer->InitialPrice = (float) $offerResponse->PriceNoRedd;
                        $offer->Comission = (float) $offerResponse->Commission;

                        $roomId = $offerResponse->BookingRoomTypes->Room->attributes()->Code;
                        
                        if ($offerResponse->Meals->Meal === null) {
                            continue;
                        }

                        $mealId = $offerResponse->Meals->Meal->attributes()->Code;

                        $hotelCheckIn = (string) $offerResponse->PeriodOfStay->CheckIn;
                        $hotelCheckOut = (string) $offerResponse->PeriodOfStay->CheckOut;

                        // Rooms
                        $room1 = new Room();
                        $room1->Id = $roomId;

                        $room1->CheckinBefore = $hotelCheckOut;
                        $room1->CheckinAfter = $hotelCheckIn;
                        $room1->Currency = $currency;
                        $room1->Availability = $offer->Availability;
                        $room1->InfoDescription = $offerResponse->OfferDescription;

                        $roomMerchType = new RoomMerchType();
                        $roomMerchType->Id = $roomId;
                        $roomMerchType->Title = $offerResponse->BookingRoomTypes->Room;

                        $merch = new RoomMerch();
                        $merch->Id = $roomId;
                        $merch->Title = $roomMerchType->Title;
                        $merch->Type = $roomMerchType;
                        $merch->Code = $roomId;
                        $merch->Name = $roomMerchType->Title;

                        $room1->Merch = $merch;
                        if (!empty($offerResponse->GrilaName)) {
                            $room1->InfoTitle = 'Grila: ' . $offerResponse->GrilaName;
                        }

                        $offer->Rooms = new RoomCollection([$room1]);

                        $offer->Item = $room1;

                        $mealItem = new MealItem();

                        $boardTypeName = $offerResponse->Meals->Meal;
                        if ($boardTypeName == 'SC') {
                            $boardTypeName = 'Ro';
                        }

                        // MealItem Merch
                        $boardMerch = new MealMerch();
                        $boardMerch->Title = $boardTypeName;

                        // MealItem
                        $mealItem->Merch = $boardMerch;
                        $mealItem->Currency = $currency;

                        $offer->MealItem = $mealItem;
                        $offerCode = $hotel->Product->ProductCode . '~' . $roomId . '~' . $mealId . '~' . $offer->CheckIn . '~' . $offer->Days . '~' . $offer->Net . '~' . $filter->rooms->first()->adults . '~' . ($ages ? implode('|', $ages->toArray()) : '');
                        $offer->Code = $offerCode;

                        $services = $offerResponse->PriceDetails->Services->Service;

                        $departureFlight = null;
                        $returnFlight = null;
                        foreach ($services as $service) {
                            $departureDate = (new DateTime($service->PeriodOfStay->CheckIn))->format('Y-m-d');
                            $arrivalDate = (new DateTime($service->PeriodOfStay->CheckOut))->format('Y-m-d');
                            if ((string) $service->Type === '7' && !empty($service->Departure->attributes())
                                && ($departureDate === $hotelCheckIn || $arrivalDate === $hotelCheckIn)) {
                                $departureFlight = $service;
                            }
                            if ((string) $service->Type === '7' && !empty((string) $service->Departure->attributes())
                                && ($departureDate === $hotelCheckOut || $arrivalDate === $hotelCheckOut)) {
                                $returnFlight = $service;
                            }
                        }

                        $departureFlightDate = $departureFlight->PeriodOfStay->CheckIn;
                        $departureFlightDateArrival = $departureFlight->PeriodOfStay->CheckOut;
                        $returnFlightDate = $returnFlight->PeriodOfStay->CheckIn;
                        $returnFlightDateArrival = $returnFlight->PeriodOfStay->CheckOut;

                        $departureDateTime = new DateTime($departureFlightDate);
                        //$arrivalDateTime = new DateTime($departureFlightDateArrival);
                        $returnDepartureDateTime = new DateTime($returnFlightDate);
                        //$returnArrivalDateTime =  new DateTime($returnFlightDateArrival);

                        // departure transport item merch
                        $departureTransportMerch = new TransportMerch();
                        $departureTransportMerch->Title = "Dus: " . $departureDateTime->format('d.m.Y');
                        $departureTransportMerch->Category = new TransportMerchCategory();
                        $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                        $departureTransportMerch->TransportType = $filter->transportTypes->first();
                        $departureTransportMerch->DepartureTime = $departureFlightDate;
                        $departureTransportMerch->ArrivalTime = $departureFlightDateArrival;

                        $departureTransportMerch->DepartureAirport = (string) $departureFlight->Departure->attributes()->Code;
                        $departureTransportMerch->ReturnAirport = (string) $returnFlight->Departure->attributes()->Code;

                        $departureCity = $cities->get($filter->departureCity);

                        $departureTransportMerch->From = new TransportMerchLocation();
                        $departureTransportMerch->From->City = $departureCity;

                        $arrivalCity = $cities->get($filter->cityId);

                        $departureTransportMerch->To = new TransportMerchLocation();
                        $departureTransportMerch->To->City = $arrivalCity;

                        $departureTransportItem = new DepartureTransportItem();
                        $departureTransportItem->Merch = $departureTransportMerch;
                        $departureTransportItem->Currency = $offer->Currency;
                        $departureTransportItem->DepartureDate = $departureFlightDate;
                        $departureTransportItem->ArrivalDate = $departureFlightDateArrival;
 
                        // return transport item
                        $returnTransportMerch = new TransportMerch();
                        $returnTransportMerch->Title = "Retur: " . $returnDepartureDateTime->format('d.m.Y');
                        $returnTransportMerch->Category = new TransportMerchCategory();
                        $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                        $returnTransportMerch->TransportType = $filter->transportTypes->first();
                        $returnTransportMerch->DepartureTime = $returnFlightDate;
                        $returnTransportMerch->ArrivalTime = $returnFlightDateArrival;

                        $returnTransportMerch->DepartureAirport = (string) $returnFlight->Departure->attributes()->Code;
                        $returnTransportMerch->ReturnAirport = (string) $departureFlight->Departure->attributes()->Code;

                        $returnDepartureCity = $cities->get($filter->cityId);
                        $returnArrivalCity = $cities->get($filter->departureCity);

                        $returnTransportMerch->From = new TransportMerchLocation();
                        $returnTransportMerch->From->City = $returnDepartureCity;

                        $returnTransportMerch->To = new TransportMerchLocation();
                        $returnTransportMerch->To->City = $returnArrivalCity;

                        $returnTransportItem = new ReturnTransportItem();
                        $returnTransportItem->Merch = $returnTransportMerch;
                        $returnTransportItem->Currency = $offer->Currency;
                        $returnTransportItem->DepartureDate = $returnFlightDate;
                        $returnTransportItem->ArrivalDate = $returnFlightDateArrival;

                        $departureTransportItem->Return = $returnTransportItem;

                        // add items to offer
                        $offer->Item = $room1;
                        $offer->DepartureTransportItem = $departureTransportItem;
                        $offer->ReturnTransportItem = $returnTransportItem;

                        $offer->Items = [];

                        $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory());
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory());
                        

                        $offers->add($offer);
                    }
                }
                $availability->Offers = $offers;
                $availabilities->add($availability);
            }
        }
        return $availabilities;
    }

    private function getCharterAvailabilityDates(): array
    {
        $cities = $this->apiGetCities();

        $data = [
            'Request' => [
                '[RequestType]' => 'getPackageNVRoutesRequest',
                'AuditInfo' => [
                    'RequestId' => '007',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                ],
                'RequestDetails' => [
                    'getPackageNVRoutesRequest' => '',
                    'Transport' => 'bus'
                ]
            ]
        ];

        $requestXml = Utils::arrayToXmlString($data);
        
        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $tries = 1;
        while ($response->getStatusCode() !== 200) {
            sleep(5);
            $tries++;
            $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            if ($tries >= 5) {
                break;
            }
        }
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

        $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $destCountries = $responseXml->getPackageNVRoutesResponse->Country;

        $availabilityDatesCollection = [];

        foreach ($destCountries as $destCountry) {
            foreach ($destCountry->Destinations->Destination as $destination) {
                foreach ($destination->Departures->Departure as $departure) {

                    $dates = new TransportDateCollection();

                    foreach ($departure->Dates->Date as $dateResp) {
                        if ((string)$dateResp->attributes()->TourOpCode !== $this->apiContext) {
                            continue;
                        }

                        $date = new TransportDate();
                        $date->Date = $dateResp;

                        $nightsArr = explode(',', $dateResp->attributes()->Nights);

                        $nights = new DateNightCollection();

                        foreach ($nightsArr as $nightNr) {
                            $night = new DateNight();
                        
                            $night->Nights = (int) $nightNr;
                            $nights->add($night);
                        }
                        $date->Nights = $nights;

                        $dates->add($date);
                    }
                    if (count($dates) === 0) {
                        continue;
                    }

                    $from = new TransportCity();

                    $city = $cities->get($departure->CityCode);



                    $from->City = $city;

                    $to = new TransportCity();
                    $city = $cities->get($destination->CityCode);



                    $to->City = $city;

                    $availabilityDates = new AvailabilityDates();
                    $availabilityDates->From = $from;
                    $availabilityDates->To = $to;

                    $transport = AvailabilityDates::TRANSPORT_TYPE_BUS;
                    $availabilityDates->Id = $transport . "~city|" . $from->City->Id . "~city|" . $to->City->Id;
                    $availabilityDates->Content = new TransportContent();

                    
                    $availabilityDates->Dates = $dates;
                    if ($availabilityDatesCollection->get($availabilityDates->Id)) {
                        throw new Exception($this->handle . ': duplicate date id');
                    }
                    $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
                }
            }
        }


        $data = [
            'Request' => [
                '[RequestType]' => 'getPackageNVRoutesRequest',
                'AuditInfo' => [
                    'RequestId' => '007',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                ],
                'RequestDetails' => [
                    'getPackageNVRoutesRequest' => '',
                    'Transport' => 'plane'

                ]
            ]
        ];

        $requestXml = Utils::arrayToXmlString($data);
        $options = ['body' => $requestXml];
        
        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $tries = 1;
        while ($response->getStatusCode() !== 200) {
            sleep(5);
            $tries++;
            $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            if ($tries >= 5) {
                break;
            }
        }

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

        $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $destCountries = $responseXml->getPackageNVRoutesResponse->Country;

        foreach ($destCountries as $destCountry) {
            foreach ($destCountry->Destinations->Destination as $destination) {
                foreach ($destination->Departures->Departure as $departure) {
                    
                    $dates = new TransportDateCollection();

                    foreach ($departure->Dates->Date as $dateResp) {
                        if ((string)$dateResp->attributes()->TourOpCode !== $this->apiContext) {
                            continue;
                        }

                        $date = new TransportDate();
                        $date->Date = $dateResp;

                        $nightsArr = explode(',', $dateResp->attributes()->Nights);

                        $nights = new DateNightCollection();

                        foreach ($nightsArr as $nightNr) {
                            $night = new DateNight();
                        
                            $night->Nights = (int) $nightNr;
                            $nights->add($night);
                        }
                        $date->Nights = $nights;

                        $dates->add($date);
                    }
                    if (count($dates) === 0) {
                        continue;
                    }

                    $from = new TransportCity();

                    $city = $cities->get($departure->CityCode);

                    if ($city === null) {
                        continue;
                    }

                    $from->City = $city;

                    $to = new TransportCity();
                    $city = $cities->get($destination->CityCode);

                    $to->City = $city;

                    $availabilityDates = new AvailabilityDates();
                    $availabilityDates->From = $from;
                    $availabilityDates->To = $to;

                    $transport = AvailabilityDates::TRANSPORT_TYPE_PLANE;
                    $availabilityDates->Id = $transport . "~city|" . $from->City->Id . "~city|" . $to->City->Id;
                    $availabilityDates->Content = new TransportContent();
                    $availabilityDates->TransportType = $transport;
                    
                    $availabilityDates->Dates = $dates;
                    if ($availabilityDatesCollection->get($availabilityDates->Id)) {
                        throw new Exception($this->handle . ': duplicate date id');
                    }
                    $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
                }
            }
        }
        return $availabilityDatesCollection;
    }

    private function getTourAvailabilityDates(): array
    {
        // get destinations
        $cities = $this->apiGetCities();

        $data = [
            'Request' => [
                '[RequestType]' => 'CircuitSearchCityRequest',
                'AuditInfo' => [
                    'RequestId' => '009',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                ],
                'RequestDetails' => [
                    'CircuitSearchCityRequest' => '',
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($data);
        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();
        
        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $responseDestinations = $responseXml->CircuitSearchCityResponse->Country;

        $availabilityDatesCollection = [];

        foreach ($responseDestinations as $country) {
            foreach ($country->Cities as $citiesResp) {
                foreach ($citiesResp->City as $cityResp) {
                    
                    $cityRequest = $cities->get((string) $cityResp->CityCode);
                    if ($cityRequest === null) {
                        continue;
                    }
                    $data = [
                        'Request' => [
                            '[RequestType]' => 'CircuitSearchRequest',
                            'AuditInfo' => [
                                'RequestId' => '010',
                                'RequestUser' => $this->username,
                                'RequestPass' => $this->password,
                                'RequestTime' => (new DateTime())->format('Y-m-d\TH:i:s')
                            ],
                            'RequestDetails' => [
                                'CircuitSearchRequest' => [
                                    'CountryCode' => (string) $country->CountryCode,
                                    'CityCode' => (string) $cityResp->CityCode,
                                    'CurrencyCode' => 'EUR',
                                    'Year' => date('Y'),
                                    'Month' => 13,
                                    'Rooms' => [
                                        'Room' => [
                                            '[Code]' => 'DB',
                                            '[NoAdults]' => 2
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $requestXml = Utils::arrayToXmlString($data);
                    $options = [
                        'body' => $requestXml,
                        'headers' => [
                            'Content-Type' => 'application/xml',
                        ]
                    ];

                    $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                    $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

                    $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;
            
                    if (!empty($responseXml->Errors->Error)) {
                        throw new Exception(json_encode($responseXml->Errors->Error));
                    }
                    $circuit = $responseXml->CircuitSearchResponse->Circuit;
                    if ($circuit->Variants === null) {
                        continue;
                    }

                    $from = new TransportCity();

                    $fromCityCode = $circuit->Variants->Variant->InfoCharter->DepArrCodLoc;

                    $fromCity = $cities->get($fromCityCode);

                    $from->City = $fromCity;

                    $to = new TransportCity();
                    $destinationcity = $cities->get((string) $cityResp->CityCode);

                    $to->City = $destinationcity;

                    $availabilityDates = new AvailabilityDates();
                    $availabilityDates->From = $from;
                    $availabilityDates->To = $to;

                    $dates = new TransportDateCollection();
                    $transport = null;

                    //todo: check daca vine multiple variants
                    if (isset($circuit->Variants->Variant->Services->Service)) {
                        foreach ($circuit->Variants->Variant as $variant) {

                            $date = new TransportDate();
                            $date->Date = $variant->InfoCharter->DepDate;

                            $nights = new DateNightCollection();
                            $night = new DateNight();
                        
                            $depDate = new DateTimeImmutable($date->Date);
                            $returnDate = new DateTimeImmutable($variant->InfoCharter->RetDate);

                            $nightPeriod = $returnDate->diff($depDate)->days;

                            $night->Nights = $nightPeriod;
                            $nights->add($night);
                            
                            $date->Nights = $nights;
                            $dates->add($date);

                            $services = $variant->Services->Service;
                            foreach ($services as $service) {
                                if ($service->Type == 7) {
                                    $transportResp = (string)$service->Transport;
                                    if ($transportResp === 'Bus') {
                                        $transport = AvailabilityDates::TRANSPORT_TYPE_BUS;
                                    } elseif ($transportResp === 'Plane') {
                                        $transport = AvailabilityDates::TRANSPORT_TYPE_PLANE;
                                    }
                                    break;
                                }
                            }
                        }
                    }

                    $availabilityDates->Id = $transport . "~city|" . $from->City->Id . "~city|" . $to->City->Id;
                    $availabilityDates->Content = new TransportContent();
                    $availabilityDates->TransportType = $transport;
                    
                    $availabilityDates->Dates = $dates;
                    if ($availabilityDatesCollection->get($availabilityDates->Id)) {
                        throw new Exception($this->handle . ': duplicate transport id');
                    }
                    $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
                }
            }
        }
        
        return $availabilityDatesCollection;
    }

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $availabilityDatesCollection = null;

        if ($filter->type === AvailabilityDatesFilter::CHARTER) {
            $availabilityDatesCollection = $this->getCharterAvailabilityDates();
        } else {
            $availabilityDatesCollection = $this->getTourAvailabilityDates();
        }
        
        return $availabilityDatesCollection;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        EuroSiteValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateAvailabilityFilter($filter);

        $availabilities = null;
        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            $availabilities = $this->getHotelOffers($filter);
        } elseif ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            $availabilities = $this->getCharterOffers($filter);
        } elseif($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
            $availabilities = $this->getTourOffers($filter);
        }
        
        return $availabilities;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        EuroSiteValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);
        $time = (new DateTime())->format('Y-m-d\TH:i:s');

        $passengers = [];
        $passengersReq = $post['args'][0]['Items'][0]['Passengers'];
        /** @var Passenger $passengerReq */
        foreach ($passengersReq as $passengerReq) {
            if ($passengerReq->Firstname !== '') {

                $paxName = [
                    'PaxName' => [
                        '[PaxType]' => $passengerReq->IsAdult ? 'adult' : 'child',
                        '[TGender]' => $passengerReq->IsAdult ? ($passengerReq->Gender === 'male' ? 'B' : 'F') : 'C',
                        '[DOB]' => $passengerReq->BirthDate,
                        $passengerReq->Firstname . ' ' . $passengerReq->Lastname
                    ]
                ];
                // if (!$passengerReq->IsAdult) {
                //     $today = new DateTime();
                //     $birthDate = new DateTime($passengerReq->BirthDate);
                //     $age
                //     $paxName['PaxName']['[ChildAge]'] = 
                // }
                
                $passengers[] = $paxName;
            }

        }

        $data = [
            'Request' => [
                '[RequestType]' => 'AddBookingRequest',
                'AuditInfo' => [
                    'RequestId' => '007',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => $time
                ],
                'RequestDetails' => [
                    'AddBookingRequest' => [
                        '[CurrencyCode]' => $filter->Items->first()->Offer_bookingCurrency,
                        'BookingName' => $filter->Items->first()->Offer_Code,
                        'BookingClientId' => $filter->Items->first()->Offer_Code,
                        'BookingItems' => [
                            'BookingItem' => [
                                '[ProductType]' => 'hotel',
                                'ItemClientId' => 1,
                                'TourOpCode' => 'EXM',
                                'HotelItem' => [
                                    'BookingAgent' => 'TravelFuse',
                                    'BookingClient' => 'TravelFuse',
                                    'CountryCode' => $filter->Items->first()->Hotel->Country_Code,
                                    'CityCode' => $filter->Items->first()->Hotel->City_Code,
                                    'ProductCode' => $post['args'][0]['Items'][0]['Hotel']['InTourOperatorId'],
                                    'Language' => 'RO',
                                    'PeriodOfStay' => [
                                        'CheckIn' => $post['args'][0]['Items'][0]['Room_CheckinAfter'],
                                        'CheckOut' => $post['args'][0]['Items'][0]['Room_CheckinBefore']
                                    ],
                                    'VariantId' => $filter->Items->first()->Offer_InitialData,
                                    'Rooms' => [
                                        'Room' => [
                                            '[Code]' => $filter->Items->first()->Room_Type_InTourOperatorId,
                                            '[NoAdults]' => $post['args'][0]['Params']['Adults'][0],
                                            '[NoChildren]' => (int) $filter->Params->Children->first(),
                                            'PaxNames' => $passengers
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($data);
        
        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $content = $response->getBody();

        $responseXml = simplexml_load_string($content)->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $bookingReferences = $responseXml->AddBookingResponse->BookingReferences;

        if (isset($responseXml->AddBookingResponse->BookingItems->BookingItem->Error->ErrorText)) {
            throw new Exception((string) $responseXml->AddBookingResponse->BookingItems->BookingItem->Error->ErrorText);
        }

        $bookingId = null;
        foreach ($bookingReferences->BookingReference as $reference) {
            if ((string) $reference->attributes()->Source === 'api') {
                $bookingId = (string) $reference;
            }
        }

        $booking = new Booking();
        $booking->Id = $bookingId;
        
        return [$booking, $content];
    }

    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    {
        EuroSiteValidator::make()
            ->validateAllCredentials($this->post)
            ->validateOfferCancelFeesFilter($filter);

        $hotels = $this->apiGetHotels();
        $hotel = $hotels->get($post['args'][0]['Hotel']['InTourOperatorId']);

        $time = (new DateTime())->format('Y-m-d\TH:i:s');
        $passengers = [];

        $adults = $post['args'][0]['rooms'][0]['adults'];
        $childrenAges = $post['args'][0]['rooms'][0]['childrenAges'];

        for ($i = 0; $i < $adults; $i++) {
            $passengers[] = [
                'PaxName' => [
                    '[PaxType]' => 'adult',
                    'Name'
                ]
            ];
        }

        if ($childrenAges !== null) {
            foreach ($childrenAges as $cAge) {
                if ($cAge !== '') {
                    $passengers[] =[
                        'PaxName' => [
                            '[PaxType]' => 'child',
                            '[ChildAge]' => $cAge,
                            'Name'
                        ]
                    ];
                }
            }
        }

        $bookingDataArr = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);

        $data = [
            'Request' => [
                '[RequestType]' => 'getItemFeesRequest',
                'AuditInfo' => [
                    'RequestId' => '006',
                    'RequestUser' => $this->username,
                    'RequestPass' => $this->password,
                    'RequestTime' => $time
                ],
                'RequestDetails' => [
                    'getItemFeesRequest' => [
                        '[CurrencyCode]' => $filter->SuppliedCurrency,
                        'BookingItems' => [[
                                'BookingItem' => [
                                    '[ProductType]' => 'hotel',
                                    'TourOpCode' => $this->apiContext,
                                    'HotelItem' => [
                                        'CountryCode' => $hotel->Address->City->Country->Code,
                                        'CityCode' => $hotel->Address->City->Id,
                                        'ProductCode' => $post['args'][0]['Hotel']['InTourOperatorId'],
                                        'PeriodOfStay' => [
                                            'CheckIn' => $post['args'][0]['CheckIn'],
                                            'CheckOut' => $post['args'][0]['CheckOut']
                                        ],
                                        'VariantId' => $bookingDataArr['VariantId'] ?? null,
                                        'Rooms' => [
                                            [
                                                'Room' => [
                                                    '[Code]' => $post['args'][0]['OriginalOffer']['Rooms'][0]['Id'],
                                                    '[NoAdults]' => $adults,
                                                    'PaxNames' => $passengers
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($data);
        $options = [
            'body' => $requestXml,
            'headers' => [
                'Content-Type' => 'application/xml',
            ]
        ];

        $client = HttpClient::create();

        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

        $responseXml = simplexml_load_string($response->getBody())->ResponseDetails;

        if (!empty($responseXml->Errors->Error)) {
            throw new Exception(json_encode($responseXml->Errors->Error));
        }

        $response = $responseXml->getItemFeesResponse;

        $fees = new OfferCancelFeeCollection();

        $currency = new Currency();
        $currency->Code = $filter->SuppliedCurrency;
        $today = (new DateTime())->format('Y-m-d');

        if (isset($response->ItemFees)) {
            foreach ($response->ItemFees->ItemFee->Fees->Fee as $fee) {
                if ((string) $fee->attributes()->Type === 'cancellation') {
                    $cp = new OfferCancelFee();
                    $cp->Currency = $currency;

                    if ((string) $fee->Value->attributes()->Procent === 'true') {
                        $price = $filter->OriginalOffer->Gross * ($fee->Value/100);
                    } else {
                        $price = (float) $fee->Value;
                    }
                    $cp->Price = $price;
                    
                    $cp->DateStart = isset($fee->FromDate) ? $fee->FromDate : $today;
                    $cp->DateEnd = $fee->ToDate;
                    $fees->add($cp);
                }
            }
        }

        return $fees;
    }
}
