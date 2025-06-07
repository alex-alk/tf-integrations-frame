<?php

namespace Integrations\Sphinx;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
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
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Entities\Tours\Location;
use App\Entities\Tours\Stage;
use App\Entities\Tours\StageCollection;
use App\Entities\Tours\StageContent;
use App\Entities\Tours\Tour;
use App\Entities\Tours\TourCollection;
use App\Entities\Tours\TourContent;
use App\Entities\Tours\TourImageGallery;
use App\Entities\Tours\TourImageGalleryItem;
use App\Entities\Tours\TourImageGalleryItemCollection;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Filters\PaymentPlansFilter;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\[];
use App\Support\Collections\StringCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Log;
use DateTime;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\ResponseConverter;
use Utils\Utils;

class SphinxApiService extends AbstractApiService
{
    public function apiGetCountries(): array
    {
        $countries = [];

        $cities = $this->apiGetCities();
        foreach ($cities as $city) {
            $countries->put($city->Country->Id, $city->Country);
        }
        
        return $countries;
    }

    public function apiGetRegions(): []
    {
        $cities = $this->apiGetCities();

        $regions = [];
        foreach ($cities as $city) {
            $region = $city->County;
            if ($region !== null) {
                $regions->put($region->Id, $region);
            }
        }
        return $regions;
    }

    private function getLocationsFromTours(): array
    {
        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode
        ];

        $requests = [];

        $url = $this->apiUrl . '/api/v1/static/circuits';

        $resp = $client->request(HttpClient::METHOD_GET, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $resp->getStatusCode());

        $requests[] = [$resp, $options];

        $respArr = json_decode($content, true);

        $resp = $respArr['data'];

        foreach ($resp as $tour) {

            foreach ($tour['destinations'] as $destination) {
                $list[$destination] = $destination;
            }

            foreach ($tour['departures'] as $departure) {
                $list[$departure['destination_id']] = $departure['destination_id'];
            }
        }
        
        return $list;
    }

    private function getLocationsFromAllHotels(): array
    {
        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode
        ];

        $requests = [];

        $url = $this->apiUrl . '/api/v1/static/hotels?page=1&for_travelfuse';

        $resp = $client->request(HttpClient::METHOD_GET, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $resp->getStatusCode());

        $requests[] = [$resp, $options];

        $respArr = json_decode($content, true);

        $pages = $respArr['meta']['last_page'];

        for ($i = 2; $i < $pages + 1; $i++) {
            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/hotels?for_travelfuse&page='.$i, $options);
            $requests[] = [$resp, $options];
        }

        $list = [];
        foreach ($requests as $response) {
            $content = $response[0]->getBody();
            $respArr = json_decode($content, true);

            $hotelsResp = $respArr['data'];

            foreach ($hotelsResp as $hotelResp) {
                //$list[] = [$hotelResp['destination_id'], $hotelResp];
                $list[$hotelResp['destination_id']] = $hotelResp['destination_id'];
            }
        }
        return $list;
    }

    public function apiGetCities(CitiesFilter $params = null): array
    {

        $file = 'cities';
        $json = Utils::getFromCache($this->handle, $file);

        if ($json === null) {

            $allCities = [];

            $client = HttpClient::create();

            $allCitiesFromTours = $this->getLocationsFromTours();

            $allHotels = $this->getLocationsFromAllHotels();

            $options['headers'] = [
                'Authorization' => 'Bearer ' . $this->apiCode
            ];

            $requests = [];
            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/destinations?page=1', $options);
            $requests[] = [$resp, 1];

            $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/destinations?page=1', $options, $resp->getBody(), $resp->getStatusCode());
            $respArr = json_decode($resp->getBody(), true);

            $pages = $respArr['meta']['last_page'];

            for ($i = 2; $i < $pages + 1; $i++) {
                $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/destinations?page='.$i, $options);
                $requests[] = [$resp, $i];
            }

            $locationsArr = [];

            foreach ($requests as $response) {
                $content = $response[0]->getBody();
                $respArr = json_decode($content, true);

                $locations = $respArr['data'];
                foreach ($locations as $location) {
                    $locationsArr[$location['id']] = $location;
                }
            }
        
            $geography = [];

            //$data = [];
            // get countries
            foreach ($locationsArr as $location) {

                // if ($location['type'] != 'destination') {
                //     foreach ($allHotels as $city) {
                //         if ($location['id'] === $city[0]) {
                //             $data[] = ['hotel id' => $city[1]['id'], 'hotel name' => $city[1]['name'], 'hotel destination id' => $city[1]['destination_id'],'location type' => $location['type'], 'location name' => $location['name']];
                //             // dump('hotel:', $city[1]);
                //             // dump('location:',$location);
                //         }
                //     }
                // }
                
                if ($location['type'] === 'country') {
                    $geography[$location['country_code']] = $location;
                }    
                
            }
            //Utils::createCsv('hotels.csv', ['hotel id', 'hotel name', 'hotel destination id', 'location type', 'location name'],$data);

            //die;

            $firstLevelRegions = [];
            // get first level regions
            foreach ($locationsArr as $location) {
                
                if ($location['type'] === 'region') {
                    // get regions with no parent
                    if ($location['parent_id'] === null) {
                        $geography[$location['country_code']]['regions'][$location['id']] = $location;
                        $firstLevelRegions[$location['id']] = $location;
                    } else {
                        // get regions with country parent
                        $parent = $locationsArr[$location['parent_id']];
                        if ($parent['type'] === 'country') {
                            $geography[$location['country_code']]['regions'][$location['id']] = $location;
                            $firstLevelRegions[$location['id']] = $location;
                        }
                    }
                    
                }
                
            }

            $secondLevelRegions = [];
            // get second level regions
            foreach ($locationsArr as $location) {
                
                // a region that has a parent in the first level region
                if ($location['type'] === 'region' && isset($firstLevelRegions[$location['parent_id']])) {
                    
                    $parent = $firstLevelRegions[$location['parent_id']];

                    $geography[$location['country_code']]['regions'][$parent['id']]['regions'][$location['id']] = $location;

                    $secondLevelRegions[$location['id']] = $location;
                }
                
            }

            // get third level regions, should be none
            foreach ($locationsArr as $location) {
                
                // a region that has a parent in the second level region
                if ($location['type'] === 'region' && isset($secondLevelRegions[$location['parent_id']])) {
                    Log::warning($this->handle . ': there are third level regions!');
                }
            }

            foreach ($locationsArr as $location) {
                
                if ($location['type'] === 'destination') {
                    if (isset($secondLevelRegions[$location['parent_id']])) {
                        // put city on second level region
                        $secondLevelRegion = $secondLevelRegions[$location['parent_id']];
                        
                        $firstLevelRegion = $firstLevelRegions[$secondLevelRegion['parent_id']];

                        $geography[$location['country_code']]['regions'][$firstLevelRegion['id']]['regions'][$secondLevelRegion['id']]['cities'][$location['id']] = $location;
                    } elseif(isset($firstLevelRegions[$location['parent_id']])) {
                        // put city on first level region
                        $firstLevelRegion = $firstLevelRegions[$location['parent_id']];

                        $geography[$location['country_code']]['regions'][$firstLevelRegion['id']]['cities'][$location['id']] = $location;
                    } else {
                        // put city on country
                        $geography[$location['country_code']]['cities'][$location['id']] = $location;
                    }
                }
            }

            foreach ($geography as $country) {
                $countryObj = Country::create($country['id'], $country['country_code'], $country['name']);

                // get cities from country
                if (isset($country['cities'])) {
                    foreach ($country['cities'] as $city) {
                        $cityObj = City::create($city['id'], $city['name'], $countryObj);
                        $allCities->add($cityObj);
                    }
                }

                if (isset($country['regions'])) {
                    foreach ($country['regions'] as $region) {
                        if (!isset($region['id'])) {
                            continue;
                        }
                        $regionObj = Region::create($region['id'], $region['name'], $countryObj);
                        // get cities from region
                        if (isset($region['cities'])) {
                            foreach ($region['cities'] as $city) {
                                $cityObj = City::create($city['id'], $city['name'], $countryObj, $regionObj);
                                $allCities->add($cityObj);
                            }
                        }
                        // get regions from region
                        if (isset($region['regions'])) {
                            foreach ($region['regions'] as $region2) {
                                $region2Obj = Region::create($region2['id'], $region2['name'], $countryObj);
                                // get cities from region2
                                if (isset($region2['cities'])) {
                                    foreach ($region2['cities'] as $city) {
                                        $cityObj = City::create($city['id'], $city['name'], $countryObj, $region2Obj);
                                        $allCities->add($cityObj);
                                    }
                                }
                            }
                        }

                    }
                }
            }

            $cities = [];
            foreach ($allCities as $cityInLoop) {
                // dump($cities);
                // dump($cityInLoop);
                // dump($allHotels);
                // die;
                if (in_array($cityInLoop->Id, $allHotels) || in_array($cityInLoop->Id, $allCitiesFromTours)) {
                    $cities->put($cityInLoop->Id, $cityInLoop);
                }
            }
            
            Utils::writeToCache($this->handle, $file, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }
        return $cities;
    }

    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    {
        $bookingData = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);
        $offerId = $bookingData['offer_id'];

        $client = HttpClient::create();

        $url = $this->apiUrl . '/api/v1/hotels/verify?offer_id=' . $offerId;
            
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode,
            'Content-Type' => 'application/json'
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $resp->getStatusCode());

        $data = json_decode($content, true)['data'];

        $currency = new Currency();
        $currency->Code = $data['pricing']['currency'];
    
        $cancelPols = new OfferCancelFeeCollection();

        $i = 0;
        foreach ($data['cancellation_fees']['rules'] as $rule) {
            $cp = new OfferCancelFee();
            $cp->Currency = $currency;
            $cp->Price = $rule['value'];
            $cp->DateStart = $rule['since'];

            if (isset($data['cancellation_fees']['rules'][$i+1])) {
                $cp->DateEnd = (new DateTime($data['cancellation_fees']['rules'][$i+1]['since']))->modify('-1 day')->format('Y-m-d');
            } else {
                $cp->DateEnd = (new DateTime($data['check_in']))->format('Y-m-d');
            }

            $cancelPols->add($cp);
            $i++;
        }

        return $cancelPols;
    }

    public function getOfferPaymentPlans(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    {
        $bookingData = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);
        $offerId = $bookingData['offer_id'];

        $client = HttpClient::create();

        $url = $this->apiUrl . '/api/v1/hotels/verify?offer_id=' . $offerId;
            
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode,
            'Content-Type' => 'application/json'
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $resp->getStatusCode());

        $data = json_decode($content, true)['data'];

        $currency = new Currency();
        $currency->Code = $data['pricing']['currency'];
    
        $payments = new OfferPaymentPolicyCollection();

        $i = 0;
        foreach ($data['payment_terms']['rules'] as $rule) {
            $payment = new OfferPaymentPolicy();
            $payment->Amount = $rule['value'];
            $payment->Currency = $currency;

            if ($i === 0) {
                $payment->PayAfter = date('Y-m-d');
            } else {
                $payment->PayAfter = (new DateTime($data['payment_terms']['rules'][$i - 1]['until']))->modify('+1 day')->format('Y-m-d');
            }
            
            $payment->PayUntil = (new DateTime($rule['until']))->format('Y-m-d');
            $payments->add($payment);
            $i++;
        }
        return $payments;
    }

    public function apiGetHotels(): []
    {
        $hotels = [];
        $cities = $this->apiGetCities();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode
        ];

        $requests = [];

        $destination = '';
        if (!empty($filter->CityId)) {
            $destination = '&destination_ids[]=' . $filter->CityId;
        }

        $url = $this->apiUrl . '/api/v1/static/hotels?page=1&for_travelfuse' . $destination;

        $resp = $client->request(HttpClient::METHOD_GET, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $resp->getStatusCode());

        $requests[] = [$resp, $options];

        $respArr = json_decode($content, true);

        $pages = $respArr['meta']['last_page'];

        for ($i = 2; $i < $pages + 1; $i++) {
            $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/hotels?for_travelfuse&page='.$i . $destination, $options);
            $requests[] = [$resp, $options];
        }

        foreach ($requests as $response) {
            $content = $response[0]->getBody();
            $respArr = json_decode($content, true);

            $hotelsResp = $respArr['data'];

            foreach ($hotelsResp as $hotelResp) {

                $facilities = new FacilityCollection();
                foreach ($hotelResp['facilities'] as $facility) {
                    $facilityObj = Facility::create($facility['id'], $facility['name']);
                    $facilities->add($facilityObj);
                }
    
                $images = new HotelImageGalleryItemCollection();
    
                foreach ($hotelResp['images'] as $img) {
                    $item = HotelImageGalleryItem::create($img['url']);
                    $images->add($item);
                }

                $city = $cities->get($hotelResp['destination_id']);

                if ($city === null) {
                    continue;
                }

                $hotel = Hotel::create($hotelResp['id'], $hotelResp['name'], $city, 
                $hotelResp['classification'], $hotelResp['description'], $hotelResp['address']['street'], $hotelResp['latitude'], 
                $hotelResp['longitude'], $facilities, $images, $hotelResp['address']['phone'], $hotelResp['address']['email'], null, $hotelResp['address']['website']);

                $hotels->add($hotel);
            }
        }

        return $hotels;
    }

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    {
        SphinxValidator::make()
            ->validateApiCode($this->post);

        $availabilityCollection = [];

        if ($filter->type === AvailabilityDatesFilter::CHARTER) {
            $availabilityCollection = $this->getCharterAvailabilityDates($filter);
        } elseif($filter->type === AvailabilityDatesFilter::TOUR)  {
            $availabilityCollection = $this->getTourAvailabilityDates($filter);
        }

        return $availabilityCollection;
    }

    private function getTourAvailabilityDates(): array
    {
        $availabilityDatesCollection = [];

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/circuits', $options);
        $content = $resp->getBody();

        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/circuits', $options, $content, $resp->getStatusCode());

        $respArr = json_decode($content, true)['data'];

        $cities = $this->apiGetCities();

        foreach ($respArr as $package) {

            // can be duplicate cities
            foreach ($package['departures'] as $departureArr) {
                $destinationCity = null;
                foreach ($package['destinations'] as $destinationId) {
                    $destinationCity = $cities->get($destinationId);
                    if ($destinationCity !== null) {
                        break;
                    }
                }

                if ($destinationCity === null) {
                    continue;
                }

                $transportType = 
                    $package['transport_type'] === 'flight' ?  AvailabilityDates::TRANSPORT_TYPE_PLANE : AvailabilityDates::TRANSPORT_TYPE_BUS;

                $cityFrom = $cities->get($departureArr['destination_id']);

                $id = $transportType . "~city|" . $cityFrom->Id . "~city|" . $destinationCity->Id;

                $existingAvailabilityDates = $availabilityDatesCollection->get($id);
                        
                // if id exists add date and night
                if ($existingAvailabilityDates !== null) {
                    $availabilityDates = $existingAvailabilityDates;
                    $dates = $availabilityDates->Dates;

                } else {
                    // id does not exist, create new
                    $availabilityDates = new AvailabilityDates();
                    $transportCity = new TransportCity();
                    $transportCity->City = $cityFrom;
                    $availabilityDates->From = $transportCity;
    
                    $transportCity = new TransportCity();
                    $transportCity->City = $destinationCity;
                    $availabilityDates->To = $transportCity;

                    $availabilityDates->Id = $id;
                    $availabilityDates->TransportType = $transportType;

                    $dates = new TransportDateCollection();
                }

                $dateObj = new TransportDate();
                $dateObj->Date = $departureArr['date'];
                $nightsCollection = new DateNightCollection();
                $dateNight = new DateNight();
                $dateNight->Nights = $package['duration']['days'];
                $nightsCollection->put($dateNight->Nights, $dateNight);
                $dateObj->Nights = $nightsCollection;
                $dates->put($dateObj->Date, $dateObj);

                $availabilityDates->Dates = $dates;
                $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
            }
        }

        return $availabilityDatesCollection;
    }

    private function getCharterAvailabilityDates(): array
    {
        return [];
    }

    public function apiGetTours(): TourCollection
    {
        $tours = new TourCollection();

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode
        ];

        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/circuits', $options);
        $content = $resp->getBody();

        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/circuits', $options, $content, $resp->getStatusCode());

        $respArr = json_decode($content, true)['data'];

        $cities = $this->apiGetCities();

        foreach ($respArr as $package) {

            $destinationCity = null;
            foreach ($package['destinations'] as $destinationId) {
                $destinationCity = $cities->get($destinationId);
                if ($destinationCity !== null) {
                    break;
                }
            }

            if ($destinationCity === null) {
                continue;
            }
            $location = new Location();
            $location->City = $destinationCity;

            $tour = new Tour();
            $tour->Id = $package['id'];
            $tour->Title = str_replace('?', '', $package['title']);
            $tour->Location = $location;

            $transports = new StringCollection();

            if ($package['transport_type'] === 'flight') {
                $transports->add(Tour::TRANSPORT_TYPE_PLANE);
            } elseif($package['transport_type'] === 'bus') {
                $transports->add(Tour::TRANSPORT_TYPE_BUS);
            } else {
                continue;
            }
            $tour->TransportTypes = $transports;

            $content = new TourContent();

            $items = new TourImageGalleryItemCollection();
            foreach ($package['images'] as $image) {
                $item = new TourImageGalleryItem();
                $item->RemoteUrl = $image['url'];
                $items->add($item);
            }
            $imageGallery = new TourImageGallery();
            $imageGallery->Items = $items;
            $content->ImageGallery = $imageGallery;

            $description = $package['description'];

            $included = '';
            foreach ($package['included'] as $includedArr) {
                $included .= $includedArr['title'] . '<br>';
            }
            $included = rtrim($included, '<br>');

            $excluded = '';
            foreach ($package['not_included'] as $notIncludedArr) {
                $excluded .= $notIncludedArr['title'] . '<br>';
            }

            $description .= '<p><b>Servicii incluse</b></p>'
                . $included
                . '<br><br><p><b>Servicii excluse</b></p>'
                . $excluded;

            $add = '';
            foreach ($package['additional_info'] as $info) {
                $add .= '<p><b>'.$info['title'].'</b></p>'
                    . $info['description'];
            }
            $description .= $add;

            $content->Content = nl2br($description);
            $tour->Content = $content;

            $destCountries = [];
            $destinations = [];
            foreach ($package['destinations'] as $destinationId) {
                $destinationCity = $cities->get($destinationId);
                if ($destinationCity === null) {
                    continue;
                }
                $destinations->add($destinationCity);
                $destCountries->put($destinationCity->Country->Id, $destinationCity->Country);
            }
            $tour->Destinations = $destinations;
            $tour->Destinations_Countries = $destCountries;
            $tour->Period = $package['duration']['nights'];

            $stages = new StageCollection();

            foreach ($package['itinerary'] as $itinerary) {
                $stage = new Stage();
                $content = new StageContent();
                $content->ShortDescription = $itinerary['description'] ?? '';
                $stage->Content = $content;
                $stages->add($stage);
            }

            $tour->Stages = $stages;


            $tours->add($tour);
        }

        return $tours;
    }

    private function getHotelOffers(AvailabilityFilter $filter): array
    {
        SphinxValidator::make()
            ->validateIndividualOffersFilter($filter);
        
        $availabilities = [];

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $url = $this->apiUrl . '/api/v1/hotels/search';

        $body = [
            'check_in' => $filter->checkIn,
            'check_out' => $filter->checkOut,
            'destination_id' => $filter->cityId,
            'occupancy' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'children_ages' => $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : []
                ]
            ],
            'currency' => $filter->RequestCurrency
        ];

        if (!empty($filter->hotelId)) {
            $body['hotel_ids'] = [$filter->hotelId];
        }

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $resp->getStatusCode());

        $respArr = json_decode($content, true);

        if (empty($respArr['cursor'])) {
            return $availabilities;
        }

        $cursor = $respArr['cursor'];
        
        $responses = [];

        $optionsCursor['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        do {
            $url = $this->apiUrl . '/api/v1/hotels/results?cursor='.$cursor;

            $respCursor = $client->request(HttpClient::METHOD_GET, $url, $optionsCursor);
            $contentCursor = $respCursor->getBody();

            $this->showRequest(HttpClient::METHOD_GET, $url, $optionsCursor, $contentCursor, $resp->getStatusCode());
            $respArr = json_decode($contentCursor, true);
            $cursor = $respArr['cursor'];
            $responses[] = $respArr;

        } while ($cursor !== null);

        foreach ($responses as $response) {

            foreach ($response['data'] as $data) {

                $taxes = 0;
                foreach ($data['pricing']['taxes'] as $tax) {
                    $taxes += $tax;
                }

                $avail = Offer::AVAILABILITY_NO;
                if ($data['confirmation'] === 'on_request') {
                    $avail = Offer::AVAILABILITY_ASK;
                } elseif ($data['confirmation'] === 'immediate') {
                    $avail = Offer::AVAILABILITY_YES;
                }

                $bookingDataArr = [
                    'offer_id' => $data['offer_id'],
                    'must_verify' => $data['must_verify'],
                    //'cancel_pol' => $data['cancellation_fees']['rules'],
                    'currency' => $data['pricing']['currency']
                ];
                $bookingDataJson = json_encode($bookingDataArr);


                $offer = Offer::createIndividualOffer(
                    $data['hotel_id'], 
                    $data['rooms'][0]['code'], 
                    $data['rooms'][0]['code'],
                    $data['rooms'][0]['name'], 
                    $data['meal_type_name'], 
                    $data['meal_type_name'], 
                    new DateTime($data['check_in']), 
                    new DateTime($data['check_out']), 
                    $data['rooms'][0]['adults'],
                    $data['rooms'][0]['children_ages'],
                    $data['pricing']['currency'],
                    $data['pricing']['supplier_price'] ?? $data['pricing']['selling_price'] - $data['pricing']['commission'] + $taxes, 
                    $data['pricing']['marketing_price'], 
                    $data['pricing']['selling_price'] + $taxes, 
                    $data['pricing']['commission'], 
                    $avail, null, null, $bookingDataJson);

                /*
                // only if is loaded

                $cancelPols = new OfferCancelFeeCollection();

                $i = 0;
                foreach ($data['cancellation_fees']['rules'] as $rule) {
                    $cp = new OfferCancelFee();
                    $cp->Currency = $offer->Currency;
                    $cp->Price = $rule['value'];
                    $cp->DateStart = $rule['since'];
                    if (isset($data['cancellation_fees']['rules'][$i+1])) {
                        $cp->DateEnd = (new DateTime($data['cancellation_fees']['rules'][$i+1]['since']))->modify('-1 day')->format('Y-m-d');
                    } else {
                        $cp->DateEnd = (new DateTime($data['check_in']))->format('Y-m-d');
                    }
                    $cancelPols->add($cp);
                    $i++;
                }

                $offer->CancelFees = $cancelPols;

                $payments = new OfferPaymentPolicyCollection();

                $i = 0;
                foreach ($data['payment_terms']['rules'] as $rule) {
                    $payment = new OfferPaymentPolicy();
                    $payment->Amount = $rule['value'];
                    $payment->Currency = $offer->Currency;
                    $payment->PayAfter = date('Y-m-d');
                    if ($i === 0) {
                        $payment->PayAfter = date('Y-m-d');
                    } else {
                        $payment->PayAfter = (new DateTime($data['payment_terms']['rules'][$i - 1]['until']))->modify('+1 day')->format('Y-m-d');
                    }
                    $payments->add($payment);
                    $i++;
                }
                $offer->Payments = $payments;
                */
                
                $searchedAvailability = $availabilities->get($data['hotel_id']);

                $availability = null;
                if ($searchedAvailability === null) {
                    $availability = Availability::create($data['hotel_id'], $filter->showHotelName, $data['hotel_name']);
                    $availability->Offers = new OfferCollection([$offer]);
                } else {
                    $availability = $searchedAvailability;
                    $availability->Offers->add($offer);
                }
                $availabilities->put($availability->Id, $availability);
            }
        }

        return $availabilities;
        
    }

    private function getTourOffers(AvailabilityFilter $filter): array
    {
        $availabilityCollection = [];
        return $availabilityCollection;
        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $url = $this->apiUrl . '/api/v1/circuits/rates';

        // todo: by circuit id daca vine hotelid
        // todo: luat circuit id din date statice
        $body = [
            'transport_types' => [$filter->transportTypes->first() === 'plane' ? 'flight' : 'bus'],
            'durations' => [$filter->days],
            'departures' => [$filter->departureCity],
            'destinations' => [$filter->cityId],
            'months' => [(new DateTime($filter->checkIn))->format('Y-m')],

            // 'occupancy' => [
            //     [
            //         'adults' => $filter->rooms->first()->adults,
            //         'children_ages' => $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : []
            //     ]
            // ],
        ];

        // if (!empty($filter->hotelId)) {
        //     $body['hotel_ids'] = [$filter->hotelId];
        // }

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $resp->getStatusCode());

        $respArr = json_decode($content, true)['data'];
        dump($respArr);

        $client = HttpClient::create();

        $url = $this->apiUrl . '/api/v1/circuits/quote';

        // get details for each
        foreach ($respArr as $circuit) {
            $body = [
                'circuit_id' => $circuit['circuit_id'],
                'departure_date' => $circuit['departure_date'],
                'departure_id' => $filter->departureCity,
    
                'occupancy' => [
                    [
                        'adults' => $filter->rooms->first()->adults,
                        'children_ages' => $post['args'][0]['rooms'][0]['childrenAges']->toArray()
                    ]
                ],
            ];
            $options['body'] = json_encode($body);
            $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
            $content = $resp->getBody();
            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $resp->getStatusCode());

            $respArr = json_decode($content, true)['data'];
            dump($respArr);

            $availability = new Availability();
            $availability->Id = $circuit['circuit_id'];

            $taxes = 0;
            foreach ($circuit['pricing']['taxes'] as $tax) {
                $taxes += $tax;
            }

            $avail = Offer::AVAILABILITY_YES;

            // $offer = Offer::createCharterOrTourOffer(
            //     $availability->Id,
            //     $circuit['rooms'][0]['code'],
            //     $circuit['rooms'][0]['code'],
            //     $circuit['rooms'][0]['name'],
            //     $circuit['meal_type_category_id'],
            //     $circuit['meal_type_name'],
            //     new DateTime($circuit['departure_date']),
            //     (new DateTime($circuit['departure_date']))->modify('+'.$circuit['duration']['nights'] . ' days'),
            //     $circuit['rooms'][0]['adults'],
            //     $circuit['rooms'][0]['children_ages'],
            //     $circuit['pricing']['currency'],
            //     $circuit['pricing']['supplier_price'] ?? $data['pricing']['selling_price'] - $data['pricing']['commission'] + $taxes,
            //     $circuit['pricing']['marketing_price'],
            //     $circuit['pricing']['selling_price'] + $taxes,
            //     $circuit['pricing']['commission'],
            //     $avail, null,


            // );
            
        }


        return $availabilityCollection;
    }

    public function apiGetOffers(AvailabilityFilter $filter): array
    {

        SphinxValidator::make()
            ->validateApiCode($this->post);

        $availabilityCollection = [];

        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            $availabilityCollection = $this->getHotelOffers($filter);
        } elseif($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR)  {
            $availabilityCollection = $this->getTourOffers($filter);
        }

        return $availabilityCollection;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        SphinxValidator::make()
            ->validateApiCode($this->post)
            ->validateBookHotelFilter($filter);

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode,
            'Content-Type' => 'application/json'
        ];

        $bookingDataJson = $post['args'][0]['Items'][0]['Offer_bookingDataJson'];
        $bookingData = json_decode($bookingDataJson, true);

        $booking = new Booking();

        // verify if needed
        $mustVerify = $bookingData['must_verify'];
        if ($mustVerify) {
            $offerId = $bookingData['offer_id'];
            $url = $this->apiUrl . '/api/v1/hotels/verify?offer_id=' . $offerId;
            
            $resp = $client->request(HttpClient::METHOD_GET, $url, $options);
            $content = $resp->getBody();
            $this->showRequest(HttpClient::METHOD_GET, $url, $options, $content, $resp->getStatusCode());

            $respArr = json_decode($content, true)['data'];

            // check prices
            $offerPrice = $filter->Items->first()->Offer_Gross;
            $checkPrice = $respArr['pricing']['selling_price'];
            if ($offerPrice != $checkPrice) {
                return [$booking, 'Prices do not match! Offer price: '. $offerPrice . ', to response: '. $content];
            }

            // check cancel pol
            // $cancelPol = json_encode($bookingData['cancel_pol']);
            // $checkCancelPol = json_encode($respArr['cancellation_fees']['rules']);

            // if ($cancelPol != $checkCancelPol) {
            //     return [$booking, 'Cancellation policies do not match! Offer cancellation policies: ' . $cancelPol , ' to response: ' .$content];
            // }
        }

        $url = $this->apiUrl . '/api/v1/hotels/book';
        $guests = [];

        /** @var Passenger $passenger */
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $passenger) {
            $guests[] = [
                'first_name' => $passenger['Firstname'],
                'last_name' => $passenger['Lastname'],
                'birth_date' => $passenger['BirthDate'],
                'gender' => strtolower(substr($passenger['Gender'] ?? 'm', 0, 1))
            ];
        }

        $body = [
            'offer_id' => $bookingData['offer_id'],
            'price' => (float) $filter->Items->first()->Offer_Gross,
            
            'currency' => $bookingData['currency'],
            'occupancy' => [[
                'room_code' => $filter->Items->first()->Room_Def_Code,
                'guests' => $guests
            ]]
        ];

        $options['body'] = json_encode($body);

        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $content = $resp->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $resp->getStatusCode());

        $respArr = json_decode($content, true);

        $statusResp = $respArr['status'];

        if ($statusResp === 'confirmed' || $statusResp === 'on_request') {
            $booking->Id = $respArr['booking_confirmation_number'];
        }
        
        return [$booking, $content];
    }

    public function apiTestConnection(): bool
    {
        SphinxValidator::make()
            ->validateApiCode($this->post);

        $client = HttpClient::create();

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->apiCode
        ];

        $requests = [];
        $resp = $client->request(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/destinations?page=1', $options);

        $this->showRequest(HttpClient::METHOD_GET, $this->apiUrl . '/api/v1/static/destinations?page=1', $options, $resp->getBody(), $resp->getStatusCode());

        $requests[] = [$resp, $options];

        $respArr = json_decode($resp->getBody(), true);
        if (empty($respArr['data'])) {
            return false;
        }
        $locations = $respArr['data'];

        if (!empty($locations)) {
            return true;
        }

        return false;
    }
}
