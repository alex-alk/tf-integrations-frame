<?php

namespace Integrations\SanSejour;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\RegionCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use DateTime;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use SimpleXMLElement;
use Utils\Utils;

class SanSejourApiService extends AbstractApiService
{
    private const HANDLE_IRELES = 'ireles';

    public function apiGetCountries(): CountryCollection
    {
        $bulgaria = Country::create('BG', 'BG', 'Bulgaria');

        $turcia = Country::create('TR', 'TR', 'Turcia');

        $countries = new CountryCollection();
        $countries->put($bulgaria->Id, $bulgaria);
        $countries->put($turcia->Id, $turcia);

        return $countries;
    }
    public function apiGetCities(CitiesFilter $params = null): CityCollection
    {
        $regions = $this->apiGetRegions();
        
        $cities = new CityCollection();
        foreach ($regions as $region) {

            $city = City::create($region->Id, $region->Name, $region->Country, $region);
            $cities->put($city->Id, $city);
        }

        return $cities;
    }

    public function apiGetRegions(): RegionCollection
    {
        SanSejourValidator::make()
            ->validateUsernameAndPassword($this->post);

        $token = $this->getToken();

        $xmlReq = '<?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
                <soap12:Body>
                    <GetRegionsDS xmlns="http://www.sansejour.com/">
                    <token>' . $token . '</token>
                    </GetRegionsDS>
                </soap12:Body>
            </soap12:Envelope>'
        ;

        $options = [
            'body' => $xmlReq,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];
        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Common.asmx', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Common.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

        $xml = simplexml_load_string($resp->getContent(false));
        $xml = $xml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children('http://www.sansejour.com/')
            ->GetRegionsDSResponse
            ->GetRegionsDSResult->children('urn:schemas-microsoft-com:xml-diffgram-v1')
            ->diffgram->children('');

        $countries = $this->apiGetCountries();

        $regions = new RegionCollection();
        foreach ($xml->NewDataSet->Region as $regionXml) {
            
            $country = $countries->get('BG');

            if ($this->handle === self::HANDLE_IRELES) {
                $country = $countries->get('TR');
            } 
            
            $region = Region::create($regionXml->Region, $regionXml->Description, $country);
            $regions->add($region);
        }

        return $regions;
    }

    private function getToken(): string
    {
        $client = HttpClient::create();
        $xmlReq = '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
            <soap:Body>
                <Login xmlns="http://www.sansejour.com/">
                    <databaseName>'. $this->apiContext .'</databaseName>
                    <userName>' . $this->username . '</userName>
                    <password>' . $this->password . '</password>
                </Login>
            </soap:Body>
            </soap:Envelope>';

        $options = [
            'body' => $xmlReq,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Authentication.asmx', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Authentication.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

        $xml = simplexml_load_string($resp->getContent(false));
        $token = (string) $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.sansejour.com/')
            ->LoginResponse
            ->LoginResult
            ->AuthKey;
        return (string) $token;
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        $file = 'hotels';
        $json = Utils::getFromCache($this->handle, $file);

        if ($json === null) {

            $token = $this->getToken();
            
            $xmlReq = '<?xml version="1.0" encoding="utf-8"?>
                <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
                    <soap12:Body>
                        <GetHotelListDS xmlns="http://www.sansejour.com/">
                            <token>' . $token . '</token>
                        </GetHotelListDS>
                    </soap12:Body>
                </soap12:Envelope>'
            ;

            $options = [
                'body' => $xmlReq,
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'Accept' => 'text/xml'
                ]
            ];
            $client = HttpClient::create();
            $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Common.asmx', $options);
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Common.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

            $xml = simplexml_load_string($resp->getContent(false));
            $xml = $xml->children('http://www.w3.org/2003/05/soap-envelope')
                ->Body->children('http://www.sansejour.com/')
                ->GetHotelListDSResponse
                ->GetHotelListDSResult->children('urn:schemas-microsoft-com:xml-diffgram-v1')
                ->diffgram->children('');

            $cities = $this->apiGetCities();

            $hotels = new HotelCollection();
            foreach ($xml->NewDataSet->Table as $hotelXml) {
                
                if ($hotelXml->HotelCode === null) {
                    continue;
                }

                $city = $cities->get($hotelXml->RegionCode);
                $stars = (int) $hotelXml->Category;

                $hotel = Hotel::create($hotelXml->HotelCode, $hotelXml->HotelName, $city, $stars, null, null, null, null, null, null, null, null, null, $hotelXml->Web);
                $hotels->put($hotel->Id, $hotel);
            }
            Utils::writeToCache($this->handle, $file, json_encode($hotels));
        } else {
            $hotels = ResponseConverter::convertToCollection(json_decode($json, true), HotelCollection::class);
        }

        return $hotels;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        $token = $this->getToken();
        
        $xmlReq = '<?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
                <soap12:Body>
                    <GetHotelAllInformations xmlns="http://www.sansejour.com/">
                        <token>' . $token . '</token>
                        <hotelCode>' . $filter->hotelId . '</hotelCode>
                    </GetHotelAllInformations>
                </soap12:Body>
            </soap12:Envelope>'
        ;

        $options = [
            'body' => $xmlReq,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];
        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Hotel.asmx', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Hotel.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

        $xml = simplexml_load_string($resp->getContent(false));
        $xml = $xml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children('http://www.sansejour.com/')
            ->GetHotelAllInformationsResponse
            ->GetHotelAllInformationsResult->children('urn:schemas-microsoft-com:xml-diffgram-v1')
            ->diffgram->children('')
            ->NewDataSet;
        
        $hotel = new Hotel();

        if ($xml->HotelPresentaion === null || $xml->HotelPresentaion->Hotel === null) {
            return $hotel;
        }

        $hotels = $this->apiGetHotels();

        $id = $xml->HotelPresentaion->Hotel;

        $hotelFromList = $hotels->get($id);
        if ($hotelFromList === null) {
            return $hotel;
        }

        $collection = new HotelImageGalleryItemCollection();

        foreach ($xml->HotelPicture as $picture) {
            $hotelImageGalleryItem = new HotelImageGalleryItem();
            $imgId = (int) $picture->RecId;

            $downloadspath = Utils::getDownloadsPath();  
            $dir = $downloadspath . '/' . $this->handle . '/images/' . $id . '/';
            $imgPath = $dir . $imgId . '.jpeg';
            $baseUrl = Utils::getDownloadsBaseUrl();
            $imgUrl = $baseUrl . '/' . $this->handle . '/images/' . $id . '/' . $imgId . '.jpeg';
            
            if (!file_exists($imgPath)) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $base64Data = $this->getImageById($imgId, $token);

                if (strlen($base64Data) > 0) {
                    $baseUrl = Utils::getDownloadsBaseUrl();
                    if (env('APP_ENV') === 'local') {
                        break;
                    }
                    file_put_contents($imgPath, base64_decode($base64Data));
                    $hotelImageGalleryItem->RemoteUrl = $imgUrl;
                    $collection->add($hotelImageGalleryItem);
                }
            } else {
                $hotelImageGalleryItem->RemoteUrl = $imgUrl;
                $collection->add($hotelImageGalleryItem);
            }
        }


        $hotel = Hotel::create($id, $xml->HotelDescription->Adi, $hotelFromList->Address->City,
            (int) $xml->HotelDescription->Kategori, $xml->HotelPresentaion->PresText, null, null, null, null, $collection,
            null, null, null, $hotelFromList->WebAddress
        );

        return $hotel;
    }

    public function getImageById(int $imageId, string $token): string
	{
        $xmlReq = '<?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
                <soap12:Body>
                    <GetHotelImageByID xmlns="http://www.sansejour.com/">
                        <token>' . $token . '</token>
                        <pictureId>'. $imageId .'</pictureId>
                    </GetHotelImageByID>
                </soap12:Body>
            </soap12:Envelope>'
        ;

        $options = [
            'body' => $xmlReq,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];
        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Hotel.asmx', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Hotel.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

        $xml = simplexml_load_string($resp->getContent(false));
        $img = $xml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children('http://www.sansejour.com/')
            ->GetHotelImageByIDResponse
            ->GetHotelImageByIDResult->children('urn:schemas-microsoft-com:xml-diffgram-v1')
            ->diffgram->children('')
            ->NewDataSet
            ->Table
            ->Picture;

        return $img;
	}

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $availabilities = new AvailabilityCollection();
        if ($filter->serviceTypes->first() !== AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            return $availabilities;
        }

        $token = $this->getToken();
        $adults = $filter->rooms->first()->adults;
        $childrenAges = false;
        if (!empty($filter->rooms->first()->childrenAges)) {
            $childrenAges = $filter->rooms->first()->childrenAges->toArray();
        }

		$params = [
            'CheckIn' => $filter->checkIn,
            'Night' => $filter->days,
            'HoneyMoon' => 'false',
            'RegionCode' => empty($filter->cityId) ?  $filter->regionId : $filter->cityId,
            'Currency' => $filter->RequestCurrency,
            'OnlyAvailable' => 'false',
            'CalculateHandlingFee' => 'false',
            'CalculateTransfer' => 'false',
            'ShowPaymentPlanInfo' => 'true',
            'ShowHotelRemarksinPriceSearch' => 'false',
            'MakeControlWebParamForPaximum' => 'false',
            'RoomCriterias' => [
                'RoomCriteria' => [
                    'Adult' => $adults,
                    'Child' => $filter->rooms->first()->children ?: 0
                ]
            ],
            'RequestDatetime' => date('Y-m-d\TH:i:s')
		];
        if ($childrenAges) {
            $ages = [];
            foreach ($childrenAges as $age) {
                $ages[] = ['int' => $age];
            }
            $params['RoomCriterias']['RoomCriteria']['ChildAges'] = $ages;
        }

		if (!empty($filter->hotelId)) {
			$params['HotelCode'] = $filter->hotelId;
        }

        // pricesearch sau priceasearchds?
        $params = [
            'soap12_--_Envelope' => [
                '[xmlns_--_xsi]' => 'http://www.w3.org/2001/XMLSchema-instance',
                '[xmlns_--_xsd]' => 'http://www.w3.org/2001/XMLSchema',
                '[xmlns_--_soap12]' => 'http://www.w3.org/2003/05/soap-envelope',
                'soap12_--_Body' => [
                    'PriceSearch' => [
                        '[xmlns]' => 'http://www.sansejour.com/',
                        'token' => $token,
                        'searchRequest' => $params
                    ]
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($params);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options = [
            'body' => $xmlString,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];
        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Reservation.asmx', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Reservation.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

        $xml = simplexml_load_string($resp->getContent(false));
        $xml = $xml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children('http://www.sansejour.com/')
            ->PriceSearchResponse
            ->PriceSearchResult
            ->RoomOffers
            ->SejourRoomOffer;

        $todayDT = new DateTime();
        $spos = [];
        $spoCall = [];

        foreach ($xml as $offerXml) {
            $hotelId = $offerXml->Hotel;
            $availability = $availabilities->get($hotelId);
            if ($availability === null) {
                $availability = new Availability();
                $availability->Id = $hotelId;
                if ($filter->showHotelName) {
                    $availability->Name = $offerXml->HotelName;
                }
            }
           // $offer = new Offer();

            //$roomId = $offerXml->Room;
            //$roomTypeId = $offerXml->RoomType;
            // $mealId = $offerXml->Board;

            //$price = (float)(($offerXml->HotelNetPrice ?: 0) + $offerXml->ExtraPrice);

            $offerCheckIn = $offerXml->CInDate;
            $offerCheckOut = $offerXml->COutDate;

            //$offer->Days = ;

            // $offerCode = $hotelId . '~' . $roomId.$roomTypeId . '~' . $mealId . '~' . $offerCheckIn
            //     . '~' . $offer->Days . '~' . $price . '~' . $adults
            //     . ($childrenAges ? '~' . implode('|', $childrenAges) : '');
            // $offer->Code = $offerCode;

            // todo: e nevoie?
            //$offer->CheckIn = $offerCheckIn;

            // $currency = new Currency();
            // $currency->Code = $offerXml->Currency;
            // $offer->Currency = $currency;

            $spoStr = "";

            // $offer->InitialPrice = 0;
            // $offer->Net = $price;

            // $offer->Comission = $offerXml->HotelPrice - $offerXml->HotelNetPrice;
			// $offer->Gross = (float) $offerXml->TotalPrice;
			// $offer->InitialPrice = ;

            $sposForPrices = [];
            $roomOfferSpos = $offerXml->CalculatedSPOs->CalculatedSPO;
            $roomInfo = '';

            $paymentPlans = [];
            if ((!empty($roomOfferSpos))) {
                foreach ($roomOfferSpos as $roomOfferSpo) {
                    $spoCode = $offerXml->Hotel . '~' . $roomOfferSpo->No;

                    $spoPaymentPlan = $roomOfferSpo->PaymentPlans->SPOPaymentPlan;
					
                    
                    if ($spoPaymentPlan) {
                        if ((new DateTime($spoPaymentPlan->BeginDate) <= $todayDT) && 
                            (new DateTime($spoPaymentPlan->EndDate) >= $todayDT)) {
                            $paymentPlans[] = $spoPaymentPlan;
                        }
                    }

                    if ((!isset($spos[$spoCode]))) {
                        $key = (string) $offerXml->Hotel. (string) $roomOfferSpo->No;
                        if (!isset($spoCall[$key])) {
                            $spo = $this->getDetailSpecialOffer( $offerXml->Hotel, $roomOfferSpo->No, $token);
                            $spoCall[$key] = $spo;
                        } else {
                            $spo = $spoCall[$key];
                        }
                        $spos[$spoCode] = $spo;
                    } else {
                        $spo = $spos[$spoCode];
                    }

                    $sposForPrices[(string) $roomOfferSpo->No] = [$spo, $roomOfferSpo];
                    $spoStr .= ((strlen($spoStr) > 0) ? " / " : "") . $roomOfferSpo->Type . " " . $roomOfferSpo->Description;
                    $roomInfo .= ((strlen($roomInfo) > 0) ? " + " : "") . $roomOfferSpo->Description;
                }
            }

            $iprice = (float) $offerXml->TotalPrice;
            foreach ($sposForPrices as $spoDetails) {
                list($spo, $roomOfferSpo) = $spoDetails;

                // calcultate initial price
                $ipriceCalc = $this->calculateInitialPrice($iprice, (string) $roomOfferSpo->Type, $spo, $filter->days);
                if ((empty($iprice)) || ($iprice < $iprice)) {
                    $iprice = $ipriceCalc;
                }
            }

            $availabilityStatus = (string) $offerXml->AvailabilityStatus;
            $availabilityStatusDesc = (string) $offerXml->AvailabilityStatusDesc;

            $availabilityStr = Offer::AVAILABILITY_NO;

            if ($availabilityStatusDesc !== 'StopSale') {
                if ($availabilityStatus === 'Ok') {
                    $availabilityStr = Offer::AVAILABILITY_YES;
                } elseif ($availabilityStatus === 'Request') {
                    $availabilityStr = Offer::AVAILABILITY_ASK;
                }
            }

            // $offer->Availability = $availabilityStr;

            $bookingData = [
                'spoStr' => $spoStr,
                'allotmentType' => (string) $offerXml->AllotmentType,
                'night' => (string) $offerXml->Night
            ];
            $bookingDataJson = json_encode($bookingData);

            // $offer->bookingDataJson = $bookingDataJson;

            // $room = new Room();
            // $room->Availability = $offer->Availability;
            // $room->CheckinAfter = $offerCheckIn;
            // $room->CheckinBefore = $offerCheckOut;
            // $room->Currency = $offer->Currency;
            // $room->Id = $roomId;
            // $roomMerch = new RoomMerch();
            // $roomMerch->Code = $roomId;
            // $roomMerch->Id = $roomId;
            // $roomMerch->Name = $offerXml->RoomName;
            // $roomMerch->Title = $offerXml->RoomName .' '. $offerXml->RoomTypeName;

            // $roomMerchType = new RoomMerchType();
            // $roomMerchType->Id = $roomTypeId;
            // $roomMerchType->Title =  $roomMerch->Title;

            // $roomMerch->Type = $roomMerchType;
            
            // $room->Merch = $roomMerch;
            // $room->Code = $roomTypeId;
            
            // $room->InfoTitle = $roomInfo;
            
            // $offer->Item = $room;
            // $rooms = new RoomCollection([$room]);
            // $offer->Rooms = $rooms;

            // $mealItem = new MealItem();
            // $mealItem->Currency = $currency;
            // $mealMerch = new MealMerch();
            // $mealMerch->Id = $offerXml->Board;
            // $mealMerch->Title = $offerXml->BoardName;

            // //dump($offerXml);

            // $mealMerchType = new MealMerchType();
            // $mealMerchType->Id = $mealMerch->Id;
            // $mealMerchType->Title = $mealMerch->Title;
            // $mealMerch->Type = $mealMerchType;
            // $mealItem->Merch = $mealMerch;
            // $offer->MealItem = $mealItem;

            // // departure transport item merch
            // $departureTransportItemMerch = new TransportMerch();
            // $departureTransportItemMerch->Title = 'CheckIn: ' . $offerCheckIn;

            // // DepartureTransportItem Return Merch
            // $departureTransportItemReturnMerch = new TransportMerch();
            // $departureTransportItemReturnMerch->Title = 'CheckOut: ' . $offerCheckOut;

            // // DepartureTransportItem Return
            // $departureTransportItemReturn = new ReturnTransportItem();
            // $departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
            // $departureTransportItemReturn->Currency = $currency;
            // $departureTransportItemReturn->DepartureDate = $offerCheckOut;
            // $departureTransportItemReturn->ArrivalDate = $offerCheckOut;

            // // DepartureTransportItem
            // $departureTransportItem = new DepartureTransportItem();
            // $departureTransportItem->Merch = $departureTransportItemMerch;
            // $departureTransportItem->Currency = $currency;
            // $departureTransportItem->DepartureDate = $offerCheckIn;
            // $departureTransportItem->ArrivalDate = $offerCheckIn;
            // $departureTransportItem->Return = $departureTransportItemReturn;

            // $offer->DepartureTransportItem = $departureTransportItem;

            // $offer->ReturnTransportItem = $departureTransportItemReturn;



            $offer = Offer::createIndividualOffer(
                $hotelId, 
                $offerXml->Room, 
                $offerXml->RoomType, 
                $offerXml->RoomName .' '. $offerXml->RoomTypeName,
                $offerXml->Board, 
                $offerXml->BoardName, 
                new DateTime($offerCheckIn), 
                new DateTime($offerCheckOut), 
                $adults, $childrenAges,
                $offerXml->Currency, 
                ($offerXml->HotelNetPrice ?: 0) + $offerXml->ExtraPrice, 
                $iprice, 
                (float) $offerXml->TotalPrice,
                $offerXml->HotelPrice - $offerXml->HotelNetPrice, 
                $availabilityStr, 
                $roomInfo, 
                null, 
                $bookingDataJson
            );

            $payments = new OfferPaymentPolicyCollection();
            foreach ($paymentPlans as $paymentPlanXml) {
            
                if (!empty($paymentPlanXml->PlanDate1)) {
                    $paymentPlan1 = new OfferPaymentPolicy();
                    $paymentPlan1->Currency = $offer->Currency;
                    $paymentPlan1->PayAfter = $paymentPlanXml->PlanDate1;
                    $paymentPlan1->PayUntil = $offerCheckIn;
                    $percent1 = (float) $paymentPlanXml->Percent1;
                    $amount1 = ($offer->Net + $offer->Comission) * $percent1/100;
                    $paymentPlan1->Amount = $amount1;
                    $payments->add($paymentPlan1);

                    if (!empty($paymentPlanXml->PlanDate2)) {
                        $afterDT = new DateTime($paymentPlanXml->PlanDate2);
                        $paymentPlan1->PayUntil = $afterDT->modify('-1 day')->format('Y-m-d');
                        $paymentPlan2 = new OfferPaymentPolicy();
                        $paymentPlan2->Currency = $offer->Currency;
                        $paymentPlan2->PayAfter = $paymentPlanXml->PlanDate2;
                        $paymentPlan2->PayUntil = $offerCheckIn;
                        $percent2 = (float) $paymentPlanXml->Percent2;
                        $amount2 = ($offer->Net + $offer->Comission) * $percent2/100;
                        $paymentPlan2->Amount = $amount2;
                        $payments->add($paymentPlan2);
                    }
                }
            }

            $offer->Payments = $payments;

            $offer->CancelFees = $this->convertIntoCancellation($payments);

            $offers = $availability->Offers;
            if ($offers === null) {
                $offers = new OfferCollection();
            }
            $offers->put($offer->Code, $offer);
            $availability->Offers = $offers;

            $availabilities->put($availability->Id, $availability);
        }

        return $availabilities;
    }

    private function convertIntoCancellation(OfferPaymentPolicyCollection $offerPaymentPolicyCollection): OfferCancelFeeCollection
    {
        $cancelFees = new OfferCancelFeeCollection();

        $i = 0;
        $prices = 0;

        /** @var OfferPaymentPolicy $payment */
        foreach ($offerPaymentPolicyCollection as $payment) {
            $cancelFee = new OfferCancelFee();
            $cancelFee->Currency = $payment->Currency;

            $cancelFee->DateStart = $payment->PayUntil;

            if ($offerPaymentPolicyCollection->get($i + 1) !== null) {
                $cancelFee->DateEnd = $offerPaymentPolicyCollection->get($i + 1)->PayAfter;
            } else {
                $cancelFee->DateEnd = $payment->PayUntil;
            }

            $prices += $payment->Amount;

            $cancelFee->Price = $prices;
            
            $cancelFees->add($cancelFee);
            $i++;
        }

        return $cancelFees;
    }

    private function calculateInitialPrice(float $price, string $spoType, SimpleXMLElement $spo, int $nights)
	{	
        $originalPrice = false;
        if ($spoType === 'EBR' || $spoType === 'EB') {
            if (!empty($spo->EBpercentage)) {
                // calculate original price
                $originalPrice = round($price + (($price * $spo->EBpercentage) / (100 - $spo->EBpercentage)));
            }
        } elseif ($spoType === 'GP') {
            // go through each discount until you find the right nights
            foreach ($spo as $spoFreeNights) {
                if ($spoFreeNights->XStay == $nights) {
                    // calculate initial price
                    $originalPrice = round($price + (($price / $spoFreeNights->YPay) * ($spoFreeNights->XStay - $spoFreeNights->YPay)));
                }
            }
		}
		
		// return original price
		return $originalPrice ?: $price;
	}

    private function getDetailSpecialOffer($hotelCode, $spoNumber, $token): SimpleXMLElement
	{
        $xmlReq = '<?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
                <soap12:Body>
                    <GetDetailSpo xmlns="http://www.sansejour.com/">
                        <token>' . $token . '</token>
                        <hotelCode>'. $hotelCode .'</hotelCode>
                        <SPONumber>'. $spoNumber .'</SPONumber>
                    </GetDetailSpo>
                </soap12:Body>
            </soap12:Envelope>'
        ;

        $options = [
            'body' => $xmlReq,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];
        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Hotel.asmx', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Hotel.asmx', $options, $resp->getContent(false), $resp->getStatusCode());

        $xml = simplexml_load_string($resp->getContent(false));
        $SPOResp = $xml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children('http://www.sansejour.com/')
            ->GetDetailSpoResponse
            ->GetDetailSpoResult->children('urn:schemas-microsoft-com:xml-diffgram-v1')
            ->diffgram->children('')
            ->NewDataSet
            ->Table1;
		
		return $SPOResp;
	}

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        SanSejourValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $token = $this->getToken();
		$passengers = $filter->Items->first()->Passengers;

        $offer = $filter->Items->first();

        $checkInDT = new DateTime($filter->Items->first()->Room_CheckinAfter);

        $bookingData = json_decode($offer->Offer_bookingDataJson, true);

		$adults = 0;
		$children = 0;
		$infants = 0;
		$passengersData = [];
		$customerOpr = [];
		$i = 0;
        /** @var Passenger $passenger */
		foreach ($passengers as $passenger) {
			$i++;
            $birthDate = new DateTime($passenger->BirthDate);
			$age = $checkInDT->diff($birthDate)->y;

			$customerOnRoom = [];
			$passengerData = [];

			$passengerData['Title'] = $passenger->Gender === 'male' ? 'Mr' : 'Mrs';
			$passengerData['Name'] = $passenger->Firstname . ' ' . $passenger->Lastname;
			$passengerData['BirthDate'] = $passenger->BirthDate . 'T00:00:00';
			$passengerData['Age'] = $age;
			$passengerData['Nationalty'] = 'RO';
			$passengerData['ApplyVisa'] = 'false';
			$passengerData['checkApplyVisaFromNationality'] = 'false';
			$passengerData['ID'] = $i;
			
			if ($passenger->IsAdult) {
				$adults++;
			} else {
				if ($age <= 2) {
					$passengerData['Title'] = 'Inf';
					$infants++;
				} else {
					$passengerData['Title'] = 'Chd';
					$children++;
				}
			}
			$passengersData[] = ['Customer' => $passengerData];
			$customerOnRoom['CustNum'] = $passengerData['ID'];
			$customerOnRoom['ResOrderNum'] = 1;
			$customerOnRoom['CinDate'] = $offer->Room_CheckinAfter;	
			$customerOpr[] = ['CustomerOpr' => $customerOnRoom];
		}

		$params = [
			"ri" => [
				"OnlyCalculate" => 'false',
				"source" => 0,
				"OnlyHotel" => 'true',
				"OwnProvider" => 'true',
				"DontWaitForCalculation" => 'false',
				"customer" => $passengersData,
				"resHotel" => [
					"ResHotel" => [
						"OrdNum" => 1,
						"Cindate" => $offer->Room_CheckinAfter,
						"CoutDate" => $offer->Room_CheckinBefore . "T00:00:00",
						"Day" => $bookingData['night'],
						"HotelCode" => $offer->Hotel->InTourOperatorId,
						"HotelNote" => $bookingData['spoStr'],
						"RoomTypeCode" => $offer->Room_Type_InTourOperatorId,
						"RoomCode" => $offer->Room_Def_Code,
						"BoardCode" => $offer->Board_Def_InTourOperatorId,
						"Adult" => $adults,
						"Child" => $children,
						"Infant" => $infants,
						"RoomCount" => 1,
						"ResStatus" => "*",
						"ArrTrf" => 'false',
						"OnlyTransfer" => 'false',
						"OnlyService" => 'false',
						"DepTrf" => 'false',
						"SatFTip" => 0,
						"AllotmentType" => $bookingData['allotmentType'],
						"HoneyMooners" => 'false',
						"SellDate" => date('Y-m-d') . 'T00:00:00+02:00',
						"CustomerOpr" => $customerOpr
					]
				]
			],
			"isB2B" => 'false'
		];

        $params = [
            'soap12_--_Envelope' => [
                '[xmlns_--_xsi]' => 'http://www.w3.org/2001/XMLSchema-instance',
                '[xmlns_--_xsd]' => 'http://www.w3.org/2001/XMLSchema',
                '[xmlns_--_soap12]' => 'http://www.w3.org/2003/05/soap-envelope',
                'soap12_--_Body' => [
                    'MultiMakeReservation' => [
                        '[xmlns]' => 'http://www.sansejour.com/',
                        'token' => $token,
                        $params
                    ]
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($params);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options = [
            'body' => $xmlString,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept' => 'text/xml'
            ]
        ];
        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Reservation.asmx', $options);
        $content = $resp->getContent(false);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Reservation.asmx', $options, $content, $resp->getStatusCode());

        $xml = simplexml_load_string($content);
        $xml = $xml->children('http://www.w3.org/2003/05/soap-envelope')
            ->Body->children('http://www.sansejour.com/')
            ->MultiMakeReservationResponse
            ->MultiMakeReservationResult;

        $booking = new Booking();
        if (!empty($xml->VoucherNo)) {
            $booking->Id = $xml->VoucherNo;
        }
        
        return [$booking, $content];
    }

    public function apiTestConnection(): bool
    {
        SanSejourValidator::make()
            ->validateUsernameAndPassword($this->post);

        $token = $this->getToken();
        if (!empty($token)) {
            return true;
        }
        return false;
    }
}
