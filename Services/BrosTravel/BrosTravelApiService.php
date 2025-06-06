<?php

namespace Integrations\BrosTravel;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
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
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Hotels\HotelRoom;
use App\Entities\Hotels\HotelRoomCollection;
use App\Entities\Hotels\HotelRoomType;
use App\Entities\Region;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
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
use App\Support\Http\RequestLog;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\Response\ResponseInterface;
use App\Support\Log;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use Utils\Utils;

class BrosTravelApiService extends AbstractApiService
{
    public function apiDoBooking(BookHotelFilter $filter): array
    {
        BrosTravelValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $optionsLogin['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'login',
            'params' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'id' => 1,
        ]);
        $jsonLogin = $this->request($this->apiUrl, HttpClient::METHOD_POST, $optionsLogin)->getContent();

        $responseLogin = json_decode($jsonLogin, true);

        if (!empty($responseLogin['error'])) {
            throw new Exception($responseLogin['error']['message']);
        }

        $token = $responseLogin['result']['token'] ?? null;

        $guests = [];
        $filter->Items->get(0)->Passengers->each(function(Passenger $passenger) use (&$guests) {
            if (!empty($passenger->Firstname)) {
                $guests[] = [
                    'type' => isset($passenger->IsAdult) && $passenger->IsAdult ? 'adult' : 'child',
                    'firstName' => $passenger->Firstname,
                    'lastName' => $passenger->Lastname,
                    'dateOfBirth' => $passenger->BirthDate
                ];
            }
        });

        $options['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'prepareReservation',
            'params' => [
                'partnerid' => (int) $responseLogin['result']['partnerid'],
                'propertyid' => (int) $filter->Items->get(0)->Hotel->InTourOperatorId,
                'checkinDate' => $filter->Items->get(0)->Room_CheckinAfter,
                'checkoutDate' => $filter->Items->get(0)->Room_CheckinBefore,
                'date' => (new DateTime())->format('Y-m-d'),
                'rooms' => [
                    [
                        'typeid' => (int) $filter->Items->get(0)->Room_Type_InTourOperatorId,
                        'board' => $filter->Items->get(0)->Board_Def_InTourOperatorId,
                        'guests' => $guests,
                        'additionalServices' => []
                    ]
                ]
            ],
            'id' => 1
        ]);

        $options['headers'] = ['Authorization' => 'Bearer ' . $token];
        $jsonPrepared = $this->request($this->apiUrl, HttpClient::METHOD_POST, $options)->getContent();
        $responsePrepared = json_decode($jsonPrepared, true);

        if (!empty($responsePrepared['error'])) {
            throw new Exception($responsePrepared['error']['message']);
        }

        $optionsBooking['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'createReservation',
            'params' => [
                'partnerid' => (int) $responseLogin['result']['partnerid'],
                'propertyid' => (int) $filter->Items->get(0)->Hotel->InTourOperatorId,
                'checkinDate' => $filter->Items->get(0)->Room_CheckinAfter,
                'checkoutDate' => $filter->Items->get(0)->Room_CheckinBefore,
                "phone" => "",
                "email" => "",
                'rooms' => [
                    [
                        'typeid' => (int) $filter->Items->get(0)->Room_Type_InTourOperatorId,
                        'board' => $filter->Items->get(0)->Board_Def_InTourOperatorId,
                        'guests' => $guests,
                        'additionalServices' => []
                    ]
                ]
            ],
            'id' => 1
        ]);

        $optionsBooking['headers'] = ['Authorization' => 'Bearer ' . $token];
        $jsonBooking = $this->request($this->apiUrl, HttpClient::METHOD_POST, $optionsBooking)->getContent();
        $responseBooking = json_decode($jsonBooking, true);

        $booking = new Booking();
        $booking->Id = $responseBooking['result']['reservationid'];
        return [$booking, json_encode($responseBooking)];
    }

    public function apiGetCities(CitiesFilter $params = null): CityCollection
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $countries = $this->apiGetCountries();
        $regions = $this->apiGetRegions();

        $options['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'getLocations',
            'params' => null,
            'id' => 1
        ]);
        $json = $this->request($this->apiUrl, HttpClient::METHOD_POST, $options)->getContent();
        $array = json_decode($json, true)['result'];

        $cities = new CityCollection();
        foreach ($array as $element) {
            $city = new City();
            $city->Name = $element['name'];
            $city->Id = $element['locationid'];
            $country = $countries->first(fn(Country $countryResponse) => $countryResponse->Name === trim($element['country']));
            $city->Country = $country;

            $regionId = preg_replace('/\s+/', '', $element['region']);
            $region = $regions->get($regionId);
            $city->County = $region;
            $cities->add($city);
        }
        return $cities;
    }

    public function apiGetRegions(): RegionCollection
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $regionsCached = Utils::getFromCache($this->handle, 'regions');
        if ($regionsCached === null) {
            $countries = $this->apiGetCountries();

            $options['body'] = json_encode([
                'jsonrpc' => '2.0',
                'method' => 'getLocations',
                'params' => null,
                'id' => 1
            ]);
            $json = $this->request($this->apiUrl, HttpClient::METHOD_POST, $options)->getContent();
            $array = json_decode($json, true)['result'];

            $regions = new RegionCollection();
            foreach ($array as $element) {
                $region = new Region();
                $region->Name = $element['region'];
                $region->Id = preg_replace('/\s+/', '', $region->Name);
                $country = $countries->first(fn(Country $countryResponse) => $countryResponse->Name === trim($element['country']));
                $region->Country = $country;
                $regions->put($region->Id, $region);
            }
            Utils::writeToCache($this->handle, 'regions', json_encode($regions), 0);
        } else {
            $regions = ResponseConverter::convertToCollection(json_decode($regionsCached, true), RegionCollection::class);
        }

        return $regions;
    }

    public function apiGetCountries(): CountryCollection
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $options['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'getLocations',
            'params' => null,
            'id' => 1
        ]);
        $json = $this->request($this->apiUrl, HttpClient::METHOD_POST, $options)->getContent();
        $array = json_decode($json, true)['result'];
        $map = CountryCodeMap::getCountryCodeMap();

        $countries = new CountryCollection();
        foreach ($array as $element) {
            $country = new Country();
            $country->Name = trim($element['country']);
            $country->Code = $map[$country->Name];
            $country->Id = $country->Code;
            $countries->put($country->Code, $country);
        }
        return $countries;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        $cities = $this->apiGetCities();

        $options['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'getProperty',
            'params' => [
                'propertyid' => (int) $filter->hotelId
            ],
            'id' => 1
        ]);
        $json = $this->request($this->apiUrl, HttpClient::METHOD_POST, $options)->getContent();
        $hotelResponse = json_decode($json, true)['result'];
        
        $city = $cities->first(fn(City $cityResponse) => $cityResponse->Id == $hotelResponse['locationid']);

        // $hotel->Address
        $address = new HotelAddress();
        $address->Latitude = $hotelResponse['lat'];
        $address->Longitude = $hotelResponse['lng'];
        $address->Details = $hotelResponse['address'];
        $address->City = $city;

        $images = new HotelImageGalleryItemCollection();

        foreach ($hotelResponse['images'] as $imageResponse) {
            $image = new HotelImageGalleryItem();
            $image->Alt = null;
            $image->RemoteUrl = $this->apiUrl 
            . '/images/properties/' 
            . $hotelResponse['propertyid']
            . '/' 
            . $imageResponse['filename'];
            $images->add($image);
        }

        $rooms = new HotelRoomCollection();
        foreach ($hotelResponse['rooms'] as $roomResponse) {
            $room = new HotelRoom();
            $room->Id = $roomResponse['typeid'];
            $room->Title = $roomResponse['type'];
            $type = new HotelRoomType();
            $type->Id = $room->Id;
            $type->Title = $room->Title;
            $room->Type = $type;
            $rooms->add($room);
        }

        $facilityMap = $this->facilityMap();

        $facilities = new FacilityCollection();
        foreach ($hotelResponse['facilities'] as $k => $facilityResponse) {
            $facility = new Facility();
            $facility->Id = $k;
            $facility->Name = $facilityMap[$facility->Id];
            $facilities->add($facility);
        }

        // $hotel->Content->ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $images;

        $contactPerson = new ContactPerson();
        $contactPerson->Email = $hotelResponse['email'];
        $contactPerson->Fax = $hotelResponse['fax'];
        $contactPerson->Phone = $hotelResponse['phone'];

        // $hotel->Content
        $content = new HotelContent();
        $content->ImageGallery = $imageGallery;
        $content->Content = $hotelResponse['description'];

        $hotel = new Hotel();
        $hotel->Id = $hotelResponse['propertyid'];
        $hotel->Name = $hotelResponse['name'];
        $hotel->Stars = $hotelResponse['rating'];
        $hotel->Content = $content;
        $hotel->Address = $address;
        $hotel->WebAddress = $hotelResponse['website'];
        $hotel->Rooms = $rooms;
        $hotel->Facilities = $facilities;

        $hotel->ContactPerson = $contactPerson;
        
        return $hotel;
    }

    private function facilityMap(): array
    {
        return [
            "charge" => "with charge", 
            "sofa" => "sofa", 
            "extra_bed" => "extra bed", 
            "bunk_bed" => "bunk bed", 
            "camp_bed" => "camp bed", 
            "other" => "other", 
            "house_rules" => "House rules", 
            "pet_policy" => "Pet policy", 
            "special_needs" => "People with special needs", 
            "dress_code" => "Dress code", 
            "services" => "Services", 
            "reception" => "Reception", 
            "elevator" => "Elevator", 
            "atm" => "ATM", 
            "exchange_office" => "Exchange office", 
            "shops" => "Shops", 
            "doctor" => "Doctor", 
            "car_rental" => "Car & bike rental", 
            "parking" => "Parking", 
            "internet" => "Internet", 
            "wifi_rooms" => "WiFi in rooms", 
            "wifi_common_areas" => "WiFi in common areas", 
            "wired_internet_common_areas" => "Wired internet in common areas", 
            "food_and_beverages" => "Food and beverages", 
            "restaurant" => "Restaurant", 
            "meals_served" => "Meals served as", 
            "buffet" => "buffet", 
            "set_menu" => "set menu", 
            "a_la_carte" => "A la carte restaurant", 
            "bars" => "Bars", 
            "children" => "Children", 
            "children_playground" => "Children's playground", 
            "children_animation" => "Children animation", 
            "mini_club" => "Mini club", 
            "activities" => "Activities", 
            "organized_beach" => "Organized beach", 
            "beach_sets" => "Umbrellas and beach sets", 
            "pool" => "Swimming pool", 
            "children_pool" => "Separate children pool", 
            "beach_towels" => "Beach towel service", 
            "daytime_animation" => "Daytime animation", 
            "evening_animation" => "Evening animation", 
            "gym" => "Gym", 
            "tennis" => "Tennis court", 
            "basketball" => "Basketball court", 
            "football" => "Football court", 
            "beach_volleyball" => "Beach volleyball", 
            "table_tennis" => "Table tennis", 
            "darts" => "Darts", 
            "billiards" => "Billiards", 
            "watersports" => "Watersports", 
            "bowling" => "Bowling", 
            "spa_wellness" => "SPA & Wellness center", 
            "body_face_treatments" => "Body and face treatments", 
            "indoor_pool" => "Indoor pool", 
            "conference" => "Conference facilities", 
            "bar_breakfast_area" => "Bar/breakfast area", 
            "outdoor_common_area" => "Outdoor common area", 
            "indoor_common_area" => "Indoor common area", 
            "sports" => "Sports activities", 
            "balcony_terrace" => "Balcony/terrace", 
            "view" => "View from the room", 
            "position" => "Room position", 
            "double_bed" => "Double bed", 
            "twin_beds" => "Twin beds", 
            "additional_beds" => "Additional beds", 
            "baby_cot" => "Baby cot", 
            "bedrooms" => "Bedrooms", 
            "kitchenette" => "Kitchenette", 
            "fridge_minibar" => "Fridge/minibar", 
            "ac" => "A/C", 
            "tv" => "TV", 
            "phone" => "Phone", 
            "safe_box" => "Safe box", 
            "wifi" => "WiFi in room", 
            "bathroom_amenities" => "Bathroom amenities", 
            "sheets_towels" => "Change of sheets and towels", 
            "additional_info" => "Additional info", 
            "mosquito_net" => "Mosquito net", 
            "living_room" => "Living room", 
            "kitchen" => "Kitchen", 
            "kitchen_amenities" => "Kitchen amenities", 
            "fridge" => "Fridge", 
            "freezer" => "Freezer", 
            "stove" => "Stove", 
            "oven" => "Oven", 
            "coffee_machine" => "Coffee machine", 
            "washing_machine" => "Washing machine", 
            "ironing_board" => "Iron and ironing board", 
            "private_bathroom_shower" => "Private bathroom with shower", 
            "private_bathroom_bathtub" => "Private bathroom with bathtub", 
            "bathroom_towels" => "Bathroom towels", 
            "cleaning" => "Room cleaning", 
            "private_entrance" => "Private entrance", 
            "adults_only" => "Adults only", 
            "family_friendly" => "Family friendly", 
            "distances" => "Distances", 
            "beach_200m" => "Up to 200m from the beach", 
            "beach_200m_500m" => "Between 200m and 500m from the beach", 
            "beach_over_500m" => "More than 500m from the beach", 
            "town_500m" => "Up to 500m from the town", 
            "town_over_500m" => "More than 500m from the town", 
            "airport_10km" => "Up to 10km from the airport", 
            "airport_over_10km" => "More than 10km from the airport" 
        ]; 
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $cities = $this->apiGetCities();

        $options['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'getProperties',
            'params' => null,
            'id' => 1
        ]);
        
        $json = $this->request($this->apiUrl, HttpClient::METHOD_POST,  $options)->getContent();
        $array = json_decode($json, true)['result'];
        
        $hotels = new HotelCollection();

        $client = HttpClient::create(['verify_peer' => false]);

        foreach ($array as $hotelResponse) {

            $options['body'] = json_encode([
                'jsonrpc' => '2.0',
                'method' => 'getProperty',
                'params' => [
                    'propertyid' => (int) $hotelResponse['propertyid']
                ],
                'id' => 1
            ]);

            $responses[] = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        }

        foreach ($responses as $responseObj) {
            
            // if (isset($this->post['get-raw-data'])) {
            //     $responseObj->pretty();
            //     $this->responses->add($responseObj);
            // }

            $hotelResponse = json_decode($responseObj->getContent(), true)['result'];
            $city = $cities->first(fn(City $cityResponse) => $cityResponse->Id == $hotelResponse['locationid']);

            // $hotel->Address
            $address = new HotelAddress();
            $address->Latitude = $hotelResponse['lat'];
            $address->Longitude = $hotelResponse['lng'];
            $address->Details = $hotelResponse['address'];
            $address->City = $city;

            $images = new HotelImageGalleryItemCollection();

            foreach ($hotelResponse['images'] as $imageResponse) {
                $image = new HotelImageGalleryItem();
                $image->Alt = null;
                $image->RemoteUrl = $this->apiUrl 
                . '/images/properties/' 
                . $hotelResponse['propertyid']
                . '/' 
                . $imageResponse['filename'];
                $images->add($image);
            }

            $rooms = new HotelRoomCollection();
            foreach ($hotelResponse['rooms'] as $roomResponse) {
                $room = new HotelRoom();
                $room->Id = $roomResponse['typeid'];
                $room->Title = $roomResponse['type'];
                $type = new HotelRoomType();
                $type->Id = $room->Id;
                $type->Title = $room->Title;
                $room->Type = $type;
                $rooms->add($room);
            }

            $facilityMap = $this->facilityMap();

            $facilities = new FacilityCollection();
            foreach ($hotelResponse['facilities'] as $k => $facilityResponse) {
                $facility = new Facility();
                $facility->Id = $k;
                $facility->Name = $facilityMap[$facility->Id];
                $facilities->add($facility);
            }

            // $hotel->Content->ImageGallery
            $imageGallery = new HotelImageGallery();
            $imageGallery->Items = $images;

            $contactPerson = new ContactPerson();
            $contactPerson->Email = $hotelResponse['email'];
            $contactPerson->Fax = $hotelResponse['fax'];
            $contactPerson->Phone = $hotelResponse['phone'];

            // $hotel->Content
            $content = new HotelContent();
            $content->ImageGallery = $imageGallery;
            $content->Content = $hotelResponse['description'];

            $hotel = new Hotel();
            $hotel->Id = $hotelResponse['propertyid'];
            $hotel->Name = $hotelResponse['name'];
            $hotel->Stars = $hotelResponse['rating'];
            $hotel->Content = $content;
            $hotel->Address = $address;
            $hotel->WebAddress = $hotelResponse['website'];
            $hotel->Rooms = $rooms;
            $hotel->Facilities = $facilities;

            $hotel->ContactPerson = $contactPerson;
           
            $hotels->add($hotel);
        }
        return $hotels;
    }

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateAvailabilityFilter($filter);

        $optionsLogin['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'login',
            'params' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'id' => 1,
        ]);
        $client = HttpClient::create();

        $responseObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $optionsLogin);
        $jsonLogin = $responseObj->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $optionsLogin, $jsonLogin, $responseObj->getStatusCode());

        $responseLogin = json_decode($jsonLogin, true);

        if (!empty($responseLogin['error'])) {
            throw new Exception($responseLogin['error']['message']);
        }

        $token = $responseLogin['result']['token'] ?? null;

        $checkIn = $filter->checkIn;
        $days = $filter->days;
        $checkInDateTime = new DateTimeImmutable($checkIn);
        $checkOutDateTime = $checkInDateTime->add(new DateInterval('P' . $days . 'D'));
        $checkOut = $checkOutDateTime->format('Y-m-d');
        $adults = (int) $filter->rooms->get(0)->adults;
        $childrenAges = $filter->rooms->get(0)->childrenAges;
        $childrenAgesInt = [];
        if ($childrenAges !== null) {
            foreach ($childrenAges as $age) {
                $childrenAgesInt[] = (int) $age;
            }
        }

        $params = [
            'partnerid' => (int) $responseLogin['result']['partnerid'],
            'date' => (new DateTime())->format('Y-m-d'),
            'checkinDate' => $checkIn,
            'nights' => (int) $days,
            'rooms' => [
                [
                    'adults' => $adults,
                    'children' => $childrenAgesInt
                ]
            ],
            'includePaymentTerms' => true
        ];

        if (!empty($filter->hotelId)) {
            $params['propertyid'] = (int) $filter->hotelId;
        } else {
            $cityId = $filter->cityId;
            if (empty($cityId)) {
                $region = $this->apiGetRegions()->get($filter->regionId);
                $params['region'] = $region->Name;
            } else {
                $params['locationid'] = (int) $cityId;
            }
        }

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'searchAvailableProperties',
            'params' => $params,
            'id' => 1
        ];

        $client = HttpClient::create();
        $options['body'] = json_encode($data);
        
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];
        
        $responseObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
       
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(false), $responseObj->getStatusCode());
        $json = $responseObj->getContent();
        
        $response = json_decode($json, true);

        if (!empty($response['error'])) {
            throw new Exception($response['error']['message']);
        }

        $availabilityCollection = new AvailabilityCollection();

        foreach($response['result'] as $offerResponse) {
            $hotelId = $offerResponse['propertyid'];
            $availability = new Availability();
            $availability->Id = $hotelId;

            $offerCollection = new OfferCollection();
            foreach ($offerResponse['availableRooms'] as $roomOffers) {
                foreach ($roomOffers['rooms'] as $roomOffer) {
                    $offer = new Offer();
                    $roomId = $roomOffer['typeid'];
                    $mealId = $roomOffer['board'];
                    $price = $roomOffer['price'];
                    $offer->Code = $hotelId . '~' 
                        . $roomId . '~' 
                        . $mealId . '~' 
                        . $checkIn . '~' 
                        . $checkOut . '~' 
                        . $price . '~' 
                        . $adults . '~' 
                        . ($childrenAges ? implode('|', $childrenAges->toArray()) : '');

                    $currency = new Currency();
                    $currency->Code = 'EUR';
                    $offer->Currency = $currency;

                    $offer->Comission = 0;
                    $offer->InitialPrice = $roomOffer['initialPrice'] ?? $price;
                    $offer->Gross = $price;
                    $offer->Net = $price;

                    $offerAvailability = '';
                    if ((isset($roomOffer['onRequest']) && $roomOffer['onRequest']) 
                        || $roomOffer['available'] === 'on_request'
                    ) {
                        $offerAvailability = Offer::AVAILABILITY_ASK;
                    } else if ($roomOffer['available'] === 'stop_sale') {
                        $offerAvailability = Offer::AVAILABILITY_NO;
                    } else {
                        $offerAvailability = Offer::AVAILABILITY_YES;
                    }

                    $offer->Availability = $offerAvailability;
                    $offer->CheckIn = $checkIn;
                    $offer->Days = $days;

                    $rooms = new RoomCollection();

                    $room = new Room();
                
                    $room->Id = $roomId;
                    $room->CheckinAfter = $checkIn;
                    $room->CheckinBefore = $checkOut;
                    $room->Currency = $currency;
                    $room->Quantity = 1;
                    $room->Availability = $offerAvailability;

                    $info = '';
                    if (isset($roomOffer['specialOffers'])) {
                        foreach ($roomOffer['specialOffers'] as $apiOffer) {
                            $info .= $apiOffer['title'] . ' ';
                        }
                    }

                    $room->InfoDescription = $info;

                    $roomMerch = new RoomMerch();
                    $roomMerch->Id = $roomId;
                    if (!isset($offerResponse['rooms'][$roomId]['type'])) {
                        continue;
                    }
                    $roomMerch->Title = $offerResponse['rooms'][$roomId]['type'];

                    $roomMerchType = new RoomMerchType();
                    $roomMerchType->Id = $roomId;
                    $roomMerchType->Title = $roomMerch->Title;
                    $roomMerch->Type = $roomMerchType;

                    $room->Merch = $roomMerch;

                    $rooms->add($room);
                    $offer->Rooms = $rooms;

                    $offer->Item = $room;

                    $mealItem = new MealItem();
                    $mealMerch = new MealMerch();
                    $mealMerch->Title = $mealId;
                    $mealMerch->Id = $mealId;
                    $mealItem->Merch = $mealMerch;
                    $mealItem->Currency = $currency;

                    $offer->MealItem = $mealItem;

                    $departureTransportItem = new DepartureTransportItem();
                    $depTransportMerch = new TransportMerch();
                    $depTransportMerch->Title = 'CheckIn: '. $checkInDateTime->format('d.m.Y');
                    $departureTransportItem->Merch = $depTransportMerch;
                    $departureTransportItem->Currency = $currency;
                    $departureTransportItem->DepartureDate = $checkIn;
                    $departureTransportItem->ArrivalDate = $checkIn;

                    $offer->DepartureTransportItem = $departureTransportItem;

                    $returnTransportItem = new ReturnTransportItem();

                    $returnTransportMerch = new TransportMerch();
                    $returnTransportMerch->Title = 'CheckOut: '. $checkOutDateTime->format('d.m.Y');
                    $returnTransportItem->Merch = $returnTransportMerch;
                    $returnTransportItem->Currency = $currency;
                    $returnTransportItem->DepartureDate = $checkOut;
                    $returnTransportItem->ArrivalDate = $checkOut;

                    $departureTransportItem->Return = $returnTransportItem;
                    
                    $offer->ReturnTransportItem = $returnTransportItem;

                    $offer->bookingPrice = $offer->Net;
                    $offer->bookingInitialPrice = $offer->InitialPrice;
                    
                    $payments = new OfferPaymentPolicyCollection();

                    $today = new DateTimeImmutable();
                    if (!empty($roomOffer['paymentTerms'])) {
                        $percent = $roomOffer['paymentTerms']['depositPercent'] / 100;
                        $firstDeadline = $roomOffer['paymentTerms']['depositDeadline'];
                        $fullDeadline = $roomOffer['paymentTerms']['fullPaymentDeadline'];
                        $payment1 = new OfferPaymentPolicy();
                        $payment1->Currency = $currency;
                        $payment1->Amount = $price * $percent;
                        $payment1->PayAfter = $today->format('Y-m-d');
                        $payment1->PayUntil = $firstDeadline;
                        $payments->add($payment1);

                        if ($percent !== 1) {

                            $firstDeadlineDate = new DateTimeImmutable($firstDeadline);
                            $fullDeadlineDate = new DateTimeImmutable($fullDeadline);

                            $payment2 = new OfferPaymentPolicy();
                            $payment2->Currency = $currency;
                            $payment2->Amount = $price - $payment1->Amount;
                            if ($firstDeadlineDate == $fullDeadlineDate) {
                                $payment2->PayAfter = $firstDeadline;
                            } else {
                                $payment2->PayAfter = $firstDeadlineDate->modify('+1 day')->format('Y-m-d');
                            }

                            
                            $payment2->PayUntil = $fullDeadline;
                            $payments->add($payment2);
                        }
                    } else {
                        Log::warning($this->handle . ': payment policy does not exist for ' . json_encode($filter));
                    }
                    $offer->Payments = $payments;

                    $cancelFees = new OfferCancelFeeCollection();
                    if (!empty($roomOffer['cancellationFees'])) {
                        foreach ($roomOffer['cancellationFees'] as $feeResponse) {
                            $cancelFee = new OfferCancelFee();
                            $cancelFee->Currency = $currency;
                            $cancelFee->DateEnd = $checkIn;
                            $cancelFee->DateStart = (new DateTime($feeResponse['after']))->format('Y-m-d');
                            $feePercent = $feeResponse['percent'] / 100;
                            $feePrice = $price * $feePercent;
                            $cancelFee->Price = $feePrice;
                            $cancelFees->add($cancelFee);
                        }
                    } else {
                        // creating from payment policies
                        $priceCP = 0;
                        $i = 0;
                        foreach ($payments as $paymentComposed) {
                            $cancelFee = new OfferCancelFee();
                            $cancelFee->Currency = $currency;
                            $priceCP += $paymentComposed->Amount;
                            $cancelFee->Price = $priceCP;
                            $cancelFee->DateStart = $paymentComposed->PayUntil;
                            if ($payments->get($i + 1) !== null) {
                                $cancelFee->DateEnd = $payments->get($i + 1)->PayAfter;
                            } else {
                                $cancelFee->DateEnd = $checkOut;
                            }
                            
                            $cancelFees->add($cancelFee);
                            $i++;
                        }
                    }
                    
                    $offer->CancelFees = $cancelFees;

                    $offerCollection->put($offer->Code, $offer);
                }
            }
            $availability->Offers = $offerCollection;
            $availabilityCollection->add($availability);
        }
        return $availabilityCollection;
    }

    public function apiGetOfferCancelFees(CancellationFeeFilter $cancelFeeFilter): OfferCancelFeeCollection
    {
        $cancelFees = new OfferCancelFeeCollection();

        $hotelId = $cancelFeeFilter->Hotel->InTourOperatorId;
        $checkInDateTime = new DateTimeImmutable($cancelFeeFilter->CheckIn);
        $checkIn = $checkInDateTime->format('Y-m-d');
        $price = $cancelFeeFilter->SuppliedPrice;
        $days = (int) $cancelFeeFilter->Duration;

        $options['body'] = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'getProperty',
            'params' => [
                'propertyid' => (int) $hotelId
            ],
            'id' => 1
        ]);
        $json = $this->request($this->apiUrl, HttpClient::METHOD_POST, $options)->getContent();
        $hotelResponse = json_decode($json, true)['result'];

        $hotelCancelFees = [];
        if (!empty($hotelResponse['cancellationFees'])) {
            $hotelCancelFees = $hotelResponse['cancellationFees'];
        }
        $currency = new Currency();
        $currency->Code = $cancelFeeFilter->SuppliedCurrency;

        foreach ($hotelCancelFees as $hotelCancelFee) {
            $cancelFee = new OfferCancelFee();
            $cancelFee->Currency = $currency;

            if (is_array($hotelCancelFee['period'])) {
                if (count($hotelCancelFee['period']) === 2) {
                    $dateStart = $checkInDateTime->modify("-{$hotelCancelFee['period'][1]} days")->format('Y-m-d');
                    $dateEnd = $checkInDateTime->modify("-{$hotelCancelFee['period'][0]} days")->format('Y-m-d');
                } else {
                    Log::warning($this->handle . ': hotel has only one period in the array');
                    continue;
                }
                
            } else {
                if ($hotelCancelFee['period'] === 0) {
                    $dateStart = $checkIn;
                    $dateEnd = $checkIn;
                } if ($hotelCancelFee['period'] === -1) {
                    // we do not use early departure
                    continue;
                } else {
                    $dateStart = $checkIn;
                    $dateEnd = $checkInDateTime->modify("-{$hotelCancelFee['period']} days")->format('Y-m-d');
                }
            }
            $cancelFee->DateStart = $dateStart;
            $cancelFee->DateEnd = $dateEnd;

            if ($hotelCancelFee['unit'] === '%') {
                $feePercent = $hotelCancelFee['value'] / 100;
                $feePrice = $price * $feePercent;
            } else if ($hotelCancelFee['unit'] === 'night'){
                $feePrice = ($price/$days) * $hotelCancelFee['value'];
            } else {
                Log::warning($this->handle . ': unknown cancellation policy unit.');
                continue;
            }

            $cancelFee->Price = $feePrice;
            $cancelFees->add($cancelFee);
        }
        return $cancelFees;
    }

    public function request(string $url, string $method = HttpClient::METHOD_GET, array $options = []): ResponseInterface
    {   
        
        $httpClient = HttpClient::create();
        $response = $httpClient->request($method, $url, $options);
        if (isset($this->post['get-raw-data'])) {
            $request = new RequestLog($method, $url, $options);
            $request->response = $response->getContent();
            $this->requests->add($request);
        }
        return $response;
    }
}
