<?php

namespace Integrations\GoGlobal;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\MealMerchType;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\ReturnTransportItem;
use App\Entities\Availability\Room;
use App\Entities\Availability\RoomCollection;
use App\Entities\Availability\RoomMerch;
use App\Entities\Availability\RoomMerchType;
use App\Entities\Availability\TransportMerch;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\ContactPerson;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\RoomType;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\StringCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Log;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use SimpleXMLElement;
use SoapClient;
use Utils\Utils;

class GoGlobalApiService extends AbstractApiService
{
    //private const TEST_HANDLE = 'localhost-goglobal_v2';

    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetCountries(): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $cities = $this->apiGetCities();
        $list = [];

        /** @var City $v */
        foreach ($cities as $v) {
            $country = $v->Country;
            $list->put($country->Id, $country);
        }

        return $list;
    }

    public function apiGetCities(?CitiesFilter $params = null): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $fileCity = 'cities';
        $citiesJson = Utils::getFromCache($this, $fileCity);

        if ($citiesJson === null) {

            $client = HttpClient::create(['user_agent' => 'curl/' . curl_version()['version']]);

            $pass = base64_encode($this->username . ':' . $this->password);
            $basic = 'Basic ' . $pass;

            $options['headers'] = [
                'Authorization' => $basic,
            ];
            $url = 'https://static-data.tourismcloudservice.com/propsdata/Destinations/compress/false';

            $response = $client->request(HttpClient::METHOD_GET, $url, $options);

            $content = $response->getBody();
            $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $response->getStatusCode());

            $downloads = Utils::getDownloadsPath();
            if (!is_dir($downloads . '/' . $this->handle)) {
                mkdir($downloads . '/' . $this->handle, 0775);
            }
            $file = $downloads . '/' . $this->handle . '/destinations.csv';
            file_put_contents($file, $content);

            // read csv
            $i = 0;
            $handle = fopen($file, 'r');
            $array = [];

            if ($handle) {
                while (($row = fgetcsv($handle, 0, '|')) !== false) {
                    if (empty($fields)) {
                        $fields = $row;
                        $fields[0] = str_replace('"', '', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $fields[0]));
                        continue;
                    }

                    foreach ($row as $k => $value) {
                        $array[$i][$fields[$k]] = $value;
                    }
                    $i++;
                }
                if (!feof($handle)) {
                    echo "Error: unexpected fgets() fail\n";
                }
                fclose($handle);
            } else {
                throw new Exception('File not found in downloads folder');
            }

            $list = [];

            foreach ($array as $v) {
                $country = new Country();
                $country->Id = $v['CountryId'];
                $country->Code = $v['IsoCode'];
                $country->Name = $v['Country'];

                $city = new City();

                $city->Id = $v['CityId'];
                $city->Name = $v['City'];
                $city->Country = $country;

                $list->put($city->Id, $city);
            }
            Utils::writeToCache($this, $fileCity, json_encode($list));
        } else {
            $citiesArr = json_decode($citiesJson, true);
            $list = ResponseConverter::convertToCollection($citiesArr, array::class);
        }

        return $list;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        Validator::make()->validateAvailabilityFilter($filter);

        $checkIn = $filter->checkIn;
        $cityId = $filter->cityId;
        $hotelId = $filter->hotelId;
        $roomTypes = $this->getRoomTypes();

        $adults = (int) $filter->rooms->get(0)->adults;

        $days = $filter->days;
        $checkInDate = new DateTimeImmutable($checkIn);
        $checkOut = $checkInDate->add(new DateInterval('P' . $days . 'D'))->format('Y-m-d');

        $childrenAges = $filter->rooms->get(0)->childrenAges !== null ? $filter->rooms->get(0)->childrenAges : new StringCollection();

        $url = $this->apiUrl . '/AvailabilityService?wsdl';

        $hotelEl = '';
        if (!empty($hotelId)) {
            $hotelEl = '<hotel code="' . $hotelId . '"/>';
        }

        $childrenEl = '';
        if (count($childrenAges) > 0) {
            $childrenEl .= '<children>';
            foreach ($childrenAges as $age) {
                if ($age !== '') {
                    $childrenEl .= '<child age="' . $age . '" />';
                }
            }
            $childrenEl .= '</children>';
        }

        $xmlString = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v2="http://webservice.gekko-holding.com/v2_4">
            <soapenv:Header/>
                <soapenv:Body>
                    <v2:hotelAvailability>
                    <identification clientId="' . $this->username . '" password="' . $this->password . '"/> 
                    <availCriteria>
                        <checkIn>' . $checkIn . '</checkIn>
                        <checkOut>' . $checkOut . '</checkOut>
                        <destinationCriteria>
                            <city code="' . $cityId . '"/>
                            ' . $hotelEl . '
                        </destinationCriteria>
                        <roomCriterias>
                            <roomPlan adultsCount="' . $adults . '">' . $childrenEl . '</roomPlan>
                        </roomCriterias>
                    </availCriteria>
                    </v2:hotelAvailability>
                </soapenv:Body>
            </soapenv:Envelope>';

        //$soapClient = new SoapClientXML($url, ['features' => SOAP_SINGLE_ELEMENT_ARRAYS, 'trace' => true, 'exceptions' => false], $xmlString);

        $client = HttpClient::create();
        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'application/xml'
        ];

        $response = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $response->getBody(), $response->getStatusCode());

        $content = $response->getBody();
        $content = str_replace('ns2:', '', $content);
        $content = str_replace('soap:', '', $content);
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');

        $response = new SimpleXMLElement($content);

        $responseHotels = $response->Body->hotelAvailabilityResponse->availResponse;

        $response = [];

        foreach ($responseHotels->hotelResponse as $responseHotel) {

            if ((string) $responseHotel->city->attributes()->code !== $cityId) {
                continue;
            }
            $hotel = new Availability();
            $hotel->Id = (string) $responseHotel->attributes()->code;

            $offers = new OfferCollection();

            foreach ($responseHotel->offers as $offersResponse) {
                foreach ($offersResponse as $responseOffer) {
                    $currency = new Currency();
                    $currency->Code = (string) $responseOffer->attributes()->currency;

                    $offerObj = new Offer();

                    $offerObj->CheckIn = $checkIn;
                    $offerObj->Currency = $currency;
                    $offerObj->InitialData = $responseOffer->offerCode;

                    $taxes = (float) $responseOffer->taxes ?? 0;
                    $offerObj->Net = (float) $responseOffer->totalPrice->attributes()->value - $taxes;

                    $offerObj->Gross = (float) $responseOffer->totalPrice->attributes()->value;
                    $offerObj->InitialPrice = $offerObj->Gross;
                    $offerObj->Comission = $taxes;

                    $offerObj->Availability = $responseOffer->onRequest ? Offer::AVAILABILITY_ASK : Offer::AVAILABILITY_YES;
                    $offerObj->Days = $days;

                    if (count($responseOffer->roomOffers->roomOffer) > 1) {
                        throw new Exception('Warning! This hotel has more than 1 room offers!');
                    }

                    $responseRoomOffer = $responseOffer->roomOffers->roomOffer[0];

                    $roomType = $roomTypes->first(fn(RoomType $roomType) => $roomType->Id === (string) $responseRoomOffer->roomType->attributes()->code);

                    // Rooms
                    $room1 = new Room();
                    $roomId = $roomType->Id;
                    $room1->Id = $roomId;

                    $room1->CheckinBefore = $checkOut;
                    $room1->CheckinAfter = $checkIn;
                    $room1->Currency = $currency;
                    $room1->Quantity = 1;
                    $room1->Availability = $offerObj->Availability;

                    $roomMerchType = new RoomMerchType();
                    $roomMerchType->Id = $roomId;
                    $roomMerchType->Title = ucfirst($roomType->Name);

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;
                    $merch->Title = ucfirst($roomType->Name);
                    $merch->Type = $roomMerchType;
                    $merch->Code = $roomId;
                    $merch->Name = ucfirst($roomType->Name);

                    $room1->Merch = $merch;

                    $offerObj->Rooms = new RoomCollection([$room1]);

                    $offerObj->Item = $room1;

                    $mealItem = new MealItem();
                    $boardTypeName = (string) $responseRoomOffer->boardType;

                    // MealItem Merch
                    $boardMerch = new MealMerch();
                    $boardMerch->Title = $boardTypeName;

                    $mealId = (string) $responseRoomOffer->boardType->attributes()->code;
                    $boardMerchType = new MealMerchType();
                    $boardMerchType->Id = $mealId;
                    $boardMerchType->Title = $boardTypeName;

                    $boardMerch->Type = $boardMerchType;

                    // MealItem
                    $mealItem->Merch = $boardMerch;
                    $mealItem->Currency = $currency;

                    $offerObj->MealItem = $mealItem;

                    $offerCode = $hotel->Id . '~' . $roomId . '~' . $mealId . '~' . $checkIn . '~' . $days . '~' . $offerObj->Net . '~' . $adults . '~' . ($childrenAges ? implode('|', $childrenAges->toArray()) : '');
                    $offerObj->Code = $offerCode;

                    // DepartureTransportItem Merch
                    $departureTransportItemMerch = new TransportMerch();
                    $departureTransportItemMerch->Title = 'CheckIn: ' . $checkIn;

                    // DepartureTransportItem Return Merch
                    $departureTransportItemReturnMerch = new TransportMerch();
                    $departureTransportItemReturnMerch->Title = 'CheckOut: ' . $checkOut;

                    // DepartureTransportItem Return
                    $departureTransportItemReturn = new ReturnTransportItem();
                    $departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
                    $departureTransportItemReturn->Currency = $currency;
                    $departureTransportItemReturn->DepartureDate = $checkOut;
                    $departureTransportItemReturn->ArrivalDate = $checkOut;

                    // DepartureTransportItem
                    $departureTransportItem = new DepartureTransportItem();
                    $departureTransportItem->Merch = $departureTransportItemMerch;
                    $departureTransportItem->Currency = $currency;
                    $departureTransportItem->DepartureDate = $checkIn;
                    $departureTransportItem->ArrivalDate = $checkIn;
                    $departureTransportItem->Return = $departureTransportItemReturn;

                    $offerObj->DepartureTransportItem = $departureTransportItem;

                    $offerObj->ReturnTransportItem = $departureTransportItemReturn;

                    $cancelFees = new OfferCancelFeeCollection();

                    // cancellation fees
                    if (isset($responseOffer->cancellationFeesPolicy)) {
                        foreach ($responseOffer->cancellationFeesPolicy as $policy) {
                            $cpObj = new OfferCancelFee();
                            $cpObj->DateStart = DateTime::createFromFormat('d/m/Y H:i', $policy->fromDate)->format('Y-m-d');
                            $cpObj->DateEnd = DateTime::createFromFormat('d/m/Y H:i', $policy->toDate)->format('Y-m-d');
                            $cpObj->Price = (float) $policy->price;
                            $cpObj->Currency = $currency;
                            $cancelFees->add($cpObj);
                        }
                    }
                    $offerObj->CancelFees = $cancelFees;
                    $offerObj->FixCancelFeesPrices = true;
                    $offers->put($offerCode, $offerObj);
                }
            }

            $hotel->Offers = $offers;
            $response->add($hotel);
        }

        return $response;
    }

    public function apiGetHotels(): []
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $client = HttpClient::create(['user_agent' => 'curl/' . curl_version()['version']]);

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;

        $options['headers'] = [
            'Authorization' => $basic,
        ];
        $url = 'https://static-data.tourismcloudservice.com/agency/hotels/' . $this->apiContext;

        //$response = $client->request(HttpClient::METHOD_GET, $url, $options);

        //$this->showRequest(HttpClient::METHOD_GET, $url, $options, $response->getBody(), $response->getStatusCode());
        //$content = $response->getBody();
        $downloads = Utils::getDownloadsPath();
        if (!is_dir($downloads . '/' . $this->handle)) {
            mkdir($downloads . '/' . $this->handle, 0775);
        }
        $fileZip = $downloads . '/' . $this->handle . '/properties.zip';
        $folder = $file = $downloads . '/' . $this->handle;
        //file_put_contents($fileZip, $content);
        //$this->unZip($fileZip, $folder);
        $file = $folder . '/properties.csv';

        // read csv
        $i = 0;
        $handle = fopen($file, 'r');
        $array = [];

        if ($handle) {
            while (($row = fgetcsv($handle, 0, '|')) !== false) {
                if (empty($fields)) {
                    $fields = $row;
                    $fields[0] = str_replace('"', '', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $fields[0]));
                    continue;
                }

                foreach ($row as $k => $value) {
                    $array[$i][$fields[$k]] = $value;
                }
                $i++;
            }
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        } else {
            throw new Exception('File not found in downloads folder');
        }

        $list = [];

        $cities = $this->apiGetCities();
        foreach ($array as $hotelData) {
            $hotel = new Hotel();
            $hotel->Id = $hotelData['HotelID'];
            $hotel->Name = $hotelData['Name'];

            $address = new HotelAddress();
            $address->City = $cities->get($hotelData['CityId']);
            $address->Details = $hotelData['Address'];
            $address->Longitude = $hotelData['Longitude'];
            $address->Latitude = $hotelData['Latitude'];
            $hotel->Content = new HotelContent();

            $hotel->Address = $address;
            $hotel->Stars = round($hotelData['Stars']);

            $contactPerson = new ContactPerson();
            $contactPerson->Fax = $hotelData['Fax'];
            $contactPerson->Phone = $hotelData['Phone'];
            $hotel->ContactPerson = $contactPerson;

            $list->add($hotel);
        }
        return $list;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateHotelDetailsFilter($filter);

        $client = HttpClient::create();

        $options['headers'] = [
            'Content-Type' => 'application/soap+xml; charset=utf-8',
        ];

        $cities = $this->apiGetCities();
        // working
        $xml = '<?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope 
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
                xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">

                <soap12:Body>
                    <MakeRequest xmlns="http://www.goglobal.travel/">
                        <requestType>61</requestType>
                        <xmlRequest><![CDATA[
                            <Root>
                                <Header>
                                    <Agency>' . $this->apiContext . '</Agency>
                                    <User>' . $this->username . '</User>
                                    <Password>' . $this->password . '</Password>
                                    <Operation>HOTEL_INFO_REQUEST</Operation>
                                    <OperationType>Request</OperationType>
                                </Header>
                                <Main>
                                    <InfoHotelId>' . $filter->hotelId . '</InfoHotelId>
                                </Main>
                            </Root>
                        ]]></xmlRequest>
                    </MakeRequest>
                </soap12:Body>
            </soap12:Envelope>';

        $options['body'] = $xml; //595962

        $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();

        $hotelXml = new SimpleXMLElement($content);
        $hotelXml = $hotelXml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children()
            ->MakeRequestResponse
            ->MakeRequestResult;

        $hotelXml = new SimpleXMLElement((string) $hotelXml);
        $hotelXml = $hotelXml->Main;

        $hotel = new Hotel();
        $hotel->Id = $hotelXml->HotelSearchCode;
        $hotel->Name = $hotelXml->HotelName;

        $hotelContent = new HotelContent();
        $hotelContent->ImageGallery = new HotelImageGallery();

        $images = new HotelImageGalleryItemCollection();

        foreach ($hotelXml->Pictures->Picture as $picture) {
            $image = new HotelImageGalleryItem();
            $image->Alt = $picture->attributes()->Description;
            $image->RemoteUrl = (string) $picture;
            $images->add($image);
        }

        $hotelContent->ImageGallery->Items = $images;

        $hotelContent->Content = $hotelXml->Description;
        $hotel->Content = $hotelContent;

        $facilities = new FacilityCollection();

        $facilitiesArr = explode(', ', (string)$hotelXml->HotelFacilities);

        foreach ($facilitiesArr as $facilityEl) {
            $facility = new Facility();
            $facility->Name = $facilityEl;
            $facility->Id = preg_replace('/\s+/', '', $facilityEl);
            $facilities->add($facility);
        }

        $hotel->Facilities = $facilities;

        $address = new HotelAddress();
        $address->City = $cities->get($hotelXml->CityCode);

        $hotel->Address = $address;

        return $hotel;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        // prebook
        InfiniteValidator::make()->validateBookHotelFilter($filter);

        $hotelCode = $filter->Items->get(0)->Hotel->InTourOperatorId;
        $startDate = $filter->Items->get(0)->Room_CheckinAfter;
        $endDate = $filter->Items->get(0)->Room_CheckinBefore;
        $offerCode = $filter->Items->get(0)->Offer_InitialData;

        $url = $this->apiUrl . '/AvailabilityService?wsdl';

        $client = HttpClient::create();

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_v2]' => 'http://webservice.gekko-holding.com/v2_4',
                'soapenv_--_Body' => [
                    'v2_--_getPreBookingInfo' => [
                        'language' => $this->language,
                        'identification' => [
                            '[clientId]' => $this->username,
                            '[password]' => $this->password
                        ],
                        'searchCriteria' => [
                            'hotelCode' => [
                                '[code]' => $hotelCode
                            ],
                            'startDate' => $startDate,
                            'endDate' => $endDate,
                            'offerCode' => $offerCode
                        ]
                    ]
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'application/xml'
        ];

        $response = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $response->getBody(), 0);
        $content = $response->getBody();

        $content = str_replace('ns2:', '', $content);
        $content = str_replace('soap:', '', $content);

        $preBooking = new SimpleXMLElement($content);
        $preBooking = $preBooking->Body->getPreBookingInfoResponse->preBookingInfo;

        // booking
        $pax = [];
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $passenger) {
            if ($passenger['Firstname'] !== '') {
                $paxArray = [
                    '[title]' => $passenger['Gender'] == 2 ? 'Mrs' : 'Mr',
                    'firstName' => $passenger['Firstname'],
                    'lastName' => $passenger['Lastname'],
                ];
                if (!$passenger['IsAdult']) {
                    $age = (new DateTime())->diff(new DateTime($passenger['BirthDate']))->y;
                    $paxArray['[childAge]'] = $age;
                    $paxArray['[isChild]'] = 'true';
                }

                $pax[] = $paxArray;
            }
        }

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_v2]' => 'http://webservice.gekko-holding.com/v2_4',
                'soapenv_--_Body' => [
                    'v2_--_bookHotel' => [
                        'language' => $this->language,
                        'identification' => [
                            '[clientId]' => $this->username,
                            '[password]' => $this->password
                        ],
                        'bookRequest' => [
                            'statisticalFields' => [],
                            'hotelBooking' => [
                                '[hotelCode]' => $hotelCode,
                                'checkIn' => $startDate,
                                'checkOut' => $endDate,
                                'bookedOffer' => [
                                    'code' => (string) $preBooking->offerCode,
                                    'bookedRooms' => [
                                        [
                                            '[roomIndex]' => 0,
                                            'v2_--_pax' => $pax
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'application/xml'
        ];

        $response = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $response->getBody(), 0);
        $contentOrig = $response->getBody();

        $content = str_replace('ns2:', '', $contentOrig);
        $content = str_replace('soap:', '', $content);

        $booking = new SimpleXMLElement($content);
        $booking = $booking->Body->bookHotelResponse->bookResponse;

        if ($booking->segments->segment[0]->status != 'CONF') {
            Log::warning($content);
        }

        $response = new Booking();
        $response->Id = $booking->attributes()->bookingId;

        return [$response, $contentOrig];
    }

    /*
    private function addResponse(SoapClient $soapClient, $url, bool $isCustom = false): void
    {
        $requestBody = $soapClient->__getLastRequest();
        if ($isCustom) {
            $requestBody = $soapClient->getRequest();
        }

        $responseBody = $soapClient->__getLastResponse();

        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        // if (!empty($responseBody)) {
        //     $dom->loadXML($responseBody);
        //     $responseBody = $dom->saveXML();
        // } else {
        //     $responseBody = '';
        // }
        $this->showRequest('POST', $url, ['body' => $requestBody], $responseBody, 0);
    }*/

    // public function request(string $url, string $method  = '', array $options = []): stdClass
    // {
    //     $soapClient = new SoapClient($url, ['features' => SOAP_SINGLE_ELEMENT_ARRAYS, 'trace' => true, 'exceptions' => false]);
    //     $defaultArr = [
    //         'identification' => ['clientId' => $this->username, 'password' => $this->password],
    //         'language' => $this->language
    //     ];

    //     $arr = array_merge($defaultArr, $options);
    //     $response = $soapClient->{$method}($arr);

    //     if (isset($response->faultstring)) {
    //         $fault = $this->handle . ': ' . $response->faultstring;
    //         if (count($options) > 0) {
    //             $fault .= ', params: '. json_encode($options) . ', method: '. $method;
    //         }

    //         Log::warning($fault);
    //         $response = new stdClass();
    //     }
    //     preg_match("/HTTP\/\d\.\d\s*\K[\d]+/", $soapClient->__getLastResponseHeaders(), $matches);
    //     $response->statusCode = $matches;

    //     if ($this->request->getPostParam('get-raw-data')) {
    //         $this->addResponse($soapClient, $url);
    //     }

    //     return $response;
    // }
}
