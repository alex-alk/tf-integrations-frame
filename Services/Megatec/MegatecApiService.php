<?php

namespace Services\Megatec;

use DateTime;
use DateTimeImmutable;
use Exception;
use HttpClient\HttpClient;
use HttpClient\Message\Request;
use Logger\Log;
use Models\Availability;
use Models\Booking;
use Models\City;
use Models\Country;
use Models\Hotel;
use Models\HotelImage;
use Models\Offer;
use Models\OfferCancelFee;
use Models\OfferPaymentPolicy;
use Models\Region;
use Psr\Http\Message\ServerRequestInterface;
use Services\IntegrationSupport\AbstractApiService;
use Services\IntegrationSupport\CountryCodeMap;
use SimpleXMLElement;
use Utils\Utils;

class MegatecApiService extends AbstractApiService
{
    public function __construct(private ServerRequestInterface $serverRequest, private HttpClient $client)
    {
        parent::__construct($serverRequest);
    }

    public function apiGetCountries(): array
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

        $body = $xmlString;
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
        $content = $response->getBody();
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $content, $response->getStatusCode());

        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetCountriesResponse
            ->GetCountriesResult;

        $ccMap = CountryCodeMap::getCountryCodeMap();

        $countries = [];
        foreach ($xml->Country as $countryXml) {
            $countryName = ucfirst(strtolower((string) $countryXml->Name));
            if (!isset($ccMap[$countryName])) {
                Log::warning($this->handle . ': country ' . $countryName . ' not found');
                continue;
            }

            $country = new Country($countryXml->ID, $ccMap[$countryName], $countryName);

            $countries[(string) $countryXml->ID] = $country;
        }

        return $countries;
    }

    public function apiGetRegions(): array
    {
        $countries = $this->apiGetCountries();
        $regions = [];

        foreach ($countries as $country) {
            $requestArr = [
                'soapenv_--_Envelope' => [
                    '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                    'soapenv_--_Body' => [
                        'meg_--_GetRegions' => [
                            'meg_--_countryKey' => $country->getId()
                        ]
                    ]
                ]
            ];
            $xmlString = Utils::arrayToXmlString($requestArr);
            $xmlString = str_replace('_--_', ':', $xmlString);

            $body = $xmlString;
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
            $content = $response->getBody();
            $xml = new SimpleXMLElement($content);
            $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
                ->Body->children('http://www.megatec.ru/')
                ->GetRegionsResponse
                ->GetRegionsResult;

            foreach ($xml->Region as $regionXml) {

                $region = new Region($regionXml->ID, $regionXml->Name, $country);
                $regions[(string) $regionXml->ID] = $region;
            }
        }

        return $regions;
    }

    public function apiGetCities(): array
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

        $body = $xmlString;
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->GetCitiesResponse
            ->GetCitiesResult;

        $cities = [];
        foreach ($xml->City as $cityXml) {
            $country = $countries[(string)$cityXml->CountryID];
            if ($country === null) {
                continue;
            }

            $region = $regions[(string)$cityXml->RegionID];

            $city = new City((string) $cityXml->ID, $cityXml->Name, $country, $region);
            $cities[(string) $cityXml->ID] = $city;
        }

        return $cities;
    }

    public function apiGetHotels(): array
    {
        $cities = $this->apiGetCities();
        $hotelsCol = [];

        $resp = $this->client->request(Request::METHOD_GET, 'https://b2b.solvex.bg/ro/api/?limit=100000000000000');
        $content = $resp->getBody();
        $hotels = json_decode($content, true)['data'];

        foreach ($hotels as $hotel) {

            $description = $hotel['description'];

            if (is_array($hotel['notes'])) {
                foreach ($hotel['notes'] as $note) {
                    $description .= '<b>' . $note['title'] . '</b>' . $note['descripion'];
                }
            }

            $images = [];

            if (is_array($hotel['images'])) {
                foreach ($hotel['images'] as $image) {
                    $higi = new HotelImage($image['url']);
                    $images[] = $higi;
                }
            }
            $stars = (int) substr($hotel['il_description'], 0, strpos($hotel['il_description'], '*'));

            $hotelObj = new Hotel(
                $hotel['il_id'],
                $hotel['il_hotelname'],
                $cities[$hotel['city']['id']],
                $stars,
                $description,
                $images,
                $hotel['lat'],
                $hotel['lon']
            );

            $hotelsCol[] = $hotelObj;
        }

        return $hotelsCol;
    }

    public function apiGetOffers(): array
    {
        $post = $this->serverRequest->getParsedBody();

        MegatecValidator::make()
            ->validateIndividualOffersFilter($post);

        $availabilities = [];

        $token = $this->getToken();

        $tariffsArr = [
            ['meg_--_int' => 0],
            ['meg_--_int' => 1993]
        ];

        $requestArrInner = [
            'meg_--_request' => [
                'meg_--_PageSize' => 100000,
                'meg_--_RowIndexFrom' => 0,
                'meg_--_DateFrom' => $post['args'][0]['checkIn'],
                'meg_--_DateTo' => $post['args'][0]['checkOut'],
                'meg_--_Pax' => (int) $post['args'][0]['rooms'][0]['adults'] + (int) $post['args'][0]['rooms'][0]['children'],
                'meg_--_Tariffs' => $tariffsArr
            ]
        ];

        if ($post['args'][0]['rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $age) {
                $age = (int) $age;

                if ($age === 0) {
                    $age = 1;
                }

                $requestArrInner['meg_--_request']['meg_--_Ages'][] = [
                    ['meg_--_int' => $age]
                ];
            }
        }

        if (!empty($post['args'][0]['travelItemId'])) {
            $requestArrInner['meg_--_request']['meg_--_HotelKeys']['meg_--_int'] = $post['args'][0]['travelItemId'];
        } elseif (!empty($post['args'][0]['cityId'])) {
            $requestArrInner['meg_--_request']['meg_--_CityKeys']['meg_--_int'] = $post['args'][0]['cityId'];
        } elseif (!empty($post['args'][0]['regionId'])) {
            $requestArrInner['meg_--_request']['meg_--_RegionKeys']['meg_--_int'] = $post['args'][0]['regionId'];
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

        $body = $xmlString;
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();

        $xml = new SimpleXMLElement($content);

        $fault = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Fault->children();
        
        if (!empty($fault->faultstring) && ((string) $fault->faultstring) === html_entity_decode('Server was unable to process request. ---&gt; Invalid user or password (Invalid GUID)')) {
            // redo request
            $token = $this->cacheToken();

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

            $body = $xmlString;

            $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
            $content = $response->getBody();

            $xml = new SimpleXMLElement($content);

            $fault = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
                ->Body->children('http://schemas.xmlsoap.org/soap/envelope/')
                ->Fault->children();
        }

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
            $response = $client->request(Request::METHOD_POST, $this->apiUrl, $options);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
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
                new DateTimeImmutable($post['args'][0]['checkIn']),
                new DateTimeImmutable($post['args'][0]['checkOut']),
                $post['args'][0]['rooms'][0]['adults'],
                $post['args'][0]['rooms'][0]['childrenAges'],
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

            // search in collection
            $id = (string) $hotelXml->HotelKey;
            $availability = $availabilities[$id] ?? null;
            if ($availability === null) {
                
                $name = null;
                if ($post['args'][0]['showHotelName']) {
                    $name = $hotelXml->HotelName;
                }
                
                $offers = [];
                $offers[] = $offer;
                $availability = new Availability($hotelXml->HotelKey, $offers, $name);
            } else {
                // add offer to offers
                $offers = $availability->addOffer($offer);
            }

            $availabilities[$availability->getId()] = $availability;
        }
        return $availabilities;
    }

    private function getMealMap(): array
    {
        $file = 'mealMap';

        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

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

            $body = $xmlString;
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            $this->client->setExtraOptions([CURLOPT_SSL_VERIFYPEER => false]);
            $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
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
            Utils::writeToCache($this, $file, json_encode($map));

            return $map;
        } else {
            return json_decode($json, true);
        }
    }

    private function cacheToken(): string
    {
        $file = 'token-' . $this->username;

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

        $body = $xmlString;
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);
        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->ConnectResponse
            ->ConnectResult;

        $token = (string) $xml;

        if ($token !== 'Connection result code: -1. Invalid login or password' && strlen($token) === 36) {
            Utils::writeToCache($this, $file, $token);
            return $token;
        } else {
            return '';
        }
    }

    private function getToken(): string
    {
        $file = 'token-' . $this->username;

        $json = Utils::getFromCache($this, $file);

        if ($json === null) {

            $token = $this->cacheToken();
            return $token;
        } else {
            return $json;
        }
    }

    /**
     * @param OfferCancelFee[] $offerCancelFeeCollection
     */
    private function convertIntoPayment(array $offerCancelFeeCollection): array
    {
        $paymentsList = [];

        $i = 0;
        $prices = 0;

        foreach ($offerCancelFeeCollection as $cancelFee) {

            $payAfter = '';
            if ($i === 0) {
                $payAfter = date('Y-m-d');
            } else {
                $payAfter = (new DateTime($offerCancelFeeCollection[$i - 1]->getDateStart()))->format('Y-m-d');
            }
            $amount = $cancelFee->getPrice() - $prices;

            $prices += $amount;

            $payUntilDT = (new DateTime($cancelFee->getDateStart()))->modify('- 1 day');
            $todayDT = new DateTime();

            $payUntil = $todayDT > $payUntilDT ? $todayDT->format('Y-m-d') : $payUntilDT->format('Y-m-d');
            
            $payment = new OfferPaymentPolicy($payAfter, $payUntil, $amount, $cancelFee->getCurrency());
            
            $paymentsList[] = $payment;
            $i++;
        }

        return $paymentsList;
    }

    /**
     * @return OfferCancelFee[]
     */
    public function apiGetOfferCancelFees(): array
    {
        $post = $this->serverRequest->getParsedBody();

        MegatecValidator::make()->validateOfferCancelFeesFilter($post);

        $fees = [];

        $bookingDataArr = json_decode($post['args'][0]['OriginalOffer']['bookingDataJson'], true);

        $token = $this->getToken();

        $requestArr = [
            'soapenv_--_Envelope' => [
                '[xmlns_--_soapenv]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_meg]' => 'http://www.megatec.ru/',
                'soapenv_--_Body' => [
                    'meg_--_GetCancellationPolicyInfoWithPenalty' => [
                        'meg_--_guid' => $token,
                        'meg_--_dateFrom' => $post['args'][0]['CheckIn'],
                        'meg_--_dateTo' => $post['args'][0]['CheckOut'],
                        'meg_--_HotelKey' => $post['args'][0]['Hotel']['InTourOperatorId'],
                        'meg_--_Pax' => $post['args'][0]['Rooms'][0]['adults'],
                        'meg_--_RoomTypeKey' =>  $bookingDataArr['roomTypeKey'],
                        'meg_--_PansionKey' => $post['args'][0]['OriginalOffer']['MealItem']['Merch']['Id'],
                        'meg_--_RoomCategoryKey' => $bookingDataArr['roomCategoryKey']
                    ]
                ]
            ]
        ];

        if ($post['args'][0]['Rooms'][0]['children'] > 0) {
            foreach ($post['args'][0]['Rooms'][0]['childrenAges'] as $age) {
                $requestArr['soapenv_--_Envelope']['soapenv_--_Body']['meg_--_GetCancellationPolicyInfoWithPenalty']['meg_--_Ages'][] = ['meg_--_int' => $age];
            }
        }

        $xmlString = Utils::arrayToXmlString($requestArr);
        $xmlString = str_replace('_--_', ':', $xmlString);

        $body = $xmlString;
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8'
        ];

        $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());

        $content = $response->getBody();
        $xml = new SimpleXMLElement($content);

        $fault = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Fault->children();

        if (!empty($fault->faultstring) && ((string) $fault->faultstring) === html_entity_decode('Server was unable to process request. ---&gt; Invalid user or password (Invalid GUID)')) {
            // redo request
            $token = $this->cacheToken();
            $requestArr['soapenv_--_Envelope']['soapenv_--_Body']['meg_--_GetCancellationPolicyInfoWithPenalty']['meg_--_guid'] = $token;
        
            $xmlString = Utils::arrayToXmlString($requestArr);
            $xmlString = str_replace('_--_', ':', $xmlString);

            $body = $xmlString;
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());

            $content = $response->getBody();
            $xml = new SimpleXMLElement($content);
        }

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

        $pricePerNight = (float) $post['args'][0]['SuppliedPrice'] / (int) $post['args'][0]['Duration'];

        $today = date('Y-m-d');

        $i = 0;

        foreach ($feesXml->CancellationPolicyWithPenaltyValue as $feeXml) {
            $i++;

            if ((string) $feeXml->PenaltyValue === '0') {
                continue;
            }

            $feeStartDateDT = (new DateTime((string) $feeXml->DateFrom))->setTime(0, 0);
            $todayDT = (new DateTime())->setTime(0, 0);

            $feeStartDate = $feeStartDateDT > $todayDT ? $feeStartDateDT->format('Y-m-d') : $todayDT->format('Y-m-d');
            $feeEndDate = (new DateTime((string) $feeXml->DateTo))->format('Y-m-d');

            $dateEnd = ((string) $feeXml->DateTo ?: $post['args'][0]['CheckIn']);

            $code = (string) $feeXml->Currency;
            if ($code === 'EU') {
                $code = 'EUR';
            } else {
                throw new Exception('invalid cp currency for ' . json_encode($post));
            }

            $policyIsOnCheckIn = false;
            if (!empty($feeStartDate) && $feeStartDate === $post['args'][0]['CheckIn']) {
                $policyIsOnCheckIn = true;
            }

            $price = 0;
            if (!$policyIsOnCheckIn) {

                $penaltyValue = (float) $feeXml->PenaltyValue;
                if ((string) $feeXml->IsPercent === 'true') {
                    $price = $post['args'][0]['SuppliedPrice'] * $penaltyValue / 100.0;
                } else {
                    $days = (int) $post['args'][0]['Duration'];
                    if ($penaltyValue > $days) {
                        continue;
                    }
                    $price = $pricePerNight * $penaltyValue;
                }
            } else {
                $price = $post['args'][0]['SuppliedPrice'];
            }

            $dateStart = $feeStartDate ?: $today;
            $dateStart = (new DateTime($dateStart))->format('Y-m-d');

            $dateEnd = (new DateTime($dateEnd))->format('Y-m-d');
            
            $fee = new OfferCancelFee($dateStart, $dateEnd, $price, $code);
            $fees[] = $fee;

            if ($feeEndDate === $post['args'][0]['CheckIn']) {
                break;
            }
        }

        return $fees;
    }

    public function apiGetOfferPaymentsPlan(): array
    {
        return $this->convertIntoPayment($this->apiGetOfferCancelFees());
    }

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

            $body = $xmlString;
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
            $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());

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

    public function apiDoBooking(): array
    {
        $post = $this->serverRequest->getParsedBody();

        MegatecValidator::make()
            ->validateBookHotelFilter($post);

        $rates = $this->getRatesMap();

        $today = new DateTimeImmutable();
        $tourists = [];
        $touristId = -1;
        /** @var Passenger $passenger */
        foreach ($post['args'][0]['Items'][0]['Passengers'] as $passenger) {
            $birthDT = new DateTimeImmutable($passenger['BirthDate']);
            $age = $today->diff($birthDT)->y;
            $tourists[] = [
                'Tourist' => [
                    '[FirstNameLat]' => $passenger['Firstname'],
                    '[SurNameLat]' => $passenger['Lastname'],
                    '[BirthDate]' => $passenger['BirthDate'],
                    '[Sex]' => $passenger['IsAdult'] ? ($passenger['Gender'] === 'female' ? 'Female' : 'Male') : ($age < 2 ? 'Infant' : 'Child'),
                    '[AgeType]' => $passenger['IsAdult'] ? 'Adult' : ($age < 2 ? 'Infant' : 'Child'),
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

        $bookingDataArr = json_decode($post['args'][0]['Items'][0]['Offer_bookingDataJson'], true);

        $startDate = new DateTime($post['args'][0]['Items'][0]['Room_CheckinAfter']);
        $endDate = new DateTime($post['args'][0]['Items'][0]['Room_CheckinBefore']);
        $days = $endDate->diff($startDate)->d;

        $token = $this->cacheToken();

        $requestArr = [
            'SOAP-ENV_--_Envelope' => [
                '[xmlns_--_SOAP-ENV]' => 'http://schemas.xmlsoap.org/soap/envelope/',
                '[xmlns_--_xsi]' => 'http://www.w3.org/2001/XMLSchema-instance',
                '[xmlns_--_xsd]' => 'http://www.w3.org/2001/XMLSchema',
                '[xmlns]' => 'http://www.megatec.ru/',
                'SOAP-ENV_--_Body' => [
                    'CreateReservation' => [
                        'guid' => $token,
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
                                            'ID' => $post['args'][0]['Items'][0]['Hotel']['InTourOperatorId']
                                        ],
                                        'Room' => [
                                            'RoomTypeID' => $bookingDataArr['roomTypeKey'],
                                            'RoomCategoryID' => $bookingDataArr['roomCategoryKey'],
                                            'RoomAccomodationID' => $bookingDataArr['roomAccomodationKey']
                                        ],
                                        'PansionID' => $post['args'][0]['Items'][0]['Board_Def_InTourOperatorId'],
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
                                        'StartDate' => $post['args'][0]['Items'][0]['Room_CheckinAfter'],
                                        'NMen' => $post['args'][0]['Params']['Adults'][0],
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

        $body = $xmlString;
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => $soapAction
        ];

        $response = $this->client->request(Request::METHOD_POST, $this->apiUrl, $body, $headers);
        $this->showRequest(Request::METHOD_POST, $this->apiUrl, $body, $headers, $response->getBody(), $response->getStatusCode());
        $content = $response->getBody();

        $xml = new SimpleXMLElement($content);

        $xml = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')
            ->Body->children('http://www.megatec.ru/')
            ->CreateReservationResponse
            ->CreateReservationResult;


        $offerPrice = $bookingDataArr['bookingPrice'];
        $bookingPrice = (string) $xml->Brutto;

        if ($offerPrice != $bookingPrice) {
            return [new Booking(null), 'Prices do not match. Response:' . $content];
        }

        $id = null;
        if (!empty($xml->Name)) {
            $id = $xml->Name;
        }
        $booking = new Booking($id);

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
