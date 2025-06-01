<?php

namespace Integrations\Megatec;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\HotelFacility;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Filters\PaymentPlansFilter;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\HotelFacilitiesCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\RegionCollection;
use App\Support\HttpClient\Client\HttpClient;
use App\Support\HttpClient\Factory\RequestFactory;
use App\Support\Log;
use DateTime;
use DateTimeImmutable;
use Dom\XMLDocument;
use Exception;
use Fig\Http\Message\RequestMethodInterface;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\Validator;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use SimpleXMLElement;
use Utils\Utils;

class MegatecApiService extends AbstractApiService
{
    public function __construct(private HttpClient $client)
    {
        parent::__construct();
    }

    public function apiGetCountries(): CountryCollection
    {
        $requestArr = [
            'soapenv:Envelope' => [
                '@attributes' => [
                    'xmlns:soapenv' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    'xmlns:meg' => 'http://www.megatec.ru/'
                ],
                'soapenv:Body' => [
                    'meg:GetCountries' => []
                ]
            ]
        ];

        $xmlString = Utils::arrayToXml($requestArr);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $content = $response->getBody();
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $content, $response->getStatusCode());

        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetCountriesResponse
            ->GetCountriesResult;

        $ccMap = CountryCodeMap::getCountryCodeMap();

        $countries = new CountryCollection();
        foreach ($xml->Country as $countryXml) {
            $country = new Country();
            $country->Id = $countryXml->ID;
            $countryName = ucfirst(strtolower((string) $countryXml->Name));
            if (!isset($ccMap[$countryName])) {
                Log::warning($this->handle . ': country ' . $countryName . ' not found');
                continue;
            }
            $country->Code = $ccMap[$countryName];
            $country->Name = $countryXml->Name;
            $countries->put($country->Id, $country);
        }

        return $countries;
    }

    public function apiGetRegions(): RegionCollection
    {
        $countries = $this->apiGetCountries();
        $regions = new RegionCollection();

        foreach ($countries as $country) {
            $requestArr = [
                'soapenv_--_Envelope' => [
                    '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                    'soapenv_--_Body' => [
                        'meg_--_GetRegions' => [
                            'meg_--_countryKey' => $country->Id
                        ]
                    ]
                ]
            ];
            $xmlString = Utils::arrayToXmlString($requestArr);
            $xmlString = str_replace('_--_', ':', $xmlString);

            $options['body'] = $xmlString;
            $options['headers'] = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
            $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
            $content = $response->getBody();
            $xml = new SimpleXMLElement($content);
            $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
                ->Body->children('http://www.megatec.ru/')
                ->GetRegionsResponse
                ->GetRegionsResult;

            foreach ($xml->Region as $regionXml) {

                $region = new Region();
                $region->Country = $country;
                $region->Id = $regionXml->ID;
                $region->Name = $regionXml->Name;
                $regions->put($region->Id, $region);
            }
        }

        return $regions;
    }

    public function apiGetCities(?CitiesFilter $params = null): CityCollection
    {
        $countries = $this->apiGetCountries();
        $regions = $this->apiGetRegions();

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_GetCities' => [
                        'meg_--_countryKey' => -1,
                        'meg_--_regionKey' => -1
                    ]
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetCitiesResponse
            ->GetCitiesResult;

        $cities = new CityCollection();
        foreach ($xml->City as $cityXml) {
            $country = $countries->get((string)$cityXml->CountryID);
            if ($country === null) {
                continue;
            }

            $region = $regions->get((string)$cityXml->RegionID);

            $city = new City();
            $city->Country = $country;
            $city->County = $region;
            $city->Id = (string) $cityXml->ID;
            $city->Name = $cityXml->Name;
            $cities->put($city->Id, $city);
        }

        return $cities;
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        $cities = $this->apiGetCities();
        $hotelsCol = new HotelCollection();

        $resp = $this->client->request(RequestFactory::METHOD_GET, 'https://b2b.solvex.bg/ro/api/?limit=100000000000000');
        $content = $resp->getBody();
        $hotels = json_decode($content, true)['data'];

        foreach ($hotels as $hotel) {

            $description = $hotel['description'];

            if (is_array($hotel['notes'])) {
                foreach ($hotel['notes'] as $note) {
                    $description .= '<b>' . $note['title'] . '</b>' . $note['descripion'];
                }
            }

            $higic = new HotelImageGalleryItemCollection();

            if (is_array($hotel['images'])) {
                foreach ($hotel['images'] as $image) {
                    $higi = HotelImageGalleryItem::create($image['url']);
                    $higic->add($higi);
                }
            }
            $stars = (int) substr($hotel['il_description'], 0, strpos($hotel['il_description'], '*'));

            $hotelObj = Hotel::create(
                $hotel['il_id'],
                $hotel['il_hotelname'],
                $cities->get($hotel['city']['id']),
                $stars,
                $description,
                null,
                $hotel['lat'],
                $hotel['lon'],
                null,
                $higic
            );

            $hotelsCol->add($hotelObj);
        }

        return $hotelsCol;
    }

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        MegatecValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateIndividualOffersFilter($filter);

        $availabilities = new AvailabilityCollection();

        $token = $this->getToken();

        //$tariffs = $this->getTariffs();

        // $tariffsXml = simplexml_load_string($tariffs)
        //     ->children('http://schemas.xmlsoap.org/soap/envelope/')
        //     ->Body->children('')->GetTariffsResponse->GetTariffsResult;
        // $tariffsArr = [];
        //foreach ($tariffsXml->Tariff as $tariff) {
        //    $tariffsArr[] = [ 'meg_--_int' => (int)$tariff->ID ];
        //}
        $tariffsArr = [
            ['meg_--_int' => 0],
            ['meg_--_int' => 1993]
        ];

        $requestArrInner = [
            'meg_--_request' => [
                'meg_--_PageSize' => 100000,
                'meg_--_RowIndexFrom' => 0,
                'meg_--_DateFrom' => $filter->checkIn,
                'meg_--_DateTo' => $filter->checkOut,
                'meg_--_Pax' => (int) $filter->rooms->first()->adults + (int) $filter->rooms->first()->children,
                'meg_--_Tariffs' => $tariffsArr
            ]
        ];

        if ($filter->rooms->first()->children > 0) {
            foreach ($filter->rooms->first()->childrenAges as $age) {
                $age = (int) $age;

                if ($age === 0) {
                    $age = 1;
                }

                $requestArrInner['meg_--_request']['meg_--_Ages'][] = [
                    ['meg_--_int' => $age]
                ];
            }
        }

        if (!empty($filter->hotelId)) {
            $requestArrInner['meg_--_request']['meg_--_HotelKeys']['meg_--_int'] = $filter->hotelId;
        } elseif (!empty($filter->regionId)) {
            $requestArrInner['meg_--_request']['meg_--_RegionKeys']['meg_--_int'] = $filter->regionId;
        } elseif (!empty($filter->cityId)) {
            $requestArrInner['meg_--_request']['meg_--_CityKeys']['meg_--_int'] = $filter->cityId;
        }

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_SearchHotelServices' => [
                        'meg_--_guid' => $token,
                        $requestArrInner
                    ]
                ]
            ]
        ];

        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);

        $fault = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Fault->children();

        if (!empty($fault->faultstring)) {
            Log::warning($this->handle . ': ' . (string) $fault->faultstring);
            return $availabilities;
        }

        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->SearchHotelServicesResponse
            ->SearchHotelServicesResult;

        /*
        $i = 1;
        while ($xml === null) {
            // retry every 30 sec for 10 times
            sleep(30);
            $response = $client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
            $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
            $content = $response->getBody();
            $xml = new SimpleXMLElement($content);
            $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
                ->Body->children('http://www.megatec.ru/')
                ->SearchHotelServicesResponse
                ->SearchHotelServicesResult;

            if ($i >= 10) {
                return $availabilities;
            }
            $i++;
        }*/

        $resultT = $xml->Data->DataRequestResult->ResultTable;
        $result = $resultT->children('urn:schemas-microsoft-com:xml-diffgram-v1')
            ->diffgram->children()->DocumentElement->HotelServices;

        if ($result === null || count($result) === 0) {
            return $availabilities;
        }

        $mealMap = $this->getMealMap();

        foreach ($result as $hotelXml) {
            if ((string) $hotelXml->Rate !== 'EU') {

                throw new Exception('Currency error');
            }
            $availabilityStr = null;
            $quoteType = (string) $hotelXml->QuoteType;

            if ($quoteType === '1') {
                $availabilityStr = Offer::AVAILABILITY_YES;
            } else if ($quoteType === '0') {
                $availabilityStr = Offer::AVAILABILITY_ASK;
            } elseif ($quoteType === '2') {
                $availabilityStr = Offer::AVAILABILITY_NO;
            } else {
                throw new Exception('Wrong availability');
            }

            $bookingData = [
                'tariffId' => (string) $hotelXml->TariffId,
                'roomCategoryKey' => (string) $hotelXml->RcKey,
                'roomTypeKey' => (string) $hotelXml->RtKey,
                'roomAccomodationKey' => (string) $hotelXml->AcKey,
                'bookingPrice' => (float) $hotelXml->TotalCost
            ];

            $bookingDataJson = json_encode($bookingData);

            $offer = Offer::createIndividualOffer(
                $hotelXml->HotelKey,
                $hotelXml->RdKey . '-' . $hotelXml->TariffName,
                $hotelXml->RdKey . '-' . $hotelXml->TariffName,
                $hotelXml->RdName,
                $hotelXml->PnKey,
                $mealMap[(string) $hotelXml->PnKey],
                new DateTimeImmutable($filter->checkIn),
                new DateTimeImmutable($filter->checkOut),
                $filter->rooms->first()->adults,
                $filter->rooms->first()->childrenAges->toArray(),
                'EUR',
                (float) $hotelXml->TotalCost,
                (float) $hotelXml->TotalCost,
                (float) $hotelXml->TotalCost,
                (float) $hotelXml->AddHotsCost + (float) $hotelXml->AddHotsWithCosts,
                $availabilityStr,
                $hotelXml->TariffName,
                null,
                $bookingDataJson
            );

            /*
            $roomId = $hotelXml->RtKey;
            $mealId = (string) $hotelXml->PnKey;
            $price = (float) $hotelXml->TotalCost;
            $childrenAges = $filter->rooms->first()->childrenAges;

            $offer->Code = $hotelXml->HotelKey . '~' . $roomId . '~' . $mealId . '~' . $filter->checkIn 
                . '~' . $filter->checkOut . '~' . $price . '~' . $filter->rooms->first()->adults 
                . (count($childrenAges) > 0 ? '~' . implode('|', $childrenAges->toArray()) : '');
            
            $offer->CheckIn = $filter->checkIn;
            $currency = new Currency();
            if ((string) $hotelXml->Rate !== 'EU') {

                throw new Exception('Currency error');
            }

            $offer->Currency = $currency;
            $offer->Net = $price;
            $offer->InitialPrice = $price;
            $offer->Gross = $price;
            $offer->InitialData = (string) $hotelXml->RcKey;
            $offer->roomCombinationId = (string) $hotelXml->AcKey;
            $offer->Days = $filter->days;
            $offer->Comission = (float) $hotelXml->AddHotsCost + (float) $hotelXml->AddHotsWithCosts;
            $offer->roomCombinationPriceDescription = (float) $offer->Net / $offer->Days;
            $offer->bookingPrice = $price;

            $quoteType = (string) $hotelXml->QuoteType;

            $availabilityStr = null;
            if ($quoteType === '1') {
                $availabilityStr = Offer::AVAILABILITY_YES;
            } else if ($quoteType === '0') {
                $availabilityStr = Offer::AVAILABILITY_ASK;
            } elseif ($quoteType === '2') {
                $availabilityStr = Offer::AVAILABILITY_NO;
            } else {
                throw new Exception('Wrong availability');
            }

            $offer->Availability = $availabilityStr;

            $room = new Room();
            $room->Id = $roomId;

            $roomMerch = new RoomMerch();
            $roomMerch->Id = $roomId;
            $roomMerch->Code = $roomId;
            $roomMerch->Name = $hotelXml->RdName;
            $roomMerch->Title = $roomMerch->Name;

            $roomMerchType = new RoomMerchType;
            $roomMerchType->Id = $roomId;
            $roomMerchType->Title = $roomMerch->Title;

            $roomMerch->Type = $roomMerchType;
            $room->Merch = $roomMerch;
            $room->CheckinAfter = $filter->checkIn;
            $room->CheckinBefore = $filter->checkOut;
            $room->Currency = $offer->Currency;
            $room->Availability = $offer->Availability;
            $room->InfoTitle = $hotelXml->TariffName;

            $rooms = new RoomCollection([$room]);

            $offer->Rooms = $rooms;
            $offer->Item = $room;
            $mealItem = new MealItem();
            $mealItem->Currency = $offer->Currency;

            $meal = $mealMap[$mealId];

            $mealItemMerch = new MealMerch();
            $mealItemMerch->Id = $mealId;
            $mealItemMerch->Title = $meal;

            $mealType = new MealMerchType();
            $mealType->Id = $mealId;
            $mealType->Title = $meal;

            $mealItemMerch->Type = $mealType;
            $mealItem->Merch = $mealItemMerch;
            $offer->MealItem = $mealItem;

            // departure transport item merch
            $departureTransportItemMerch = new TransportMerch();
            $departureTransportItemMerch->Title = 'CheckIn: ' . $filter->checkIn;

            // DepartureTransportItem Return Merch
            $departureTransportItemReturnMerch = new TransportMerch();
            $departureTransportItemReturnMerch->Title = 'CheckOut: ' . $filter->checkOut;

            // DepartureTransportItem Return
            $departureTransportItemReturn = new ReturnTransportItem();
            $departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
            $departureTransportItemReturn->Currency = $currency;
            $departureTransportItemReturn->DepartureDate = $filter->checkOut;
            $departureTransportItemReturn->ArrivalDate = $filter->checkOut;

            // DepartureTransportItem
            $departureTransportItem = new DepartureTransportItem();
            $departureTransportItem->Merch = $departureTransportItemMerch;
            $departureTransportItem->Currency = $currency;
            $departureTransportItem->DepartureDate = $filter->checkIn;
            $departureTransportItem->ArrivalDate = $filter->checkIn;
            $departureTransportItem->Return = $departureTransportItemReturn;

            $offer->DepartureTransportItem = $departureTransportItem;

            $offer->ReturnTransportItem = $departureTransportItemReturn;
*/
            // search in collection
            $id = $hotelXml->HotelKey;
            $availability = $availabilities->get($id);
            if ($availability === null) {
                $availability = new Availability();
                $availability->Id = $hotelXml->HotelKey;
                if ($filter->showHotelName) {
                    $availability->Name = $hotelXml->HotelName;
                }
                $offers = new OfferCollection();
                $offers->add($offer);
                $availability->Offers = $offers;
            } else {
                // add offer to offers
                $offers = $availability->Offers;
                $offers->add($offer);
            }

            $availabilities->put($availability->Id, $availability);
        }
        return $availabilities;
    }

    private function getMealMap(): array
    {
        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_GetPansions' => []
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $this->client->setExtraOptions([CURLOPT_SSL_VERIFYPEER => false]);
        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetPansionsResponse
            ->GetPansionsResult;

        $map = [];
        foreach ($xml->Pansion as $pansion) {
            $map[(string) $pansion->ID] = (string) $pansion->Name;
        }

        return $map;
    }

    private function getToken(): string
    {
        Validator::make()->validateUsernameAndPassword($this->post);

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_Connect' => [
                        'meg_--_login' => $this->username,
                        'meg_--_password' => $this->password
                    ]
                ]
            ]
        ];
        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->ConnectResponse
            ->ConnectResult;

        $token = (string) $xml;
        return $token;
    }

    private function convertIntoPayment(OfferCancelFeeCollection $offerCancelFeeCollection): OfferPaymentPolicyCollection
    {
        $paymentsList = new OfferPaymentPolicyCollection();

        $i = 0;
        $prices = 0;

        /** @var OfferCancelFee $cancelFee */
        foreach ($offerCancelFeeCollection as $cancelFee) {
            $payment = new OfferPaymentPolicy();
            $payment->Currency = $cancelFee->Currency;

            if ($i === 0) {
                $payment->PayAfter = date('Y-m-d');
            } else {
                $payment->PayAfter = (new DateTime($offerCancelFeeCollection->get($i - 1)->DateStart))->format('Y-m-d');
            }
            $payment->Amount = $cancelFee->Price - $prices;

            $prices += $payment->Amount;

            $payUntilDT = (new DateTime($cancelFee->DateStart))->modify('- 1 day');
            $todayDT = new DateTime();

            $payment->PayUntil = $todayDT > $payUntilDT ? $todayDT->format('Y-m-d') : $payUntilDT->format('Y-m-d');

            $paymentsList->add($payment);
            $i++;
        }

        return $paymentsList;
    }

    public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    {
        MegatecValidator::make()->validateOfferCancelFeesFilter($filter);

        $fees = new OfferCancelFeeCollection();

        $bookingDataArr = json_decode($filter->OriginalOffer->bookingDataJson, true);

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_GetCancellationPolicyInfoWithPenalty' => [
                        'meg_--_guid' => $this->getToken(),
                        'meg_--_dateFrom' => $filter->CheckIn,
                        'meg_--_dateTo' => $filter->CheckOut,
                        'meg_--_HotelKey' => $filter->Hotel->InTourOperatorId,
                        'meg_--_Pax' => $filter->Rooms->first()->adults,
                        'meg_--_RoomTypeKey' =>  $bookingDataArr['roomTypeKey'],
                        'meg_--_PansionKey' => $filter->OriginalOffer->MealItem->Merch->Id,
                        'meg_--_RoomCategoryKey' => $bookingDataArr['roomCategoryKey']
                    ]
                ]
            ]
        ];

        if ($filter->Rooms->first()->children > 0) {
            foreach ($filter->Rooms->first()->childrenAges as $age) {
                $requestArr['soapenv_--_Envelope']['soapenv_--_Body']['meg_--_GetCancellationPolicyInfoWithPenalty']['meg_--_Ages'][] = ['meg_--_int' => $age];
            }
        }

        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetCancellationPolicyInfoWithPenaltyResponse
            ->GetCancellationPolicyInfoWithPenaltyResult;

        $feesXml = $xml->Data;

        $tariffId = $bookingDataArr['tariffId'];

        $selectedPol = null;

        foreach ($feesXml->CancellationPolicyInfoWithPenalty as $cpiwp) {
            $firstTariffId = (string) $cpiwp->PolicyData->CancellationPolicyWithPenaltyValue->TariffId;
            if ($tariffId == $firstTariffId) {
                $selectedPol = $cpiwp;
            }
        }

        $feesXml = $selectedPol->PolicyData;

        $pricePerNight = (float) $filter->SuppliedPrice / (int) $filter->Duration;

        $today = date('Y-m-d');

        $i = 0;

        foreach ($feesXml->CancellationPolicyWithPenaltyValue as $feeXml) {
            $i++;

            if ((string) $feeXml->PenaltyValue === '0') {
                continue;
            }

            $fee = new OfferCancelFee();

            $feeStartDateDT = (new DateTime((string) $feeXml->DateFrom))->setTime(0, 0);
            $todayDT = (new DateTime())->setTime(0, 0);

            $feeStartDate = $feeStartDateDT > $todayDT ? $feeStartDateDT->format('Y-m-d') : $todayDT->format('Y-m-d');
            $feeEndDate = (new DateTime((string) $feeXml->DateTo))->format('Y-m-d');

            $dateEnd = ((string) $feeXml->DateTo ?: $filter->CheckIn);

            $code = (string) $feeXml->Currency;
            if ($code === 'EU') {
                $code = 'EUR';
            } else {
                throw new Exception('invalid cp currency for ' . json_encode($filter));
            }
            $currency = new Currency();
            $currency->Code = $code;

            $policyIsOnCheckIn = false;
            if (!empty($feeStartDate) && $feeStartDate === $filter->CheckIn) {
                $policyIsOnCheckIn = true;
            }

            if (!$policyIsOnCheckIn) {

                $penaltyValue = (float) $feeXml->PenaltyValue;
                if ((string) $feeXml->IsPercent === 'true') {
                    $fee->Price = $filter->SuppliedPrice * $penaltyValue / 100.0;
                } else {
                    $days = (int) $filter->Duration;
                    if ($penaltyValue > $days) {
                        continue;
                    }
                    $fee->Price = $pricePerNight * $penaltyValue;

                    //$fee->Price = (float)$feeXml->PenaltyTotal;
                }
            } else {
                $fee->Price = $filter->SuppliedPrice;
            }

            $fee->Currency = $currency;
            $dateStart = $feeStartDate ?: $today;
            $fee->DateStart = (new DateTime($dateStart))->format('Y-m-d');

            $fee->DateEnd = (new DateTime($dateEnd))->format('Y-m-d');
            $fees->add($fee);

            if ($feeEndDate === $filter->CheckIn) {
                break;
            }

            // last policy
            // if ($i === count($feesXml->CancellationPolicyWithPenaltyValue)) {
            //     // check the price

            //     if ($fee->Price !== (float) $filter->SuppliedPrice) {
            //         $fee = new OfferCancelFee();
            //         $fee->Currency = $currency;
            //         $fee->DateEnd = (new DateTime($filter->CheckIn))->format('Y-m-d');
            //         $fee->DateStart = $dateEnd;
            //         $fee->Price = (float) $filter->SuppliedPrice;
            //         $fees->add($fee);
            //     }
            // }
        }

        return $fees;
    }

    public function getOfferPaymentPlans(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    {
        $filter = new CancellationFeeFilter($filter->toArray());
        return $this->convertIntoPayment($this->apiGetOfferCancelFees($filter));
    }

    /*
    public function getOfferPaymentPlans_(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    {
        MegatecValidator::make()->validateOfferPaymentPlansFilter($filter);

        $fees = new OfferPaymentPolicyCollection();

        $bookingDataArr = json_decode($filter->OriginalOffer->bookingDataJson, true);

        $roomId = $filter->OriginalOffer->Rooms->first()->Id;

        $roomId = substr($roomId, 0, strpos($roomId, '-'));

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_GetCancellationPolicyInfoWithPenalty' => [
                        'meg_--_guid' => $this->getToken(),
                        'meg_--_dateFrom' => $filter->CheckIn,
                        'meg_--_dateTo' => $filter->CheckOut,
                        'meg_--_HotelKey' => $filter->Hotel->InTourOperatorId,
                        'meg_--_Pax' => $filter->Rooms->first()->adults,
                        'meg_--_RoomTypeKey' => $roomId,
                        'meg_--_PansionKey' => $filter->OriginalOffer->MealItem->Merch->Id,
                        'meg_--_RoomCategoryKey' => $bookingDataArr['roomCategoryKey']
                    ]
                ]
            ]
        ];

        if ($filter->Rooms->first()->children > 0) {
            foreach ($filter->Rooms->first()->childrenAges as $age) {
                $requestArr['soapenv_--_Envelope']
                    ['soapenv_--_Body']
                        ['meg_--_GetCancellationPolicyInfoWithPenalty']
                            ['meg_--_Ages'][] = ['meg_--_int' => $age];
            }
        }

        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $client = HttpClient::create();
        $response = $client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetCancellationPolicyInfoWithPenaltyResponse
            ->GetCancellationPolicyInfoWithPenaltyResult;
        
        $feesXml = $xml->Data;


        $tariffId = $bookingDataArr['tariffId'];

        $selectedPol = null;

        foreach ($feesXml->CancellationPolicyInfoWithPenalty as $cpiwp) {
            $firstTariffId = (string) $cpiwp->PolicyData->CancellationPolicyWithPenaltyValue->TariffId;
            if ($tariffId == $firstTariffId) {
                $selectedPol = $cpiwp;
            }
        }

        $feesXml = $selectedPol->PolicyData;

        $pricePerNight = (float) $filter->SuppliedPrice / (int) $filter->Duration;

        $todayDT = (new DateTime())->setTime(0,0);
        $today = $todayDT->format('Y-m-d');

        $i = 0;
        $price = 0;
        foreach ($feesXml->CancellationPolicyWithPenaltyValue as $feeXml) {
            $i++;
            if ((string) $feeXml->PenaltyValue === '0') {
                continue;
            }

            if (((string)$feeXml->TariffId) !== $tariffId) {
                throw new Exception('policy error');
            }

            $fee = new OfferPaymentPolicy();
            $penaltyValue = (float) $feeXml->PenaltyValue;

            $dateStart = (new DateTime((string)$feeXml->DateFrom ?: $today));
            $dateStart->modify('-1 days')->setTime(0,0);
            
            $fee->PayAfter = ($dateStart)->format('Y-m-d');

            $dateEndDT = (new DateTime(((string) $feeXml->DateTo ?: $filter->CheckIn)));
            $dateEndDT->modify('-1 days')->setTime(0,0);

            if ((string) $feeXml->IsPercent === 'true') {
                $feePrice = $filter->SuppliedPrice * $penaltyValue/100.0;
            } else {
 
                $days = (int) $filter->Duration;
                if ($penaltyValue > $days) {
                    continue;
                }
                //$feePrice = $pricePerNight * $penaltyValue;
                $feePrice = (float) $feeXml->PenaltyTotal;
            }
            
            if ($i === count($feesXml->CancellationPolicyWithPenaltyValue)) {
                $fee->Amount =  $filter->SuppliedPrice - $price;
            } else {
                $fee->Amount = $feePrice - $price;
            }
            if ($fee->Amount == 0) {
                continue;
            }

            if ($dateEndDT < $todayDT) {
                // add to price and continue
                $price =  $fee->Amount;
                continue;
            }

            $dateEnd = $dateEndDT->format('Y-m-d');

            $fee->PayUntil = $dateEnd;
            $price =  $fee->Amount;

            $currency = new Currency();
            $code = (string) $feeXml->Currency;
            if ($code === 'EU') {
                $code = 'EUR';
            } else {
                throw new Exception('invalid cp currency for ' . json_encode($filter));
            }
            $currency->Code = $code;
            $fee->Currency = $currency;
            
            $fees->add($fee);
        }

        return $fees;
    }*/

    private function getRatesMap(): array
    {
        $file = 'rates-map';
        $mapJson = Utils::getFromCache($this->handle, $file);
        if ($mapJson === null) {
            $requestArr = [
                'soapenv_--_Envelope' => [
                    '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                    'soapenv_--_Body' => [
                        'meg_--_GetRates' => []
                    ]
                ]
            ];
            $xmlString = Utils::arrayToXmlString($requestArr);
            $xmlString = str_replace('_--_', ':', $xmlString);

            $options['body'] = $xmlString;
            $options['headers'] = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
            $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());

            $content = $response->getBody();
            $xml = new SimpleXMLElement($content);
            $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
                ->Body->children('http://www.megatec.ru/')
                ->GetRatesResponse
                ->GetRatesResult;
            $map = [];
            foreach ($xml->Rate as $rate) {
                $map[(string) $rate->Unicode] = (string) $rate->ID;
            }

            Utils::writeToCache($this->handle, $file, json_encode($map));
        } else {
            $map = json_decode($mapJson, true);
        }
        return $map;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        MegatecValidator::make()
            ->validateBookHotelFilter($filter);

        $rates = $this->getRatesMap();

        $today = new DateTimeImmutable();
        $tourists = [];
        $touristId = -1;
        /** @var Passenger $passenger */
        foreach ($filter->Items->first()->Passengers as $passenger) {
            $birthDT = new DateTimeImmutable($passenger->BirthDate);
            $age = $today->diff($birthDT)->y;
            $tourists[] = [
                'Tourist' => [
                    '[FirstNameLat]' => $passenger->Firstname,
                    '[SurNameLat]' => $passenger->Lastname,
                    '[BirthDate]' => $passenger->BirthDate,
                    '[Sex]' => $passenger->IsAdult ? ($passenger->Gender === 'female' ? 'Female' : 'Male') : ($age < 2 ? 'Infant' : 'Child'),
                    '[AgeType]' => $passenger->IsAdult ? 'Adult' : ($age < 2 ? 'Infant' : 'Child'),
                    '[ID]' => $touristId
                ]
            ];
            $touristId--;
        }

        $servicesBooking = [];
        $servicesCount = 1;
        for ($tId = -1; $tId >= -count($tourists); $tId--) {
            for ($sId = -1; $sId >= -$servicesCount; $sId--) {
                $servicesBooking['TouristServices'][] = [
                    'TouristService' => [
                        'ID' => 0,
                        'Name' => [],
                        'TouristID' => $tId,
                        'ServiceID' => -1
                    ]
                ];
            }
        }

        $bookingDataArr = json_decode($filter->Items->first()->Offer_bookingDataJson, true);

        $startDate = new DateTime($filter->Items->first()->Room_CheckinAfter);
        $endDate = new DateTime($filter->Items->first()->Room_CheckinBefore);
        $days = $endDate->diff($startDate)->d;

        $requestArr = [
            'SOAP-ENV_--_Envelope' => [
                '[xmlns_--_SOAP-ENV]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_xsi]' => 'http://www.w3.org/2001/XMLSchema-instance',
                '[xmlns_--_xsd]' => 'http://www.w3.org/2001/XMLSchema',
                '[xmlns]' => 'http://www.megatec.ru/',
                'SOAP-ENV_--_Body' => [
                    'CreateReservation' => [
                        'guid' => $this->getToken(),
                        'reserv' => [
                            'Rate' => [
                                'ID' => $rates['EUR']
                            ],
                            $servicesBooking,
                            'Tourists' => $tourists,
                            'Services' => [
                                [
                                    'Service' => [
                                        '[xsi_--_type]' => 'HotelService',
                                        'Hotel' => [
                                            'ID' => $filter->Items->first()->Hotel->InTourOperatorId
                                        ],
                                        'Room' => [
                                            'RoomTypeID' => $bookingDataArr['roomTypeKey'],
                                            'RoomCategoryID' => $bookingDataArr['roomCategoryKey'],
                                            'RoomAccomodationID' => $bookingDataArr['roomAccomodationKey']
                                        ],
                                        'PansionID' => $filter->Items->first()->Board_Def_InTourOperatorId,
                                        'AdditionalParams' => [
                                            'ParameterPair' => [
                                                '[Key]' => 'Tariff',
                                                'Value' => [
                                                    '[xsi_--_type]' => 'xsd:int',
                                                    $bookingDataArr['tariffId']
                                                ]
                                            ]
                                        ],
                                        'Duration' => $days,
                                        'StartDate' => $filter->Items->first()->Room_CheckinAfter,
                                        'NMen' => $filter->Params->Adults->first(),
                                        'ID' => -1
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $soapAction = 'http://www.megatec.ru/CreateReservation';

        $options['body'] = $xmlString;
        $options['headers'] = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => $soapAction
        ];

        $response = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl, $options);
        $this->showRequest(RequestFactory::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();

        $xml = new SimpleXMLElement($content);

        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->CreateReservationResponse
            ->CreateReservationResult;

        $booking = new Booking();
        $offerPrice = $bookingDataArr['bookingPrice'];
        $bookingPrice = (string) $xml->Brutto;

        if ($offerPrice != $bookingPrice) {
            return [$booking, 'Prices do not match. Response:' . $content];
        }
        if (!empty($xml->Name)) {
            $booking->Id = $xml->Name;
        }

        return [$booking, $content];
    }

    public function apiTestConnection(): bool
    {
        $token = $this->getToken();
        if (!empty($token) && $token !== 'Connection result code: -1. Invalid login or password' && strlen($token) === 36) {
            return true;
        }
        return false;
    }
}
