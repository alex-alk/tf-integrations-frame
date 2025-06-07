<?php

namespace Services\Odeon;

use Exception;
use HttpClient\HttpClient;
use HttpClient\Message\Request;
use Models\City;
use Models\Country;
use Models\Hotel;
use Models\Region;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Services\IntegrationSupport\AbstractApiService;
use Services\IntegrationSupport\CountryCodeMap;
use Services\IntegrationSupport\Validator;
use Utils\Utils;

class OdeonApiService extends AbstractApiService
{
    const TEST_HANDLE = 'localhost-coral';
    const EUR = 3;

    private static array $mapForAvailDates = [
        5 => [ // Antalya
            3, // Alanya
            5, // Antalya 
            6, // Belek
            8, // Kemer
            10, // Side
            27, // Kas
            12962, // Adrasan
            12966, // Cirali
            14144, // Demre
        ],
        1 => [ // Bodrum
            1, // Bodrum
            22, // Cesme 
            24, // Didim
            25, // Fethiye
            28, // Kusadasi
            30, // Marmaris
            32, // Ozdere
            3264, // Dalaman
            13208, // Seferihisar
            13209, // Gumuldur

        ],
        3264 => [ // Dalaman
            25, // Fethiye
            30, // Marmaris
            3264 // Dalaman
        ],
        143 => [ // Heraklion
            143, // Heraklion
            755, // Crete,
            820 // Chania
        ],
        94 => [ // Monastir
            41, // Hammamet,
            93, // Mahdia,
            94, // Monastir
            95, // Sousse
            12597 // Zarzis
        ],
        12588 => [ // Djerba
            12588, // Djerba
            12597 // Zarzis
        ],
        146 => [ // Punta Cana
            146, // Punta Cana
            302, // La Romana
            776, // Juan Dolio
            3236, // Puerto Plata
            12040, // Cap Cana
            13277, // Bayahibe
        ]
    ];

    public function __construct(private ServerRequestInterface $serverRequest, private HttpClient $client)
    {
        parent::__construct($serverRequest);
    }

    private function getHotelByCityIdMap(): array
    {
        $map = [];
        $hotels = $this->apiGetHotels();

        foreach ($hotels as $hotel) {
            $map[$hotel->Address->City->Id] = $hotel;
        }
        return $map;
    }

    private function getCitiesByRegionMap(): array
    {
        $cities = $this->apiGetCities();
        $map = [];
        foreach ($cities as $city) {
            if (isset($map[$city->County->Id])) {
                $map[$city->County->Id][] = $city;
            } else {
                $map[$city->County->Id] = [$city];
            }
        }
        return $map;
    }

    private function getCityByAirportCodeMap(): array
    {
        $cities = $this->apiGetCities();
        $token = $this->getToken();

        // get departure dates
        $requestArr = [
            'Command' => 'Flight.General.ListAirportView',
            'Token' => $token
        ];
        $body = json_encode($requestArr);
        $headers = ['Content-Type' => 'application/json'];

        $responseDepObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        $responseDep = json_decode($responseDepObj->getBody(), true);

        if ($responseDep['Error'] && $responseDep['Response'] === 'ERROR_SESSION_NOT_FOUND') {
            $token = $this->cacheToken();
            $requestArr['Token'] = $token;
            $body = json_encode($requestArr);
            $responseDepObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $responseDep = json_decode($responseDepObj->getBody(), true);
        }
        if ($responseDep['Error']) {
            throw new Exception($responseDep['Response']);
        }
        $airports = json_decode($responseDep['Details'], true);

        $map = [];
        foreach ($airports as $airport) {
            $city = $cities->get($airport['PlaceID']);
            $map[$airport['Sname']] = $city;
        }

        return $map;
    }

    public function apiGetAvailabilityDates(): array
    {
        $availabilityDatesCollection = [];

        $file = 'avail-dates';
        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

            $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;
            $token = $this->getToken();

            // get departure dates
            $requestArr = [
                'Command' => 'Product.Package.ListPackageAvailableDate',
                'Token' => $token
            ];
            $body = json_encode($requestArr);
            $headers = ['Content-Type' => 'application/json'];

            $this->client = HttpClient::create();
            $responseDepObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $responseDep = json_decode($responseDepObj->getBody(), true);

            if ($responseDep['Error'] && $responseDep['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                $token = $this->cacheToken();
                $requestArr['Token'] = $token;
                $body = json_encode($requestArr);
                $responseDepObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                $responseDep = json_decode($responseDepObj->getBody(), true);
            }
            // $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseDepObj->getBody(), $responseDepObj->getStatusCode());

            if ($responseDep['Error']) {
                throw new Exception($responseDep['Response']);
            }

            //$cities = $this->apiGetCities();
            $hotelMap = $this->getHotelByCityIdMap();
            $citiesByRegionMap = $this->getCitiesByRegionMap();

            $datesResponseDep = json_decode($responseDep['Details'], true);

            $cityAirpotMap = $this->getCityByAirportCodeMap();

            $this->client = HttpClient::create();

            $flightRequests = [];

            foreach ($datesResponseDep as $dateResponseDep) { // for each date and location combination get flights
                $date = new DateTime($dateResponseDep['PackageDate']);
                $dateStr = $date->format('Y-m-d');

                foreach ($dateResponseDep['ToCountryList'] as $countryId) {

                    $flightRequestKey =
                        $dateResponseDep['FromArea']
                        . '-' . $countryId
                        . '-' . (int) $date->format('n')
                        . '-' . (int) $date->format('Y');

                    if (array_key_exists($flightRequestKey, $flightRequests)) {
                        $response = $flightRequests[$flightRequestKey];
                    } else {
                        $requestArr = [
                            'Command' => 'Product.Package.GetFlightInfo',
                            'Token' => $token,
                            'Parameters' => json_encode([
                                'fromArea' => $dateResponseDep['FromArea'],
                                'toCountry' => $countryId,
                                'month' => (int) $date->format('n'),
                                'year' => (int) $date->format('Y')
                            ])
                        ];

                        $body = json_encode($requestArr);
                        $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                        $response = json_decode($responseObj->getBody(), true);

                        if ($responseDep['Error'] && $responseDep['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                            $token = $this->cacheToken();
                            $requestArr['Token'] = $token;
                            $body = json_encode($requestArr);
                            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                            $response = json_decode($responseObj->getBody(), true);
                        }

                        if ($response['Error']) {
                            throw new Exception($response['Response']);
                        }


                        $flightsToProc = json_decode($response['Details'], true);

                        $flightsNew = [];
                        foreach ($flightsToProc as $flightToProc) {

                            $flightNew = [
                                'directFlightType' => $flightToProc['directFlightType'],
                                'returnFlightType' => $flightToProc['returnFlightType'],
                                'departureTakeOffDate' => $flightToProc['departureTakeOffDate'],
                                'departureTakeOffAirportCode' => $flightToProc['departureTakeOffAirportCode'],
                                'returnTakeOffDate' => $flightToProc['returnTakeOffDate']
                            ];
                            $flightsNew[] = $flightNew;
                        }

                        $flightRequests[] = ['Details' => $flightsNew];
                    }

                    $flights = json_decode($response['Details'], true);

                    // foreach fromArea get city
                    //$citiesFromArea = $cities->filter(fn(City $city) => (int) $city->County->Id === $dateResponseDep['FromArea']);

                    foreach ($flights as $flight) {

                        if (!($flight['directFlightType'] === 0 && $flight['returnFlightType'] === 0)) {
                            continue;
                        }

                        $departureDateTime = (new DateTime($flight['departureTakeOffDate']))->setTime(0, 0);
                        $departureDateStr = $departureDateTime->format('Y-m-d');
                        if ($departureDateStr !== $dateStr) {
                            continue;
                        }
                        $cityFromArea = $cityAirpotMap[$flight['departureTakeOffAirportCode']];

                        //foreach ($citiesFromArea as $cityFromArea) {

                        foreach ($dateResponseDep['ToAreaList'] as $toAreaAirport) {

                            $toAreas = self::$mapForAvailDates[$toAreaAirport] ?? [$toAreaAirport];

                            foreach ($toAreas as $toArea) {

                                $citiesFromDestinationArea = $citiesByRegionMap[$toArea];

                                /** @var City $destinationCity */
                                foreach ($citiesFromDestinationArea as $destinationCity) {
                                    if ($destinationCity->Country->Id != $countryId) {
                                        continue;
                                    }

                                    if (!isset($hotelMap[$destinationCity->Id])) {
                                        continue;
                                    }

                                    $id = $transportType . "~city|" . $cityFromArea->Id . "~city|" . $destinationCity->Id;
                                    $existingAvailabilityDates = $availabilityDatesCollection->get($id);

                                    $returnDateTime = (new DateTime($flight['returnTakeOffDate']))->setTime(0, 0);
                                    $nightsInt = (int) $returnDateTime->diff($departureDateTime)->days;

                                    $availabilityDates = null;
                                    if ($existingAvailabilityDates === null) {

                                        $transportCityFrom = new TransportCity();
                                        $transportCityFrom->City = $cityFromArea;
                                        $transportCityTo = new TransportCity();
                                        $transportCityTo->City = $destinationCity;

                                        $transportDate = new TransportDate();
                                        $transportDate->Date = $departureDateStr;

                                        $availabilityDates = new AvailabilityDates();
                                        $availabilityDates->Id = $id;
                                        $availabilityDates->From = $transportCityFrom;
                                        $availabilityDates->To = $transportCityTo;
                                        $availabilityDates->TransportType = $transportType;
                                        $availabilityDates->Content = new TransportContent();

                                        // creating Dates array
                                        $night = new DateNight();
                                        $night->Nights = $nightsInt;

                                        $nights = new DateNightCollection();
                                        $nights->put($nightsInt, $night);
                                        $transportDate->Nights = $nights;

                                        $transportDateCollection = new TransportDateCollection();
                                        $transportDateCollection->put($transportDate->Date, $transportDate);
                                        $availabilityDates->Dates = $transportDateCollection;
                                    } else {
                                        $dateObj = $existingAvailabilityDates->Dates;

                                        // check if date index exists
                                        $existingDateIndex = $dateObj->get($departureDateStr);

                                        if ($existingDateIndex === null) {

                                            // adding date to cities index
                                            $transportDate = new TransportDate();
                                            $transportDate->Date = $departureDateStr;

                                            // creating Dates array
                                            $night = new DateNight();
                                            $night->Nights = $nightsInt;

                                            $nights = new DateNightCollection();
                                            $nights->put($nightsInt, $night);
                                            $transportDate->Nights = $nights;

                                            $dateObj->put($transportDate->Date, $transportDate);
                                            $existingAvailabilityDates->Dates = $dateObj;
                                            $availabilityDates = $existingAvailabilityDates;
                                        } else {

                                            // add nights to date object
                                            $night = new DateNight();
                                            $night->Nights = $nightsInt;

                                            $nights = $existingDateIndex->Nights;
                                            $nights->put($nightsInt, $night);
                                            $existingDateIndex->Nights = $nights;

                                            $dateObj->put($existingDateIndex->Date, $existingDateIndex);
                                            $existingAvailabilityDates->Dates = $dateObj;
                                            $availabilityDates = $existingAvailabilityDates;
                                        }
                                    }
                                    $availabilityDatesCollection->put($id, $availabilityDates);
                                }
                            }
                        }
                        //}
                    }
                }
            }
            Utils::writeToCache($this, $file, json_encode($availabilityDatesCollection));
        } else {
            $availabilityDatesCollection = json_decode($json, true);
        }

        return $availabilityDatesCollection;
    }

    private function getCountriesFromAvailabilityDates(): array
    {
        $availabilityDatesCountries = [];

        $file = 'avail-dates-countries';
        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

            $token = $this->getToken();

            // get departure dates
            $requestArr = [
                'Command' => 'Product.Package.ListPackageAvailableDate',
                'Token' => $token
            ];
            $body = json_encode($requestArr);
            $headers = ['Content-Type' => 'application/json'];

            $this->client = HttpClient::create();
            $responseDepObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $responseDep = json_decode($responseDepObj->getBody(), true);

            if ($responseDep['Error'] && $responseDep['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                $token = $this->cacheToken();
                $requestArr['Token'] = $token;
                $body = json_encode($requestArr);
                $responseDepObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                $responseDep = json_decode($responseDepObj->getBody(), true);
            }
            // $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseDepObj->getBody(), $responseDepObj->getStatusCode());

            if ($responseDep['Error']) {
                throw new Exception($responseDep['Response']);
            }

            $datesResponseDep = json_decode($responseDep['Details'], true);

            $this->client = HttpClient::create();

            foreach ($datesResponseDep as $dateResponseDep) {

                foreach ($dateResponseDep['ToCountryList'] as $countryId) {

                    $availabilityDatesCountries[$countryId] = $countryId;
                }
            }
            Utils::writeToCache($this, $file, json_encode($availabilityDatesCountries));
        } else {
            $availabilityDatesCountries = json_decode($json, true);
        }

        return $availabilityDatesCountries;
    }

    public function apiGetCountries(): array
    {
        $cities = $this->apiGetCities();

        $countries = [];
        foreach ($cities as $city) {
            $countries[$city->getCountry()->getId()] = $city->getCountry();
        }
        return $countries;
        // $token = $this->cacheToken();

        // $headers = ['Content-Type' => 'application/json'];

        // $requestArr = [
        //     'Command' => 'General.Geography.ListGeography',
        //     'Token' => $token
        // ];
        // $body = json_encode($requestArr);

        // $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        // $responseData = json_decode($responseObj->getBody(), true);

        // if ($responseData['Error'] && $responseData['Response'] === 'ERROR_SESSION_NOT_FOUND') {
        //     $token = $this->cacheToken();
        //     $requestArr['Token'] = $token;
        //     $body = json_encode($requestArr);
        //     $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        //     $responseData = json_decode($responseObj->getBody(), true);
        // }
        // $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        // if ($responseData['Error']) {
        //     throw new Exception($responseData['Response']);
        // }

        // $countriesResponse = json_decode($responseData['Details'], true);

        // $countries = [];
        // $map = CountryCodeMap::getCountryCodeMap();

        // foreach ($countriesResponse as $value) {
        //     $code = '';
        //     if (!isset($map[trim($value['CountryLName'])])) {
        //          $code = $value['CountryLName'];
        //     } else {
        //          $code = $map[trim($value['CountryLName'])];
        //     }

        //     $country = new Country($value['CountryID'], $code, $value['CountryLName']);
        //     $countries[$value['CountryID']] = $country;
        // }
        // return $countries;
    }

    private function cacheToken(): string
    {
        $body = json_encode([
            'Command' => 'Login',
            'Token' => null,
            'Parameters' => json_encode([
                'User' => $this->username,
                'Password' => $this->password,
                'Language' => 15
            ])
        ]);
        $headersLogin = ['Content-Type' => 'application/json'];

        $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headersLogin);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headersLogin, $responseObj->getBody(), $responseObj->getStatusCode());
        $response = json_decode($responseObj->getBody(), true);

        if ($response['Error']) {
            throw new Exception($response['Details']);
        }
        Utils::writeToCache($this, 'token' . $this->username, $response['Token']);
        return $response['Token'];
    }

    /*
    private function cacheBookingToken(): string
    {
        $this->client = HttpClient::create();

        $optionsLogin['body'] = json_encode([
            'User' => $this->bookingApiUsername,
            'Password' => $this->bookingApiPassword
        ]);
        $optionsLogin['headers'] = ['Content-Type' => 'application/json'];
        $url = $this->bookingUrl . '/Authentication/Login';

        $responseObj = $this->client->request(Request::METHOD_POST, $url, $optionsLogin);
        $this->showRequest(Request::METHOD_POST, $url, $optionsLogin, $responseObj->getBody(), $responseObj->getStatusCode());
        $response = json_decode($responseObj->getBody(), true);

        if ($responseObj->getStatusCode() !== 200) {
            throw new Exception('Authentication failed!');
        }
        //Utils::writeToCache($this, 'bookingToken', $response['UserToken']);
        return $response['UserToken'];
    }*/

    private function cacheClientIdAndBookingToken(): array
    {
        $optionsLogin['body'] = json_encode([
            'User' => $this->username,
            'Password' => $this->password
        ]);
        $optionsLogin['headers'] = ['Content-Type' => 'application/json'];
        $url = $this->bookingUrl . '/Authentication/Login';

        $responseObj = $this->client->request(Request::METHOD_POST, $url, $optionsLogin);
        $this->showRequest(Request::METHOD_POST, $url, $optionsLogin, $responseObj->getBody(), $responseObj->getStatusCode());
        $response = json_decode($responseObj->getBody(), true);


        if ($responseObj->getStatusCode() !== 200) {
            throw new Exception('Authentication failed!');
        }
        Utils::writeToCache($this, 'bookingToken' . $this->username, $response['UserToken']);
        Utils::writeToCache($this, 'clientId' . $this->username, $response['AgencyStaffID']);

        return [$response['AgencyStaffID'], $response['UserToken']];
    }

    private function getToken(): string
    {
        $token = Utils::getFromCache($this, 'token' . $this->username);
        if ($token === null) {
            $token = $this->cacheToken();
        }
        return $token;
    }

    public function apiGetCities(): array
    {
        $file = 'cities';
        $citiesJson = Utils::getFromCache($this, $file);

        if ($citiesJson === null) {
            $token = $this->getToken();
            $requestArr = [
                'Command' => 'General.Geography.ListGeography',
                'Token' => $token
            ];
            $body = json_encode($requestArr);

            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $responseData = json_decode($responseObj->getBody(), true);

            if ($responseData['Error'] && $responseData['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                $token = $this->cacheToken();
                $requestArr['Token'] = $token;
                $body = json_encode($requestArr);
                $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                $responseData = json_decode($responseObj->getBody(), true);
            }
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, [], $responseObj->getBody(), $responseObj->getStatusCode());

            if ($responseData['Error']) {
                throw new Exception($responseData['Response']);
            }

            $response = json_decode($responseData['Details'], true);

            $map = CountryCodeMap::getCountryCodeMap();
            $cities = [];

            foreach ($response as $value) {

                $code = '';
                if (!isset($map[trim($value['CountryLName'])])) {
                    $code = '?';
                } else {
                    $code = $map[trim($value['CountryLName'])];
                }

                $country = new Country($value['CountryID'], $code, $value['CountryLName']);

                $regionName = $value['AreaLName'] ?? $value['AreaName'];
                $region = new Region($value['AreaID'], $regionName, $country);

                $cityName = $value['PlaceLName'] ?? $value['PlaceName'];

                $city = new City($value['PlaceID'], $cityName, $country, $region);

                $cities[$value['PlaceID']] = $city;
            }

            $data = json_encode($cities);
            Utils::writeToCache($this, $file, $data);
        } else {
            $cities = json_decode($citiesJson, true);
        }
        return $cities;
    }

    public function apiGetRegions(): array
    {
        $cities = $this->apiGetCities();

        $regions = [];
        foreach ($cities as $city) {
            $regions[$city->getRegion()->getId()] = $city->getRegion();
        }
        return $regions;

        // $requestArr = [
        //     'Command' => 'General.Geography.ListGeography',
        //     'Token' => $this->getToken()
        // ];
        // $body = json_encode($requestArr);
        // $headers = ['Content-Type' => 'application/json'];

        // $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        // $responseData = json_decode($responseObj->getBody(), true);

        // if ($responseData['Error'] && $responseData['Response'] === 'ERROR_SESSION_NOT_FOUND') {
        //     $token = $this->cacheToken();
        //     $requestArr['Token'] = $token;
        //     $body = json_encode($requestArr);
        //     $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        //     $responseData = json_decode($responseObj->getBody(), true);
        // }
        // $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        // if ($responseData['Error']) {
        //     throw new Exception($responseData['Response']);
        // }

        // $response = json_decode($responseData['Details'], true);

        // $map = CountryCodeMap::getCountryCodeMap();
        // $regions = [];
        // foreach ($response as $value) {
            
        //     $regionName = $value['AreaLName'] ?? $value['AreaName'];

        //     $country = new Country();
        //     $country->Id = $value['CountryID'];
        //     if (!isset($map[trim($value['CountryLName'])])) {
        //         $country->Code = '?';
        //     } else {
        //         $country->Code = $map[trim($value['CountryLName'])];
        //     }

        //     $country->Name = $value['CountryLName'];

        //     $region->Country = $country;

        //     $region = new Region($value['AreaID'], $regionName, );

        //     $regions->put($region->Id, $region);
        // }
        // return $regions;
    }

    public function getHotelRatingMap(): array
    {
        $token = $this->getToken();

        $this->client = HttpClient::create();
        $body = json_encode([
            'Command' => 'Accommodation.Hotel.ListHotelCategory',
            'Token' => $token,
            'Parameters' => json_encode([[
                'Name' => 'Name',
                'Value' => ''
            ]])
        ]);
        $headers = ['Content-Type' => 'application/json'];

        $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        $responseData = json_decode($responseObj->getBody(), true);

        if ($responseData['Error'] && $responseData['Response'] === 'ERROR_SESSION_NOT_FOUND') {
            $token = $this->cacheToken();
            $body = json_encode([
                'Command' => 'Accommodation.Hotel.ListHotelCategory',
                'Token' => $token
            ]);
            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $responseData = json_decode($responseObj->getBody(), true);
        }
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseObj->getBody(), $responseObj->getStatusCode());

        if ($responseData['Error']) {
            throw new Exception($responseData['Response']);
        }

        $response = json_decode($responseData['Details'], true);

        $map = [];
        foreach ($response as $rating) {
            $stars = 0;
            if (!in_array($rating['ShortName'], ['4*HV', '5*HV', 'HV-1', 'HV-2'])) {
                $stars = (int) $rating['ShortName'];
                if ($stars === 0) {
                    $stars = (int) substr($rating['ShortName'], -2);
                    if ($stars === 0) {
                        if ($rating['Name'] === 'Five Star Special Category') {
                            $stars = 5;
                        }
                    }
                }
            }

            $map[$rating['ID']] = $stars;
        }

        return $map;
    }


    public function apiGetHotels(): array
    {
        $file = 'hotels';
        $hotelsJson = Utils::getFromCache($this, $file);

        if ($hotelsJson === null) {
            $hotels = [];

            $token = $this->getToken();
            $requestArr = [
                'Command' => 'Accommodation.Hotel.ListHotel',
                'Token' => $token,
                'Parameters' => json_encode([[
                    'Name' => 'Name',
                    'Value' => ''
                ]])
            ];
            $body = json_encode($requestArr);
            $headers = ['Content-Type' => 'application/json'];

            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
            $responseData = json_decode($responseObj->getBody(), true);

            if ($responseData['Error'] && $responseData['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                $token = $this->cacheToken();
                $requestArr['Token'] = $token;
                $body = json_encode($requestArr);
                $headers = ['Content-Type' => 'application/json'];
                $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                $responseData = json_decode($responseObj->getBody(), true);
            }
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $responseObj->getBody(), $responseObj->getStatusCode());

            if ($responseData['Error']) {
                throw new Exception($responseData['Response']);
            }

            $response = json_decode($responseData['Details'], true);

            $cities = $this->apiGetCities();
            $ratingsMap = $this->getHotelRatingMap();
            $filter = new AvailabilityDatesFilter(['type' => 'charter']);

            $countriesFromAvailabilityDates = $this->getCountriesFromAvailabilityDates();

            foreach ($response as $hotelResponse) {
                $city = $cities->get($hotelResponse['Place']);

                if (!in_array($city->Country->Id, $countriesFromAvailabilityDates)) {
                    continue;
                }

                // $hotel->Address
                $address = new HotelAddress();
                $address->Latitude = $hotelResponse['Latitude'];
                $address->Longitude = $hotelResponse['Longitude'];
                $address->Details = $hotelResponse['Address'];
                $address->City = $city;


                $hotel = new Hotel();
                $hotel->Id = $hotelResponse['ID'];
                $hotel->Name = $hotelResponse['Name'];

                $hotel->Stars = $ratingsMap[$hotelResponse['HotelCategory']] ?? 0;
                //$hotel->Content = $content;
                $hotel->Address = $address;
                $hotel->WebAddress = $hotelResponse['Web'];

                $hotels->put($hotel->Id, $hotel);
            }

            $data = json_encode_pretty($hotels);
            Utils::writeToCache($this, $file, $data);
        } else {
            $hotels = json_decode($hotelsJson, true);
        }

        return $hotels;
    }

    private function getAirportMap(): array
    {
        $file = 'airport-map';
        $airportsJson = Utils::getFromCache($this, $file);

        if ($airportsJson === null) {

            $token = $this->getToken();
            $requestArr = [
                'Command' => 'Flight.General.ListAirportView',
                'Token' => $token,
            ];
            $this->client = HttpClient::create();

            $body = json_encode($requestArr);
            $headers = ['Content-Type' => 'application/json'];

            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $response = json_decode($responseObj->getBody(), true);

            if ($response['Error'] && $response['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                $token = $this->cacheToken();
                $requestArr['Token'] = $token;
                $body = json_encode($requestArr);
                $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
                $response = json_decode($responseObj->getBody(), true);
            }
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseObj->getBody(), $responseObj->getStatusCode());

            if ($response['Error']) {
                throw new Exception($response['Response']);
            }

            $airports = json_decode($response['Details'], true);

            $airportMap = [];
            foreach ($airports as $airport) {
                $airportMap[$airport['Sname']] = $airport['PlaceID'];
            }
            $data = json_encode_pretty($airportMap);
            Utils::writeToCache($this, $file, $data);
        } else {
            $airportMap = json_decode($airportsJson, true);
        }

        return $airportMap;
    }

    public function apiGetHotelDetails(): Hotel
    {
        if ($this->handle === 'localhost-coral') {
            $contentUrl = env('CORAL_CONTENT_URL_TEST');
        } else {
            $contentUrl = env('CORAL_CONTENT_URL');
        }

        $hotelId = $filter->hotelId;
        $details = new Hotel();

        $hotels = $this->apiGetHotels();
        $hotelFromList = $hotels->get($hotelId);
        if ($hotelFromList === null) {
            return $details;
        }

        $countryId = $hotelFromList->Address->City->Country->Id;
        $url = $contentUrl . '/info/' . $countryId . '/' . $hotelId . '/list.json';

        $this->client = HttpClient::create();

        $responseObj = $this->client->request(HttpClient::METHOD_GET, $url);
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseUrl = json_decode($responseObj->getBody(), true);

        $urlInfos = [];

        if (!isset($responseUrl[0])) {
            return $details;
        }

        foreach ($responseUrl[0]['URLs'] as $info) {
            $urlInfos[$info['LanguageID']] = $info;
        }

        $urlInfo = null;
        if (isset($urlInfos[15])) { // romanian
            $urlInfo = $urlInfos[15];
        } elseif (isset($urlInfos[2])) { // english
            $urlInfo = $urlInfos[2];
        }
        if ($urlInfo === null) {
            return $details;
        }

        if (!isset($urlInfo['InfoSheetURL'])) {
            return $details;
        }

        $hotelUrl = $contentUrl . $urlInfo['InfoSheetURL'];

        $responseInfo = $this->client->request(HttpClient::METHOD_GET, $hotelUrl);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $options, $responseInfo->getBody(), $responseInfo->getStatusCode());
        if ($responseInfo->getStatusCode() == 404) {
            return $details;
        }

        $response = json_decode($responseInfo->getBody(), true);

        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();

        foreach ($response['Images'] as $imageResponse) {
            $image = new HotelImageGalleryItem();
            $image->RemoteUrl = $contentUrl . $imageResponse['ImageURL'];

            $alt = $imageResponse['SubCategory'];
            if (strlen($alt) > 255) {
                $alt = substr($alt, 0, 254);
            }

            $image->Alt = $alt;
            $items->add($image);
        }

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        // Content Address
        $address = new HotelAddress();
        $address->City = $hotelFromList->Address->City;
        $address->Details = $response['GeneralInfo']['Address']['Value'];

        $address->Latitude = $response['GeneralInfo']['Latitude']['Value'];
        $address->Longitude = $response['GeneralInfo']['Longitude']['Value'];

        // Content ContactPerson
        $contactPerson = new ContactPerson();
        $contactPerson->Email = $response['GeneralInfo']['WebAddress']['Value'];
        $contactPerson->Fax = $response['GeneralInfo']['FaxNumber']['Value'];
        $contactPerson->Phone = $response['GeneralInfo']['PhoneNumber']['Value'];

        $facilities = new FacilityCollection();

        // foreach ($response['facilities'] as $facilityResponse) {
        //     $facility = new Facility();
        //     $facility->Id = $facilityResponse['facilityCode'];
        //     $facility->Name = $facilityResponse['description']['content'];
        //     $facilities->add($facility);
        // }

        $description = '';
        foreach ($response['GeneralInfo'] as $info) {
            if ((isset($info['isExists']) && $info['isExists']) || !isset($info['isExists'])) {
                if (!empty($info['Caption']) && !empty($info['Value'])) {
                    $description .= '<p><b>' . $info['Caption'] . '</b><br>';
                    $description .= $info['Value'] . '</p>';
                }
            }
        }

        /*
        $description = '<b>Despre hotel<b><br>';
        $description .= '<b>HOTEL<b><br>';
        foreach ($response['Building'] as $building) {
            $description .= $building['BuildingType']['Caption'] . ': ' . $building['BuildingType']['Value'] . '<br>';
            $description .= $building['BuildingCount']['Caption'] . ': ' . $building['BuildingCount']['Value'] . '<br>';
            $description .= $building['BuildingFloorCount']['Caption'] . ': ' . $building['BuildingFloorCount']['Value'] . '<br>';
            $description .= $building['BuildingElevator']['Caption'] . ': ' . $building['BuildingElevator']['Value'] . '<br>';
        }
        $description .= '<b>ADRESA<b><br>';
        $description .= $response['GeneralInfo']['City'] . '<br>'
            .'<b>COD POȘTAL<b><br>'
            .$response['GeneralInfo']['PostCode']['Value'] . '<br>'
            .'<b>WEBSITE<b><br>'
            .$response['GeneralInfo']['WebAddress']['Value'] . '<br>'
            .'<b>NUMĂR DE TELEFON<b><br>'
            .$response['GeneralInfo']['PhoneNumber']['Value'] . '<br>'
            .'<b>FAX<b><br>'
            .$response['GeneralInfo']['FaxNumber']['Value'] . '<br>'
            .'<b>E-MAIL<b><br>'
            .$response['GeneralInfo']['MailAddress']['Value'] . '<br>'
            .'<b>CATEGORIE<b><br>'
            .$response['GeneralInfo']['HotelCategory']['Value'] . '<br>'
            .'<b>CONCEPT<b><br>'
            .$response['GeneralInfo']['HotelConcepts']['Value'] . '<br>'
            .'<b>FACEBOOK<b><br>'
            .$response['GeneralInfo']['Facebook']['Value'] . '<br>'
            .'<b>INSTAGRAM<b><br>'
            .$response['GeneralInfo']['Instagram']['Value'] . '<br>'
            .'<b>INSTAGRAM<b><br>'
            .$response['GeneralInfo']['Instagram']['Value'] . '<br>'
            .'<b>NUMĂR DE CAMERE ADAPTATE PENTRU PERSOANE CU DIZABILITAȚI<b><br>'
            .$response['GeneralInfo']['Instagram']['Value'] . '<br>'
        ;
        */

        $starsMap = $this->getHotelRatingMap();

        // Content
        $content = new HotelContent();
        $content->Content = $description;
        $content->ImageGallery = $imageGallery;

        $details->Id = $response['Header']['Hotel'];
        $details->Name = $response['Header']['HotelName'];
        $details->Address = $address;
        $details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Stars = $starsMap[$response['GeneralInfo']['HotelCategory']['ID']];
        $details->Content = $content;
        $details->Stars = $starsMap[$response['GeneralInfo']['HotelCategory']['ID']];
        $details->WebAddress = $response['GeneralInfo']['WebAddress']['Value'];
        return $details;
    }

    public function apiGetOffers(): array
    {
        $availabilities = [];

        if ($filter->serviceTypes->first() !== AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            return $availabilities;
        }

        OdeonValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateAvailabilityFilter($filter);

        $children = (int) $filter->rooms->get(0)->children;
        if ($filter->serviceTypes->first() !== AvailabilityFilter::SERVICE_TYPE_CHARTER || $children > 3) {
            return $availabilities;
        }

        $checkIn = $filter->checkIn;
        $days = $filter->days;
        $checkInDate = new DateTimeImmutable($checkIn);
        $checkOut = $checkInDate->modify('+' . $days . ' days')->format('Y-m-d');

        $departureCityId = $filter->departureCity;

        $cities = $this->apiGetCities();

        $departureRegionId = $cities->get($departureCityId)->County->Id;

        $countryId = $filter->countryId;

        $citiesList = '';
        if (!empty($filter->cityId)) {
            $citiesList = $filter->cityId;
        } elseif (!empty($filter->regionId)) {

            // get all cities from that region
            $citiesFromRegion = $cities->filter(fn(City $city) => $city->County->Id === $filter->regionId);
            $cityIdArr = [];
            $citiesFromRegion->each(function (City $city) use (&$cityIdArr) {
                $cityIdArr[] = $city->Id;
            });
            $citiesList = implode(',', $cityIdArr);
        }

        $hotelId = $filter->hotelId;

        $this->client = HttpClient::create(['max_duration' => 120]);

        $token = $this->getToken();

        $responseHotels = [];

        // loop in batches of 10 requests
        // get results
        // if error, do the request again
        // if error persists after 10 tries, continue with the next request in the batch
        // if the last result is not empty, continue loop

        $i = 0;
        $startIndex = 1;

        $headers = ['Content-Type' => 'application/json'];
        $batch = [];
        $t0 = microtime(true);

        do {
            $i++;
            // safety break
            if ($i >= 100) {
                break;
            }

            $requestArr = [
                'Command' => 'Product.Package.PackageSearch',
                'Token' => $token,
                'Parameters' => json_encode([
                    'BeginDate' => $checkIn,
                    'EndDate' => $checkIn,
                    'FromArea' => (int) $departureRegionId,
                    'ToCountry' => $countryId,
                    'ToPlace' => $citiesList,
                    'Hotel' => $hotelId,
                    'BeginNight' => $days,
                    'EndNight' => $days,
                    'Adult' => (int) $filter->rooms->get(0)->adults,
                    'Child' => $children,
                    'Child1Age' => isset($filter->rooms->get(0)->childrenAges) ? (int) $filter->rooms->get(0)->childrenAges->get(0) : 0,
                    'Child2Age' => isset($filter->rooms->get(0)->childrenAges) ? (int) $filter->rooms->get(0)->childrenAges->get(1) : 0,
                    'Child3Age' => isset($filter->rooms->get(0)->childrenAges) ? (int) $filter->rooms->get(0)->childrenAges->get(2) : 0,
                    'OnlyAvailableFlight' => true,
                    'NotShowStopSale' => false,
                    'ShowOnlyConfirm' => false,
                    'StartIndex' => $startIndex,
                    'PageSize' => 100,
                    'Currency' => self::EUR,
                ])
            ];

            $body = json_encode($requestArr);
            $responseObject = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $batch[] = [$responseObject, $options];

            $startIndex += 100;

            $response = true;

            $break = false;

            if ($i % 10 === 0) {
                foreach ($batch as $request) {
                    $t1 = microtime(true);
                    $dif = $t1 - $t0;
                    if ($dif > 50) {
                        break;
                        $break = true;
                    }
                    $responseObj = $request[0];
                    $optionsResp = $request[1];

                    $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $responseObj->getBody(), $responseObj->getStatusCode());
                    $responseData = json_decode($responseObj->getBody(), true);

                    if ($responseData === null) {
                        continue;
                    }

                    if ($responseData['Error']) {
                        $count = 0;
                        $doNotAddResponse = false;

                        while (true) {
                            $count++;
                            if ($responseData['Response'] === 'ERROR_SESSION_NOT_FOUND') {
                                $token = $this->cacheToken();
                                $optionsResp['Token'] = $token;
                            } elseif ($responseData['Response'] === 'ERROR_RETRY_REQUEST') {
                                sleep(1);
                                //continue;
                            } elseif ($responseData['Response'] === 'ERROR_NO_FREE_OBJECT_LEFT') {
                                $doNotAddResponse = true;
                                break;
                            }
                            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);

                            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $responseObj->getBody(), $responseObj->getStatusCode());
                            $responseData = json_decode($responseObj->getBody(), true);

                            if (!$responseData['Error']) {
                                $doNotAddResponse = false;
                                break;
                            }
                            if ($count >= 3) {
                                $doNotAddResponse = true;
                                break;
                            }
                        }
                        if ($doNotAddResponse) {
                            continue;
                        }
                    }
                    $response = json_decode($responseData['Details'], true) ?? [];
                    $responseHotels = array_merge($responseHotels, $response);
                }

                $batch = [];
            }
            if ($break) {
                break;
            }
        } while (!empty($response));

        if (empty($responseHotels)) {
            return $availabilities;
        }
        $requestArr = [
            'Command' => 'Product.Package.GetFlightInfo',
            'Token' => $token,
            'Parameters' => json_encode([
                'fromArea' => $departureRegionId,
                'toCountry' => $countryId,
                'month' => (int) $checkInDate->format('n'),
                'year' => (int) $checkInDate->format('Y')
            ])
        ];

        $optionsFlight['body'] = json_encode($requestArr);
        $optionsFlight['headers'] = ['Content-Type' => 'application/json'];

        $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $optionsFlight);
        $response = json_decode($responseObj->getBody(), true);

        if ($response['Error'] && $response['Response'] === 'ERROR_SESSION_NOT_FOUND') {
            $token = $this->cacheToken();
            $requestArr['Token'] = $token;
            $optionsFlight['body'] = json_encode($requestArr);
            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $optionsFlight);
            $response = json_decode($responseObj->getBody(), true);
        }
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $optionsFlight, $responseObj->getBody(), $responseObj->getStatusCode());

        if ($response['Error']) {
            throw new Exception($response['Response']);
        }

        $flights = json_decode($response['Details'], true);
        $airportMap = $this->getAirportMap();

        $flightMap = [];
        foreach ($flights as $flight) {

            if ($flight['directFlightType'] === 0 && $flight['returnFlightType'] === 0) {
                $departureDate = (new DateTime($flight['departureTakeOffDate']))->format('Y-m-d');
                $returnDepartureDate = (new DateTime($flight['returnDepartureDate']))->format('Y-m-d');

                if ($departureDate === $checkIn && $returnDepartureDate === $checkOut && $flight['allotmentStatus'] !== 0 && $flight['returnAllotmentStatus'] !== 0) {
                    $flightMap[] = $flight;
                }
            }
        }

        foreach ($responseHotels as $responseHotel) {

            $priceResp = $responseHotel['PackagePrice'];
            $prices = [$priceResp];

            if ($responseHotel['PromotionStatus'] === 1) {
                $prices[] = $responseHotel['WithOutPromoPrice'];
            }
            foreach ($flightMap as $flightOffer) {
                if ($flightOffer['route'] !== $responseHotel['AirportRoute']) {
                    continue;
                }

                foreach ($prices as $i => $price) {

                    $departureDateTime = (new DateTimeImmutable($responseHotel['FlightDate']));
                    $hotelCheckInDateTime = new DateTimeImmutable($responseHotel['HotelCheckInDate']);
                    $nights = $responseHotel['PackageNight'];
                    $returnDateTime = $hotelCheckInDateTime->add(new DateInterval('P' . $nights . 'D'));

                    $flightKey = $flightOffer['depFlightID'] . '-' . $flightOffer['returnFlightID'];

                    //$flightOffer = $flightMap[$flightKey];

                    $offer = new Offer();
                    $offer->departureFlightId = $flightOffer['depFlightID'];
                    $offer->returnFlightId = $flightOffer['returnFlightID'];

                    $currency = new Currency();
                    $currency->Code = 'EUR';

                    if ($responseHotel['HotelStopSaleStatus']) {
                        $offer->Availability = Offer::AVAILABILITY_NO;
                    } else {
                        if ($responseHotel['HotelAllotmentStatus']) {
                            $offer->Availability = Offer::AVAILABILITY_YES;
                        } else {
                            $offer->Availability = Offer::AVAILABILITY_ASK;
                        }
                    }

                    $roomId = $responseHotel['RoomID'];
                    $mealCode = $responseHotel['MealID'];

                    $initialPrice = $responseHotel['PackagePriceOld'];

                    $offer->CheckIn = $departureDateTime->format('Y-m-d');

                    $offer->Code = $responseHotel['HotelID'] . '~' . $flightKey . '~'
                        . $roomId . '~'
                        . $mealCode . '~'
                        . $offer->CheckIn . '~'
                        . $nights . '~'
                        . $price . '~'
                        . $filter->rooms->get(0)->adults
                        . (count($filter->rooms->first()->childrenAges) > 0 ? '~' . implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '');;

                    $offer->Currency = $currency;

                    $offer->Days = $nights;

                    $offer->InitialData = $departureRegionId;

                    $taxes = 0;

                    $offer->Net = $price;
                    $offer->Gross = $price;
                    $offer->InitialPrice = $initialPrice;
                    $offer->Comission = $taxes;

                    // Rooms
                    $room1 = new Room();
                    $room1->Id = $roomId;
                    $room1->CheckinBefore = $returnDateTime->format('Y-m-d');
                    $room1->CheckinAfter = $offer->CheckIn;

                    $room1->Currency = $offer->Currency;
                    $room1->Quantity = 1;
                    $room1->Availability = $offer->Availability;

                    if ($responseHotel['EarlyBookingEndDate']) {
                        $dateEB = new DateTime($responseHotel['EarlyBookingEndDate']);
                        $text = 'ÎNAINTE DE ' . $dateEB->format('d.m.Y');
                        $room1->InfoDescription = $text;
                    }

                    if ($responseHotel['PromotionStatus'] === 1 && $i === 0) {
                        $room1->InfoTitle = 'Pret promo';
                    }

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;
                    $merch->Title = $responseHotel['RoomName'];

                    $merchType = new RoomMerchType();
                    $merchType->Id = $roomId;
                    $merchType->Title = $merch->Title;
                    $merch->Type = $merchType;
                    $merch->Code = $merch->Id;
                    $merch->Name = $merch->Title;

                    $room1->Merch = $merch;

                    $offer->Rooms = new RoomCollection([$room1]);

                    $offer->Item = $room1;

                    $boardTypeName = $responseHotel['MealName'];

                    // MealItem Merch
                    $boardMerch = new MealMerch();
                    $boardMerch->Title = $boardTypeName;
                    $boardMerch->Id = $mealCode;

                    $boardMerchType = new MealMerchType();
                    $boardMerchType->Id = $mealCode;
                    $boardMerchType->Title = $boardTypeName;
                    $boardMerch->Type = $boardMerchType;

                    $mealItem = new MealItem();

                    // MealItem
                    $mealItem->Merch = $boardMerch;
                    $mealItem->Currency = $offer->Currency;

                    $offer->MealItem = $mealItem;

                    $flightDepartureDateTime = new DateTime($flightOffer['departureTakeOffDate']);
                    $flightArrivalDateTime = new DateTime($flightOffer['departureLandingDate']);
                    $flightReturnDateTime = new DateTime($flightOffer['returnTakeOffDate']);
                    $flightReturnArrivalDateTime = new DateTime($flightOffer['returnLandingDate']);

                    // departure transport item merch
                    $departureTransportMerch = new TransportMerch();
                    $departureTransportMerch->Title = "Dus: " . $flightDepartureDateTime->format('d.m.Y');
                    $departureTransportMerch->Category = new TransportMerchCategory();
                    $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                    $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $departureTransportMerch->DepartureTime = $flightDepartureDateTime->format('Y-m-d') . ' ' . $flightOffer['departureTakeOffTime'];
                    $departureTransportMerch->ArrivalTime = $flightArrivalDateTime->format('Y-m-d') . ' ' . $flightOffer['departureLandingTime'];

                    $departureTransportMerch->DepartureAirport = $flightOffer['departureTakeOffAirportCode'];
                    $departureTransportMerch->ReturnAirport = $flightOffer['departureLandingAirportCode'];

                    $departureTransportMerch->From = new TransportMerchLocation();
                    $departureTransportMerch->From->City = $cities->get($departureCityId);

                    $departureTransportMerch->To = new TransportMerchLocation();
                    $departureTransportMerch->To->City = $cities->get($airportMap[$flightOffer['departureLandingAirportCode']]);

                    $departureTransportItem = new DepartureTransportItem();
                    $departureTransportItem->Merch = $departureTransportMerch;
                    $departureTransportItem->Currency = $offer->Currency;
                    $departureTransportItem->DepartureDate = $flightDepartureDateTime->format('Y-m-d');
                    $departureTransportItem->ArrivalDate = $flightArrivalDateTime->format('Y-m-d');

                    // return transport item
                    $returnTransportMerch = new TransportMerch();
                    $returnTransportMerch->Title = "Retur: " . $flightReturnDateTime->format('d.m.Y');
                    $returnTransportMerch->Category = new TransportMerchCategory();
                    $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                    $returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $returnTransportMerch->DepartureTime = $flightReturnDateTime->format('Y-m-d') . ' ' . $flightOffer['returnTakeOffTime'];
                    $returnTransportMerch->ArrivalTime = $flightReturnArrivalDateTime->format('Y-m-d') . ' ' . $flightOffer['returnLandingTime'];

                    $returnTransportMerch->DepartureAirport = $flightOffer['returnTakeOffAirportCode'];
                    $returnTransportMerch->ReturnAirport = $flightOffer['returnLandingAirportCode'];

                    $returnTransportMerch->From = new TransportMerchLocation();
                    $returnTransportMerch->From->City = $departureTransportMerch->To->City;

                    $returnTransportMerch->To = new TransportMerchLocation();
                    $returnTransportMerch->To->City = $departureTransportMerch->From->City;

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

                    $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);

                    if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE) {
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                    }

                    // check if the hotel id is already in avail collection
                    $existingAvailability = $availabilities->get($responseHotel['HotelID']);
                    if ($existingAvailability === null) {
                        // creating new availability
                        $availability = new Availability();
                        $offers = new OfferCollection();
                        $availability->Id = $responseHotel['HotelID'];
                        if ($filter->showHotelName) {
                            $availability->Name = $responseHotel['HotelName'];
                        }

                        $offers->put($offer->Code, $offer);
                        $availability->Offers = $offers;
                    } else {
                        // adding offers to the existing availability
                        $availability = $existingAvailability;
                        $availability->Offers->put($offer->Code, $offer);
                    }

                    $availabilities->put($availability->Id, $availability);
                }
            }
        }

        return $availabilities;
    }

    public function apiDoBooking(): array
    {
        OdeonValidator::make()
            ->validateBookingUrl($this->post)
            ->validateBookHotelFilter($filter);

        $creds = $this->getClientIdAndBookingToken();
        $clientId = $creds[0];
        $token = $creds[1];

        $checkInDT = new DateTime($filter->Items->first()->Room_CheckinAfter);
        $ages = [];
        /** @var Passenger $passenger */
        foreach ($filter->Items->first()->Passengers as $passenger) {
            if (!$passenger->IsAdult) {
                $bdDT = new DateTime($passenger->BirthDate);
                $age = $checkInDT->diff($bdDT)->y;
                $ages[] = $age;
            }
        }

        $body = json_encode([
            'HotelId' => $filter->Items->first()->Hotel->InTourOperatorId,
            'MealId' => $filter->Items->first()->Board_Def_InTourOperatorId,
            'RoomId' => $filter->Items->first()->Room_Type_InTourOperatorId,
            'FromAreaId' => $filter->Items->first()->Offer_InitialData,
            'BeginDate' => $filter->Items->first()->Room_CheckinAfter,
            'Night' => $filter->Items->first()->Offer_Days,
            'CurrencyId' => self::EUR,
            'AdultCount' => $filter->Params->Adults->first(),
            'ChildAges' => $ages
        ]);
        $headers = [
            'ClientID' => $clientId,
            'Authorization' => $token,
            'Content-Type' => 'application/json'
        ];

        $url = $this->bookingUrl . '/Product/Package/GetPackageAlternativeFlights';

        $this->client = HttpClient::create();
        $responseObj = $this->client->request(Request::METHOD_POST, $url, $options);

        $this->showRequest(Request::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseData = json_decode($responseObj->getBody(), true);

        // cache expired
        if (isset($responseData['type']) && $responseData['type'] === 'Unauthorized') {
            // retry caching and request
            $creds = $this->cacheClientIdAndBookingToken();
            $clientId = $creds[0];
            $token = $creds[1];
            $headers = [
                'ClientID' => $clientId,
                'Authorization' => $token,
                'Content-Type' => 'application/json'
            ];
            $responseObj = $this->client->request(Request::METHOD_POST, $url, $options);

            $this->showRequest(Request::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
            $responseData = json_decode($responseObj->getBody(), true);
        }

        $flightData = null;
        foreach ($responseData as $alternative) {
            $departureFlight = $alternative['Flight']['DepartureFlight']['Segments'][0]['FlightDateId'];
            $returnFlight = $alternative['Flight']['ReturnFlight']['Segments'][0]['FlightDateId'];

            if (
                $departureFlight == $filter->Items->first()->Offer_departureFlightId &&
                $returnFlight == $filter->Items->first()->Offer_returnFlightId
            ) {
                $flightData = $alternative;
                break;
            }
        }
        if ($flightData === null) {
            throw new Exception('Flight not found!');
        }

        $bookingCode = $flightData['BookingCode'];

        $body = [
            'BookingCode' => $bookingCode
        ];
        $body = json_encode($body);

        $url = $this->bookingUrl . '/Reservation/General/InitReservation';
        $responseObjInit = $this->client->request(Request::METHOD_POST, $url, $options);

        $this->showRequest(Request::METHOD_POST, $url, $options, $responseObjInit->getBody(), $responseObjInit->getStatusCode());
        $tempReservation = json_decode($responseObjInit->getBody(), true);

        // cache expired
        if (isset($tempReservation['type']) && $tempReservation['type'] === 'Unauthorized') {
            // retry caching and request
            $creds = $this->cacheClientIdAndBookingToken();
            $clientId = $creds[0];
            $token = $creds[1];
            $headers = [
                'ClientID' => $clientId,
                'Authorization' => $token,
                'Content-Type' => 'application/json'
            ];
            $responseObj = $this->client->request(Request::METHOD_POST, $url, $options);

            $this->showRequest(Request::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
            $tempReservation = json_decode($responseObj->getBody(), true);
        }

        //$reservationId = $tempReservation['Reservation']['ID'];
        $touristsResponse = $tempReservation['Reservation']['ReservationTourists'];

        $i = 0;
        /** @var Passenger $passenger */
        foreach ($filter->Items->first()->Passengers as $passenger) {
            $touristsResponse[$i]['Name'] = $passenger->Firstname;
            $touristsResponse[$i]['SurName'] = $passenger->Lastname;
            if ($passenger->IsAdult) {
                $touristsResponse[$i]['Gender'] = ucfirst($passenger->Gender);
            }
            $touristsResponse[$i]['BirthDate'] = $passenger->BirthDate;
            $touristsResponse[$i]['Nationality'] = 27; // Romania - from email

            $i++;
        }

        $tempReservation['Reservation']['ReservationTourists'] = $touristsResponse;

        $bodyUpdate = ['Reservation' => $tempReservation];

        $body = json_encode($bodyUpdate);

        // update reservation
        $url = $this->bookingUrl . '/Reservation/General/UpdateReservation';
        $responseObjUpdate = $this->client->request(Request::METHOD_POST, $url, $options);

        $this->showRequest(Request::METHOD_POST, $url, $options, $responseObjUpdate->getBody(), $responseObjUpdate->getStatusCode());
        $responseDataUpdate = json_decode($responseObjUpdate->getBody(), true);

        // cache expired
        if (isset($responseDataUpdate['type']) && $responseDataUpdate['type'] === 'Unauthorized') {
            // retry caching and request
            $creds = $this->cacheClientIdAndBookingToken();
            $clientId = $creds[0];
            $token = $creds[1];
            $headers = [
                'ClientID' => $clientId,
                'Authorization' => $token,
                'Content-Type' => 'application/json'
            ];
            $responseObj = $this->client->request(Request::METHOD_POST, $url, $options);

            $this->showRequest(Request::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
            $responseDataUpdate = json_decode($responseObj->getBody(), true);
        }

        $body = json_encode($responseDataUpdate);

        $url = $this->bookingUrl . '/Reservation/General/CommitReservation';
        $responseObjCommit = $this->client->request(Request::METHOD_POST, $url, $options);

        $this->showRequest(Request::METHOD_POST, $url, $options, $responseObjCommit->getBody(), $responseObjCommit->getStatusCode());
        $responseData = json_decode($responseObjCommit->getBody(), true);

        // cache expired
        if (isset($responseData['type']) && $responseData['type'] === 'Unauthorized') {
            // retry caching and request
            $creds = $this->cacheClientIdAndBookingToken();
            $clientId = $creds[0];
            $token = $creds[1];
            $headers = [
                'ClientID' => $clientId,
                'Authorization' => $token,
                'Content-Type' => 'application/json'
            ];
            $responseObj = $this->client->request(Request::METHOD_POST, $url, $options);

            $this->showRequest(Request::METHOD_POST, $url, $options, $responseObj->getBody(), $responseObj->getStatusCode());
            $responseData = json_decode($responseObj->getBody(), true);
        }

        $booking = new Booking();
        if (!isset($responseData['voucher'])) {
            return [$booking, 'Error, voucher not received'];
        }
        $reservationId = $responseData['voucher'];

        $booking->Id = $reservationId;

        return [$booking, $responseObjCommit->getBody()];
    }

    // private function getBookingToken(): string
    // {
    //     $token = Utils::getFromCache($this, 'bookingToken');
    //     if ($token === null) {
    //         $token = $this->cacheBookingToken();
    //     }
    //     return $token;
    // }

    private function getClientIdAndBookingToken(): array
    {
        $id = Utils::getFromCache($this, 'clientId' . $this->username);
        $bookingToken = Utils::getFromCache($this, 'bookingToken' . $this->username);
        if ($id === null) {
            $creds = $this->cacheClientIdAndBookingToken();
            $id = $creds[0];
            $bookingToken = $creds[1];
        }

        return [$id, $bookingToken];
    }

    #overrride
    public function apiTestConnection(): bool
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookingUrl($this->post);

        $this->client = HttpClient::create();

        $optionsLogin['body'] = json_encode([
            'Command' => 'Login',
            'Token' => null,
            'Parameters' => json_encode([
                'User' => $this->username,
                'Password' => $this->password
            ])
        ]);
        $optionsLogin['headers'] = ['Content-Type' => 'application/json'];

        $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $optionsLogin);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $optionsLogin, $responseObj->getBody(), $responseObj->getStatusCode());
        $response = json_decode($responseObj->getBody(), true);

        $requestArr = [
            'Command' => 'General.Geography.ListGeography',
            'Token' => $response['Token'],
        ];
        $body = json_encode($requestArr);
        $headers = ['Content-Type' => 'application/json'];

        $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseGeo = json_decode($responseObj->getBody(), true);
        $geo = true;
        if ($responseGeo['Error'] && $responseGeo['Response'] === 'ERROR_SESSION_NOT_FOUND') {

            try {
                $token = $this->cacheToken();
            } catch (Exception $e) {
                return false;
            }
            $requestArr['Token'] = $token;
            $body = json_encode($requestArr);
            $responseObj = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $options, $responseObj->getBody(), $responseObj->getStatusCode());
            $responseGeo = json_decode($responseObj->getBody(), true);
        }

        $optionsLogin['body'] = json_encode([
            'User' => $this->username,
            'Password' => $this->password
        ]);
        $optionsLogin['headers'] = ['Content-Type' => 'application/json'];
        $url = $this->bookingUrl . '/Authentication/Login';

        $responseObj = $this->client->request(Request::METHOD_POST, $url, $optionsLogin);
        $this->showRequest(Request::METHOD_POST, $url, $optionsLogin, $responseObj->getBody(), $responseObj->getStatusCode());
        $responseBook = json_decode($responseObj->getBody(), true);

        $ok = false;
        if (!empty($response['Token']) && !empty($responseBook['AgencyStaffID']) && $geo) {
            $ok = true;
        }
        return $ok;
    }
}