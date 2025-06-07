<?php

namespace Integrations\TourVisio;

use App\Entities\Availability\AirportTaxesCategory;
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
use App\Entities\Availability\TransferCategory;
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
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\Passenger;
use App\Handles;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\[];
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\HttpClient2;
use App\Support\Log;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use Utils\Utils;

class TourVisioApiService extends AbstractApiService
{
    private const LOCATION_TYPE_COUNTRY = 1;
    private const LOCATION_TYPE_CITY = 2;
    private const LOCATION_TYPE_TOWN = 3;
    private const LOCATION_TYPE_VILLAGE = 4;
    private const PRODUCT_TYPE_HOTEL = 2;
    private const PRODUCT_TYPE_HOLIDAY_PACKAGE = 1;
    private const AUTOCOMPLETE_TYPE_CITY = 1;
    private const AUTOCOMPLETE_TYPE_HOTEL = 2;
    private const TEST_HANDLE = 'localhost-fibula_v2';

    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    {
        $file = 'availability-dates-charter';
        $availabilityDatesJson = Utils::getFromCache($this, $file);

        if ($availabilityDatesJson === null) {

            $availabilityDatesCollection = [];

            $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;
            $token = $this->getToken();
            $cities = $this->apiGetCities();
            $regions = $this->apiGetRegions();

            $httpClient = HttpClient::create();

            // get departures
            $options['body'] = json_encode([
                'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
            ]);
            $options['headers'] = [
                'Authorization' => 'Bearer ' . $token
            ];

            $url = $this->apiUrl . '/api/productservice/getdepartures';

            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
            $responseData = json_decode($responseObj->getBody(), true);
            //$this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

            $depLocations = $responseData['body']['locations'];
            $departureLocations = [];
            $countryMap = CountryCodeMap::getCountryCodeMap();
            $romania = new Country();
            foreach ($depLocations as $location) {
                if ($location['type'] === self::LOCATION_TYPE_COUNTRY && $location['name'] === 'ROMANIA') { // country
                    $romania->Id = $location['id'];
                    $romania->Code = $countryMap[ucfirst(strtolower($location['name']))];
                    $romania->Name = $location['name'];
                    break;
                }
            }

            $httpClient = HttpClient2::create();
            $requests = [];
            foreach ($depLocations as $departureLocation) {

                if ($departureLocation['countryId'] !== $romania->Id || $departureLocation['type'] === self::LOCATION_TYPE_COUNTRY) {
                    continue;
                }

                $departureLocations = [
                    'Type' => $departureLocation['type'],
                    'Id' => $departureLocation['id']
                ];
                
                $departureCity = $cities->get($departureLocation['id'] . '-' . $departureLocation['type']);

                // get arrivals
                $options['body'] = json_encode([
                    'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
                    'DepartureLocations' => [$departureLocation]
                ]);

                $url = $this->apiUrl . '/api/productservice/getarrivals';

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                $requests[] = [$responseObj, $options, $departureCity, $departureLocations];
            }
            
            $httpClient = HttpClient2::create();
            
            $datesRequests = [];
            foreach ($requests as $request) {

                $responseObj = $request[0];
                $options = $request[1];
                $departureCity = $request[2];
                $departureLocations = $request[3];

                $responseData = json_decode($responseObj->getBody(), true);

                //$this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

                if (!isset($responseData['body'])) {
                    continue;
                }

                $arrLocations = $responseData['body']['locations'];

                foreach ($arrLocations as $arrLocation) {
                    if ($arrLocation['type'] === self::LOCATION_TYPE_COUNTRY) {
                        continue;
                    }
                    
                    $arrivalLocations = [
                        'Type' => $arrLocation['type'],
                        'Id' => $arrLocation['id']
                    ];
                    $destinationCity = $cities->get($arrLocation['id'] . '-' . $arrLocation['type']);
                    if ($destinationCity === null) {
                        continue;
                    }
                    
                    $destinationRegion = $regions->get($arrLocation['id'] . '-' . $arrLocation['type']);
                    if ($destinationRegion === null) {
                        //continue;
                    }
                    
                    // get checkin dates
                    $options['body'] = json_encode([
                        'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
                        'DepartureLocations' => [$departureLocations],
                        'ArrivalLocations' => [$arrivalLocations],
                        'IncludeSubLocations' => true
                    ]);

                    $url = $this->apiUrl . '/api/productservice/getcheckindates';

                    $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                    $responseObj->getBody();
                    $datesRequests[] = [$responseObj, $options, $arrLocation, $departureCity, $departureLocations, $arrivalLocations];
                }
            }
            Log::debug('get content 1 for '. count($datesRequests));
            $i = 0;
            
            $checkInDatesSet = [];

            foreach ($datesRequests as $datesRequest) {
                $i++;
                Log::debug($i);
                $options = $datesRequest[1];
                $responseObj = $datesRequest[0];
                $arrLocation = $datesRequest[2];
                $departureCity = $datesRequest[3];
                $departureLocations = $datesRequest[4];
                $arrivalLocations = $datesRequest[5];

                $destinationCity = $cities->get($arrLocation['id'] . '-' . $arrLocation['type']);

                $id = $transportType . "~city|" . $departureCity->Id . "~city|" . $destinationCity->Id;
                $transportCityFrom = new TransportCity();
                $transportCityFrom->City = $departureCity;
                $transportCityTo = new TransportCity();
                $transportCityTo->City = $destinationCity;
                $availabilityDates = new AvailabilityDates();
                $availabilityDates->Id = $id;
                $availabilityDates->From = $transportCityFrom;
                $availabilityDates->To = $transportCityTo;
                $availabilityDates->TransportType = $transportType;
                $availabilityDates->Content = new TransportContent();

                $responseData = json_decode($responseObj->getBody(), true);

                $checkInDates = $responseData['body']['dates'];
                $checkInDatesSet[] = [$checkInDates, $availabilityDates, $departureLocations, $arrivalLocations];
            }

            $httpClient = HttpClient2::create();
            $transportRequests = [];
            $j = 0;
            $i = 0;

            $count = 0;
            foreach ($checkInDatesSet as $checkInDateSet) {
                $checkInDates = $checkInDateSet[0];
                $count += count($checkInDates);
            }
            Log::debug('requests '. $count);

            foreach ($checkInDatesSet as $checkInDateSet) {

                $checkInDates = $checkInDateSet[0];
                /** @var AvailabilityDates */
                $availabilityDates = $checkInDateSet[1];
                $departureLocations = $checkInDateSet[2];
                $arrivalLocations = $checkInDateSet[3];
  
                $checkInDatesWithNights = [];

                $j++;
                Log::debug('get content '.$j.' for '. count($checkInDates));

                foreach ($checkInDates as $checkInDate) {
 
                    $transportDate = new TransportDate();
                    $transportDate->Date = $checkInDate;

                    // get nights
                    $options['body'] = json_encode([
                        'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
                        'DepartureLocations' => [$departureLocations],
                        'ArrivalLocations' => [$arrivalLocations],
                        'IncludeSubLocations' => true,
                        'CheckIn' => $checkInDate
                    ]);
            
                    $url = $this->apiUrl . '/api/productservice/getnights';

                    $i++;
                    Log::debug($i);
                    $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                    $responseObj->getBody();
                    $checkInDatesWithNights[] = [$responseObj, $transportDate];

                }
                $transportRequests[] = [$availabilityDates, $checkInDatesWithNights];

            }


            foreach ($transportRequests as $transportRequest) {


                $availabilityDates = $transportRequest[0];
                $checkInDatesWithNights = $transportRequest[1];



                $transportDateCollection = new TransportDateCollection();
                foreach ($checkInDatesWithNights as $checkInDateWithNights) {
                    $i++;
                    Log::debug($i);
                    $responseObj = $checkInDateWithNights[0];
                    $transportDate = $checkInDateWithNights[1];

                    $responseData = json_decode($responseObj->getBody(), true);
                    //$this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

                    $nightsArr = $responseData['body']['nights'];

                    if (count($nightsArr) === 0) {
                        continue;
                    }

                    $nights = new DateNightCollection();
                    foreach ($nightsArr as $nightInt) {
                        $night = new DateNight();
                        $night->Nights = $nightInt;
                        $nights->put($nightInt, $night);
                    }
                    $transportDate->Nights = $nights;

                    $transportDateCollection->put($transportDate->Date, $transportDate);
                }

                if (count($transportDateCollection) === 0) {
                    continue;
                }
                $availabilityDates->Dates = $transportDateCollection;
                $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
            }
            
                
            $data = json_encode_pretty($availabilityDatesCollection);
            Utils::writeToCache($this, $file, $data);
        } else {
            $ad = json_decode($availabilityDatesJson, true);
            $availabilityDatesCollection = ResponseConverter::convertToCollection($ad, array::class);
        }

        return $availabilityDatesCollection;
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

    private function getToken(): ?string
    {
        $httpClient = HttpClient::create();

        $optionsLogin['body'] = json_encode([
            'Agency' => $this->apiContext,
            'User' => $this->username,
            'Password' => $this->password
        ]);

        $url = $this->apiUrl . '/api/authenticationservice/login';

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $optionsLogin);

        $this->showRequest(HttpClient::METHOD_POST, $url, $optionsLogin, $responseObj->getBody(), $responseObj->getStatusCode());
        $response = json_decode($responseObj->getBody(), true);

        if (!empty($response['header']['success'])) {
            return $response['body']['token'];
        } else {
            return null;
        } 
    }

    public function apiTestConnection(): bool
    {
        $token = $this->getToken();

        if ($token === null) {
            return false;
        }
        return true;
    }

    public function apiGetCities(?CitiesFilter $params = null): array
    {
        Validator::make()->validateAllCredentials($this->post);

        $file = 'cities';
        $citiesJson = Utils::getFromCache($this, $file);

        if ($citiesJson === null || ($params && $params->clearCache)) {
            $cities = [];
            $token = $this->getToken();

            $httpClient = HttpClient::create();

            // get departures
            $options['body'] = json_encode([
                'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
            ]);
            $options['headers'] = [
                'Authorization' => 'Bearer ' . $token
            ];

            $url = $this->apiUrl . '/api/productservice/getdepartures';

            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
            $responseData = json_decode($responseObj->getBody(), true);
            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

            $depLocations = $responseData['body']['locations'];

            $countryMap = CountryCodeMap::getCountryCodeMap();

            $countries = [];
            $romania = null;
            foreach ($depLocations as $location) {
                if ($location['type'] === self::LOCATION_TYPE_COUNTRY && $location['name'] === 'ROMANIA') { // country
                    $romania = new Country();
                    $romania->Id = $location['id'];
                    $romania->Code = $countryMap[ucfirst(strtolower($location['name']))];
                    $romania->Name = $location['name'];
                    $countries->put($romania->Id, $romania);
                }
            }

            $regions = [];
            foreach ($depLocations as $location) {
                if ($location['countryId'] !== $romania->Id || $location['type'] === self::LOCATION_TYPE_COUNTRY) {
                    continue;
                }

                $departureLocations = [
                    'Type' => $location['type'],
                    'Id' => $location['id']
                ];

                if (in_array($location['type'], [2, 3, 4])) { // city, town, village
                    $region = new Region();
                    $region->Id = $location['id'] . '-' . $location['type'];
                    $region->Name = $location['name'];
                    $region->Country = $romania;
                    $regions->put($region->Id, $region);

                    $city = new City();
                    $city->Id = $location['id'] . '-' . $location['type'];
                    $city->Name = $location['name'];
                    $city->Country = $romania;
                    $city->County = $region;
                    $cities->put($city->Id, $city);
                }

                // get arrivals
                $options['body'] = json_encode([
                    'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
                    'DepartureLocations' => [ $departureLocations ]
                ]);

                $url = $this->apiUrl . '/api/productservice/getarrivals';

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                $responseData = json_decode($responseObj->getBody(), true);
                
                $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

                if (!$responseData['header']['success']) {
                    continue;
                }

                $arrLocations = $responseData['body']['locations'];

                $tempCities = [];

                $invalidCountryFound = false;
                // get countries - charter
                foreach ($arrLocations as $arrLocation) {
                    if ($arrLocation['type'] === self::LOCATION_TYPE_COUNTRY) {
                        $country = new Country();
                        $country->Id = $arrLocation['id'];
                        $countryNameResp = $arrLocation['name'];
                        $countryNameUc = ucfirst(strtolower($countryNameResp));

                        if (!isset($countryMap[$countryNameResp])) {
                            if (!isset($countryMap[$countryNameUc])) {
                                Log::warning($this->handle . ': add country ' . $countryNameUc);
                                $invalidCountryFound = true;
                                continue;
                            } else {
                                $country->Code = $countryMap[$countryNameUc];
                            }

                        } else {
                            $country->Code = $countryMap[$countryNameResp];
                        }
                        
                        $country->Name = $arrLocation['name'];
                        $countries->put($country->Id, $country);
                    }
                }
                if ($invalidCountryFound) {
                    continue;
                }
                // get regions - charter
                foreach ($arrLocations as $arrLocation) {
                    if ($arrLocation['type'] === self::LOCATION_TYPE_CITY) {
                        $region = new Region();
                        $region->Id = $arrLocation['id'] . '-' . self::LOCATION_TYPE_CITY;
                        $region->Name = $arrLocation['name'];
                        $region->Country = $countries->get($arrLocation['countryId']);
                        $regions->put($region->Id, $region);
                    }
                }

                // get cities - charter
                foreach ($arrLocations as $arrLocation) {
                    if ($arrLocation['type'] === self::LOCATION_TYPE_CITY) {
                        $city = new City();
                        $city->Id = $arrLocation['id'] . '-' . $arrLocation['type'];
                        $city->Name = $arrLocation['name'];
                        $city->Country = $countries->get($arrLocation['countryId']);
                        $city->County = $regions->get($arrLocation['id'] . '-' . $arrLocation['type']);
                        $cities->put($city->Id, $city);
                    }

                    if ($arrLocation['type'] === self::LOCATION_TYPE_TOWN) {
                        $city = new City();
                        $city->Id = $arrLocation['id'] . '-' . $arrLocation['type'];
                        $city->Name = $arrLocation['name'];
                        $city->Country = $countries->get($arrLocation['countryId']);
                        $city->County = $regions->get($arrLocation['parentId'] . '-' . self::LOCATION_TYPE_CITY);
                        $tempCities[$arrLocation['id']] = $arrLocation;

                        $cities->put($city->Id, $city);
                    }
                }

                foreach ($arrLocations as $arrLocation) {
                    if ($arrLocation['type'] === 4) {
                        $city = new City();
                        $city->Id = $arrLocation['id'] . '-' . $arrLocation['type'];
                        $city->Name = $arrLocation['name'];
                        $city->Country = $countries->get($arrLocation['countryId']);
                        $regionId = $tempCities[$arrLocation['parentId']]['parentId'];
                        $region = $regions->get($regionId . '-' . self::LOCATION_TYPE_CITY);
                        $city->County = $region;

                        $cities->put($city->Id, $city);
                    }
                }
            }

            $url = $this->apiUrl . '/api/productservice/getarrivalautocomplete';

            foreach ($cities as $cityCharter) {
                $options['body'] = json_encode([
                    'ProductType' => self::PRODUCT_TYPE_HOTEL,
                    'Query' => $cityCharter->Name
                ]);
        
                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
                $responseData = json_decode($responseObj->getBody(), true)['body']['items'];

                foreach ($responseData as $data) {
                    if ($data['type'] === self::AUTOCOMPLETE_TYPE_CITY && $data['country']['id'] !== 'RO' && isset($data['state'])) {

                        $city = new City();
                        $city->Id = $data['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
                        $city->Name = $data['city']['name'];
                        $selectedCountry = $countries->first(fn(Country $c) => $c->Code === $data['country']['id']);
                        if ($selectedCountry === null) {
                            continue;
                        }
                        $city->Country = $selectedCountry;

                        $region = new Region();
                        $region->Id = $data['state']['id'] . '-' . self::LOCATION_TYPE_TOWN;
                        $region->Name = $data['state']['name'];
                        $region->Country = $city->Country;
                        $city->County = $region;

                        $cities->put($city->Id, $city);
                    }
                }
            }

            $options['body'] = json_encode([
                'ProductType' => self::PRODUCT_TYPE_HOTEL,
                'Query' => 'bulgaria'
            ]);
            $bulgaria = new Country();
            $bulgaria->Id = 'BG';
            $bulgaria->Code = $bulgaria->Id;
            $bulgaria->Name = 'Bulgaria';
    
            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
            $responseData = json_decode($responseObj->getBody(), true)['body']['items'];

            $regions = [];
            foreach ($responseData as $data) {
                if ($data['type'] === self::AUTOCOMPLETE_TYPE_CITY && $data['country']['id'] === $bulgaria->Id
                    && !isset($data['state']) && !isset($data['hotel'])
                ) {
                    $region = new Region();
                    $region->Id = $data['city']['id'] . '-' . self::LOCATION_TYPE_TOWN;
                    $region->Name = $data['city']['name'];
                    $region->Country = $bulgaria;
                    $regions->put($region->Id, $region);
                }
            }

            foreach ($regions as $regionToSearch) {
                $options['body'] = json_encode([
                    'ProductType' => self::PRODUCT_TYPE_HOTEL,
                    'Query' => $regionToSearch->Name
                ]);
                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                $arrData = json_decode($responseObj->getBody(), true);
                $responseData = $arrData['body']['items'];

                // get cities from this region
                foreach ($responseData as $data) {
                    if ($data['type'] === self::AUTOCOMPLETE_TYPE_CITY && $data['country']['id'] === $bulgaria->Id
                        && isset($data['state']) && !isset($data['hotel'])
                    ) {
                        $region = $regions->get($data['state']['id'] . '-' . self::LOCATION_TYPE_TOWN);
                        $city = new City();
                        $city->Id = $data['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
                        $city->Name = $data['city']['name'];
                        $city->Country = $bulgaria;
                        $city->County = $region;
                        $cities->put($city->Id, $city);
                    }
                }
            }

            // get extra
            $filePath = __DIR__ . '/extra-hotels/' . $this->handle;
            if (file_exists($filePath)) {
                $fileExtra = file_get_contents($filePath);
                if ($fileExtra) {
                    $hotelsArr = json_decode($fileExtra, true);

                    /** @var [] $hotels */
                    $hotels = ResponseConverter::convertToCollection($hotelsArr, []::class);

                    foreach ($hotels as $hotel) {
                        $city = $this->getCityForHotel($hotel, $countries);
                        if ($city === null) {
                            continue;
                        }
                        $cities->put($city->Id, $city);
                    }
                }
            }

            $data = json_encode_pretty($cities);
            Utils::writeToCache($this, $file, $data);
        } else {
            $citiesArray = json_decode($citiesJson, true);
            /** @var array $cities */
            $cities = ResponseConverter::convertToCollection($citiesArray, array::class);
        }

        return $cities;
    }

    public function apiGetRegions(): []
    {
        $cities = $this->apiGetCities();
        $regions = [];
        /** @var City $city */
        foreach ($cities as $city) {
            $regions->put($city->County->Id, $city->County);
        }

        return $regions;
    }

    private function getCityForHotel(Hotel $hotelToFind, array $countryList): ?City
    {
        $httpClient = HttpClient::create();
        $url = $this->apiUrl . '/api/productservice/getarrivalautocomplete';

        $options['body'] = json_encode([
            'ProductType' => self::PRODUCT_TYPE_HOTEL,
            'Query' => $hotelToFind->Name
        ]);
        $token = $this->getToken();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseData = json_decode($responseObj->getBody(), true)['body']['items'];

        $city = null;
        $hotelParam = explode('-', $hotelToFind->Id);
        foreach ($responseData as $data) {
            if ($data['type'] === self::AUTOCOMPLETE_TYPE_HOTEL) {
                $hotelId = $data['hotel']['id'];
                if ($hotelId === $hotelParam[0]) {
                    $country = $countryList->first(fn(Country $c) => $c->Code === $data['country']['id']);

                    if ($country === null) {
                        continue;
                    }

                    $region = new Region();
                    $region->Id = $data['state']['id'] . '-' . self::LOCATION_TYPE_TOWN;
                    $region->Name = $data['state']['name'];
                    $region->Country = $country;

                    $city = new City();
                    $city->Id = $data['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
                    $city->Name = $data['city']['name'];
                    $city->Country = $country;
                    $city->County = $region;
                    break;
                }
            }
        }

        if ($city === null) {
            // try hotel details
            $url = $this->apiUrl . '/api/productservice/getproductinfo';

            // get departures
            $options['body'] = json_encode([
                'productType' => self::PRODUCT_TYPE_HOTEL,
                'product' => $hotelParam[0],
                'culture' => 'en-US',
                'ownerProvider' => $hotelParam[1],
            ]);

            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
            $responseData = json_decode($responseObj->getBody(), true);
            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

            if (empty($responseData['body'])) {
                return null;
            }

            $responseHotel = $responseData['body']['hotel'];

            $locationId = '';
            $name = '';
            if (isset($responseHotel['village'])) {
                $locationId = $responseHotel['village']['id'] . '-' . self::LOCATION_TYPE_VILLAGE;
                $name = $responseHotel['village']['name'];
            } elseif (isset($responseHotel['town'])) {
                $locationId = $responseHotel['town']['id'] . '-' . self::LOCATION_TYPE_TOWN;
                $name = $responseHotel['town']['name'];
            } else {
                $locationId = $responseHotel['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
                $name = $responseHotel['city']['name'];
            }

            $country = $countryList->first(fn(Country $c) => $c->Code === $responseHotel['country']['id']);
            if ($country === null) {
                $country = $countryList->get($responseHotel['country']['id']);
                if ($country === null) {
                    return null;
                }
            }

            $region = new Region();
            $region->Id = $responseHotel['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
            $region->Name = $responseHotel['city']['name'];
            $region->Country = $country;

            $city = new City();
            $city->Id = $locationId;
            $city->Name = $name;
            $city->County = $region;
            $city->Country = $country;
        }
        
        return $city;
    }
    
    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        TourVisioValidator::make()
            ->validateAllCredentials($this->post)
            ->validateHotelDetailsFilter($filter);

        $cities = $this->apiGetCities();
        $httpClient = HttpClient::create();

        $hotelFilter = explode('-', $filter->hotelId);

        // get departures
        $options['body'] = json_encode([
            'productType' => self::PRODUCT_TYPE_HOTEL,
            'product' => $hotelFilter[0],
            'culture' => 'en-US',
            'ownerProvider' => $hotelFilter[1],
        ]);
        $token = $this->getToken();
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $url = $this->apiUrl . '/api/productservice/getproductinfo';

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        $responseData = json_decode($responseObj->getBody(), true);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        $details = new Hotel();
        if (!isset($responseData['body'])) {
            return $details;
        }

        $response = $responseData['body']['hotel'];
        
        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();

        if (isset($response['seasons'])) {
            foreach ($response['seasons'] as $season) {
                if (isset($season['mediaFiles'])) {
                    foreach ($season['mediaFiles'] as $mediaFile) {
                        $image = new HotelImageGalleryItem();
                        $image->RemoteUrl = $mediaFile['urlFull'];
                        $image->Alt = null;
                        $items->put($image->RemoteUrl, $image);
                    }
                }
            }
        }

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        $type = '';
        $locationId = null;
        if (isset($response['village'])) {
            $type = self::LOCATION_TYPE_VILLAGE;
            $locationId = $response['village']['id'];
        } elseif (isset($response['town'])) {
            $type =  self::LOCATION_TYPE_TOWN;
            $locationId = $response['town']['id'];
        } else {
            $type =  self::LOCATION_TYPE_CITY;
            $locationId = $response['city']['id'];
        }

        // Content Address
        $address = new HotelAddress();

        $existingCity = $cities->get($locationId. '-' . $type);
        if ($existingCity === null) {
            // save hotel
            $hotelToSave = new Hotel();
            $hotelToSave->Id = $response['id'] . '-' . $response['provider'];
            $hotelToSave->Name = $response['name'];

            $this->saveHotel($hotelToSave, $locationId. '-' . $type);

            //$filter = new CitiesFilter();
            //$filter->clearCache = true;

            $cities = $this->apiGetCities();
            $existingCity = $cities->get($locationId. '-' . $type);
        }

        $address->City = $existingCity;
        
        if (isset($response['location']['latitude'])) {
            $address->Latitude = $response['location']['latitude'];
            $address->Longitude = $response['location']['longitude'];
        } elseif (isset($response['geolocation']['latitude'])) {
            $address->Latitude = $response['geolocation']['latitude'];
            $address->Longitude = $response['geolocation']['longitude'];
        }

        // Content ContactPerson
        $contactPerson = new ContactPerson();
        $contactPerson->Fax = $response['faxNumber'];
        $contactPerson->Phone = $response['phoneNumber'];

        $facilities = new FacilityCollection();
        if (isset($response['seasons'])) {
            foreach ($response['seasons'] as $season) {
                if (isset($season['facilityCategories'])) {
                    foreach ($season['facilityCategories'] as $category) {
                        foreach ($category['facilities'] as $facilityResponse) {
                            $facility = new Facility();
                            $facility->Id = $facilityResponse['id'];
                            $facility->Name = $facilityResponse['name'];
                            $facilities->put($facility->Id, $facility);
                        }
                    }
                }
            }
        }
        $description = null;
        if (isset($response['description'])) {
            $description = $response['description']['text'];
        }

        // Content
        $content = new HotelContent();
        $content->Content = $description;
        $content->ImageGallery = $imageGallery;

        $details->Id = $response['id'] . '-' . $response['provider'];
        $details->Name = $response['name'];
        $details->Stars = (int) $response['stars'] ?? 0;
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Content = $content;
        $details->WebAddress = $response['homePage'] ?? null;
        return $details;
    }

    private function getHotelOffers(AvailabilityFilter $filter): array
    {
        TourVisioValidator::make()
            ->validateAllCredentials($this->post)
            ->validateAvailabilityFilter($filter);

        $hotels = [];
        $token = $this->getToken();

        $httpClient = HttpClient::create();

        $ages = $post['args'][0]['rooms'][0]['childrenAges'];

        $cityFilter = [];
        if (!empty($filter->regionId)) {
            $cityFilter = explode('-', $filter->regionId);
        } else {
            $cityFilter = explode('-', $filter->cityId);
        }

        $body = [
            'checkAllotment' => false,
            'checkStopSale' => false,
            'getOnlyDiscountedPrice' => false,
            'getOnlyBestOffers' => false,
            'productType' => self::PRODUCT_TYPE_HOTEL,
            'roomCriteria' => [
                [
                    'adult' => $filter->rooms->first()->adults,
                    'childAges' => $ages? $ages->toArray() : []
                ]
            ],
            'checkIn' => $filter->checkIn,
            'night' => (int) $filter->days,
            'currency' => 'EUR'
        ];

        if (!empty($filter->hotelId)) {
            $hotelFilter = explode('-', $filter->hotelId);
            $body['Products'] = [$hotelFilter[0]];
        } else {
            $body['arrivalLocations'] = [
                [
                    'id' => $cityFilter[0],
                    'type' => $cityFilter[1]
                ]
            ];
        }

        // get departures
        $options['body'] = json_encode($body);
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $url = $this->apiUrl . '/api/productservice/pricesearch';

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        
        $responseData = json_decode($responseObj->getBody(), true);

        if (!isset($responseData['body'])) {
            return $hotels;
        }

        $responseData = $responseData['body']['hotels'];
        $cities = $this->apiGetCities();
        //$countries = $this->apiGetCountries();

        foreach ($responseData as $responseHotel) {
            $hotel = new Availability();
            $hotel->Id = $responseHotel['id'] . '-' . $responseHotel['provider'];
            $hotel->Name = $responseHotel['name'];
            $hotel->Stars = $responseHotel['stars'] ?? 0;

            $address = new HotelAddress();
            $existingCity = null;
            
            $availability = new Availability();
            $availability->Id = $responseHotel['id'] . '-' . $responseHotel['provider'];
            $availability->Name = $responseHotel['name'];

            $locationId = '';
            if (isset($responseHotel['village'])) {
                $locationId = $responseHotel['village']['id'] . '-' . self::LOCATION_TYPE_VILLAGE;
            } elseif (isset($responseHotel['town'])) {
                $locationId = $responseHotel['town']['id'] . '-' . self::LOCATION_TYPE_TOWN;
            } else {
                $locationId = $responseHotel['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
            }
            $existingCity = $cities->get($locationId);

            if ($existingCity === null) {
                // save hotel
                $hotelToSave = new Hotel();
                $hotelToSave->Id = $availability->Id;
                $hotelToSave->Name = $availability->Name;

                $this->saveHotel($hotelToSave, $locationId);

                // skip hotel
                continue;
            }

            $address->City = $existingCity;
            $address->Details = $responseHotel['address'];
            if (isset($responseHotel['geolocation'])) {
                $address->Latitude = $responseHotel['geolocation']['latitude'];
                $address->Longitude = $responseHotel['geolocation']['longitude'];
            }

            $hotel->Address = $address;

            $offers = new OfferCollection();
            foreach ($responseHotel['offers'] as $responseOffer) {
                $offer = new Offer();

                $offer->offerId = $responseOffer['offerId'];

                if ($responseOffer['isAvailable']) {
                    $offer->Availability = Offer::AVAILABILITY_YES;
                } else {
                    $offer->Availability = Offer::AVAILABILITY_NO;
                }

                if (!isset($responseOffer['rooms'][0]['roomId'])) {
                    $roomId = Utils::stripSpaces($responseOffer['rooms'][0]['roomName']);
                } else {
                    $roomId = $responseOffer['rooms'][0]['roomId'];
                }
                
                $boardId = $responseOffer['rooms'][0]['boardId'];
                $price = $responseOffer['price']['amount'];

                if (isset($responseOffer['price']['oldAmount'])) {
                    $initialPrice = $responseOffer['price']['oldAmount'];
                } else {
                    $initialPrice = $price;
                }

                $departureDateTime = (new DateTimeImmutable($responseOffer['checkIn']));
                $offer->CheckIn = $departureDateTime->format('Y-m-d');
                $nights = $responseOffer['night'];

                $offer->Code = $hotel->Id . '~' 
                    . $roomId . '~' 
                    . $boardId . '~' 
                    . $offer->CheckIn . '~' 
                    . $nights . '~' 
                    . $price . '~'
                    . $filter->rooms->get(0)->adults
                    . ($filter->rooms->get(0)->children > 0 ? '~' . implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '')
                ;
                $offer->Days = $nights;

                $currency = new Currency();
                $currency->Code = $responseOffer['price']['currency'];
                $offer->Currency = $currency;
                $offer->bookingCurrency = $currency->Code;

                $offer->Net = $price;
                $offer->Gross = $price;
                $offer->InitialPrice = $initialPrice;

                $hotelCheckInDateTime = new DateTimeImmutable($responseOffer['checkIn']);
                $hotelCheckOut = $hotelCheckInDateTime->add(new DateInterval('P' . $nights . 'D'));

                $room1 = new Room();
                $room1->Id = $roomId;
                $room1->CheckinAfter = $offer->CheckIn;
                $room1->CheckinBefore = $hotelCheckOut->format('Y-m-d');
    
                $room1->Currency = $offer->Currency;
                $room1->Availability = $offer->Availability;

                $merch = new RoomMerch();
                $merch->Id = $roomId;
                $merch->Title = $responseOffer['rooms'][0]['roomName'];

                $merchType = new RoomMerchType();
                $merchType->Id = $roomId;
                $merchType->Title = $merch->Title;
                $merch->Type = $merchType;
                $merch->Code = $merch->Id;
                $merch->Name = $merch->Title;

                $room1->Merch = $merch;
                $offer->Rooms = new RoomCollection([$room1]);
                $offer->Item = $room1;

                $boardTypeName = $responseOffer['rooms'][0]['boardName'];
                // MealItem Merch
                $boardMerch = new MealMerch();
                $boardMerch->Title = $boardTypeName;

                $mealItem = new MealItem();

                // MealItem
                $mealItem->Merch = $boardMerch;
                $mealItem->Currency = $offer->Currency;
                $offer->MealItem = $mealItem;

                // $cancelFeesCollection = new OfferCancelFeeCollection();
                // if (isset($responseOffer['cancellationPolicies'])) {
                //     foreach ($responseOffer['cancellationPolicies'] as $cancelPolicy) {
                //         $cp = new OfferCancelFee();
                //         $cp->Currency = $currency;
                //         $cp->Price = $cancelPolicy['price']['amount'];
                //         $cp->DateStart = $cancelPolicy['dueDate'];
                //         $cp->DateEnd = $room1->CheckinBefore;
                //         $cancelFeesCollection->add($cp);
                //     }
                // }
                // $offer->CancelFees = $cancelFeesCollection;

                // DepartureTransportItem Merch
                $departureTransportItemMerch = new TransportMerch();
                $departureTransportItemMerch->Title = 'CheckIn: ' . $hotelCheckInDateTime->format('d.m.Y');

                // DepartureTransportItem Return Merch
                $departureTransportItemReturnMerch = new TransportMerch();
                $departureTransportItemReturnMerch->Title = 'CheckOut: ' . $hotelCheckOut->format('d.m.Y');

                // DepartureTransportItem Return
                $departureTransportItemReturn = new ReturnTransportItem();
                $departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
                $departureTransportItemReturn->Currency = $currency;
                $departureTransportItemReturn->DepartureDate = $room1->CheckinBefore;
                $departureTransportItemReturn->ArrivalDate = $room1->CheckinBefore;

                // DepartureTransportItem
                $departureTransportItem = new DepartureTransportItem();
                $departureTransportItem->Merch = $departureTransportItemMerch;
                $departureTransportItem->Currency = $currency;
                $departureTransportItem->DepartureDate = $room1->CheckinAfter;
                $departureTransportItem->ArrivalDate = $room1->CheckinAfter;
                $departureTransportItem->Return = $departureTransportItemReturn;

                $offer->DepartureTransportItem = $departureTransportItem;

                $offer->ReturnTransportItem = $departureTransportItemReturn;

                $offers->add($offer);
            }

            $hotel->Offers = $offers;
            $hotels->add($hotel);
        }
        return $hotels;
    }

    private function saveHotel(Hotel $hotelToSave, string $locationId): void
    {
        // find file
        $filePath = __DIR__ . '/extra-hotels/' . $this->handle;

        if (!is_dir(__DIR__ . '/extra-hotels')) {
            mkdir(__DIR__ . '/extra-hotels', 0755);
        }
        if (!file_exists($filePath)) {
            $hotels = [];
            $hotels->put($locationId, $hotelToSave);
        } else {
            // get cities from file, add, save
            $content = file_get_contents($filePath);
            $hotelsArr = json_decode($content, true);
            $hotels = [];
            if ($hotelsArr !== null) {
                /** @var [] $hotels */
                $hotels = ResponseConverter::convertToCollection($hotelsArr, []::class);
            }
            $hotelExists = $hotels->get($locationId);
            if ($hotelExists === null) {
                $hotels->put($locationId, $hotelToSave);
            }
        }
        file_put_contents($filePath, json_encode_pretty($hotels));
    }

    private function getCharterOffers(AvailabilityFilter $filter): array
    {
        TourVisioValidator::make()
            ->validateAllCredentials($this->post)
            ->validateCharterOffersFilter($filter);

        $availabilities = [];
        $token = $this->getToken();
        $cities = $this->apiGetCities();

        $httpClient = HttpClient::create();

        $ages = $post['args'][0]['rooms'][0]['childrenAges'];
        $cityFilter = [];
        if (!empty($filter->regionId)) {
            $cityFilter = explode('-', $filter->regionId);
        } else {
            $cityFilter = explode('-', $filter->cityId);
        }

        $departureCityFilter = explode('-', $filter->departureCity);

        $hotelId = [];

        // todo
        //$filter->hotelId = '2655-1';

        if (!empty($filter->hotelId)) {
            $hotelFilter = explode('-', $filter->hotelId);
            $hotelId[] = $hotelFilter[0];
        }

        $options['body'] = json_encode([
            'CheckAllotment' => false,
            'CheckStopSale' => false,
            'ProductType' => self::PRODUCT_TYPE_HOLIDAY_PACKAGE,
            'IncludeSubLocations' => true,
            'DepartureLocations' => [
                [
                    'Id' => $departureCityFilter[0],
                    'Type' => $departureCityFilter[1]
                ]
            ],
            'ArrivalLocations' => [
                [
                    'Id' => $cityFilter[0],
                    'Type' => $cityFilter[1]
                ]
            ],
            'RoomCriteria' => [
                [
                    'Adult' => $filter->rooms->first()->adults,
                    'ChildAges' => $ages? $ages->toArray() : []
                ]
            ],
            'CheckIn' => $filter->checkIn,
            'Night' => (int) $filter->days,
            'Currency' => 'EUR',
            'Products' => $hotelId
        ]);
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $url = $this->apiUrl . '/api/productservice/pricesearch';

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        
        $responseData = json_decode($responseObj->getBody(), true);

        //$this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        
        if (!isset($responseData['body'])) {
            return $availabilities;
        }

        $responseHotels = $responseData['body']['hotels'];
        $passengers = (int) $filter->rooms->first()->adults + (int)$post['args'][0]['rooms'][0]['children'];
        //$countries = $this->apiGetCountries();

        $responseHotelsMap = [];
        foreach ($responseHotels as $responseHotel) {

            $availability = new Availability();
            $availability->Id = $responseHotel['id'] . '-' . $responseHotel['provider'];
            $availability->Name = $responseHotel['name'];

            $locationId = '';
            if (isset($responseHotel['village'])) {
                $locationId = $responseHotel['village']['id'] . '-' . self::LOCATION_TYPE_VILLAGE;
            } elseif (isset($responseHotel['town'])) {
                $locationId = $responseHotel['town']['id'] . '-' . self::LOCATION_TYPE_TOWN;
            } else {
                $locationId = $responseHotel['city']['id'] . '-' . self::LOCATION_TYPE_CITY;
            }
            $existingCity = $cities->get($locationId);

            if ($existingCity === null) {
                // save hotel
                $hotelToSave = new Hotel();
                $hotelToSave->Id = $availability->Id;
                $hotelToSave->Name = $availability->Name;
                $this->saveHotel($hotelToSave, $locationId);

                // skip hotel
                continue;
            }

            if (isset($responseHotel['stars'])) {
                $availability->Stars = (int) $responseHotel['stars'] ?? 0;
            }

            $address = new HotelAddress();

            if (isset($responseHotel['geolocation'])) {
                $address->Latitude = $responseHotel['geolocation']['latitude'];
                $address->Longitude = $responseHotel['geolocation']['longitude'];
            } else {
                $address->Latitude = $responseHotel['location']['latitude'] ?? null;
                $address->Longitude = $responseHotel['location']['longitude'] ?? null;
            }

            $address->Details = $responseHotel['address'];

            $address->City = $existingCity;
            $availability->Address = $address;

            $offers = new OfferCollection();

            // if more than 10 offers
            // if fibula
            // get only the lower price for room type/meal type

            $offersIndexed = [];
            foreach ($responseHotel['offers'] as $responseOffer) {

                $seatsOutbound = $responseOffer['rooms'][0]['transportation']['outbound']['availableSeatCount'];
                $seatsReturn = $responseOffer['rooms'][0]['transportation']['return']['availableSeatCount'];

                if ($seatsOutbound < $passengers || $seatsReturn < $passengers) {
                    continue;
                }

                $offer = new Offer();

                $offer->offerId = $responseOffer['offerId'];

                if ($responseOffer['isAvailable']) {
                    $offer->Availability = Offer::AVAILABILITY_YES;
                } else {
                    $offer->Availability = Offer::AVAILABILITY_NO;
                }

                $roomId = md5($responseOffer['rooms'][0]['roomName']);

                $boardId = md5($responseOffer['rooms'][0]['boardName']);

                $price = $responseOffer['price']['amount'];

                if (isset($responseOffer['price']['oldAmount'])) {
                    $initialPrice = $responseOffer['price']['oldAmount'];
                } else {
                    $initialPrice = $price;
                }

                $departureDateTime = (new DateTimeImmutable($responseOffer['checkIn']));
                $offer->CheckIn = $departureDateTime->format('Y-m-d');
                $nights = $responseOffer['night'];

                $offer->Code = $availability->Id . '~' 
                    . $roomId . '~' 
                    . $boardId . '~' 
                    . $offer->CheckIn . '~' 
                    . $nights . '~' 
                    . $price . '~'
                    . $filter->rooms->get(0)->adults
                    . ($filter->rooms->get(0)->children > 0 ? '~' . implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '')
                ;

                $offer->Days = $nights;

                $currency = new Currency();
                $currency->Code = $responseOffer['price']['currency'];
                $offer->Currency = $currency;

                $offer->Net = $price;
                $offer->Gross = $price;

                // get the lowest only
                // if ($this->handle === Handles::FIBULA_V2 && count($responseHotel['offers']) > 10) {
                //     if (isset($offersIndexed[$roomId.'-'.$boardId])) {

                //         if ($price < $offersIndexed[$roomId.'-'.$boardId]->Gross) {
                //             $offers->forget($offersIndexed[$roomId.'-'.$boardId]->Code);
                //         } else {
                //             continue;
                //         }
                //     } else {
                //         $offersIndexed[$roomId.'-'.$boardId] = $offer;
                //     }
                // }

                $offer->InitialPrice = $initialPrice;

                $hotelCheckInDateTime = new DateTimeImmutable($responseOffer['accomodationCheckIn']);
                $hotelCheckOut = $hotelCheckInDateTime->add(new DateInterval('P' . $nights . 'D'));

                $room1 = new Room();
                $room1->Id = $roomId;
                $room1->CheckinAfter = $offer->CheckIn;
                $room1->CheckinBefore = $hotelCheckOut->format('Y-m-d');
                
    
                $room1->Currency = $offer->Currency;
                $room1->Availability = $offer->Availability;

                $merch = new RoomMerch();
                $merch->Id = $roomId;
                $merch->Title = $responseOffer['rooms'][0]['roomName'];

                $merchType = new RoomMerchType();
                $merchType->Id = $roomId;
                $merchType->Title = $merch->Title;
                $merch->Type = $merchType;
                $merch->Code = $merch->Id;
                $merch->Name = $merch->Title;

                $room1->Merch = $merch;
                $offer->Rooms = new RoomCollection([$room1]);
                $offer->Item = $room1;

                $boardTypeName = $responseOffer['rooms'][0]['boardName'];
                // MealItem Merch
                $boardMerch = new MealMerch();
                $boardMerch->Title = $boardTypeName;
                $boardMerch->Id = $boardId;

                $mealItem = new MealItem();

                // MealItem
                $mealItem->Merch = $boardMerch;
                $mealItem->Currency = $offer->Currency;
                $offer->MealItem = $mealItem;

                //$transportation = $responseOffer['rooms'][0]['transportation'];

                // array with flight code - offer id
                $flightCodeOutbound = $responseOffer['rooms'][0]['transportation']['outbound']['code'];
                $flightCodeReturn = $responseOffer['rooms'][0]['transportation']['return']['code'];
                $index = $flightCodeOutbound . '-' . $flightCodeReturn;

                $offerDetails = null;
                if (!isset($responseHotelsMap[$index])) {
                    $options['body'] = json_encode([
                        'offerIds' => [$responseOffer['offerId']],
                        'getProductInfo' => true,
                        'currency' => 'EUR',
                    ]);
                    $url = $this->apiUrl . '/api/productservice/getofferdetails';
            
                    $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
                    $offerDetails = json_decode($responseObj->getBody(), true);

                    $responseHotelsMap[$index] = $offerDetails;
                    $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
                    
                } else {
                    $offerDetails = $responseHotelsMap[$index];
                }

                $offerDetails = $offerDetails['body']['offerDetails'][0];

                $flights = [];

                if (count($offerDetails['flights']) != 2) {
                    throw new Exception($this->handle . ' number of flights?');
                }
                
                foreach ($offerDetails['flights'] as $k => $flight) {
                    if (count($flight['offers']) > 1) {
                        throw new Exception($this->handle . ' number of offers not ok');
                    }

                    if (count($flight['items']) > 1) {
                        throw new Exception($this->handle . ' number of items not ok');
                    }

                    if ($flight['offers'][0]['seatInfo']['availableSeatCount'] >= $passengers) {
                        $flights[$flight['items'][0]['flightNo']] = $flight;
                    }
                }

                // $flightOutbound = $offerDetails['flights'][0];
                // $flightReturn = $offerDetails['flights'][1];

                if (!isset($flights[$flightCodeOutbound])) {
                    continue;
                }

                $flightOutbound = $flights[$flightCodeOutbound];
                $flightReturn = $flights[$flightCodeReturn];

                $flightDepartureDateTime = new DateTime($flightOutbound['items'][0]['departure']['date']);
                $flightArrivalDateTime = new DateTime($flightOutbound['items'][0]['arrival']['date']);
                $flightReturnDateTime = new DateTime($flightReturn['items'][0]['departure']['date']);
                $flightReturnArrivalDateTime = new DateTime($flightReturn['items'][0]['arrival']['date']);

                // departure transport item merch
                $departureTransportMerch = new TransportMerch();
                $departureTransportMerch->Title = "Dus: ". $flightDepartureDateTime->format('d.m.Y');
                $departureTransportMerch->Category = new TransportMerchCategory();
                $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                $departureTransportMerch->DepartureTime = $flightDepartureDateTime->format('Y-m-d H:i');
                $departureTransportMerch->ArrivalTime = $flightArrivalDateTime->format('Y-m-d H:i');
                $departureTransportMerch->DepartureAirport = $flightOutbound['items'][0]['departure']['airport']['id'];
                $departureTransportMerch->ReturnAirport = $flightOutbound['items'][0]['arrival']['airport']['id'];
                $departureTransportMerch->From = new TransportMerchLocation();
                $cityId = $flightOutbound['items'][0]['departure']['city']['id'] . '-' . $flightOutbound['items'][0]['departure']['city']['type'];
                
                $departureTransportMerch->From->City = $cities->get($cityId);
    
                $cityId = $flightOutbound['items'][0]['arrival']['city']['id'] . '-' . $flightOutbound['items'][0]['arrival']['city']['type'];
                $departureTransportMerch->To = new TransportMerchLocation();

                $cityDest = $cities->get($cityId);
                if ($cityDest === null) {
                    $cityDest = new City();
                    $cityDest->Id = $flightOutbound['items'][0]['arrival']['city']['id'];
                    $cityDest->Name = $flightOutbound['items'][0]['arrival']['city']['name'];
                }

                $departureTransportMerch->To->City = $cityDest;

                $departureTransportItem = new DepartureTransportItem();
                $departureTransportItem->Merch = $departureTransportMerch;
                $departureTransportItem->Currency = $offer->Currency;
                $departureTransportItem->DepartureDate = $flightDepartureDateTime->format('Y-m-d');
                $departureTransportItem->ArrivalDate = $flightArrivalDateTime->format('Y-m-d');

                // return transport item
                $returnTransportMerch = new TransportMerch();
                $returnTransportMerch->Title = "Retur: ". $flightReturnDateTime->format('d.m.Y');
                $returnTransportMerch->Category = new TransportMerchCategory();
                $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                $returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                $returnTransportMerch->DepartureTime = $flightReturnDateTime->format('Y-m-d H:i');
                $returnTransportMerch->ArrivalTime = $flightReturnArrivalDateTime->format('Y-m-d');

                $returnTransportMerch->DepartureAirport = $flightReturn['items'][0]['departure']['airport']['id'];
                $returnTransportMerch->ReturnAirport = $flightReturn['items'][0]['arrival']['airport']['id'];

                //$cityId = $flightReturn['items'][0]['departure']['city']['id'] . '-' . $flightReturn['items'][0]['departure']['city']['type'];
                $returnTransportMerch->From = new TransportMerchLocation();
                $returnTransportMerch->From->City = $cityDest;

                $cityId = $flightReturn['items'][0]['arrival']['city']['id'] . '-' . $flightReturn['items'][0]['arrival']['city']['type'];
                $returnTransportMerch->To = new TransportMerchLocation();
 
                $returnTransportMerch->To->City = $cities->get($cityId);

                $returnTransportItem = new ReturnTransportItem();
                $returnTransportItem->Merch = $returnTransportMerch;
                $returnTransportItem->Currency = $offer->Currency;
                $returnTransportItem->DepartureDate = $flightReturnDateTime->format('Y-m-d');
                $returnTransportItem->ArrivalDate = $flightReturnArrivalDateTime->format('Y-m-d');

                $departureTransportItem->Return = $returnTransportItem;

                // add items to offer
                $offer->Item = $room1;
                $offer->DepartureTransportItem = $departureTransportItem;
                $offer->ReturnTransportItem = $returnTransportItem;

                $offer->Items = [];
                if ($this->handle === self::TEST_HANDLE) {
                    $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory, '');
                    $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory, '');
                } else {
                    $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);
                    $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                }

                $cancelFeesCollection = new OfferCancelFeeCollection();
                // if (isset($responseOffer['cancellationPolicies'])) {
                //     foreach ($responseOffer['cancellationPolicies'] as $cancelPolicy) {
                //         $cp = new OfferCancelFee();
                //         $cp->Currency = $currency;
                //         $cp->Price = $cancelPolicy['price']['amount'];
                //         $cp->DateStart = $cancelPolicy['dueDate'];
                //         $cp->DateEnd = $room1->CheckinAfter;
                //         $cancelFeesCollection->add($cp);
                //     }
                // }

                $offer->CancelFees = $cancelFeesCollection;
                
                $offers->put($offer->Code, $offer);
            }
            if (count($offers) === 0) {
                continue;
            }
            $availability->Offers = $offers;

            $availabilities->put($availability->Id, $availability);
        }
       
        return $availabilities;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        $availabilities = [];
        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            $availabilities = $this->getHotelOffers($filter);
        } else if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            $availabilities = $this->getCharterOffers($filter);
        }
        return $availabilities;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        TourVisioValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);
            
        $offerId = $filter->Items->first()->Offer_offerId;
        $token = $this->getToken();
        $options['body'] = json_encode([
            'offerIds' => [$offerId],
            'currency' => $filter->Items->first()->Offer_bookingCurrency
        ]);
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $url = $this->apiUrl . '/api/bookingservice/begintransaction';

        $httpClient = HttpClient::create();
        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseData = json_decode($responseObj->getBody(), true);

        $passengers = $post['args'][0]['Items'][0]['Passengers'];

        $travellers = [];
        $infantMaxAge = 2;

        $checkInDT = new DateTimeImmutable($post['args'][0]['Items'][0]['Room_CheckinAfter']);
        $i = 0;
        /** @var Passenger $passenger */
        foreach ($passengers as $passenger) {
            $i++;
            $isLeader = false;
            if ($i === 1) {
                $isLeader = true;
            }
            
            $birthDT = new DateTimeImmutable($passenger['BirthDate']);
            $age = $checkInDT->diff($birthDT)->y;

            // constants are taken from docs
            // ex: http://docs.santsg.com/tourvisio/enumarations/#traveller-types
            $passengerType = $passenger['IsAdult'] ? 1 : ($age >= $infantMaxAge ? 2 : 3);
            $travellers[] = [
                'travellerId' => $i,
                'type' => $passengerType,
                'title' => $passenger['IsAdult'] ? ($passenger['Gender'] === 'male' ? 1 : 3) : 5,
                'name' => $passenger['Firstname'],
                'surname' => $passenger['Lastname'],
                'birthDate' => $passenger['BirthDate'],
                'nationality' => [
                    'twoLetterCode' => 'RO'
                ],
                'isLeader' => $isLeader,
                'address' => [
                    'contactPhone' => [
                        'countryCode' => '0040',
                        'phoneNumber' => '740000000'
                    ],
                    'email' => $filter->BillingTo->Email
                ]
            ];
        }

        $transactionId = $responseData['body']['transactionId'];
        $options['body'] = json_encode([
            'transactionId' => $transactionId,
            'travellers' => $travellers
        ]);

        $url = $this->apiUrl . '/api/bookingservice/setreservationinfo';

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseData = json_decode($responseObj->getBody(), true);

        $options['body'] = json_encode([
            'transactionId' => $transactionId
        ]);

        $url = $this->apiUrl . '/api/bookingservice/committransaction';

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseData = json_decode($responseObj->getBody(), true);

        $booking = new Booking();
        $booking->Id = $responseData['body']['reservationNumber'];

        return [$booking, $responseObj->getBody()];
    }
}
