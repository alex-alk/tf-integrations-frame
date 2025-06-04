<?php

namespace Integrations\Etg;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Filters\PaymentPlansFilter;
use App\Handles;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
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

class EtgApiService extends AbstractApiService
{

    public function apiGetCountries(): CountryCollection
    {
        $countries = new CountryCollection();

        $cities = $this->apiGetCities();

        foreach ($cities as $cityResp) {
            $country = $cityResp->Country;
            $countries->put($country->Id, $country);
        }

        return $countries;
    }

    public function apiGetCities(?CitiesFilter $filter = null): CityCollection
    {
        $cache = 'cities';

        $json = Utils::getFromCache($this, $cache);
        $cities = new CityCollection();

        if ($json === null) {
            $client = HttpClient::create();
            $options['headers'] = [
                'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}")
            ];
            $options['body'] = json_encode([
                'inventory' => 'all',
                'language' => 'ro'
            ]);
            $req = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/hotel/region/dump/', $options);

            $content = $req->getContent();
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/hotel/region/dump/', $options, $content, $req->getStatusCode());

            $contentArr = json_decode($content, true);
            
            $url = $contentArr['data']['url'];

            $path = Utils::getDownloadsPath() . '/' . $this->handle . '/regions';

            $fileName = 'regions-'.date('Y-m-d').'.zst';
            $file = $path . '/' . $fileName;

            if (!file_exists($file)) {

                $client = FtpsClient::create(
                    [
                        'CURLOPT_PORT' => false
                    ]
                );

                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }
                $client->request($url, $path, $fileName);
            }

            $jsonl = 'regions-'.date('Y-m-d').'.jsonl';

            if (!file_exists($path . '/' . $jsonl)) {
                exec('zstd --decompress ' . $file . ' -o ' . $path . '/' . $jsonl);
            }

            $handle = fopen($path . '/' . $jsonl, 'r');
    
            while (($row = fgets($handle)) !== false) {
                $rowArr = json_decode($row, true);
                if (!isset($rowArr['country_code'])) {
                    continue;
                }
                $country = Country::create($rowArr['country_code'], $rowArr['country_code'],  $rowArr['country_name']['ro']);

                $city = City::create($rowArr['id'], $rowArr['name']['ro'] ??  $rowArr['name']['en'], $country);
                $cities->put($city->Id, $city);
            }
            fclose($handle);

            Utils::deleteDirectory($path);

            Utils::writeToCache($this, $cache, json_encode($cities));
        } else {
            $cities = ResponseConverter::convertToCollection(json_decode($json, true), CityCollection::class);
        }

        return $cities;
    }

    public function cacheTopData(string $operation, array $config = [], array $filters = []): array
    {
        $result = [];
        switch ($operation) {
            case 'Hotels_Details':
                $cities = $this->apiGetCities();
                // todo: get details async
                foreach ($filters['Hotels'] as $hotelFilter) {

                    if (!empty($hotelFilter['InTourOperatorId'])) {

                        $client = HttpClient::create();
                        $options['headers'] = [
                            'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}")
                        ];
                        $body = [
                            'hid' => $hotelFilter['InTourOperatorId'],
                            'language' => 'ro'
                        ];
                
                        $url = $this->apiUrl . '/hotel/info/';

                        $options['body'] = json_encode($body);
                        $req = $client->request(HttpClient::METHOD_POST, $url, $options);
                
                        $content = $req->getContent();
                        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

                        $rowArr = json_decode($content, true)['data'];
                        //dump($rowArr);
                        
                        if ($rowArr['region']['type'] !== 'City') {
                            continue;
                        }
                
                        $city = $cities->get($rowArr['region']['id']);
        
                        if ($city === null && $this->handle === Handles::RATEHAWK_STG) {
                            continue;
                        }
                
                        $description = '';
                        foreach ($rowArr['description_struct'] as $descr) {
                            $description .= '<br>' . $descr['title'] . '</b><br>';
                            foreach ($descr['paragraphs'] as $paragraph) {
                                $description .= $paragraph . '<br>';
                            } 
                        }
            
                        $facilities = new FacilityCollection();
                        foreach ($rowArr['amenity_groups'] as $k => $amenityGroup) {
                            foreach ($amenityGroup['amenities'] as $j => $amenity) {
                                $facility = Facility::create($k.'-'.$j, $amenity);
                                $facilities->add($facility);
                            }
                            foreach ($amenityGroup['non_free_amenities'] as $amenity) {
                                $facility = Facility::create('n'.$k.'-'.$j, $amenity . ' (€)');
                                $facilities->add($facility);
                            }
                        }
            
                        $images = new HotelImageGalleryItemCollection();
                        foreach ($rowArr['images_ext'] as $image) {
                            $img = HotelImageGalleryItem::create(str_replace('{size}', '1024x768', $image['url']), $image['category_slug']);
                            $images->add($img);
                        }
            
                        $hotel = Hotel::create(
                            $rowArr['hid'], 
                            $rowArr['name'], 
                            $city,
                            $rowArr['star_rating'],
                            $description,
                            $rowArr['address'],
                            $rowArr['latitude'],
                            $rowArr['longitude'],
                            $facilities,
                            $images,
                            $rowArr['phone'],
                            $rowArr['email']
                        );

                        // if (empty($hotel->Id)) {
                        //     continue;
                        // }
                        $result[] = $hotel;
                    }
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
        }
        return $result;
    }
 
    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        if (empty($filter->CityId)) {
            throw new Exception('CityId is required');
        }

        $cacheFolder = Utils::getCachePath() . '/' .$this->handle . '/hotels/' . date('Y-m-d');
        
        $hotels = new HotelCollection();

        



        
        // $cities = $this->apiGetCities();
        // $city = $cities->get(3421);
        // $hotel = Hotel::create('8473727', 'Test Hotel', $city);
        // $hotels->add($hotel);
        // return $hotels;
        



        if (!file_exists($cacheFolder)) {
            $client = HttpClient::create();
            $options['headers'] = [
                'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}")
            ];
            $options['body'] = json_encode([
                'inventory' => 'all',
                'language' => 'ro'
            ]);
            $req = $client->request(HttpClient::METHOD_POST, $this->apiUrl . '/hotel/info/dump/', $options);

            $content = $req->getContent();
            $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/hotel/info/dump/', $options, $content, $req->getStatusCode());

            $contentArr = json_decode($content, true);
            
            $url = $contentArr['data']['url'];

            $path = Utils::getDownloadsPath() . '/' . $this->handle;

            $hotelJsonl = $path . '/hotels/hotels_'.date('Y-m-d').'.jsonl';
            $hotelArchive = $path . '/hotels/hotels_'.date('Y-m-d').'.zst';

            if (!file_exists($hotelArchive)) {

                $client = FtpsClient::create(
                    [
                        'CURLOPT_PORT' => false
                    ]
                );
                if (!is_dir($path . '/hotels')) {
                    mkdir($path . '/hotels', 0755, true);
                }
                $client->request($url, $path, 'hotels/hotels_'.date('Y-m-d').'.zst');
            }

            if (!file_exists($hotelJsonl)) {
                exec('zstd --decompress ' . $hotelArchive . ' -o ' . $hotelJsonl);
            }

            $handle = fopen($hotelJsonl, 'r');

            mkdir($cacheFolder, 0755, true);
            
            $cities = $this->apiGetCities();

            $i = 0;

            while (($row = fgets($handle)) !== false) {

                $rowArr = json_decode($row, true);
                //dump($rowArr);

                // if ($rowArr['id'] != 'test_hotel_do_not_book') {
                //     continue;
                // }

                if ($rowArr['region']['type'] !== 'City') {
                    continue;
                }
        
                $city = $cities->get($rowArr['region']['id']);

                if ($city === null && $this->handle === Handles::RATEHAWK_STG) {
                    continue;
                }

                if ($rowArr['region']['id'] != 2734) {
                    continue;
                }

                $i++;
                if ($i > 10000) {
                    break;
                }
        
                $description = '';
                foreach ($rowArr['description_struct'] as $descr) {
                    $description .= '<b>' . $descr['title'] . ':</b><br>';

                    foreach ($descr['paragraphs'] as $paragraph) {
                        $description .= $paragraph . '<br>';
                    }
                }

                if (!empty($rowArr['metapolicy_extra_info'])) {
                    $description .= '<br>'. $rowArr['metapolicy_extra_info'] . '<br>';
                }
                
                foreach ($rowArr['metapolicy_struct'] as $nameKey => $metapolicyStruct) {
                    if (!empty($metapolicyStruct)) {
                        

                        $structDescription = '';
                        foreach ($metapolicyStruct as $fieldName => $fieldValue) {
                            if ($fieldValue === 'unspecified' || $fieldValue === null) {
                                continue;
                            }
                            if (is_array($fieldValue)) {
                                foreach ($fieldValue as $fieldNameInner => $fieldValueInner) {
                                    if ($fieldValueInner === 'unspecified' || $fieldValueInner === null) {
                                        continue;
                                    }
                                    $structDescription .= str_replace('_', ' ', $fieldNameInner) . ': ' . 
                                        str_replace('_', ' ', $fieldValueInner) . '<br>';
                                }

                            } else {
                                $structDescription .= str_replace('_', ' ', $fieldName) . ': ' . 
                                    str_replace('_', ' ', $fieldValue) . '<br>';
                            }
                        }
                        $structDescription = rtrim($structDescription, '<br>');

                        if ($structDescription !== '') {
                            $description .= '<br><b>'. str_replace('_', ' ', ucfirst($nameKey)) . '</b>:<br>';
                            $description .= $structDescription . '<br>';
                        }


                        //$name = str_replace('_', ' ', ucfirst($nameKey));

                        // todo: au toate?

                        // $price = $metapolicyStruct['price'] . ' ' . $metapolicyStruct['currency'];

                        // $inclusion = '';
                        // if (isset($metapolicyStruct['inclusion']) && $metapolicyStruct['inclusion'] !== 'unspecified') {
                        //     $inclusion = str_replace('_', ' ', $metapolicyStruct['inclusion']);
                        // }

                        // if ($nameKey === 'internet' && !empty($metapolicyStruct)) {
                        //     dd($rowArr);
                        // }

                        // switch ($nameKey) {
                        //     case 'add_fee':
                        //         $feeType = str_replace('_', ' ', $metapolicyStruct['fee_type']);
                        //         $price_unit = str_replace('_', ' ', $metapolicyStruct['price_unit']);
                                
                        //         $description .= $name . ' - ' . $feeType . ': ' . $price . ' ' . $price_unit . '<br>';
                        //         break;
                        //     case 'check_in_check_out':
                        //         $check_in_check_out_type = str_replace('_', ' ', $metapolicyStruct['check_in_check_out_type']);
                        //         $description .= $name . ' - ' . $check_in_check_out_type . ': ' . $price. ' ' . $inclusion . '<br>';
                        //         break;
                        //     case 'children':
                        //         $check_in_check_out_type = str_replace('_', ' ', $metapolicyStruct['check_in_check_out_type']);
                        //         $description .= $name . ' - ' . $check_in_check_out_type . ': ' . $price. ' ' . $inclusion . '<br>';
                        //         break;
                        // }
                    }
                }





    
                $facilities = new FacilityCollection();
                foreach ($rowArr['amenity_groups'] as $k => $amenityGroup) {
                    foreach ($amenityGroup['amenities'] as $j => $amenity) {
                        $facility = Facility::create($k.'-'.$j, $amenity);
                        $facilities->add($facility);
                    }
                    foreach ($amenityGroup['non_free_amenities'] as $amenity) {
                        $facility = Facility::create('n'.$k.'-'.$j, $amenity . ' (€)');
                        $facilities->add($facility);
                    }
                }
    
                $images = new HotelImageGalleryItemCollection();
                foreach ($rowArr['images_ext'] as $image) {
                    $img = HotelImageGalleryItem::create(str_replace('{size}', '1024x768', $image['url']), $image['category_slug']);
                    $images->add($img);
                }
    
                $hotel = Hotel::create(
                    $rowArr['hid'], 
                    $rowArr['name'], 
                    $city,
                    $rowArr['star_rating'],
                    $description,
                    $rowArr['address'],
                    $rowArr['latitude'],
                    $rowArr['longitude'],
                    $facilities,
                    $images,
                    $rowArr['phone'],
                    $rowArr['email']
                );

                file_put_contents($cacheFolder . '/' . $rowArr['region']['id'], json_encode($hotel) . PHP_EOL, FILE_APPEND);  
            }
            fclose($handle);

            // $hotelsCacheFolder = glob(Utils::getCachePath() . '/' .$this->handle . '/hotels/*');
            // if (count($hotelsCacheFolder) > 0) {
            //     foreach ($hotelsCacheFolder as $folder) {
            //         if (file_exists($folder) && $folder !== $cacheFolder) {
            //             Utils::deleteDirectory($folder);
            //         }
            //     }
            // }

            Utils::deleteDirectory($path . '/hotels');
        } 

        $hotelsFile = $cacheFolder  . '/' . $filter->CityId;

        if (file_exists($hotelsFile)) {

            $handle = fopen($hotelsFile, 'r');

            while (($row = fgets($handle)) !== false) {

                $rowArr = json_decode($row, true);

                $hotel = ResponseConverter::convertToItemResponse($rowArr, Hotel::class);

                $hotels->add($hotel);   

            }
        }

        return $hotels;
    }

    /*
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
                $this->showRequest(HttpClient::METHOD_POST, $this->apiUrl . '/HotelDetails', $options, $resp->getContent(), $resp->getStatusCode());

                $respArr = json_decode($resp->getContent(), true);
        
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
    */

    // todo: availability?
    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        Validator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateIndividualOffersFilter($filter);
        
        $availabilities = new AvailabilityCollection();


        $client = HttpClient::create();
        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}")
        ];

        $ages = $filter->rooms->first()->childrenAges->toArray();

        $body = [
            'checkin' => $filter->checkIn,
            'checkout' => $filter->checkOut,
            'residency' => 'uz', //todo: 'ro',
            'language' => 'ro',
            'guests' => [
                [
                    'adults' => (int)$filter->rooms->first()->adults,
                    'children' => array_map(fn(string $el) => (int) $el, $ages)
                ]
            ],
            'region_id' => (int)$filter->cityId,
            'currency' => 'EUR',
            'timeout' => 30

        ];

        $url = null;
        if (!empty($filter->hotelId)) {
            $body['hid'] = (int) $filter->hotelId;
            $url = $this->apiUrl . '/search/hp/';
        } else {
            $url = $this->apiUrl . '/search/serp/region/';
        }

        $options['body'] = json_encode($body);
        $req = $client->request(HttpClient::METHOD_POST, $url, $options);

        $content = $req->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

        $contentArr = json_decode($content, true);

        foreach ($contentArr['data']['hotels'] as $hotelResponse) {

            $hotel = Availability::create($hotelResponse['hid']);

            $offers = new OfferCollection();
            foreach ($hotelResponse['rates'] as $rate) {
                //dump($rate,$hotelResponse);

                $bookingDataJson = null;
                
                if (isset($rate['book_hash'])) {

                    $bookingDataJson = json_encode([
                        'book_hash' => $rate['book_hash']
                    ]);
                }

                $offer = Offer::createIndividualOffer(
                    $hotelResponse['hid'],
                    md5($rate['room_name']),
                    md5($rate['room_name']),
                    $rate['room_name'],
                    md5($rate['meal']),
                    $rate['meal'],
                    new DateTimeImmutable($filter->checkIn),
                    new DateTimeImmutable($filter->checkOut),
                    $filter->rooms->first()->adults,
                    $filter->rooms->first()->childrenAges->toArray(),
                    $rate['payment_options']['payment_types'][0]['show_currency_code'],
                    $rate['payment_options']['payment_types'][0]['show_amount'],
                    $rate['payment_options']['payment_types'][0]['show_amount'],
                    $rate['payment_options']['payment_types'][0]['show_amount'],
                    0,
                    Offer::AVAILABILITY_YES,
                    null,
                    null,
                    $bookingDataJson
                );

                if ($rate['payment_options']['payment_types'][0]['cancellation_penalties']['policies'][0]['start_at'] !== null) {
                    dd($rate['payment_options']);
                }
                if (count($rate['payment_options']['payment_types']) > 1) {
                    dd($rate['payment_options']);
                }


                $offers->put($offer->Code, $offer);
            }
            $hotel->Offers = $offers;
            $availabilities->add($hotel);
        }

        return $availabilities;
    }

    public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    {
        $client = HttpClient::create();
        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}"),
            'Content-Type' => 'application/json'
        ];

        $data = json_decode($filter->OriginalOffer->bookingDataJson, true);

        $body = [
            'hash' => $data['book_hash'],
            'timeout' => 30
        ];

        $url = $this->apiUrl . '/hotel/prebook/';

        $options['body'] = json_encode($body);
        $req = $client->request(HttpClient::METHOD_POST, $url, $options);

        $content = $req->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

        $contentArr = json_decode($content, true)['data']['hotels'][0]['rates'][0];

        if (!isset($contentArr['payment_options']['payment_types'][0])) {
            throw new Exception('ratehawk payment error: ' . $this->post);
        }

        $payment = $contentArr['payment_options']['payment_types'][0];

        if (isset($contentArr['payment_options']['payment_types'][1])) {
            throw new Exception('ratehawk payment error ' . $this->post);
        }

        $currency = Currency::create($payment['currency_code']);

        $offFees = new OfferCancelFeeCollection();

        foreach ($payment['cancellation_penalties']['policies'] as $policy) {
            $cp = new OfferCancelFee();
            $cp->Currency = $currency;
            $cp->Price = $policy['amount_show'];
            if ($cp->Price === 0.0) {
                continue;
            }
            $cp->DateStart = $policy['start_at'] ?? date('Y-m-d');
            $cp->DateEnd = $policy['end_at'] ?? date('Y-m-d');
            $offFees->add($cp);
        }
        
        $offPayments = new OfferPaymentPolicyCollection();

        $offAvailability = Offer::AVAILABILITY_YES;

        $offPrice = $payment['show_amount'];
        $offInitialPrice = $offPrice;
        $offCurrency = $payment['currency_code'];

        $notes = [];
        foreach ($payment['tax_data']['taxes'] as $tax) {
            $notes[] = str_replace('_', ' ', ucfirst($tax['name'])) . ': ' . 
                $tax['amount'] . ' ' . 
                $tax['currency_code'] . ' (' . 
                ($tax['included_by_supplier'] ? 'inclus in pret' : 'se plateste separat') . ')';
        }

        return [$offFees, $offPayments, $offAvailability, $offPrice, $offInitialPrice, $offCurrency, null, $notes];
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        $client = HttpClient::create();
        $options['headers'] = [
            'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}"),
            'Content-Type' => 'application/json'
        ];
        $bookingData = json_decode($filter->Items->first()->Offer_bookingDataJson, true);
        $uid = uniqid();

        $body = [
            'book_hash' => $bookingData['book_hash'],
            'partner_order_id' => $uid,
            'language' => 'ro',
            'user_ip' => Utils::getIPAddress()
        ];

        $url = $this->apiUrl . '/hotel/order/booking/form/';

        $options['body'] = json_encode($body);
        $req = $client->request(HttpClient::METHOD_POST, $url, $options);

        $content = $req->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

        $contentArr = json_decode($content, true);

        $bookingId = $contentArr['data']['order_id'];

        $booking = new Booking();

        if ($contentArr['status'] !== 'ok') {
            return [$booking, $content];
        }

        //dd($contentArr);

        $guests = [];
        /** @var Passenger $passenger */
        foreach ($filter->Items->first()->Passengers as $passenger) {
            $guest = [
                'first_name' => $passenger->Firstname,
                'last_name' => $passenger->Lastname
            ];
            if (!$passenger->IsAdult) {
                $guest['is_child'] = true;
                $guest['age'] = (new DateTime())->diff(new DateTime($passenger->BirthDate))->y;
            }
            $guests[] = $guest;
        }

        // todo: de unde iau valuta
        $body = [
            'user' => [
                'phone' => $filter->BillingTo->Phone,
                'email' => $filter->BillingTo->Email
            ],
            'partner' => [
                'partner_order_id' => $uid
            ],
            'language' => 'ro',
            'rooms' => [
                [
                    'guests' => $guests
                ]
            ],
            'payment_type' => [
                'type' => 'deposit',
                'amount' => $filter->Items->first()->Offer_Gross,
                'currency_code' => 'EUR'
            ],
            'book_timeout' => 50
        ];

        $url = $this->apiUrl . '/hotel/order/booking/finish/';

        $options['body'] = json_encode($body);
        $req = $client->request(HttpClient::METHOD_POST, $url, $options);

        $content = $req->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

        $contentArr = json_decode($content, true);

        if ($contentArr['status'] !== 'ok') {
            return [$booking, $content];
        }

        sleep(8);

        $body = [
            'partner_order_id' => $uid,
        ];

        $url = $this->apiUrl . '/hotel/order/booking/finish/status/';

        $options['body'] = json_encode($body);
        $req = $client->request(HttpClient::METHOD_POST, $url, $options);

        $content = $req->getContent();
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

        $contentArr = json_decode($content, true);
        $status = $contentArr['status'];

        // check every second, 50 times
        $i = 0;
        while ($status === 'processing') {
            $i++;
            if ($i > 50) {
                return [$booking, $contentArr];
            }
            sleep(1);
            $req = $client->request(HttpClient::METHOD_POST, $url, $options);

            $content = $req->getContent();
            $this->showRequest(HttpClient::METHOD_POST, $url, $options, $content, $req->getStatusCode());

            $contentArr = json_decode($content, true);
            $status = $contentArr['status'];
        }

        if ($status === 'ok') {
            $booking->Id = $bookingId;
        }

        return [$booking, $contentArr];
    }
}