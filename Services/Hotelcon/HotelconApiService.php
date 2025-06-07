<?php

namespace Integrations\Hotelcon;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\MealMerchType;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
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
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\RoomTypeCollection;
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
use Utils\Utils;

class HotelconApiService extends AbstractApiService
{
    private string $language = 'EN';
    private const TEST_HANDLE = 'localhost-infinitehotel';

    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetCountries(): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $url = $this->apiUrl . '/ReferentialService?wsdl';

        $client = HttpClient::create();

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_v2]' => 'http://webservice.gekko-holding.com/v2_4',
                'soapenv_--_Body' => [
                    'v2_--_getCountries' => [
                        'language' => $this->language,
                        'identification' => [
                            '[clientId]' => $this->username,
                            '[password]' => $this->password
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
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
       
        $content = str_replace('ns2:', '', $content);
        $content = str_replace('soap:', '', $content);

        $response = new SimpleXMLElement($content);
        $newResult = [];

        foreach ($response->Body->getCountriesResponse->country as $v) {
            $country = new Country();
            $country->Id = (string) $v->attributes()->code;
            $country->Code = $country->Id;
            $country->Name = (string) $v;
            $newResult->add($country);
        }

        return $newResult;
    }

    public function apiGetCities(CitiesFilter $params = null): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $file = 'cities';
        $citiesJson = Utils::getFromCache($this->handle, $file);

        if ($citiesJson === null) {

            $countries = $this->apiGetCountries();

            $cities = [];
            $getFromCache = false;

            $client = HttpClient::create();
            $requests = [];
            foreach ($countries as $country) {
                $url = $this->apiUrl . '/ReferentialService?wsdl';

                $requestArr = [
                    'soapenv_--_Envelope' => [
                        '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                        '[xmlns_--_v2]' => 'http://webservice.gekko-holding.com/v2_4',
                        'soapenv_--_Body' => [
                            'v2_--_getCities' => [
                                'language' => $this->language,
                                'identification' => [
                                    '[clientId]' => $this->username,
                                    '[password]' => $this->password
                                ],
                                'countryCode' => $country->Code
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
                $requests[] = [$response, $options];
            }

            foreach ($requests as $request) {
                $responseObj = $request[0];
                $options = $request[1];
                $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
                $content = $responseObj->getBody();
                $tries = 0;
                
                if ($responseObj->getStatusCode() !== 200) {
                    while(true) {
                        if ($this->handle !== self::TEST_HANDLE) {
                            sleep(10);
                        }
                        $responseObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                        $content = $responseObj->getBody();
                        
                        if ($responseObj->getStatusCode() === 200) {
                            break;
                        }
                        
                        $tries++;
                        if ($tries >= 5) {
                            $getFromCache = true;
                            Log::warning($this->handle . ': cannot get cities from API');
                            break;
                        }
                    }
                    if ($getFromCache) {
                        break;
                    }
                }

                $content = str_replace('ns2:', '', $content);
                $content = str_replace('soap:', '', $content);
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');    

                $response = new SimpleXMLElement($content);

                foreach ($response->Body->getCitiesResponse->city as $v) {

                    $city = new City();
                    $city->Id = (string) $v->attributes()->code;
                    $city->Name = (string) $v;
                    $city->Country = $country;

                    $cities->add($city);
                }
            }

            if ($getFromCache) {
                $citiesJson = Utils::getFromCache($this->handle, $file, true);
                if ($citiesJson === null) {
                    throw new Exception($this->handle . ': cannot get cities from API and no files are cached');
                }
                $citiesArray = json_decode($citiesJson, true);
                $cities = ResponseConverter::convertToCollection($citiesArray, array::class);
            } else {
                $data = json_encode_pretty($cities);
                Utils::writeToCache($this->handle, $file, $data, 0);
            }
        } else {
            $citiesArray = json_decode($citiesJson, true);
            $cities = ResponseConverter::convertToCollection($citiesArray, array::class);
        }

        return $cities;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateAvailabilityFilter($filter);

        $availabilities = [];
        if ($filter->serviceTypes->first() !== AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            return $availabilities;
        }

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
            $hotelEl = '<hotel code="'.$hotelId.'"/>';
        }

        $childrenEl = '';
        if (count($childrenAges) > 0) {
            $childrenEl .= '<children>';
            foreach ($childrenAges as $age) {
                if ($age !== '') {
                    $childrenEl .= '<child age="'.$age.'" />';
                }
            }
            $childrenEl .= '</children>';
        }

        $xmlString = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v2="http://webservice.gekko-holding.com/v2_4">
        <soapenv:Header/>
            <soapenv:Body>
                <v2:hotelAvailability>
                <identification clientId="'.$this->username.'" password="'.$this->password.'"/> 
                <availCriteria>
                    <checkIn>'.$checkIn.'</checkIn>
                    <checkOut>'.$checkOut.'</checkOut>
                    <destinationCriteria>
                        <city code="'.$cityId.'"/>
                        '.$hotelEl.'
                    </destinationCriteria>
                    <roomCriterias>
                        <roomPlan adultsCount="'.$adults.'">'.$childrenEl.'</roomPlan>
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

        if ($responseHotels === null) {
            return $availabilities;
        }

        foreach ($responseHotels->hotelResponse as $responseHotel) {

            if ((string) $responseHotel->city->attributes()->code !== $cityId) {
                continue;
            }
            $hotel = new Availability();
            $hotel->Id = (string) $responseHotel->attributes()->code;

            if ($filter->showHotelName) {
                $hotel->Name = (string) $responseHotel->name;
            }

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
                    $payments = new OfferPaymentPolicyCollection();

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
                        $amountLeft = 0;
                        $i = 0;
                        foreach ($cancelFees as $cFee) {
                            $pFee = new OfferPaymentPolicy();
                            $pFee->Currency = $currency;
                            $amountLeft = $cFee->Price - $amountLeft;
                            $pFee->Amount = $amountLeft;
                            if ($i === 0) {
                                $pFee->PayAfter = date('Y-m-d');
                            } else {
                                $pFee->PayAfter = $cancelFees->get($i - 1)->DateEnd;
                            }
                            $pFee->PayUntil = $cFee->DateStart;

                            $payments->add($pFee);
                            $i++;
                        }
                    }
                    $offerObj->CancelFees = $cancelFees;
                    $offerObj->Payments = $payments;

                    $offerObj->FixCancelFeesPrices = true;
                    $offers->put($offerCode, $offerObj);
                }
            }

            $hotel->Offers = $offers;
            $availabilities->add($hotel);
        }

        return $availabilities;
    }

    public function apiGetHotels(): []
    {
        $response = $this->getHotelsArr($this->handle);

        $hotels = [];
        //$i = 0;
        foreach ($response as $hotelResponse) {
           // $i++;
            //if ($i > 20) break;
            // $address->Country
            $country = new Country();
            $country->Id = $hotelResponse['CountryCode'];
            $country->Code = $country->Id;
            $country->Name = $hotelResponse['CountryName'];

            // $address->City
            $city = new City();
            $city->Id = $hotelResponse['CityCode'];
            $city->Name = $hotelResponse['CityName'];
            $city->Country = $country;

            // $hotel->Address
            $address = new HotelAddress();
            $address->Latitude = $hotelResponse['Latitude'];
            $address->Longitude = $hotelResponse['Longitude'];
            $address->Details = $hotelResponse['HotelAddress'];
            $address->City = $city;

            // $content->ImageGallery
            $image = new HotelImageGalleryItem();
            $image->RemoteUrl = $hotelResponse['ThumbnailUrl'];
            $image->Alt = $hotelResponse['HotelName'];

            // $hotel->Content->ImageGallery
            $imageGallery = new HotelImageGallery();
            $imageGallery->Items = new HotelImageGalleryItemCollection([]);

            // $hotel->Content
            $content = new HotelContent();
            $content->ImageGallery = $imageGallery;
            $content->Content = null;

            $hotel = new Hotel();
            $hotel->Id = $hotelResponse['InfiniteCode'];
            $hotel->Name = $hotelResponse['HotelName'];
            $hotel->Stars = (int) $hotelResponse['HotelRatingCode'];
            $hotel->Content = $content;
            $hotel->Address = $address;
            $hotel->WebAddress = null;

            $hotels->add($hotel);
        }

        return $hotels;
    }

    private function getHotelsArr(string $handle): array
    {
        $directory = __DIR__ . '/../../Storage/Downloads/' . $handle. '/';
        $tempDirectory = __DIR__ . '/../../Storage/Downloads/' . $handle. '/temp/';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $date = (new DateTime())->format('Y-m-d');
        $csvFile = 'hotels' . $date . '.csv';

        // download to temp file
        if (!is_file($directory . $csvFile)) {

            if (!is_dir($tempDirectory)) {
                mkdir($tempDirectory, 0755, true);
            }

            $tempFiles = glob($tempDirectory . "*");

            // clear temp folder
            if (count($tempFiles) !== 0) {
                foreach ($tempFiles as $tempFile) {
                    unlink($tempFile);
                }
            }

            if ($this->handle === self::TEST_HANDLE) {

                $hotelsData = "InfiniteCode;HotelName;HotelAddress;ZipCode;CityName;CityCode;CountryName;CountryCode;HotelRatingCode;HotelPhone;HotelFax;HotelEmail;ThumbnailUrl;Latitude;Longitude;chainname
AD000083;Golden Tulip Andorra Fenix Hotel;Prat Gran;   3 - 5;Escaldes-Engordany;ADESC;Andorra;AD;4STR;376760760;00 376 760 800;info@goldentulipandorrafenix.com;http://media.iceportal.com/66732/photos/73101631_M.jpg;42.5087;1.5375;Golden Tulip";
                
                $date = (new DateTime())->format('Y-m-d');
                file_put_contents($tempDirectory . $csvFile, $hotelsData);
            } else {
                $script = 'sftp -o StrictHostKeyChecking=accept-new -i ' . __DIR__ . '/../../Storage/Keys/' . $handle .'/infinite_id_rsa galtour@sfiles.gekko-holding.com:* ' . $tempDirectory;
                shell_exec($script);
            }
        }
        
        $tempFiles = glob($tempDirectory . "*");

        // check that there is only 1 file in temp folder
        if (count($tempFiles) === 1) {
            // clear
            $hotelFiles = glob($directory . "*");
            if (count($hotelFiles) > 0) {
                foreach ($hotelFiles as $hotelFile) {
                    if (is_file($hotelFile)) {
                        unlink($hotelFile);
                    }
                }
            }

            // copy file to upper folder
            copy($tempFiles[0], $directory . $csvFile);
        } elseif (count($tempFiles) > 1) {
            throw new Exception('sfiles.gekko-holding.com provides multiple hotel csv files');
        } else {
            Log::warning('Infinite: hotels file cannot be downloaded');
            $hotelFiles = glob($directory . "*");
            $csvFile = basename($hotelFiles[0]); // from cache
        }

        // read csv
        $i = 0;
        $handle = @fopen($directory . $csvFile, 'r');
        $array = [];
        
        if ($handle) {
            while (($row = fgetcsv($handle, 0, ";")) !== false) {
                if (empty($fields)) {
                    $fields = $row;
                    $fields[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $fields[0]);
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

        $fileLocUrl = Utils::getDownloadsBaseUrl() . '/' . $this->handle . '/' . $csvFile;

        $this->showRequest('SFTP', 'galtour@sfiles.gekko-holding.com:*', [], $fileLocUrl, 0);

        return $array;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateHotelDetailsFilter($filter);

        $hotelCode = $filter->hotelId;
        
        $client = HttpClient::create();

        $url = $this->apiUrl . '/AvailabilityService?wsdl';
        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_v2]' => 'http://webservice.gekko-holding.com/v2_4',
                'soapenv_--_Body' => [
                    'v2_--_getHotelDetails' => [
                        'language' => $this->language,
                        'identification' => [
                            '[clientId]' => $this->username,
                            '[password]' => $this->password
                        ],
                        'hotelCodes' => [
                            'hotelCode' => [
                                '[code]' => $hotelCode
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
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $response->getBody(), $response->getStatusCode());

        $content = $response->getBody();
        $content = str_replace('ns2:', '', $content);
        $content = str_replace('soap:', '', $content);
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');    

        $details = new Hotel();
        try {
            $response = new SimpleXMLElement($content);
        } catch(Exception $e) {
            Log::warning($this->handle . ': invalid xml for hotel details: ' . $content);
            return $details;
        }
        $response = $response->Body->getHotelDetailsResponse;

    
        if (!isset($response->hotel)) {
            return $details;
        }

        $response = $response->hotel[0];
        
        // Content ImageGallery Items
        $items = [];
        if (isset($response->images)) {
            foreach ($response->images->image as $imageResponse) {
                $image = new HotelImageGalleryItem();
                $image->RemoteUrl = (string) $imageResponse;
                $image->Alt = $imageResponse->attributes()->type;
                $items[] = $image;
            }
        }

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = new HotelImageGalleryItemCollection($items);

        // create file and contents
        $cities = $this->apiGetCities();

        $city = $cities->first(fn(City $item) => $item->Id === (string) $response->city->attributes()->code);

        if ($city === null) {
            return $details;
        }

        // Content Address Country
        $country = new Country();
        $country->Id = $city->Country->Id;
        $country->Name = $city->Country->Name;
        $country->Code = $city->Country->Code;

        // Content Address City
        $cityResponse = new City();
        $cityResponse->Name = $city->Name;
        $cityResponse->Id = $city->Id;
        $cityResponse->Country = $country;

        // Content Address
        $address = new HotelAddress();
        $address->City = $cityResponse;
        $address->Details = $response->address;
        $address->Latitude = $response->geoLocalization->latitude;
        $address->Longitude = $response->geoLocalization->longitude;

        // Content ContactPerson
        $contactPerson = new ContactPerson();

        $contactPerson->Email = $response->email ?? null;
        $contactPerson->Phone = $response->phone ?? null;
        $contactPerson->Fax = $response->fax ?? null;

        // Content Facilities
        $facilities = new FacilityCollection();
        
        if (isset($response->facilities)) {
            $facilitiesResponse = $response->facilities->facility;
            foreach ($facilitiesResponse as $facilityResponse) {
                $facility = new Facility();
                $facility->Name = (string) $facilityResponse;
                $facility->Id = $facilityResponse->attributes()->code;
                $facilities->add($facility);
            }
        }
        
        $description = null;
        if (isset($response->shortDescription)) {
            $description = $response->shortDescription;
        }

        // Content
        $content = new HotelContent();
        $content->Content = $description;
        $content->ImageGallery = $imageGallery;

        $details->Id = $response->attributes()->hotelCode;
        $details->Name = $response->hotelName;
        $details->Content = $content;
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->WebAddress = null;

        return $details;
    }

    public function getRoomTypes(): RoomTypeCollection
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $file = 'room-types';
        $roomTypes = Utils::getFromCache($this->handle, $file);
        if ($roomTypes === null) {

            $url = $this->apiUrl . '/ReferentialService?wsdl';
            $client = HttpClient::create();

            $requestArr = [
                'soapenv_--_Envelope' => [
                    '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    '[xmlns_--_v2]' => 'http://webservice.gekko-holding.com/v2_4',
                    'soapenv_--_Body' => [
                        'v2_--_getRoomTypes' => [
                            'language' => $this->language,
                            'identification' => [
                                '[clientId]' => $this->username,
                                '[password]' => $this->password
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

            $response = new SimpleXMLElement($content);
            $response = $response->Body->getRoomTypesResponse->roomType;

            $newResult = new RoomTypeCollection();

            foreach ($response as $v) {
                $roomType = new RoomType();
                $roomType->Id = (string) $v->attributes()->code;
                $roomType->Name = $v;
                $newResult->add($roomType);
            }
            Utils::writeToCache($this->handle, $file, json_encode($newResult));
        } else {
            $arr = json_decode($roomTypes, true);
            $newResult = ResponseConverter::convertToCollection($arr, RoomTypeCollection::class);
        }

        return $newResult;
    }
    
    public function apiDoBooking(BookHotelFilter $filter): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        // prebook

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

        if ((string)$booking->segments->segment[0]->attributes()->status != 'CONF') {
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

    public function apiTestConnection(): bool
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
	xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
	xmlns:ns1="http://tbs.dcsplus.net/ws/1.0/"
	xmlns:ns2="https://tbs.accenttravel.ro/reseller/ws/?wsdl">
	<SOAP-ENV:Header>
		<ns1:AuthHeader>
			<ns1:ResellerCode>'.$this->apiContext.'</ns1:ResellerCode>
			<ns1:Username>'.$this->username.'</ns1:Username>
			<ns1:Password>'.$this->password.'</ns1:Password>
		</ns1:AuthHeader>
	</SOAP-ENV:Header>
	<SOAP-ENV:Body>
		<ns1:GetCountriesRQ>
			<ns1:Filters/>
		</ns1:GetCountriesRQ>
	</SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        $options['body'] = $body;
        $option['headers'] = [
            'Content-Type' => 'text/xml'
        ];

        $client = HttpClient::create();
        $respObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $data = $respObj->getBody();

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $data, $respObj->getStatusCode());

        $countries = simplexml_load_string($data);

        $countries = $countries->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://tbs.dcsplus.net/ws/1.0/')->GetCountriesRS->Countries;

        if (isset($countries->Country)) {
            return true;
        }

        return false;
    }
}