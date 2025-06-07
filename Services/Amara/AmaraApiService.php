<?php

namespace Services\Amara;

use Service\IntegrationSupport\AbstractApiService;

class AmaraApiService extends AbstractApiService
{
    private string $hashKey = 'Amara@81O2#12e4_';
    private const TEST_HANDLE = 'localhost-amara_v2';

    private const s = 'http://www.w3.org/2003/05/soap-envelope';
    private const b = 'http://schemas.datacontract.org/2004/07/WebAPI.Model';
    private const c = 'http://schemas.datacontract.org/2004/07/KartagoBL.ExportXML.Oferte';
    private const d = 'http://schemas.microsoft.com/2003/10/Serialization/Arrays';
    
    public function apiDoBooking(BookHotelFilter $filter): array
    {
        AmaraValidator::make()
            ->validateAllCredentials($this->post)
            ->validateBookHotelFilter($filter);
        
        $bookingParams['bookingPrice'] = $filter->Items->get(0)->Offer_bookingPrice;
        $bookingParams['bookingCurrency'] = $filter->Items->get(0)->Offer_bookingCurrency;
        $bookingParams['roomCombinationDescription'] = $filter->Items->get(0)->Offer_roomCombinationPriceDescription;
        $bookingParams['roomCombinationId'] = $filter->Items->get(0)->Offer_roomCombinationId;

        $passengers = [];
        $passengersRequest = $post['args'][0]['Items'][0]['Passengers'];
        foreach ($passengersRequest as $passenger) {
            if (!empty($passenger['Firstname'])) {
                $age = (new DateTime())->diff(new DateTime($passenger['BirthDate']))->y;
                $isInfant = $age < 2 ? 1 : 0;

                $isMale = $passenger['Gender'] === 'male' ? 1 : 0;
                $isAdult = isset($passenger['IsAdult']) && $passenger['IsAdult'] === true ? 1 : 0;
                $passengerArr = [
                    'BirthDate' => $passenger['BirthDate'],
                    'FirstName' => $passenger['Firstname'],
                    'IsAdult' => $isAdult,
                    'IsInfant' => $isInfant,
                    'IsMale' => $isMale,
                    'LastName' => $passenger['Lastname']
                ];
                $passengers[] = $passengerArr;
            }
        }

        $bookingParams['passengers'] = $passengers;

        $url = $this->apiUrl . '/Reservations.svc';

        //$responseValidator = $this->requestold($url, 'ValidateBeforeReservation', $bookingParams);

        $client = HttpClient::create();

        $options['body'] = $this->makeRequestBody($url, 'ValidateBeforeReservation', $bookingParams);
        $options['headers'] = [
            'Content-Type' => 'application/soap+xml;charset=UTF-8'
        ];
        $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $content = $responseObj->getBody();

        $responseValidator = $this->getXmlBody($content);

        $message = (string)$responseValidator->ValidateBeforeReservationResponse->ValidateBeforeReservationResult->children(self::b)->ResultMessage;
        
        $booking = new Booking();
        if ($message !== 'OK') {
            return [$booking, $message];
        }

        //$response = $this->requestold($url,'MakeReservation', $bookingParams);
        $options['body'] = $this->makeRequestBody($url, 'MakeReservation', $bookingParams);

        $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $content = $responseObj->getBody();

        $response = $this->getXmlBody($content);

        $message = (string)$response->MakeReservationResponse->MakeReservationResult->children(self::b)->ResultMessage;

        if ($message !== 'OK') {
            return [$booking, $message];
        }

        $booking->Id = (string) $response->MakeReservationResponse->MakeReservationResult->children(self::b)->ReservationInfo->children(self::b)->ReservationNo;
        //$booking->rawResp = json_encode($response);

        $bookingCol = new BookingCollection();
        $bookingCol->add($booking);
        return [$booking, $content];
    }

    private function makeRequestBody(string $url, string $action, array $params = []): string
    {
        $hash = hash_hmac('md5',
            (
                strlen($useApiContext = ($this->apiContext)) . $useApiContext 
                .strlen(($useApiUsername = ($this->username))) . $useApiUsername 
                .strlen(($useApiPassword = ($this->password))) . $useApiPassword
            ),
            $this->hashKey
        );

        $action1 = '';
		if ($action === 'MakeReservation' || $action === 'ValidateBeforeReservation') {
			$action1 = 'IReservations';
         } else {
            $action1 = 'IOffer';
        }

        $requestArr = [
            'soap_--_Envelope' => [
                '[xmlns_--_soap]' => 'http://www.w3.org/2003/05/soap-envelope',
                '[xmlns_--_tem]' => 'http://tempuri.org/',
                '[xmlns_--_kar]' => 'http://schemas.datacontract.org/2004/07/KartagoBL.ExportXML.Oferte',
                '[xmlns_--_arr]' => 'http://schemas.microsoft.com/2003/10/Serialization/Arrays',
                '[xmlns_--_web]' => 'http://schemas.datacontract.org/2004/07/WebAPI.Model',
                'soap_--_Header' => [
                    '[xmlns_--_wsa]' => 'http://www.w3.org/2005/08/addressing',
                    'wsa_--_To' => $url,
                    'wsa_--_Action' => 'http://tempuri.org/'.$action1.'/'.$action
                ],
                'soap_--_Body' => [
                    'tem_--_'.$action => [
                        'tem_--_userToken' => [
                            'web_--_DealerCode' => $this->apiContext,
                            'web_--_Hash' => $hash,
                            'web_--_Password' => $this->password,
                            'web_--_UserName' => $this->username
                        ],
                    ]
                ]
            ]
        ];

        if ($action === 'OnlineSearch') {

            $childrenAges = [];
            $infants = 0;
            foreach ($params['childrenAges'] ?? [] as $age) {
                if ($age < 2) {
                    $infants++;
                } else {
                    $childrenAges[] = ['arr_--_int' => $age];
                }
            } 

            $requestArr['soap_--_Envelope']['soap_--_Body']['tem_--_'.$action]['tem_--_requestInfo'] = [
                
                'web_--_RequestedRooms' => [
                    'kar_--_OnlineSearchRoom' => [
                        'kar_--_AdultsNo' => [
                            $params['adults']
                        ],
                        'kar_--_ChildrenAges' => $childrenAges,
                        'kar_--_InfantsNo' => $infants
                    ]
                ],
                'web_--_SeasonTransportTimeTableID' => $params['transportId'],
                'web_--_UnificationCode' => $params['hotelId'] ?? ''
                
            ];
        } elseif ($action === 'MakeReservation' || $action === 'ValidateBeforeReservation') {
            
            $passengers = [];
            foreach ($params['passengers'] as $passenger) {
                $tourist = [
                    'web_--_BirthDate' => $passenger['BirthDate'],
                    'web_--_FirstName' => $passenger['FirstName'],
                    'web_--_IsAdult' => $passenger['IsAdult'],
                    'web_--_IsInfant' => $passenger['IsInfant'],
                    'web_--_IsMale' => $passenger['IsMale'],
                    'web_--_LastName' => $passenger['LastName'],
                ];
                $passengers[] = ['web_--_ReservationTourist' => $tourist];
            } 

            $requestArr['soap_--_Envelope']['soap_--_Body']['tem_--_'.$action]['tem_--_requestInfo'] = [
                'web_--_BookIfOnRequest' => true,
                'web_--_CachedPrice' => $params['bookingPrice'],
                'web_--_CachedPriceCurrency' => $params['bookingCurrency'],
                'web_--_Rooms' => [
                    'web_--_ReservationRoom' => [
                        'web_--_RoomCombinationDescription' => $params['roomCombinationDescription'],
                        'web_--_RoomCombinationID' => $params['roomCombinationId'],
                        'web_--_Tourists' => $passengers
                    ]
                ]
            ];
        } elseif ($action === 'DownloadPictureByUnificationCode') {
            $requestArr['soap_--_Envelope']['soap_--_Body']['tem_--_'.$action][] = [
                'tem_--_unificationCode' => $params['unificationCode'],
                'tem_--_pictureName' => htmlspecialchars($params['pictureName'])
            ];
        }

        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        return $xmlString;
    }

    private function clearContent(string $content): string 
    {
        $pos = strpos($content, '<s:Envelope');
        $xml =  substr($content, $pos);
        $posEnd = strpos($xml, '</s:Envelope>');
        $xml = substr($xml, 0, $posEnd + strlen('</s:Envelope>'));
        return $xml;
    }

    private function getXmlBody(string $content)
    {
        $content = $this->clearContent($content);
        $content = @simplexml_load_string($content);

        if ($content === false) {
            return false;
        }
        $response = $content
            ->children(self::s)
            ->Body
            ->children();
        return $response;
    }

    public function apiGetCountries(): array
    {
        $cities = $this->apiGetCities();

        $countries = [];

        /** @var City $city */
        foreach ($cities as $city) {
            $countries->put($city->Country->Id, $city->Country);
        }

        return $countries;
    }

    public function apiGetCities(CitiesFilter $params = null): array
    {
        $response = $this->getRoutesInfoXml();

        $routes = $response
            ->GetRoutesInfoResponse
            ->GetRoutesInfoResult->children(self::b)
            ->Routes->children(self::c)
            ->RouteInfo;
    
        $cities = [];

        $romania = new Country();
        $romania->Id = 'RO';
        $romania->Code = $romania->Id;
        $romania->Name = 'Romania';

        foreach ($routes as $route) {
            $cityRomania = new City();
            $cityRomania->Id = $route->FromCode;
            $cityRomania->Name = $route->From;
            $cityRomania->Country = $romania;

            $cities->put($cityRomania->Id, $cityRomania);

            $country = new Country();
            $country->Name = $route->ToCountry;

            $cc = $route->ToCountryCode;
            if ($country->Name === 'TUNISIA') {
                $cc = 'TN';
            }

            $country->Code = $cc;
            $country->Id = $country->Code;
            
            $city = new City();
            $city->Id = $route->ToCode;
            $city->Name = $route->To;
            $city->Country = $country;

            $cities->put($city->Id, $city);
        }
            
        return $cities;
    }

    public function apiGetHotels(): []
    {
        $response = $this->getRoutesInfoXml();

        $hotelsInfo = $response
            ->GetRoutesInfoResponse
            ->GetRoutesInfoResult->children(self::b)
            ->DestinationHotels->children(self::c)
            ->DestinationHotelsInfo;

        $hotelCollection = [];
        $url = $this->apiUrl . '/Offer.svc?singleWsdl';

        $cities = $this->apiGetCities();
        $client = HttpClient::create();
        $options['headers'] = [
            'Content-Type' => 'application/soap+xml;charset=UTF-8'
        ];

        foreach ($hotelsInfo as $destinationHotel) {
            if (!isset($destinationHotel->Hotels->HotelInfo)) {
                continue;
            }
            $hotels = $destinationHotel->Hotels->HotelInfo;
            foreach ($hotels as $hotelResponse) {
                $hotel = new Hotel();
                $hotel->Id = $hotelResponse->UnificationCode;

                $hotel->Name = $hotelResponse->HotelName;
                $hotel->Stars = (int) $hotelResponse->Classification;

                $hcontent = new HotelContent();
                $hcontent->Content = nl2br($hotelResponse->Description);
                $hotelImageGallery = new HotelImageGallery();

                $galleryItems = new HotelImageGalleryItemCollection();

                if (isset($hotelResponse->children(self::c)->PictureResourcesFileNames->children(self::d)->string)) {
                    foreach ($hotelResponse->children(self::c)->PictureResourcesFileNames->children(self::d)->string as $photo) {
                        $hotelImageGalleryItem = new HotelImageGalleryItem();
                        $photo = (string) $photo;
                        if (strpos($photo, 'http') !== false) {

                            $hotelImageGalleryItem->RemoteUrl = $photo;
                            $galleryItems->add($hotelImageGalleryItem);

                        } else {
                            $path = '/Storage/Downloads/' . $this->handle . '/images/' . $hotel->Id . '/';
                            $dir = __DIR__ . '/../..' . $path;

                            if (!file_exists($dir . $photo)) {
                                if (!is_dir($dir)) {
                                    mkdir($dir, 0755, true);
                                }
                                $params['unificationCode'] = $hotel->Id;
                                $params['pictureName'] = $photo;
                                //$response = $this->requestold($url, 'DownloadPictureByUnificationCode', $params);

                                $options['body'] = $this->makeRequestBody($url, 'DownloadPictureByUnificationCode', $params);

                                $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                                $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
                                $content = $responseObj->getBody();

                                $response = $this->getXmlBody($content);

                                $contentResponse = (string) $response->DownloadPictureByUnificationCodeResponse->DownloadPictureByUnificationCodeResult->children(self::b)->FileContent;

                                file_put_contents($dir . $photo, base64_decode($contentResponse));
                            }

                            $imgUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' .$_SERVER['SERVER_NAME'] . env('APP_FOLDER') . $path . $photo;

                            $hotelImageGalleryItem->RemoteUrl = $imgUrl;
                            $galleryItems->add($hotelImageGalleryItem);

                            if (env('APP_ENV') === 'local' || env('APP_ENV') === 'dev') {
                                break;
                            }
                        }
                    }
                }

                $hotelImageGallery->Items = $galleryItems;

                $hcontent->ImageGallery = $hotelImageGallery;
                $hotel->Content = $hcontent;

                $address = new HotelAddress();
                $address->Longitude = $hotelResponse->Longitude;
                $address->Latitude = $hotelResponse->Latitude;
                $city = $cities->first(fn(City $city) => $city->Id === (string) $destinationHotel->DestinationCode);
                $address->City = $city;
                $hotel->Address = $address;

                $hotelCollection->put($hotel->Id, $hotel);
            }
        }

        return $hotelCollection;
    }
    /*
    public function getHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        
        AmaraValidator::make()->validateAllCredentials($this->post);
        Validator::make()->validateHotelDetailsFilter($filter);

        $hotels = $this->getHotels();
        $hotel = $hotels->get($filter->hotelId);
        $url = $this->apiUrl . '/Offer.svc?singleWsdl';

        $galleryItems = new HotelImageGalleryItemCollection();

        foreach ($hotel->Content->ImageGallery->Items as $photo) {
            $hotelImageGalleryItem = new HotelImageGalleryItem();
            $params['unificationCode'] = $filter->hotelId;
            $params['pictureName'] = $photo->RemoteUrl;
            $response = $this->getResponse('DownloadPictureByUnificationCode', $url, $params);
            $contentResponse = $response->DownloadPictureByUnificationCodeResult->FileContent;

            $path = '/Storage/Downloads/' . $this->handle . '/images/' . $filter->hotelId . '/';
            $dir = __DIR__ . '/../..' . $path;

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($dir . $photo->RemoteUrl, base64_decode($contentResponse));

            $imgUrl = $_SERVER['SERVER_NAME'] . env('APP_FOLDER') . $path . $photo->RemoteUrl;

            $hotelImageGalleryItem->RemoteUrl = $imgUrl;
            $galleryItems->add($hotelImageGalleryItem);
        }

        $hotel->Content->ImageGallery->Items = $galleryItems;

        return $hotel;
    }
    */

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    {
        AmaraValidator::make()->validateAllCredentials($this->post);

        $availabilityDatesCollection = [];

        $cities = $this->apiGetCities();

        $response = $this->getRoutesInfoXml();
        
        $routes = $response
            ->GetRoutesInfoResponse
            ->GetRoutesInfoResult->children(self::b)
            ->Routes->children(self::c)
            ->RouteInfo;
        $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;

        foreach ($routes as $route) {
            
            $transportCityFrom = new TransportCity();
            $transportCityFrom->City = $cities->first(fn(City $city) => $city->Id === (string)$route->FromCode);
            
            $transportCityTo = new TransportCity();
            $transportCityTo->City = $cities->first(fn(City $city) => $city->Id === (string)$route->ToCode);
           
            $id = $transportType . "~city|" . $transportCityFrom->City->Id . "~city|" . $transportCityTo->City->Id;

            $existingAvailDates = $availabilityDatesCollection->get($id);

            $departureDates = $route->Departures->DepartureInfo;

            if ($existingAvailDates === null) {
                $availabilityDates = new AvailabilityDates();
                $availabilityDates->From = $transportCityFrom;
                $availabilityDates->To = $transportCityTo;
                $availabilityDates->Id = $id;
                $availabilityDates->TransportType = $transportType;
    
                $availabilityDates->Content = new TransportContent();
    
                $transportDateCollection = new TransportDateCollection();
                
                foreach ($departureDates as $departureDate) {
                    $transportDate = new TransportDate();
                    $departureDateTime = (new DateTime($departureDate->DepartureDate))->setTime(0,0);
                    $transportDate->Date = $departureDateTime->format('Y-m-d');
                    $nightsInt = (int) $departureDate->TourPeriod;
        
                    // check if date is already present
                    $transportDateFound = $transportDateCollection->get($transportDate->Date);
                    if ($transportDateFound !== null) {
                        $nights = $transportDateFound->Nights;
                        $night = new DateNight();
                        $night->Nights = $nightsInt;
                        $nights->add($night);
                        $transportDateFound->Nights = $nights;
        
                        $transportDateCollection->put($transportDate->Date, $transportDateFound);
                    } else {
                        $nights = new DateNightCollection();
                        $night = new DateNight();
                        $night->Nights = $nightsInt;
                        $nights->add($night);
        
                        $transportDate->Nights = $nights;
        
                        $transportDateCollection->put($transportDate->Date, $transportDate);
                    }
                }
    
                $availabilityDates->Dates = $transportDateCollection;
                $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
            } else {
                
                $existingTransportDateCollection = $existingAvailDates->Dates;
                foreach ($departureDates as $departureDate) {
                    $transportDate = new TransportDate();
                    $departureDateTime = (new DateTime($departureDate->DepartureDate))->setTime(0,0);
                    $transportDate->Date = $departureDateTime->format('Y-m-d');
                    $nightsInt = (int) $departureDate->TourPeriod;
        
                    // check if date is already present
                    $transportDateFound = $existingTransportDateCollection->get($transportDate->Date);
                    if ($transportDateFound !== null) {
                        $nights = $transportDateFound->Nights;
                        $night = new DateNight();
                        $night->Nights = $nightsInt;
                        $nights->add($night);
                        $transportDateFound->Nights = $nights;
        
                        $existingTransportDateCollection->put($transportDate->Date, $transportDateFound);
                    } else {
                        $nights = new DateNightCollection();
                        $night = new DateNight();
                        $night->Nights = $nightsInt;
                        $nights->add($night);
        
                        $transportDate->Nights = $nights;
        
                        $existingTransportDateCollection->put($transportDate->Date, $transportDate);
                    }
                    $existingAvailDates->Dates = $existingTransportDateCollection;
                    $availabilityDatesCollection->put($existingAvailDates->Id, $existingAvailDates);
                }
            }
        }

        return $availabilityDatesCollection;
    }

    public function apiTestConnection(): bool
    {
        $ok = false;
        $url = $this->apiUrl . '/Offer.svc';

        $client = HttpClient::create();

        $options['body'] = $this->makeRequestBody($url, 'OnlineSearch', ['adults' => 2, 'children' => 0, 'childrenAges' => [], 'transportId' => 123]);
        $options['headers'] = [
            'Content-Type' => 'application/soap+xml;charset=UTF-8'
        ];

        $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $content = $responseObj->getBody();

        $responseSearch = $this->getXmlBody($content);
        if (!$responseSearch) {
            return false;
        }

        //$responseSearch = $this->requestold($url, 'OnlineSearch', $offerSearchParams);

        //$result = $this->requestold($url, 'OnlineSearch', ['adults' => 2, 'children' => 0, 'childrenAges' => null, 'transportId' => 123]);
        if ((string)$responseSearch->OnlineSearchResponse->OnlineSearchResult->children(self::b)->ResultMessage === 'OK') {
            $ok = true;
        }
        return $ok;
    }

    public function getRoutesInfoXml(): SimpleXMLElement
    {
        AmaraValidator::make()->validateAllCredentials($this->post);
        
        $fileName = 'routesInfo';

        $content = Utils::getFromCache($this, $fileName);

        $response = null;
        if ($content === null) {

            $client = HttpClient::create();
        
            $url = $this->apiUrl . '/Offer.svc';
            $options['body'] = $this->makeRequestBody($url, 'GetRoutesInfo');
            $options['headers'] = [
                'Content-Type' => 'application/soap+xml;charset=UTF-8'
            ];

            $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
            $content = $responseObj->getBody();

            $response = $this->getXmlBody($content);

            // check content, if content is ok, write into file
            if (!isset($response->GetRoutesInfoResponse->GetRoutesInfoResult->children(self::b)->Routes->children(self::c)->RouteInfo)) {
                $content = Utils::getFromCache($this, $fileName, true);
            } else {
                Utils::writeToCache($this, $fileName, $content);
            }
        } else {
            $response = $this->getXmlBody($content);
        }

	    return $response;
    }

    /*
    public function requestold(string $url, string $method = '', array $params = []): stdClass
    {
        // add params
        $paramsSoap = [
            'trace' => 1,
            'soap_version' => SOAP_1_2,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'style' => SOAP_DOCUMENT,
            'encoding' => SOAP_LITERAL,
            'exceptions' => false,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS
        ];

        // init custom soap client
        $soapClient = new AmaraSoap($url, $paramsSoap);

        // setup needed params
        $soapClient->currentRequestUrlWSDL = $url;
        $soapClient->currentRequestDealerCode = ($this->apiContext);
        $soapClient->currentRequestHash = hash_hmac('md5', 
            (strlen($useApiContext = ($this->apiContext)) . $useApiContext 
            . strlen(($useApiUsername = ($this->username))) . $useApiUsername 
            . strlen(($useApiPassword = ($this->password))) . $useApiPassword), $this->hashKey);
        $soapClient->currentRequestPassword = $useApiPassword;
        $soapClient->currentRequestUsername = $useApiUsername;
        $soapClient->currentRequestMethod = $method;
        $soapClient->currentRequestUrl = str_replace('?singleWsdl', '', ($url));
        $soapClient->handle = $this->handle;

        if (isset($params['adults'])) {
            $soapClient->adults = $params['adults'];
            $soapClient->childrenAges = $params['childrenAges'];
            $soapClient->transportId = $params['transportId'];
            $soapClient->hotelId = $params['hotelId'] ?? '';
        }

        if ($method === 'MakeReservation' || $method === 'ValidateBeforeReservation') {
            $soapClient->bookingPrice = $params['bookingPrice'];
            $soapClient->bookingCurrency = $params['bookingCurrency'];
            $soapClient->roomCombinationDescription = $params['roomCombinationDescription'];
            $soapClient->roomCombinationId = $params['roomCombinationId'];
            $soapClient->passengers = $params['passengers'];
        }

        if ($method === 'DownloadPictureByUnificationCode') {
            $soapClient->unificationCode = $params['unificationCode'];
            $soapClient->pictureName = $params['pictureName'];
        }

        // request and get response
        $response = $soapClient->{$method}();


	    return $response;
    }

    private function addResponse(AmaraSoap $soapClient, $url): void
    {
        $requestBody = $soapClient->getRequestXml();
        $responseBodySoap = $soapClient->__getLastResponse();

        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;
        try {
            $dom->loadXML($responseBodySoap);
            $responseBody = $dom->saveXML();
        } catch (Exception $e) {
            $responseBody = $responseBodySoap;
        }

        $this->showRequest(HttpClient::METHOD_POST, $url, ['body' => $requestBody], $responseBody, 0);
    }*/

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        Validator::make()->validateAllCredentials($this->post);
        AmaraValidator::make()->validateAvailabilityFilter($filter);

        $offerSearchParams['adults'] = $filter->rooms->get(0)->adults;
        $offerSearchParams['childrenAges'] = $filter->rooms->get(0)->childrenAges;

        if (isset($filter->hotelId)) {
            $offerSearchParams['hotelId'] = $filter->hotelId;
        }

        $url = $this->apiUrl . '/Offer.svc';
        
        $response = $this->getRoutesInfoXml();

        $routes = $response
            ->GetRoutesInfoResponse
            ->GetRoutesInfoResult->children(self::b)
            ->Routes->children(self::c)
            ->RouteInfo;

        // find a flight
        // get route id
        $route = null;
        $isFound = false;
        foreach ($routes as $routeResponse) {
            foreach ($routeResponse->Departures->DepartureInfo as $departureInfo) {
                $departureDate = (new DateTime($departureInfo->DepartureDate))->format('Y-m-d');

                if ($departureDate === $filter->checkIn
                    && (string)$departureInfo->TourPeriod == $filter->days
                    && (string)$departureInfo->FromCode === $filter->departureCity
                    && (string)$departureInfo->ToCode == $filter->cityId
                ) {
                    $route = $departureInfo;
                    $isFound = true;
                    break;
                }
            }
            if ($isFound) {
                break;
            }
        }

        $availabilityCollection = [];

        if ($route === null) {
            // flight not found
            return $availabilityCollection;
        }

        $offerSearchParams['transportId'] = $route->SeasonTransportTimeTableID;


        $options['body'] = $this->makeRequestBody($url, 'OnlineSearch', $offerSearchParams);
        $options['headers'] = [
            'Content-Type' => 'application/soap+xml;charset=UTF-8'
        ];

        $client = HttpClient::create();
        $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $content = $responseObj->getBody();

        $responseSearch = $this->getXmlBody($content);

        //$responseSearch = $this->requestold($url, 'OnlineSearch', $offerSearchParams);

        $hotels = $responseSearch
            ->OnlineSearchResponse
            ->OnlineSearchResult->children(self::b)
            ->Hotels->children(self::c)
            ->OnlineSearchHotel;

        $cities = $this->apiGetCities();

        foreach ($hotels as $hotelRoom) {

            $availability = new Availability();

            $availability->Id = $hotelRoom->UnificationCode;

            $offers = new OfferCollection();

            $offer = new Offer();
            $currency = new Currency();
            $currency->Code = $hotelRoom->Currency;

            $offer->Availability = Offer::AVAILABILITY_YES;
            $roomId = preg_replace('/\s+/', '', $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->RoomType);

            $mealCode = preg_replace('/\s+/', '', $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->BoardTypeName);

            $price = (float) $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->Price;
            $initialPrice = (float) $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->CatalogPrice;
            
            $offer->Code = $availability->Id . '~' 
                . $roomId . '~' 
                . $mealCode . '~' 
                . $filter->checkIn . '~' 
                . $filter->days . '~' 
                . $price . '~'
                . $filter->rooms->get(0)->adults
                . ($filter->rooms->get(0)->childrenAges ? '~' .implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '')
            ;
            
            $offer->roomCombinationId = $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->RoomCombinationPriceID;
            $offer->roomCombinationPriceDescription = $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->RoomCombinationPriceDescription;

            $departureDate = (new DateTimeImmutable($hotelRoom->DepartureDate));
            $offer->CheckIn = $departureDate->format('Y-m-d');

            $offer->Currency = $currency;
            $returnDate = (new DateTimeImmutable($hotelRoom->ReturnDate));
            $returnDateZero = $returnDate->setTime(0, 0);
            $offer->Days = $returnDateZero->diff($departureDate->setTime(0, 0))->d;
            
            $taxes = 0;

            $offer->Net = $price;
            $offer->Gross = $price;
            $offer->InitialPrice = $initialPrice;
            $offer->Comission = $taxes;
            $offer->bookingPrice = $offer->Net;
            $offer->bookingCurrency = $offer->Currency->Code;
            
            // Rooms
            $room1 = new Room();
            $room1->Id = $roomId;
            $room1->CheckinBefore = $returnDate->format('Y-m-d');
            $room1->CheckinAfter = $offer->CheckIn;

            $room1->Currency = $offer->Currency;
            $room1->Quantity = 1;
            $room1->Availability = $offer->Availability;
            $room1->InfoDescription = $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->SpecialOfferValidity;

            $merch = new RoomMerch();
            $merch->Id = $roomId;
            $merch->Title = $hotelRoom->RoomTypeName;

            $merchType = new RoomMerchType();
            $merchType->Id = $roomId;
            $merchType->Title = $merch->Title;
            $merch->Type = $merchType;
            $merch->Code = $merch->Id;
            $merch->Name = $merch->Title;

            $room1->Merch = $merch;

            $offer->Rooms = new RoomCollection([$room1]);

            $offer->Item = $room1;

            $mealItem = new MealItem();
            $mealItem->Currency = $offer->Currency;

            $boardTypeName = $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->BoardTypeName;

            // MealItem Merch
            $boardMerch = new MealMerch();
            $boardMerch->Title = $boardTypeName;

            $boardMerchType = new MealMerchType();
            $boardMerchType->Id = $mealCode;
            $boardMerchType->Title = $boardTypeName;
            $boardMerch->Type = $boardMerchType;

            // MealItem
            $mealItem->Merch = $boardMerch;

            $offer->MealItem = $mealItem;

            $departureFlightDate = (new DateTime($route->DepartureDate))->format('Y-m-d H:i');
            $departureFlightDateArrival = (new DateTime($route->DepatureArrival))->format('Y-m-d H:i');

            $returnFlightDate = (new DateTime($route->ReturnDate))->format('Y-m-d H:i');
            $returnFlightDateArrival = (new DateTime($route->ReturnArrival))->format('Y-m-d H:i');

            // departure transport item merch
            $departureTransportMerch = new TransportMerch();
            $departureTransportMerch->Title = "Dus: ".$departureDate->format('d.m.Y');
            $departureTransportMerch->Category = new TransportMerchCategory();
            $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
            $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
            $departureTransportMerch->DepartureTime = $departureFlightDate;
            $departureTransportMerch->ArrivalTime = $departureFlightDateArrival;
            
            $departureTransportMerch->DepartureAirport = $route->FromCode;
            $departureTransportMerch->ReturnAirport = $route->ToCode;

            $departureTransportMerch->From = new TransportMerchLocation();
            $departureTransportMerch->From->City = $cities->get($route->FromCode);

            $departureTransportMerch->To = new TransportMerchLocation();
            $departureTransportMerch->To->City = $cities->get($route->ToCode);

            $departureTransportItem = new DepartureTransportItem();
            $departureTransportItem->Merch = $departureTransportMerch;
            $departureTransportItem->Quantity = 1;
            $departureTransportItem->Currency = $offer->Currency;
            $departureTransportItem->UnitPrice = 0;
            $departureTransportItem->Gross = 0;
            $departureTransportItem->Net = 0;
            $departureTransportItem->InitialPrice = 0;
            $departureTransportItem->DepartureDate = $offer->CheckIn;
            $departureTransportItem->ArrivalDate = $offer->CheckIn;

            // return transport item
            $returnTransportMerch = new TransportMerch();
            $returnTransportMerch->Title = "Retur: ".$returnDate->format('d.m.Y');
            $returnTransportMerch->Category = new TransportMerchCategory();
            $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
            $returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
            $returnTransportMerch->DepartureTime = $returnFlightDate;
            $returnTransportMerch->ArrivalTime = $returnFlightDateArrival;

            $returnTransportMerch->DepartureAirport = $route->ToCode;
            $returnTransportMerch->ReturnAirport = $route->FromCode;

            $returnTransportMerch->From = new TransportMerchLocation();
            $returnTransportMerch->From->City = $cities->get($route->ToCode);

            $returnTransportMerch->To = new TransportMerchLocation();
            $returnTransportMerch->To->City = $cities->get($route->FromCode);

            $returnTransportItem = new ReturnTransportItem();
            $returnTransportItem->Merch = $returnTransportMerch;
            $returnTransportItem->Quantity = 1;
            $returnTransportItem->Currency = $offer->Currency;
            $returnTransportItem->UnitPrice = 0;
            $returnTransportItem->Gross = 0;
            $returnTransportItem->Net = 0;
            $returnTransportItem->InitialPrice = 0;
            $returnTransportItem->DepartureDate = $returnDate->format('Y-m-d');
            $returnTransportItem->ArrivalDate = $returnDate->format('Y-m-d');

            $departureTransportItem->Return = $returnTransportItem;

            // add items to offer
            $offer->Item = $room1;
            $offer->DepartureTransportItem = $departureTransportItem;
            $offer->ReturnTransportItem = $returnTransportItem;

            $offer->Items = [];

            $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);

            if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE) {
                $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
            }

            $pt = $hotelRoom->Rooms->RequestedRoomStructure[0]->Rooms->Room[0]->PaymentTerms;

            $today = (new DateTimeImmutable())->setTime(0, 0);
            $cancelFeesCol = new OfferCancelFeeCollection();
            $paymentsPol = new OfferPaymentPolicyCollection();

            if (isset($pt->PaymentTerm )) {
                $i = 0;
                $endDate = new DateTimeImmutable();
                $payments = $pt->PaymentTerm;
                $priceCp = 0;
                $payEndFirst = new DateTimeImmutable();
                $payAfterDT = new DateTimeImmutable();
                for ($j = 0; $j < count($payments); $j++) {
                    $i++;

                    $paymentPol = new OfferPaymentPolicy();

                    $paymentPol->Currency = $currency;
                    $percentNr = (float) $payments[$j]->AmountPercent;
                    $percent = $percentNr / 100;
                    
                    $paymentPol->Amount = $offer->Net * $percent;

                    $daysOffset = $payments[$j]->DaysOffset;

                    $payUntilDT = null;
                    if ((string)$payments[$j]->OffsetBy === 'DEPARTURE_DATE') {
                        $payUntilDT = $departureDate->modify($daysOffset . ' day')->setTime(0, 0);
                    } elseif ((string)$payments[$j]->OffsetBy === 'CONFIRMATION_DATE') {
                        $payUntilDT = $today->modify($daysOffset . ' day')->setTime(0, 0);
                    } else {
                        $payUntilDT = new DateTimeImmutable((string)$payments[$j]->OffsetBy);
                    }

                    $paymentPol->PayAfter = $payAfterDT->format('Y-m-d');
                    $paymentPol->PayUntil = $payUntilDT->format('Y-m-d');
                    $payAfterDT = $payUntilDT->modify('+1day');

                    $paymentsPol->add($paymentPol);

                    $payEnd = new DateTimeImmutable();
                    if (isset($payments[$j + 1])) {
                        $days = $payments[$j + 1]->DaysOffset;

                        if ((string)$payments[$j + 1]->OffsetBy === 'DEPARTURE_DATE') {
                            $payEnd = $departureDate->modify($days . ' day');
                        } elseif ((string)$payments[$j + 1]->OffsetBy === 'CONFIRMATION_DATE') {
                            $payEnd = $today->modify($days . ' day');
                        } else {
                            $payOffsetNext = new DateTimeImmutable((string)$payments[$j + 1]->OffsetBy);
                            $payEnd = $payOffsetNext->modify($days . ' day');
                        }
                    } else {
                        $payEnd = $departureDate;
                    }

                    if ($i === 1) {
                        $daysFirst = $payments[$j]->DaysOffset;
                        if ((string)$payments[$j]->OffsetBy === 'DEPARTURE_DATE') {
                            $payEndFirst = $departureDate->modify($daysFirst . ' day')->setTime(0, 0);
                        } elseif ((string)$payments[$j]->OffsetBy === 'CONFIRMATION_DATE') {
                            $payEndFirst = $today->modify($daysFirst . ' day')->setTime(0, 0);
                        } else {
                            $payOffsetNext = new DateTimeImmutable((string)$payments[$j]->OffsetBy);
                            $payEndFirst = $payOffsetNext->modify($daysFirst . ' day')->setTime(0, 0);
                        }

                        if ($today < $payEndFirst) {
                            // $cancelPolicy = new OfferCancelFee();
                            // $cancelPolicy->Price = 0;
                            // $cancelPolicy->Currency = $currency;
                            // $cancelPolicy->DateStart = $today->format('Y-m-d');
                            // $cancelPolicy->DateEnd = $payEndFirst->format('Y-m-d');
                            // $cancelFeesCol->add($cancelPolicy);
                            $endDate = new DateTimeImmutable($payEndFirst->format('Y-m-d'));
                        }
                    }
 
                    $priceCp = $priceCp + $offer->Net * $percent;

                    $cancelPolicy = new OfferCancelFee();
                    $cancelPolicy->Price = $priceCp;
                    $cancelPolicy->Currency = $currency;

                    $payStart = new DateTimeImmutable();

                    if ($endDate->modify('+1 day') >= $departureDate) {
                        $payStart = $departureDate;
                    } else {
                        $payStart = $endDate->modify('+1 day');
                    }

                    if ($today >= $payEndFirst && $i === 1) {
                        $payStart = $today;
                        $payEnd = $today;
                    }

                    $cancelPolicy->DateStart = $payStart->format('Y-m-d');
                    
                    $cancelPolicy->DateEnd = $payEnd->format('Y-m-d');
                    
                    $cancelFeesCol->add($cancelPolicy);
                    $endDate = $payEnd;
                }
            }

            $offer->CancelFees = $cancelFeesCol;
            $offer->Payments = $paymentsPol;
            
            $hotelInList = $availabilityCollection->get($availability->Id);

            // if already present, add offer to hotel
            if ($hotelInList !== null) {
                $hotelInList->Offers->put($offer->Code, $offer);
                $availabilityCollection->put($hotelInList->Id, $hotelInList);

            } else {
                $offers = new OfferCollection();
                $offers->put($offer->Code, $offer);
                $availability->Offers = $offers;

                $availabilityCollection->put($availability->Id, $availability);
            }
        }

        return $availabilityCollection;
    }
}