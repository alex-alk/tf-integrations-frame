<?php

namespace Integrations\Cyberlogic;

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
use App\Entities\Availability\TransportMerch;
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
use App\Entities\Region;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Support\Collections\Collection;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\[];
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\Response\ResponseInterface;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\Validator;
use SimpleXMLElement;
use Utils\Utils;

class CyberlogicApiService extends AbstractApiService
{

    private string $language = 'en';

    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetCountries(): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $requestArr = [
            'PlaceSearchRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'Language' => $this->language,
                'PlaceType' => 'Countries'
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);

        $options['body'] = "xml=$xml";

        $response = $this->request($this->apiUrl . '/PlaceSearch', HttpClient::METHOD_POST, $options);
        $rawResponse = $response->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $countryResults =  $responseXml->Response->Countries;

        $countries = [];
        
        foreach ($countryResults->Country as $countryResult) {
            $country = new Country();
            $country->Id = (string) $countryResult->attributes()->ID;
            $country->Code = $country->Id;
            $country->Name = (string) $countryResult->attributes()->Name;
            $countries->add($country);
        }

        return $countries;
    }

    public function apiGetCities(CitiesFilter $params = null): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $requestArr = [
            'PlaceSearchRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'Language' => $this->language,
                'PlaceType' => 'Cities'
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);

        $options['body'] = "xml=$xml";

        $response = $this->request($this->apiUrl . '/PlaceSearch', HttpClient::METHOD_POST, $options);
        $rawResponse = $response->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $citiesResult =  $responseXml->Response->Cities;

        $mapPrefecture = $this->getCountryCodeByPrefectureIdAsMap();
        $mapCountries = $this->getCountriesByCountryCodeAsMap();
        $mapProvinceId = $this->getProvinceNameByProvinceIdAsMap();

        $cities = [];
        foreach ($citiesResult->City as $cityResult) {

            $provinceId = (string) $cityResult->attributes()->province_id;
            $prefectureId = (string) $cityResult->attributes()->prefecture_id;
            $countryCode = $mapPrefecture[$prefectureId];
            $contryName = $mapCountries[$countryCode];

            $country = new Country();
            $country->Id = $countryCode;
            $country->Code = $country->Id;
            $country->Name = $contryName;

            $county = new Region();
            $county->Id = $provinceId;
            $county->Name = $mapProvinceId[$county->Id];
            $county->Country = $country;

            $city = new City();
            $city->Id = (string) $cityResult->attributes()->city_id;
            $city->Name = (string) $cityResult->CityName;
            $city->Country = $country;
            $city->County = $county;
            $cities->put($city->Id, $city);
        }

        return $cities;
    }

    public function apiGetRegions(): []
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $cities = $this->apiGetCities();
        $regions = [];

        foreach ($cities as $city) {
            $regions->put($city->County->Id, $city->County);
        }

        return $regions;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        //todo: de verficat daca vin dubluri
        Validator::make()->validateUsernameAndPassword($this->post);
        Validator::make()->validateAvailabilityFilter($filter);

        $checkIn = $filter->checkIn;
        $days = $filter->days;
        $checkOut = $filter->checkOut;
        $cityCode = $filter->cityId;
        $provinceId = $filter->regionId;
        $hotelId = $filter->hotelId;
        $adults = $filter->rooms->get(0)->adults;
        $children = (int) $filter->rooms->get(0)->children;
        
        $childrenAges = '';
        if ($filter->rooms->get(0)->childrenAges) {
           foreach ($filter->rooms->get(0)->childrenAges as $age) {
                if ($age !== '') {
                    $childrenAges .= $age . ',';
                }
            } 
        }
        
        $childrenAges = rtrim($childrenAges, ',');

        $requestArr = [
            'SearchHotelsRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'DateFrom' => $checkIn,
                'DateTo' => $checkOut,
                'Rooms' => [
                    'Room' => [
                        '[Adults]' => $adults
                    ]
                ],
                'Language' => $this->language,
                'UseTariff' => true,
                'IsOptimized' => true
            ]
        ];

        if ($children > 0) {
            $requestArr['SearchHotelsRequest']['Rooms']['Room']['[Children]'] = $children;
            $requestArr['SearchHotelsRequest']['Rooms']['Room']['[ChildrenAges]'] = $childrenAges;
        }

        if (!empty($cityCode)) {
            $requestArr['SearchHotelsRequest']['City'] = $cityCode;
        }

        if (!empty($provinceId)) {
            $requestArr['SearchHotelsRequest']['Province'] = $provinceId;
        }

        if (!empty($hotelId)) {
            $requestArr['SearchHotelsRequest']['HotelID'] = $hotelId;
        }
        $xml = Utils::arrayToXmlString($requestArr);

        $options['body'] = "xml=$xml";
        $response = [];

        $responseObj = $this->request($this->apiUrl . '/HotelsSearch',HttpClient::METHOD_POST,  $options);
        $rawResponse = $responseObj->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $responseHotels =  $responseXml->Response->Hotels->Hotel;

        $currency = new Currency();
        $currency->Code = 'EUR';

        foreach ($responseHotels as $responseHotel) {
            $hotel = new Availability();
            $hotel->Id = (string) $responseHotel->attributes()->ID;

            $offers = new OfferCollection();

            foreach ($responseHotel->Rooms->Room as $room) {
                foreach ($room->Name as $name) {

                    $ratePlan = $name->RatePlan;

                    $boards = $this->getBoards($ratePlan);
                    if (count($boards) === 0) {
                        continue;
                    }

                    foreach ($boards as $board) {
                        $offerObj = new Offer();
                        $roomId = (string) $room->attributes()->ID;

                        $offerCodeString = $hotel->Id . '~'
                            . $roomId . '~' 
                            . $board['code'] . '~'
                            . $checkIn . '~'
                            . $checkOut . '~'
                            . (float) $this->getOfferNet($ratePlan, $board['code']) . '~'
                            . $adults . '~'
                            . $children . '~'
                            . $childrenAges;

                        $offerCode = $offerCodeString;
                        $offerObj->Code = $offerCode;
                        $offerObj->ContractId = (string) $ratePlan->attributes()->ContractID;

                        $offerObj->CheckIn = $checkIn;
                        $offerObj->Currency = $currency;

                        $offerObj->Net = (float) $this->getOfferNet($ratePlan, $board['code']);
                        $offerObj->Gross = $offerObj->Net;
                        $offerObj->InitialPrice = (float) $this->getOfferInitial($ratePlan, $board['code']);
                        $offerObj->Comission = 0;
                        $offerObj->Availability = (int) $ratePlan->attributes()->Availability > 0 ? Offer::AVAILABILITY_YES : Offer::AVAILABILITY_ASK;
                        $offerObj->Days = $days;

                        // Rooms
                        $roomResp = new Room();
                        $roomResp->Id = $roomId;
                        $roomResp->CheckinBefore = $checkOut;
                        $roomResp->CheckinAfter = $checkIn;
                        $roomResp->Currency = $currency;
                        $roomResp->Quantity = 1;
                        $roomResp->Availability = $offerObj->Availability;

                        $roomMerchType = new RoomMerchType();
                        $roomMerchType->Id = $roomId;
                        $roomMerchType->Title = $name->attributes()->RoomName;

                        $merch = new RoomMerch();
                        $merch->Id = $roomId;
                        $merch->Title = $roomMerchType->Title;
                        $merch->Type = $roomMerchType;
                        $merch->Code = $roomId;
                        $merch->Name = $roomMerchType->Title;

                        $roomResp->Merch = $merch;
                        $rooms = new RoomCollection([$roomResp]);

                        $offerObj->Rooms = $rooms;

                        $offerObj->Item = $rooms->get(0);

                        $mealItem = new MealItem();

                        $boardTypeName = $board['name'];

                        // MealItem Merch
                        $boardMerch = new MealMerch();
                        $boardMerch->Title = $boardTypeName;
                        $boardMerch->Id = $board['code'];

                        // MealItem
                        $mealItem->Merch = $boardMerch;
                        $mealItem->Currency = $currency;

                        $offerObj->MealItem = $mealItem;

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
                        
                        $offers->put($offerObj->Code, $offerObj);
                    }
                }
            }

            $hotel->Offers = $offers;
            $response->add($hotel);
        }

        return $response;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        CyberlogicValidator::make()->validateBookHotelFilter($filter);

        $hotelCode = $filter->Items->get(0)->Hotel->InTourOperatorId;
        $checkIn = $filter->Items->get(0)->Room_CheckinAfter;
        $checkOut = $filter->Items->get(0)->Room_CheckinBefore;
        $offerCode = $filter->Items->get(0)->Offer_Code;
        $roomId = $filter->Items->get(0)->Room_Type_InTourOperatorId;
        $contractId = $filter->Items->get(0)->Offer_ContractId;
        $boardCode = $filter->Items->get(0)->Board_Def_InTourOperatorId;
        $passengers = $post['args'][0]['Items'][0]['Passengers'];
        
        $booking = new Booking();

        $adults = [];
        $clients = [];

        // get passengers
        $passengerReference = 0;
        foreach ($passengers as $passenger) {

            if (!empty($passenger['Firstname'])) {
                $title = '';
                if ($passenger['IsAdult']) {
                    if ($passenger['Gender'] == '2') {
                        $title = 2;
                    } else {
                        $title = 1;
                    }
                } else {
                    $title = 3;
                }
                $adults[] = [
                    'Adult' => [
                        ['[Title]' => $title],
                        ['[Name]' => $passenger['Firstname']],
                        ['[Surname]' => $passenger['Lastname']],
                        ['[Reference]' => $passengerReference]
                    ]
                ];
                $clients[] = [
                    'Client' => [
                        ['[Reference]' => $passengerReference]
                    ]
                ];
            }
        }

        // get boardTypeId
        $boardTypeId = '';
        switch ($boardCode) {
            case 'RR':
                $boardTypeId = '1';
                break;
            case 'BB':
                $boardTypeId = '2';
                break;
            case 'HB':
                $boardTypeId = '3';
                break;
            case 'FB':
                $boardTypeId = '4';
                break;
            case 'AI':
                $boardTypeId = '5';
                break;
            case 'UI':
                $boardTypeId = '6';
                break;
        }

        // get services
        $services = [
            'Hotels' => [
                'Hotel' => [
                    '[HotelID]' => $hotelCode,
                    '[RoomID]' => $roomId,
                    '[ContractID]' => $contractId,
                    '[RoomQuantity]' => 1,
                    '[BoardBasis]' => $boardTypeId,
                    'ArrivalDate' => $checkIn,
                    'DepartureDate' => $checkOut,
                    'ClientList' => $clients
                ]
            ]
        ];

        $requestData = [
            'ReservationSchema' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'Reference' => $offerCode,
                'BookingType' => 4,
                'BookingStatus' => 1,
                'UseHotelTariff' => 1,
                'ArrivalDate' => $checkIn,
                'DepartureDate' => $checkOut,
                'Adults' => $adults,
                'ClientTransferArrivalType' => 1,
                'ClientTransferDepartureType' => 1,
                'Services' => $services
            ]
        ];

        $xml = Utils::arrayToXmlString($requestData);

        $options['body'] = "xml=$xml";

        $responseObj = $this->request($this->apiUrl . '/Reservation', HttpClient::METHOD_POST, $options);
        $rawResponse = $responseObj->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $response =  $responseXml->Response;

        $booking->Id = (string) $response->ReservationID;
        //$booking->rawResp = json_encode($response);
        
        return [$booking, json_encode($response)];
    }

    public function apiGetHotels(): []
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $requestArr = [
            'HotelListRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'Language' => $this->language
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);

        $options['body'] = "xml=$xml";

        $response = $this->request($this->apiUrl . '/HotelList', HttpClient::METHOD_POST,  $options);

        $rawResponse = $response->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $responseHotels =  $responseXml->Response->Hotels;

        $countriesMap = $this->getCountriesByCountryCodeAsMap();

        $hotels = [];
        foreach ($responseHotels->Hotel as $hotelResponse) {

            // $address->City->Country
            $country = new Country();
            $country->Id = (string) $hotelResponse->Country;
            $country->Code = $country->Id;
            $country->Name = $countriesMap[(string) $country->Id];

            // $address->City->County
            $county = new Region();
            $county->Id = (string) $hotelResponse->State;
            $county->Name = (string) $hotelResponse->StateProvinceName;
            $county->Country = $country;
            
            // $address->City
            $city = new City();
            $city->Id = (string) $hotelResponse->City;
            $city->Name = (string) $hotelResponse->CityName;
            $city->Country = $country;
            $city->County = $county;

            // $hotel->Address
            $address = new HotelAddress();
            $address->Latitude = (string) $hotelResponse->Latitude;
            $address->Longitude = (string) $hotelResponse->Longitude;
            $address->Details = $hotelResponse->Address1 . ' ' . $hotelResponse->Address2;
            $address->City = $city;

            $image = new HotelImageGalleryItem();
            // todo: trebuie sa il primesc prin api?
            $oldUrl = 'https://mediaapi.filostravel.gr/images/hotels/';
            $image->RemoteUrl = $oldUrl . str_replace('\\\\', '', (string) $hotelResponse->ImageThumbURL);
            $image->Alt = (string) $hotelResponse->Name;

            // $hotel->Content->ImageGallery
            $imageGallery = new HotelImageGallery();
            $imageGallery->Items = new HotelImageGalleryItemCollection([$image]);

            // $hotel->Content
            $content = new HotelContent();
            $content->Content = (string) $hotelResponse->Description;
            $content->ImageGallery = $imageGallery;

            $hotel = new Hotel();
            $hotel->Id = (string) $hotelResponse->attributes()->ID;
            $hotel->Name = (string) $hotelResponse->Name;
            $hotel->Stars = (int) $hotelResponse->Rating;
            $hotel->WebAddress = (string) $hotelResponse->URL;
            $hotel->Content = $content;
            $hotel->Address = $address;

            $hotels->add($hotel);
        }

        return $hotels;
    }

    private function getCancelFeesByHotel(string $hotelId): ?SimpleXMLElement
    {
        $policies = null;

        $requestArr = [
            'HotelCancellationListRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'HotelID' => $hotelId
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);

        $options['body'] = "xml=$xml";

        $response = $this->request($this->apiUrl . '/HotelCancellationPolicies', HttpClient::METHOD_POST, $options);
        $rawResponse = $response->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $policiesResponse =  $responseXml->Response->HotelCancelationPolicies->Hotel;

        if ($policiesResponse !== null && $policiesResponse->Policy !== null) {
            $policies = $policiesResponse->Policy->HotelPolicy;
        }
        return $policies;
    }

    public function calculateCancelFee(?SimpleXMLElement $policies, CancellationFeeFilter $filter):OfferCancelFeeCollection
    {
        $ret = new Collection();
        $result = new OfferCancelFeeCollection();
        $skipNextRules = false;

        if ($policies === null) {
            return $result;
        }

        foreach ($policies as $policy) {
            if (!$skipNextRules) {
                /*
                cases:
                1. Out of interval
                2. FeeTypeID: 1 No Show Fee, 2 Early Departure Fee, 3 Cancellation Fee
                3. Criterion: 1 Stay, 2 Arrival(not used)
                4. Amount Type: 1 % of Reservation, 2 Night(s) of Stay
                */
                $checkInDate = new DateTimeImmutable($post['args'][0]['CheckIn']);
                $checkOutDate = new DateTimeImmutable($post['args'][0]['CheckOut']);
                $stayFromDate = new DateTimeImmutable($policy->StayFrom);
                $stayToDate = new DateTimeImmutable($policy->StayTo);
                $beforeArrivalFromInterval = new DateInterval('P' . $policy->BeforeArrivalFrom . 'D');
                $beforeArrivalToInterval = new DateInterval('P' . $policy->BeforeArrivalTo . 'D');
                $nightsOfStay = $checkOutDate->diff($checkInDate)->days;

                $dateStart = '';
                $dateEnd = '';
                $fee = 0.0;
                $shouldSkip = false;

                // if checkIn and checkOut are inside the response interval
                $isInInterval = $checkInDate >= $stayFromDate && $checkOutDate <= $stayToDate;

                // if out of interval, skip
                if ($isInInterval) {
                    // checkIn - beforeArrivalFrom
                    $dateStart = (new DateTime($post['args'][0]['CheckIn']))->sub($beforeArrivalFromInterval)->format('Y-m-d');

                    // checkIn - beforeArrivalEnd
                    $dateEnd = (new DateTime($post['args'][0]['CheckIn']))->sub($beforeArrivalToInterval)->format('Y-m-d');
                } else {
                    continue;
                }

                $amount = (float) $policy->Amount;

                // if 1 night of stay, maximum 1 night fee
                if ((int) $policy->AmountTypeID === AmountType::NIGHTS_OF_STAY) {

                    // skip the next rules if the number of nights of the policy is greater and set end date
                    if ($amount >= $nightsOfStay) {
                        $dateEnd = $post['args'][0]['CheckIn'];
                        $skipNextRules = true;
                        $amount = $nightsOfStay;
                    }
                    $fee = $post['args'][0]['SuppliedPrice'] * $amount / $nightsOfStay;
                } else {
                    $fee = $post['args'][0]['SuppliedPrice'] * $amount / 100;
                }

                // compare with the rest of the array
                foreach ($ret as $policyRow) {
                    // only the record with the biggest fee will be kept
                    if ((int) $policyRow['FeeTypeID'] === FeeType::NO_SHOW_FEE || (int) $policyRow['FeeTypeID'] === FeeType::EARLY_DEPARTURE_FEE) {
                        $priceRow = (float) $policyRow['Price'];

                        // replace the price if needed
                        if ($fee > $priceRow) {
                            $policyRow['Price'] = $fee;
                        }
                        $shouldSkip = true;
                    } elseif ($policyRow['DateEnd'] === $dateEnd && $policyRow['Price'] = $fee) {
                        $shouldSkip = true;
                    } elseif ($policyRow['DateEnd'] === $dateEnd) {
                        throw new Exception('DateEnd error ' . $policy->attributes()->PolicyID . ' - ' . $policyRow['PolicyID']);
                    }
                }
                if ($shouldSkip) {
                    continue;
                }

                // FeeType No show or Early Departure: dates will be check In
                if ((int) $policy->FeeTypeID === FeeType::NO_SHOW_FEE || (int) $policy->FeeTypeID === FeeType::EARLY_DEPARTURE_FEE) {
                    $dateStart = $post['args'][0]['CheckIn'];
                    $dateEnd = $post['args'][0]['CheckIn'];
                }

                if ((int) $policy->AmountTypeID === AmountType::PERCENT_OF_RESERVATION) {
                    $amount = (float) $policy->Amount;
                    $fee = $post['args'][0]['SuppliedPrice'] * $amount / 100;
                }

                $cp['DateStart'] = $dateStart;
                $cp['DateEnd'] = $dateEnd;
                $cp['Price'] = $fee;
                $currency = new Currency();
                $currency->Code = 'EUR';
                $cp['Currency'] = $currency;
                $cp['FeeTypeID'] = (string) $policy->FeeTypeID;
                $cp['PolicyID'] = (string) $policy->attributes()->PolicyID;
                $cp['PolicyText'] = (string) $policy->PolicyText;
                $cp['CriterionID'] = (string) $policy->CriterionID;

                $ret->add($cp);

                $cpObj = new OfferCancelFee();
                $cpObj->DateStart = $cp['DateStart'];
                $cpObj->DateEnd = $cp['DateEnd'];
                $cpObj->Currency = $cp['Currency'];
                $cpObj->Price = $cp['Price'];

                $result->add($cpObj);
            }
        }

        return $result;
    }

    
    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    {
        $policies = $this->getCancelFeesByHotel($post['args'][0]['Hotel']['InTourOperatorId']);

        $ret = new Collection();
        $result = new OfferCancelFeeCollection();
        $skipNextRules = false;

        if ($policies === null) {
            return $result;
        }

        foreach ($policies as $policy) {
            if (!$skipNextRules) {
                /*
                cases:
                1. Out of interval
                2. FeeTypeID: 1 No Show Fee, 2 Early Departure Fee, 3 Cancellation Fee
                3. Criterion: 1 Stay, 2 Arrival(not used)
                4. Amount Type: 1 % of Reservation, 2 Night(s) of Stay
                */
                $checkInDate = new DateTimeImmutable($post['args'][0]['CheckIn']);
                $checkOutDate = new DateTimeImmutable($post['args'][0]['CheckOut']);
                $stayFromDate = new DateTimeImmutable($policy->StayFrom);
                $stayToDate = new DateTimeImmutable($policy->StayTo);
                $beforeArrivalFromInterval = new DateInterval('P' . $policy->BeforeArrivalFrom . 'D');
                $beforeArrivalToInterval = new DateInterval('P' . $policy->BeforeArrivalTo . 'D');
                $nightsOfStay = $checkOutDate->diff($checkInDate)->days;

                $dateStart = '';
                $dateEnd = '';
                $fee = 0.0;
                $shouldSkip = false;

                // if checkIn and checkOut are inside the response interval
                $isInInterval = $checkInDate >= $stayFromDate && $checkOutDate <= $stayToDate;

                // if out of interval, skip
                if ($isInInterval) {
                    // checkIn - beforeArrivalFrom
                    $dateStart = (new DateTime($post['args'][0]['CheckIn']))->sub($beforeArrivalFromInterval)->format('Y-m-d');

                    // checkIn - beforeArrivalEnd
                    $dateEnd = (new DateTime($post['args'][0]['CheckIn']))->sub($beforeArrivalToInterval)->format('Y-m-d');
                } else {
                    continue;
                }

                $amount = (float) $policy->Amount;

                // if 1 night of stay, maximum 1 night fee
                if ((int) $policy->AmountTypeID === AmountType::NIGHTS_OF_STAY) {

                    // skip the next rules if the number of nights of the policy is greater and set end date
                    if ($amount >= $nightsOfStay) {
                        $dateEnd = $post['args'][0]['CheckIn'];
                        $skipNextRules = true;
                        $amount = $nightsOfStay;
                    }
                    $fee = $post['args'][0]['SuppliedPrice'] * $amount / $nightsOfStay;
                } else {
                    $fee = $post['args'][0]['SuppliedPrice'] * $amount / 100;
                }

                // compare with the rest of the array
                foreach ($ret as $policyRow) {
                    // only the record with the biggest fee will be kept
                    if ((int) $policyRow['FeeTypeID'] === FeeType::NO_SHOW_FEE || (int) $policyRow['FeeTypeID'] === FeeType::EARLY_DEPARTURE_FEE) {
                        $priceRow = (float) $policyRow['Price'];

                        // replace the price if needed
                        if ($fee > $priceRow) {
                            $policyRow['Price'] = $fee;
                        }
                        $shouldSkip = true;
                    } elseif ($policyRow['DateEnd'] === $dateEnd && $policyRow['Price'] = $fee) {
                        $shouldSkip = true;
                    } elseif ($policyRow['DateEnd'] === $dateEnd) {
                        throw new Exception('DateEnd error ' . $policy->attributes()->PolicyID . ' - ' . $policyRow['PolicyID']);
                    }
                }
                if ($shouldSkip) {
                    continue;
                }

                // FeeType No show or Early Departure: dates will be check In
                if ((int) $policy->FeeTypeID === FeeType::NO_SHOW_FEE || (int) $policy->FeeTypeID === FeeType::EARLY_DEPARTURE_FEE) {
                    $dateStart = $post['args'][0]['CheckIn'];
                    $dateEnd = $post['args'][0]['CheckIn'];
                }

                if ((int) $policy->AmountTypeID === AmountType::PERCENT_OF_RESERVATION) {
                    $amount = (float) $policy->Amount;
                    $fee = $post['args'][0]['SuppliedPrice'] * $amount / 100;
                }

                $cp['DateStart'] = $dateStart;
                $cp['DateEnd'] = $dateEnd;
                $cp['Price'] = $fee;
                $currency = new Currency();
                $currency->Code = 'EUR';
                $cp['Currency'] = $currency;
                $cp['FeeTypeID'] = (string) $policy->FeeTypeID;
                $cp['PolicyID'] = (string) $policy->attributes()->PolicyID;
                $cp['PolicyText'] = (string) $policy->PolicyText;
                $cp['CriterionID'] = (string) $policy->CriterionID;

                $ret->add($cp);

                $cpObj = new OfferCancelFee();
                $cpObj->DateStart = $cp['DateStart'];
                $cpObj->DateEnd = $cp['DateEnd'];
                $cpObj->Currency = $cp['Currency'];
                $cpObj->Price = $cp['Price'];

                $result->add($cpObj);
            }
        }

        return $result;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()->validateHotelDetailsFilter($filter);

        $hotelId = $filter->hotelId;

        Validator::make()->validateUsernameAndPassword($this->post);
        $requestArr = [
            'HotelInfoRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'HotelID' => $hotelId,
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);

        $options['body'] = "xml=$xml";

        $responseHotel = $this->request($this->apiUrl . '/HotelInfo', HttpClient::METHOD_POST, $options);
        $rawResponse = $responseHotel->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $response =  $responseXml->Response->Hotels->Hotel;
        //dump($response);
        // todo:
        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();
        foreach ($response->photos->photogallery as $imageResponse) {
            $image = new HotelImageGalleryItem();
            // todo: trebuie sa primim prin api?
            $oldUrl = 'https://mediaapi.filostravel.gr/images/hotels/';
            $image->RemoteUrl = $oldUrl . str_replace('\\\\', '', (string) $imageResponse->photo->image);
            $image->Alt = null;
            $items->add($image);
        }

        $cities = $this->apiGetCities();

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        $cityId = (string) $response->City_ID;
        $city = $cities->get($cityId);

        // Content Address
        $address = new HotelAddress();
        $address->City = $city;
        $address->Details = $response->Address1 . $response->Address2;
        $address->Latitude = (string) $response->Latitude;
        $address->Longitude = (string) $response->Longitude;

        // Content ContactPerson
        $contactPerson = null;

        // Content Facilities
        $facilities = new FacilityCollection();
        if (isset($response->HotelAmenities)) {
            foreach ($response->HotelAmenities->Amenities as $facilityResponse) {
                $facility = new Facility();
                $facility->Name = (string) $facilityResponse->HotelAmenity->languages->lang[0];
                $facility->Id = (string) $facilityResponse->attributes()->ID;
                $facilities->add($facility);
            }
        }

        // Content
        $content = new HotelContent();
        $content->Content = (string) $response->ShortDescription->languages->lang[0];
        $content->ImageGallery = $imageGallery;

        $details = new Hotel();
        $details->Id = $response['id'];
        $details->Name = (string) $response->Name;
        $details->Content = $content;
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->WebAddress = null;

        return $details;
    }

    private function getBoards(SimpleXMLElement $ratePlan): array
    {

        $boardTypes = [];

        if (!empty($ratePlan->attributes()->HB)) {
            $boardTypes[] = ['code' => 'HB', 'name' => 'Half Board'];
        } 
        if (!empty($ratePlan->attributes()->RR )) {
            $boardTypes[] = ['code' => 'RR', 'name' => 'Room Rates'];
        } 
        if (!empty($ratePlan->attributes()->BB)) {
            $boardTypes[] = ['code' => 'BB', 'name' => 'Bed and Breakfast'];
        } 
        if (!empty($ratePlan->attributes()->FB)) {
            $boardTypes[] = ['code' => 'FB', 'name' => 'Full Board'];
        } 
        if (!empty($ratePlan->attributes()->AI)) {
            $boardTypes[] = ['code' => 'AI', 'name' => 'All Inclusive'];
        } 
        if (!empty($ratePlan->attributes()->UI)) {
            $boardTypes[] = ['code' => 'UI', 'name' => 'Ultra All Inclusive'];
        }

        return $boardTypes;
    }

    private function getOfferNet(SimpleXMLElement $ratePlan, string $boardCode): string
    {
        $boardCode .= 'Discount';
        return (string) $ratePlan->attributes()->$boardCode;
    }

    private function getOfferInitial(SimpleXMLElement $ratePlan, string $boardCode): string
    {
        return (string) $ratePlan->attributes()->$boardCode;
    }

    private function getCountriesByCountryCodeAsMap(): array
    {
        $countriesResponse = $this->apiGetCountries();

        $countries = [];
        foreach ($countriesResponse as $country) {
            $countries[$country->Id] = $country->Name;
        }
        return $countries;
    }

    private function getCountryCodeByPrefectureIdAsMap(): array
    {
        $requestArr = [
            'PlaceSearchRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'Language' => $this->language,
                'PlaceType' => 'Prefectures'
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);
        $options['body'] = "xml=$xml";

        $response = $this->request($this->apiUrl . '/PlaceSearch', HttpClient::METHOD_POST, $options);
        $rawResponse = $response->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $prefectures =  $responseXml->Response->Prefectures;


        $map = [];
        foreach ($prefectures->Prefecture as $prefecture) {
            $map[(string) $prefecture->attributes()->id] = (string) $prefecture->attributes()->country_id;
        }

        return $map;
    }

    private function getProvinceNameByProvinceIdAsMap(): array
    {
        $requestArr = [
            'PlaceSearchRequest' => [
                'Username' => $this->username,
                'Password' => $this->password,
                'Language' => $this->language,
                'PlaceType' => 'Provinces'
            ]
        ];
        $xml = Utils::arrayToXmlString($requestArr);
        $options['body'] = "xml=$xml";

        $response = $this->request($this->apiUrl . '/PlaceSearch', HttpClient::METHOD_POST, $options);
        $rawResponse = $response->getBody();
        $responseXml = simplexml_load_string($rawResponse);
        $provinces =  $responseXml->Response->Provinces;

        $map = [];
        foreach ($provinces->Province as $province) {
            $map[(string) $province->attributes()->id] = (string) $province->ProvinceName;
        }
        return $map;
    }

    public function request(string $url, string $method = HttpClient::METHOD_GET, array $options = []): ResponseInterface
    {        
        $httpClient = HttpClient::create();
        $response = $httpClient->request($method, $url, $options);
        $this->showRequest($method, $url, $options, $response->getBody(), $response->getStatusCode());

        return $response;
    }
    
}

abstract class FeeType
{

    const NO_SHOW_FEE = 1;
    const EARLY_DEPARTURE_FEE = 2;
    const CANCELLATION_FEE = 3;

}

abstract class Criterion
{

    const STAY = 1;
    const ARRIVAL = 2;

}

abstract class AmountType
{

    const PERCENT_OF_RESERVATION = 1;
    const NIGHTS_OF_STAY = 2;

}