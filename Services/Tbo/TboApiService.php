<?php

namespace Integrations\Tbo;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Filters\PaymentPlansFilter;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Log;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use Utils\Utils;

class TboApiService extends AbstractApiService
{

    const TBO_TEST = 'locahost-tbo';

    public function apiGetCountries(): array
    {
        $countries = [];

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password)
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/CountryList', $options);
        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/CountryList', $options, $resp->getBody(), $resp->getStatusCode());

        $countriesResp = json_decode($resp->getBody(), true)['CountryList'];
        foreach ($countriesResp as $countryResp) {
            $country = Country::create($countryResp['Code'], $countryResp['Code'], $countryResp['Name']);
            $countries->add($country);
        }

        return $countries;
    }

    public function apiGetCities(?CitiesFilter $filter = null): array
    {
        $file = 'cities';

        $json = Utils::getFromCache($this->handle, $file);

        if ($json === null || $this->skipTopCache || $this->renewTopCache) {

            $cities = [];

            $client = HttpClient::create();

            $options['headers'] = [
                'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'application/json'
            ];

            $countries = $this->apiGetCountries();

            foreach ($countries as $country) {
                $options['body'] = json_encode(['CountryCode' => $country->Code]);

                $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/CityList', $options);

                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/CityList', $options, $resp->getBody(), $resp->getStatusCode());

                $citiesResp = json_decode($resp->getBody(), true)['CityList'];
        
                foreach ($citiesResp as $cityResp) {
                    $city = City::create($cityResp['Code'], $cityResp['Name'], $country);
                    $cities->put($city->Id, $city);
                }
            }
            if (!$this->skipTopCache) {
                Utils::writeToCache($this->handle, $file, json_encode($cities));
            }
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }

        return $cities;
    }

    // todo: de facut lista la fel ca la karpaten
    // 
    public function apiGetHotels(?HotelsFilter $filter = null): []
    {
        if (empty($filter->CityId)) {
            throw new Exception('CityId is required');
        }

        $file = 'city-' . $filter->CityId . '-hotels';

        $json = Utils::getFromCache($this, $file);
        $hotels = [];

        if ($json === null) {
            $cities = $this->apiGetCities();

            $client = HttpClient::create();
    
            $options['headers'] = [
                'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'application/json'
            ];
    
            $options['body'] = json_encode(['CityCode' => $filter->CityId, 'IsDetailedResponse' => true]);
    
            $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/TBOHotelCodeList', $options);
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/TBOHotelCodeList', $options, $resp->getBody(), $resp->getStatusCode());
    
            $hotelRespJson = json_decode($resp->getBody(), true);
    
            if (!empty($hotelRespJson['Hotels'])) {

                $hotelsResp = $hotelRespJson['Hotels'];
                
                foreach ($hotelsResp as $hotelResp) {
        
                    $city = $cities->get($filter->CityId);
                    $addressDetails = $hotelResp['Address'];
                    $description = $hotelResp['Description'];
        
                    $map = explode('|', $hotelResp['Map']);
        
                    $latitude = $map[0] ?? null;
                    $longitude = $map[1] ?? null;
        
                    $facilities = new FacilityCollection();
                    foreach ($hotelResp['HotelFacilities'] as $facilityResp) {
                        $facility = Facility::create(md5($facilityResp), $facilityResp);
                        $facilities->add($facility);
                    }
        
                    $hotel = Hotel::create(
                        $hotelResp['HotelCode'], 
                        $hotelResp['HotelName'], 
                        $city, 
                        null, 
                        $description, 
                        $addressDetails, 
                        $latitude, 
                        $longitude, 
                        $facilities, 
                        null, 
                        $hotelResp['PhoneNumber'], 
                        null, 
                        $hotelResp['FaxNumber'], 
                        null
                    );
        
                    $hotels->add($hotel);
                }
            }
            Utils::writeToCache($this, $file, json_encode($hotels));
        } else {
            $hotels = ResponseConverter::convertToCollection(json_decode($json, true), []::class);
        }

        return $hotels;
    }

    public function cacheTopData(string $operation, array $config = [], array $filters = []): array
    {
        $result = [];
        switch ($operation) {
            case 'Hotels_Details':
                $client = HttpClient::create();
                $cities = $this->apiGetCities();

                $hotelCodesStr = '';
                foreach ($filters['Hotels'] as $hotelFilter) {
                    $hotelCodesStr .= $hotelFilter['InTourOperatorId'] . ',';  
                }

                $hotelCodesStr = rtrim($hotelCodesStr, ',');

                $options['headers'] = [
                    'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
                    'Content-Type' => 'application/json'
                ];

                $options['body'] = json_encode(['Hotelcodes' => $hotelCodesStr]);

                $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/HotelDetails', $options);
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/HotelDetails', $options, $resp->getBody(), $resp->getStatusCode());

                $respArr = json_decode($resp->getBody(), true);
        
                if (!isset($respArr['HotelDetails'])) {
                    Log::warning($this->handle .': no details for ' . json_encode($filters['Hotels']));
                    return $result;
                }
                
                $hotelsResp = $respArr['HotelDetails'];
                foreach ($hotelsResp as $hotelResp) {
                    $city = $cities->get($hotelResp['CityId']);
                    $addressDetails = $hotelResp['Address'];
                    $description = $hotelResp['Description'];

                    $map = explode('|', $hotelResp['Map']);

                    $latitude = $map[0];
                    $longitude = $map[1];

                    $facilities = new FacilityCollection();
                    foreach ($hotelResp['HotelFacilities'] ?? [] as $facilityResp) {
                        $facility = Facility::create(md5($facilityResp), $facilityResp);
                        $facilities->add($facility);
                    }

                    $images = new HotelImageGalleryItemCollection();
                    foreach ($hotelResp['Images'] ?? [] as $img) {
                        $image = HotelImageGalleryItem::create($img);
                        $images->add($image);
                    }

                    $hotel = Hotel::create($hotelResp['HotelCode'], $hotelResp['HotelName'], $city, $hotelResp['HotelRating'], $description, $addressDetails, $latitude, 
                        $longitude, $facilities, $images, $hotelResp['PhoneNumber'] ?? null, null, $hotelResp['FaxNumber'] ?? null, null);

                    $result[] = $hotel;
                }
                break;
            case 'Hotels':
                foreach ($filters['Cities'] as $hotelFilter) {

                    if (!empty($hotelFilter['InTourOperatorId'])) {
                        $hotelsFilter = new HotelsFilter(['CityId' => $hotelFilter['InTourOperatorId']]);
                        $hotels = $this->apiGetHotels($hotelsFilter)->toArray();
                        $result += $hotels;
                    }
                }
                break;

            default:
                throw new Exception('Operation not found!');
        }
        return $result;
    }
    
    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        $hotel = new Hotel();

        $cities = $this->apiGetCities();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];

        $options['body'] = json_encode(['Hotelcodes' => $filter->hotelId]);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/HotelDetails', $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/HotelDetails', $options, $resp->getBody(), $resp->getStatusCode());

        $respArr = json_decode($resp->getBody(), true);

        if (!isset($respArr['HotelDetails'])) {
            Log::warning($this->handle .': no details for ' . json_encode($filter));
            return $hotel;
        }
        
        $hotelResp = $respArr['HotelDetails'][0];

        $city = $cities->get($hotelResp['CityId']);
        $addressDetails = $hotelResp['Address'];
        $description = $hotelResp['Description'];

        $map = explode('|', $hotelResp['Map']);

        $latitude = $map[0];
        $longitude = $map[1];

        $facilities = new FacilityCollection();
        foreach ($hotelResp['HotelFacilities'] ?? [] as $facilityResp) {
            $facility = Facility::create(md5($facilityResp), $facilityResp);
            $facilities->add($facility);
        }

        $images = new HotelImageGalleryItemCollection();
        foreach ($hotelResp['Images'] ?? [] as $img) {
            $image = HotelImageGalleryItem::create($img);
            $images->add($image);
        }

        $hotel = Hotel::create($hotelResp['HotelCode'], $hotelResp['HotelName'], $city, $hotelResp['HotelRating'], $description, $addressDetails, $latitude, 
            $longitude, $facilities, $images, $hotelResp['PhoneNumber'] ?? null, null, $hotelResp['FaxNumber'] ?? null, null);

        return $hotel;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateIndividualOffersFilter($filter);
        
        $availabilities = [];

        if ($filter->rooms->first()->adults > 6) {
            return $availabilities;
        }
        if (!empty($post['args'][0]['rooms'][0]['children'])) {
            if ($post['args'][0]['rooms'][0]['children'] > 4) {
                return $availabilities;
            }
        }

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];

        $hotels = null;
        
        $body = [
            'CheckIn' => $filter->checkIn,
            'CheckOut' => $filter->checkOut,
            'GuestNationality' => 'RO',
            'IsDetailedResponse' => true,
            'PaxRooms' => [
                [
                    'Adults' => $filter->rooms->first()->adults
                ]
            ]
        ];

        if (!empty($post['args'][0]['rooms'][0]['children'])) {
            $body['PaxRooms'][0]['Children'] = $post['args'][0]['rooms'][0]['children'];
            $body['PaxRooms'][0]['ChildrenAges'] = $post['args'][0]['rooms'][0]['childrenAges']->toArray();
        }

        $responses = [];

        if (empty($filter->hotelId)) {
            $hotels = $this->apiGetHotels(new HotelsFilter(['CityId' => $filter->cityId]));
            
            $i = 1;

            $hotelCodesStr = '';
            foreach ($hotels as $hotel) {
                $hotelCodesStr .= $hotel->Id . ',';

                if ($i % 100 === 0 || $i === count($hotels)) { // every 100 or finish
                    $hotelCodesStr = rtrim($hotelCodesStr, ',');

                    $body['ResponseTime'] = 23;
                    $body['HotelCodes'] = $hotelCodesStr;

                    $options['body'] = json_encode($body);

                    $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Search', $options);
                    $responses[] = [$resp, $options];
                    $hotelCodesStr = '';
                }
                $i++;
            }
        } else {
            $body['ResponseTime'] = 5;
            $body['HotelCodes'] = $filter->hotelId;
            $options['body'] = json_encode($body);

            $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Search', $options);
            $responses[] = [$resp, $options];
        }
        
        foreach($responses as $response) {
            $resp = $response[0];
            $options = $response[1];
        
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Search', $options, $resp->getBody(), $resp->getStatusCode());
            $c = json_decode($resp->getBody(), true);
            if (empty($c['HotelResult'])) {
                continue;
            }
            $offersResp = $c['HotelResult'];
            
            foreach ($offersResp as $hotel) {
                $availability = new Availability();
                $availability->Id = $hotel['HotelCode'];

                $currency = $hotel['Currency'];

                $offers = new OfferCollection();

                foreach ($hotel['Rooms'] as $offerResp) {
                    $roomName = '';
                    foreach ($offerResp['Name'] as $rn) {
                        $roomName .= $rn . ' ';
                    }
                    trim($roomName);

                    $roomCode = Utils::stripSpaces($roomName);

                    $mealName = $offerResp['MealType'];

                    $mealId = Utils::stripSpaces($mealName);

                    $offerCheckInDT = new DateTime($filter->checkIn);
                    $offerCheckOutDT = new DateTime($filter->checkOut);

                    $adults = $filter->rooms->first()->adults;

                    $childrenAges = $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : null;

                    $priceNet = $offerResp['TotalFare'];
                    
                    //$offerResp['CancelPolicies'] = array_reverse($offerResp['CancelPolicies']);
                    $bookingDataArr = [
                        'BookingCode' => $offerResp['BookingCode']
                        //'CancelPolicies' => $offerResp['CancelPolicies']
                    ];

                    $bookingDataArr['RecommendedSellingRate'] = $offerResp['RecommendedSellingRate'] ?? null;

                    $bookingDataJson = json_encode($bookingDataArr);

                    $roomInfo = null;
                    $hasSupplements = false;

                    // Suplimente de achitat la receptie: $Description - $Price $Currency, $Description - $Price $Currency
                    if (!empty($offerResp['Supplements'])) {
                        $text = 'Suplimente de achitat la receptie: ';
                        foreach ($offerResp['Supplements'] as $set) {
                            foreach ($set as $supplement) {
                                if ($supplement['Type'] === 'AtProperty') {
                                    $hasSupplements = true;
                                    $text .= $supplement['Description'] . ' - ' . $supplement['Price'] . ' ' . $supplement['Currency'] . ',';
                                }
                            }
                        }
                        $text = rtrim($text, ',');
                    }
                    if ($hasSupplements) {
                        $roomInfo = $text . '. ';
                    }

                    if (!empty($offerResp['Inclusion'])) {
                        $roomInfo .= "Inclus: ". $offerResp['Inclusion'];
                    }
                    $roomCode = md5($roomCode . '-' . $roomInfo);

                    $exclamationMark = null;
                    if (!empty($offerResp['RoomPromotion'])) {
                        foreach ($offerResp['RoomPromotion'] as $promotion) {
                            $exclamationMark .= $promotion . ',';
                        }
                        $exclamationMark = rtrim($exclamationMark, ',');
                    }
                    
                    $offer = Offer::createIndividualOffer(
                        $availability->Id, 
                        $roomCode, 
                        $roomCode, 
                        $roomName, 
                        $mealId,
                        $mealName, 
                        $offerCheckInDT, 
                        $offerCheckOutDT, 
                        $adults, 
                        $childrenAges, 
                        $currency, 
                        $priceNet,
                        $priceNet,
                        $priceNet, 
                        0, 
                        Offer::AVAILABILITY_YES, 
                        $roomInfo, 
                        $exclamationMark, 
                        $bookingDataJson
                    );
                    
                    // $cancellationFees = new OfferCancelFeeCollection();

                    // //dump($offerResp);

                    // $i = -1;
                    // foreach ($offerResp['CancelPolicies'] as $cancelPolResp) {
                    //     $i++;

                    //     $type = $cancelPolResp['ChargeType'];

                    //     $amount = $cancelPolResp['CancellationCharge'];
                    //     $cancelPrice = 0;

                    //     if ($type === 'Percentage') {
                    //         $cancelPrice = $priceNet * ( $amount/100);
                    //     } elseif ($type === 'Fixed') {
                    //         $cancelPrice = $amount;
                    //     } else {
                    //         Log::warning($this->handle . ' :unknown type for ' . json_encode($filter));
                    //         continue;
                    //     }
                    //     if ($cancelPrice == 0) {
                    //         continue;
                    //     }

                    //     $cancellationFee = new OfferCancelFee();
                    //     $cancellationFee->DateStart = (new DateTime($cancelPolResp['FromDate']))->format('Y-m-d');
                        
                    //     // next date - 1 if exists or checkin
                    //     $endDate = $offerCheckInDT->format('Y-m-d');

                    //     if (isset($offerResp['CancelPolicies'][$i + 1])) {

                    //         $endDate = (new DateTime($offerResp['CancelPolicies'][$i + 1]['FromDate']))->modify('-1 day')->format('Y-m-d');
                    //     }

                    //     $cancellationFee->DateEnd = $endDate;
                    //     $cancellationFee->Price = $cancelPrice;
                    //     $currencyObj = new Currency();
                    //     $currencyObj->Code = $currency;
                    //     $cancellationFee->Currency = $currencyObj;

                    //     $cancellationFees->add($cancellationFee);
                    // }
                    //$offer->CancelFees = $cancellationFees;
                    //$offer->Payments = $this->convertIntoPayment($cancellationFees);
                    
                    $offers->add($offer);
                }
                $availability->Offers = $offers;
                $availabilities->add($availability);
            }
        }
        return $availabilities;
    }

    public function getOfferPaymentPlans(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    {
        $filter = new CancellationFeeFilter($filter->toArray());
        return $this->convertIntoPayment($this->apiGetOfferCancelFees($filter));
    }

    public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    {
        TboValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateOfferPaymentPlansFilter($filter);

        $bookingArr = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);
        $bookingCode = $bookingArr['BookingCode'];

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];

        $body = [
            'BookingCode' => $bookingCode,
            'PaymentMode' => 'Limit'
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options, $resp->getBody(), $resp->getStatusCode());
        
        $preBook = json_decode($resp->getBody(), true)['HotelResult'][0];

        $cpFromApi = $preBook['Rooms'][0]['CancelPolicies'];

        //dd($preBook);

        $cancellationFees = new OfferCancelFeeCollection();

        $currencyObj = new Currency();
        $currencyObj->Code = $preBook['Currency'];

        $i = -1;
        foreach ($cpFromApi as $cancelPolResp) {
            $i++;

            $type = $cancelPolResp['ChargeType'];

            $amount = $cancelPolResp['CancellationCharge'];
            $cancelPrice = 0;

            if ($type === 'Percentage') {
                $cancelPrice = $preBook['Rooms'][0]['TotalFare'] * ( $amount/100);
            } elseif ($type === 'Fixed') {
                $cancelPrice = $amount;
            } else {
                Log::warning($this->handle . ' :unknown type for ' . json_encode($filter));
                continue;
            }
            if ($cancelPrice == 0) {
                continue;
            }

            $cancellationFee = new OfferCancelFee();
            $cancellationFee->DateStart = (new DateTime($cancelPolResp['FromDate']))->format('Y-m-d');
            
            // todo: next date - 1 if exists or checkin
            $endDate = $post['args'][0]['CheckIn'];

            if (isset($offerResp['CancelPolicies'][$i + 1])) {

                $endDate = (new DateTime($offerResp['CancelPolicies'][$i + 1]['FromDate']))->modify('-1 day')->format('Y-m-d');
            } else {
                $endDate = (new DateTime($post['args'][0]['CheckIn']))->format('Y-m-d');
            }

            $cancellationFee->DateEnd = $endDate;
            $cancellationFee->Price = $cancelPrice;

            $cancellationFee->Currency = $currencyObj;

            $cancellationFees->add($cancellationFee);
        }

        $offPayments = $this->convertIntoPayment($cancellationFees);

        $offPrice = $preBook['Rooms'][0]['TotalFare'];
        $offInitialPrice = $offPrice;

        $notes = [];
        $rateConditions = '';

        foreach ($preBook['RateConditions'] ?? [] as $rate) {
            $rateConditions .= $rate . '&lt;br&gt;';
        }
        $rateConditions = rtrim($rateConditions, '&lt;br&gt;');

        $amenities = '';
        foreach ($preBook['Rooms'][0]['Amenities'] ?? [] as $amenity) {
            $amenities .= $amenity . '&lt;br&gt;';
        }
        $amenities = rtrim($amenities, '&lt;br&gt;');

        if ($rateConditions !== '') {
            $notes[] = $rateConditions;
        }

        if ($amenities !== '') {
            $notes[] = $amenities;
        }


        return [$cancellationFees, $offPayments, Offer::AVAILABILITY_YES, $offPrice, $offInitialPrice, $currencyObj->Code, null, $notes];
    }

    // todo: testat cu politici care se schimba
    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection 
    {
        TboValidator::make()->validateOfferCancelFeesFilter($filter);

        $bookingArr = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);
        $bookingCode = $bookingArr['BookingCode'];

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];

        $body = [
            'BookingCode' => $bookingCode,
            'PaymentMode' => 'Limit'
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options, $resp->getBody(), $resp->getStatusCode());
        
        $preBook = json_decode($resp->getBody(), true)['HotelResult'][0];

        $cpFromApi = $preBook['Rooms'][0]['CancelPolicies'];

        $cancellationFees = new OfferCancelFeeCollection();

        $i = -1;
        foreach ($cpFromApi as $cancelPolResp) {
            $i++;

            $type = $cancelPolResp['ChargeType'];

            $amount = $cancelPolResp['CancellationCharge'];
            $cancelPrice = 0;

            if ($type === 'Percentage') {
                $cancelPrice = $preBook['Rooms'][0]['TotalFare'] * ( $amount/100);
            } elseif ($type === 'Fixed') {
                $cancelPrice = $amount;
            } else {
                Log::warning($this->handle . ' :unknown type for ' . json_encode($filter));
                continue;
            }
            if ($cancelPrice == 0) {
                continue;
            }

            $cancellationFee = new OfferCancelFee();
            $cancellationFee->DateStart = (new DateTime($cancelPolResp['FromDate']))->format('Y-m-d');
            
            // todo: next date - 1 if exists or checkin
            $endDate = $post['args'][0]['CheckIn'];

            if (isset($offerResp['CancelPolicies'][$i + 1])) {

                $endDate = (new DateTime($offerResp['CancelPolicies'][$i + 1]['FromDate']))->modify('-1 day')->format('Y-m-d');
            }

            $cancellationFee->DateEnd = $endDate;
            $cancellationFee->Price = $cancelPrice;
            $currencyObj = new Currency();
            $currencyObj->Code = $preBook['Currency'];
            $cancellationFee->Currency = $currencyObj;

            $cancellationFees->add($cancellationFee);
        }
        return $cancellationFees;
                    
    }

    private function convertIntoPayment(OfferCancelFeeCollection $offerCancelFeeCollection): OfferPaymentPolicyCollection
    {
        $paymentsList = new OfferPaymentPolicyCollection();

        $i = 0;
        $prices = 0;

        /** @var OfferCancelFee $cancelFee */
        foreach ($offerCancelFeeCollection as $cancelFee) {
            $payment = new OfferPaymentPolicy();
            $payment->Currency = $cancelFee->Currency;

            if ($i === 0) {
                $payment->PayAfter = date('Y-m-d');
            } else {
                $payment->PayAfter = $offerCancelFeeCollection->get($i - 1)->DateEnd;
            }
            $payment->Amount = $cancelFee->Price - $prices;

            $prices += $payment->Amount;

            $payment->PayUntil = $cancelFee->DateStart;

            $paymentsList->add($payment);
            $i++;
        }

        return $paymentsList;
    }

    /*
    // [$offFees, $offPayments, $offAvailability, $offPrice, $offInitialPrice, $offCurrency]
    public function getOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    {
        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];

        $client = HttpClient::create();

        $body = [
            'BookingCode' => json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true)['BookingCode']
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options, $resp->getBody(), $resp->getStatusCode());
        
        $preBook = json_decode($resp->getBody(), true)['HotelResult'][0];

        $price = $preBook['Rooms'][0]['TotalFare'];

        $cancellationFees = new OfferCancelFeeCollection();

        foreach ($preBook['Rooms'][0]['CancelPolicies'] as $cancelPolResp) {

            $type = $cancelPolResp['ChargeType'];

            $amount = $cancelPolResp['CancellationCharge'];
            $cancelPrice = 0;

            if ($type === 'Percentage') {
                $cancelPrice = $price * ( $amount/100);
            } elseif ($type === 'Fixed') {
                $cancelPrice = $amount;
            } else {
                Log::warning($this->handle . ' :unknown type for ' . json_encode($filter));
                continue;
            }

            $cancellationFee = new OfferCancelFee();
            $cancellationFee->DateStart = $cancelPolResp['FromDate'];
            $cancellationFee->DateEnd = date('Y-m-d');
            $cancellationFee->Price = $cancelPrice;
            $currencyObj = new Currency();
            $currencyObj->Code = $preBook['Currency'];
            $cancellationFee->Currency = $currencyObj;

            $cancellationFees->add($cancellationFee);
        }

        return [$cancellationFees, null, null, $price, $price, $preBook['Currency']];
    }
    */

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        TboValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];

        $client = HttpClient::create();

        $bookingArr = json_decode($post['args'][0]['Items'][0]['Offer_bookingDataJson'], true);
        $bookingCode = $bookingArr['BookingCode'];

        $body = [
            'BookingCode' => $bookingCode,
            'PaymentMode' => 'Limit'
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/PreBook', $options, $resp->getBody(), $resp->getStatusCode());
        
        $preBook = json_decode($resp->getBody(), true)['HotelResult'][0];

        $prebookPrice = $preBook['Rooms'][0]['TotalFare'];

        $booking = new Booking();
        //$originalOfferPrice = $filter->Items->first()->Offer_Gross;

        // compare prices
        // if ($originalOfferPrice != $prebookPrice) {
        //     return [$booking, 'Prices do not match!Offer price: '.$originalOfferPrice.', Prebook response: ' . $resp->getBody()];
        // }
        //dump(json_encode($preBook['Rooms'][0]['CancelPolicies']));

        // compare cancel policies
        // if (json_encode($bookingArr['CancelPolicies']) !== json_encode($preBook['Rooms'][0]['CancelPolicies'])) {
        //     return [$booking, 'Cancellation policies do not match! Offer cp:'. json_encode($bookingArr['CancelPolicies']) .', Prebook response: ' . $resp->getBody()];
        // }

        $names = [];
        /** @var Passenger $passenger */
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $passenger) {

            if (!ctype_alpha($passenger['Firstname'])) {
                return [$booking, 'Firstname '.$passenger['Firstname'].' has special characters!'];
            }
            if (!ctype_alpha($passenger['Lastname'])) {
                return [$booking, 'Lastname '.$passenger['Lastname'].' has special characters!'];
            }
            if (strlen($passenger['Firstname']) < 3 || strlen($passenger['Firstname']) > 25) {
                return [$booking, 'Firstname '.$passenger['Firstname'].' has less than 3 or more than 25 characters!'];
            }
            if (strlen($passenger['Lastname']) < 3 || strlen($passenger['Lastname']) > 25) {
                return [$booking, 'Lastname '.$passenger['Lastname'].' has less than 3 or more than 25 characters!'];
            }
            $names[] = [
                'Title' => $passenger['Gender'] ? ($passenger['Gender'] === 'male' ? 'Mr' : 'Ms') : 'Mr',
                'FirstName' => $passenger['Firstname'],
                'LastName' => $passenger['Lastname'],
                'Type' => ucfirst($passenger->Type)
            ];
        }

        $referenceId = uniqid();
        $body = [
            'BookingCode' => $bookingCode,
            'CustomerDetails' => [[
                'CustomerNames' => $names,

            ]],
            'PaymentMode' => 'Limit',
            'ClientReferenceId' => uniqid(),
            'BookingReferenceId' => $referenceId,
            'TotalFare' => $prebookPrice,
            'EmailId' => $filter->BillingTo->Email,
            'PhoneNumber' => $filter->BillingTo->Phone
        ];
        
        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/Book', $options);

        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/Book', $options, $resp->getBody(), $resp->getStatusCode());

        $bookingResponseArr = json_decode($resp->getBody(), true);

        if (!isset($bookingResponseArr['ConfirmationNumber'])) {
            // get booking details

            //todo: cate secunde decidem sa fie?
            if (!($this->handle === self::TBO_TEST)) {
                sleep(120);
            }

            $body = [
                'BookingReferenceId' => $referenceId,
            ];
            
            $options['body'] = json_encode($body);
    
            $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/BookingDetail', $options);
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/BookingDetail', $options, $resp->getBody(), $resp->getStatusCode());

            $bookingDetailResponseArr = json_decode($resp->getBody(), true);

            $booking->Id = $bookingDetailResponseArr['BookingDetail']['ConfirmationNumber'];
        } else {
            $booking->Id = $bookingResponseArr['ConfirmationNumber'];
        }

        return [$booking, $resp->getBody()];
    }
}