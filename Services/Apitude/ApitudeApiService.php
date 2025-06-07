<?php

namespace Integrations\Apitude;

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
use App\Entities\Hotels\ContactPerson;
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
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\BookingCollection;
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

class ApitudeApiService extends AbstractApiService
{

    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetCountries(): array
    {
        $url = $this->apiUrl . '/hotel-content-api/1.0/locations/countries?language=RUM&from=1&to=1000';
        $responseObj = $this->request($url);

        $response = json_decode($responseObj->getBody(), true)['countries'];

        $countries = [];
        foreach ($response as $value) {
            $country = new Country();
            $country->Id = $value['isoCode'];
            $country->Code = $value['code'];
            $country->Name = $value['description']['content'];
            $countries->add($country);
        }
        return $countries;
    }

    public function apiGetCities(CitiesFilter $params = null): array
    {
        $cities = [];

        $countries = $this->apiGetCountries();

        for ($from = 1; $from <= 6999; $from = $from + 999) {
            $to = $from + 999;
            $url = $this->apiUrl . '/hotel-content-api/1.0/locations/destinations?language=RUM&from=' . $from . '&to=' . $to;
            $from++;

            $response = json_decode($this->request($url)->getBody(), true)['destinations'];

            foreach ($response as $regionResponse) {

                if (array_key_exists('name', $regionResponse)) {
                    $region = new Region();

                    $country = $countries->first(fn(Country $country) => $country->Code === $regionResponse['countryCode']);

                    $region->Id = $regionResponse['code'];
                    $region->Name = $regionResponse['name']['content'];
                    $region->Country = $country;
                    foreach ($regionResponse['zones'] as $cityResponse) {
                        $city = new City();
                        $city->Id = $region->Id . '-' . $cityResponse['zoneCode'];
                        $city->Name = $cityResponse['name'];
                        $city->County = $region;
                        $city->Country = $country;
                        $cities->add($city);
                    }
                }
            }
        }
        return $cities;
    }

    public function apiGetRegions(): []
    {
        $countries = $this->apiGetCountries();

        $regions = [];

        for ($from = 1; $from <= 6999; $from = $from + 999) {
            $to = $from + 999;
            $url = $this->apiUrl . '/hotel-content-api/1.0/locations/destinations?language=RUM&from=' . $from . '&to=' . $to;
            $from++;

            $responseObj = $this->request($url);

            $response = json_decode($responseObj->getBody(), true)['destinations'];

            foreach ($response as $value) {

                if (array_key_exists('name', $value)) {
                    $region = new Region();

                    $country = new Country();
                    $country->Code = $value['countryCode'];
                    $country->Id = $value['isoCode'];
                    $country->Name = $countries->first(fn(Country $country) => $country->Code === $value['countryCode'])->Name;

                    $region->Id = $value['code'];
                    $region->Name = $value['name']['content'];
                    $region->Country = $country;
                    $regions->add($region);
                }
            }
        }
        return $regions;
    }

    public function apiGetHotels(): []
    {
        $countries = $this->apiGetCountries();
        $regions = $this->apiGetRegions();

        $hotels = [];

        $url = $this->apiUrl . '/hotel-content-api/1.0/hotels?language=RUM&from=1&to=1000&fields=countryCode,destinationCode,zoneCode,city,coordinates,address,images,description,code,name,S2C,web';

        $response = json_decode($this->request($url)->getBody(), true)['hotels'];

        foreach ($response as $hotelResponse) {

            $countryResponse = $countries->first(fn(Country $countryResponse) => $hotelResponse['countryCode'] === $countryResponse->Id);

            // $address->Country
            $country = new Country();
            $country->Id = $hotelResponse['countryCode'];
            $country->Code = $countryResponse->Code;
            $country->Name = $countryResponse->Name;

            // $address->City->County
            $county = new Region();
            $county->Id = $hotelResponse['destinationCode'];
            $county->Name = $regions->first(fn(Region $regionResponse) => $regionResponse->Id === $county->Id)->Name;
            $county->Country = $country;

            // $address->City
            $city = new City();
            $city->Id = $hotelResponse['destinationCode'] . '-' . $hotelResponse['zoneCode'];
            $city->Name = $hotelResponse['city']['content'];
            $city->Country = $country;
            $city->County = $county;

            // $hotel->Address
            $address = new HotelAddress();
            $address->Latitude = $hotelResponse['coordinates']['latitude'];
            $address->Longitude = $hotelResponse['coordinates']['longitude'];
            $address->Details = $hotelResponse['address']['content'];
            $address->City = $city;

            $images = new HotelImageGalleryItemCollection();

            foreach ($hotelResponse['images'] as $imageResponse) {
                $image = new HotelImageGalleryItem();
                $image->RemoteUrl = $imageResponse['path'];
                $image->Alt = 'Hotel image';
                $images->add($image);
            }

            // $hotel->Content->ImageGallery
            $imageGallery = new HotelImageGallery();
            $imageGallery->Items = $images;

            // $hotel->Content
            $content = new HotelContent();
            $content->ImageGallery = $imageGallery;
            $content->Content = $hotelResponse['description']['content'];

            $hotel = new Hotel();
            $hotel->Id = $hotelResponse['code'];
            $hotel->Name = $hotelResponse['name']['content'];
            $hotel->Stars = (int) $hotelResponse['S2C'];
            $hotel->Content = $content;
            $hotel->Address = $address;
            $hotel->WebAddress = $hotelResponse['web'];

            $hotels->add($hotel);
        }
        return $hotels;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()->validateHotelDetailsFilter($filter);

        $hotelId = $filter->hotelId;
        $url = $this->apiUrl . '/hotel-content-api/1.0/hotels/' . $hotelId . '/details?language=RUM&from=1&to=1000&fields=countryCode,destinationCode,zoneCode,city,coordinates,address,images,description,code,name,S2C,web';

        $response = json_decode($this->request($url)->getBody(), true)['hotel'];

        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();

        foreach ($response['images'] as $imageResponse) {
            $image = new HotelImageGalleryItem();
            $image->RemoteUrl = $imageResponse['path'];
            $image->Alt = 'Hotel image';
            $items->add($image);
        }

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        // Content Address Country
        $country = new Country();
        $country->Id = $response['country']['code'];
        $country->Name = $response['country']['description']['content'];
        $country->Code = $response['country']['isoCode'];

        // Content Address County
        $county = new Region();
        $county->Id = $response['destination']['code'];
        $county->Name = $response['destination']['name']['content'];
        $county->Country = $country;

        // Content Address City
        $city = new City();
        $city->Name = $response['zone']['description']['content'];
        $city->Id = $county->Id . '-' . $response['zone']['zoneCode'];
        $city->Country = $country;
        $city->County = $county;

        // Content Address
        $address = new HotelAddress();
        $address->City = $city;
        $address->Details = $response['address']['content'];
        $address->Latitude = $response['coordinates']['latitude'];
        $address->Longitude = $response['coordinates']['longitude'];

        // Content ContactPerson
        $contactPerson = new ContactPerson();
        $contactPerson->Email = $response['web'];
        $contactPerson->Fax = null;
        $contactPerson->Phone = $response['phones'][0]['phoneNumber'];

        $facilities = new FacilityCollection();
        foreach ($response['facilities'] as $facilityResponse) {
            $facility = new Facility();
            $facility->Id = $facilityResponse['facilityCode'];
            $facility->Name = $facilityResponse['description']['content'];
            $facilities->add($facility);
        }

        // Content
        $content = new HotelContent();
        $content->Content = $response['description']['content'];
        $content->ImageGallery = $imageGallery;

        $details = new Hotel();
        $details->Name = $response['name']['content'];
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Content = $content;
        $details->WebAddress = $response['web'];

        return $details;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        Validator::make()->validateAvailabilityFilter($filter);

        $checkIn = $filter->checkIn;
        $days = $filter->days;
        $checkInDate = new DateTimeImmutable($checkIn);
        $checkOut = $checkInDate->add(new DateInterval('P' . $days . 'D'))->format('Y-m-d');

        $hotelId = $filter->hotelId;

        $adults = (int) $filter->rooms->get(0)->adults;
        $children = (int) $filter->rooms->get(0)->children;

        $opts = [
            'stay' => [
                'checkIn' => $checkIn,
                'checkOut' => $checkOut
            ],
            'occupancies' => [
                [
                    'rooms' => 1,
                    'adults' => $adults,
                    'children' => $children
                ]
            ]
        ];

        if (empty($hotelId)) {

            $regionId = $filter->regionId;
            $cityId = $filter->cityId;
            if (empty($regionId)) {
                $regionId = substr($cityId, 0, strpos($cityId, '-'));
                $cityId = substr($cityId, strpos($cityId, '-') + 1, strlen($cityId));
            }

            $opts['destination'] = [
                'code' => $regionId
            ];
        } else {
            $opts['hotels'] = [
                'hotel' => [$hotelId]
            ];
        }

        $options['body'] = json_encode($opts);

        $url = $this->apiUrl . '/hotel-api/1.0/hotels';
        $responseObj = $this->request($url, 'POST', $options);

        $responseHotelsArr = json_decode($responseObj->getBody(), true);

        if (isset($responseHotelsArr['error'])) {
            throw new Exception($responseHotelsArr['error']);
        }

        $responseHotels = $responseHotelsArr['hotels']['hotels'] ?? [];

        $response = [];

        foreach ($responseHotels as $responseHotel) {

            if (!empty($cityId)) {
                $cityCode = $responseHotel['zoneCode'];
                if ($cityCode != $cityId) {
                    continue;
                }
            }

            $hotel = new Availability();
            $offers = new OfferCollection();
            $hotel->Id = $responseHotel['code'];

            foreach ($responseHotel['rooms'] as $room) {
                foreach ($room['rates'] as $responseOffer) {
                    $offerObj = new Offer();

                    $currency = new Currency();
                    $currency->Code = $responseHotel['currency'];
                    $offerCode = $responseOffer['rateKey'];
                    $offerObj->Code = $offerCode;
                    $offerObj->rateType = $responseOffer['rateType'];

                    $offerObj->CheckIn = $checkIn;
                    $offerObj->Currency = $currency;

                    $taxes = 0;
                    $offerObj->Net = $responseOffer['net'] - $taxes;
                    $offerObj->Gross = $offerObj->Net;
                    $offerObj->InitialPrice = $offerObj->Net;
                    $offerObj->Comission = $taxes;

                    $offerObj->Availability = 'yes';
                    $offerObj->Days = $days;

                    // Rooms
                    $room1 = new Room();
                    $roomId = $room['code'];
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

                    $boardTypeName = $responseOffer['boardName'];

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
        }
        return $response;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        ApitudeValidator::make()->validateBookHotelFilter($filter);

        $offerCode = $filter->Items->get(0)->Offer_Code;
        
        if ($filter->Items->get(0)->Offer_rateType === 'RECHECK') {
            $url = $this->apiUrl . '/hotel-api/1.0/checkrates';

            $options['body'] = json_encode([
                'rooms' => [
                    [
                        'rateKey' => $offerCode
                    ]
                ]
            ]);

            $preBookingObj = $this->request($url, 'POST', $options);

            $preBooking = json_decode($preBookingObj->getBody(), true);

            $offerKey = $preBooking['hotel']['rooms'][0]['rates'][0]['rateKey'];
            if ($offerKey !== $filter->Items->get(0)->Offer_Code) {
                throw new Exception('Rate keys do not match');
            }
        }

        $paxes = [];
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $passenger) {
            if (!empty($passenger['Firstname'])) {
                if ($passenger['IsAdult']) {
                    $paxes[] = [
                        'roomId' => 1,
                        'name' => $passenger['Firstname'],
                        'surname' => $passenger['Lastname'],
                        'type' => 'AD',
                    ];
                } else {
                    $from = new DateTime($passenger['BirthDate']);
                    $to = new DateTime();
                    $age = $from->diff($to)->y;
                    $paxes[] = [
                        'roomId' => 1,
                        'name' => $passenger['Firstname'],
                        'surname' => $passenger['Lastname'],
                        'type' => 'CH',
                        'age' => $age
                    ];
                }
            }
        }

        $options['body'] = json_encode([
            'holder' => [
                'name' => $paxes[0]['name'],
                'surname' => $paxes[0]['surname']
            ],
            'rooms' => [
                [
                    'rateKey' => $offerCode,
                    'paxes' => $paxes
                ]
            ],
            'clientReference' => 'IntegrationAgency'
        ]);

        $url = $this->apiUrl . '/hotel-api/1.0/bookings';

        $bookingObj = $this->request($url, 'POST', $options);

        $bookingArr = json_decode($bookingObj->getBody(), true);
        $bookingId = $bookingArr['booking']['reference'];
        $booking = new Booking();
        $booking->Id = $bookingId;
        //$booking->rawResp = $bookingObj->getBody();

        $bookingCollection = new BookingCollection();
        $bookingCollection->add($booking);

        return [$booking, $bookingObj->getBody()];
    }

    public function request(string $url, string $method = HttpClient::METHOD_GET, array $options = []): ResponseInterface
    {        
        $data = $this->username . $this->password . time();
        $xsignature = hash('sha256', $data);
        $options['headers'] = [
            'Content-Type: application/json',
            'Api-Key: ' . $this->username,
            'X-Signature: '. $xsignature
        ];

        $httpClient = HttpClient::create();

        $response = $httpClient->request($method, $url, $options);
        // if (isset($this->post['get-raw-data'])) {
        //     $response->pretty();
        //     $this->requests->add($response);
        // }
        return $response;
    }
}
