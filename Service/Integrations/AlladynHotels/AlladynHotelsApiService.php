<?php

namespace Integrations\AlladynHotels;

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
use App\Entities\Availability\TransportMerch;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Support\Collections\Collection;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\Response\ResponseInterface;
use DateInterval;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\Validator;

class AlladynHotelsApiService extends AbstractApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetCountries(): CountryCollection
    {
        $url = $this->apiUrl . '/v2/references/countries';
        $responseObj = $this->request($url);

        $response = json_decode($responseObj->getContent(), true);

        if (!empty($response['error'])) {
            throw new Exception(json_encode($response['error']));
        }

        $countries = new CountryCollection();
        foreach ($response as $v) {
            $country = new Country();
            $country->Id = $v['id'];
            $country->Code = AlladynHotelsUtils::getCountryCodeByName($v['name']['en']);
            
            if ($country->Code === '?') {
                continue;
            }
            
            $country->Name = $v['name']['en'];
            $countries->add($country);
        }
        return $countries;
    }

    public function apiGetCities(CitiesFilter $params = null): CityCollection
    {
        $countries = $this->apiGetCountries();

        $cities = new CityCollection();

        foreach ($countries as $countryResponse) {

            $url = $this->apiUrl . '/v2/references/regions?countryId=' . $countryResponse->Id;

            try {
                $responseObj = $this->request($url);

                $citiesFromCountry = json_decode($responseObj->getContent(), true);

                if (!empty($citiesFromCountry['error'])) {
                    throw new Exception(json_encode($citiesFromCountry['error']));
                }

                foreach ($citiesFromCountry as $cityFromCountry) {
                    $city = new City();
                    $city->Id = $cityFromCountry['id'];
                    $city->Name = $cityFromCountry['name']['en'];
                    $city->Country = $countryResponse;

                    $cities->put($city->Id, $city);
                }
            } catch (Exception $ex) {
                // no cities for this country, do nothing
            }
        }
        return $cities;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()->validateHotelDetailsFilter($filter);

        $hotelId = $filter->hotelId;
        $url = $this->apiUrl . '/v2/hotels/info?hotel=' . $hotelId;

        $responseObj = $this->request($url);

        $response = json_decode($responseObj->getContent(), true);

        if (!empty($response['error'])) {
            throw new Exception(json_encode($response['error']));
        }

        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        // Content Address Country
        $country = new Country();
        $country->Id = $response['id'];
        $country->Name = $response['name'];
        $country->Code = 'getCountryCode';

        // Content Address City
        $city = new City();
        $city->Name = $response['region']['name'];
        $city->Id = $response['region']['id'];
        $city->Country = $country;

        // Content Address
        $address = new HotelAddress();
        $address->City = $city;
        $address->Details = $response['address'];
        $address->Latitude = $response['lat'];
        $address->Longitude = $response['lng'];

        // Content ContactPerson
        $contactPerson = null;

        $facilities = new FacilityCollection();

        // Content
        $content = new HotelContent();
        $content->Content = null;
        $content->ImageGallery = $imageGallery;

        $details = new Hotel();
        $details->Name = $response['name'];
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Content = $content;
        $details->WebAddress = null;

        return $details;
    }

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        $response = new AvailabilityCollection();
        return $response;

        Validator::make()->validateAvailabilityFilter($filter);

        $checkIn = $filter->checkIn;
        
        $countryId = $filter->countryId;
        
        $cityId = $filter->cityId;

        $hotelId = $filter->hotelId;

        $adults = (int) $filter->rooms->get(0)->adults;

        $days = $filter->days;
        $checkInDate = new DateTimeImmutable($checkIn);
        $checkOut = $checkInDate->add(new DateInterval('P' . $days . 'D'))->format('Y-m-d');

        $childrenAges = '';
        if (isset($filter->rooms->get(0)->childrenAges) && !empty($filter->rooms->get(0)->childrenAges->get(0))) {
            $childrenAges = '&ages='.implode(',', $filter->rooms->get(0)->childrenAges->toArray());
        }
        
        $urlCurrency = $this->apiUrl . "/v1/references/currencies";

        $currencyResponseObj = $this->request($urlCurrency);
        $responseCurrencyArr = json_decode($currencyResponseObj->getContent(), true);

        if (!empty($responseCurrencyArr['error'])) {
            throw new Exception(json_encode($responseCurrencyArr['error']));
        }
        $responseCurrency = new Collection($responseCurrencyArr);

        $hotel = '';
        if (!empty($hotelId)) {
            $hotel = '&detail=1&hotel=' . $hotelId;
        }

        $url = $this->apiUrl . '/v2/hotels/availability?from_date='.$checkIn.'&to_date='.$checkOut
                .'&adults='.$adults.'&countryId='.$countryId.$childrenAges.'&regions='.$cityId.$hotel;

        $responseHotelsObj = $this->request($url);
        $responseHotels = json_decode($responseHotelsObj->getContent(), true);

        if (!empty($responseHotels['error'])) {
            throw new Exception(json_encode($responseHotels['error']));
        }

        foreach ($responseHotels as $responseHotel) {
            $hotel = new Availability();
            $offers = new OfferCollection();

            if (!empty($hotelId)) {
                $hotel->Id = $hotelId;
                $responseHotel['rooms'] = $responseHotels;
            } else {
                $hotel->Id = $responseHotel['hotel']['id'];
            }

            foreach ($responseHotel['rooms'] as $room) {
                foreach ($room['rates'] as $responseOffer) {
                    $currency = new Currency();
                    $currency->Code = $responseCurrency->filter(fn($currencyEl) => $currencyEl['id'] ==  $responseOffer['id_currency'])->first()['code'];

                    $offerObj = new Offer();

                    $offerCode = $responseOffer['token'];

                    $offerObj->Code = $offerCode;

                    $offerObj->CheckIn = $checkIn;
                    //$offerObj->Nights = (int) $days;
                    $offerObj->Currency = $currency;

                    $taxes = 0;
                    $offerObj->Net = $responseOffer['price'] - $taxes;
                    $offerObj->Gross = $offerObj->Net;
                    $offerObj->InitialPrice = $offerObj->Net;
                    $offerObj->Comission = $taxes;

                    $offerObj->Availability = 'yes';
                    $offerObj->Days = $days;

                    // Rooms
                    $room1 = new Room();
                    $roomId = preg_replace('/\s+/', '', $room['name']);
                    $room1->Id = $roomId;
                    $room1->CheckinBefore = $checkIn;
                    $room1->CheckinAfter = $checkOut;
                    $room1->Currency = $currency;
                    $room1->Quantity = 1;
                    $room1->Availability = 'yes';

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;

                    $room1->Merch = $merch;

                    $offerObj->Rooms = new RoomCollection([$room1]);

                    $offerObj->Item = $room1;

                    $mealItem = new MealItem();

                    $boardTypeName = $responseOffer['mealplan']['name'];

                    // MealItem Merch
                    $boardMerch = new MealMerch();
                    $boardMerch->Title = $boardTypeName;

                    // MealItem
                    $mealItem->Merch = $boardMerch;
                    $mealItem->Currency = $currency;
                    $mealItem->Quantity = 1;
                    $mealItem->UnitPrice = 0;
                    $mealItem->Gross = 0;
                    $mealItem->Net = 0;
                    $mealItem->InitialPrice = 0;

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
                    $departureTransportItemReturn->Quantity = 1;
                    $departureTransportItemReturn->Currency = $currency;
                    $departureTransportItemReturn->UnitPrice = 0;
                    $departureTransportItemReturn->Gross = 0;
                    $departureTransportItemReturn->Net = 0;
                    $departureTransportItemReturn->InitialPrice = 0;
                    $departureTransportItemReturn->DepartureDate = $checkOut;
                    $departureTransportItemReturn->ArrivalDate = $checkOut;

                    // DepartureTransportItem
                    $departureTransportItem = new DepartureTransportItem();
                    $departureTransportItem->Merch = $departureTransportItemMerch;
                    $departureTransportItem->Quantity = 1;
                    $departureTransportItem->Currency = $currency;
                    $departureTransportItem->UnitPrice = 0;
                    $departureTransportItem->Gross = 0;
                    $departureTransportItem->Net = 0;
                    $departureTransportItem->InitialPrice = 0;
                    $departureTransportItem->DepartureDate = $checkIn;
                    $departureTransportItem->ArrivalDate = $checkIn;
                    $departureTransportItem->Return = $departureTransportItemReturn;

                    $offerObj->DepartureTransportItem = $departureTransportItem;

                    $offerObj->ReturnTransportItem = $departureTransportItemReturn;

                    $cancelFees = new OfferCancelFeeCollection();
                    // cancellation fees
                    if (count($responseOffer['cancellationPolicies'])) {
                        $cpObj = new OfferCancelFee();
                        foreach ($responseOffer['cancellationPolicies'] as $policy) {
                            $cpObj->DateStart = $policy['from'];
                            $cpObj->DateEnd = $checkIn;
                            $cpObj->Price = $policy['amount'];
                            $cpObj->Currency = $currency;
                            $cancelFees->add($cpObj);
                        }
                    }
                    $offerObj->CancelFees = $cancelFees;
                    $offers->put($offerObj->Code, $offerObj);
                }
            }

            $hotel->Offers = $offers;

            $response->add($hotel);
            if (!empty($hotelId)) {
                break;
            }
        }

        return $response;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        // prebook
        AlladynHotelsValidator::make()->validateBookHotelFilter($filter);

        $offerCode = $filter->Items->get(0)->Offer_Code;
        
        $url = $this->apiUrl . "/v2/hotels/ratecheck?token=$offerCode";

        $preBookingObj = $this->request($url);

        $preBooking = json_decode($preBookingObj->getContent(), true);

        if (!empty($preBooking['error'])) {
            throw new Exception(json_encode($preBooking['error']));
        }

        // booking
        $tourists = [];
        foreach ($filter->Items->get(0)->Passengers as $passenger) {
            if (!empty($passenger->Firstname)) {
                $tourists[] = [
                    'prefix' => $passenger->Gender == 2 ? 'Mrs' : 'Mr',
                    'name' => $passenger->Firstname,
                    'lastname' => $passenger->Lastname,
                    'birthdate' => $passenger->BirthDate,
                    'isAdult' => $passenger->IsAdult
                ];
            }
        }

        $urlBooking = $this->apiUrl . "/v2/bookings";
        
        $options['body'] = json_encode([
            'tourists' => $tourists,
            'products' => [
                [
                    'token' => $preBooking['token']
                ]
            ],
        ]);

        $bookingObj = $this->request($urlBooking, 'POST', $options);

        $booking = json_decode($bookingObj->getContent(), true);

        if (!empty($booking['error'])) {
            throw new Exception(json_encode($booking['error']));
        }

        // if booking error, comanda nu a putut fi procesata
        if ($booking['status'] != 'confirmed' && $booking['status'] != 'onrequest') {
            throw new Exception('Comanda nu a putut fi procesata!');
        }

        $response = new Booking();
        $response->Id = $booking['id'];
        //$response->rawResp = json_encode($booking);

        return [$response, json_encode($booking)];
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        set_time_limit(1800);
        $countries = $this->apiGetCountries();
        $cities = $this->apiGetCities();
        
        $hotels = new HotelCollection();

        foreach ($countries as $countryResponse) {
        
            $url = $this->apiUrl . '/v2/references/hotels?countryId=' . $countryResponse->Id;

            $responseObj = $this->request($url);
            
            $response = json_decode($responseObj->getContent(), true);
            
            if (!empty($response['error']['message'])) {
                if ($response['error']['message'] === 'No results were found') {
                    continue;
                } else {
                    throw new Exception(json_encode($response['error']['message']));
                }
            }

            foreach ($response as $hotelResponse) {

                // $address->Country
                $country = new Country();
                $country->Id = $countryResponse->Id;
                $country->Code = $countryResponse->Code;
                $country->Name = $countryResponse->Name;

                // $address->City
                $city = new City();
                $city->Id = $hotelResponse['id_region'];

                $cityResponse = $cities->get($hotelResponse['id_region']);
                if ($cityResponse === null) {
                    continue;
                }
                
                $city->Name = $cityResponse->Name;
                $city->Country = $country;

                // $hotel->Address
                $address = new HotelAddress();
                $address->Latitude = $hotelResponse['lat'];
                $address->Longitude = $hotelResponse['lng'];
                $address->Details = null;
                $address->City = $city;

                // $hotel->Content->ImageGallery
                $imageGallery = new HotelImageGallery();
                $imageGallery->Items = new HotelImageGalleryItemCollection([]);

                // $hotel->Content
                $content = new HotelContent();
                $content->ImageGallery = $imageGallery;
                $content->Content = null;

                $hotel = new Hotel();
                $hotel->Id = $hotelResponse['id'];
                $hotel->Name = $hotelResponse['name']['en'];
                $hotel->Stars = $hotelResponse['stars']['internalId'];
                $hotel->Content = $content;
                $hotel->Address = $address;
                $hotel->WebAddress = null;

                $hotels->add($hotel);
            }
        }
        return $hotels;
    }


    public function request(string $url, string $method = 'GET', array $options = []): ResponseInterface
    {
        $options['headers'] = [
            'Content-Type: application/json',
            'Api-Key: ' . $this->password,
        ];

        $httpClient = HttpClient::create();
        
        $response = $httpClient->request($method, $url, $options);
        // if ($this->request->getPostParam('get-raw-data')) {
        //     $this->responses->add($response);
        // }
        return $response;
    }
}
