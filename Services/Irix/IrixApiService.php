<?php

namespace Integrations\Irix;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
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
use App\Handles;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\[];
use App\Support\Ftp\FtpsClient;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Log;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use Utils\Utils;

class IrixApiService extends AbstractApiService
{
    private function getToken(): string
    {
        $url = $this->apiUrl . '/oauth2/token';

        $client = HttpClient::create();

        $optionsArr = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->username,
            'client_secret' => $this->password,
            'scope' => 'read:resellerCredit read:mapping read:hotels-search write:hotels-book'
        ];
        $options['headers'] = [
            'Content-Type' => 'application/json'
        ];
        $options['body'] = json_encode($optionsArr);

        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getBody(), $resp->getStatusCode());

        $content = json_decode($resp->getBody(), true);
        return $content['access_token'];
    }

    public function apiGetCountries(): array
    {
        $token = $this->getToken();

        $countries = [];

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/countries?perPage=100', $options);
        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/countries', $options, $resp->getBody(), $resp->getStatusCode());

        $responses = [];
        $countriesResp = json_decode($resp->getBody(), true);
        $responses[] = $countriesResp;
        
        $pages = $countriesResp['_page_count'];
        $page = $countriesResp['_page'];

        while ($page < $pages) {
            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/countries?perPage=100&page='.$page + 1, $options);
            $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/countries', $options, $resp->getBody(), $resp->getStatusCode());
    
            $countriesResp = json_decode($resp->getBody(), true);
            $responses[] = $countriesResp;
            
            $pages = $countriesResp['_page_count'];
            $page = $countriesResp['_page'];
        }

        foreach ($responses as $response) {
            foreach ($response['_embedded']['countries'] as $countryResp) {
                if ($countryResp['iso'] === null) {
                    continue;
                }
                $country = Country::create($countryResp['id'], $countryResp['iso'], $countryResp['name']);
                $countries->put($country->Id, $country);
            }
        }

        return $countries;
    }

    public function apiGetCities(?CitiesFilter $filter = null): array
    {
        $cache = 'cities';

        $json = Utils::getFromCache($this, $cache);
        $cities = [];

        if ($json === null) {
            
            $token = $this->getToken();

            $client = HttpClient::create();

            $options['headers'] = [
                'Authorization' => 'Bearer ' . $token
            ];

            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/cities?perPage=100', $options);
            $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/cities', $options, $resp->getBody(), $resp->getStatusCode());

            $responses = [];
            $resp = json_decode($resp->getBody(), true);
            $responses[] = $resp;

            $pages = $resp['_page_count'];
            $page = $resp['_page'];

            while ($page < $pages) {
                $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/cities?perPage=100&page='.$page + 1, $options);
                $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/cities', $options, $resp->getBody(), $resp->getStatusCode());
        
                $resp = json_decode($resp->getBody(), true);
                Log::debug($page);
                $responses[] = $resp;
                
                $pages = $resp['_page_count'];
                $page = $resp['_page'];
            }

            $countries = $this->apiGetCountries();

            foreach ($responses as $response) {
                foreach ($response['_embedded']['cities'] as $cityResp) {
                    $country = $countries->get($cityResp['countryId']);

                    if ($country === null) {
                        continue;
                    }

                    $city = City::create($cityResp['id'], $cityResp['name'], $country);
                    $cities->put($city->Id, $city);
                }
            }

            Utils::writeToCache($this, $cache, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }

        return $cities;
    }

    /*
    public function apiGetRegions(): []
    {
        $cache = 'regions';

        $json = Utils::getFromCache($this, $cache);
        $regions = [];

        if ($json === null) {
            
            $token = $this->getToken();

            $client = HttpClient::create();

            $options['headers'] = [
                'Authorization' => 'Bearer ' . $token
            ];

            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/regions?perPage=100', $options);
            $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/regions', $options, $resp->getBody(), $resp->getStatusCode());

            $responses = [];
            $resp = json_decode($resp->getBody(), true);
            $responses[] = $resp;
            dd($resp);

            $pages = $resp['_page_count'];
            $page = $resp['_page'];

            while ($page < $pages) {
                $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/cities?perPage=100&page='.$page + 1, $options);
                $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/cities', $options, $resp->getBody(), $resp->getStatusCode());
        
                $resp = json_decode($resp->getBody(), true);
                Log::debug($page);
                $responses[] = $resp;
                
                $pages = $resp['_page_count'];
                $page = $resp['_page'];
            }

            $countries = $this->apiGetCountries();

            foreach ($responses as $response) {
                foreach ($response['_embedded']['cities'] as $cityResp) {
                    $country = $countries->get($cityResp['countryId']);

                    if ($country === null) {
                        continue;
                    }

                    $city = City::create($cityResp['id'], $cityResp['name'], $country);
                    $cities->put($city->Id, $city);
                }
            }

            Utils::writeToCache($this, $cache, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }

        return $regions;
    }
    */
 
    public function apiGetHotels(?HotelsFilter $filter = null): []
    {
        if (empty($filter->CityId)) {
            throw new Exception('CityId is required');
        }

        $token = $this->getToken();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/hotels?perPage=100&cityId='.$filter->CityId, $options);
        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/hotels?perPage=100&cityId='.$filter->CityId, $options, $resp->getBody(), $resp->getStatusCode());

        $responses = [];
        $countriesResp = json_decode($resp->getBody(), true);
        $responses[] = $countriesResp;
        
        $pages = $countriesResp['_page_count'];
        $page = $countriesResp['_page'];

        while ($page < $pages) {
            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/hotels?perPage=100&cityId='.$filter->CityId.'&page='.$page + 1, $options);
            $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/mapping/v1/hotels?perPage=100&cityId='.$filter->CityId.'&page='.$page + 1, $options, $resp->getBody(), $resp->getStatusCode());
    
            $resp = json_decode($resp->getBody(), true);
            $responses[] = $resp;
            
            $pages = $resp['_page_count'];
            $page = $resp['_page'];
        }

        $hotels = [];

        $cities = $this->apiGetCities();

        foreach ($responses as $response) {
            foreach ($response['_embedded']['hotels'] as $hotelResp) {
                
                $city = $cities->get($hotelResp['city']['id']);

                $hotel = Hotel::create(
                    $hotelResp['id'],
                    $hotelResp['name'],
                    $city,
                    $hotelResp['stars'],
                    null,
                    $hotelResp['address'],
                    $hotelResp['geolocation']['latitude'],
                    $hotelResp['geolocation']['longitude'],
                    null,
                    null,
                    $hotelResp['telephone'],
                    $hotelResp['email'],
                    $hotelResp['fax']
                );
                $hotels->add($hotel);
            }
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

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateIndividualOffersFilter($filter);
        
        $availabilities = [];

        $token = $this->getToken();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ];

        $body = [
            'checkIn' => $filter->checkIn,
            'checkOut' => $filter->checkOut,
            'occupancy' => [
                'leaderNationality' => 158,
                'rooms' => [
                    [
                        'adults' => (int)$filter->rooms->first()->adults,
                        'childrenAges' => $post['args'][0]['rooms'][0]['childrenAges']
                    ]
                ]
            ],
            'language' => 'ro_RO',
            'sellingChannel' => 'B2B'
        ];

        if (!empty($filter->hotelId)) {
            $body['destination']['accommodation'] = [$filter->hotelId];
        } else {
            $body['destination']['city']['id'] = $filter->cityId;
        }

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/api/hotels/v1/search/start', $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/api/hotels/v1/search/start', $options, $resp->getBody(), $resp->getStatusCode());

        $respAsync = json_decode($resp->getBody(), true);

        $srk = $respAsync['srk'];
        $token = $respAsync['tokens']['async'];

        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/hotels/v1/search/async/'.$srk.'?token='.$token, $options);
        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/hotels/v1/search/async/'.$srk.'?token='.$token, $options, $resp->getBody(), $resp->getStatusCode());

        $resp = json_decode($resp->getBody(), true);
        $token = $resp['tokens']['next'];

        $results = [];
        $i = 0;
        while ($token !== null) {
            $i++;
            sleep(1);
            if ($i > 500) {
                break;
            }

            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/hotels/v1/search/async/'.$srk.'?token='.$token, $options);
            $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/hotels/v1/search/async/'.$srk.'?token='.$token, $options, $resp->getBody(), $resp->getStatusCode());

            $resp = json_decode($resp->getBody(), true);

            if ($resp['tokens']['next'] === null) {
                if (!empty($resp['hotels'])) {

                    $results[] = $resp;
                }

                //Log::warning($this->handle . ' token error ' . json_encode($this->post));
                break;
            } else {
                $token = $resp['tokens']['next'];

                if (empty($resp['hotels'])) {
                    continue;
                } else {
                    $results[] = $resp;
                }
            }
        }

        foreach ($results as $result) {
            foreach ($result['hotels'] as $hotelResp) {
                $offers = new OfferCollection();
                foreach ($hotelResp['offers'] as $offerSet) {

                    foreach ($offerSet['packages'] as $package) {
                        $packageToken = $package['packageToken'];

                        foreach ($package['packageRooms'] as $packageRoom) {
                            foreach ($packageRoom['roomReferences'] as $roomRef) {
                                $roomReferenceMap[$roomRef['roomCode']] = [$roomRef['roomToken'], $packageToken];
                            }
                        }
                    }

                    foreach ($offerSet['rooms'] as $roomResp) {
                        if (!isset($roomResp['price']['selling']['currency'])) {
                            continue;
                        }

                        
                        //dump($hotelResp);
                        //dump($roomResp);
                        

                        $bookingDataJson = json_encode([
                            'srk' => $srk,
                            'hotelIndex' => $hotelResp['index'],
                            'offerIndex' => $offerSet['id'],
                            'token' => $respAsync['tokens']['results'],
                            'packageToken' => $roomReferenceMap[$roomResp['index']][1],
                            'roomTokens' => $roomReferenceMap[$roomResp['index']][0]
                        ]);

                        $offer = Offer::createIndividualOffer(
                            $hotelResp['index'],
                            md5($roomResp['name']),
                            md5($roomResp['name']),
                            $roomResp['name'],
                            $roomResp['boardBasis'],
                            $roomResp['board'],
                            new DateTimeImmutable($filter->checkIn),
                            new DateTimeImmutable($filter->checkOut),
                            $filter->rooms->first()->adults,
                            $post['args'][0]['rooms'][0]['childrenAges']->toArray(),
                            $roomResp['price']['selling']['currency'],
                            $roomResp['price']['selling']['value'],
                            $roomResp['price']['selling']['value'],
                            $roomResp['price']['selling']['value'],
                            0,
                            $roomResp['status'] === 'OK' ?  Offer::AVAILABILITY_YES : Offer::AVAILABILITY_ASK,
                            null,
                            null,
                            $bookingDataJson
                        );

                        $offers->put($offer->Code, $offer);
                    }
                }
                if (empty($offers->toArray())) {
                    continue;
                }

                $availability = $availabilities->get($hotelResp['index']);

                if ($availability === null) {
                    $availability = Availability::create($hotelResp['index'], $filter->showHotelName, $hotelResp['name']);
                    $availability->Offers = $offers;
                } else {
                    $existingOffers = $availability->Offers;
                    $existingOffers = $existingOffers->merge($offers);
                    $availability->Offers = $existingOffers;
                }

                $availabilities->put($availability->Id, $availability);
            }
        }

        return $availabilities;
    }

    public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    {

        $cancelPol = new OfferCancelFeeCollection();

        $token = $this->getToken();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ];

        $bookingData = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);

        $body = [
            'packageToken' => $bookingData['packageToken'],
            'roomTokens' => [$bookingData['roomTokens']]
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, 
            $this->apiUrl . '/api/hotels/v1/search/results/'.$bookingData['srk'].'/hotels/'.$bookingData['hotelIndex'].'/offers/'.$bookingData['offerIndex'].'/availability?token='.$bookingData['token'], 
            $options
        );
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/api/hotels/v1/search/results/'.$bookingData['srk'].'/hotels/'.$bookingData['hotelIndex'].'/offers/'.$bookingData['offerIndex'].'/availability?token='.$bookingData['token'], $options, $resp->getBody(), $resp->getStatusCode());

        $respAsync = json_decode($resp->getBody(), true);

        foreach ($respAsync['cancellationPolicy']['policies'] as $policy) {
            if (isset($policy['charge'])) {
                $cp = new OfferCancelFee();
                $currency = new Currency();
                $currency->Code = $policy['charge']['currency'];
                $cp->Currency = $currency;
                $cp->Price = $policy['charge']['value'];
                $cp->DateStart = $policy['date'];

                $cp->DateEnd = '';
                $cancelPol->add($cp);
            }
        }

        return [
            $cancelPol, 
            [], 
            $respAsync['rooms'][0]['status'] === 'OK' ?  Offer::AVAILABILITY_YES : Offer::AVAILABILITY_ASK, 
            $respAsync['price']['selling']['value'],
            $respAsync['price']['selling']['value'],
            $respAsync['price']['selling']['currency']
        ];
    }

    /*
    public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    {
        $client = HttpClient::create();
        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}"),
            'Content-Type' => 'application/json'
        ];
        $body = [
            'hash' => $post['args'][0]['OriginalOffer']['bookingDataJson']
        ];

        $url = $this->apiUrl . '/serp/prebook/';

        $options['body'] = json_encode($body);
        $req = $client->request(HttpClient::METHOD_POST, $url, $options);

        $content = $req->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

        $contentArr = json_decode($content, true);
        dd($contentArr);

        $offFees = new OfferCancelFeeCollection();
        $offPayments = new OfferPaymentPolicyCollection();

        $offAvailability = Offer::AVAILABILITY_YES;

        $offPrice = 0;
        $offInitialPrice = 0;
        $offCurrency = 0;

        return [$offFees, $offPayments, $offAvailability, $offPrice, $offInitialPrice, $offCurrency];
    }
        */

    // todo: testat cu servicii extra
    // verificat id-urile de camera
    public function apiDoBooking(BookHotelFilter $filter): array
    {
    
        $token = $this->getToken();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ];

        $bookingData = json_decode($post['args'][0]['Items'][0]['Offer_bookingDataJson'], true);

        $body = [
            'packageToken' => $bookingData['packageToken'],
            'roomTokens' => [$bookingData['roomTokens']]
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, 
            $this->apiUrl . '/api/hotels/v1/search/results/'.$bookingData['srk'].'/hotels/'.$bookingData['hotelIndex'].'/offers/'.$bookingData['offerIndex'].'/availability?token='.$bookingData['token'], 
            $options
        );
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/api/hotels/v1/search/results/'.$bookingData['srk'].'/hotels/'.$bookingData['hotelIndex'].'/offers/'.$bookingData['offerIndex'].'/availability?token='.$bookingData['token'], $options, $resp->getBody(), $resp->getStatusCode());

        $respAsync = json_decode($resp->getBody(), true);

        $travellers = [];
        $i = 0;
        /** @var Passenger $passenger */
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $passenger) {
            $i++;
            $lead = false;
            if ($i === 1) {
                $lead = true;
            }
            $travellers[] = [
                'reference' => uniqid(),
                'type' => $passenger->Type,
                'title' => $passenger['Gender'] === 'male' ? 'mr' : 'mrs',
                'firstName' => $passenger['Firstname'],
                'lastName' => $passenger['Lastname'],
                'birthDate' => $passenger['BirthDate'],
                'lead' => $lead
            ];
        }

        $body = [
            'availabilityToken' => $respAsync['availabilityToken'],
            'clientRef' => uniqid(),
            'rooms' => [
                [
                    'packageRoomToken' => $bookingData['roomTokens'],
                    'travelers' => $travellers
                ]
            ],
            'payment' => [
                'method' => 'credit'
            ]
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, 
            $this->apiUrl . '/api/hotels/v1/search/results/'.$bookingData['srk'].'/hotels/'.$bookingData['hotelIndex'].'/offers/'.$bookingData['offerIndex'].'/book?token='.$bookingData['token'], 
            $options
        );
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/api/hotels/v1/search/results/'.$bookingData['srk'].'/hotels/'.$bookingData['hotelIndex'].'/offers/'.$bookingData['offerIndex'].'/book?token='.$bookingData['token'], $options, $resp->getBody(), $resp->getStatusCode());

        $respAsync = json_decode($resp->getBody(), true);

        //dd($respAsync);

        return [];
    }
}