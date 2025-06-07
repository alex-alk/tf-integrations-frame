<?php

namespace Integrations\Etrip;

use App\Entities\Availability\AirportTaxesCategory;
use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\MealMerchType;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
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
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Entities\Tours\Tour;
use App\Entities\Tours\TourCollection;
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
use App\Handles;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\[];
use App\Support\Collections\StringCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\TestHandles;
use DateTime;
use DateTimeImmutable;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\AirportMap;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use Utils\Utils;

class EtripApiService extends AbstractApiService
{
    private static array $handlesWithTourMode = [TestHandles::LOCALHOST_HOLIDAYOFFICE_TOUR_ONLY];
    private static array $handlesWithBothModes = [];

    public function __construct()
    {
        parent::__construct();
    }
    
    public function apiDoBooking(BookHotelFilter $filter): array
    {
        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $url = $this->apiUrl . '/v2/booking';
        $client = HttpClient::create();

        $offerId = $filter->Items->first()->Offer_offerId;

        $pass = base64_encode($this->username . ':' . $this->password);
        $options['headers'] = [
            'Authorization' => 'Basic ' . $pass,
            'Cookie' => 'PHPSESSID=' . $filter->Items->first()->Offer_InitialData,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $departure = new DateTime($post['args'][0]['Items'][0]['Room_CheckinAfter']);
        $passengers = [];

        /** @var Passenger $filterPassenger */
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $filterPassenger) {
            $dob = new DateTime($filterPassenger->BirthDate);
            $age = $departure->diff($dob)->y;
            
            $passengers[] = [
                'title' => $filterPassenger->Gender === 'female' ? 'Mrs' : 'Mr',
                'firstName' => $filterPassenger->Firstname,
                'lastName' => $filterPassenger->Lastname,
                'type' => $filterPassenger->IsAdult ?  'ADT' : ($age < 2 ? 'INF' : 'CHD'),
                'birthdate' => $filterPassenger->BirthDate,
                'gender' => $filterPassenger->Gender
            ];
        }

        $body = [
            'paxInfo' => $passengers,
            'resultIndex' => $offerId,
            'status' => 'confirm'
        ];
        
        if (empty($this->apiContext)) {

            if (empty($filter->BillingTo->Email)) {
                throw new Exception('args[0][BillingTo][Email] is missing');
            }

            if (empty($filter->BillingTo->Phone)) {
                throw new Exception('args[0][BillingTo][Phone] is missing');
            }

            $body['client'] = [
                'title' => $passengers[0]['title'],
                'firstName' => $passengers[0]['firstName'],
                'lastName' => $passengers[0]['lastName'],
                'birthDate' => $passengers[0]['birthdate'],
                'email' => $filter->BillingTo->Email,
                'phone' => $filter->BillingTo->Phone
            ];
        }

        $options['body'] = json_encode($body);

        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), 0);

        $resp = json_decode($respObj->getBody(), true);
        
        $booking = new Booking();
        if ($resp !== null && isset($resp['reference'])) {
            $booking->Id = $resp['reference'];
        }

        return [$booking, $respObj->getBody()];
    }

    public function getOfferPaymentPlans(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    {
        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateOfferPaymentPlansFilter($filter);

        $url = $this->apiUrl . '/v2/booking/paymentDates';
        $client = HttpClient::create();

        $offerId = $filter->OriginalOffer->offerId;

        $pass = base64_encode($this->username . ':' . $this->password);
        $options['headers'] = [
            'Authorization' => 'Basic ' . $pass,
            'Cookie' => 'PHPSESSID=' . $filter->OriginalOffer->InitialData,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $passengers = [];

        for ($i = 1; $i <= $post['args'][0]['rooms'][0]['adults']; $i++) {
            $passengers[] = [
                'type' => 'ADT',
            ];
        }

        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $passengers[] = [
                    'type' => $age < 2 ? 'INF' : 'CHD',
                ];
            }
        }

        $body = [
            'paxInfo' => $passengers,
            'resultIndex' => $offerId,
            'status' => 'quote'
        ];

        if (empty($this->apiContext)) {

            $body['client'] = [
                'title' => 'Mr',
                'firstName' => 'Travelfuse',
                'lastName' => 'Travelfuse',
                'birthDate' => '2000-01-01',
                'email' => 'test@test.com',
                'phone' => '0741111111'
            ];
        }

        $options['body'] = json_encode($body);

        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), 0);

        $resp = json_decode($respObj->getBody(), true);
        
        $paymentPlans = new OfferPaymentPolicyCollection();

        if (!empty($resp['error'])) {
            return $paymentPlans;
        }

        $currency = new Currency();
        $currency->Code = 'EUR';

        $i = 0;
        $payAfterDT = new DateTimeImmutable();
        foreach ($resp as $pol) {
            $paymentPlan = new OfferPaymentPolicy();
            $paymentPlan->Amount = $pol['amount'];
            $paymentPlan->Currency = $currency;
            $paymentPlan->PayUntil = $pol['date'];

            if ($i == 0) {
                $paymentPlan->PayAfter = date('Y-m-d');
            } else {
                $paymentPlan->PayAfter = $payAfterDT->modify('+1 day')->format('Y-m-d');
            }
            $payAfterDT = new DateTimeImmutable($paymentPlan->PayUntil);
            
            $paymentPlans->add($paymentPlan);
            $i++;
        }

        return $paymentPlans;
    }

    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    {
        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateOfferCancelFeesFilter($filter);

        $url = $this->apiUrl . '/v2/booking/paymentDates';
        $client = HttpClient::create();

        $offerId = $filter->OriginalOffer->offerId;

        $pass = base64_encode($this->username . ':' . $this->password);
        $options['headers'] = [
            'Authorization' => 'Basic ' . $pass,
            'Cookie' => 'PHPSESSID=' . $filter->OriginalOffer->InitialData,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $passengers = [];

        for ($i = 1; $i <= $post['args'][0]['rooms'][0]['adults']; $i++) {
            $passengers[] = [
                'type' => 'ADT',
            ];
        }

        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $passengers[] = [
                    'type' => $age < 2 ? 'INF' : 'CHD',
                ];
            }
        }

        $body = [
            'paxInfo' => $passengers,
            'resultIndex' => $offerId,
            'status' => 'quote'
        ];

        if (empty($this->apiContext)) {

            $body['client'] = [
                'title' => 'Mr',
                'firstName' => 'Travelfuse',
                'lastName' => 'Travelfuse',
                'birthDate' => '2000-01-01',
                'email' => 'test@test.com',
                'phone' => '0741111111'
            ];
        }

        $options['body'] = json_encode($body);

        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), 0);

        $resp = json_decode($respObj->getBody(), true);
        
        $paymentPlans = new OfferPaymentPolicyCollection();
        $cancellationFees = new OfferCancelFeeCollection();

        if (!empty($resp['error'])) {
            return $cancellationFees;
        }

        $currency = new Currency();
        $currency->Code = 'EUR';

        $i = 0;
        $payAfterDT = new DateTimeImmutable();
        foreach ($resp as $pol) {
            $paymentPlan = new OfferPaymentPolicy();
            $paymentPlan->Amount = $pol['amount'];
            $paymentPlan->Currency = $currency;
            $paymentPlan->PayUntil = $pol['date'];

            if ($i == 0) {
                $paymentPlan->PayAfter = date('Y-m-d');
            } else {
                $paymentPlan->PayAfter = $payAfterDT->modify('+1 day')->format('Y-m-d');
            }
            $payAfterDT = new DateTimeImmutable($paymentPlan->PayUntil);
            
            $paymentPlans->add($paymentPlan);
            $i++;
        }

        $i = 0;
        $amount = 0;
        /** @var OfferPaymentPolicy $paymentPlan */
        foreach ($paymentPlans as $paymentPlan) {
            $cp = new OfferCancelFee();
            $cp->Currency = $currency;
            $cp->DateStart = $paymentPlan->PayUntil;
            if (isset($paymentPlans->toArray()[$i + 1])) {
                $cp->DateEnd = $paymentPlans->toArray()[$i + 1]->PayAfter;
            } else {
                $cp->DateEnd = $post['args'][0]['CheckOut'];
            }

            $amount += (float) $paymentPlan->Amount;
            $cp->Price = $amount;
            
            $cancellationFees->add($cp);
            $i++;
        }

        return $cancellationFees;
    }

    public function getTourAvailabilityDates(): array
    {
        if (in_array($this->handle, self::$handlesWithBothModes)) {
            return $this->getTourAvailabilityDatesFromTours()->combine($this->getTourAvailabilityDatesFromPackages());
        } elseif (in_array($this->handle, self::$handlesWithTourMode)) {
            return $this->getTourAvailabilityDatesFromTours();
        } else {
            return $this->getTourAvailabilityDatesFromPackages();
        }
    }

    public function getTourAvailabilityDatesFromPackages(): array
    {
        $file = 'availability-dates-tour-from-packages';
        $availabilityDatesJson = Utils::getFromCache($this, $file);

        if ($availabilityDatesJson === null) {

            $url = $this->apiUrl . '/v2/static/packages';
            $cities = $this->apiGetCities();
            $countries = $this->apiGetCountries();
            $regions = $this->apiGetRegions();

            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);
            $basic = 'Basic ' . $pass;
            $options['headers'] = [
                'Authorization' => $basic,
            ];
            
            $respObj = $client->request(HttpClient::METHOD_GET, $url, $options);
            $respJson = $respObj->getBody();
            // $this->showRequest(HttpClient::METHOD_GET, $url, $options, $respJson, $respObj->getStatusCode());
            
            $packages = json_decode($respJson, true);

            $today = new DateTime();

            $availabilityDatesCollection = [];
            foreach ($packages as $package) {
                if ($package['packageType'] !== 'tour') {
                    continue;
                }
                foreach ($package['departurePoints'] as $departureCityId) {

                    $cityTo = $cities->get($package['destination']);
                    if ($cityTo === null) {
                        // might be region
                        $regionTo = $regions->get($package['destination']);
                        if ($regionTo === null) {
                            // should be country
                            $countryTo = $countries->get($package['destination']);
                            if ($countryTo === null) {
                                throw new Exception('country ' . $package['destination'] . ' not found');
                            } else {
                                $cityTo = $cities->filter(fn(City $c) => $c->Country->Id === $package['destination']);
                            }
                        } else {
                            $cityTo = $cities->filter(fn(City $c) => $c->County->Id === $package['destination']);
                        }

                    } else {
                        $cityTo = [$cityTo];
                    }

                    foreach ($cityTo as $destinationCityFromCountry) {
                        $availabilityDates = new AvailabilityDates();
                        $availabilityDates->TransportType = 
                            $package['transportType'] === 'flight' ?  AvailabilityDates::TRANSPORT_TYPE_PLANE : AvailabilityDates::TRANSPORT_TYPE_BUS;
                        $availabilityDates->Content = new TransportContent();
        
                        $cityFrom = $cities->get($departureCityId);

                        if ($cityFrom === null) {
                            continue;
                        }

                        $transportCity = new TransportCity();
                        $transportCity->City = $cityFrom;
                        $availabilityDates->From = $transportCity;
        
                        $transportCity = new TransportCity();
                        $transportCity->City = $destinationCityFromCountry;
                        $availabilityDates->To = $transportCity;
                        $availabilityDates->Id = $availabilityDates->TransportType . "~city|" . $departureCityId . "~city|" . $package['destination'];
        
                        // if availability id exists, add date and night to it
                        $existingAvailabilityDates = $availabilityDatesCollection->get($availabilityDates->Id);
                        // if id exists
                        if ($existingAvailabilityDates !== null) {
                            $availabilityDates = $existingAvailabilityDates;
                            $dates = $availabilityDates->Dates;

                            foreach ($package['departureDates'] as $departureDate) {
                                $departureDateTime = new DateTime($departureDate);
                                if ($departureDateTime < $today) {
                                    continue;
                                }

                                // if date exist, just add night to it, a night obj already exists
                                
                                $dateObj = $dates->get($departureDate); // transport date
                                $dateNight = new DateNight();
                                $dateNight->Nights = $package['duration'];

                                if ($dateObj === null) {
                                    // add date and night
                                    $dateObj = new TransportDate();
                                    $dateObj->Date = $departureDate;
                                    $nights = new DateNightCollection();
                                    $nights->put($dateNight->Nights, $dateNight);
                                } else {
                                    // just add night to night collection
                                    $nights = $dateObj->Nights;
                                    $nights->put($dateNight->Nights, $dateNight);
                                }
                                $dateObj->Nights = $nights;
                                $dates->put($dateObj->Date, $dateObj);
                                
                            }
                        } else {
                            // id does not exist

                            $dates = new TransportDateCollection();
                            foreach ($package['departureDates'] as $departureDate) {
                                $departureDateTime = new DateTime($departureDate);
                                if ($departureDateTime < $today) {
                                    continue;
                                }

                                $dateObj = new TransportDate();
                                $dateObj->Date = $departureDate;
                                $nightsCollection = new DateNightCollection();
                                $dateNight = new DateNight();
                                $dateNight->Nights = $package['duration'];
                                $nightsCollection->put($dateNight->Nights, $dateNight);
                                $dateObj->Nights = $nightsCollection;
                                $dates->put($dateObj->Date, $dateObj);
                            }
            
                            $availabilityDates->Dates = $dates;
                            $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
                        }
                    }
                }
            }
            $data = json_encode_pretty($availabilityDatesCollection);
            Utils::writeToCache($this, $file, $data);
        } else {
            $ad = json_decode($availabilityDatesJson, true);
            $availabilityDatesCollection = ResponseConverter::convertToCollection($ad, array::class);
        }

        return $availabilityDatesCollection;
    }

    public function getTourAvailabilityDatesFromTours(): array
    {
        $file = 'availability-dates-tour-from-tours';
        $availabilityDatesJson = Utils::getFromCache($this, $file);

        if ($availabilityDatesJson === null) {

            $url = $this->apiUrl . '/v2/static/tours';
            $cities = $this->apiGetCities();
            $countries = $this->apiGetCountries();
            $regions = $this->apiGetRegions();

            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);
            $basic = 'Basic ' . $pass;
            $options['headers'] = [
                'Authorization' => $basic,
            ];
            
            $respObj = $client->request(HttpClient::METHOD_GET, $url, $options);
            $respJson = $respObj->getBody();
            // $this->showRequest(HttpClient::METHOD_GET, $url, $options, $respJson, $respObj->getStatusCode());
            
            $packages = json_decode($respJson, true);

            $today = new DateTime();

            $availabilityDatesCollection = [];
            foreach ($packages as $package) {

                //$destination = $package['destinations'][0]['id'] . '-t';

                foreach ($package['departurePoints'] as $departureCityId) {
           
                    $cityFrom = $cities->get($departureCityId['id']);

                    if ($cityFrom === null) {
                        continue;
                    }

                    $selectedCountries = [];

                    foreach ($package['destinations'] as $destination) {
                        $destination = $destination['id'] . '-t';
                        $cityTo = $cities->get($destination);

                        if (isset($selectedCountries[$cityTo->Country->Id])) {
                            continue;
                        }

                        $selectedCountries[$cityTo->Country->Id] = $cityTo->Country->Id;

                        if ($cityTo === null) {
                            // might be region
                            $regionTo = $regions->get($destination);
                            if ($regionTo === null) {
                                // should be country
                                $countryTo = $countries->get($destination);
                                if ($countryTo === null) {

                                    //continue;
                                    throw new Exception('country ' . $destination . ' not found');
                                } else {
                                    $cityTo = $cities->filter(fn(City $c) => $c->Country->Id === $destination);
                                }
                            } else {
                                $cityTo = $cities->filter(fn(City $c) => $c->County->Id === $destination);
                            }

                        } else {
                            $cityTo = [$cityTo];
                        }

                        if (isset($package['transport'][1])) {

                            if ($package['transport'][1] !== 'flight' && $package['transport'][1] !== 'bus') {
                                continue;
                            }
                        }

                        $transportType = '';
                        if ($package['transport'][0] === 'flight') {
                            $transportType = Tour::TRANSPORT_TYPE_PLANE;
                        } elseif ($package['transport'][0] === 'bus') {
                            $transportType = Tour::TRANSPORT_TYPE_BUS;
                        } else {
                            continue;
                        }
                        
                        foreach ($cityTo as $destinationCityFromCountry) {
                            if ($destinationCityFromCountry->County !== null) {
                                dd($package);
                            }
                            $availabilityDates = new AvailabilityDates();

                            $availabilityDates->TransportType = $transportType;
                            $availabilityDates->Content = new TransportContent();

                            

                            $transportCity = new TransportCity();
                            $transportCity->City = $cityFrom;
                            $availabilityDates->From = $transportCity;
            
                            $transportCity = new TransportCity();
                            $transportCity->City = $destinationCityFromCountry;
                            $availabilityDates->To = $transportCity;
                            $availabilityDates->Id = $availabilityDates->TransportType . "~city|" . $departureCityId['id'] . "~city|" . $destinationCityFromCountry->Id;
            
                            // if availability id exists, add date and night to it
                            $existingAvailabilityDates = $availabilityDatesCollection->get($availabilityDates->Id);
                            // if id exists
                            if ($existingAvailabilityDates !== null) {
                                $availabilityDates = $existingAvailabilityDates;
                                $dates = $availabilityDates->Dates;

                                foreach ($package['departureDates'] as $departureDate) {
                                    $departureDateTime = new DateTime($departureDate);
                                    if ($departureDateTime < $today) {
                                        continue;
                                    }

                                    // if date exist, just add night to it, a night obj already exists
                                    
                                    $dateObj = $dates->get($departureDate); // transport date
                                    $dateNight = new DateNight();
                                    $dateNight->Nights = $package['duration'];

                                    if ($dateObj === null) {
                                        // add date and night
                                        $dateObj = new TransportDate();
                                        $dateObj->Date = $departureDate;
                                        $nights = new DateNightCollection();
                                        $nights->put($dateNight->Nights, $dateNight);
                                    } else {
                                        // just add night to night collection
                                        $nights = $dateObj->Nights;
                                        $nights->put($dateNight->Nights, $dateNight);
                                    }
                                    $dateObj->Nights = $nights;
                                    $dates->put($dateObj->Date, $dateObj);
                                    
                                }
                            } else {
                                // id does not exist

                                $dates = new TransportDateCollection();
                                foreach ($package['departureDates'] as $departureDate) {
                                    $departureDateTime = new DateTime($departureDate);
                                    if ($departureDateTime < $today) {
                                        continue;
                                    }

                                    $dateObj = new TransportDate();
                                    $dateObj->Date = $departureDate;
                                    $nightsCollection = new DateNightCollection();
                                    $dateNight = new DateNight();
                                    $dateNight->Nights = $package['duration'];
                                    $nightsCollection->put($dateNight->Nights, $dateNight);
                                    $dateObj->Nights = $nightsCollection;
                                    $dates->put($dateObj->Date, $dateObj);
                                }
                
                                $availabilityDates->Dates = $dates;


                                $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
                            }
                        }
                    }
                    
                }
            }
            $data = json_encode_pretty($availabilityDatesCollection);
            Utils::writeToCache($this, $file, $data);
        } else {
            $ad = json_decode($availabilityDatesJson, true);
            $availabilityDatesCollection = ResponseConverter::convertToCollection($ad, array::class);
        }

        return $availabilityDatesCollection;
    }

    public function getCharterAvailabilityDates(): array
    {
        $file = 'availability-dates-charter';
        $availabilityDatesJson = Utils::getFromCache($this, $file);

        if ($availabilityDatesJson === null) {

            $url = $this->apiUrl . '/v2/static/packages';
            $cities = $this->apiGetCities();
            $countries = $this->apiGetCountries();
            $regions = $this->apiGetRegions();

            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);
            $basic = 'Basic ' . $pass;
            $options['headers'] = [
                'Authorization' => $basic,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            
            $respObj = $client->request(HttpClient::METHOD_GET, $url, $options);
            $respJson = $respObj->getBody();
            $this->showRequest(HttpClient::METHOD_GET, $url, $options, $respJson, $respObj->getStatusCode());
            
            $packages = json_decode($respJson, true);

            $today = new DateTime();

            $availabilityDatesCollection = [];
            foreach ($packages as $package) {
                if ($package['packageType'] !== 'package') {
                    continue;
                }

                foreach ($package['departurePoints'] as $departureCityId) {

                    $cityTo = $cities->get($package['destination']);

                    if ($cityTo === null) {
                        // might be region
                        $regionTo = $regions->get($package['destination']);
                        if ($regionTo === null) {
                            // should be country
                            $countryTo = $countries->get($package['destination']);
                            if ($countryTo === null) {
                                throw new Exception('country ' . $package['destination'] . ' not found');
                            } else {
                                $cityTo = $cities->filter(fn(City $c) => $c->Country->Id === $package['destination']);
                            }
                        } else {
                            $cityTo = $cities->filter(fn(City $c) => $c->County->Id === $package['destination']);
                        }

                    } else {
                        $cityTo = [$cityTo];
                    }

                    foreach ($cityTo as $destinationCityFromCountry) {
                        $availabilityDates = new AvailabilityDates();
                        $availabilityDates->TransportType = 
                            $package['transportType'] === 'flight' ?  AvailabilityDates::TRANSPORT_TYPE_PLANE : AvailabilityDates::TRANSPORT_TYPE_BUS;
                        $availabilityDates->Content = new TransportContent();
        
                        $cityFrom = $cities->get($departureCityId);

                        if ($cityFrom === null) {
                            continue;
                        }
                        $transportCity = new TransportCity();
                        $transportCity->City = $cityFrom;
                        $availabilityDates->From = $transportCity;
        
                        $transportCity = new TransportCity();
                        $transportCity->City = $destinationCityFromCountry;
                        $availabilityDates->To = $transportCity;
                        $availabilityDates->Id = $availabilityDates->TransportType . "~city|" . $departureCityId . "~city|" . $package['destination'];
        
                        // if availability id exists, add date and night to it
                        $existingAvailabilityDates = $availabilityDatesCollection->get($availabilityDates->Id);
                        // if id exists
                        if ($existingAvailabilityDates !== null) {
                            $availabilityDates = $existingAvailabilityDates;
                            $dates = $availabilityDates->Dates;

                            foreach ($package['departureDates'] as $departureDate) {
                                $departureDateTime = new DateTime($departureDate);
                                if ($departureDateTime < $today) {
                                    continue;
                                }

                                // if date exist, just add night to it, a night obj already exists
                                
                                $dateObj = $dates->get($departureDate); // transport date
                                $dateNight = new DateNight();
                                $dateNight->Nights = $package['duration'];

                                if ($dateObj === null) {
                                    // add date and night
                                    $dateObj = new TransportDate();
                                    $dateObj->Date = $departureDate;
                                    $nights = new DateNightCollection();
                                    $nights->put($dateNight->Nights, $dateNight);
                                } else {
                                    // just add night to night collection
                                    $nights = $dateObj->Nights;
                                    $nights->put($dateNight->Nights, $dateNight);
                                }
                                $dateObj->Nights = $nights;
                                $dates->put($dateObj->Date, $dateObj);
                                
                            }
                        } else {
                            // id does not exist

                            $dates = new TransportDateCollection();
                            foreach ($package['departureDates'] as $departureDate) {
                                $departureDateTime = new DateTime($departureDate);
                                if ($departureDateTime < $today) {
                                    continue;
                                }

                                $dateObj = new TransportDate();
                                $dateObj->Date = $departureDate;
                                $nightsCollection = new DateNightCollection();
                                $dateNight = new DateNight();
                                $dateNight->Nights = $package['duration'];
                                $nightsCollection->put($dateNight->Nights, $dateNight);
                                $dateObj->Nights = $nightsCollection;
                                $dates->put($dateObj->Date, $dateObj);
                            }
            
                            $availabilityDates->Dates = $dates;
                            $availabilityDatesCollection->put($availabilityDates->Id, $availabilityDates);
                        }
                    }
                }
            }
            $data = json_encode_pretty($availabilityDatesCollection);
            Utils::writeToCache($this, $file, $data);
        } else {
            $ad = json_decode($availabilityDatesJson, true);
            $availabilityDatesCollection = ResponseConverter::convertToCollection($ad, array::class);
        }

        return $availabilityDatesCollection;
    }

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    {
       $availabilityDates = [];

        if ($filter->type === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            return $this->getCharterAvailabilityDates();
        } else {
            return $this->getTourAvailabilityDates();
        }

       return $availabilityDates;
    }
    
    public function apiTestConnection(): bool
    {
        if ($this->username === 'holiday.office' && $this->handle === Handles::HOLIDAYOFFICE) {
            Validator::make()->validateUsernameAndPassword($this->post);
        } else {
            Validator::make()->validateAllCredentials($this->post);
        }

        $url = $this->apiUrl . '/v2/static/geography';
        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);

        $options['headers'] = [
            'Authorization' => 'Basic ' . $pass,
            'Accept' => 'application/json'
        ];
        
        $geo = $client->request(HttpClient::METHOD_GET, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $geo->getBody(), $geo->getStatusCode());

        $geo = json_decode($geo->getBody(), true);

        $conGeo = false;
        if (isset($geo['children'])) {
            $conGeo = true;
        }

        $url = $this->apiUrl . '/v2/search/hotels';

        $apiContextGood = true;

        if (!empty($this->apiContext)) {
            $options['headers']['X-AgentCode'] = $this->apiContext;
        
            $body = [];

            $options['body'] = json_encode($body);
            
            $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
            $resp = json_decode($respObj->getBody(), true);
            if (isset($resp['items']['description']) && $resp['items']['description'] === 'invalid agent code') {
                $apiContextGood = false;
            }
        }

        return $conGeo && $apiContextGood;
    }

    public function apiGetCities(?CitiesFilter $params = null): array
    {
        if (in_array($this->handle, self::$handlesWithBothModes) || in_array($this->handle, self::$handlesWithTourMode)) {
            return $this->getCitiesJoined();
        } else {
            return $this->getCitiesFromGeography();
        }
    }

    private function getCitiesFromGeography(CitiesFilter $params = null): array
    {
        $file = 'cities';

        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

            Validator::make()
                ->validateUsernameAndPassword($this->post);

            $url = $this->apiUrl . '/v2/static/geography';
            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);

            $options['headers'] = [
                'Authorization' => 'Basic ' . $pass
            ];
            
            $geo = $client->request(HttpClient::METHOD_GET, $url, $options);
            
            $this->showRequest(HttpClient::METHOD_GET, $url, $options, $geo->getBody(), $geo->getStatusCode()); 
            $geoJson = $geo->getBody();

            $geoResponse = json_decode($geoJson, true)['children'];
            $map = CountryCodeMap::getCountryCodeMap();

            $cities = [];
            $countries = [];

            foreach ($geoResponse as $continent) {
                foreach ($continent['children'] as $countryResponse) {
                    $country = new Country();
                    $countryName = trim($countryResponse['intName'] ?? $countryResponse['name']);
                    $countryNameForMap = trim(str_replace('circuite', '', $countryName));
                    $countryNameForMap = trim(str_replace('Circuite', '', $countryNameForMap));
                    
                    if (isset($map[$countryNameForMap])) {
                        $country->Code = $map[$countryNameForMap];
                    } else {
                        $country->Code = $countryName;
                    }
                    
                    $country->Id = $countryResponse['id'];
                    $country->Name = $countryName;
                    $countries->put($country->Id, $country);

                    foreach ($countryResponse['children'] as $cityResponse) {
                        
                        $city = new City();
                        $city->Id = $cityResponse['id'];
                        $city->Name = $cityResponse['name'];
                        $city->Country = $country;

                        $region = new Region();
                        $region->Id = $cityResponse['id'];
                        $region->Name = $cityResponse['name'];
                        $region->Country = $country;
                        $city->County = $region;
                        $cities->put($city->Id, $city);

                        foreach ($cityResponse['children'] as $cityResp) {
                            $city = new City();
                            $city->Id = $cityResp['id'];
                            $city->Name = $cityResp['name'];
                            $city->Country = $country;
                            $city->County = $region;
                            $cities->put($city->Id, $city);

                            foreach ($cityResp['children'] as $cityRespo) {
                                $city = new City();
                                $city->Id = $cityRespo['id'];
                                $city->Name = $cityRespo['name'];
                                $city->Country = $country;
                                $city->County = $region;
                                $cities->put($city->Id, $city);
                            }
                        }
                    }
                }
            }

            Utils::writeToCache($this, $file, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }
        return $cities;
    }

    private function getCitiesJoined(CitiesFilter $params = null): array
    {
        $file = 'cities-joined';

        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

            Validator::make()
                ->validateUsernameAndPassword($this->post);

            $url = $this->apiUrl . '/v2/static/geography';
            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);

            $options['headers'] = [
                'Authorization' => 'Basic ' . $pass
            ];
            
            $geo = $client->request(HttpClient::METHOD_GET, $url, $options);
            
            $this->showRequest(HttpClient::METHOD_GET, $url, $options, $geo->getBody(), $geo->getStatusCode()); 
            $geoJson = $geo->getBody();

            $geoResponse = json_decode($geoJson, true)['children'];
            $map = CountryCodeMap::getCountryCodeMap();

            $cities = [];
            $countries = [];

            foreach ($geoResponse as $continent) {
                foreach ($continent['children'] as $countryResponse) {
                    $country = new Country();
                    $countryName = trim($countryResponse['intName'] ?? $countryResponse['name']);
                    $countryNameForMap = trim(str_replace('circuite', '', $countryName));
                    $countryNameForMap = trim(str_replace('Circuite', '', $countryNameForMap));
                    
                    if (isset($map[$countryNameForMap])) {
                        $country->Code = $map[$countryNameForMap];
                    } else {
                        $country->Code = $countryName;
                    }
                    
                    $country->Id = $countryResponse['id'];
                    $country->Name = $countryName;
                    $countries->put($country->Id, $country);

                    foreach ($countryResponse['children'] as $cityResponse) {
                        
                        $city = new City();
                        $city->Id = $cityResponse['id'];
                        $city->Name = $cityResponse['name'];
                        $city->Country = $country;

                        $region = new Region();
                        $region->Id = $cityResponse['id'];
                        $region->Name = $cityResponse['name'];
                        $region->Country = $country;
                        $city->County = $region;
                        $cities->put($city->Id, $city);

                        foreach ($cityResponse['children'] as $cityResp) {
                            $city = new City();
                            $city->Id = $cityResp['id'];
                            $city->Name = $cityResp['name'];
                            $city->Country = $country;
                            $city->County = $region;
                            $cities->put($city->Id, $city);

                            foreach ($cityResp['children'] as $cityRespo) {
                                $city = new City();
                                $city->Id = $cityRespo['id'];
                                $city->Name = $cityRespo['name'];
                                $city->Country = $country;
                                $city->County = $region;
                                $cities->put($city->Id, $city);
                            }
                        }
                    }
                }
            }

            $url = $this->apiUrl . '/v2/static/tourDestinations';

            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);
            $basic = 'Basic ' . $pass;
            $options['headers'] = [
                'Authorization' => $basic
            ];
            
            $respObj = $client->request(HttpClient::METHOD_GET, $url, $options);
            $respJson = $respObj->getBody();

            $this->showRequest(HttpClient::METHOD_GET, $url, $options, $respJson, $respObj->getStatusCode());
            
            $data = json_decode($respJson, true);

            foreach ($data as $cityResp) {
                $country = new Country();
                $countryName = $cityResp['countryNameInternational'];
                $country->Code = $map[$countryName];

                $country->Id = $cityResp['countryId'];

                $country->Name = $countryName;
                $city = City::create($cityResp['id'] . '-t', $cityResp['name'], $country);

                $cities->put($city->Id, $city);
            }

            Utils::writeToCache($this, $file, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
        }
        return $cities;
    }

    public function apiGetRegions(): []
    {
        $cities = $this->apiGetCities();
        $regions = [];

        /** @var City $city */
        foreach ($cities as $city) {
            if (!empty($city->County->Id))
                $regions->put($city->County->Id, $city->County);
        }

        return $regions;
    }

    public function apiGetCountries(): array
    {   
        $cities = $this->apiGetCities();

        $countries = [];
        foreach ($cities as $city) {
            $countries->put($city->Country->Id, $city->Country);
        }

        return $countries;
    }

    public function apiGetHotels(): []
    {
        $getAllCities = [Handles::TUI_TRAVEL_CENTER_V2, Handles::CHRISTIAN_TOUR_V2, TestHandles::LOCALHOST_HOLIDAYOFFICE_TOUR_ONLY];
        $includedCountry = [Handles::HOLIDAYOFFICE, 147];
        $includedCities = [
            Handles::HOLIDAYOFFICE => [350, 2583]
        ];

        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $cities = $this->apiGetCities();

        $file = 'hotels';
        $countriesJson = Utils::getFromCache($this, $file);
        if ($countriesJson === null || !empty($filter->CityId)) {

            $url = $this->apiUrl . '/v2/static/hotels';

            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);

            $options['headers'] = [
                'Authorization' => 'Basic ' . $pass
            ];
            $cities = $this->apiGetCities();

            $hotelsResponseArr = [];

            if (!in_array($this->handle, $getAllCities)) {
                $adFilter = new AvailabilityDatesFilter(['type' => 'charter']);
                $availabilityDates = $this->apiGetAvailabilityDates($adFilter);
            }

            if (!empty($filter->CityId)) {

                if (in_array($this->handle, $getAllCities)) {
                    $options['body'] = json_encode([
                        'destinationIds' => [
                            $filter->CityId
                        ]
                    ]);
                    $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                    $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                    $hotelsResponseArr[] = [$respObj, $options];
                } else {
                    $get = false;
                    foreach ($availabilityDates as $availabilityDate) {
                        $cityId = $availabilityDate->To->City->Id;
                        if ($cityId === $filter->CityId) {
                            $get = true;
                            break;
                        }
                    }

                    if (!$get) {
                        $hotelsResponseArr = [];
                    } else {
    
                        $options['body'] = json_encode([
                            'destinationIds' => [
                                $filter->CityId
                            ]
                        ]);
                        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                        $hotelsResponseArr[] = [$respObj, $options];
                    }
                }
                
            } else {

                if (in_array($this->handle, $getAllCities)) {
                    foreach ($cities as $cityFromList) {

                        $options['body'] = json_encode([
                            'destinationIds' => [
                                $cityFromList->Id
                            ]
                        ]);
                        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                        $hotelsResponseArr[] = [$respObj, $options, $cityFromList];
                        
                    }
                } else {
                    $cityIds = [];
                    /** @var AvailabilityDates $availabilityDate */
                    foreach ($availabilityDates as $availabilityDate) {
                        $cityId = $availabilityDate->To->City->Id;
                        $city = $availabilityDate->To->City;
                        if (!isset($cityIds[$cityId])) {
                            $options['body'] = json_encode([
                                'destinationIds' => [
                                    $cityId
                                ]
                            ]);
                            $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                            $hotelsResponseArr[] = [$respObj, $options, $city];
                            $cityIds[$cityId] = $cityId;
                        }
                    }
                    if ($this->handle === $includedCountry[0]) {
                        $citiesFromIncludedCountry = $cities->filter(fn(City $city) => $city->Country->Id == $includedCountry[1]);
                        foreach ($citiesFromIncludedCountry as $cityIncluded) {
                            $options['body'] = json_encode([
                                'destinationIds' => [
                                    $cityIncluded->Id
                                ]
                            ]);
                            $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                            $hotelsResponseArr[] = [$respObj, $options, $city];
                        }
                    }

                    if (isset($includedCities[$this->handle])) {
                        foreach ($includedCities[$this->handle] as $cityIncluded) {
                            $options['body'] = json_encode([
                                'destinationIds' => [
                                    $cityIncluded
                                ]
                            ]);
                            $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                            $hotelsResponseArr[] = [$respObj, $options, $city];
                        }
                    }
                }
            }

            $hotels = [];

            $data = [];
            foreach ($hotelsResponseArr as $hotelsPerCitiesResponse) {
                $respObj = $hotelsPerCitiesResponse[0];
                
                $hotelsResponse = json_decode($respObj->getBody(), true);

                foreach ($hotelsResponse as $hotelResponse) {
                    $hotel = new Hotel();

                    $hotelAddress = new HotelAddress();
                    $city = $cities->get($hotelResponse['location']);
                    if ($city === null) {
                        continue;
                    }
                    $hotelAddress->City = $city;
                    $hotelAddress->Details = $hotelResponse['address'];
                    if (strlen($hotelAddress->Details) > 255) {
                        $hotelAddress->Details = preg_replace('/\s+/', ' ', $hotelAddress->Details);
                        if (strlen($hotelAddress->Details) > 255) {
                            $hotelAddress->Details = substr($hotelAddress->Details, 0, 254);
                        }
                    }

                    $hotelAddress->Latitude = $hotelResponse['latitude'];
                    $hotelAddress->Longitude = $hotelResponse['longitude'];
                    $hotel->Address = $hotelAddress;

                    $cp = new ContactPerson();
                    $cp->Phone = $hotelResponse['phone'];
                    $cp->Fax = $hotelResponse['fax'];

                    $hotel->ContactPerson = $cp;

                    $hc = new HotelContent();
                    
                    $description = '';

                    if (!empty($hotelResponse['description'])) {
                        $description = $hotelResponse['description'] . '<br>';
                    }
                    
                    if (!empty($hotelResponse['detailedDescriptions'])) {
                        foreach ($hotelResponse['detailedDescriptions'] as $textArr) {
                            if (!empty($textArr['label'])) {
                                $description .= '<b>' . $textArr['label'] .'</b>';
                            }
                            $description .= $textArr['text'];
                        }
                    }
                    $hotel->Id = $hotelResponse['id'];

                    $hc->Content = $description;

                    $items = new HotelImageGalleryItemCollection();

                    foreach ($hotelResponse['images'] as $image) {
                        $imageItem = new HotelImageGalleryItem();
                        if (strlen($image['url']) > 255) {
                            continue;
                        }
                        $imageItem->RemoteUrl = $image['url'];
                        $items->add($imageItem);
                    }

                    $him = new HotelImageGallery();
                    $him->Items = $items;

                    $hc->ImageGallery = $him;
                    $hotel->Content = $hc;

                    $facilities = new FacilityCollection();

                    foreach ($hotelResponse['amenities'] as $facilityResp) {
                        $facility = new Facility();
                        $facility->Id = preg_replace('/\s+/', '', $facilityResp);
                        $facility->Name = $facilityResp;
                        $facilities->add($facility);
                    }
                    
                    $hotel->Facilities = $facilities;

                    $hotel->Name = $hotelResponse['name'];

                    $hotel->Stars = (int) $hotelResponse['class'];
                    $hotel->WebAddress = $hotelResponse['url'];

                    $hotels->put($hotel->Id, $hotel);

                    //id hotel, nume tara, nume zona, nume oras, id dat(internal)
                    // $data[$hotel->Id] = [$hotel->Id, $hotel->Name, $hotel->Address->City->Country->Name,
                    //     $hotel->Address->City->County->Name, $hotel->Address->City->Name,
                    //     json_encode($hotelResponse['inhouseHotelIds'])];
                }
            }
            //Utils::createCsv(__DIR__ . '/hello', ['hotel id','nume', 'tara', 'zona', 'oras', 'inhouseHotelIds'], $data);

            if (empty($filter->CityId)) {
                $data = json_encode_pretty($hotels);
                Utils::writeToCache($this, $file, $data);
            }
        } else {
            $hotelsArray = json_decode($countriesJson, true);
            $hotels = ResponseConverter::convertToCollection($hotelsArray, []::class);
        }

        return $hotels;
    }

    public function getTourHotels(): []
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $file = 'hotels-tour';
        $countriesJson = Utils::getFromCache($this, $file);
        if ($countriesJson === null || !empty($filter->CityId)) {

            $url = $this->apiUrl . '/v2/static/hotels';

            $client = HttpClient::create();

            $pass = base64_encode($this->username . ':' . $this->password);

            $options['headers'] = [
                'Authorization' => 'Basic ' . $pass
            ];
            $cities = $this->apiGetCities();

            $hotelsResponseArr = [];
            if (!empty($filter->CityId)) {
                $options['body'] = json_encode([
                    'destinationIds' => [
                        $filter->CityId
                    ]
                ]);
                $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                $hotelsResponseArr[] = [$respObj, $options];
            } else {
                $adFilter = new AvailabilityDatesFilter(['type' => 'tour']);
                $availabilityDates = $this->apiGetAvailabilityDates($adFilter);

                $cityIds = [];
                /** @var AvailabilityDates $availabilityDate */
                foreach ($availabilityDates as $availabilityDate) {
                    $cityId = $availabilityDate->To->City->Id;
                    if (!isset($cityIds[$cityId])) {
                        $options['body'] = json_encode([
                            'destinationIds' => [
                                str_replace('-t', '', $cityId)
                            ]
                        ]);
                        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
                        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
                        $hotelsResponseArr[] = [$respObj, $options, $availabilityDate->To->City];
                        $cityIds[$cityId] = $cityId;
                    }
                }
            }

            $hotels = [];

            foreach ($hotelsResponseArr as $hotelsPerCitiesResponse) {
                $respObj = $hotelsPerCitiesResponse[0];
                $city = $hotelsPerCitiesResponse[2];
                
                $hotelsResponse = json_decode($respObj->getBody(), true);

                foreach ($hotelsResponse as $hotelResponse) {
                    $hotel = new Hotel();

                    $hotelAddress = new HotelAddress();
                    $city = $cities->get($hotelResponse['location']);
                    if ($city === null) {
                        continue;
                    }
                    $hotelAddress->City = $city;
                    $hotelAddress->Details = $hotelResponse['address'];
                    if (strlen($hotelAddress->Details) > 255) {
                        $hotelAddress->Details = preg_replace('/\s+/', ' ', $hotelAddress->Details);
                        if (strlen($hotelAddress->Details) > 255) {
                            $hotelAddress->Details = substr($hotelAddress->Details, 0, 254);
                        }
                    }

                    $hotelAddress->Latitude = $hotelResponse['latitude'];
                    $hotelAddress->Longitude = $hotelResponse['longitude'];
                    $hotel->Address = $hotelAddress;

                    $cp = new ContactPerson();
                    $cp->Phone = $hotelResponse['phone'];
                    $cp->Fax = $hotelResponse['fax'];

                    $hotel->ContactPerson = $cp;

                    $hc = new HotelContent();

                    $description = '';

                    if (!empty($hotelResponse['description'])) {
                        $description = $hotelResponse['description'] . '<br>';
                    }
                    
                    if (!empty($hotelResponse['detailedDescriptions'])) {
                        foreach ($hotelResponse['detailedDescriptions'] as $textArr) {
                            if (!empty($textArr['label'])) {
                                $description .= '<b>' . $textArr['label'] .'</b>';
                            }
                            $description .=  $textArr['text'];
                        }
                    }

                    $hc->Content = $description;

                    $items = new HotelImageGalleryItemCollection();

                    foreach ($hotelResponse['images'] as $image) {
                        $imageItem = new HotelImageGalleryItem();
                        if (strlen($image['url']) > 512) {
                            continue;
                        }
                        $imageItem->RemoteUrl = $image['url'];
                        $items->add($imageItem);
                    }

                    $him = new HotelImageGallery();
                    $him->Items = $items;

                    $hc->ImageGallery = $him;
                    $hotel->Content = $hc;

                    $facilities = new FacilityCollection();

                    foreach ($hotelResponse['amenities'] as $facilityResp) {
                        $facility = new Facility();
                        $facility->Id = preg_replace('/\s+/', '', $facilityResp);
                        $facility->Name = $facilityResp;
                        $facilities->add($facility);
                    }
                    
                    $hotel->Facilities = $facilities;

                    $hotel->Id = $hotelResponse['id'];
                    $hotel->Name = $hotelResponse['name'];

                    $hotel->Stars = (int) $hotelResponse['class'];
                    $hotel->WebAddress = $hotelResponse['url'];

                    $hotels->put($hotel->Id, $hotel);
                }
            }
            if (empty($filter->CityId)) {
                $data = json_encode_pretty($hotels);
                Utils::writeToCache($this, $file, $data);
            }
        } else {
            $hotelsArray = json_decode($countriesJson, true);
            $hotels = ResponseConverter::convertToCollection($hotelsArray, []::class);
        }

        return $hotels;
    }

    private function getHotelOffers(AvailabilityFilter $filter): array
    {
        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateIndividualOffersFilter($filter);

        $availabilityCollection = [];

        $url = $this->apiUrl . '/v2/search/hotels';
        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $options['headers'] = [
            'Authorization' => 'Basic ' . $pass,
            'Accept' => 'application/json'
        ];

        if (!empty($this->apiContext)) {
            $options['headers']['X-AgentCode'] = $this->apiContext;
        }

        $destination = '';
        if (!empty($filter->cityId)) {
            $destination = (int) $filter->cityId;
        } else {
            $destination = (int) $filter->regionId;
        }

        $ages = [];
        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $ages[] = (int) $age;
            }
        }

        $body = [
            'currency' => 'EUR',
            'destination' => $destination,
            'checkIn' => $filter->checkIn,
            'checkOut' => $filter->checkOut,
            'rooms' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'childAges' => $ages
                ]
            ],
            'forPackage' => false,
            'showBlackedOut' => true
        ];

        if (!empty($filter->hotelId)) {
            $body['hotelIds'] = [(int) $filter->hotelId];
        }

        $options['body'] = json_encode($body);
        
        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respObj->getBody(), $respObj->getStatusCode());
        
        if ($respObj->getStatusCode() === 500) {
            return $availabilityCollection;
        }
        
        $respJson = $respObj->getBody();

        $headers = $respObj->getHeaders(false);

        if (!isset($headers['set-cookie'])) {
            return $availabilityCollection;
        }

        $cookieheader = $headers['set-cookie'][0];
        $start = strpos($cookieheader, 'PHPSESSID=') + strlen('PHPSESSID=');
        $length = strpos($cookieheader, ';') - strlen('PHPSESSID=');
        $phpsessid = substr($cookieheader, $start, $length);

        $responseData = json_decode($respJson, true);

        if (!empty($responseData['error'])) {
            return $availabilityCollection;
        }
        foreach ($responseData ?? [] as $index => $responseOffer) {

            $availabilityStr = null;
            if (!$responseOffer['isAvailable'] && $responseOffer['isBookable']) {
                $availabilityStr = Offer::AVAILABILITY_ASK;
            } elseif ($responseOffer['isAvailable']) {
                $availabilityStr = Offer::AVAILABILITY_YES;
            } else {
                $availabilityStr = Offer::AVAILABILITY_NO;
            }

            $checkInDateTime = new DateTime($filter->checkIn);
            $checkOutDateTime = new DateTime($filter->checkOut);

            $offer = Offer::createIndividualOffer(
                $responseOffer['hotelId'],
                md5($responseOffer['rooms']),
                md5($responseOffer['rooms']),
                $responseOffer['rooms'],
                md5($responseOffer['meals'] ?? 'Fara masa)'),
                $responseOffer['meals'] ?? 'Fara masa)',
                $checkInDateTime,
                $checkOutDateTime,
                $filter->rooms->first()->adults,
                $post['args'][0]['rooms'][0]['childrenAges']->toArray(),
                'EUR',
                $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'] - $responseOffer['priceInfo']['commission'],
                $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'] + $responseOffer['totalDiscount'],
                $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'],
                $responseOffer['priceInfo']['commission'],
                $availabilityStr
            );

            $currency = new Currency();
            $currency->Code = 'EUR';

            $cancelFees = new OfferCancelFeeCollection();
            foreach ($responseOffer['cancellationCharges'] as $charge) {
                $cancelFee = new OfferCancelFee();
                $cancelFee->Currency = $currency;
                $cancelFee->DateStart = (new DateTime())->format('Y-m-d');
                $cancelFee->DateEnd = (new DateTime($charge['applicableBefore']))->format('Y-m-d');
                $cancelFee->Price = $charge['charge'];
            }
            $offer->CancelFees = $cancelFees;

            $offer->CheckIn = $filter->checkIn;
            $offer->offerId = $index;
            $offer->InitialData = $phpsessid;

            $existingAvailability = $availabilityCollection->get($responseOffer['hotelId']);
            if ($existingAvailability === null) {
                $availability = new Availability();
                $availability->Id = $responseOffer['hotelId'];
                $offers = new OfferCollection();

                $offers->put($offer->Code, $offer);
                $availability->Offers = $offers;
            } else {
                // adding offers to the existing availability
                $availability = $existingAvailability;
                $availability->Offers->put($offer->Code, $offer);
            }

            $availabilityCollection->put($availability->Id, $availability);
        }

        return $availabilityCollection;
    }

    private function getPackageOrTourOffersFromPackages(AvailabilityFilter $filter): array
    {
        $isTour = $filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR;
        if ($isTour) {
            EtripValidator::make()
                ->validateUsernameAndPassword($this->post)
                ->validateTourOffersFilter($filter);
        } else {
            EtripValidator::make()
                ->validateUsernameAndPassword($this->post)
                ->validateCharterOffersFilter($filter);
        }

        $cities = $this->apiGetCities();

        $availabilityCollection = [];

        $url = $this->apiUrl . '/v2/search/packages';
        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $options['headers'] = [
            'Authorization' => $basic,
            'Accept' => 'application/json'
        ];
        if (!empty($this->apiContext)) {
            $options['headers']['X-AgentCode'] = $this->apiContext;
        }

        $destination = '';
        if (!empty($filter->cityId)) {
            $destination = (int) $filter->cityId;
        } else {
            $destination = (int) $filter->regionId;
        }

        $ages = [];
        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $ages[] = (int) $age;
            }
        }

        $isFlight = true;

        if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
            $isFlight = false;
        }

        $body = [
            'currency' => 'EUR',
            'isTour' => $isTour,
            'isFlight' => $isFlight,
            'isBus' => !$isFlight,
            'departure' => $filter->departureCity ? (int) $filter->departureCity : (int) $filter->departureCityId,
            'destination' => $destination,
            'departureDate' => $filter->checkIn,
            'duration' => $filter->days,
            'rooms' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'childAges' => $ages
                ]
            ],
            'showBlackedOut' => true
        ];

        if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
            $body['isFlight'] = false;
            $body['isBus'] = true;
        }

        if (!empty($filter->hotelId) && !$isTour) {
            $body['hotelIds'] = [(int) $filter->hotelId];
        }

        $options['body'] = json_encode($body);
        
        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $respJson = $respObj->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respJson, $respObj->getStatusCode());

        $headers = $respObj->getHeaders();

        if (!isset($headers['set-cookie'])) {
            return $availabilityCollection;
        }

        $cookieheader = $headers['set-cookie'][0];
        $start = strpos($cookieheader, 'PHPSESSID=') + strlen('PHPSESSID=');
        $length = strpos($cookieheader, ';') - strlen('PHPSESSID=');
        $phpsessid = substr($cookieheader, $start, $length);

        $responseData = json_decode($respJson, true);

        $aiportMap = AirportMap::getAirportMap();

        foreach ($responseData ?? [] as $index => $responseOffer) {
            $availability = new Availability();
            if ($isTour) {
                if (!empty($filter->hotelId) && $responseOffer['packageId'] != $filter->hotelId) {
                    continue;
                }
                $availability->Id = $responseOffer['packageId'];
            } else {
                
                $availability->Id = $responseOffer['hotel']['hotelId'];
                if ($filter->showHotelName) {
                    $availability->Name = $responseOffer['hotel']['hotelName'];
                }
            }

            $departureTransportDate = '';
            $departureArrivalTransportDate = '';
            $returnTransportDate = '';
            $returnArrivalTransportDate = '';

            if ($isFlight) {
                if (empty($responseOffer['flight']['journeys'][0])) {
                    continue;
                }

                $departureTransportDate = 
                    $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])] // the last flight
                        ['legs']['0']['departure']; // the first leg
                $departureArrivalTransportDate = 
                    $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])] // the last flight
                        ['legs'][array_key_last($responseOffer['flight']['journeys'][0]
                            [array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['arrival']; // the last leg

                if (empty($responseOffer['flight']['journeys'][1])) {
                    continue;
                }
                
                $returnTransportDate = 
                    $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']['0']['departure'];
                
                $returnArrivalTransportDate = 
                    $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])] // the last flight
                        ['legs'][array_key_last($responseOffer['flight']['journeys'][1]
                            [array_key_last($responseOffer['flight']['journeys'][1])]['legs'])]['arrival']; // the last leg

            } else {
                $departureTransportDate = $responseOffer['bus']['outboundDate'];
                $departureArrivalTransportDate = $responseOffer['bus']['outboundArrivalDate'];
                $returnTransportDate = $responseOffer['bus']['inboundDate'];
                $returnArrivalTransportDate = $responseOffer['bus']['inboundArrivalDate'];
            }

            $checkInDateTime = new DateTimeImmutable($departureArrivalTransportDate);
            $checkOutDateTime = $checkInDateTime->modify('+' . $filter->days . ' days');
            $offerCheckIn = $checkInDateTime->format('Y-m-d');

            $offer = new Offer();

            $offer->CheckIn = $offerCheckIn;
            $offer->offerId = $index;
            $offer->InitialData = $phpsessid;

            if (!$responseOffer['isAvailable'] && $responseOffer['isBookable']) {
                $offer->Availability = Offer::AVAILABILITY_ASK;
            } elseif ($responseOffer['isAvailable']) {
                $offer->Availability = Offer::AVAILABILITY_YES;
            } else {
                $offer->Availability = Offer::AVAILABILITY_NO;
            }

            
            $currency = new Currency();
            $currency->Code = 'EUR';

            $cancelFees = new OfferCancelFeeCollection();
            foreach ($responseOffer['cancellationCharges'] as $charge) {
                $cancelFee = new OfferCancelFee();
                $cancelFee->Currency = $currency;
                $cancelFee->DateStart = (new DateTime())->format('Y-m-d');
                $cancelFee->DateEnd = (new DateTime($charge['applicableBefore']))->format('Y-m-d');
                $cancelFee->Price = $charge['charge'];
            }
            $offer->CancelFees = $cancelFees;

            $offer->Currency = $currency;
            //$offer->Days = $filter->days;

            $offer->Comission = $responseOffer['priceInfo']['commission'];
            $offer->Gross = $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'];

            $offer->Net = $offer->Gross - $offer->Comission;
            $offer->InitialPrice = $offer->Gross + $responseOffer['totalDiscount'];

            $room = new Room();
            $room->Id = preg_replace('/\s+/', '', $responseOffer['hotel']['rooms']);
            $room->Availability = $offer->Availability;
            $room->CheckinAfter = $offerCheckIn;
            $room->CheckinBefore = $checkOutDateTime->format('Y-m-d');
            $room->Currency = $currency;

            $merch = new RoomMerch();
            $merch->Name = $responseOffer['hotel']['rooms'];
            $merch->Id = $room->Id;
            $merch->Code = $merch->Id;
            $merch->Title = $merch->Name;

            $roomMerchType = new RoomMerchType();
            $roomMerchType->Id = $room->Id;
            $roomMerchType->Title = $merch->Name;
            $merch->Type = $roomMerchType;

            $room->Merch = $merch;

            $offer->Item = $room;
            $offer->Rooms = new RoomCollection([$room]);

            $meal = new MealItem();
            $meal->Currency = $currency;

            $mealMerch = new MealMerch();
            $mealMerch->Title = $responseOffer['hotel']['meals'];
            $mealMerch->Id = preg_replace('/\s+/', '', $mealMerch->Title);

            $mealType = new MealMerchType();
            $mealType->Id = $mealMerch->Id;
            $mealType->Title = $mealMerch->Title;

            $mealMerch->Type = $mealType;

            $meal->Merch = $mealMerch;

            $offer->MealItem = $meal;
            $offer->Code = $availability->Id . '~' . $room->Id . '~' . $mealMerch->Id . '~' 
                . $room->CheckinAfter . '~' . $room->CheckinBefore . '~' . $offer->Gross . '~' 
                . $filter->rooms->first()->adults 
                . (count($post['args'][0]['rooms'][0]['childrenAges']) > 0 ? '~' . implode('|', $post['args'][0]['rooms'][0]['childrenAges']->toArray()) : '')
            ;

            // can be also bus
            $flightDepartureDateTime = new DateTimeImmutable($departureTransportDate);
            $flightArrivalDateTime = new DateTimeImmutable($departureArrivalTransportDate);
            $flightReturnDateTime = new DateTimeImmutable($returnTransportDate);
            $flightReturnArrivalDateTime = new DateTimeImmutable($returnArrivalTransportDate);

            // departure transport item merch
            $departureTransportMerch = new TransportMerch();
            $departureTransportMerch->Title = "Dus: ". $flightDepartureDateTime->format('d.m.Y');
            $departureTransportMerch->Category = new TransportMerchCategory();
            $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
            $departureTransportMerch->TransportType = $isFlight ? TransportMerch::TRANSPORT_TYPE_PLANE : TransportMerch::TRANSPORT_TYPE_BUS;
            $departureTransportMerch->DepartureTime = $flightDepartureDateTime->format('Y-m-d H:i');
            $departureTransportMerch->ArrivalTime = $flightArrivalDateTime->format('Y-m-d H:i');
            
            if ($isFlight) {
                $departureTransportMerch->DepartureAirport = $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']['0']['from'];
                $departureTransportMerch->ReturnAirport = $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']
                    [array_key_last($responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['to'];
            }

            $departureTransportMerch->From = new TransportMerchLocation();
            $cityDep = new City();

            if ($isFlight) {
                $cityDep = $cities->get($filter->departureCity ?? $filter->departureCityId);
            } else {
                $cityDep = $cities->get($responseOffer['bus']['from']);
            }

            $departureTransportMerch->From->City = $cityDep;

            $cityArr = new City();

            if ($isFlight) {

                $cityArr->Id = $departureTransportMerch->ReturnAirport;
                if (isset($aiportMap[$departureTransportMerch->ReturnAirport])) {
                    $cityArr->Name = $aiportMap[$departureTransportMerch->ReturnAirport]['cityName'];
                }
            } else {
                $cityArr = $cities->get($responseOffer['bus']['to']);
            }
            $departureTransportMerch->To = new TransportMerchLocation();
            $departureTransportMerch->To->City = $cityArr;

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
            $returnTransportMerch->TransportType = $isFlight ? TransportMerch::TRANSPORT_TYPE_PLANE : TransportMerch::TRANSPORT_TYPE_BUS;
            $returnTransportMerch->DepartureTime = $flightReturnDateTime->format('Y-m-d H:i');
            $returnTransportMerch->ArrivalTime = $flightReturnArrivalDateTime->format('Y-m-d H:i');

            if ($isFlight) {

                $returnTransportMerch->DepartureAirport = $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']['0']['from'];
                $returnTransportMerch->ReturnAirport = $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']
                    [array_key_last($responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs'])]['to'];
            }

            $cityArrReturn = new City();

            if ($isFlight) {
                $cityArrReturn->Id = $departureTransportMerch->ReturnAirport;
                if (isset($aiportMap[$returnTransportMerch->DepartureAirport])) {
                    $cityArrReturn->Name = $aiportMap[$returnTransportMerch->DepartureAirport]['cityName'];
                }
            } else {
                $cityArrReturn = $cityArr;
            }
            $returnTransportMerch->From = new TransportMerchLocation();
            $returnTransportMerch->From->City = $cityArrReturn;

            $cityDepReturn = new City();

            if ($isFlight) {
                $cityDepReturn->Id = $departureTransportMerch->DepartureAirport;
                if (isset($aiportMap[$returnTransportMerch->ReturnAirport])) {
                    $cityDepReturn->Name = $aiportMap[$returnTransportMerch->ReturnAirport]['cityName'];
                }
            } else {
                $cityDepReturn = $cityDep;
            }
            $returnTransportMerch->To = new TransportMerchLocation();
            $returnTransportMerch->To->City = $cityDepReturn;

            $returnTransportItem = new ReturnTransportItem();
            $returnTransportItem->Merch = $returnTransportMerch;
            $returnTransportItem->Currency = $offer->Currency;
            $returnTransportItem->DepartureDate = $flightReturnDateTime->format('Y-m-d');
            $returnTransportItem->ArrivalDate = $flightReturnArrivalDateTime->format('Y-m-d');

            $departureTransportItem->Return = $returnTransportItem;

            $offer->DepartureTransportItem = $departureTransportItem;
            $offer->ReturnTransportItem = $returnTransportItem;

            $offer->Items = [];
            if ($isFlight) {
                if ($this->handle === TestHandles::LOCALHOST_HOLIDAYOFFICE) {
                    $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory, '');
                } else {
                    $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                }
            }
            if ($responseOffer['transfer'] !== null) {
                
                if ($this->handle === TestHandles::LOCALHOST_HOLIDAYOFFICE) {
                    $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory, '');
                } else {
                    $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);
                }
            }

            $offers = new OfferCollection();
            $offers->put($offer->Code, $offer);

            $existingAvailability = $availabilityCollection->get($availability->Id);
            if ($existingAvailability === null) {
                $availability->Offers = $offers;
            } else {
                // adding offers to the existing availability
                $availability = $existingAvailability;
                $availability->Offers->put($offer->Code, $offer);
            }

            $availabilityCollection->put($availability->Id, $availability);
        }
        
        return $availabilityCollection;
    }

    private function getTourOffersFromTours(AvailabilityFilter $filter): array
    {
        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateTourOffersFilter($filter);


        $cities = $this->apiGetCities();

        $availabilityCollection = [];

        $url = $this->apiUrl . '/v2/search/tours';
        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $options['headers'] = [
            'Authorization' => $basic,
            'Accept' => 'application/json'
        ];
        if (!empty($this->apiContext)) {
            $options['headers']['X-AgentCode'] = $this->apiContext;
        }

        $destination = '';
        if (!empty($filter->cityId)) {
            $destination = (int) str_replace('-t', '', $filter->cityId);
        } else {
            $destination = (int) $filter->regionId;
        }

        $ages = [];
        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $ages[] = (int) $age;
            }
        }

        // $isFlight = true;

        // if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
        //     $isFlight = false;
        // }

        $body = [
            'currency' => 'EUR',
            'departures' => [$filter->departureCity ? (int) $filter->departureCity : (int) $filter->departureCityId],
            'destinations' => [$destination],
            'dateFrom' => $filter->checkIn,
            'dateTo' => $filter->checkIn,
            'minDuration' => $filter->days,
            'maxDuration' => $filter->days,
            'rooms' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'childAges' => $ages
                ]
            ]
        ];

        if (!empty($filter->hotelId)) {
            $body['tourIds'] = [(int) $filter->hotelId];
        }

        $options['body'] = json_encode($body);
        
        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $respJson = $respObj->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respJson, $respObj->getStatusCode());

        $headers = $respObj->getHeaders();

        if (!isset($headers['set-cookie'])) {
            return $availabilityCollection;
        }

        $cookieheader = $headers['set-cookie'][0];
        $start = strpos($cookieheader, 'PHPSESSID=') + strlen('PHPSESSID=');
        $length = strpos($cookieheader, ';') - strlen('PHPSESSID=');
        $phpsessid = substr($cookieheader, $start, $length);

        $responseData = json_decode($respJson, true);

        //$aiportMap = AirportMap::getAirportMap();

        $currency = 'EUR';
        foreach ($responseData ?? [] as $index => $responseHotel) {

            //dd($responseHotel);
            if (count($responseHotel['dates']) > 1 || count($responseHotel['dates'][0]['rooms']) > 1) {
                //dd($responseHotel);
                throw new Exception($this->handle . ' wrong data');
            }
            if ($responseHotel['dates'][0]['availability'] === 'unavailable') {
                continue;
            }

            if ($responseHotel['dates'][0]['availability'] !== 'unavailable' && 
                $responseHotel['dates'][0]['availability'] !== 'available') {
                throw new Exception('wrong availability');
            }

            $transportsFound = [];

            foreach ($responseHotel['dates'][0]['elements'] as $element) {
                if ($element['type'] === 'flight') {
                    $transportsFound[] = $element;
                }
                if ($element['type'] === 'bus') {
                    $transportsFound[] = $element;
                }
            }
            //dd($responseHotel);
            // if (($isFlight && $isBus) || (!$isFlight && !$isBus)) {
            //     throw new Exception($this->handle . ': bad data');
            // }

            // if (count($transportsFound) > 1) {
            //     throw new Exception($this->handle . ': bad flight data');
            // }
            if ($transportsFound[0]['type'] === 'bus' && $filter->transportTypes->first() !== AvailabilityFilter::TRANSPORT_TYPE_BUS) {
                continue;
            }

            if ($transportsFound[0]['type'] === 'flight' && $filter->transportTypes->first() !== AvailabilityFilter::TRANSPORT_TYPE_PLANE) {
                continue;
            }

            if ($transportsFound[0]['type'] !== 'flight' && $transportsFound[0]['type'] !== 'bus') {
                continue;
            }

            $firstTransportType = '';
            if ($transportsFound[0]['type'] === 'bus') {
                $firstTransportType = AvailabilityFilter::TRANSPORT_TYPE_BUS;
            } else {
                $firstTransportType = AvailabilityFilter::TRANSPORT_TYPE_PLANE;
            }

            $offers = new OfferCollection();

            foreach ($responseHotel['dates'][0]['rooms'][0] as $responseOffer) {

                $checkInDateTime = new DateTimeImmutable($filter->checkIn);
                $checkOutDateTime = $checkInDateTime->modify('+' . $filter->days . ' days');

                $commision = $responseOffer['priceInfo']['commission'];
                $gross = $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'];

                $net = $gross - $commision;
                $initial = $gross + $responseOffer['totalDiscount'];

                $availability = null;

                if ($responseOffer['isBookable']) {
                    $availabilityStr = $responseOffer['availability'];
                    if ($availabilityStr === 'available') {
                        $availability = Offer::AVAILABILITY_YES;
                    } elseif ($availabilityStr === 'unavailable') {
                        $availability = Offer::AVAILABILITY_NO;
                    } else  {
                        throw new Exception($this->handle . ': ' . $availabilityStr);
                    }
                } else {
                    $availability = Offer::AVAILABILITY_NO;
                }
                //dd($responseHotel);
               
                $flightDepartureDt= (new DateTimeImmutable($transportsFound[0]['startDate']))->setTime(0,0);

                if (isset($transportsFound[1])) {
                    $flightReturnDt = (new DateTimeImmutable($transportsFound[1]['startDate']))->setTime(0,0);
                } else {
                    $flightReturnDt = (new DateTimeImmutable($transportsFound[0]['endDate']))->setTime(0,0);
                }
                
                $departureCity = $cities->get($filter->departureCity ?? $filter->departureCityId);
                $arrivalCity = $cities->get($filter->cityId);

                $offer = Offer::createCharterOrTourOffer(
                    $responseHotel['id'],
                    $responseOffer['id'],
                    $responseOffer['id'],
                    $responseOffer['name'],
                    null,
                    null,
                    $checkInDateTime,
                    $checkOutDateTime,
                    $filter->rooms->first()->adults,
                    $post['args'][0]['rooms'][0]['childrenAges']->toArray(),
                    $currency,
                    $net,
                    $initial,
                    $gross,
                    $commision,
                    $availability,
                    null,
                    $flightDepartureDt,
                    $flightDepartureDt,
                    $flightReturnDt,
                    $flightReturnDt,
                    '',
                    '',
                    '',
                    '',
                    $firstTransportType,
                    $departureCity,
                    $arrivalCity,
                    $arrivalCity,
                    $departureCity,
                    null,
                    false
                );

                $offer->CheckIn = $filter->checkIn;
                $offer->offerId = $index;
                $offer->InitialData = $phpsessid;

                $currencyObj = new Currency();
                $currencyObj->Code = $currency;

                $cancelFees = new OfferCancelFeeCollection();
                foreach ($responseOffer['cancellationCharges'] as $charge) {
                    $cancelFee = new OfferCancelFee();
                    $cancelFee->Currency = $currencyObj;
                    $cancelFee->DateStart = (new DateTime())->format('Y-m-d');
                    $cancelFee->DateEnd = (new DateTime($charge['applicableBefore']))->format('Y-m-d');
                    $cancelFee->Price = $charge['charge'];
                }
                $offer->CancelFees = $cancelFees;

                $offers->put($offer->Code, $offer);
            }

            $tour = new Availability();

            $existingAvailability = $availabilityCollection->get($responseHotel['id']);
            if ($existingAvailability === null) {
                $tour->Offers = $offers;
                $tour->Id = $responseHotel['id'];
            } else {
                // adding offers to the existing availability
                $tour = $existingAvailability;
                $tour->Offers->put($offer->Code, $offer);
            }

            $availabilityCollection->put($tour->Id, $tour);
        }
        
        return $availabilityCollection;
    }

    private function getJoinedOffers(AvailabilityFilter $filter): array
    {

        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateTourOffersFilter($filter);

        $cities = $this->apiGetCities();

        $availabilityCollection = [];

        $urlPackages = $this->apiUrl . '/v2/search/packages';
        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $optionsPackages['headers'] = [
            'Authorization' => $basic,
            'Accept' => 'application/json'
        ];
        if (!empty($this->apiContext)) {
            $optionsPackages['headers']['X-AgentCode'] = $this->apiContext;
        }

        $destination = '';
        if (!empty($filter->cityId)) {
            $destination = (int) $filter->cityId;
        } else {
            $destination = (int) $filter->regionId;
        }

        $ages = [];
        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $ages[] = (int) $age;
            }
        }

        $isFlight = true;

        if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
            $isFlight = false;
        }

        $body = [
            'currency' => 'EUR',
            'isTour' => true,
            'isFlight' => $isFlight,
            'isBus' => !$isFlight,
            'departure' => $filter->departureCity ? (int) $filter->departureCity : (int) $filter->departureCityId,
            'destination' => $destination,
            'departureDate' => $filter->checkIn,
            'duration' => $filter->days,
            'rooms' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'childAges' => $ages
                ]
            ],
            'showBlackedOut' => true
        ];

        if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
            $body['isFlight'] = false;
            $body['isBus'] = true;
        }

        $optionsPackages['body'] = json_encode($body);
        
        $respObjPackages = $client->request(HttpClient::METHOD_POST, $urlPackages, $optionsPackages);
        //-------------------end request for packages

        //---------------prepare request for tours
        $url = $this->apiUrl . '/v2/search/tours';

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $options['headers'] = [
            'Authorization' => $basic,
            'Accept' => 'application/json'
        ];
        if (!empty($this->apiContext)) {
            $options['headers']['X-AgentCode'] = $this->apiContext;
        }

        $destination = '';
        if (!empty($filter->cityId)) {
            $destination = (int) $filter->cityId;
        } else {
            $destination = (int) $filter->regionId;
        }

        $ages = [];
        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $ages[] = (int) $age;
            }
        }

        // $isFlight = true;

        // if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
        //     $isFlight = false;
        // }

        $body = [
            'currency' => 'EUR',
            'departures' => [$filter->departureCity ? (int) $filter->departureCity : (int) $filter->departureCityId],
            'destinations' => [$destination],
            'dateFrom' => $filter->checkIn,
            'dateTo' => $filter->checkIn,
            'minDuration' => $filter->days,
            'maxDuration' => $filter->days,
            'rooms' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'childAges' => $ages
                ]
            ]
        ];

        if (!empty($filter->hotelId)) {
            $body['tourIds'] = [(int) $filter->hotelId];
        }

        $options['body'] = json_encode($body);
        
        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        //---------------end request for tours

        // --------------get results from packages
        $respJson = $respObjPackages->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $urlPackages, $optionsPackages, $respJson, $respObjPackages->getStatusCode());

        $headers = $respObjPackages->getHeaders();

        if (isset($headers['set-cookie'])) {

            $cookieheader = $headers['set-cookie'][0];
            $start = strpos($cookieheader, 'PHPSESSID=') + strlen('PHPSESSID=');
            $length = strpos($cookieheader, ';') - strlen('PHPSESSID=');
            $phpsessid = substr($cookieheader, $start, $length);

            $responseData = json_decode($respJson, true);

            $aiportMap = AirportMap::getAirportMap();

            foreach ($responseData ?? [] as $index => $responseOffer) {
                $availability = new Availability();

                if (!empty($filter->hotelId) && $responseOffer['packageId'] != $filter->hotelId) {
                    continue;
                }
                $availability->Id = $responseOffer['packageId'];

                $departureTransportDate = '';
                $departureArrivalTransportDate = '';
                $returnTransportDate = '';
                $returnArrivalTransportDate = '';

                if ($isFlight) {
                    if (empty($responseOffer['flight']['journeys'][0])) {
                        continue;
                    }

                    $departureTransportDate = 
                        $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])] // the last flight
                            ['legs']['0']['departure']; // the first leg
                    $departureArrivalTransportDate = 
                        $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])] // the last flight
                            ['legs'][array_key_last($responseOffer['flight']['journeys'][0]
                                [array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['arrival']; // the last leg

                    if (empty($responseOffer['flight']['journeys'][1])) {
                        continue;
                    }
                    
                    $returnTransportDate = 
                        $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']['0']['departure'];
                    
                    $returnArrivalTransportDate = 
                        $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])] // the last flight
                            ['legs'][array_key_last($responseOffer['flight']['journeys'][1]
                                [array_key_last($responseOffer['flight']['journeys'][1])]['legs'])]['arrival']; // the last leg

                } else {
                    $departureTransportDate = $responseOffer['bus']['outboundDate'];
                    $departureArrivalTransportDate = $responseOffer['bus']['outboundArrivalDate'];
                    $returnTransportDate = $responseOffer['bus']['inboundDate'];
                    $returnArrivalTransportDate = $responseOffer['bus']['inboundArrivalDate'];
                }

                $checkInDateTime = new DateTimeImmutable($departureArrivalTransportDate);
                $checkOutDateTime = $checkInDateTime->modify('+' . $filter->days . ' days');
                $offerCheckIn = $checkInDateTime->format('Y-m-d');

                $offer = new Offer();

                $offer->CheckIn = $offerCheckIn;
                $offer->offerId = $index;
                $offer->InitialData = $phpsessid;

                if (!$responseOffer['isAvailable'] && $responseOffer['isBookable']) {
                    $offer->Availability = Offer::AVAILABILITY_ASK;
                } elseif ($responseOffer['isAvailable']) {
                    $offer->Availability = Offer::AVAILABILITY_YES;
                } else {
                    $offer->Availability = Offer::AVAILABILITY_NO;
                }

                
                $currency = new Currency();
                $currency->Code = 'EUR';

                $cancelFees = new OfferCancelFeeCollection();
                foreach ($responseOffer['cancellationCharges'] as $charge) {
                    $cancelFee = new OfferCancelFee();
                    $cancelFee->Currency = $currency;
                    $cancelFee->DateStart = (new DateTime())->format('Y-m-d');
                    $cancelFee->DateEnd = (new DateTime($charge['applicableBefore']))->format('Y-m-d');
                    $cancelFee->Price = $charge['charge'];
                }
                $offer->CancelFees = $cancelFees;

                $offer->Currency = $currency;
                //$offer->Days = $filter->days;

                $offer->Comission = $responseOffer['priceInfo']['commission'];
                $offer->Gross = $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'];

                $offer->Net = $offer->Gross - $offer->Comission;
                $offer->InitialPrice = $offer->Gross + $responseOffer['totalDiscount'];

                $room = new Room();
                $room->Id = preg_replace('/\s+/', '', $responseOffer['hotel']['rooms']);
                $room->Availability = $offer->Availability;
                $room->CheckinAfter = $offerCheckIn;
                $room->CheckinBefore = $checkOutDateTime->format('Y-m-d');
                $room->Currency = $currency;

                $merch = new RoomMerch();
                $merch->Name = $responseOffer['hotel']['rooms'];
                $merch->Id = $room->Id;
                $merch->Code = $merch->Id;
                $merch->Title = $merch->Name;

                $roomMerchType = new RoomMerchType();
                $roomMerchType->Id = $room->Id;
                $roomMerchType->Title = $merch->Name;
                $merch->Type = $roomMerchType;

                $room->Merch = $merch;

                $offer->Item = $room;
                $offer->Rooms = new RoomCollection([$room]);

                $meal = new MealItem();
                $meal->Currency = $currency;

                $mealMerch = new MealMerch();
                $mealMerch->Title = $responseOffer['hotel']['meals'];
                $mealMerch->Id = preg_replace('/\s+/', '', $mealMerch->Title);

                $mealType = new MealMerchType();
                $mealType->Id = $mealMerch->Id;
                $mealType->Title = $mealMerch->Title;

                $mealMerch->Type = $mealType;

                $meal->Merch = $mealMerch;

                $offer->MealItem = $meal;
                $offer->Code = $availability->Id . '~' . $room->Id . '~' . $mealMerch->Id . '~' 
                    . $room->CheckinAfter . '~' . $room->CheckinBefore . '~' . $offer->Gross . '~' 
                    . $filter->rooms->first()->adults 
                    . (count($post['args'][0]['rooms'][0]['childrenAges']) > 0 ? '~' . implode('|', $post['args'][0]['rooms'][0]['childrenAges']->toArray()) : '')
                ;

                // can be also bus
                $flightDepartureDateTime = new DateTimeImmutable($departureTransportDate);
                $flightArrivalDateTime = new DateTimeImmutable($departureArrivalTransportDate);
                $flightReturnDateTime = new DateTimeImmutable($returnTransportDate);
                $flightReturnArrivalDateTime = new DateTimeImmutable($returnArrivalTransportDate);

                // departure transport item merch
                $departureTransportMerch = new TransportMerch();
                $departureTransportMerch->Title = "Dus: ". $flightDepartureDateTime->format('d.m.Y');
                $departureTransportMerch->Category = new TransportMerchCategory();
                $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                $departureTransportMerch->TransportType = $isFlight ? TransportMerch::TRANSPORT_TYPE_PLANE : TransportMerch::TRANSPORT_TYPE_BUS;
                $departureTransportMerch->DepartureTime = $flightDepartureDateTime->format('Y-m-d H:i');
                $departureTransportMerch->ArrivalTime = $flightArrivalDateTime->format('Y-m-d H:i');
                
                if ($isFlight) {
                    $departureTransportMerch->DepartureAirport = $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']['0']['from'];
                    $departureTransportMerch->ReturnAirport = $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']
                        [array_key_last($responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['to'];
                }

                $departureTransportMerch->From = new TransportMerchLocation();
                $cityDep = new City();

                if ($isFlight) {
                    $cityDep = $cities->get($filter->departureCity ?? $filter->departureCityId);
                } else {
                    $cityDep = $cities->get($responseOffer['bus']['from']);
                }

                $departureTransportMerch->From->City = $cityDep;

                $cityArr = new City();

                if ($isFlight) {

                    $cityArr->Id = $departureTransportMerch->ReturnAirport;
                    if (isset($aiportMap[$departureTransportMerch->ReturnAirport])) {
                        $cityArr->Name = $aiportMap[$departureTransportMerch->ReturnAirport]['cityName'];
                    }
                } else {
                    $cityArr = $cities->get($responseOffer['bus']['to']);
                }
                $departureTransportMerch->To = new TransportMerchLocation();
                $departureTransportMerch->To->City = $cityArr;

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
                $returnTransportMerch->TransportType = $isFlight ? TransportMerch::TRANSPORT_TYPE_PLANE : TransportMerch::TRANSPORT_TYPE_BUS;
                $returnTransportMerch->DepartureTime = $flightReturnDateTime->format('Y-m-d H:i');
                $returnTransportMerch->ArrivalTime = $flightReturnArrivalDateTime->format('Y-m-d H:i');

                if ($isFlight) {

                    $returnTransportMerch->DepartureAirport = $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']['0']['from'];
                    $returnTransportMerch->ReturnAirport = $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']
                        [array_key_last($responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs'])]['to'];
                }

                $cityArrReturn = new City();

                if ($isFlight) {
                    $cityArrReturn->Id = $departureTransportMerch->ReturnAirport;
                    if (isset($aiportMap[$returnTransportMerch->DepartureAirport])) {
                        $cityArrReturn->Name = $aiportMap[$returnTransportMerch->DepartureAirport]['cityName'];
                    }
                } else {
                    $cityArrReturn = $cityArr;
                }
                $returnTransportMerch->From = new TransportMerchLocation();
                $returnTransportMerch->From->City = $cityArrReturn;

                $cityDepReturn = new City();

                if ($isFlight) {
                    $cityDepReturn->Id = $departureTransportMerch->DepartureAirport;
                    if (isset($aiportMap[$returnTransportMerch->ReturnAirport])) {
                        $cityDepReturn->Name = $aiportMap[$returnTransportMerch->ReturnAirport]['cityName'];
                    }
                } else {
                    $cityDepReturn = $cityDep;
                }
                $returnTransportMerch->To = new TransportMerchLocation();
                $returnTransportMerch->To->City = $cityDepReturn;

                $returnTransportItem = new ReturnTransportItem();
                $returnTransportItem->Merch = $returnTransportMerch;
                $returnTransportItem->Currency = $offer->Currency;
                $returnTransportItem->DepartureDate = $flightReturnDateTime->format('Y-m-d');
                $returnTransportItem->ArrivalDate = $flightReturnArrivalDateTime->format('Y-m-d');

                $departureTransportItem->Return = $returnTransportItem;

                $offer->DepartureTransportItem = $departureTransportItem;
                $offer->ReturnTransportItem = $returnTransportItem;

                $offer->Items = [];
                if ($isFlight) {
                    if ($this->handle === TestHandles::LOCALHOST_HOLIDAYOFFICE) {
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory, '');
                    } else {
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                    }
                }
                if ($responseOffer['transfer'] !== null) {
                    
                    if ($this->handle === TestHandles::LOCALHOST_HOLIDAYOFFICE) {
                        $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory, '');
                    } else {
                        $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);
                    }
                }

                $offers = new OfferCollection();
                $offers->put($offer->Code, $offer);

                $existingAvailability = $availabilityCollection->get($availability->Id);
                if ($existingAvailability === null) {
                    $availability->Offers = $offers;
                } else {
                    // adding offers to the existing availability
                    $availability = $existingAvailability;
                    $availability->Offers->put($offer->Code, $offer);
                }

                $availabilityCollection->put($availability->Id, $availability);
            }
        }

        // get result from tours
        $respJson = $respObj->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respJson, $respObj->getStatusCode());

        $headers = $respObj->getHeaders();

        if (isset($headers['set-cookie'])) {

            $cookieheader = $headers['set-cookie'][0];
            $start = strpos($cookieheader, 'PHPSESSID=') + strlen('PHPSESSID=');
            $length = strpos($cookieheader, ';') - strlen('PHPSESSID=');
            $phpsessid = substr($cookieheader, $start, $length);

            $responseData = json_decode($respJson, true);

            $currency = 'EUR';
            foreach ($responseData ?? [] as $index => $responseHotel) {

                //dd($responseData);
                if (count($responseHotel['dates']) > 1 || count($responseHotel['dates'][0]['rooms']) > 1) {
                    dd($responseHotel);
                    throw new Exception($this->handle . ' wrong data');
                }

                $transportsFound = [];

                $isFlight = false;;
                $isBus = false;

                foreach ($responseHotel['dates'][0]['elements'] as $element) {
                    if ($element['type'] === 'flight') {
                        $transportsFound[] = $element;
                        $isFlight = true;
                    }
                    if ($element['type'] === 'bus') {
                        $transportsFound[] = $element;
                        $isBus = true;
                    }
                }
                if (($isFlight && $isBus) || (!$isFlight && !$isBus)) {
                    throw new Exception($this->handle . ': bad data');
                }

                if (count($transportsFound) > 1) {
                    throw new Exception($this->handle . ': bad flight data');
                }
                if ($isFlight && $filter->transportTypes->first() !== AvailabilityFilter::TRANSPORT_TYPE_PLANE) {
                    continue;
                }

                foreach ($responseHotel['dates'][0]['rooms'][0] as $responseOffer) {

                    $checkInDateTime = new DateTimeImmutable($filter->checkIn);
                    $checkOutDateTime = $checkInDateTime->modify('+' . $filter->days . ' days');

                    // $offer->Items = [];
                    // if ($this->handle === self::TEST_HANDLE) {
                    //     $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory, '');
                    // } else {
                    //     $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                    // }
                    // if ($responseOffer['transfer'] !== null) {
                        
                    //     if ($this->handle === self::TEST_HANDLE) {
                    //         $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory, '');
                    //     } else {
                    //         $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);
                    //     }
                    // }

                    $commision = $responseOffer['priceInfo']['commission'];
                    $gross = $responseOffer['priceInfo']['gross'] + $responseOffer['priceInfo']['tax'];

                    $net = $gross - $commision;
                    $initial = $gross + $responseOffer['totalDiscount'];

                    $availability = null;

                    if ($responseOffer['isBookable']) {
                        $availabilityStr = $responseOffer['availability'];
                        if ($availabilityStr === 'available') {
                            $availability = Offer::AVAILABILITY_YES;
                        } elseif ($availabilityStr === 'unavailable') {
                            $availability = Offer::AVAILABILITY_NO;
                        } else  {
                            throw new Exception($this->handle . ': ' . $availabilityStr);
                        }
                    } else {
                        $availability = Offer::AVAILABILITY_NO;
                    }
                
                    $flightDepartureDt= (new DateTimeImmutable($transportsFound[0]['startDate']))->setTime(0,0);
                    $flightReturnDt = (new DateTimeImmutable($transportsFound[0]['endDate']))->setTime(0,0);
                    $departureCity = $cities->get($filter->departureCity);
                    $arrivalCity = $cities->get($filter->cityId);

                    $offer = Offer::createCharterOrTourOffer(
                        $responseHotel['id'],
                        $responseOffer['id'],
                        $responseOffer['id'],
                        $responseOffer['name'],
                        null,
                        null,
                        $checkInDateTime,
                        $checkOutDateTime,
                        $filter->rooms->first()->adults,
                        $post['args'][0]['rooms'][0]['childrenAges']->toArray(),
                        $currency,
                        $net,
                        $initial,
                        $gross,
                        $commision,
                        $availability,
                        null,
                        $flightDepartureDt,
                        $flightDepartureDt,
                        $flightReturnDt,
                        $flightReturnDt,
                        '',
                        '',
                        '',
                        '',
                        $filter->transportTypes->first(),
                        $departureCity,
                        $arrivalCity,
                        $arrivalCity,
                        $departureCity,
                        null
                    );

                    $offer->CheckIn = $filter->checkIn;
                    $offer->offerId = $index;
                    $offer->InitialData = $phpsessid;

                    $currencyObj = new Currency();
                    $currencyObj->Code = $currency;

                    $cancelFees = new OfferCancelFeeCollection();
                    foreach ($responseOffer['cancellationCharges'] as $charge) {
                        $cancelFee = new OfferCancelFee();
                        $cancelFee->Currency = $currencyObj;
                        $cancelFee->DateStart = (new DateTime())->format('Y-m-d');
                        $cancelFee->DateEnd = (new DateTime($charge['applicableBefore']))->format('Y-m-d');
                        $cancelFee->Price = $charge['charge'];
                    }
                    $offer->CancelFees = $cancelFees;

                    $offers = new OfferCollection();
                    $offers->put($offer->Code, $offer);
                }

                $tour = new Availability();

                $existingAvailability = $availabilityCollection->get($responseOffer['id']);
                if ($existingAvailability === null) {
                    $tour->Offers = $offers;
                    $tour->Id = $responseOffer['id'];
                } else {
                    // adding offers to the existing availability
                    $tour = $existingAvailability;
                    $tour->Offers->put($offer->Code, $offer);
                }

                $availabilityCollection->put($tour->Id, $tour);
            }
        }
        
        return $availabilityCollection;
    }

    /*
    private function getTourOffers(AvailabilityFilter $filter): array
    {
        EtripValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateTourOffersFilter($filter);

        $availabilityCollection = [];

        $url = $this->apiUrl . '/v2/search/packages';
        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $options['headers'] = [
            'Authorization' => $basic
        ];
        if (!empty($this->apiContext)) {
            $options['headers']['X-AgentCode'] = $this->apiContext;
        }

        $destination = '';
        if (!empty($filter->cityId)) {
            $destination = (int) $filter->cityId;
        } else {
            $destination = (int) $filter->regionId;
        }

        $ages = [];
        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $ages[] = (int) $age;
            }
        }

        $isFlight = true;

        if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
            $isFlight = false;
        }

        $body = [
            'currency' => 'EUR',
            'isTour' => true,
            'isFlight' => $isFlight,
            'isBus' => !$isFlight,
            'departure' => $filter->departureCity ? (int) $filter->departureCity : (int) $filter->departureCityId,
            'destination' => $destination,
            'departureDate' => $filter->checkIn,
            'duration' => $filter->days,
            'rooms' => [
                [
                    'adults' => $filter->rooms->first()->adults,
                    'childAges' => $ages
                ]
            ],
            'showBlackedOut' => true
        ];

        if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_BUS) {
            $body['isFlight'] = false;
            $body['isBus'] = true;
        }

        $checkInDateTime = new DateTimeImmutable($filter->checkIn);
        $checkOutDateTime = $checkInDateTime->modify('+' . $filter->days . ' days');

        if (!empty($filter->hotelId)) {
            $body['hotelIds'] = [(int) $filter->hotelId];
        }

        $options['body'] = json_encode($body);
        
        $respObj = $client->request(HttpClient::METHOD_POST, $url, $options);
        $respJson = $respObj->getBody();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $respJson, $respObj->getStatusCode());

        $headers = $respObj->getHeaders();
        $cookieheader = $headers['set-cookie'][0];
        $start = strpos($cookieheader, 'PHPSESSID=') + strlen('PHPSESSID=');
        $length = strpos($cookieheader, ';') - strlen('PHPSESSID=');
        $phpsessid = substr($cookieheader, $start, $length);
        $responseData = json_decode($respJson, true);

        $aiportMap = AirportMap::getAirportMap();
        
        foreach ($responseData ?? [] as $index => $responseOffer) {
            $availability = new Availability();
            $availability->Id = $responseOffer['packageId'];

            $offer = new Offer();
            $offer->CheckIn = $filter->checkIn;
            $offer->offerId = $index;
            $offer->InitialData = $phpsessid;

            if (!$responseOffer['isAvailable'] && $responseOffer['isBookable']) {
                $offer->Availability = Offer::AVAILABILITY_ASK;
            } elseif ($responseOffer['isAvailable']) {
                $offer->Availability = Offer::AVAILABILITY_YES;
            } else {
                $offer->Availability = Offer::AVAILABILITY_NO;
            }
            
            $currency = new Currency();
            $currency->Code = 'EUR';

            $cancelFees = new OfferCancelFeeCollection();
            foreach ($responseOffer['cancellationCharges'] as $charge) {
                $cancelFee = new OfferCancelFee();
                $cancelFee->Currency = $currency;
                $cancelFee->DateStart = (new DateTime())->format('Y-m-d');
                $cancelFee->DateEnd = (new DateTime($charge['applicableBefore']))->format('Y-m-d');
                $cancelFee->Price = $charge['charge'];
            }
            $offer->CancelFees = $cancelFees;

            $offer->Currency = $currency;
            //$offer->Days = $filter->days;

            $offer->Comission = $responseOffer['priceInfo']['commission'];
            $offer->Gross = $responseOffer['priceInfo']['gross'];
            $offer->Net = $offer->Gross;
            $offer->InitialPrice = $offer->Net + $responseOffer['totalDiscount'];

            $room = new Room();
            $room->Id = preg_replace('/\s+/', '', $responseOffer['hotel']['rooms']);
            $room->Availability = $offer->Availability;
            $room->CheckinAfter = $filter->checkIn;
            $room->CheckinBefore = $checkOutDateTime->format('Y-m-d');
            $room->Currency = $currency;

            $merch = new RoomMerch();
            $merch->Name = $responseOffer['hotel']['rooms'];
            $merch->Id = $room->Id;
            $merch->Code = $merch->Id;
            $merch->Title = $merch->Name;

            $roomMerchType = new RoomMerchType();
            $roomMerchType->Id = $room->Id;
            $roomMerchType->Title = $merch->Name;
            $merch->Type = $roomMerchType;
            $room->Merch = $merch;

            $offer->Item = $room;
            $offer->Rooms = new RoomCollection([$room]);

            $meal = new MealItem();
            $meal->Currency = $currency;

            $mealMerch = new MealMerch();
            $mealMerch->Title = $responseOffer['hotel']['meals'];
            $mealMerch->Id = preg_replace('/\s+/', '', $mealMerch->Title);
            $meal->Merch = $mealMerch;

            $offer->MealItem = $meal;
            $offer->Code = $availability->Id . '~' . $room->Id . '~' . $mealMerch->Id . '~' 
                . $room->CheckinAfter . '~' . $room->CheckinBefore . '~' . $offer->Net . '~' 
                . $filter->rooms->first()->adults 
                . ($post['args'][0]['rooms'][0]['childrenAges'] ? '~' . implode('|', $post['args'][0]['rooms'][0]['childrenAges']->toArray()) : '')
            ;

            if (empty($responseOffer['flight']['journeys'][0])) {
                continue;
            }
            
            $departureTransportDate = 
                $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']['0']['departure'];
            $departureArrivalTransportDate = 
                $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']
                [array_key_last($responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['arrival'];
            
            if (empty($responseOffer['flight']['journeys'][1])) {
                continue;
            }
            
            $returnTransportDate = 
                $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']['0']['departure'];
            $returnArrivalTransportDate = 
                $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][0])]['legs']
                [array_key_last($responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['arrival'];

            $flightDepartureDateTime = new DateTimeImmutable($departureTransportDate);
            $flightArrivalDateTime = new DateTimeImmutable($departureArrivalTransportDate);
            $flightReturnDateTime = new DateTimeImmutable($returnTransportDate);
            $flightReturnArrivalDateTime = new DateTimeImmutable($returnArrivalTransportDate);

            // departure transport item merch
            $departureTransportMerch = new TransportMerch();
            $departureTransportMerch->Title = "Dus: ". $flightDepartureDateTime->format('d.m.Y');
            $departureTransportMerch->Category = new TransportMerchCategory();
            $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
            $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
            $departureTransportMerch->DepartureTime = $flightDepartureDateTime->format('Y-m-d H:i');
            $departureTransportMerch->ArrivalTime = $flightArrivalDateTime->format('Y-m-d H:i');
            
            $departureTransportMerch->DepartureAirport = $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']['0']['from'];
            $departureTransportMerch->ReturnAirport = $responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs']
                [array_key_last($responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['to'];

            $departureTransportMerch->From = new TransportMerchLocation();
            $cityDep = new City();
            $cityDep->Id = $departureTransportMerch->DepartureAirport;
            if (isset($aiportMap[$departureTransportMerch->DepartureAirport])) {
                $cityDep->Name = $aiportMap[$departureTransportMerch->DepartureAirport]['cityName'];
            }

            $departureTransportMerch->From->City = $cityDep;

            $cityArr = new City();
            $cityArr->Id = $departureTransportMerch->ReturnAirport;
            if (isset($aiportMap[$departureTransportMerch->ReturnAirport])) {
                $cityArr->Name = $aiportMap[$departureTransportMerch->ReturnAirport]['cityName'];
            }
            $departureTransportMerch->To = new TransportMerchLocation();
            $departureTransportMerch->To->City = $cityArr;

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
            $returnTransportMerch->ArrivalTime = $flightReturnArrivalDateTime->format('Y-m-d H:i');

            $returnTransportMerch->DepartureAirport = $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][1])]['legs']['0']['from'];
            $returnTransportMerch->ReturnAirport = $responseOffer['flight']['journeys'][1][array_key_last($responseOffer['flight']['journeys'][0])]['legs']
                [array_key_last($responseOffer['flight']['journeys'][0][array_key_last($responseOffer['flight']['journeys'][0])]['legs'])]['to'];

            $cityArr = new City();
            $cityArr->Id = $departureTransportMerch->ReturnAirport;
            if (isset($aiportMap[$returnTransportMerch->DepartureAirport])) {
                $cityArr->Name = $aiportMap[$returnTransportMerch->DepartureAirport]['cityName'];
            }
            $returnTransportMerch->From = new TransportMerchLocation();
            $returnTransportMerch->From->City = $cityArr;

            $cityDep = new City();
            $cityDep->Id = $departureTransportMerch->DepartureAirport;
            if (isset($aiportMap[$returnTransportMerch->ReturnAirport])) {
                $cityDep->Name = $aiportMap[$returnTransportMerch->ReturnAirport]['cityName'];
            }
            $returnTransportMerch->To = new TransportMerchLocation();
            $returnTransportMerch->To->City = $cityDep;

            $returnTransportItem = new ReturnTransportItem();
            $returnTransportItem->Merch = $returnTransportMerch;
            $returnTransportItem->Currency = $offer->Currency;
            $returnTransportItem->DepartureDate = $flightReturnDateTime->format('Y-m-d');
            $returnTransportItem->ArrivalDate = $flightReturnArrivalDateTime->format('Y-m-d');

            $departureTransportItem->Return = $returnTransportItem;

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

            $offers = new OfferCollection([$offer]);
            $availability->Offers = $offers;

            $availabilityCollection->add($availability);
        }
        
        return $availabilityCollection;
    }*/

    public function apiGetOffers(AvailabilityFilter $filter): array
    {
        $availabilityCollection = null;
        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            $availabilityCollection = $this->getHotelOffers($filter);
        } elseif ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            $availabilityCollection = $this->getPackageOrTourOffersFromPackages($filter);
        } else {
            if (in_array($this->handle, self::$handlesWithBothModes)) {
                // if contains -t is a tour city
                if (str_contains($filter->cityId, '-t')) {
                    $availabilityCollection = $this->getTourOffersFromTours($filter);
                } else {
                    $availabilityCollection = $this->getJoinedOffers($filter);
                }
                
            } elseif (in_array($this->handle, self::$handlesWithTourMode)) {
                $availabilityCollection = $this->getTourOffersFromTours($filter);
            } else {
                $availabilityCollection = $this->getPackageOrTourOffersFromPackages($filter);
            }
        }

        return $availabilityCollection;
    }

    public function apiGetTours(): TourCollection
    {
        if (in_array($this->handle, self::$handlesWithBothModes)) {
            return $this->getToursFromPackages()->merge($this->getToursFromTours());
        } elseif (in_array($this->handle, self::$handlesWithTourMode)) {
            return $this->getToursFromTours();
        } else {
            return $this->getToursFromPackages();
        }
    }

    public function getToursFromTours(): TourCollection
    {
        $tours = new TourCollection();

        $url = $this->apiUrl . '/v2/static/tours';
        $cities = $this->apiGetCities();
        //$hotels = $this->apiGetHotels();
        //$tourHotels = $this->getTourHotels();

        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $options['headers'] = [
            'Authorization' => $basic
        ];
        
        $respObj = $client->request(HttpClient::METHOD_GET, $url, $options);
        $respJson = $respObj->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $respJson, $respObj->getStatusCode());
        
        $packages = json_decode($respJson, true);

        foreach ($packages as $package) {
            $destination = $package['destinations'][0];

            $selectedCountries = [];
            //foreach ($package['destinations'] as $destination) {

                $destinationCity = $cities->get($destination['id'] . '-t');

                if ($destinationCity === null) {
                    throw new Exception('City not found!');
                }

                if (isset($selectedCountries[$destinationCity->Country->Id])) {
                    continue;
                }

                $selectedCountries[$destinationCity->Country->Id] = $destinationCity->Country->Id;

                $destinations = [];

                foreach ($package['destinations'] as $dest) {
                    $dc = $cities->get($dest['id'] . '-t');
                    $destinations->add($dc);
                }

                $description = '';

                $description .= '<p><b>Servicii incluse</b></p>'
                    . $package['includedServices']
                    . '<br><br><p><b>Servicii excluse</b></p>'
                    . $package['excludedServices'];

                foreach ($package['detailedDescriptions'] as $detailedDescription) {
                    $description .= '<b>'.$detailedDescription['label'].'</b><br>';
                    $description .= $detailedDescription['text'];
                }

                $description = nl2br($description);

                $items = new TourImageGalleryItemCollection();

                foreach ($package['images'] as $itemHotel) {
                    $item = new TourImageGalleryItem();
                    $item->RemoteUrl = $itemHotel;
                    $items->add($item);
                }

                $transports = new StringCollection();

                if (isset($package['transport'][1])) {
                    if (($package['transport'][1] !== 'flight') && ($package['transport'][1] !== 'bus')) {
                        continue;
                    }
                }

                $transportType = '';
                if ($package['transport'][0] === 'flight') {
                    $transportType = Tour::TRANSPORT_TYPE_PLANE;
                } elseif ($package['transport'][0] === 'bus') {
                    $transportType = Tour::TRANSPORT_TYPE_BUS;
                } else {
                    continue;
                }
                $transports->add($transportType);

                $tour = Tour::create($package['id'], $package['name'], $destinations, $description, $items, 
                    null, $package['duration'], $transports, $destinationCity, null
                );

                $tours->add($tour);
            //}
        }

        return $tours;
    }

    public function getToursFromPackages(): TourCollection
    {
        $tours = new TourCollection();

        $url = $this->apiUrl . '/v2/static/packages';
        $cities = $this->apiGetCities();
        $hotels = $this->apiGetHotels();
        $tourHotels = $this->getTourHotels();

        $client = HttpClient::create();

        $pass = base64_encode($this->username . ':' . $this->password);
        $basic = 'Basic ' . $pass;
        $options['headers'] = [
            'Authorization' => $basic
        ];
        
        $respObj = $client->request(HttpClient::METHOD_GET, $url, $options);
        $respJson = $respObj->getBody();
        $this->showRequest(HttpClient::METHOD_GET, $url, $options, $respJson, $respObj->getStatusCode());
        
        $packages = json_decode($respJson, true);

        foreach ($packages as $package) {
            if ($package['packageType'] !== 'tour') {
                continue;
            }

            $destinationCity = $cities->get($package['destination']);

            if ($package['hotelId'] === null) {
                continue;
            }
            $hotel = $hotels->get($package['hotelId']);

            if ($hotel === null) {
                $hotel = $tourHotels->get($package['hotelId']);
            }

            if ($destinationCity === null) {
                continue;
            }

            $destinations = [];
            $destinations->add($destinationCity);

            $description = '';
            if ($hotel !== null) {
                $description .= $hotel->Content->Content;
            }

            $description .= '<p><b>Servicii incluse</b></p>'
                . $package['includedServices']
                . '<br><br><p><b>Servicii excluse</b></p>'
                . $package['excludedServices'];

            $description = nl2br($description);

            $items = new TourImageGalleryItemCollection();

            if ($hotel !== null) {
                foreach ($hotel->Content->ImageGallery->Items as $itemHotel) {
                    $item = new TourImageGalleryItem();
                    $item->Alt = $itemHotel->Alt;
                    $item->RemoteUrl = $itemHotel->RemoteUrl;
                    $items->add($item);
                }
            }

            $transports = new StringCollection();

            $package['transportType'] === 'flight' ? $transports->add(Tour::TRANSPORT_TYPE_PLANE) : $transports->add(Tour::TRANSPORT_TYPE_BUS);
   
            $tour = Tour::create($package['id'], $package['name'], $destinations, $description, $items, 
                null, $package['duration'], $transports, $destinationCity, null
            );

            $tours->add($tour);
        }

        return $tours;
    }
}
