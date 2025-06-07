<?php

namespace Integrations\AlladynCharters;

use App\Entities\Availability\AirportTaxesCategory;
use App\Entities\Availability\AirportTaxesItem;
use App\Entities\Availability\AirportTaxesMerch;
use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\ReturnTransportItem;
use App\Entities\Availability\Room;
use App\Entities\Availability\RoomCollection;
use App\Entities\Availability\RoomMerch;
use App\Entities\Availability\RoomMerchType;
use App\Entities\Availability\TransferCategory;
use App\Entities\Availability\TransferItem;
use App\Entities\Availability\TransferMerch;
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
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\[];
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\Response\ResponseInterface;
use App\Support\Log;
use CurlMultiHandle;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use Integrations\AlladynHotels\AlladynHotelsUtils;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use SimpleXMLElement;
use Utils\Utils;

use function env;

class AlladynChartersApiService extends AbstractApiService
{
    private string $usernameOld;
    private string $passwordOld;
    private ?array $services = null;

    private const ROMANIA_ID = '156505';
    private const MINIMUM_MEAL_ID = '15350';
    private const MINIMUM_RATING_ID = '269506';

    public function __construct()
    {
        parent::__construct();

        $this->usernameOld = env('ALLADYN_OLD_USERNAME');
        $this->passwordOld = env('ALLADYN_OLD_PASSWORD');
    }

    #override
    public function apiTestConnection(): bool
    {
        $ok = false;
        $apiKeyOk = false;
        $oldCred = false;
        $bookingCred = false;

        // checking Api-Key
        $url = $this->apiUrl . '/v2/tours/depcities';
        $response = json_decode($this->request($url)->getBody(), true);

        if ($response !== null && empty($response['error'])) {
            foreach ($response as $departureCity) {
                if ($departureCity['country'] === 'Romania') {
                    $urlDestCountries = $this->apiUrl . '/v2/tours/arrcountries?cityId='.$departureCity['id'];
                    $respBody = $this->request($urlDestCountries)->getBody();
                    $responseDestCountries = json_decode($respBody, true);
                    if ($responseDestCountries !== null && count($responseDestCountries) > 0) {
                        foreach ($responseDestCountries as $destinationCountry) {

                            // getting hotels
                            $urlHotelList = $this->apiUrl . '/v2/tours/list?countryId='. $destinationCountry['id'] .'&cityId='.$departureCity['id'];
    
                            $hotelList = json_decode($this->request($urlHotelList)->getBody(), true)['hotels'];

                            if ($hotelList !== null && count($hotelList) > 0) {
                                $id = $hotelList[0]['hotelId'];
                                $url = $this->apiUrl . '/v2/tours/content?hotelId='. $id . '&locale=ro';
                                $responseObj = $this->request($url);
                                $hotel = json_decode($responseObj->getBody(), true);
                                if (isset($hotel['success']) && $hotel['success'] === true) {
                                    $apiKeyOk = true;
                                }
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }

        // checking old credentials
        $body = [
            'auth' => [
                'user' => $this->usernameOld,
                'parola' => $this->passwordOld
            ],
            'cerere' => [
                'serviciu' => 'fileList'
            ]
        ];

        if ($this->handle === 'localhost-tezc') {
            $url = 'http://localhost/travelfuse-integrations/test-alladyn/xml/';
        } else {
            $url = 'https://data.tez-tour.ro/xml/';
        }

        $response = $this->request($url, 'POST', [
            'body' => http_build_query($body),
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ]);
        $xml = $response->getBody();
        if ($this->isXml($xml)) {
            $filesXml = simplexml_load_string($xml);
            if (empty($filesXml->DataSet->Body->Erori->Eroare)) {
                $oldCred = true;
            }
        }

        // checking booking credentials
        $username = env('ALLADYN_BOOKING_USERNAME');
        $password = env('ALLADYN_BOOKING_PASSWORD');
        $url = $this->apiUrl . '/v2/xmlgate/auth?j_login='.$username.'&j_passwd='.$password;
        $response = $this->request($url)->getBody();

        if ($this->isXml($response)) {
            $xml = new SimpleXMLElement($response);
            $attr = $xml->attributes();
            if (!(bool) $attr->userError) {
                $bookingCred = true;
            }
        }

        if ($apiKeyOk && $oldCred && $bookingCred) {
            $ok = true;
        }

        return $ok;
    }

    //todo: add in utils
    function isXml(string $value): bool
    {
        $prev = libxml_use_internal_errors(true);

        $doc = simplexml_load_string($value);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return false !== $doc && empty($errors);
    }

    public function apiGetCountries(): array
    {
        // Validator::make()->validateUsernameAndPassword($this->post);
        $file = 'countries';
        $countriesJson = Utils::getFromCache($this->handle, $file);
        if ($countriesJson === null) {

            $url = $this->apiUrl . '/v2/tours/depcities';
            $response = json_decode($this->request($url)->getBody(), true);

            if (!empty($response['error'])) {
                throw new Exception(json_encode($response['error']));
            }
            
            $countries = [];
            foreach ($response as $departureCity) {
                $urlDestCountries = $this->apiUrl . '/v2/tours/arrcountries?cityId='.$departureCity['id'];
                $responseDestCountries = json_decode($this->request($urlDestCountries)->getBody(), true);

                if (!empty($responseDestCountries['error'])) {
                    continue;
                }

                foreach ($responseDestCountries as $destinationCountry) {
                    $addRomania = true;
                    if ($departureCity['country'] === 'Romania') {
                        if ($addRomania) {
                            $country = new Country();
                            $country->Id = $departureCity['id_country'];
                            $country->Code = AlladynChartersUtils::getCountryCodeByName($departureCity['country']);
                            $country->Name = $departureCity['country'];
                            $countries->put($country->Code, $country);
                            $addRomania = false;
                        }  
                        $country = new Country();
                        $country->Id = $destinationCountry['id'];
                        $country->Code = AlladynChartersUtils::getCountryCodeByName($destinationCountry['name']);
                        $country->Name = $destinationCountry['name']; 
    
                        $countries->put($country->Code, $country);
                    }
                }
            }
            $data = json_encode_pretty($countries);
            Utils::writeToCache($this->handle, $file, $data, 7);
        } else {
            $countriesArray = json_decode($countriesJson, true);
            $countries = ResponseConverter::convertToCollection($countriesArray, array::class);
        }

        return $countries;
    }

    public function apiGetCities(CitiesFilter $params = null): array
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $file = 'cities';
        $citiesJson = Utils::getFromCache($this->handle, $file);

        if ($citiesJson === null) {

            $url = $this->apiUrl. '/v2/tours/depcities';
            $response = json_decode($this->request($url)->getBody(), true);

            if (!empty($response['error'])) {
                throw new Exception(json_encode($response['error']));
            }
            $mappedAirports = $this->getMappedAirportsByCity();
            $flights = $this->getFlightDepartures();
            $regions = $this->getRegionsById();
            $countries = $this->apiGetCountries();

            $cities = [];

            foreach ($response as $departureCity) {
                if ($departureCity['country'] === 'Romania') {
                    $romania = new Country();
                    $romania->Id = $departureCity['id_country'];
                    $romania->Name = $departureCity['country'];
                    $romania->Code = AlladynHotelsUtils::getCountryCodeByName($romania->Name);
                    
                    $region = new Region();
                    $region->Id = $departureCity['id'];
                    $region->Name = $departureCity['name'];
                    $region->Country = $romania;

                    $cityFromRomania = new City();
                    $cityFromRomania->Id = $departureCity['id'];
                    $cityFromRomania->Name = $departureCity['name'];
                    $cityFromRomania->Country = $romania;
                    $cityFromRomania->County = $region;
                    $cities->put($cityFromRomania->Id, $cityFromRomania);
                }
            }

            // get arrival regions from arrival airports, dep city from Romania
            foreach ($flights->item as $flight) {
                if ($flight->prop[6] == 'true' && $mappedAirports[(string)$flight->prop[1]]['countryId'] === self::ROMANIA_ID) {

                    $region = new Region();
                    $region->Id = $mappedAirports[(string)$flight->prop[2]]['cityId'];
                    $region->Name = $regions[$region->Id]['regionName'];
                    $region->Country = $romania;

                    $cityDestination = new City();
                    $cityDestination->Id = $mappedAirports[(string)$flight->prop[2]]['cityId'];
                    $cityDestination->Name = $regions[$cityDestination->Id]['regionName'];
                    $countryDestionation = $countries->first(fn(Country $country) => $country->Id === $regions[$cityDestination->Id]['countryId']);
                    if ($countryDestionation === null) {
                        continue;
                    }
                    $cityDestination->Country = $countryDestionation;
                    $cityDestination->County = $region;
                    $cities->put($cityDestination->Id, $cityDestination);
                }
            }
            $json = $this->getHotelsDetailsCache();

            $hotelDetailsList = json_decode($json, true);

            foreach($hotelDetailsList as $detailsResponse) {
                $responseHotelDetails = json_decode($detailsResponse, true)['hotel'];

                $country = new Country();
                $countryId = $responseHotelDetails['country']['id'];
                $country = $countries->first(fn(Country $country) => $country->Id === $countryId);

                $region = new Region();
                $region->Id = $responseHotelDetails['region']['id'];
                $region->Name = $responseHotelDetails['region']['title'];
                $region->Country = $country;

                // $address->City
                $city = new City();
                $city->Id = $responseHotelDetails['region']['id'];
                $city->Name = $responseHotelDetails['region']['title'];
                $city->Country = $country;
                $city->County = $region;
                $cities->put($city->Id, $city);
            }
            // cache cities
            $data = json_encode_pretty($cities);
            Utils::writeToCache($this->handle, $file, $data, 7);
        } else {
            $citiesArray = json_decode($citiesJson, true);
            $cities = ResponseConverter::convertToCollection($citiesArray, array::class);
        }

        return $cities;
    }

    public function apiGetRegions(): []
    {
        Validator::make()->validateUsernameAndPassword($this->post);
        $cities = $this->apiGetCities();
        $regions = [];

        foreach ($cities as $cityResponse) {
            $region = new Region();
            $region->Id = $cityResponse->Id;
            $region->Name = $cityResponse->Name;
            $region->Country = $cityResponse->Country;
            $regions->add($region);
        }
        return $regions;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateHotelDetailsFilter($filter);

        $hotelId = $filter->hotelId;
        $url = $this->apiUrl . '/v2/tours/content?hotelId='.$hotelId . '&locale=ro';

        $responseObj = $this->request($url);

        $response = json_decode($responseObj->getBody(), true)['hotel'];
        $countries = $this->apiGetCountries();

        if (!empty($response['error'])) {
            throw new Exception(json_encode($response['error']));
        }

        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();
        // Content ImageGallery
        $imageGallery = new HotelImageGallery();

        foreach ($response['images'] as $responseImage) {
            $image = new HotelImageGalleryItem();
            $image->RemoteUrl = $responseImage['orig'];
            
            $image->Alt = $responseImage['title'] ?? null;
            $items->add($image);
        }

        $imageGallery->Items = $items;

        // Content Address Country
        $countryId = $response['country']['id'];
        $country = $countries->first(fn(Country $countryCol) => $countryId === $countryCol->Id);

        $region = new Region();
        $region->Id = $response['region']['id'];
        $region->Name = $response['region']['title'];
        $region->Country = $country;
        
        // Content Address City
        $city = new City();
        $city->Name = $response['region']['title'];
        $city->Id = $response['region']['id'];
        $city->Country = $country;
        $city->County = $region;

        // Content Address
        $address = new HotelAddress();
        $address->City = $city;
        $address->Details = $response['address'];
        $address->Latitude = $response['coordinates']['lat'];
        $address->Longitude = $response['coordinates']['lng'];

        // Content ContactPerson
        $contactPerson = null;

        $facilities = new FacilityCollection();

        foreach ($response['facilities'] as $facilityResponse) {
            if (isset($facilityResponse['list'])) {
                foreach ($facilityResponse['list'] as $facilitySub) {
                    $facility = new Facility();
                    $facility->Id = preg_replace('/\s+/', '', $facilitySub['name']);
                    $facility->Name = $facilitySub['name'];
                    $facilities->put($facility->Id, $facility);
                }
            }
        }

        // Content
        $content = new HotelContent();
        $content->Content = $response['description'];;
        $content->ImageGallery = $imageGallery;

        $details = new Hotel();
        $details->Name = $response['title'];
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Content = $content;
        $details->WebAddress = null;
        return $details;
    }

    public function getFlightsMap(array $airportsMap): array
    {
        $flightDepartures = $this->getFlightDepartures();

        $flights = [];
        foreach ($flightDepartures->item as $departure) {
            $departureFromFlights = (new DateTime($departure->prop[3]))->setTime(0, 0)->format('Y.m.d');
            $flights[
                $departureFromFlights
                . '-'
                . $airportsMap[(string) $departure->prop[1]]['cityId'] 
                . '-' 
                . $airportsMap[(string) $departure->prop[2]]['cityId']
            ] = $departure;
        }
        return $flights;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        $response = [];
        return $response;

        $destinationCityId  = empty($filter->cityId) ? $filter->regionId : $filter->cityId;

        AlladynChartersValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateAvailabilityFilter($filter);

        $checkIn = $filter->checkIn;

        $destinationCountryId = $filter->countryId;

        $cities = $this->apiGetCities();

        if ($destinationCountryId === null) {
            $destinationCountryId = $cities->get($destinationCityId)->Country->Id;
        }

        $departureCityId = $filter->departureCity;

        $tourId = '';
        if (!empty($destinationCityId)) {
            $tourId .= '&tourId=' . $destinationCityId;
        }

        $hotelId = $filter->hotelId;

        $days = $filter->days;
        $checkInDate = (new DateTimeImmutable($checkIn))->setTime(0, 0);
        $checkOutDateTime = $checkInDate->add(new DateInterval('P' . $days . 'D'));
        $checkOut = $checkOutDateTime->format('Y-m-d');

		$accommodationId = $this->getAccommodationId($filter);
        $airports = $this->getMappedAirportsByCity();

        $flightsMap = $this->getFlightsMap($airports);

        if ($filter->price_filter === null) {
            $priceMin = 0;
            $priceMax = 99999;
        } else {
            $priceMin = (float) $filter->price_filter->from;
            $priceMax = (float) $filter->price_filter->to;
        }

        $agencyId = 1119; //todo: astept
        $currency = 18864; // eur

        $hotelClassId = $this->getMinAcceptableRating($destinationCountryId, $departureCityId);
        $hotelClassBetter = 'true';
        $rAndBId = $this->getMinAcceptablerAndBId($destinationCountryId, $departureCityId);
        $rAndBBetter = 'true';

        $departurecity = $cities->first(fn(City $cityResponse) => $cityResponse->Id === $departureCityId);

        $offersArray = [];
        $hasMore = true;
        $i = 0;
        
        while ($hasMore) {
            $i ++;
            $url = $this->apiUrl . '/v2/tours/search?'
                .'countryId='.$destinationCountryId
                .'&cityId='.$departureCityId
                .'&agencyId='.$agencyId
                .'&priceMin='.$priceMin
                .'&priceMax='.$priceMax
                .'&before='.$checkInDate->format('d.m.Y')
                .'&after='.$checkInDate->format('d.m.Y')
                .'&currency='.$currency
                .'&nightsMin='.$days
                .'&nightsMax='.$days
                .'&accommodationId='.$accommodationId
                .'&hotelClassId='.$hotelClassId
                .'&hotelClassBetter='.$hotelClassBetter
                .'&rAndBId='.$rAndBId
                .'&rAndBBetter='.$rAndBBetter
                . $tourId
                .'&locale=ro'
            ;

            if (!empty($hotelId)) {
                $url .= '&hotelId='.$hotelId;
            }
            
            $responseObj = $this->request($url);
            $responseArr = json_decode($responseObj->getBody(), true);
            
            if (isset($responseArr['error']) && $responseArr['error']['message'] === 'No results were found') {
                //Log::debug('Result set nr ' . $i . ' returned in  '. $time .'s for '. $url . '.' . 'Has more: false');
                break;
            }

            if (isset($responseArr['success']) && $responseArr['success'] === false) {
                throw new Exception($responseArr['message']);
            }

            $hasMore = $responseArr['info'][3][1] === 'true';

            //Log::debug('Result set nr ' . $i . ' returned in  '. $time .'s for '. $url . '. Has more: ' . $responseArr['info'][3][1]);

            // get the last item in data
            $lastIndex = count($responseArr['data']) - 1;
            if ($lastIndex != -1) {
                $lastItem = $responseArr['data'][$lastIndex];
                $priceMin = $lastItem[10]['total'];
                $offersArray[] = $responseArr;
            }
        }

        foreach ($offersArray as $responseHotels) {

            foreach ($responseHotels['data'] as $responseHotel) {
                $hotel = new Availability();
                
                $hotel->Id = $responseHotel[6][3];
                
                $currency = new Currency();
                $currency->Code = $responseHotel[10]['currency'];

                $destinationCity = $cities->get($responseHotel[5][2]);
                
                $offer = new Offer();

                $offer->InitialData = $responseHotel[11][0][0];
                
                $offer->CheckIn = $checkIn;
                $offer->Currency = $currency;
                $offer->Days = $days;

                $taxes = 0;
                $offer->Net = $responseHotel[10]['total'] - $taxes;
                $offer->Gross = $offer->Net;
                $offer->Comission = $taxes;

                $offer->Availability = $responseHotel[14]['onlineConfirm']['value'] ? Offer::AVAILABILITY_YES : Offer::AVAILABILITY_ASK;
                
                // Rooms
                $room1 = new Room();
                $roomId = $responseHotel[8][0];
                if (is_array($roomId)) {
                    continue;
                }
                
                $room1->Id = $roomId;
                $room1->CheckinBefore = $checkOut;
                $room1->CheckinAfter = $checkIn;
                $room1->Currency = $currency;
                $room1->Quantity = 1;
                $room1->Availability = $offer->Availability;

                $merch = new RoomMerch();
                $merch->Id = $roomId;
                $merch->Title = $responseHotel[8][1];

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

                $boardTypeName = $responseHotel[7][1];

                // MealItem Merch
                $boardMerch = new MealMerch();
                $boardMerch->Title = $boardTypeName;
                $boardMerch->Id = $responseHotel[7][0];

                $offer->Code = $hotel->Id . '~' . 
                    $roomId . '~' . 
                    $responseHotel[7][0] . '~' . 
                    $checkIn . '~' . 
                    $checkOut . '~' . 
                    $offer->Net . '~' . 
                    $filter->rooms->first()->adults . '~' . 
                    $post['args'][0]['rooms'][0]['children'] . '~' .
                    ($post['args'][0]['rooms'][0]['childrenAges'] ? implode('|', $post['args'][0]['rooms'][0]['childrenAges']->toArray()) : '');
                
                // MealItem
                $mealItem->Merch = $boardMerch;
                $mealItem->Currency = $currency;
                $offer->MealItem = $mealItem;

                // ------ search for offers with the same roomId and mealId ----------
                // get the offer from offer collection for this hotel
                
                $availabilityToCheck = $response->get($hotel->Id);

                if ($availabilityToCheck !== null) {
                    $cheaperOffer = $availabilityToCheck->Offers->first(
                        function(Offer $offerResponse) use ($offer) {
                            $result = false;
                            if ($offerResponse->Rooms->first()->Id === $offer->Rooms->first()->Id
                                && $offerResponse->MealItem->Merch->Id === $offer->MealItem->Merch->Id
                            ) {
                                $result = true;
                            }
                            
                            return $result;
                    });

                    if ($cheaperOffer !== null) {
                        //dump($cheaperOffer);
                        $cheaperOffer->InitialPrice = $offer->Net;
                        // add the offer back to the list

                        $availabilityToCheck->Offers->put($cheaperOffer->Code, $cheaperOffer);
                        $response->put($availabilityToCheck->Id, $availabilityToCheck);

                        // found similar cheaper offer, no need to add
                        continue;
                    }
                }
                
                $offer->InitialPrice = $offer->Net;

                // get departure datetime using the last flight from the day/departure airport/arrival airport
                $departureFlight = $flightsMap[$checkInDate->format('Y.m.d') . '-' . $departureCityId . '-' . $responseHotel[5][5]];
                $returnFlight = $flightsMap[$checkOutDateTime->format('Y.m.d') . '-'. $responseHotel[5][5] . '-' . $departureCityId];

                $departureFlightDate = (new DateTime($departureFlight->prop[3]))->format('Y-m-d H:i');
                $departureFlightDateArrival = (new DateTime($departureFlight->prop[4]))->format('Y-m-d H:i');

                $returnFlightDate = (new DateTime($returnFlight->prop[3]))->format('Y-m-d H:i');
                $returnFlightDateArrival = (new DateTime($returnFlight->prop[4]))->format('Y-m-d H:i');

                // if no flights, skip offer
                if ($departureFlight === null || $returnFlight === null) {
                    continue;
                }

                $offer->departureFlightId = $departureFlight->id;
                $offer->returnFlightId = $returnFlight->id;

                // get arrival airport from today + nights and departure airport/ arrival airport

                // departure transport item merch
                $departureTransportMerch = new TransportMerch();
                $departureTransportMerch->Title = "Dus: ".$checkInDate->format('d.m.Y');
                $departureTransportMerch->Category = new TransportMerchCategory();
                $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                $departureTransportMerch->DepartureTime = $departureFlightDate;
                $departureTransportMerch->ArrivalTime = $departureFlightDateArrival;
                
                $departureTransportMerch->DepartureAirport = $airports[(string) $departureFlight->prop[1]]['IATA'];
                $departureTransportMerch->ReturnAirport = $airports[(string) $departureFlight->prop[2]]['IATA'];

                $departureTransportMerch->From = new TransportMerchLocation();
                $departureTransportMerch->From->City = $departurecity;

                $departureTransportMerch->To = new TransportMerchLocation();
                $departureTransportMerch->To->City = $destinationCity;

                $departureTransportItem = new DepartureTransportItem();
                $departureTransportItem->Merch = $departureTransportMerch;
                $departureTransportItem->Quantity = 1;
                $departureTransportItem->Currency = $offer->Currency;
                $departureTransportItem->UnitPrice = 0;
                $departureTransportItem->Gross = 0;
                $departureTransportItem->Net = 0;
                $departureTransportItem->InitialPrice = 0;
                $departureTransportItem->DepartureDate = $checkIn;
                $departureTransportItem->ArrivalDate = $checkIn;

                // return transport item
                $returnTransportMerch = new TransportMerch();
                $returnTransportMerch->Title = "Retur: ".$checkOutDateTime->format('d.m.Y');
                $returnTransportMerch->Category = new TransportMerchCategory();
                $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                $returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                $returnTransportMerch->DepartureTime = $returnFlightDate;
                $returnTransportMerch->ArrivalTime = $returnFlightDateArrival;

                $returnTransportMerch->DepartureAirport = $airports[(string) $returnFlight->prop[1]]['IATA'];
                $returnTransportMerch->ReturnAirport = $airports[(string) $returnFlight->prop[2]]['IATA'];

                $returnTransportMerch->From = new TransportMerchLocation();
                $returnTransportMerch->From->City = $destinationCity;

                $returnTransportMerch->To = new TransportMerchLocation();
                $returnTransportMerch->To->City = $departurecity;

                $returnTransportItem = new ReturnTransportItem();
                $returnTransportItem->Merch = $returnTransportMerch;
                $returnTransportItem->Quantity = 1;
                $returnTransportItem->Currency = $offer->Currency;
                $returnTransportItem->UnitPrice = 0;
                $returnTransportItem->Gross = 0;
                $returnTransportItem->Net = 0;
                $returnTransportItem->InitialPrice = 0;
                $returnTransportItem->DepartureDate = $checkOut;
                $returnTransportItem->ArrivalDate = $checkOut;

                // for identify purpose
                $departureTransportItem->Return = $returnTransportItem;

                // add items to offer
                $offer->Item = $room1;
                $offer->DepartureTransportItem = $departureTransportItem;
                $offer->ReturnTransportItem = $returnTransportItem;

                $offer->Items = [];
                if ($responseHotel[10]['priceTypes'][2]) {
                    $offer->Items[] = $this->getApiTransferItem($offer, new TransferCategory);
                }

                $offer->Items[] = $this->getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                
                $hotelInList = $response->get($hotel->Id);

                // if already present, add offer to hotel
                if ($hotelInList !== null) {
                    $hotelInList->Offers->put($offer->Code, $offer);
                    $response->put($hotelInList->Id, $hotelInList);

                } else {
                    $hotel->Offers = new OfferCollection();
                    $hotel->Offers->put($offer->Code, $offer);
                    $response->put($hotel->Id, $hotel);
                }
            }
        }
        return $response;
    }

    private function getFlightDepartures(): SimpleXMLElement
    {
        $file = 'flight-departures';
        $xmlString = Utils::getFromCache($this->handle, $file);

        if ($xmlString === null) {
            $url = $this->getFiles()['flightDepartures'];
            $xmlString = $this->request($url)->getBody();
            Utils::writeToCache($this->handle, $file, $xmlString, 7);
        }
       
        $datasXml = new SimpleXMLElement($xmlString);

        return $datasXml;
    }

    private function getAirports(): SimpleXMLElement
    {
        $file = 'airports';
        $xmlString = Utils::getFromCache($this->handle, $file);
        if ($xmlString === null) {
            $url = $this->getFiles()['airports'];
            $xmlString = $this->request($url)->getBody();
            Utils::writeToCache($this->handle, $file, $xmlString, 7);
        }
        
        $datasXml = new SimpleXMLElement($xmlString);

        return $datasXml;
    }

    private function getRegionsById(): array
    {
        $url = $this->getFiles()['regions'];
        $data = $this->getData($url);
        $result = [];
        foreach ($data->item as $region) {
            $result[(string)$region->id] =  [
                'regionName' => (string)$region->name,
                'countryId' => (string)$region->prop
            ];
        }
        return $result;
    }

    private function getData(string $url): SimpleXMLElement
    {
        $data = $this->request($url)->getBody();
        $datasXml = new SimpleXMLElement($data);
        return $datasXml;
    }

    public function getMinAcceptablerAndBId(string $arrivalCountryId, string $departureCityId)
    {
        $file = 'meals-' . $arrivalCountryId . '-' . $departureCityId;
        $json = Utils::getFromCache($this->handle, $file);
        if ($json === null) {
            $url = $this->apiUrl . '/v2/tours/pansions?countryId='.$arrivalCountryId.'&cityId='.$departureCityId;
            $json = $this->request($url)->getBody();
            Utils::writeToCache($this->handle, $file, $json, 7);
        }

        $response = json_decode(
            $json, true
        );

        if (!isset($response['rAndBs'])) {
            Log::error($this->handle . ': rAndBs does not exist.');
            return self::MINIMUM_MEAL_ID;
        }

		$acceptables = $response['rAndBs'];

		$min = null;
        $minId = null;
		
		foreach ($acceptables as $acceptable) {
			// set minimum if not set
			if ($min === null) {
				$min = $acceptable['weight'];
                $minId = $acceptable['rAndBId'];
            }
			
			if ($min > $acceptable['weight']) {
				$min = $acceptable['weight'];
                $minId = $acceptable['rAndBId'];
			}
		}		
		return $minId;
    }

    public function getMinAcceptableRating(string $arrivalCountryId, string $departureCityId)
	{
        $file = 'ratings-' . $arrivalCountryId . '-' . $departureCityId;
        $json = Utils::getFromCache($this->handle, $file);
        $url = '';
        if ($json === null) {
            $url = $this->apiUrl . '/v2/tours/hotelclasses?countryId='.$arrivalCountryId.'&cityId='.$departureCityId;
            $json = $this->request($url)->getBody();
            Utils::writeToCache($this->handle, $file, $json, 7);
        }

        $response = json_decode(
            $json, true
        );

        if (empty($response['hotelClasses'])) {
            Log::warning($this->handle . ': hotelClasses does not exist for ' . $url);
            return self::MINIMUM_RATING_ID;
        }

		$acceptableHotelRatings = $response['hotelClasses'];
		$min = null;
        $minId = null;
		
		foreach ($acceptableHotelRatings as $hotelClass) {
			// set minimum if not set
			if ($min === null) {
				$min = $hotelClass['weight'];
                $minId = $hotelClass['classId'];
            }
			
			if ($min > $hotelClass['weight']){
				$min = $hotelClass['weight'];
                $minId = $hotelClass['classId'];
			}
		}		
		return $minId;
	}

    public function getApiTransferItem(Offer $offer, TransferCategory $category): TransferItem
	{
		$transferMerch = new TransferMerch();
		$transferMerch->Code = uniqid();
		$transferMerch->Category = $category;
		$transferMerch->Title = TransferMerch::TITLE;
		$transferItem = new TransferItem();
		$transferItem->Merch = $transferMerch;
		$transferItem->Currency = $offer->Currency;
		$transferItem->Quantity = 1;
		$transferItem->UnitPrice = 0;
		$transferItem->Availability = TransferItem::AVAILABILITY_YES;
		$transferItem->Gross = 0;
		$transferItem->Net = 0;
		$transferItem->InitialPrice = 0;
		return $transferItem;
	}

    public function getApiAirpotTaxesItem(Offer $offer, AirportTaxesCategory $category): AirportTaxesItem
	{
		$airportTaxesMerch = new AirportTaxesMerch();
		$airportTaxesMerch->Title = AirportTaxesMerch::TITLE;
		$airportTaxesMerch->Code = uniqid();
		$airportTaxesMerch->Category = $category;
		$airportTaxesItem = new AirportTaxesItem();
		$airportTaxesItem->Merch = $airportTaxesMerch;
		$airportTaxesItem->Currency = $offer->Currency;
		$airportTaxesItem->Quantity = 1;
		$airportTaxesItem->UnitPrice = 0;
		$airportTaxesItem->Availability = AirportTaxesItem::AVAILABILITY_YES;
		$airportTaxesItem->Gross = 0;
		$airportTaxesItem->Net = 0;
		$airportTaxesItem->InitialPrice = 0;
		return $airportTaxesItem;
	}

    private function getAccommodationId(AvailabilityFilter $filter)
	{
		$adults = $filter->rooms->get(0)->adults;
		$children = $filter->rooms->get(0)->children;
		
		$accommodationTypes = $this->getAccommodationTypes();
		
		// build accommodation string
		$accString = '';
		
		if ($adults == 1) {
			$accString = 'SGL';
			
			if ($children <= 2) {
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString .= '+2CHD';
			}
			else
				$accString = '';
		} else if ($adults == 2) {
			$accString = 'DBL';
			
			if ($children <= 2) {
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString .= '+2CHD';
			}
			else
				$accString = '';
		} else if ($adults == 3) {
			$accString = 'DBL+EXB';
			
			if ($children <= 2) {
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString = 'TRPL+2CHD';
			}
			else
				$accString = '';
		} else if (($adults > 3) && ($adults <= 8)) {
			$accString = $adults . ' PAX';
			
			if ($children <= 2) {
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString .= '+2CHD';
			} else {
				$accString = '';
            }
			
			if (($children > 0) && ($adults == 8)) {
                $accString = '';
            }
			
			if (($adults == 7) && ($children > 1)) {
                $accString = '';
            }
		}
		// search for code in accommodation types
		$accommodation = array_filter($accommodationTypes, fn(array $room) => $room['name'] === $accString);
        $accommodationId = empty($accommodation) ? '' : $accommodation[array_key_first($accommodation)]['id'];
		return $accommodationId;
	}

    public function getAccommodationTypes(): array
	{
        $file = 'accommodation-types';
        $json = Utils::getFromCache($this->handle, $file);
        if ($json === null) {
            $url = $this->apiUrl . '/v2/tours/accommodations';
            $json = $this->request($url)->getBody();
            Utils::writeToCache($this->handle, $file, $json, 7);
        }

        $accommodations = json_decode($json, true);

		return $accommodations;
	}

    public function getFiles(): array
	{
        $file = 'xml-files';
        $xml = Utils::getFromCache($this->handle, $file);

        if ($xml === null) {
            $body = [
                'auth' => [
                    'user' => $this->usernameOld,
                    'parola' => $this->passwordOld
                ],
                'cerere' => [
                    'serviciu' => 'fileList'
                ]
            ];

            if ($this->handle === 'localhost-tezc') {
                $url = 'http://localhost/travelfuse-integrations/test-alladyn/xml/';
            } else {
                $url = 'https://data.tez-tour.ro/xml/';
            }

            $response = $this->request($url, 'POST', [
                'body' => http_build_query($body),
                'headers' => [
                    'Content-Type: application/x-www-form-urlencoded',
                ]
            ]);
            $xml = $response->getBody();
            Utils::writeToCache($this->handle, $file, $xml, 7);
        }
        if ($this->services === null) {
            
            $filesXml = simplexml_load_string($xml);
            
            $services = [];
            
            foreach ($filesXml->DataSet->Body->files->service as $serviceFile)
            {
                $_serviceName = (string)$serviceFile->name;
                $_serviceUrl = (string)$serviceFile->url;
                $services[$_serviceName] = $_serviceUrl;	
            }
            $this->services = $services;
        }
		return $this->services;
	}

    private function getSessionId(): string
    {
        $username = env('ALLADYN_BOOKING_USERNAME');
        $password = env('ALLADYN_BOOKING_PASSWORD');
        $url = $this->apiUrl . '/v2/xmlgate/auth?j_login='.$username.'&j_passwd='.$password;
        $response = $this->request($url)->getBody();
        $xml = new SimpleXMLElement($response);
        
        return $xml->sessionId;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        AlladynChartersValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $id = $this->getSessionId();

        $bookingLink = $filter->Items->first()->Offer_InitialData;
        
        $parts = parse_url($bookingLink);
        parse_str($parts['query'], $queryParamsArray);
        
        // forming order
        $url = $this->apiUrl . '/v2/xmlgate/orderFromOfferId';

        $params = [
            'resTariffs' => $queryParamsArray['cResId'],
            'flyTariffs' => $queryParamsArray['cFlyIds'],
            'priceOfferId' => $queryParamsArray['priceOfferId'],
            'tariffDepCityId' => $queryParamsArray['depCity'],
            'firstTransferType' => $queryParamsArray['ftt'],
            'lastTransferType' => $queryParamsArray['ltt'],
            'resortArrivalRegionId' => $queryParamsArray['rar'],
            'resortDepartureRegionId' => $queryParamsArray['rdr'],
            'aid' => $id,
            'spoKindId' => $queryParamsArray['sk'] 
        ];
        
        $query = '?' . http_build_query($params);

        $responseString = $this->request($url . $query)->getBody();
        $responseXml = new SimpleXMLElement($responseString);

        // get passengers
        $passengers = $post['args'][0]['Items'][0]['Passengers'];
        
        $i = 0;
        foreach($passengers as $passenger) {
            if (!empty($passenger['Firstname'])) {
                $responseXml->Tourist[$i]->name = $passenger['Firstname'];
                $responseXml->Tourist[$i]->surname = $passenger['Lastname'];
                $date = new DateTime($passenger['BirthDate']);
                $responseXml->Tourist[$i]->birthday = $date->format('d.m.Y');
                $responseXml->Tourist[$i]->sex = $passenger['Gender'];
                $responseXml->Tourist[$i]->nationality = self::ROMANIA_ID;
                $i++;
            }
        }

        // check flights, remove extra tickets
        $i = 0;
        $extraTickets = [];
        foreach ($responseXml->Ticket as $ticket) {
            if ((string) $ticket->flightDeparture !== $filter->Items->first()->Offer_departureFlightId
                && (string) $ticket->flightDeparture !== $filter->Items->first()->Offer_returnFlightId
            ) {
                $extraTickets[] = $i;
                $i++;
            }
        }

        foreach ($extraTickets as $ticket) {
            unset($responseXml->Ticket[0]);
        }

        if (count($responseXml->Ticket) !== 2) {
            throw new Exception('flights not found!');
        }
        
        $xmlString = $responseXml->asXML();

        $urlBooking = $this->apiUrl . '/v2/xmlgate/book?aid='.$id;

        // booking
        $responseStringBook = $this->request($urlBooking, 'POST', [
            'body' => $xmlString
        ])->getBody();

        $responseXmlBooking = new SimpleXMLElement($responseStringBook);

        $response = new Booking();
        if (isset($responseXmlBooking->orderId)) {
            $response->Id = $responseXmlBooking->orderId;
            //$response->rawResp = $responseStringBook;
        } else {
            throw new Exception('Comanda nu a putut fi procesata!');
        }

        return [$response, $responseStringBook];
    }

    public function apiGetHotels(): []
    {
        //Validator::make()->validateUsernameAndPassword($this->post);
        $hotels = [];
        $countries = $this->apiGetCountries();

        // get from cache
        $json = $this->getHotelsDetailsCache();
        $hotelDetailsList = json_decode($json, true);

        foreach($hotelDetailsList as $detailsResponse) {
            $responseHotelDetails = json_decode($detailsResponse, true)['hotel'];

            $country = new Country();

            $countryId = $responseHotelDetails['country']['id'];
            $country = $countries->first(fn(Country $countryCol) => $countryId === $countryCol->Id);

            $region = new Region();
            $region->Id = $responseHotelDetails['region']['id'];
            $region->Name = $responseHotelDetails['region']['title'];
            $region->Country = $country;

            // $address->City
            $city = new City();
            $city->Id = $responseHotelDetails['region']['id'];
            $city->Name = $responseHotelDetails['region']['title'];
            $city->Country = $country;
            $city->County = $region;

            // $hotel->Address
            $address = new HotelAddress();
            $address->Latitude = $responseHotelDetails['coordinates']['lat'];
            $address->Longitude = $responseHotelDetails['coordinates']['lng'];
            $address->Details = $responseHotelDetails['address'];
            $address->City = $city;

            $images = new HotelImageGalleryItemCollection();

            foreach ($responseHotelDetails['images'] as $responseImage) {
                $image = new HotelImageGalleryItem();
                $image->RemoteUrl = $responseImage['orig'];
                
                $image->Alt = $responseImage['title'] ?? null;
                $images->add($image);
            }

            $imageGallery = new HotelImageGallery();
            $imageGallery->Items = $images;

            $content = new HotelContent();
            $content->ImageGallery = $imageGallery;
            $content->Content = $responseHotelDetails['description'];

            $facilities = new FacilityCollection();

            foreach ($responseHotelDetails['facilities'] as $facilityResponse) {
                if (isset($facilityResponse['list'])) {
                    foreach ($facilityResponse['list'] as $facilitySub) {
                        $facility = new Facility();
                        $facility->Id = md5($facilitySub['name']);//preg_replace('/\s+/', '', $facilitySub['name']);
                        $facility->Name = $facilitySub['name'];
                        $facilities->put($facility->Id, $facility);
                    }
                }
            }

            $hotel = new Hotel();
            $hotel->Id = $responseHotelDetails['id'];
            $hotel->Name = $responseHotelDetails['title'];
            $hotel->Stars = $responseHotelDetails['category']['id'];
            $hotel->Facilities = $facilities;
            $hotel->Content = $content;
            $hotel->Address = $address;
            $hotel->WebAddress = $responseHotelDetails['contacts']['web'] ?? null;

            $hotels->add($hotel);
        }

        return $hotels;
    }

    public function getHotelsDetailsCache(): string
    {   
        set_time_limit(1200);
        $hotelsDetailsCache = Utils::getFromCache($this->handle, 'hotels-details');
        
        if ($hotelsDetailsCache === null) {// creating cache

            $url = $this->apiUrl . '/v2/tours/depcities';
            $response = json_decode($this->request($url)->getBody(), true);

            if ($response === null) {
                throw new Exception('Empty cities response');
            }

            if (!empty($response['error'])) {
                throw new Exception(json_encode($response['error']));
            }
            
            $filteredHotelList = [];
            foreach ($response as $departureCity) {
                if ($departureCity['country'] === 'Romania') {
                    $urlDestCountries = $this->apiUrl . '/v2/tours/arrcountries?cityId='.$departureCity['id'];
                    $respBody = $this->request($urlDestCountries)->getBody();
                    $responseDestCountries = json_decode($respBody, true);
                    
                    if (!empty($responseDestCountries['error'])) {
                        continue;
                    }
                    if ($responseDestCountries === null) {
                        throw new Exception('Empty destination countries response at '.$urlDestCountries);
                    }

                    foreach ($responseDestCountries as $destinationCountry) {
                        $country = new Country();
                        $country->Id = $destinationCountry['id'];
                        $country->Name = $destinationCountry['name'];
                        $country->Code = AlladynChartersUtils::getCountryCodeByName($country->Name);
                        
                        // getting hotels
                        $urlHotelList = $this->apiUrl . '/v2/tours/list?countryId='.$country->Id.'&cityId='.$departureCity['id'];

                        $hotelList = json_decode($this->request($urlHotelList)->getBody(), true)['hotels'];

                        foreach ($hotelList as $hotelResponse) {
                            $filteredHotelList[$hotelResponse['hotelId']] = $hotelResponse;
                        }
                    }
                }
            }

            $request = new AsyncRequest($this->password);
            foreach ($filteredHotelList as $hotelResponse) {
                $urlHotelDetails = $this->apiUrl . '/v2/tours/content?hotelId='.$hotelResponse['hotelId'] . '&locale=ro';
                $request->getAsyncWithUrlIndex($urlHotelDetails);
            }
            //todo: add to requests
            $hotelDetailsArray = $request->getResponses();

            $hotelsDetailsCache = json_encode_pretty($hotelDetailsArray);

            Utils::writeToCache($this->handle, 'hotels-details', $hotelsDetailsCache, 7);

        }
        return $hotelsDetailsCache;
    }

    // if not Romania, cityId will be the region
    private function getMappedAirportsByCity(): array
    {
        $airports = $this->getAirports();
        $mapped = [];
        foreach ($airports->item as $airport) {

            if ($airport->prop[0] == '156505') {// Romania
                $mapped[(string)$airport->id] = ['countryId' => (string)$airport->prop[0], 'cityId' => (string)$airport->prop[2], 'IATA' => (string)$airport->prop[3]];
            } else {
                $mapped[(string)$airport->id] = [
                    'countryId' => (string)$airport->prop[0],
                    'cityId' => (string)$airport->prop[1],
                    'realCityId' => (string)$airport->prop[2],
                    'IATA' => (string)$airport->prop[3]
                ];
            }
        }
        return $mapped;
    }


    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    {
        $cities = $this->apiGetCities();

        $transports = [];
        if ($filter->type !== AvailabilityDatesFilter::CHARTER) {
            return $transports;
        }
        
        $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;

        $flights = $this->getFlightDepartures();
        //$airports = $this->getAirports();

        $mappedAirports = $this->getMappedAirportsByCity();
        $grouped = [];

        foreach ($flights->item as $flight) {
            
            // if departure airport is in departure city and arrival airport is in destination city
            if ($flight->prop[6] == 'true' 
                //&& $dateLeave <= $reference
                && $mappedAirports[(string)$flight->prop[1]]['countryId'] === self::ROMANIA_ID
            ) {
                $departureDate = (string)$flight->prop[3];

                $key = $mappedAirports[(string)$flight->prop[1]]['cityId'].$mappedAirports[(string)$flight->prop[2]]['cityId'];

                if (array_key_exists($key, $grouped)) {
                    $datesArray = $grouped[$key];
                    $datesArray[] = $departureDate; // add to array

                    $grouped[$key] = $datesArray;
                } else {
                    $grouped[$key] =  [
                        'departureAirportId' => (string)$flight->prop[1],
                        'arrivalAirportId' => (string)$flight->prop[2],
                        $departureDate
                    ];
                }
            }
        }

        $urls = [];
        $urlsReturn = [];
        foreach ($grouped as $dates) {
            $availDates = new AvailabilityDates();
            $availDates->Content = new TransportContent();
            $availDates->TransportType = $transportType;
            $departureAirportId = $dates['departureAirportId'];
            $arrivalAirportId = $dates['arrivalAirportId'];

            $transportDateCollection = new TransportDateCollection();

            $city = $cities->first(fn(City $city) => $city->Id === $mappedAirports[$dates['arrivalAirportId']]['cityId']);

            // destination not in available countries
            if ($city === null) {
                continue;
            }

            $cityFrom = $cities->first(fn(City $city) => $city->Id === $mappedAirports[$dates['departureAirportId']]['cityId']);

            // departure city not in the list
            if ($cityFrom === null) {
                continue;
            }
            
            foreach ($dates as $key => $date) {
                if ($key === 'arrivalAirportId' || $key === 'departureAirportId') {
                    continue;
                }

                // if dep date not in array, skip
                $url = $this->apiUrl . '/v2/tours/flightcalendar?cityId='. $cityFrom->Id . '&countryId='.$city->Country->Id;
                
                // check url, if already, skip the call and get from array

                $available = [];

                if (isset($urls[$url])) {
                    $available = $urls[$url];
                } else {
                    $response = json_decode($this->request($url)->getBody(), true);
                    if (!empty($response['error'])) {
                        throw new Exception(json_encode($response['error']));
                    }
                    $available = $response['available'];
    
                    $urls[$url] = $available;
                }
                
                if (!in_array((new DateTime($date))->format('Y-m-d'), $available)) {
                    continue;
                }

                $firstDate = (new DateTime($date))->setTime(0,0);
                $maximumDateTime = (new DateTime($date))->add(new DateInterval('P30D'))->setTime(0,0);

                $transportDate = new TransportDate();
                $transportDate->Date = $date;

                // for each date we have nights
                $dateNightCollection = new DateNightCollection();

                // get return dates, max 30 days
                foreach ($flights->item as $flightReturn) {

                    $dateLeave = (new DateTime($flightReturn->prop[3]))->setTime(0,0);

                    if ((string)$flightReturn->prop[6] == 'true'
                        && $dateLeave <= $maximumDateTime
                        && $dateLeave > $firstDate
                        && $arrivalAirportId === (string)$flightReturn->prop[1]
                        && $departureAirportId === (string)$flightReturn->prop[2]
                    ) {

                        // if dep date not in array, skip
                        $urlReturn = $this->apiUrl . '/v2/tours/flightcalendar?cityId='
                            . $mappedAirports[(string) $flightReturn->prop[1]]['realCityId']. '&countryId='
                            . $mappedAirports[(string) $flightReturn->prop[2]]['countryId'];


                        if (isset($urlsReturn[$urlReturn])) {
                            $availableReturn = $urlsReturn[$urlReturn];
                        } else {
                            $responseReturn = json_decode($this->request($urlReturn)->getBody(), true);
        
                            if (!empty($responseReturn['error'])) {
                                throw new Exception(json_encode($responseReturn['error']));
                            }
                            $availableReturn = $responseReturn['available'];
            
                            $urlsReturn[$urlReturn] = $availableReturn;
                        }

                        if (!in_array((new DateTime((string) $flightReturn->prop[3]))->format('Y-m-d'), $availableReturn)) {
                            continue;
                        }

                        // get nights for each departure date
                        $dateNight = new DateNight();
                        
                        $dateNight->Nights = $dateLeave->diff($firstDate)->days;

                        $dateNightCollection->put($dateNight->Nights, $dateNight);
                    }
                }
                if (count($dateNightCollection) == 0) {
                    continue;
                }
                $transportDate->Nights = $dateNightCollection;
                $transportDateCollection->add($transportDate);
            }

            if (count($transportDateCollection) == 0) {
                continue;
            }

            $availDates->Dates = $transportDateCollection;

            $transportCityFrom = new TransportCity();
            $transportCityFrom->City = $cityFrom;
            $availDates->From = $transportCityFrom;

            $transportCityTo = new TransportCity();
            $transportCityTo->City = $city;
            $availDates->To = $transportCityTo;

            $availDates->Id = $transportType . "~city|" . $cityFrom->Id . "~city|" . $city->Id;
            $transports->add($availDates);
        }
        return $transports;
    }

    public function request(string $url, string $method = HttpClient::METHOD_GET, array $options = []): ResponseInterface
    {
        if (!isset($options['headers'])) {
            $options['headers'] = [
                'Content-Type' => 'application/json',
                'Api-Key' => $this->password,
            ];
        }

        $httpClient = HttpClient::create();
        $response = $httpClient->request($method, $url, $options);
        $this->showRequest($method, $url, $options, $response->getBody(), $response->getStatusCode());

        // if ($this->request->getPostParam('get-raw-data')) {
        //     $response->pretty();
        //     $this->responses->add($response);
        // }
        
        return $response;
    }

}

class AsyncRequest
{
    private array $multiCurl = [];
    private array $result = [];
    private CurlMultiHandle $mh;
    private int $index = 0;
    private int $tries = 0;
    private $password;

    public function __construct(string $password)
    {
        $this->password = $password;
        $this->mh = curl_multi_init();
    }

    public function getAsync(string $url): void
    {
        $this->multiCurl[$this->index] = curl_init();
        curl_setopt($this->multiCurl[$this->index], CURLOPT_URL,$url);
        curl_setopt($this->multiCurl[$this->index], CURLOPT_HTTPHEADER, ['Api-Key: ' . $this->password]);
        curl_setopt($this->multiCurl[$this->index], CURLOPT_RETURNTRANSFER,1);
        curl_setopt($this->multiCurl[$this->index], CURLOPT_TIMEOUT, 100);
        curl_multi_add_handle($this->mh, $this->multiCurl[$this->index]);
        $this->index++;
    }

    public function getAsyncWithUrlIndex(string $url): void
    {
        if (!isset($this->multiCurl[$url])) {
            $this->multiCurl[$url] = curl_init();
            curl_setopt($this->multiCurl[$url], CURLOPT_URL,$url);
            curl_setopt($this->multiCurl[$url], CURLOPT_HTTPHEADER, ['Api-Key: ' . $this->password]);
            curl_setopt($this->multiCurl[$url], CURLOPT_RETURNTRANSFER,1);
            curl_setopt($this->multiCurl[$url], CURLOPT_TIMEOUT, 100);
            curl_multi_add_handle($this->mh, $this->multiCurl[$url]);
        }
    }

    public function resetCurl(): void
    {
        $this->multiCurl = [];
    }

    public function getResponses()
    {
        $this->tries++;
        do {
            $mrc = curl_multi_exec($this->mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            curl_multi_select($this->mh);
            do {
                $mrc = curl_multi_exec($this->mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        $emptyResponses = [];
        foreach ($this->multiCurl as $index => $ch) {
            $this->result[$index] = curl_multi_getcontent($ch); // get the content
            $result = json_decode($this->result[$index], true);
            $ok = isset($result['success']) && $result['success'] === true;

            if (isset($result['success']) && $result['success'] === false) {
                throw new Exception($result['message']);
            }
            
            if (!$ok) {
                unset($this->result[$index]);
                $emptyResponses[] = $index;
            }
            
            if ($this->tries > 20) {
                throw new Exception('Too many tries!');
            }
            curl_multi_remove_handle($this->mh, $ch);
        }

        if (count($emptyResponses) > 0) {
            $this->resetCurl();
        }
        
        foreach ($emptyResponses as $empty) {
            $this->getAsyncWithUrlIndex($empty);
        }
        if (count($emptyResponses) > 0) {
            $this->getResponses();
        }

        return $this->result;
    }

    public function __destruct()
    {
        if (isset($this->mh)) {
            curl_multi_close($this->mh);
        }
    }
}

