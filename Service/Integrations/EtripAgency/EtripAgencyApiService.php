<?php

namespace Integrations\EtripAgency;

use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Hotel;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\Response\ResponseInterface;
use App\Support\Request;
use IntegrationSupport\AbstractApiService;

class EtripAgencyApiService extends AbstractApiService
{
    //private string $username;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function apiDoBooking(BookHotelFilter $filter): array
    {
        return [];
    }

    public function apiGetCities(CitiesFilter $params = null): CityCollection
    {
        $url = $this->apiUrl . '/v2/geography/city/query';
        
        $body = [
            'pagination' => [
                'page' => 0,
                'page_size' => 20000
            ],
            'name_like' => ''
        ];
        $options['body'] = json_encode($body);
        
        $json = $this->request($url, HttpClient::METHOD_POST, $options)->getContent();
      
        $response = json_decode($json, true)['results'];
        

        $cities = new CityCollection();

        foreach ($response as $countryResponse) {
            $city = new City();
            $city->Id = $countryResponse['id'];
            $city->Name = $countryResponse['name'];
            $cities->add($city);
        }
        return $cities;
    }

    public function apiGetCountries(): CountryCollection
    {   
        $client = HttpClient::create();

        $url = $this->apiUrl . '/v2/geography/country/query';
        
        $body = [
            'pagination' => [
                'page' => 0,
                'page_size' => 1000
            ],
            'name_like' => ''
        ];

        $options['body'] = json_encode($body);

        $options['headers'] = [
            'Authorization' => $this->password
        ];

        $countriesJson = $client->request(HttpClient::METHOD_POST, $url, $options)->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $countriesJson, 0);
        
        $countriesResponse = json_decode($countriesJson, true)['results'];

        $countries = new CountryCollection();

        foreach ($countriesResponse as $countryResponse) {
            $country = new Country();
            $country->Code = $countryResponse['iata_code'];
            $country->Id = $countryResponse['id'];
            $country->Name = $countryResponse['name'];
            $countries->add($country);
        }
        return $countries;
    }

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        return new Hotel();
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        return new HotelCollection();
    }

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        return new AvailabilityCollection();
    }
    
    public function request(string $url, string $method = HttpClient::METHOD_GET, array $options = []): ResponseInterface
    {
        $options['headers'] = [
            'Authorization: ' . $this->password,
        ];

        $httpClient = HttpClient::create();
        $response = $httpClient->request($method, $url, $options);
        if ($this->request->getPostParam('get-raw-data')) {
            $request = new Request();
            //$this->responses->add($response);
        }
        return $response;
    }
}
