<?php

namespace Integrations\Travelio;

use App\Entities\Availability\AirportTaxesCategory;
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
use App\Entities\Availability\TransferCategory;
use App\Entities\Availability\TransportMerch;
use App\Entities\Availability\TransportMerchCategory;
use App\Entities\Availability\TransportMerchLocation;
use App\Entities\AvailabilityDates\AvailabilityDates;
use App\Entities\AvailabilityDates\AvailabilityDatesCollection;
use App\Entities\AvailabilityDates\DateNight;
use App\Entities\AvailabilityDates\DateNightCollection;
use App\Entities\AvailabilityDates\TransportCity;
use App\Entities\AvailabilityDates\TransportDate;
use App\Entities\AvailabilityDates\TransportDateCollection;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Region;
use App\Entities\Tours\Location;
use App\Entities\Tours\Tour;
use App\Entities\Tours\TourCollection;
use App\Entities\Tours\TourContent;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\PaymentPlansFilter;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\RegionCollection;
use App\Support\Collections\StringCollection;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Http\SimpleAsync\HttpClient2;
use App\Support\Log;
use DateTime;
use DateTimeImmutable;
use DOMDocument;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use RuntimeException;
use SimpleXMLElement;
use Utils\Utils;

class TravelioApiService extends AbstractApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function apiTestConnection(): bool
    {
        TravelioValidator::make()
            ->validateUsernameAndPassword($this->post);


        $httpClient = HttpClient::create();


        $requestArr = [
            'root' => [
                'head' => [
                    'auth' => [
                        'username' => $this->username,
                        'password' => $this->password
                    ],
                    'service' => 'StartAsyncSearch'
                ],
                'main' => [
                    'SearchType' => 'packages',
                    'DepartureCityId' => 123,
                    'CityId' => 123,
                    'TransportType' => AvailabilityFilter::TRANSPORT_TYPE_PLANE,
                    'CheckIn' => (new DateTime())->modify('+1 day')->format('Y-m-d'),
                    'CheckOut' => (new DateTime())->modify('+1 day')->format('Y-m-d'),
                    'Rooms' => [
                        'Room' => [
                            'Adults' => 2,
                        ]
                    ]
                ]
            ]
        ];

        $requestJson = json_encode($requestArr);

        $options['body'] = $requestJson;
        $options['headers'] = ['Content-Type' => 'application/json'];

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $content = $responseObj->getContent(false);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());

        $search = json_decode($content, true);
        if (empty($search['searchId'])) {
            return false;
        }
        return true;
    }

    /*
    private function getAvailabilityDates(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {

        $file = 'availability-'.$filter->type;
        $json = Utils::getFromCache($this->handle, $file);

        if ($json === null) {

            $cities = $this->apiGetCities();

            $availabilityDatesCollection = new AvailabilityDatesCollection();

            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'GetDepartures',
                    ],
                    'main' => [
                        'GetDepartureAvailableTypes' => 1
                    ]
                ]
            ];

            $requestXml = Utils::arrayToXmlString($requestArr);

            $options['body'] = $requestXml;

            $httpClient = HttpClient::create();

            $i = 0;
            $j = 0;
            $getFromOldCache = false;
            while(true) {
                if ($i > 10 || $j > 20) {
                    // get the old cache
                    $getFromOldCache = true;
                    if ($j > 20) {
                        Log::warning($this->handle . ': IsCircuit is missing after '.$j.' tries with 30s breaks, getting from old cache');
                    }
                    break;
                }
                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());

                $respStr = $responseObj->getContent();
                if ($respStr === 'stream timeout') {
                    $i++;
                    continue;
                }
                $resp = simplexml_load_string($respStr);
                
                if (!isset($resp->GetDepartures->Departure->Departures->DepartureDate->IsCircuit)) {
                    Log::warning('hahahhahaha');
                    $j++;
                    sleep(30);
                    continue;
                }

                break;
            }

            if ($getFromOldCache) {
                $json = Utils::getFromCache($this->handle, $file, true);
                $availArr = json_decode($json, true);
                $availabilityDatesCollection = ResponseConverter::convertToAvailabilityDatesCollection($availArr);
            } else {

                $departures = $resp->GetDepartures->Departure;
//$aa = [];
                foreach ($departures as $departure) {
                    foreach ($departure->Departures->DepartureDate as $departureDate) {

                        $superDeal = (int) $departureDate->SuperDealDeparture;
                        if ($superDeal === 0) {
                            $availableSeats = (string) $departureDate->AvailableSeats;
                            if ($availableSeats === 'No') {
                                continue;
                            }
                            $availableSeats = (int) $departureDate->SeatsRemaining;
                            $seats = (int) $departureDate->SeatsRemaining;
                            if ($seats < 2) {
                                continue;
                            }
                        }

                        $isCircuit = (string) $departureDate->IsCircuit;

                        if ($isCircuit === 'true' && $filter->type !== AvailabilityFilter::SERVICE_TYPE_TOUR) {
                            continue;
                        }
                        if ($isCircuit === 'false' && $filter->type !== AvailabilityFilter::SERVICE_TYPE_CHARTER) {
                            continue;
                        }
                        

                        $departureCityId = (string) $departureDate->DepartureCityId;
                        $arrivalCityId = (string) $departureDate->ArrivalCityId;

                        if (empty($arrivalCityId)) {
                            continue;
                        }
                        //$arrivalAirport = (string)$departureDate->ArrivalAirport;

                        // if ($arrivalAirport == 'BRU') {
                        //     dump($aa);
                        // }
                        // if (isset($aa[$arrivalAirport])) {
                        //     continue;
                        // }

                        //$aa[$arrivalAirport] = $arrivalAirport;

                        $toFromApi = $cities->get($arrivalCityId);
                        if ($toFromApi === null) {
                        
                            // $domxml = new DOMDocument();
                            // $domxml->preserveWhiteSpace = false;
                            // $domxml->formatOutput = true;
                            // $domxml->loadXML($departureDate->asXML());
                            // $fx = $domxml->saveXML();
                            // dump($arrivalAirport);
                            // file_put_contents(__DIR__ . '/karp.txt', $fx, FILE_APPEND);
                        
                            continue;
                        }
                        //continue;

                        $toCities = [];
                        if ($toFromApi->County === null) {
                            $toCities = [$toFromApi];
                        } else {
                            $toCities = $cities->filter(fn(City $city) => $city->County !== null ? ($city->County->Id === $toFromApi->County->Id) : false);
                        }
                        
                        foreach ($toCities as $to) {

                            $transportType = '';

                            if ((string)$departureDate->TransportType === 'Avion') {
                                $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;
                            } elseif ((string)$departureDate->TransportType === 'Autocar') {
                                $transportType = AvailabilityDates::TRANSPORT_TYPE_BUS;
                            } else {
                                throw new Exception('what transport');
                            }

                            $id = $transportType . "~city|" . $departureCityId . "~city|" . $to->Id;

                            $availabilityDates = $availabilityDatesCollection->get($id);

                            if ($availabilityDates === null) {
                                $availabilityDates = new AvailabilityDates();
                                $availabilityDates->Id = $id;

                                $transport = new TransportCity();

                                $from = $cities->get($departureCityId);

                                if ($from === null) {
                                    continue;
                                }

                                $transport->City = $from;
                                $availabilityDates->From = $transport;

                                $transport = new TransportCity();
                                
                                $transport->City = $to;
                                $availabilityDates->To = $transport;
                
                                $availabilityDates->TransportType = $transportType;

                                $nights = new DateNightCollection();

                                $nightsArr = explode(',', (string)$departureDate->Nights);
                                foreach ($nightsArr as $nightStr) {
                                    $night = new DateNight();
                                    $night->Nights = (int) trim($nightStr);
                                    $nights->put($night->Nights, $night);
                                }

                                $date = new TransportDate();
                                $date->Date = (string) $departureDate->Date;

                                $date->Nights = $nights;

                                $dates = new TransportDateCollection();
                                $dates->put($date->Date, $date);
                                $availabilityDates->Dates = $dates;
                                $availabilityDatesCollection->put($id, $availabilityDates);
                            } else {
                                // if ($id == 'plane~city|2233~city|7525' && (string)$departureDate->Nights !== '7, 8, 9, 10' && (string) $departureDate->Date == '2025-02-02') {
                                //     dump((string)$departureDate->Nights);
                                //     dump($availabilityDates->Dates);
                                //     die;
                                // }
                                // check if date exist
                                // if yes, add nights
                                // if no, create date and add nights
                                $existingDate = $availabilityDates->Dates->get((string) $departureDate->Date);
                                if ($existingDate !== null) {
                                    // add night to date
                                    $enights = $existingDate->Nights;

                                    $nightsArr = explode(',', (string)$departureDate->Nights);
                                    foreach ($nightsArr as $nightStr) {
                                        $night = new DateNight();
                                        $night->Nights = (int) trim($nightStr);
                                        $enights->put($night->Nights, $night);
                                    }
                                    $existingDate->Nights = $enights;
                                    $availabilityDates->Dates->put($existingDate->Date, $existingDate);
                                    $availabilityDatesCollection->put($id, $availabilityDates);
                                } else {
                                    // add date 
                                    $date = new TransportDate();
                                    $date->Date = (string) $departureDate->Date;

                                    $nights = new DateNightCollection();

                                    $nightsArr = explode(',', (string)$departureDate->Nights);
                                    foreach ($nightsArr as $nightStr) {
                                        $night = new DateNight();
                                        $night->Nights = (int) trim($nightStr);
                                        $nights->put($night->Nights, $night);
                                    }
                                    $date->Nights = $nights;

                                    $availabilityDates->Dates->put($date->Date, $date);
                                    $availabilityDatesCollection->put($id, $availabilityDates);
                                }
                            }
                        }

                    }
                }
                //Utils::createCsv(__DIR__.'/karp.csv', ['From city', 'Destination city', 'Destination region', 'Destination country', 'Superdeal'], $data);
                //die;
                $data = json_encode($availabilityDatesCollection);
                Utils::writeToCache($this->handle, $file, $data);
            }
        } else {
            $availArr = json_decode($json, true);
            $availabilityDatesCollection = ResponseConverter::convertToAvailabilityDatesCollection($availArr);
        }

        if (!empty($filter->countryId)) {
            $availabilityDatesCollectionFiltered = $availabilityDatesCollection->filter(fn(AvailabilityDates $ad) => $ad->To->City->Country->Id == $filter->countryId);
            $availabilityDatesCollection = $availabilityDatesCollectionFiltered;
        }

        return $availabilityDatesCollection;
    }
    */

    private function apiGetAvailabilityDatesRaport(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {
        $departures =  $this->apiGetAvailabilityDatesFromDepartures($filter);
        $packages = $this->apiGetAvailabilityDatesFromPackages($filter);

        $cityZonesDep = [];
        /** @var AvailabilityDates $a */
        foreach ($departures as $a) {
            if (empty($a->To->City->County->Id)) {
                continue;
            }
            $cityZonesDep[$a->From->City->Id.'-'.$a->To->City->County->Id] = $a;
        }

        $cityZonesPack = [];
        /** @var AvailabilityDates $a */
        foreach ($packages as $a) {
            if (empty($a->To->City->County->Id)) {
                continue;
            }
            $cityZonesPack[$a->From->City->Id.'-'.$a->To->City->County->Id] = $a;
        }
        uasort($cityZonesDep, fn($a, $b) => strcmp($a->From->City->Name . ' - ' . $a->To->City->County->Name, $b->From->City->Name . ' - ' . $b->To->City->County->Name));
        uasort($cityZonesPack, fn($a, $b) => strcmp($a->From->City->Name . ' - ' . $a->To->City->County->Name, $b->From->City->Name . ' - ' . $b->To->City->County->Name));
        // dump('from packages');
        // dump('transporturi cu orase de dest fara regiuni:');
        // dump($c);
        // dump('orase de dest fara regiuni');
        // dump($d);
        // dump('transporturi');
        // dump($cityZones);
        // dump('zone unice');
        // ksort($czu);
        // dump($czu);

        // foreach ($czu as $cz) {
        //     echo $cz->County->Id . '-';
        // }


            // doar cu regiuni
    // care sunt la fel cu verde
    // care sunt in departures si nu sunt in packages cu rosu
    // care sunt in packages si nu sunt departures cu negru
    // nume de oras de plecare - nume de regiune


        echo '<div style="margin-left: 30px; margin-top: 30px; display:flex">';
       
        $i = 0;
        echo '<div style="margin-right: 30px">';
        echo '<p>comune:</p>';

        foreach ($cityZonesDep as $kd => $czDep) {
            
            if (array_key_exists($kd, $cityZonesPack)) {
                $i++;
                echo '<span style="color:green">'.$i.'. '.$czDep->From->City->Name . ' - ' . $czDep->To->City->County->Name.'</span><br>';
            }
        }
        echo '</div>';

        echo '<div style="margin-right: 30px">';
        echo '<p>care sunt in departures si nu sunt in packages:</p>';
        $i = 0;
        foreach ($cityZonesDep as $kd => $czDep) {
            
            if (!array_key_exists($kd, $cityZonesPack)) {
                $i++;
                echo '<span style="color:red">'.$i.'. '.$czDep->From->City->Name . ' - ' . $czDep->To->City->County->Name.'</span><br>';
            }
        }
        echo '</div>';

        echo '<div style="margin-right: 30px">';
        echo '<p>care sunt in packages si nu sunt in departures:</p>';
        $i = 0;
        foreach ($cityZonesPack as $kp => $czPac) {
            
            if (!array_key_exists($kp, $cityZonesDep)) {
                $i++;
                echo '<span style="color:black">'.$i.'. '.$czPac->From->City->Name . ' - ' . $czPac->To->City->County->Name.'</span><br>';
            }
        }
        echo '</div>';


        echo '</div>';


        // alt raport, sa corespunda tot
        // verific ce e cu verde: data, nr de nopti
        
        echo '<div style="margin-left: 30px; margin-top: 30px;">';
        echo '<p><b>Verificare data si nr nopti</b></p>';
        
        $t = 0;
        foreach ($cityZonesDep as $kd => $czDep) {
            
            if (array_key_exists($kd, $cityZonesPack)) {
                $t++;
                echo '<p style="margin-top: 30px; font-size: 16px"><b>'.$t.'. Transport: '.$czDep->From->City->Name . ' - ' . $czDep->To->City->County->Name.'</b></p>';

                $czPack = $cityZonesPack[$kd];

                echo '<div>';

                $czDepDates = $czDep->Dates;
                $czDepDates->ksort();
                $czPackDates = $czPack->Dates;
                $czPackDates->ksort();
                
                $a1 = 0;
                foreach ($czDepDates as $depDate => $czDepDate) {
                    
                    if ($czPackDates->get($depDate) === null) {
                        $a1++;
                    }
                }
                echo '<div><b>care sunt in departures si nu sunt in packages: ';
                if ($a1 > 0) {
                    echo 'dif';
                } else {
                    echo 'ok';
                }
                echo '</b></div>';

                foreach ($czDepDates as $depDate => $czDepDate) {
                    
                    if ($czPackDates->get($depDate) === null) {
                        echo '<span style="color:red">'.$depDate.'</span><br>';
                    }
                }

                $a2 = 0;
                foreach ($czPackDates as $packDate => $czPackDate) {
                    
                    if ($czDepDates->get($packDate) === null) {
                        $a2++;
                    }
                }

                echo '<div style="margin-top: 20px"><b>care sunt in packages dar nu in departures: ';
                if ($a2 > 0) {
                    echo 'dif';
                } else {
                    echo 'ok';
                }
                echo '</b></div>';

                foreach ($czPackDates as $packDate => $czPackDate) {
                    
                    if ($czDepDates->get($packDate) === null) {
                        echo '<span style="color:black">'.$packDate.'</span><br>';
                    }
                }

                $a3 = 0;
                foreach ($czDepDates as $depDate => $czDepDate) {
                    $czPackDate = $czPackDates->get($depDate);
                    if ($czPackDate !== null) {
                        // get nopti comune:
                        $packageNights = $czPackDate->Nights;
                        $departureNights = $czDepDate->Nights;

                        /** @var DateNight $dn */
                        foreach ($departureNights as $dn) {
                            if ($packageNights->get($dn->Nights) === null) {
                                $a3++;
                            }
                        }
                        /** @var DateNight $dn */
                        foreach ($packageNights as $dn) {
                            if ($departureNights->get($dn->Nights) === null) {
                                $a3++;
                            }
                        }
                    }
                }


                echo '<div style="margin-top: 20px; margin-bottom: 10px"><b>comune: ';
                if ($a3 > 0) {
                    echo 'dif';
                } else {
                    echo 'ok';
                }
                echo '</b></div>';

                echo '<div style="display:flex; flex-wrap: wrap">';
                foreach ($czDepDates as $depDate => $czDepDate) {
                    $czPackDate = $czPackDates->get($depDate);
                    if ($czPackDate !== null) {
                        echo '<div style="margin-right: 100px; margin-bottom: 30px">';
                        echo '<span style="color:green">'.$depDate.'</span><br>';
                        // get nopti comune:
                        $packageNights = $czPackDate->Nights;
                        $packageNights->ksort();
                        $departureNights = $czDepDate->Nights;
                        $packageNights->ksort();

                        echo '<span style="color:green">nopti c: ';
                        /** @var DateNight $dn */
                        foreach ($departureNights as $dn) {
                            if ($packageNights->get($dn->Nights) !== null) {
                                echo $dn->Nights . ' ';
                            }
                        }
                        echo '</span><br>';

                        echo '<span style="color:red">nopti d: ';
                        /** @var DateNight $dn */
                        foreach ($departureNights as $dn) {
                            if ($packageNights->get($dn->Nights) === null) {
                                echo $dn->Nights . ' ';
                            }
                        }
                        echo '</span><br>';

                        echo '<div style="color:black;">nopti p: ';
                        /** @var DateNight $dn */
                        foreach ($packageNights as $dn) {
                            if ($departureNights->get($dn->Nights) === null) {
                                echo $dn->Nights . ' ';
                            }
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                }
                echo '</div>';

                echo '</div>';


            }
        }
        echo '</div>';


        // dump($cityZonesDep);
        // dump($cityZonesPack);

        die;

        return $ad;
    }

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {
        $adCities = $this->apiGetAvailabilityDatesFromPackages($filter);

        $regionMapExists = Utils::cachedFileExists($this, 'region-map-transports');

        // group by region
        $ad = new AvailabilityDatesCollection();
        $regionMap = [];

        // compare transports
        /** @var AvailabilityDates $transport1 */
        foreach ($adCities as $transport1) {

            // just add transports with no region
            if (!empty($transport1->To->City->County->Id)) {

                if (!$regionMapExists) {
                    if ($transport1->To->City->County !== null) {
                        $regionMap[$transport1->To->City->County->Id][$transport1->To->City->Id] = $transport1->To->City->Id;
                    }
                }

                // if the regions has not be added, just add

                $found = false;
                /** @var AvailabilityDates $transport2 */
                foreach ($ad as $transport2) {
                    if (!empty($transport2->To->City->County->Id)) {

                        // find a transport with the same region and transport type
                        if (
                            $transport1->TransportType === $transport2->TransportType &&
                            $transport1->From->City->Id === $transport2->From->City->Id && 
                            $transport1->To->City->County->Id === $transport2->To->City->County->Id
                        ) {
                            $found = true;
                            $equals = $transport1->Dates->equals($transport2->Dates);
                            if (!$equals) {
                                $transport2->Dates = $transport1->Dates->combine($transport2->Dates);
                            }
                        }
                        $ad->put($transport2->Id, $transport2);
                    }
                }
                if (!$found) {
                    $ad->put($transport1->Id, $transport1);
                }
                
            } else {
                $ad->put($transport1->Id, $transport1);
            }

        }

        if (!$regionMapExists) {
            if ($filter->type === AvailabilityDatesFilter::CHARTER) {
                $filter->type = AvailabilityDatesFilter::TOUR;
                $adCities = $this->apiGetAvailabilityDatesFromPackages($filter);
            } else {
                $filter->type = AvailabilityDatesFilter::CHARTER;
                $adCities = $this->apiGetAvailabilityDatesFromPackages($filter);
            }

            // compare transports
            /** @var AvailabilityDates $transport1 */
            foreach ($adCities as $transport1) {
                if ($transport1->To->City->County !== null) {
                    $regionMap[$transport1->To->City->County->Id][$transport1->To->City->Id] = $transport1->To->City->Id;
                }   
            }

            Utils::writeToCache($this, 'region-map-transports', json_encode($regionMap));
        }

        return $ad;
    }

    private function apiGetAvailabilityDatesFromPackages(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {
        $file = 'availability-dates-'.$filter->type.'-packages';

        $json = Utils::getFromCache($this, $file);

        if ($json === null) {
           

            $cities = $this->apiGetCities();
            $countries = $this->apiGetCountries();

            $packagesCountry = [];

            $httpClientAsync = HttpClient::create();

            $packagesCountryJson = Utils::getFromCache($this, 'pac-'.$filter->type);

            if ($packagesCountryJson === null) {

                foreach ($countries as $country) {
                    $requestArr = [
                        'root' => [
                            'head' => [
                                'auth' => [
                                    'username' => $this->username,
                                    'password' => $this->password
                                ],
                                'service' => 'GetPackagesList',
                            ],
                            'main' => [
                                'CountryID' => $country->Id,
                                'PackageType' => $filter->type === AvailabilityFilter::SERVICE_TYPE_CHARTER ? 'Sejur' : 'Circuit'
                            ]
                        ]
                    ];

                    $requestXml = Utils::arrayToXmlString($requestArr);

                    $options['body'] = $requestXml;

                    $responseObj = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

                    $content = $responseObj->getContent(false);
                    $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());

                    $responseData = simplexml_load_string($content);
                    $packages = $responseData->GetPackagesList->PackagesList;
                    if (((string)$responseData->GetPackagesList->detalii_err) === 'No results found') {
                        continue;
                    } else {
                        if ($packages->Package === null) {
                            dump($content);
                        }
                    }

                    $packagesCountry[] = [$content, $options];
                }
                Utils::writeToCache($this, 'pac-'.$filter->type, json_encode($packagesCountry));
            } else {
                $packagesCountry = json_decode($packagesCountryJson, true); 
            }

            $availabilityDatesCollection = new AvailabilityDatesCollection();

            $ids = [];
            $idsCached = [];
            foreach($packagesCountry as $packagesArr) {
                
                $content = $packagesArr[0];
                
                $responseData = simplexml_load_string($content);
                $packages = $responseData->GetPackagesList->PackagesList;

                foreach ($packages->Package as $package) {
                    $packageId = (string) $package->ID;
                    $packageIsCached = Utils::cachedFileExists($this, 'package-'. $packageId);

                    if ($packageIsCached) {
                        $idsCached[] = $packageId;
                    } else {
                        $ids[] = $packageId;
                    }
                }
            }

            $i = 0;
            foreach ($idsCached as $idCached) {
                $i++;
                //Log::debug($i . ' packageC-'. $idCached);
                $content = Utils::getFromCache($this, 'package-'. $idCached);
                $responseData = simplexml_load_string($content);
                $packageDetails = $responseData->GetPackageDetails;

                if (!$this->processPackageDetails($cities, $packageDetails, $availabilityDatesCollection)) {
                    continue;
                }
            }

            $httpClientAsync = HttpClient2::create();
            $reqs = [];

            foreach ($ids as $id) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetPackageDetails',
                        ],
                        'main' => [
                            'PackageID' => $id,
                        ]
                    ]
                ];
    
                $requestXml = Utils::arrayToXmlString($requestArr);
    
                $options['body'] = $requestXml;

                $i++;
                //Log::debug($i . ' package-' . $id);
                
                $reqs[] = [$httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options), $options, 'package-'. $id];

                if ($i % 100 === 0) {
                    foreach ($reqs as $req) {
                        $content = $req[0]->getContent();
                        $responseData = simplexml_load_string($content);
                        
                        $packageDetails = $responseData->GetPackageDetails;

                        $retry = 0;
                        $failed = false;
                        while (empty($packageDetails)) {
                            Log::debug('failed');
                            if ($retry === 0) {
                                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $req[0]->getStatusCode());
                               // Log::debug(substr($content, 0, 300));
                            }
                            $retry++;
                            if ($retry > 3) {
                                $failed = true;
                                break;
                            }
                            sleep(10);
                            $client = HttpClient2::create();
                            $contentResp = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $req[1]);
                            $content = $contentResp->getContent();
                            //Log::debug(substr($content, 0, 300));
                            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $contentResp->getStatusCode());

                            $responseData = simplexml_load_string($content);
                            $packageDetails = $responseData->GetPackageDetails;
                        }
                        if ($failed) {
                            // get package details from cache
                            $content = Utils::getFromCache($this, $req[2], true);
                            if ($content !== null) {
                                $responseData = simplexml_load_string($content);
                                $packageDetails = $responseData->GetPackageDetails;
                            } else {
                                // if not in cache, return old av cache
                                $jsonOld = Utils::getFromCache($this, $file, true);
                                return ResponseConverter::convertToAvailabilityDatesCollection(json_decode($jsonOld, true));
                            } 
                        }
                        Utils::writeToCache($this, $req[2], $content);
                        
                        if (!$this->processPackageDetails($cities, $packageDetails, $availabilityDatesCollection)) {
                            continue;
                        }
                    }

                    $httpClientAsync = HttpClient2::create();
                    $reqs = [];
                }
                
                
            }
            
            Utils::writeToCache($this, $file, json_encode($availabilityDatesCollection));
        } else {
            $availabilityDatesCollection = ResponseConverter::convertToAvailabilityDatesCollection(json_decode($json, true));
        }
        
        return $availabilityDatesCollection;
    }

    private function processPackageDetails($cities, $packageDetails, &$availabilityDatesCollection): bool
    {
        $cityFromId = (string) $packageDetails->Outward->ResortDepartureID;

        $cityFrom = $cities->get($cityFromId);

        $resortId = (string) $packageDetails->ResortID;
        $cityTo = $cities->get($resortId);

        if ($cityTo === null) {
            return false;
        }

        $transport = null;

        if ((string) $packageDetails->Transportation === 'avion') {
            $transport = AvailabilityDates::TRANSPORT_TYPE_PLANE;
        } elseif ((string) $packageDetails->Transportation === 'autocar') {
            $transport = AvailabilityDates::TRANSPORT_TYPE_BUS;
        } else {
            return false;
        }

        $id = $transport . "~city|" . $cityFromId . "~city|" . $resortId;

        $departureDates = $packageDetails->DepartureDates->DepartureDate;
        if ($departureDates === null) {
            return false;
        }

        $availabilityDate = $availabilityDatesCollection->get($id);

        if($availabilityDate !== null) {
            $dates = $availabilityDate->Dates;
        } else {
            $dates = new TransportDateCollection();
        }
        
        foreach ($departureDates as $packageDepartureDate) {

            $departureDateStr = (string) $packageDepartureDate->DepartureDate;

            $date = $dates->get($departureDateStr);

            $nights = new DateNightCollection();
            $dateNight = DateNight::create((int) $packageDepartureDate->HotelNights);

            if ($date !== null) {
                // just add nights
                $date->Nights->put($dateNight->Nights, $dateNight);
            } else {
                $nights->put($dateNight->Nights, $dateNight);
                $date = TransportDate::create($departureDateStr, $nights);
            }

            $dates->put($date->Date, $date);
        }

        if($availabilityDate !== null) {
            $availabilityDate->Dates = $dates;
        } else {
            $availabilityDate = AvailabilityDates::create($id, $cityFrom, $cityTo, $transport, $dates);
        }
        
        $availabilityDatesCollection->put($availabilityDate->Id, $availabilityDate);
        return true;
    }

    private function apiGetAvailabilityDatesFromDepartures(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $cities = $this->apiGetCities();

        $availabilityDatesCollection = new AvailabilityDatesCollection();

        $file = 'availability-'.$filter->type;

        $respStr = Utils::getFromCache($this, $file);

        if ($respStr === null) {
            // make call
            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'GetDepartures',
                    ],
                    'main' => [
                        'GetDepartureAvailableTypes' => 1
                    ]
                ]
            ];

            $requestXml = Utils::arrayToXmlString($requestArr);

            $options['body'] = $requestXml;
            $httpClient = HttpClient::create();

            $i = 0;
            $j = 0;
            $k = 0;
            $getFromOldCache = false;
            while(true) {
                if ($i > 10 || $j > 20 || $k > 20) {
                    // get the old cache
                    $getFromOldCache = true;
                    if ($j > 20) {
                        Log::warning($this->handle . ': IsCircuit is missing after '.$j.' tries with 30s breaks, getting from old cache');
                    }
                    if ($k > 20) {
                        Log::warning($this->handle . ': IsSejur is missing after '.$k.' tries with 30s breaks, getting from old cache');
                    }
                    break;
                }
                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());

                $respStr = $responseObj->getContent();
                if ($respStr === 'stream timeout') {
                    $i++;
                    continue;
                }

                $resp = simplexml_load_string($respStr);

                if (!isset($resp->GetDepartures->Departure->Departures->DepartureDate->IsCircuit)) {
                    Log::warning('IsCircuit is missing');
                    $j++;
                    sleep(30);
                    continue;
                }

                if (!isset($resp->GetDepartures->Departure->Departures->DepartureDate->IsSejur)) {
                    Log::warning('IsSejur is missing');
                    $k++;
                    sleep(30);
                    continue;
                }

                break;
            }
            if (!$this->skipTopCache) {
                if ($getFromOldCache) {
                    $respStr = Utils::getFromCache($this, $file, true);
                } else {
                    Utils::writeToCache($this, $file, $respStr);
                }
            }
        }

        $resp = simplexml_load_string($respStr);
        
        $departures = $resp->GetDepartures->Departure;
//$aa = [];
        foreach ($departures as $departure) {
            foreach ($departure->Departures->DepartureDate as $departureDate) {

                $superDeal = (int) $departureDate->SuperDealDeparture;
                if ($superDeal === 0) {
                    $availableSeats = (string) $departureDate->AvailableSeats;
                    if ($availableSeats === 'No') {
                        continue;
                    }
                    $availableSeats = (int) $departureDate->SeatsRemaining;
                    $seats = (int) $departureDate->SeatsRemaining;
                    if ($seats < 2) {
                        continue;
                    }
                }

                $isCircuit = (string) $departureDate->IsCircuit;
                $isSejur = (string) $departureDate->IsSejur;

                if (!($isSejur === 'true' && $filter->type === AvailabilityFilter::SERVICE_TYPE_CHARTER || 
                    $isCircuit === 'true' && $filter->type === AvailabilityFilter::SERVICE_TYPE_TOUR)) {
                    continue;
                }

                $departureCityId = (string) $departureDate->DepartureCityId;
                $arrivalCityId = (string) $departureDate->ArrivalCityId;

                if (empty($arrivalCityId)) {
                    continue;
                }

                $toFromApi = $cities->get($arrivalCityId);
                if ($toFromApi === null) {
                    continue;
                }

                $toCities = [];
                //if ($toFromApi->County === null) {
                    $toCities = [$toFromApi];
                // } else {
                //     $toCities = $cities->filter(fn(City $city) => $city->County !== null ? ($city->County->Id === $toFromApi->County->Id) : false);
                // }
                
                foreach ($toCities as $to) {

                    $transportType = '';

                    if ((string)$departureDate->TransportType === 'Avion') {
                        $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;
                    } elseif ((string)$departureDate->TransportType === 'Autocar') {
                        $transportType = AvailabilityDates::TRANSPORT_TYPE_BUS;
                    } else {
                        throw new Exception('what transport');
                    }

                    $id = $transportType . "~city|" . $departureCityId . "~city|" . $to->Id;

                    $availabilityDates = $availabilityDatesCollection->get($id);

                    if ($availabilityDates === null) {
                        $availabilityDates = new AvailabilityDates();
                        $availabilityDates->Id = $id;

                        $transport = new TransportCity();

                        $from = $cities->get($departureCityId);

                        if ($from === null) {
                            continue;
                        }

                        $transport->City = $from;
                        $availabilityDates->From = $transport;

                        $transport = new TransportCity();
                        
                        $transport->City = $to;
                        $availabilityDates->To = $transport;
        
                        $availabilityDates->TransportType = $transportType;

                        $nights = new DateNightCollection();

                        $nightsArr = explode(',', (string)$departureDate->Nights);
                        foreach ($nightsArr as $nightStr) {
                            $night = new DateNight();
                            $night->Nights = (int) trim($nightStr);
                            $nights->put($night->Nights, $night);
                        }

                        $date = new TransportDate();
                        $date->Date = (string) $departureDate->Date;

                        $date->Nights = $nights;

                        $dates = new TransportDateCollection();
                        $dates->put($date->Date, $date);
                        $availabilityDates->Dates = $dates;
                        $availabilityDatesCollection->put($id, $availabilityDates);
                    } else {
                        // if ($id == 'plane~city|2233~city|7525' && (string)$departureDate->Nights !== '7, 8, 9, 10' && (string) $departureDate->Date == '2025-02-02') {
                        //     dump((string)$departureDate->Nights);
                        //     dump($availabilityDates->Dates);
                        //     die;
                        // }
                        // check if date exist
                        // if yes, add nights
                        // if no, create date and add nights
                        $existingDate = $availabilityDates->Dates->get((string) $departureDate->Date);
                        if ($existingDate !== null) {
                            // add night to date
                            $enights = $existingDate->Nights;

                            $nightsArr = explode(',', (string)$departureDate->Nights);
                            foreach ($nightsArr as $nightStr) {
                                $night = new DateNight();
                                $night->Nights = (int) trim($nightStr);
                                $enights->put($night->Nights, $night);
                            }
                            $existingDate->Nights = $enights;
                            $availabilityDates->Dates->put($existingDate->Date, $existingDate);
                            $availabilityDatesCollection->put($id, $availabilityDates);
                        } else {
                            // add date 
                            $date = new TransportDate();
                            $date->Date = (string) $departureDate->Date;

                            $nights = new DateNightCollection();

                            $nightsArr = explode(',', (string)$departureDate->Nights);
                            foreach ($nightsArr as $nightStr) {
                                $night = new DateNight();
                                $night->Nights = (int) trim($nightStr);
                                $nights->put($night->Nights, $night);
                            }
                            $date->Nights = $nights;

                            $availabilityDates->Dates->put($date->Date, $date);
                            $availabilityDatesCollection->put($id, $availabilityDates);
                        }
                    }
                }

            }
        }
        //Utils::createCsv(__DIR__.'/karp.csv', ['From city', 'Destination city', 'Destination region', 'Destination country', 'Superdeal'], $data);
        //die;
            
        

        // if (!empty($filter->countryId)) {
        //     $availabilityDatesCollectionFiltered = $availabilityDatesCollection->filter(fn(AvailabilityDates $ad) => $ad->To->City->Country->Id == $filter->countryId);
        //     $availabilityDatesCollection = $availabilityDatesCollectionFiltered;
        // }

        return $availabilityDatesCollection;

    }

    public function apiGetAvailabilityDatesTest(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {
        $cities = $this->apiGetCities();

        $availabilityDatesCollection = new AvailabilityDatesCollection();

        $file = 'availability-'.$filter->type;

        $respStr = Utils::getFromCache($this, $file);

        if ($respStr === null) {
            // make call
            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'GetDepartures',
                    ],
                    'main' => [
                        'GetDepartureAvailableTypes' => 1
                    ]
                ]
            ];

            $requestXml = Utils::arrayToXmlString($requestArr);

            $options['body'] = $requestXml;
            $httpClient = HttpClient::create();

            $i = 0;
            $j = 0;
            $k = 0;
            $getFromOldCache = false;
            while(true) {
                if ($i > 10 || $j > 20 || $k > 20) {
                    // get the old cache
                    $getFromOldCache = true;
                    if ($j > 20) {
                        Log::warning($this->handle . ': IsCircuit is missing after '.$j.' tries with 30s breaks, getting from old cache');
                    }
                    if ($k > 20) {
                        Log::warning($this->handle . ': IsSejur is missing after '.$k.' tries with 30s breaks, getting from old cache');
                    }
                    break;
                }
                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());

                $respStr = $responseObj->getContent();
                if ($respStr === 'stream timeout') {
                    $i++;
                    continue;
                }

                $resp = simplexml_load_string($respStr);

                if (!isset($resp->GetDepartures->Departure->Departures->DepartureDate->IsCircuit)) {
                    Log::warning('IsCircuit is missing');
                    $j++;
                    sleep(30);
                    continue;
                }

                if (!isset($resp->GetDepartures->Departure->Departures->DepartureDate->IsSejur)) {
                    Log::warning('IsSejur is missing');
                    $k++;
                    sleep(30);
                    continue;
                }

                break;
            }
            if (!$this->skipTopCache) {
                if ($getFromOldCache) {
                    $respStr = Utils::getFromCache($this, $file, true);
                } else {
                    Utils::writeToCache($this, $file, $respStr);
                }
            }
        }

        $resp = simplexml_load_string($respStr);
        
        $departures = $resp->GetDepartures->Departure;
$aa = [];
        foreach ($departures as $departure) {
            foreach ($departure->Departures->DepartureDate as $departureDate) {

                $superDeal = (int) $departureDate->SuperDealDeparture;
                if ($superDeal === 0) {
                    $availableSeats = (string) $departureDate->AvailableSeats;
                    if ($availableSeats === 'No') {
                        continue;
                    }
                    $availableSeats = (int) $departureDate->SeatsRemaining;
                    $seats = (int) $departureDate->SeatsRemaining;
                    if ($seats < 2) {
                        continue;
                    }
                }

                $isCircuit = (string) $departureDate->IsCircuit;
                $isSejur = (string) $departureDate->IsSejur;


                $departureCityId = (string) $departureDate->DepartureCityId;
                $arrivalCityId = (string) $departureDate->ArrivalCityId;

                if (empty($arrivalCityId)) {
                    //continue;
                }
                $arrivalAirport = (string)$departureDate->ArrivalAirport;

                // if ($arrivalAirport == 'BRU') {
                //     dump($aa);
                // }
                if (isset($aa[$arrivalAirport])) {
                    continue;
                }

                $aa[$arrivalAirport] = $arrivalAirport;

                $toFromApi = $cities->get($arrivalCityId);
                if ($toFromApi === null) {
                
                    $domxml = new DOMDocument();
                    $domxml->preserveWhiteSpace = false;
                    $domxml->formatOutput = true;
                    $domxml->loadXML($departureDate->asXML());
                    $fx = $domxml->saveXML();
                    file_put_contents(__DIR__ . '/karp.txt', $fx, FILE_APPEND);
                
                }
                continue;

            }
        }
        //Utils::createCsv(__DIR__.'/karp.csv', ['From city', 'Destination city', 'Destination region', 'Destination country', 'Superdeal'], $data);
        //die;
            
        

        // if (!empty($filter->countryId)) {
        //     $availabilityDatesCollectionFiltered = $availabilityDatesCollection->filter(fn(AvailabilityDates $ad) => $ad->To->City->Country->Id == $filter->countryId);
        //     $availabilityDatesCollection = $availabilityDatesCollectionFiltered;
        // }

        return $availabilityDatesCollection;

    }

    public function apiGetCountries(): CountryCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $file = 'countries';
        $countriesJson = Utils::getFromCache($this, $file);

        if ($countriesJson === null) {

            $map = CountryCodeMap::getCountryCodeMap();

            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'GetCountriesList'
                    ]
                ]
            ];
            $requestXml = Utils::arrayToXmlString($requestArr);

            $options['body'] = $requestXml;

            $httpClient = HttpClient::create();

            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());
            $responseData = simplexml_load_string($responseObj->getContent());
        

            $countriesResponse = $responseData->GetCountriesList->CountriesList->Country;

            $countries = new CountryCollection();
            foreach ($countriesResponse as $value) {
                $country = new Country();
                $country->Id = $value->ID;
                if (empty($value->CodIso2)) {
                    $country->Code = $map[(string) $value->Name] ?? '';
                } else {
                    $country->Code = strtoupper($value->CodIso2);
                }
                
                $country->Name = $value->Name;
                $countries->put($country->Id, $country);
            }

            Utils::writeToCache($this, $file, json_encode($countries));

        } else {
            $countries = ResponseConverter::convertToCollection(json_decode($countriesJson, true), CountryCollection::class);
        }
        return $countries;
    }

    public function getToken(): string
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $optionsLogin['body'] = json_encode([
            'Command' => 'Login',
            'Token' => null,
            'Parameters' => json_encode([
                'User' => $this->username,
                'Password' => $this->password,
                'Language' => 15
            ])
        ]);

        $client = HttpClient::create();

        $responseObj = $client->request($this->apiUrl, HttpClient::METHOD_POST, $optionsLogin);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $optionsLogin, $responseObj->getContent(), $responseObj->getStatusCode());
        $response = json_decode($responseObj->getContent(), true);

        if ($response['Error']) {
            throw new Exception($response['Details']);
        }

        return $response['Token'];
    }

    public function apiGetCities(CitiesFilter $params = null): CityCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $file = 'cities';
        $citiesJson = Utils::getFromCache($this, $file);

        if ($citiesJson === null) {

            $regions = $this->apiGetRegions();
            $countries = $this->apiGetCountries();
            $cities = new CityCollection();

            $httpClient = HttpClient::create();

            $responses = [];
            foreach ($regions as $region) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetResortsList'
                        ],
                        'main' => [
                            'RegionID' => $region->Id,
                            'CountryID' => $region->Country->Id
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);

                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options, $region];
            }
            foreach ($regions as $region) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetResortsList'
                        ],
                        'main' => [
                            'RegionID' => $region->Id,
                            'CountryID' => $region->Country->Id,
                            'ShowUnpublishedResorts' => 1
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);

                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options, $region];
            }
    
            foreach ($responses as $resp) {
                $content = $resp[0]->getContent();
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $resp[1], $content, $resp[0]->getStatusCode());
                
                $responseData = simplexml_load_string($content);

                $responseList = $responseData->GetResortsList->ResortsList;
                
                if ($responseList === null) {
                    continue;
                }

                $response = $responseList->Resort;
                
                foreach ($response as $value) {
                    $city = new City();
                    $city->Country = $resp[2]->Country;
                    $city->County = $resp[2];

                    $city->Id = $value->ID;
                    $city->Name = $value->Name;
                    $cities->put($city->Id, $city);
                }
            }

            // get cities only by countries
            $responses = [];
            foreach ($countries as $country) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetResortsList',
                        ],
                        'main' => [
                            'CountryID' => $country->Id
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);

                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options, $country];
            }
            foreach ($countries as $country) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetResortsList',
                            'ShowUnpublishedResorts' => 1
                        ],
                        'main' => [
                            'CountryID' => $country->Id
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);

                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options, $country];
            }
 
            foreach ($responses as $resp) {
                $content = $resp[0]->getContent();
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $resp[1], $content, $resp[0]->getStatusCode());
                
                $responseData = simplexml_load_string($content);
                $responseList = $responseData->GetResortsList->ResortsList;
                
                if ($responseList === null) {
                    continue;
                }

                $response = $responseList->Resort;
                
                foreach ($response as $value) {
                    $city = new City();
                    $city->Country = $resp[2];

                    $city->Id = $value->ID;
                    $city->Name = $value->Name;
                    $city->County = null;

                    if ($cities->get($city->Id) === null) {
                        $cities->put($city->Id, $city);
                    }
                }
            }

            // cache cities
            $data = json_encode_pretty($cities);
            Utils::writeToCache($this, $file, $data);
        } else {
            $citiesArray = json_decode($citiesJson, true);
            $cities = ResponseConverter::convertToCollection($citiesArray, CityCollection::class);
        }

        return $cities;
    }

    public function apiGetRegions(): RegionCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $file = 'regions';
        $regionsJson = Utils::getFromCache($this, $file);

        if ($regionsJson === null) {
            $countries = $this->apiGetCountries();
            $regions = new RegionCollection();

            $httpClient = HttpClient::create();

            $responses = [];
            foreach ($countries as $country) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetRegionsList'
                        ],
                        'main' => [
                            'CountryID' => $country->Id
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);
        
                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

                $responses[] = [$responseObj, $options, $country];
            }
            
            foreach ($responses as $resp) {
                $content = $resp[0]->getContent();
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $resp[1], $content, $resp[0]->getStatusCode());

                $responseData = simplexml_load_string($content);
                $responseList = $responseData->GetRegionsList->RegionsList;

                if ($responseList === null) {
                    continue;
                }

                if (empty($responseList->Region)) {
                    continue;
                }

                foreach ($responseList->Region as $value) {

                    $region = new Region();
                    $region->Id = $value->ID;
                    $region->Name = $value->Name;
        
                    $region->Country = $resp[2];
                    $regions->put($region->Id, $region);
                }
            }

            $data = json_encode_pretty($regions);
            Utils::writeToCache($this, $file, $data);
        } else {
            $regArray = json_decode($regionsJson, true);
            $regions = ResponseConverter::convertToCollection($regArray, RegionCollection::class);
        }
        
        return $regions;
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);
        $json = Utils::getFromCache($this, 'hotels-list');

        if ($json === null || !empty($filter->CountryId)) {

            $hotels = new HotelCollection();
            $countries = $this->apiGetCountries();

            $cities = $this->apiGetCities();

            $httpClient = HttpClient::create();

            $responses = [];

            if (!empty($filter->CountryId)) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetHotelsList'
                        ],
                        'main' => [
                            'CountryID' => $filter->CountryId
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);
        
                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options];
            } else {

                foreach ($countries as $country) {
                    $requestArr = [
                        'root' => [
                            'head' => [
                                'auth' => [
                                    'username' => $this->username,
                                    'password' => $this->password
                                ],
                                'service' => 'GetHotelsList'
                            ],
                            'main' => [
                                'CountryID' => $country->Id
                            ]
                        ]
                    ];
                    $requestXml = Utils::arrayToXmlString($requestArr);
            
                    $options['body'] = $requestXml;

                    $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                    $responses[] = [$responseObj, $options];
                }
            }


            $regionMap = [];
            foreach ($responses as $resp) {
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $resp[1], $resp[0]->getContent(), $resp[0]->getStatusCode());
                $responseData = simplexml_load_string($resp[0]->getContent());
        
                $responseList = $responseData->GetHotelsList->HotelsList;
                
                if ($responseList === null) {
                    continue;
                }

                $hotelResponses = $responseList->Hotel;

                foreach($hotelResponses ?? [] as $hotelResponse) {

                    $city = $cities->get((string) $hotelResponse->ResortID);

                    if ($city === null) {
                        continue;
                    }

                    if (empty($filter->CountryId)) {
                        if ($city->County !== null) {
                            $regionMap[$city->County->Id][$city->Id] = $city->Id;
                        }
                    }

                    // $hotel->Address
                    $address = new HotelAddress();
                    $address->City = $city;

                    $hotel = new Hotel();
                    $hotel->Id = $hotelResponse->ID;
                    $hotel->Name = $hotelResponse->Name;
                    $hotel->Address = $address;

                    $hotels->put($hotel->Id, $hotel);
                }
            }
            if (empty($filter->CountryId)) {
                $data = json_encode_pretty($hotels);
                Utils::writeToCache($this, 'hotels-list', $data);
                Utils::writeToCache($this, 'region-map-hotels', json_encode($regionMap));
            }
        } else {
            $arr = json_decode($json, true);
            $hotels = ResponseConverter::convertToCollection($arr, HotelCollection::class);
        }
        
        return $hotels;
    }

    /*
    // this can be used to get hotel details with the hotel list
    public function getHotels(?HotelsFilter $filter = null): HotelCollection
    {
        set_time_limit(6000);
        Validator::make()->validateUsernameAndPassword($this->post);
        $json = Utils::getFromCache($this->handle, 'hotels-list');
        $tempHotels = new HotelCollection();
        if ($json === null || !empty($filter->countryId)) {

            $countries = $this->apiGetCountries();

            $cities = $this->getCities();
            

            $httpClient = HttpClient::create([
                'verify_peer' => false
            ]);

            $responses = [];

            if (!empty($filter->countryId)) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetHotelsList'
                        ],
                        'main' => [
                            'CountryID' => $filter->countryId
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);
        
                $options['body'] = $requestXml;

                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj, $options];
            } else {

                foreach ($countries as $country) {
                    $requestArr = [
                        'root' => [
                            'head' => [
                                'auth' => [
                                    'username' => $this->username,
                                    'password' => $this->password
                                ],
                                'service' => 'GetHotelsList'
                            ],
                            'main' => [
                                'CountryID' => $country->Id
                            ]
                        ]
                    ];
                    $requestXml = Utils::arrayToXmlString($requestArr);
            
                    $options['body'] = $requestXml;

                    $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                    $responses[] = [$responseObj, $options];
                }
            }

            foreach ($responses as $resp) {
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $resp[1], $resp[0]->getContent(), $resp[0]->getStatusCode());
                $responseData = simplexml_load_string($resp[0]->getContent());
        
                $responseList = $responseData->GetHotelsList->HotelsList;
                
                if ($responseList === null) {
                    continue;
                }

                $hotelResponses = $responseList->Hotel;

                foreach($hotelResponses as $hotelResponse) {

                    $city = $cities->get((string) $hotelResponse->ResortID);

                    if ($city === null) {
                        continue;
                    }

                    // $hotel->Address
                    $address = new HotelAddress();
                    $address->City = $city;

                    $hotel = new Hotel();
                    $hotel->Id = $hotelResponse->ID;
                    $hotel->Name = $hotelResponse->Name;
                    $hotel->Address = $address;

                    $tempHotels->put($hotel->Id, $hotel);
                }
            }
            if (empty($filter->countryId)) {
                $data = json_encode_pretty($tempHotels);
                Utils::writeToCache($this->handle, 'hotels-list', $data);
            }
        } else {
            $arr = json_decode($json, true);
            $tempHotels = ResponseConverter::convertToCollection($arr, HotelCollection::class);
        }

        Log::debug('=================================================');
        $json = Utils::getFromCache($this->handle, 'hotels');
        if ($json === null || !empty($filter->countryId)) {
            $hotels = new HotelCollection();
            $cities = $this->getCities();
            

            $i = 0;
            $httpClientAsync = HttpClient::create([
                'verify_peer' => false,
            ]);
            $responses = [];
            foreach ($tempHotels as $hotel) {$i++;
                
 
                Log::debug($i);
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetHotelDetails'
                        ],
                        'main' => [
                            'HotelID' => $hotel->Id
                        ]
                    ]
                ];
                $requestXml = Utils::arrayToXmlString($requestArr);
        
                $options['body'] = $requestXml;
        
                $responseObj = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $responses[] = [$responseObj];


                if ($i % 20 === 0) {
                    // get responses
                    Log::debug('------------------------------------------');
                    $j = 0;
                    foreach ($responses as $responseArr) {$j++;
                        $responseObj = $responseArr[0];
                        if (!simplexml_load_string($responseObj->getContent())) dump($responseObj->getContent());
                        $response = simplexml_load_string($responseObj->getContent())->GetHotelDetails;
                        Log::debug($j);

                        // Content ImageGallery Items
                        $items = new HotelImageGalleryItemCollection();

                        foreach ($response->Images->Image as $k => $imageResponse) {
                            $image = new HotelImageGalleryItem();
                            $image->RemoteUrl = $imageResponse;
                            $image->Alt = $response->Images->ImageAlt[$k];
                            $items->add($image);
                        }

                        // Content ImageGallery
                        $imageGallery = new HotelImageGallery();
                        $imageGallery->Items = $items;

                        // Content Address
                        $address = new HotelAddress();

                        $city = $cities->get($response->Resort->ID);
                        if ($city === null) {
                            continue;
                        }
                        $address->City = $city;
                        $address->Details = null;

                        $address->Latitude = $response->Latitude;
                        $address->Longitude = $response->Longitude;

                        $facilities = new FacilityCollection();
                        foreach ($response->Facilities->Facility as $facilityResponse) {
                            $facility = new Facility();
                            $facility->Id = $facilityResponse->ID;
                            $facility->Name = $facilityResponse->Name;
                            $facilities->add($facility);
                        }

                        // Content
                        $content = new HotelContent();
                        $content->Content = $response->Description;
                        $content->ImageGallery = $imageGallery;

                        $details = new Hotel();
                        $details->Id = $response->ID;
                        $details->Name = $response->Name;
                        $details->Address = $address;

                        $details->Facilities = $facilities;
                        $details->Content = $content;
                        $details->WebAddress = $response->Website;
                        $details->Stars = (int) $response->Classification->Value;
                        $hotels->put($details->Id, $details);
                        
                    }
                    $responses = [];
                }
            }
            
            
            if (empty($filter->countryId)) {
                $data = json_encode_pretty($hotels);
                Utils::writeToCache($this->handle, 'hotels', $data);
            }
        } else {
            $arr = json_decode($json, true);
            $hotels = ResponseConverter::convertToCollection($arr, HotelCollection::class);
        }
        
        $data = [];
        foreach ($hotels as $hotel) {
            $data[] = [$hotel->Name, $hotel->Id, $hotel->Address->City->Country->Name, 
                $hotel->Address->City->Name, count($hotel->Content->ImageGallery->Items)
            ];
        }
        Utils::createCsv('hotels.csv', ['Hotel', 'Id', 'Tara', 'Oras', 'Imagini'], $data);
        
        return $hotels;
    }
    */

    function cacheTopData(string $operation, array $config = [], array $filters = []): array
    {
        $result = [];
        switch ($operation) {
            case 'Hotels_Details':

                //$json = Utils::getFromCache($this->handle, 'hotel-details');
                //if ($json === null) {
                    
                    $requests = [];
                    $cities = $this->apiGetCities();
                    $httpClientAsync = HttpClient::create();

                    foreach ($filters['Hotels'] as $hotelFilter) {
                        if (!empty( $hotelFilter['InTourOperatorId'])) {

                            $filter = new HotelDetailsFilter(['HotelId' => $hotelFilter['InTourOperatorId']]);
                            
                            Validator::make()
                                ->validateUsernameAndPassword($this->post)
                                ->validateHotelDetailsFilter($filter);

                            $hotelId = $filter->hotelId;
                            
                            $requestArr = [
                                'root' => [
                                    'head' => [
                                        'auth' => [
                                            'username' => $this->username,
                                            'password' => $this->password
                                        ],
                                        'service' => 'GetHotelDetails'
                                    ],
                                    'main' => [
                                        'HotelID' => $hotelId
                                    ]
                                ]
                            ];
                            $requestXml = Utils::arrayToXmlString($requestArr);

                            $options['body'] = $requestXml;

                            $responseObj = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

                            $requests[] = [$responseObj, $options];
                        }
                    }

                    foreach ($requests as $request) {
                        $responseObj = $request[0];
                        $options = $request[1];
                        $response = simplexml_load_string($responseObj->getContent())->GetHotelDetails;
                        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());

                        if (!empty($response->Error)) {
                            continue;
                        }
                        // Content ImageGallery Items
                        $items = new HotelImageGalleryItemCollection();

                        foreach ($response->Images->Image as $k => $imageResponse) {
                            $image = new HotelImageGalleryItem();
                            $image->RemoteUrl = $imageResponse;
                            $image->Alt = $response->Images->ImageAlt[$k];
                            $items->add($image);
                        }

                        // Content ImageGallery
                        $imageGallery = new HotelImageGallery();
                        $imageGallery->Items = $items;

                        // Content Address
                        $address = new HotelAddress();

                        $city = $cities->get((string)$response->Resort->ID);
                        if ($city === null) {
                            continue;
                        }

                        $address->City = $city;
                        $address->Details = null;

                        $address->Latitude = $response->Latitude;
                        $address->Longitude = $response->Longitude;

                        $facilities = new FacilityCollection();
                        foreach ($response->Facilities->Facility as $facilityResponse) {
                            $facility = new Facility();
                            $facility->Id = $facilityResponse->ID;
                            $facility->Name = $facilityResponse->Name;
                            $facilities->add($facility);
                        }

                        // Content
                        $content = new HotelContent();
                        $content->Content = $response->Description;
                        $content->ImageGallery = $imageGallery;

                        $details = new Hotel();
                        $details->Id = $response->ID;
                        $details->Name = $response->Name;
                        $details->Address = $address;
                        //$details->ContactPerson = $contactPerson;
                        $details->Facilities = $facilities;
                        $details->Content = $content;
                        $details->WebAddress = $response->Website;
                        $details->Stars = (int) $response->Classification->Value;

                        $result[] = $details;
                        //Utils::writeToCache($this->handle, 'hotel-details', json_encode($result));
                    }
                //} else {
                    //$result = json_decode($json, true);
                //}
                
                break;
            case 'Countries':
                break;
            case 'Counties':
                break;
            case 'Cities':
                break;
            case 'Hotels':
                
                foreach ($filters['Cities'] as $hotelFilter) {
                    if (!empty( $hotelFilter['InTourOperatorId'])) {
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

    public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateHotelDetailsFilter($filter);

        $details = new Hotel();
        $hotelId = $filter->hotelId;
        $cities = $this->apiGetCities();

        $requestArr = [
            'root' => [
                'head' => [
                    'auth' => [
                        'username' => $this->username,
                        'password' => $this->password
                    ],
                    'service' => 'GetHotelDetails'
                ],
                'main' => [
                    'HotelID' => $hotelId
                ]
            ]
        ];
        $requestXml = Utils::arrayToXmlString($requestArr);

        $options['body'] = $requestXml;

        $httpClientAsync = HttpClient::create([
            'verify_peer' => false,
        ]);

        $responseObj = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $response = simplexml_load_string($responseObj->getContent())->GetHotelDetails;
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());

        // Content ImageGallery Items
        $items = new HotelImageGalleryItemCollection();

        foreach ($response->Images->Image as $k => $imageResponse) {
            $image = new HotelImageGalleryItem();
            $image->RemoteUrl = $imageResponse;
            $image->Alt = $response->Images->ImageAlt[$k];
            $items->add($image);
        }

        // Content ImageGallery
        $imageGallery = new HotelImageGallery();
        $imageGallery->Items = $items;

        // Content Address
        $address = new HotelAddress();

        $address->City = $cities->get($response->Resort->ID);
        $address->Details = null;

        $address->Latitude = $response->Latitude;
        $address->Longitude = $response->Longitude;

        $facilities = new FacilityCollection();
        foreach ($response->Facilities->Facility as $facilityResponse) {
            $facility = new Facility();
            $facility->Id = $facilityResponse->ID;
            $facility->Name = $facilityResponse->Name;
            $facilities->add($facility);
        }

        // Content
        $content = new HotelContent();
        $content->Content = $response->Description;
        $content->ImageGallery = $imageGallery;

        $details->Id = $response->ID;
        $details->Name = $response->Name;
        $details->Address = $address;
        //$details->ContactPerson = $contactPerson;
        $details->Facilities = $facilities;
        $details->Content = $content;
        $details->WebAddress = $response->Website;
        $details->Stars = (int) $response->Classification->Value;
        return $details;
    }

    // la getCharterOffers: de facut sa trimit request pe fiecare oras de sub zona respectiva un fel de config
    // orasul sa fie in datele de plecare din  toate transporturile
    private function getHotelOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        TravelioValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateAvailabilityFilter($filter);

        $availabilityCollection = new AvailabilityCollection();

        $cities = [];
        $region = null;

        if (empty($filter->cityId) && empty($filter->hotelId)) {
            $regionMap = Utils::getFromCache($this, 'region-map-hotels', true);
            $regionMap = json_decode($regionMap, true);

            $cities = $regionMap[$filter->regionId];
            
        } elseif (!empty($filter->hotelId) && !empty($filter->regionId)) {
            $region = $filter->regionId;
        } else {
            $cities = [$filter->cityId];
        }

        $client = HttpClient2::create();

        $requests = [];

        if ($region === null) {
            foreach ($cities as $cityFilter) {
                // prepare requests
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'SearchAvailableHotels'
                        ],
                        'main' => [
                            'Currency' => 'EUR',
                            'ResortID' => $cityFilter,
                            'CheckIn' => $filter->checkIn,
                            'CheckOut' => $filter->checkOut,
                            'Rooms' => [
                                'Room' => [
                                    'Adults' => $filter->rooms->first()->adults,
                                    'Children' => $filter->rooms->first()->children
                                ]
                            ],
                            'HotelID' => $filter->hotelId
                        ]
                    ]
                ];
        
                $ages = $filter->rooms->first()->childrenAges;
                if ($filter->rooms->first()->children > 0) {
                    foreach ($ages as $age) {
                        if ($age !== '') {
                            $requestArr['root']['main']['Rooms']['Room']['ChildrenAges'][] = ['ChildAge' => $age];
                        }
                    }
                }
        
                $requestXml = Utils::arrayToXmlString($requestArr);
        
                $optionsB['body'] = $requestXml;
        
                $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $optionsB);
                $requests[] = [$response, $optionsB];
            }
        } else {
            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'SearchAvailableHotels'
                    ],
                    'main' => [
                        'Currency' => 'EUR',
                        'RegionID' => $region,
                        'CheckIn' => $filter->checkIn,
                        'CheckOut' => $filter->checkOut,
                        'Rooms' => [
                            'Room' => [
                                'Adults' => $filter->rooms->first()->adults,
                                'Children' => $filter->rooms->first()->children
                            ]
                        ],
                        'HotelID' => $filter->hotelId
                    ]
                ]
            ];
    
            $ages = $filter->rooms->first()->childrenAges;
            if ($filter->rooms->first()->children > 0) {
                foreach ($ages as $age) {
                    if ($age !== '') {
                        $requestArr['root']['main']['Rooms']['Room']['ChildrenAges'][] = ['ChildAge' => $age];
                    }
                }
            }
    
            $requestXml = Utils::arrayToXmlString($requestArr);
    
            $optionsB['body'] = $requestXml;
    
            $response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $optionsB);
            $requests[] = [$response, $optionsB];
        }

        foreach ($requests as $request) {
            $responseObj = $request[0];
            $options = $request[1];

            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());
            $responseData = simplexml_load_string($responseObj->getContent());

            $offerResponses = $responseData->SearchAvailableHotels->HotelsList;

            foreach ($offerResponses->Hotel as $availResponse) {
                foreach ($availResponse->Response as $offerResponse) {

                    $availabilityStr = Offer::AVAILABILITY_ASK;

                    if ($offerResponse->Rooms->Room->Availability === 'Available') {
                        $availabilityStr = Offer::AVAILABILITY_YES;
                    }
                    $checkInDateTime = new DateTimeImmutable($offerResponse->Checkin);
                    $checkOutDateTime = new DateTimeImmutable($offerResponse->Checkout);

                    // todo: de vazut unde apare tipul de camera
                    $offer = Offer::createIndividualOffer(
                        $offerResponse->ID,
                        $offerResponse->Rooms->Room->ID,
                        $offerResponse->Rooms->Room->ID,// care e necesar?
                        $offerResponse->Rooms->Room->NiceName,
                        $offerResponse->BoardType->ID,
                        $offerResponse->BoardType->NiceName,
                        $checkInDateTime,
                        $checkOutDateTime,
                        $filter->rooms->first()->adults,
                        $ages->toArray(),
                        $offerResponse->Currency,
                        (float) $offerResponse->TotalPrice,
                        (float) $offerResponse->TotalPrice + (float) $offerResponse->Discounts,
                        (float) $offerResponse->TotalPrice,
                        (float) $offerResponse->Commission,
                        $availabilityStr
                    );
                    $offer->InitialData = $offerResponse->SearchID;
                    $offer->roomCombinationPriceDescription = $offerResponse->Rooms->Room->SearchResultData;
                    $offer->Days = $offerResponse->Nights;

                    $availability = $availabilityCollection->get($offerResponse->ID);
                    if ($availability === null) {
                        $availability = new Availability();
                        $availability->Id = $offerResponse->ID;
                        $offers = new OfferCollection();
                    } else {
                        $offers = $availability->Offers;
                    }

                    $offers->put($offer->Code, $offer);
                    $availability->Offers = $offers;

                    $availabilityCollection->put($availability->Id, $availability);
                }
            }

        }

        return $availabilityCollection;
    }

    private function getCharterOrTourOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        TravelioValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateCharterOffersFilter($filter);

        $availabilityCollection = new AvailabilityCollection();

        $httpClient = HttpClient::create();
        $cities = $this->apiGetCities();

        $days = $filter->days;
        $checkInDT = new DateTimeImmutable($filter->checkIn);
        $checkOut = $checkInDT->modify('+ '.$days. ' days')->format('Y-m-d');

        $hotelId = $filter->hotelId;
        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
            $hotelId = explode('|', $hotelId)[2] ?? null;
        }

        $results = [];

        $sync = false;
        if ($sync) {
            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'StartAsyncSearch'
                    ],
                    'main' => [
                        'SearchType' => 'packages',
                        'DepartureCityId' => $filter->departureCity ?? $filter->departureCityId,
                        'TransportType' => $filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE ? 'Avion' : 'Autocar',
                        'CheckIn' => $filter->checkIn,
                        'CheckOut' => $checkOut,
                        'Rooms' => [
                            'Room' => [
                                'Adults' => $filter->rooms->first()->adults,
                                'Children' => $filter->rooms->first()->children
                            ]
                        ],
                        'HotelID' => $hotelId
                    ]
                ]
            ];
    
            if (!empty($filter->cityId)) {
                $requestArr['root']['main']['ResortID'] = $filter->cityId; 
            } elseif (!empty($filter->regionId)) {
                $requestArr['root']['main']['RegionID'] = $filter->regionId;
            }
    
            if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
                $requestArr['root']['main']['MultipleFlights'] = 1;
            }
    
            $ages = $filter->rooms->first()->childrenAges;
            if ($filter->rooms->first()->children > 0) {   
                $requestArr['root']['main']['Rooms']['Room']['ChildrenAges']['ChildAge'] = $filter->rooms->first()->childrenAges->toArray();
            }
    
            $requestJson = json_encode($requestArr);
    
            $options['body'] = $requestJson;
            $options['headers'] = ['Content-Type' => 'application/json'];
    
            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            $content = $responseObj->getContent(false);
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
    
            $searchId = json_decode($content, true)['searchId'];
            $requestArr = [
                'root' => [
                    'head' => [
                        'auth' => [
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'service' => 'GetSearchResults'
                    ],
                    'main' => [
                        'searchId' => $searchId,
                    ]
                ]
            ];
            $requestXml = json_encode($requestArr);
    
            $options['body'] = $requestXml;
            $options['headers'] = ['Content-Type' => 'application/json'];
    
            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            $content = $responseObj->getContent(false);
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
            $response = json_decode($content, true);
            $results[] = $response;
    
            $t0 = microtime(true);
            $count = 0;
            while(true) {
                $t1 = microtime(true);
                $dif = $t1 - $t0;
                if ($dif > 50) break;
    
                $count++;
                if ($count === 120) {
                    // timeout break
                    break;
                }
                sleep(1);
                $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $content = $responseObj->getContent();
    
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
                $response = json_decode($content, true);
    
                if (empty($response['status'])) {
                    break;
                }
    
                if (count($response['searchResults']) > 0) {
                    $results[] = $response;
                }
    
                if ($response['status'] !== 'running') {
                    break;
                }
            }
        } else {





//-----------------------------------------------------------------------------

            $citiesToSearch = [];
            if (empty($filter->cityId) && empty($filter->hotelId)) {
                $regionMap = Utils::getFromCache($this, 'region-map-transports', true);
                $regionMap = json_decode($regionMap, true);
                if (!isset($regionMap[$filter->regionId])) {
                    return $availabilityCollection;
                }
                $citiesToSearch = $regionMap[$filter->regionId];
            } else {
                $citiesToSearch = [$filter->cityId];
            }

            $client = HttpClient::create();

            $max = 50; // seconds
            $t0 = microtime(true);

            $ages = $filter->rooms->first()->childrenAges;

            $asyncSearchRequests = [];
            foreach ($citiesToSearch as $cityToSearch) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'StartAsyncSearch'
                        ],
                        'main' => [
                            'SearchType' => 'packages',
                            'DepartureCityId' => $filter->departureCity ?? $filter->departureCityId,
                            'TransportType' => $filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE ? 'Avion' : 'Autocar',
                            'CheckIn' => $filter->checkIn,
                            'CheckOut' => $checkOut,
                            'Rooms' => [
                                'Room' => [
                                    'Adults' => $filter->rooms->first()->adults,
                                    'Children' => $filter->rooms->first()->children
                                ]
                            ],
                            'ResortID' => $cityToSearch,
                            'HotelID' => $hotelId
                        ]
                    ]
                ];

                if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
                    $requestArr['root']['main']['MultipleFlights'] = 1;
                }
        
                
                if ($filter->rooms->first()->children > 0) {   
                    $requestArr['root']['main']['Rooms']['Room']['ChildrenAges']['ChildAge'] = $filter->rooms->first()->childrenAges->toArray();
                }
        
                $requestJson = json_encode($requestArr);
        
                $options['body'] = $requestJson;
                $options['headers'] = ['Content-Type' => 'application/json'];

                $responseObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                $asyncSearchRequests[] = [$responseObj, $options];
            }

            $searchIds = [];
            foreach ($asyncSearchRequests as $asyncSearchRequest) {
                $responseObj = $asyncSearchRequest[0];
                $options = $asyncSearchRequest[1];

                $content = $responseObj->getContent(false);
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
    
                $searchId = json_decode($content, true)['searchId'];
                $searchIds[] = $searchId;
            }
            
            $requests = [];

            foreach ($searchIds as $searchId) {
                $requestArr = [
                    'root' => [
                        'head' => [
                            'auth' => [
                                'username' => $this->username,
                                'password' => $this->password
                            ],
                            'service' => 'GetSearchResults'
                        ],
                        'main' => [
                            'searchId' => $searchId,
                        ]
                    ]
                ];
                $requestXml = json_encode($requestArr);
        
                $options['body'] = $requestXml;
                $options['headers'] = ['Content-Type' => 'application/json'];
        
                $responseObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

                $requests[] = [$responseObj, $options];
            }

            while(true) {
                $current = microtime(true);
                $remaining = (int)($max - ($current - $t0));
                if ($remaining < 1) {
                    $remaining = 1;
                }

                $client = HttpClient::create(['max_duration' => $remaining]);

                $currentRequests = [];
                $break = false;
                foreach ($requests as $request) {

                    $t1 = microtime(true);
                    $dif = $t1 - $t0;
                    
                    if ($dif > $max) {
                        $break = true;
                        break;
                    }

                    $responseObj = $request[0];
                    $options = $request[1];

                    $content = $responseObj->getContent();
                    $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
                    $response = json_decode($content, true);

                    if (!empty($response['status']) && $response['status'] === 'running') {
                        $requestObj = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                        $currentRequests[] = [$requestObj, $options];
                    }

                    if (isset($response['searchResults']) && count($response['searchResults']) > 0) {
                        $results[] = $response;
                    }
                }

                $requests = $currentRequests;
                // if no requests left, break
                if (count($currentRequests) === 0 || $break) {
                    break;
                }
                sleep(1);
            }
            
        }
        //$b = microtime(true);
        //Log::debug('total: '. $b-$a);

        //----------------------------------------------------------------------

        //dump($results);
        foreach ($results as $responseData) {

            $offerResponses = $responseData['searchResults'];

            foreach ($offerResponses as $responseHotel) {
                if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER && $responseHotel['packageType'] !== 'Sejur') {
                    continue;
                }
                if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR && $responseHotel['packageType'] !== 'Circuit') {
                    continue;
                }

                $availability = $availabilityCollection->get($responseHotel['hotel']['id']);

                $offers = new OfferCollection();
                if ($availability === null) { 
                    $availability = new Availability();
                    if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
                        $availability->Id = $responseHotel['id'] . '|' . $responseHotel['prices'][0]['packageNights'] . '|' . $responseHotel['hotel']['id'];
                    } else {
                        $availability->Id = $responseHotel['hotel']['id'];
                    }

                    if ($filter->showHotelName) {
                        $availability->Name = $responseHotel['hotel']['hotelName'];
                    }
                } else {
                    $offers = $availability->Offers;
                }
                
                foreach ($responseHotel['prices'] as $offerResponse) {

                    $availabilityStr = Offer::AVAILABILITY_ASK;
                    if ($offerResponse['rooms']['room']['availability'] === 'Available') {
                        $availabilityStr = Offer::AVAILABILITY_YES;
                    }

                    if (!isset($responseHotel['transportation'])) {
                        $departureTransport = $offerResponse['transportation']['departure'];
                        $returnTransport = $offerResponse['transportation']['return'];
                    } else {
                        $departureTransport = $responseHotel['transportation']['departure'];
                        $returnTransport = $responseHotel['transportation']['return'];
                    }

                    $flightDepartureDateTime = DateTime::createFromFormat('d-m-Y H:i', $departureTransport['DepartureDate'] . ' '. $departureTransport['DepartureTime']);
                    $flightArrivalDateTime = DateTime::createFromFormat('d-m-Y H:i', $departureTransport['ArrivalDate'] . ' '. $departureTransport['ArrivalTime']);
                    $flightReturnDateTime = DateTime::createFromFormat('d-m-Y H:i', $returnTransport['DepartureDate'] . ' '. $returnTransport['DepartureTime']);
                    $flightReturnArrivalDateTime = DateTime::createFromFormat('d-m-Y H:i', $returnTransport['ArrivalDate'] . ' '. $returnTransport['ArrivalTime']);

                    $cityDep = $cities->get($filter->departureCity ?? $filter->departureCityId);
                    $cityArr = null;
                    if ($departureTransport['ArrivalCityId'] !== null) {
                        $cityArr = $cities->get($departureTransport['ArrivalCityId']);
                    }

                    $arr['id_hotel'] = $responseHotel['hotel']['id'];
                    if (isset($offerResponse['hasDynamicTransport']) && $offerResponse['hasDynamicTransport'] ) {
                        $arr['id_transport'] = $offerResponse['transportId'];
                    }
                    $json = json_encode($arr);

                    $hasTransferIncluded = false;

                    if (!empty($offerResponse['services'])) {								
                        foreach ($offerResponse['services'] as $packageServices) {
                            if (($packageServices['nume'] == 'Transfer aeroport - hotel - aeroport') && ($packageServices['tip_oblig'] == 'Inclus')) {
                                $hasTransferIncluded = true;
                            }
                        }
                    }	

                    $offer = Offer::createCharterOrTourOffer(
                        $availability->Id,
                        $offerResponse['rooms']['room']['id'],
                        $offerResponse['rooms']['room']['id'],
                        $offerResponse['rooms']['room']['niceName'],
                        $offerResponse['rooms']['room']['boardId'],
                        $offerResponse['rooms']['room']['boardNiceName'],
                        new DateTimeImmutable($offerResponse['checkinHotel']),
                        new DateTimeImmutable($offerResponse['checkoutHotel']),
                        $filter->rooms->first()->adults,
                        $ages->toArray(),
                        $offerResponse['rooms']['room']['currency'],
                        !empty($offerResponse['totalNet']) ? (float) $offerResponse['totalNet'] : (float)$offerResponse["total"],
                        (float) $offerResponse["total"] + (float) $offerResponse['totalDiscount'],
                        (float) $offerResponse["total"],
                        0,
                        $availabilityStr,
                        null,
                        $flightDepartureDateTime,
                        $flightArrivalDateTime,
                        $flightReturnDateTime,
                        $flightReturnArrivalDateTime,
                        $departureTransport['DepartureCode'],
                        $departureTransport['ArrivalCode'],
                        $departureTransport['ArrivalCode'],
                        $departureTransport['DepartureCode'],
                        $filter->transportTypes->first(),
                        $cities->get($filter->departureCity ?? $filter->departureCityId),
                        $cityArr,
                        $cityArr,
                        $cityDep,
                        $json,
                        $hasTransferIncluded
                    );

                    $offer->InitialData = $responseHotel['searchId'];
                    $offer->packageId = $responseHotel['id'];
                    $offer->roomCombinationPriceDescription = $offerResponse['rooms']['room']['searchResultData'];
                    $offers->add($offer);
                    
                }
                $availability->Offers = $offers;
                $availabilityCollection->put($availability->Id, $availability);
            }
        }
        
        return $availabilityCollection;
    }

    private function getTourOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        TravelioValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateCharterOffersFilter($filter);

        $availabilityCollection = new AvailabilityCollection();

        $httpClient = HttpClient::create();
        $cities = $this->apiGetCities();

        $days = $filter->days;
        $checkInDT = new DateTimeImmutable($filter->checkIn);
        $checkOut = $checkInDT->modify('+ '.$days. ' days')->format('Y-m-d');

        $requestArr = [
            'root' => [
                'head' => [
                    'auth' => [
                        'username' => $this->username,
                        'password' => $this->password
                    ],
                    'service' => 'StartAsyncSearch'
                ],
                'main' => [
                    'SearchType' => 'packages',
                    'DepartureCityId' => $filter->departureCity ?? $filter->departureCityId,
                    'TransportType' => $filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE ? 'Avion' : 'Autocar',
                    'ResortID' => $filter->cityId,
                    'CheckIn' => $filter->checkIn,
                    'CheckOut' => $checkOut,
                    'Rooms' => [
                        'Room' => [
                            'Adults' => $filter->rooms->first()->adults,
                            'Children' => $filter->rooms->first()->children
                        ]
                    ],
                    'HotelID' => $filter->hotelId,
                    
                ]
            ]
        ];

        $ages = $filter->rooms->first()->childrenAges;
        if ($filter->rooms->first()->children > 0) {
            foreach ($ages as $age) {
                if ($age !== '') {
                    $requestArr['root']['main']['Rooms']['Room']['ChildrenAges'][] = ['ChildrenAge' => $age];
                }
            }
        }

        $requestXml = json_encode($requestArr);

        $options['body'] = $requestXml;
        $options['headers'] = ['Content-Type' => 'application/json'];

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $content = $responseObj->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());

        $searchId = json_decode($content, true)['searchId'];
        $requestArr = [
            'root' => [
                'head' => [
                    'auth' => [
                        'username' => $this->username,
                        'password' => $this->password
                    ],
                    'service' => 'GetSearchResults'
                ],
                'main' => [
                    'searchId' => $searchId,
                ]
            ]
        ];

        $requestXml = json_encode($requestArr);

        $options['body'] = $requestXml;
        $options['headers'] = ['Content-Type' => 'application/json'];

        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $content = $responseObj->getContent(false);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
        $response = json_decode($content, true);
        $results[] = $response;

        $count = 0;
        while(true) {
            $count++;
            if ($count === 120) {
                // timeout break
                break;
            }
            sleep(1);
            $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
            $content = $responseObj->getContent();

            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $content, $responseObj->getStatusCode());
            $response = json_decode($content, true);
            if (count($response['searchResults']) > 0) {
                $results[] = $response;
            }

            if ($response['status'] !== 'running') {
                break;
            }
        }

        foreach ($results as $responseData) {

            $offerResponses = $responseData['searchResults'];

            foreach ($offerResponses as $responseHotel) {
                if ($responseHotel['packageType'] !== 'Circuit') {
                    continue;
                }

                $availability = new Availability();
                $availability->Id = $responseHotel['id'].'|'.$responseHotel['prices'][0]['packageNights'];
                if ($filter->showHotelName) {
                    $availability->Id = $responseHotel['name'];
                }

                $offers = new OfferCollection();
                foreach ($responseHotel['prices'] as $offerResponse) {

                    $offer = new Offer();
                    $currency = new Currency();
                    $currency->Code = $offerResponse['rooms']['room']['currency'];
                    $offer->Currency = $currency;

                    $availabilityStr = Offer::AVAILABILITY_ASK;

                    if ($offerResponse['rooms']['room']['availability'] === 'Available') {
                        $availabilityStr = Offer::AVAILABILITY_YES;
                    }
                    $offer->Availability = $availabilityStr;

                    $roomId = $offerResponse['rooms']['room']['id'];
                    $mealId = $offerResponse['rooms']['room']['boardId'];

                    $discount = (float) $offerResponse['totalDiscount'];
                    $offer->Net = !empty($offerResponse['totalNet']) ? (float) $offerResponse['totalNet'] : (float)$offerResponse["total"];
                    $offer->Gross = (float) $offerResponse["total"];
                    $offer->InitialPrice = $offer->Gross + $discount;

                    $offer->InitialData = $responseHotel['searchId'];
                    $offer->roomCombinationPriceDescription = $offerResponse['rooms']['room']['searchResultData'];

                    $room1 = new Room();
                    $room1->Id = $roomId;
                    $room1->CheckinAfter = $offerResponse['checkinHotel'];
                    $room1->CheckinBefore = $offerResponse['checkoutHotel'];

                    $room1->Currency = $offer->Currency;
                    $room1->Availability = $offer->Availability;

                    $roomMerchType = new RoomMerchType();
                    $roomMerchType->Id = $roomId;
                    $roomMerchType->Title = $offerResponse['rooms']['room']['niceName'];

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;
                    $merch->Title = $roomMerchType->Title;
                    $merch->Type = $roomMerchType;
                    $merch->Code = $roomId;
                    $merch->Name = $roomMerchType->Title;

                    $room1->Merch = $merch;

                    $offer->Rooms = new RoomCollection([$room1]);

                    $offer->Item = $room1;

                    $mealItem = new MealItem();

                    $boardTypeName = $offerResponse['rooms']['room']['boardNiceName'];

                    // MealItem Merch
                    $boardMerch = new MealMerch();
                    $boardMerch->Title = $boardTypeName;

                    // MealItem
                    $mealItem->Merch = $boardMerch;
                    $mealItem->Currency = $currency;

                    $offer->MealItem = $mealItem;

                    $mealId = $offerResponse['rooms']['room']['boardId'];

                    $offerCode = $availability->Id . '~' . $roomId . '~' . $mealId . '~' . $room1->CheckinAfter . '~' . $room1->CheckinBefore . '~' . $offer->Net . '~' . $filter->rooms->first()->adults . ($ages ? '~' . implode('|', $ages->toArray()) : '');
                    $offer->Code = $offerCode;

                    $departureTransport = $responseHotel['transportation']['departure'];
                    $returnTransport = $responseHotel['transportation']['return'];

                    $flightDepartureDateTime = DateTime::createFromFormat('d-m-Y H:i', $departureTransport['DepartureDate'] . ' '. $departureTransport['DepartureTime']);
                    $flightArrivalDateTime = DateTime::createFromFormat('d-m-Y H:i', $departureTransport['ArrivalDate'] . ' '. $departureTransport['ArrivalTime']);
                    $flightReturnDateTime = DateTime::createFromFormat('d-m-Y H:i', $returnTransport['DepartureDate'] . ' '. $returnTransport['DepartureTime']);
                    $flightReturnArrivalDateTime = DateTime::createFromFormat('d-m-Y H:i', $returnTransport['ArrivalDate'] . ' '. $returnTransport['ArrivalTime']);

                    // departure transport item merch
                    $departureTransportMerch = new TransportMerch();
                    $departureTransportMerch->Title = "Dus: ". $flightDepartureDateTime->format('d.m.Y');
                    $departureTransportMerch->Category = new TransportMerchCategory();
                    $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                    $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $departureTransportMerch->DepartureTime = $flightDepartureDateTime->format('Y-m-d H:i');
                    $departureTransportMerch->ArrivalTime = $flightArrivalDateTime->format('Y-m-d H:i');
                    
                    $departureTransportMerch->DepartureAirport = $departureTransport['DepartureCode'];
                    $departureTransportMerch->ReturnAirport = $departureTransport['ArrivalCode'];

                    $departureTransportMerch->From = new TransportMerchLocation();
                    //$cityDep = new City();
                    $cityDep = $cities->get($filter->departureCity ?? $filter->departureCityId);
                    //$cityDep->Id = $filter->departureCity;
                    $departureTransportMerch->From->City = $cityDep;

                    //$cityArr = new City();
                    $cityArr = $cities->get($filter->cityId);
                    $cityArr->Id = $filter->cityId;
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

                    $returnTransportMerch->DepartureAirport = $returnTransport['DepartureCode'];
                    $returnTransportMerch->ReturnAirport = $returnTransport['ArrivalCode'];

                    $returnTransportMerch->From = new TransportMerchLocation();
                    $returnTransportMerch->From->City = $cityArr;

                    $returnTransportMerch->To = new TransportMerchLocation();
                    $returnTransportMerch->To->City = $cityDep;

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
                    $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);

                    $offers->add($offer);
                    
                }
                $availability->Offers = $offers;
                $availabilityCollection->add( $availability);
            }
        }
        return $availabilityCollection;
    }

    public function apiGetTours(): TourCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post);

        $file = 'tours';
        $toursJson = Utils::getFromCache($this, $file);

        if ($toursJson === null) {

            $cities = $this->apiGetCities();
            $countries = $this->apiGetCountries();

            $packagesCountry = [];

            $httpClientAsync = HttpClient::create();

            $packagesCountryJson = Utils::getFromCache($this, 'pac');

            if ($packagesCountryJson === null) {
            
                foreach ($countries as $country) {
                    $requestArr = [
                        'root' => [
                            'head' => [
                                'auth' => [
                                    'username' => $this->username,
                                    'password' => $this->password
                                ],
                                'service' => 'GetPackagesList',
                            ],
                            'main' => [
                                'CountryID' => $country->Id,
                                'PackageType' => 'Circuit'
                            ]
                        ]
                    ];

                    $requestXml = Utils::arrayToXmlString($requestArr);

                    $options['body'] = $requestXml;

                    $responseObj = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

                    $content = $responseObj->getContent(false);

                    $packagesCountry[] = [$content, $options];
                }
                Utils::writeToCache($this, 'pac', json_encode($packagesCountry));
            } else {
               $packagesCountry = json_decode($packagesCountryJson, true); 
            }

            $tours = new TourCollection();

            foreach($packagesCountry as $packagesArr) {
                
                //$this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $packagesArr[1], $packagesArr[0]->getContent(), $packagesArr[0]->getStatusCode());
                $content = $packagesArr[0];
                
                $responseData = simplexml_load_string($content);
                $packages = $responseData->GetPackagesList->PackagesList;
                if (empty($responseData->GetPackagesList->PackagesList->Package->ID)) {
                    continue;
                }

                //$httpClientAsync = HttpClient::create();
                //$asyncRequestsPerCountry = [];
 
                $httpClientAsync = HttpClient::create();

                $reqs = [];
                foreach ($packages->Package as $package) {
                    $requestArr = [
                        'root' => [
                            'head' => [
                                'auth' => [
                                    'username' => $this->username,
                                    'password' => $this->password
                                ],
                                'service' => 'GetPackageDetails',
                            ],
                            'main' => [
                                'PackageID' => (string) $package->ID,
                            ]
                        ]
                    ];
        
                    $requestXml = Utils::arrayToXmlString($requestArr);
        
                    $options['body'] = $requestXml;
                    
                    // check cache
                    //$content = Utils::getFromCache($this->handle, 'package-'.$package->ID);
                    //$responseObjDetails = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                    //$asyncRequestsPerCountry[] = ['url' => $this->apiUrl, 'method' => HttpClient::METHOD_POST, 'options' => $options];
                    $reqs[] = $httpClientAsync->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
                }
                // foreach ($reqs as $r) {
                //     $r->getContent();
                //     dump(count($reqs));die;
                // }
                //$r = $this->curl_fetch_multi_2($asyncRequestsPerCountry);
                //dump(count($r));


                //die;



                foreach ($reqs as $requestsPerCountry) {
                    
                    $responseData = simplexml_load_string($requestsPerCountry->getContent());

                    $packageDetails = $responseData->GetPackageDetails;
                    if ((int) $packageDetails->Circuit !== 1) {
                        continue;
                    }
                    
                    $departureDates = $packageDetails->DepartureDates->DepartureDate;
                    if ($departureDates === null) {
                        continue;
                    }
    
                    foreach ($departureDates as $packageDepartureDate) {
                        
                        $tour = new Tour();
                        $tour->Id = $packageDetails->ID . '|' . $packageDepartureDate->Nights . '|' .$packageDetails->HotelID;
                        $tour->Title = $packageDetails->Name;
    
                        $destinations = new CityCollection();
                        $destinationCountries = new CountryCollection();
                        $resortId = $packageDetails->ResortID;
                        $destinationCity = $cities->get($resortId);
                        $destinations->add($destinationCity);
                        $destinationCountries->add($destinationCity->Country);
    
                        $tour->Destinations = $destinations;
                        $tour->Destinations_Countries = $destinationCountries;
    
                        $location = new Location();
                        $location->City = $destinationCity;
                        $tour->Location = $location;
    
                        $content = new TourContent();
                        $content->Content = $packageDetails->Description;
                        $tour->Content = $content;
    
                        $servicesText = '';
                        $transportTypes = new StringCollection();

                        $transport = null;
                        if ((string) $packageDetails->Transportation === 'avion') {
                            $transport = AvailabilityDates::TRANSPORT_TYPE_PLANE;
                        } elseif ((string) $packageDetails->Transportation === 'autocar') {
                            $transport = AvailabilityDates::TRANSPORT_TYPE_BUS;
                        } else {
                            continue;
                        }

                        $transportTypes->add($transport);
                        
                        foreach ($packageDetails->Services->Service as $packageService) {
                            $servicesText .= '<p>' . $packageService->Name . '</p>';
                        }
                        $tour->Period = (int) $packageDepartureDate->Nights;
                        $tour->TransportTypes = $transportTypes;
                        $tour->Services = $servicesText;
                        $tours->put($tour->Id, $tour);
                    }
                }
            }

            $data = json_encode_pretty($tours);
            Utils::writeToCache($this, $file, $data);
            
        } else {
            $toursArray = json_decode($toursJson, true);
            $tours = ResponseConverter::convertToCollection($toursArray, TourCollection::class);
        }
        
        return $tours;
    }

    private function curl_fetch_multi_2(array $requests, int $max_connections = 100, array $additional_curlopts = null)
    {
        
        $ret = [];
        $mh = curl_multi_init();
       
        $max_connections = min($max_connections, count($requests));
        $unemployed_workers = [];
        for ($i = 0; $i < $max_connections; ++ $i) {
            $unemployed_worker = curl_init();
            if (! $unemployed_worker) {
                throw new \RuntimeException("failed creating unemployed worker #" . $i);
            }
            $unemployed_workers[] = $unemployed_worker;
        }
        unset($i, $unemployed_worker);

        $j = 0;
        $workers = [];
        $work = function () use (&$workers, &$unemployed_workers, &$mh, &$ret, &$j): void {
            assert(count($workers) > 0, "work() called with 0 workers!!");
            $number_of_curl_handles_still_running = null;
            for (;;) {
                do {
                    $err = curl_multi_exec($mh, $number_of_curl_handles_still_running);
                } while ($err === CURLM_CALL_MULTI_PERFORM);
                if ($err !== CURLM_OK) {
                    $errinfo = [
                        "multi_exec_return" => $err,
                        "curl_multi_errno" => curl_multi_errno($mh),
                        "curl_multi_strerror" => curl_multi_strerror($err)
                    ];
                    $errstr = "curl_multi_exec error: " . str_replace([
                        "\r",
                        "\n"
                    ], "", var_export($errinfo, true));
                    throw new \RuntimeException($errstr);
                }
                if ($number_of_curl_handles_still_running < count($workers)) {
                    // some workers has finished downloading, process them
                    // echo "processing!";
                    break;
                } else {
                    // no workers finished yet, sleep-wait for workers to finish downloading.
                    // echo "select()ing!";
                    curl_multi_select($mh, 1);
                    // sleep(1);
                }
            }

            while (false !== ($info = curl_multi_info_read($mh))) {
                
                if ($info['msg'] !== CURLMSG_DONE) {
                    // no idea what this is, it's not the message we're looking for though, ignore it.
                    continue;
                }
                if ($info['result'] !== CURLM_OK) {
                    $errinfo = [
                        "effective_url" => curl_getinfo($info['handle'], CURLINFO_EFFECTIVE_URL),
                        "curl_errno" => curl_errno($info['handle']),
                        "curl_error" => curl_error($info['handle']),
                        "curl_multi_errno" => curl_multi_errno($mh),
                        "curl_multi_strerror" => curl_multi_strerror(curl_multi_errno($mh))
                    ];
                    $errstr = "curl_multi worker error: " . str_replace([
                        "\r",
                        "\n"
                    ], "", var_export($errinfo, true));
                    throw new \RuntimeException($errstr);
                }
                $ch = $info['handle'];
                $ch_index = (int) $ch;
                $url = $workers[$ch_index];
                $j++;Log::debug('while: '.$j);
                $ret[$url] = curl_multi_getcontent($ch);
                unset($workers[$ch_index]);
                curl_multi_remove_handle($mh, $ch);
                $unemployed_workers[] = $ch;
            }
        };
        $opts = array(
            CURLOPT_URL => '',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_ENCODING => ''
        );
        if (! empty($additional_curlopts)) {
            // i would have used array_merge(), but it does scary stuff with integer keys.. foreach() is easier to reason about
            foreach ($additional_curlopts as $key => $val) {
                $opts[$key] = $val;
            }
        }

        $i = 0;
        foreach ($requests as $k => $request) {$i++;
            Log::debug('req: '.$i);
            while (empty($unemployed_workers)) {
                $work();
            }
            $new_worker = array_pop($unemployed_workers);
            $opts[CURLOPT_URL] = $request['url'];
            $opts[CURLOPT_POST] =  1;
            $opts[CURLOPT_POSTFIELDS] = $request['options']['body'];
            if (! curl_setopt_array($new_worker, $opts)) {
                $errstr = "curl_setopt_array failed: " . curl_errno($new_worker) . ": " . curl_error($new_worker) . " " . var_export($opts, true);
                throw new RuntimeException($errstr);
            }
            $workers[(int) $new_worker] = $k;
            curl_multi_add_handle($mh, $new_worker);
        }
        while (count($workers) > 0) {
            $work();
        }
        foreach ($unemployed_workers as $unemployed_worker) {
            curl_close($unemployed_worker);
        }
        curl_multi_close($mh);
        //dump($ret);die;
        return $ret;
    }

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        
        // todo: de verificat daca vin conditii de plata/anulare
        $availabilityCollection = new AvailabilityCollection();

        $checkInDt = (new DateTime($filter->checkIn))->setTime(0, 0);
        $today = (new DateTime())->setTime(0, 0);
        if ($checkInDt <= $today) {
            return $availabilityCollection;
        }

        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            $availabilityCollection = $this->getHotelOffers($filter);
        } elseif($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER||$filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) { 
            $availabilityCollection = $this->getCharterOrTourOffers($filter);
        } 
        // elseif($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
        //     $availabilityCollection = $this->getTourOffers($filter);
        // }
        
        return $availabilityCollection;
    }

    public function getOfferPaymentPlans(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    {
        
        $data = $this->requestPaymentData($filter)->SearchResultDetails->Valuation;

        $payments = new OfferPaymentPolicyCollection();
        foreach ($data->PaymentPolicy->item ?? [] as $policy) {
            $payment = new OfferPaymentPolicy();
            $currency = new Currency();
            $currency->Code = $policy->Currency;
            $payment->Currency = $currency;

            $percent = ((float)$policy->Percent) / 100;
            $payment->Amount = $percent * (float) $filter->SuppliedPrice;

            $payment->PayAfter = date('Y-m-d');
            $payment->PayUntil = (string) $policy->To;
            $payments->add($payment);
        }
        
        return $payments;
    }

    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    {
        TravelioValidator::make()->validateOfferCancelFeesFilter($filter);

        $data = $this->requestPaymentData($filter)->SearchResultDetails->Valuation;

        $cancelPolicies = new OfferCancelFeeCollection();

        foreach ($data->CancellationPolicy->item as $policy) {

            $percent = ((float)$policy->Percent) / 100;

            $payment = new OfferCancelFee();
            $currency = new Currency();
            $currency->Code = $policy->Currency;
            $payment->Currency = $currency;

            $payment->Price = $percent * (float) $filter->SuppliedPrice;

            $payment->DateStart = (string) $policy->From;
            $payment->DateEnd = (string) $policy->To;
            $cancelPolicies->add($payment);
        }
        return $cancelPolicies;
    }

    private function requestPaymentData(CancellationFeeFilter|PaymentPlansFilter $filter): SimpleXMLElement|bool
    {
        $httpClient = HttpClient::create();
        $requestArr = [
            'root' => [
                'head' => [
                    'auth' => [
                        'username' => $this->username,
                        'password' => $this->password
                    ],
                    'service' => 'SearchResultDetails'
                ],
                'main' => [
                    'SearchID' => $filter->OriginalOffer->InitialData,
                    'SearchResultData' => $filter->OriginalOffer->roomCombinationPriceDescription,
                    'Valuation' => 1,
                ]
            ]
        ];

        $requestXml = Utils::arrayToXmlString($requestArr);
        $options['body'] = $requestXml;
        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());
    
        $responseData = simplexml_load_string($responseObj->getContent());
        return $responseData;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {

        TravelioValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $httpClient = HttpClient::create();

        foreach ($filter->Items->first()->Passengers as $key => $passenger) {
            if (!empty($passenger->Firstname)) {
                $passengerTitle = (($passenger->IsAdult) ? 'ADT' : 'CHD');
                
                $touristsData[] = [
                    'turist' =>
                    [
                        'nr' => ($key + 1),
                        'titlu' => $passengerTitle,
                        'nume' => $passenger->Lastname,
                        'prenume' => $passenger->Firstname,
                        'data_nasterii' => $passenger->BirthDate,
                        'sex' => $passenger->Gender === 'male' ? 'M' : 'F',
                        'email' => $filter->AgencyDetails->Email
                    ]
                ];
            }
		}
        $roomData = [
			'item' => [
				'room_info' => [
					'id_camera' => $filter->Items->first()->Room_Type_InTourOperatorId,
					'id_pensiune' => $filter->Items->first()->Board_Def_InTourOperatorId
				],
				$touristsData
			]
		];

        $touristsData[0]['turist']['telefon'] = str_replace('+', '', $filter->BillingTo->Phone);
        
        $titular = $touristsData[0]['turist'];

        $bookingDataArr = json_decode($filter->Items->first()->Offer_bookingDataJson, true);

        $requestArr = [
            'root' => [
                'head' => [
                    'auth' => [
                        'username' => $this->username,
                        'password' => $this->password
                    ],
                    'service' => 'Reservation'
                ],
                'main' => [
                    'v2' => 1,
                    'titular' => $titular,
                    'id_hotel' => $bookingDataArr['id_hotel'],
                    'id_pachet' => $filter->Items->first()->Offer_packageId,
                    'data_start' => $filter->Items->first()->Room_CheckinAfter,
                    'data_sfarsit' => $filter->Items->first()->Room_CheckinBefore,
                    'nr_camere' => 1,
                    'numar_turisti' => count($filter->Items->first()->Passengers),
                    'camera' => $roomData,
                ]
            ]
        ];

        if (isset($bookingDataArr['id_transport'])) {
            $requestArr['root']['main']['id_transport'] = $bookingDataArr['id_transport'];
        }

        $requestXml = Utils::arrayToXmlString($requestArr);
    
        $options['body'] = $requestXml;
        
        $responseObj = $httpClient->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $responseObj->getContent(), $responseObj->getStatusCode());
        $responseData = json_decode($responseObj->getContent(), true);

        if (!empty($responseData['error'])) {
            throw new Exception($responseData['error']);
        }

        $responseData = new SimpleXMLElement($responseObj->getContent());

		$order = new Booking();
        $order->Id = $responseData->Reservation->reservationID;
		
		return [$order, $responseObj->getContent()];
    }
}