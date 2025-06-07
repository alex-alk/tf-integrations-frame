<?php

namespace Integrations\Anex;

use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\MealMerchType;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\ReturnTransportItem;
use App\Entities\Availability\Room;
use App\Entities\Availability\RoomCollection;
use App\Entities\Availability\RoomMerch;
use App\Entities\Availability\RoomMerchType;
use App\Entities\Availability\TransportMerch;
use App\Entities\Availability\TransportMerchCategory;
use App\Entities\Availability\TransportMerchLocation;
use App\Entities\AvailabilityDates\AvailabilityDates;
use App\Entities\AvailabilityDates\array;
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
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Entities\Tours\Location;
use App\Entities\Tours\Tour;
use App\Entities\Tours\TourCollection;
use App\Entities\Tours\TourContent;
use App\Entities\Tours\TourImageGallery;
use App\Entities\Tours\TourImageGalleryItem;
use App\Entities\Tours\TourImageGalleryItemCollection;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
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
use App\Support\HttpClient\Client\HttpClient;
use App\Support\HttpClient\Factory\RequestFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;
use Exception;
use Integrations\Samo\CryptAes;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\ResponseConverter;
use SimpleXMLElement;
use stdClass;
use Utils\Utils;

class AnexApiService extends AbstractApiService
{
	private const JOIN_UP = 'join_up_v2';
	private const JOIN_UP_TEST = 'join_up_test';
	private const TYPE_CHARTER = 2;
	private const TYPE_TOUR_TOUR = 3;
	private const TYPE_TOUR_EXOTIC = 15;
	private const JOIN_UP_ROMANIA = 82;
	private const PRESTIGE_ROMANIA = 80;
	private const ROMANIA = 'Romania';
	private const ANEX_ROMANIA = 83;
	private const ANEX_WEBAPI_URL = 'https://webapi.anextour.com.ro';

	public function __construct(private HttpClient $client)
	{
		parent::__construct();
	}

	public function apiTestConnection(): bool
	{
		AnexValidator::make()
			->validateAllCredentials($this->post)
			->validateApiCode($this->post)
			->validateBookingCredentials($this->post);

		$this->skipTopCache = true;

		$countriesResp = $this->getDataFromXmlGate('state');
		$countriesOk = false;
		if (count($countriesResp) > 0) {
			$countriesOk = true;
		}

		$townFroms = $this->requestData('SearchTour_TOWNFROMS', []);
		$townFromsOk = false;
		if (count($townFroms) > 0) {
			$townFromsOk = true;
		}

		if ($countriesOk && $townFromsOk) {
			return true;
		}

		return false;
	}

	/*
    public function apiGetCountriesOld(): array
    {
        $countries = [];

		$cities = $this->apiGetCities();

		/** @var City $city */
	/*
		foreach ($cities as $city) {
			$countries->put($city->Country->Id, $city->Country);
		}

        return $countries;
    }
	*/

	public function apiGetCountries(): array
	{
		$file = 'countries';

		$json = Utils::getFromCache($this, $file);

		if ($json === null) {

			$countries = [];

			$countriesMap = CountryCodeMap::getCountryCodeMap();

			$countriesResp = $this->getDataFromXmlGate('state');

			foreach ($countriesResp as $state) {
				$country = Country::create($state['inc'], $countriesMap[(string) $state['lname']] ?? (string) $state['lname'], $state['lname']);
				$countries->put($country->Id, $country);
			}

			Utils::writeToCache($this, $file, json_encode($countries));
		} else {
			$countries = ResponseConverter::convertToCollection(json_decode($json, true), array::class);
		}

		return $countries;
	}

	public function apiGetRegions(): []
	{

		$cities = $this->apiGetCities();

		$regions = [];

		/** @var City $city */
		foreach ($cities as $city) {
			$county = $city->County;
			if ($county === null) {
				continue;
			}
			$regions->put($city->County->Id, $city->County);
		}

		return $regions;
	}

	/*
	public function apiGetRegionsOld(): []
	{
		$regions = [];
		$cities = $this->apiGetCities();

		/** @var City $city */
	/*
		foreach ($cities as $city) {
			if ($city->County === null) {
				continue;
			}
			$regions->put($city->County->Id, $city->County);
		}

        return $regions;
	}*/

	private function requestData(string $action, array $params = []): array
	{
		$this->apiUrl = 'https://search.anextour.com.ro';

		$cachedActions = [
			'SearchTour_STATES',
			'SearchTour_TOWNFROM',
			'SearchTour_Tours',
			'SearchTour_TOWNS',
			'SearchTour_CHECKIN',
			'SearchTour_NIGHTS',
			'SearchTour_CURRENCIES'
		];
		if (in_array($action, $cachedActions)) {
			// try cache

			$file = 'static_data-' . $action . '-' . implode('-', $params);

			$json = Utils::getFromCache($this, $file);
			if ($json === null) {

				$params = [
					'samo_action' => 'api',
					'oauth_token' => $this->apiContext,
					'type' => 'json',
					'action' => $action
				] + $params;

				$url = $this->apiUrl . '/export/default.php' . "?" . http_build_query($params);

				$req = $this->client->request(RequestFactory::METHOD_GET, $url);

				$content = $req->getBody();
				$this->showRequest(RequestFactory::METHOD_GET, $url, [], $content, $req->getStatusCode());

				$contentArr = json_decode($content, true);

				$i = 0;
				while ($contentArr === null) {
					$i++;
					if ($i > 10) {
						throw new Exception($this->handle . ': content error ' . $content);
					}
					sleep(10);
					$req = $this->client->request(RequestFactory::METHOD_GET, $url);
					$content = $req->getBody();
					$contentArr = json_decode($content, true);
				}

				$arr = $contentArr[$action];
				Utils::writeToCache($this, $file, $content);
			} else {
				$arr = json_decode($json, true)[$action];
			}
		} else {
			$params = [
				'samo_action' => 'api',
				'oauth_token' => $this->apiContext,
				'type' => 'json',
				'action' => $action
			] + $params;

			$url = $this->apiUrl . '/export/default.php' . "?" . http_build_query($params);

			$req = $this->client->request(RequestFactory::METHOD_GET, $url);
			$content = $req->getBody();
			$this->showRequest(RequestFactory::METHOD_GET, $url, [], $content, $req->getStatusCode());
			$arr = json_decode($content, true)[$action];
		}

		return $arr;
	}

	private function requestDataXml(string $type, array $params = []): string
	{
		// add actions to be cached here
		$cachedActions = [];

		$content = null;
		if (in_array($type, $cachedActions)) {
			// try cache

			$file = 'static_data-' . $type . '-' . implode('-', $params);

			$json = Utils::getFromCache($this, $file);
			if ($json === null) {
				$params = [
					'samo_action' => 'reference',
					'oauth_token' => $this->apiCode,
					'type' => $type
				] + $params;

				$url = $this->apiUrl . '/incoming/export/default.php' . "?" . http_build_query($params);

				$req = $this->client->request(RequestFactory::METHOD_GET, $url);
				$content = $req->getBody();
				$this->showRequest(RequestFactory::METHOD_GET, $url, [], $content, $req->getStatusCode());

				Utils::writeToCache($this, $file, $content);
			}
			// else {
			// 	$arr = json_decode($json, true)[$type] ?? [];
			// }

		} else {

			$params = [
				'samo_action' => 'reference',
				'oauth_token' => $this->apiCode,
				'type' => $type
			] + $params;

			$url = $this->apiUrl . '/export/default.php' . "?" . http_build_query($params);

			$req = $this->client->request(RequestFactory::METHOD_GET, $url);
			$content = $req->getBody();
			$this->showRequest(RequestFactory::METHOD_GET, $url, [], $content, $req->getStatusCode());
		}

		return $content;
	}

	private function getDataFromXmlGate(string $type): array
	{
		$data = [];

		$laststamp = '';
		$delstamp = '';
		$currentStamp = '';

		$i = 0;
		while ($i < 20) {
			$i++;
			$params = [
				'laststamp' => $laststamp,
				'delstamp' => $delstamp
			];
			$respXml = $this->requestDataXml($type, $params);

			$xml = simplexml_load_string($respXml);

			$iterator = $xml->Data->$type;

			if (count($iterator) === 0) {
				break;
			}

			foreach ($iterator as $resp) {

				if (!isset($resp['name'])) {
					continue;
				}

				$status = (string) $resp['status'];

				if ($status === 'D') {
					$delstamp = (string) $resp['stamp'];
					continue;
				}
				$currentStamp = (string) $resp['stamp'];
				$data[] = $resp;
			}

			if ($currentStamp === $laststamp) {
				break;
			}

			$laststamp = $currentStamp;
		}
		return $data;
	}

	public function apiGetCities(?CitiesFilter $filter = null): array
	{
		$file = 'cities';
		$citiesJson = Utils::getFromCache($this, $file);

		$cities = [];
		if ($citiesJson === null) {

			$respTownsFrom = $this->client->request(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/townfroms?type=json&xdebug=false');
			$content = $respTownsFrom->getBody();
			$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/townfroms?type=json&xdebug=false', [], $content, $respTownsFrom->getStatusCode());

			$respTownsFrom = json_decode($content, true)['SearchTour_TOWNFROMS'];

			foreach ($respTownsFrom as $townFrom) {
				if ($townFrom['stateFromName'] === 'Romania') {
					$country = Country::create($townFrom['id'], 'RO', 'Romania');
					$city = City::create($townFrom['id'], $townFrom['name'], $country);
					$cities->put($city->Id, $city);
				}

				$respStates = $this->client->request(
					RequestFactory::METHOD_GET,
					$this->apiUrl . '/samo/searchtour/states?type=json&xdebug=true&TOWNFROMINC=' . $townFrom['id']
				);
				$content = $respStates->getBody();
				$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/states?type=json&xdebug=true&TOWNFROMINC=' . $townFrom['id'], [], $content, $respStates->getStatusCode());

				$respStates = json_decode($content, true)['SearchTour_STATES'];

				foreach ($respStates as $state) {
					$country = Country::create($state['id'], $state['stateISO'], $state['name']);
					$respTowns = $this->client->request(
						RequestFactory::METHOD_GET,
						$this->apiUrl . '/search/towns?townFrom=' . $townFrom['id'] . '&state=' . $state['id'] . '&region=-1&tour=-1&ptype=-1&tourType=-1&SEARCH_TYPE=PACKET_TOUR'
					);
					$content = $respTowns->getBody();
					$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/townfroms?type=json&xdebug=false', [], $content, $respTowns->getStatusCode());

					$respTowns = json_decode($content, true);

					foreach ($respTowns as $town) {
						$region = Region::create($town['regionInc'], $town['regionLName'], $country);
						$city = City::create($town['townInc'], $town['townLName'], $country, $region);
						$cities->put($city->Id, $city);
					}
				}
			}

			// $regions = $this->apiGetRegions();
			// $countries = $this->apiGetCountries();

			// $citiesResp = $this->getDataFromXmlGate('town');

			// foreach ($citiesResp as $resp) {

			// 	$country = $countries->get((string) $resp['state']);
			// 	$region = $regions->get((string) $resp['region']);

			// 	$city = City::create($resp['inc'], $resp['lname'], $country, $region);
			// 	$cities->put($city->Id, $city);
			// }

			Utils::writeToCache($this, $file, json_encode($cities));
		} else {
			$cities = ResponseConverter::convertToCollection(json_decode($citiesJson, true), array::class);
		}
		return $cities;
	}

	/*
    public function apiGetCitiesOld(CitiesFilter $params = null): array
    {
		$file = 'cities';
		$citiesJson = Utils::getFromCache($this, $file);

		if ($citiesJson === null) {

			$cities = [];
			$map = CountryCodeMap::getCountryCodeMap();

			// HOTEL
			// 1
			// get statefrom
			$stateFroms = $this->requestData('SearchHotel_STATEFROM');

			foreach ($stateFroms as $stateFrom) {
				// 2
				// get townfrom
				// param: statefrom
				$params = ['STATEFROM' => $stateFrom['id']];
				$townFroms = $this->requestData('SearchHotel_TOWNFROMS', $params);

				foreach ($townFroms as $townFrom) {
					// 3
					// get states
					// param: townfrominc
					$params = [
						'TOWNFROMINC' => $townFrom['id'],
						'STATEFROM' => $stateFrom['id']
					];
					$states = $this->requestData('SearchHotel_STATES', $params);

					foreach ($states as $state) {
						// 4 get towns
						// params: stateinc, townfrominc
						$params = [
							'STATEINC' => $state['id'],
							'TOWNFROMINC' => $townFrom['id'],
							'STATEFROM' => $stateFrom['id']
						];
						
						$towns = $this->requestData('0', $params);
						foreach ($towns as $town) {
							$country = new Country();
							$country->Name = $state['name'];
							$country->Id = $state['id'];
							$country->Code = $map[$country->Name];

							$region = new Region();
							$region->Id = $town['regionKey'];
							$region->Name = $town['region'];
							$region->Country = $country;

							$city = new City();
							$city->Id = $town['id'];
							$city->Name = $town['name'];
							$city->County = $region;
							$city->Country = $country;

							$cities->put($city->Id, $city);
						}
					}
				}
			}

			// HOLIDAY
			$townFroms = $this->requestData('SearchTour_TOWNFROMS');
			foreach ($townFroms as $townFrom) {
				if (($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) && $townFrom['stateFromKey'] !== self::JOIN_UP_ROMANIA) {
					continue;
				}

				$country = new Country();
				$country->Id = $townFrom['stateFromKey'];
				$country->Name = $townFrom['stateFromName'];
				$country->Code = $map[$country->Name];
				
				$city = new City();
				$city->Id = $townFrom['id'];
				$city->Name = $townFrom['name'];
				$city->Country = $country;
				$cities->put($city->Id, $city);
				
				$paramsh1['TOWNFROMINC'] = $townFrom['id'];
				$states = $this->requestData('SearchTour_STATES', $paramsh1);

				foreach ($states as $state) {
					$params = [
						'STATEINC' => $state['id'], 
						'TOWNFROMINC' => $townFrom['id']
					];
					$towns = $this->requestData('SearchTour_TOWNS', $params);
					
					foreach ($towns as $town) {
						$country = new Country();
						$country->Name = $state['name'];
						$country->Id = $state['id'];
						if ($country->Name === 'Bali') {
							$country->Code = 'ID';
						} else {
							$country->Code = $map[$country->Name] ?? $country->Name;
						}

						$region = new Region();
						$region->Id = $town['regionKey'];
						$region->Name = $town['region'];
						$region->Country = $country;

						$city = new City();
						$city->Id = $town['id'];
						$city->Name = $town['name'];
						$city->County = $region;
						$city->Country = $country;

						$cities->put($city->Id, $city);
					}
				}
			}

			
			// Exotic

			/*
			$townFroms = $this->requestData('SearchExcursion_TOWNFROMS');
			
			foreach ($townFroms as $townFrom) {
				$params = ['TOWNFROMINC' => $townFrom['id']];
				$exoticCountries = $this->requestData('SearchExcursion_STATES', $params);

				foreach ($exoticCountries as $state) {
					// prepare params
					$params = [
						'STATEINC' => $state['id'], 
						'TOWNFROMINC' => $townFrom['id']
					];
					
					// request for individual cities
					$towns = $this->requestData('SearchExcursion_TOWNS', $params);
					foreach ($towns as $town) {
						$country = new Country();
						$country->Name = $state['name'];
						$country->Id = $state['id'];
						if (!isset($map[$country->Name])) {
							//continue;
						}
						
						$country->Code = $map[$country->Name];

						$region = new Region();
						$region->Id = $town['regionKey'];
						$region->Name = $town['region'];
						$region->Country = $country;

						$city = new City();
						$city->Id = $town['id'];
						$city->Name = $town['name'];
						$city->County = $region;
						$city->Country = $country;

						$cities->put($city->Id, $city);
					}
				}

			}*/


	/*
			Utils::writeToCache($this, $file, json_encode($cities));
		} else {
			$cities = ResponseConverter::convertToCollection(json_decode($citiesJson, true), array::class);
		}

        return $cities;
    }*/

	public function apiGetHotels(?HotelsFilter $filter = null): []
	{
		$file = 'hotels';
		$json = Utils::getFromCache($this, $file);

		// todo: luat hotelurile si testat
		if ($json === null) {

			$cities = $this->apiGetCities();
			$hotels = [];

			// INDIVIDUAL
			$stateFroms = $this->requestData('SearchHotel_STATEFROM');
			foreach ($stateFroms as $stateFrom) {
				// 2
				// get townfrom
				$params = ['STATEFROM' => $stateFrom['id']];
				$townFroms = $this->requestData('SearchHotel_TOWNFROMS', $params);

				foreach ($townFroms as $townFrom) {
					// 3
					// get states
					$params = [
						'TOWNFROMINC' => $townFrom['id'],
						'STATEFROM' => $stateFrom['id']
					];
					$states = $this->requestData('SearchHotel_STATES', $params);

					foreach ($states as $state) {
						// 4 get towns
						$params = [
							'STATEINC' => $state['id'],
							'TOWNFROMINC' => $townFrom['id'],
							'STATEFROM' => $stateFrom['id']
						];

						$hotelsResp = $this->requestData('SearchHotel_HOTELS', $params);
						if (!isset($hotelsResp[0])) {
							continue;
						}
						foreach ($hotelsResp as $hotelData) {
							$hotel = new Hotel();
							$hotel->Name = $hotelData['name'];

							$hotel->Id = $hotelData['id'];
							$hotel->Stars = (int) $hotelData['star'];
							$city = $cities->get($hotelData['townKey']);
							if ($city === null) {
								continue;
							}

							$address = new HotelAddress();
							$address->City = $city;

							$hotel->Address = $address;

							$hotels->put($hotel->Id, $hotel);
						}
					}
				}
			}

			// HOLIDAY
			$townFroms = $this->requestData('SearchTour_TOWNFROMS');
			foreach ($townFroms as $townFrom) {
				if (($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) && $townFrom['stateFromKey'] !== self::JOIN_UP_ROMANIA) {
					continue;
				}
				$params['TOWNFROMINC'] = $townFrom['id'];
				$states = $this->requestData('SearchTour_STATES', $params);

				foreach ($states as $state) {
					$params = [
						'STATEINC' => $state['id'],
						'TOWNFROMINC' => $townFrom['id']
					];
					$hotelsResp = $this->requestData('SearchTour_HOTELS', $params);

					if (!isset($hotelsResp[0])) {
						continue;
					}

					foreach ($hotelsResp as $hotelData) {
						$hotel = new Hotel();

						$hotel->Id = $hotelData['id'];
						$hotel->Name = $hotelData['name'];
						$hotel->Stars = (int) $hotelData['star'];
						$city = $cities->get($hotelData['townKey']);
						if ($city === null) {
							continue;
						}

						$address = new HotelAddress();
						$address->City = $city;

						$hotel->Address = $address;

						// add hotel to array
						$hotels->put($hotel->Id, $hotel);
					}
				}
			}
			Utils::writeToCache($this, $file, json_encode($hotels));
		} else {
			$hotels = ResponseConverter::convertToCollection(json_decode($json, true), []::class);
		}

		return $hotels;
	}
	public function cacheTopData(string $operation, array $config = [], array $filters = []): array
	{
		$result = [];
		switch ($operation) {
			case 'Hotels_Details':
				$cities = $this->apiGetCities();
				if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {

					$requests = [];
					foreach ($filters['Hotels'] as $hotelFilter) {
						$hotelId = $hotelFilter['InTourOperatorId'];
						$params = [
							'samo_action' => 'api',
							'oauth_token' => $this->apiContext,
							'type' => 'json',
							'action' => 'SearchTour_HOTELDATA',
							'HOTELINC' => $hotelId
						];
						$url = $this->apiUrl . "?" . http_build_query($params);
						$req = $this->client->request(RequestFactory::METHOD_GET, $url);
						$requests[] = [$req, $hotelId];
					}
					foreach ($requests as $request) {
						$hotel = new Hotel();
						$hotelReq = $request[0];
						$content = $hotelReq->getBody();

						$arr = json_decode($content, true)['SearchTour_HOTELDATA'];

						$hotelResp = $arr['Reservation']['hotel']['0']['hoteldata'];

						if (!isset($hotelResp['hotel_info']['title'])) {
							continue;
						}

						$facilities = new FacilityCollection();

						foreach ($hotelResp['hotel_facility'] ?? [] as $hotelFacility) {
							$facility = Facility::create(md5($hotelFacility['ro']), $hotelFacility['ro']);
							$facilities->add($facility);
						}

						$images = new HotelImageGalleryItemCollection();

						foreach ($hotelResp['hotel_photo'] ?? [] as $photo) {
							$image = HotelImageGalleryItem::create($photo['url_orig']);
							$images->add($image);
						}

						$hotel = Hotel::create(
							$hotelResp['hotel_info']['orig_id'],
							$hotelResp['hotel_info']['title'],
							$cities->get($hotelResp['hotel_info']['town']['town_id']),
							(int)$hotelResp['hotel_info']['hotel_category'],
							$hotelResp['hotel_description']['orig_description']['ro'],
							$hotelResp['hotel_info']['hotel_adres']['ro'] ?? null,
							$hotelResp['hotel_info']['lat'] ?? null,
							$hotelResp['hotel_info']['lng'] ?? null,
							$facilities,
							$images
						);
						$result[] = $hotel;
					}
				} else {
					$hotels = $this->apiGetHotels();

					$requests = [];
					foreach ($filters['Hotels'] as $hotelFilter) {
						$hotelId = $hotelFilter['InTourOperatorId'];
						$params = [
							'samo_action' => 'api',
							'oauth_token' => $this->apiContext,
							'type' => 'json',
							'action' => 'SearchTour_CONTENT',
							'HOTEL' => $hotelId
						];
						$url = $this->apiUrl . "?" . http_build_query($params);
						$req = $this->client->request(RequestFactory::METHOD_GET, $url);
						$requests[] = [$req, $hotelId];
					}
					foreach ($requests as $request) {

						$hotelReq = $request[0];
						$content = $hotelReq->getBody();
						$arr = json_decode($content, true)['SearchTour_CONTENT'];

						$hotelResp = $arr;

						$hotelId = $request[1];
						$hotel = new Hotel();

						if (empty($hotelResp['samo_id'])) {
							continue;
						}

						$hotelFromList = $hotels->get($hotelResp['samo_id']);
						if ($hotelFromList === null) {
							continue;
						}

						$hotel->Id = $hotelResp['samo_id'];
						$hotel->Name = $hotelResp['title'];
						$hotel->Stars = (int) $hotelResp['stars'];

						$description = null;
						if (!empty($hotelResp['description'])) {
							$description = $hotelResp['description'];
						}
						if (!empty($hotelResp['about'][0]['text'])) {
							$description .= $hotelResp['about'][0]['text'];
						}

						if (isset($hotelResp['for_children'][0])) {
							$description .= 'PENTRU COPII <br>' . $hotelResp['for_children'][0]['info'];
							if (!empty($hotelResp['for_children'][0]['item'])) {
								$description .= '<ul>';
								foreach ($hotelResp['for_children'][0]['item'] as $item) {
									$pay = '';
									if (empty($item['name'])) {
										continue;
									}
									if ($item['pay'] != 0) {
										$pay = ' <span style="background-color: #9ad3f0; color: white; border-radius: 50%; padding-right: 5px !important;padding-left: 4px !important;">€</span>';
									}
									$description .= '<li>' . $item['name'] . $pay . '</li>';
								}
								$description .= '</ul>';
							}
						}

						if (isset($hotelResp['food'][0])) {
							$description .= 'REGIMUL DE MASA <br>' . $hotelResp['food'][0]['info'];
							if (!empty($hotelResp['for_children'][0]['item'])) {
								$description .= '<ul>';
								foreach ($hotelResp['food'][0]['item'] as $item) {
									$pay = '';
									if ($item['pay'] != 0) {
										$pay = ' <span style="background-color: #9ad3f0; color: white; border-radius: 50%; padding-right: 5px !important;padding-left: 4px !important;">€</span>';
									}
									$description .= '<li>' . $item['name'] . $pay . '</li>';
								}
								$description .= '</ul>';
							}
						}

						if (isset($hotelResp['beach'][0])) {
							$description .= 'PLAJA SI PISCINE <br>' . $hotelResp['beach'][0]['info'];
							if (!empty($hotelResp['for_children'][0]['item'])) {
								$description .= '<ul>';
								foreach ($hotelResp['beach'][0]['item'] as $item) {
									$pay = '';
									if ($item['pay'] != 0) {
										$pay = ' <span style="background-color: #9ad3f0; color: white; border-radius: 50%; padding-right: 5px !important;padding-left: 4px !important;">€</span>';
									}
									$description .= '<li>' . $item['name'] . $pay . '</li>';
								}
								$description .= '</ul>';
							}
						}

						if ($description !== null) {
							while (true) {
								$indexStart = strpos($description, '<a href="/assets/');
								$indexEnd = strpos($description, '</a>', $indexStart);

								if (!$indexStart) {
									break;
								}

								$link = substr($description, $indexStart, $indexEnd - $indexStart + 4);

								$description = str_replace($link, '', $description);
							}

							while (true) {
								$indexStart = strpos($description, '<img src="/assets/');
								$indexEnd = strpos($description, '/>', $indexStart);

								if (!$indexStart) {
									break;
								}

								$img = substr($description, $indexStart, $indexEnd - $indexStart + 2);

								$description = str_replace($img, '', $description);
							}
						}

						$content = new HotelContent();
						$content->Content = $description;

						if (!empty($hotelResp['gallery'])) {
							$prestigeUrl = 'https://prestigetours.ro';

							$imageGallery = new HotelImageGallery();
							$items = new HotelImageGalleryItemCollection();

							foreach ($hotelResp['gallery'] as $pic) {
								$photo = new HotelImageGalleryItem();
								$photo->RemoteUrl = $prestigeUrl . $pic;
								$items->add($photo);
							}
							$imageGallery->Items = $items;
							$content->ImageGallery = $imageGallery;
						}

						$hotel->Content = $content;

						$address = new HotelAddress();

						$address->City = $hotelFromList->Address->City;

						if (!empty($hotelResp['map'][0])) {
							$address->Latitude = $hotelResp['map'][0]['latitude'];
							$address->Longitude = $hotelResp['map'][0]['longitude'];
							$address->Details = $hotelResp['map'][0]['address'] ?? null;
						}

						$hotel->Address = $address;

						if (!empty($hotelResp['service'])) {
							$facilities = new FacilityCollection();
							foreach ($hotelResp['service'] as $service) {
								foreach ($service['item'] as $serviceItem) {
									$pay = '';
									if ($serviceItem['pay'] != 0) {
										$pay = ' (€)';
									}
									$facility = new Facility();
									$facility->Name = $serviceItem['name'] . $pay;
									$facility->Id = $service['MIGX_id'] . $serviceItem['MIGX_id'];
									$facilities->put($facility->Id, $facility);
								}
							}
							$hotel->Facilities = $facilities;
						}

						if (!empty($hotelResp['site'][0])) {
							$hotel->WebAddress = $hotelResp['site'][0]['site'];
						}

						$result[] = $hotel;
					}
				}

				break;
		}
		return $result;
	}

	public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
	{
		$cities = $this->apiGetCities();

		$hotel = new Hotel();

		if ($this->handle === Handles::ANEX) {
			$resp = $this->client->request(RequestFactory::METHOD_GET, self::ANEX_WEBAPI_URL . '/B2C/Hotel?lang=ro&hotel=' . $filter->hotelId);
			$content = $resp->getBody();
			$this->showRequest(RequestFactory::METHOD_GET, self::ANEX_WEBAPI_URL . '/B2C/Hotel?lang=ro&hotel=' . $filter->hotelId, [], $content, 0);
			$hotelResp = json_decode($content, true)[0];

			$respImages = $this->client->request(RequestFactory::METHOD_GET, 'https://files.anextour.com.ro/hotel/hotelimagelist?take=500&code=' . $filter->hotelId);
			$content = $respImages->getBody();
			$this->showRequest(RequestFactory::METHOD_GET, 'https://files.anextour.com.ro/hotel/hotelimagelist?take=500&code=' . $filter->hotelId, [], $content, 0);
			$hotelRespImages = json_decode($content, true);


			$images = new HotelImageGalleryItemCollection();
			foreach ($hotelRespImages as $image) {
				$imageObj = HotelImageGalleryItem::create($image['Img'], $image['Alt']);
				$images->add($imageObj);
			}

			// todo: facilities?

			$hotel = Hotel::create(
				$hotelResp['info']['inc'],
				$hotelResp['info']['lname'],
				$cities->get($hotelResp['town']['inc']),
				(int) $hotelResp['info']['starKey'],
				null,
				$hotelResp['info']['address'],
				$hotelResp['info']['latitude'],
				$hotelResp['info']['longitude'],
				null,
				$images,
				$hotelResp['info']['phone'],
				$hotelResp['info']['email'],
				$hotelResp['info']['fax']
			);

			return $hotel;
		}

		if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
			$params = [
				'HOTELINC' => $filter->hotelId,
			];
			$hotelResp = $this->requestData('SearchTour_HOTELDATA', $params);
			$hotelResp = $hotelResp['Reservation']['hotel']['0']['hoteldata'];

			$facilities = new FacilityCollection();
			if (is_array($hotelResp['hotel_facility'])) {
				foreach ($hotelResp['hotel_facility'] as $hotelFacility) {
					$facility = Facility::create(md5($hotelFacility['ro']), $hotelFacility['ro']);
					$facilities->add($facility);
				}
			}

			$images = new HotelImageGalleryItemCollection();

			foreach ($hotelResp['hotel_photo'] as $photo) {
				$image = HotelImageGalleryItem::create($photo['url_orig']);
				$images->add($image);
			}

			$hotel = Hotel::create(
				$hotelResp['hotel_info']['orig_id'],
				$hotelResp['hotel_info']['title'],
				$cities->get($hotelResp['hotel_info']['town']['town_id']),
				(int)$hotelResp['hotel_info']['hotel_category'],
				$hotelResp['hotel_description']['orig_description']['ro'],
				$hotelResp['hotel_info']['hotel_adres']['ro'] ?? null,
				$hotelResp['hotel_info']['lat'] ?? null,
				$hotelResp['hotel_info']['lng'] ?? null,
				$facilities,
				$images
			);
		} else {

			$params = [
				'HOTEL' => $filter->hotelId,
			];
			$hotelResp = $this->requestData('SearchTour_CONTENT', $params);

			if (empty($hotelResp['samo_id'])) {
				return $hotel;
			}

			$hotels = $this->apiGetHotels();
			$hotelFromList = $hotels->get($hotelResp['samo_id']);
			if ($hotelFromList === null) {
				return $hotel;
			}

			$hotel->Id = $hotelResp['samo_id'];
			$hotel->Name = $hotelResp['title'];
			$hotel->Stars = (int) $hotelResp['stars'];

			$description = null;
			if (!empty($hotelResp['description'])) {
				$description = $hotelResp['description'];
			}
			if (!empty($hotelResp['about'][0]['text'])) {
				$description .= $hotelResp['about'][0]['text'];
			}

			if (isset($hotelResp['for_children'][0])) {
				$description .= 'PENTRU COPII <br>' . $hotelResp['for_children'][0]['info'];
				if (!empty($hotelResp['for_children'][0]['item'])) {
					$description .= '<ul>';
					foreach ($hotelResp['for_children'][0]['item'] as $item) {
						$pay = '';
						if ($item['pay'] != 0) {
							$pay = ' <span style="background-color: #9ad3f0; width: 16px; height: 16px; color: white; border-radius: 50%; display: inline-block; text-align: center; padding-right: 2px;padding-left: 1px; padding-bottom: 2px">€</span>';
						}
						$description .= '<li>' . $item['name'] . $pay . '</li>';
					}
					$description .= '</ul>';
				}
			}

			if (isset($hotelResp['food'][0])) {
				$description .= 'REGIMUL DE MASA <br>' . $hotelResp['food'][0]['info'];
				if (!empty($hotelResp['for_children'][0]['item'])) {
					$description .= '<ul>';
					foreach ($hotelResp['food'][0]['item'] as $item) {
						$pay = '';
						if ($item['pay'] != 0) {
							$pay = ' <span style="background-color: #9ad3f0; width: 16px; height: 16px; color: white; border-radius: 50%; display: inline-block; text-align: center; padding-right: 2px;padding-left: 1px; padding-bottom: 2px">€</span>';
						}
						$description .= '<li>' . $item['name'] . $pay . '</li>';
					}
					$description .= '</ul>';
				}
			}

			if (isset($hotelResp['beach'][0])) {
				$description .= 'PLAJA SI PISCINE <br>' . $hotelResp['beach'][0]['info'];
				if (!empty($hotelResp['for_children'][0]['item'])) {
					$description .= '<ul>';
					foreach ($hotelResp['beach'][0]['item'] as $item) {
						$pay = '';
						if ($item['pay'] != 0) {
							$pay = ' <span style="background-color: #9ad3f0; width: 16px; height: 16px; color: white; border-radius: 50%; display: inline-block; text-align: center; padding-right: 2px;padding-left: 1px; padding-bottom: 2px">€</span>';
						}
						$description .= '<li>' . $item['name'] . $pay . '</li>';
					}
					$description .= '</ul>';
				}
			}

			while (true) {
				$indexStart = strpos($description, '<a href="/assets/');
				$indexEnd = strpos($description, '</a>', $indexStart);

				if (!$indexStart) {
					break;
				}

				$link = substr($description, $indexStart, $indexEnd - $indexStart + 4);

				$description = str_replace($link, '', $description);
			}

			while (true) {
				$indexStart = strpos($description, '<img src="/assets/');
				$indexEnd = strpos($description, '/>', $indexStart);

				if (!$indexStart) {
					break;
				}

				$img = substr($description, $indexStart, $indexEnd - $indexStart + 2);

				$description = str_replace($img, '', $description);
			}

			$content = new HotelContent();
			$content->Content = $description;

			if (!empty($hotelResp['gallery'])) {
				$prestigeUrl = 'https://prestigetours.ro';

				$imageGallery = new HotelImageGallery();
				$items = new HotelImageGalleryItemCollection();

				foreach ($hotelResp['gallery'] as $pic) {
					$photo = new HotelImageGalleryItem();
					$photo->RemoteUrl = $prestigeUrl . $pic;
					$items->add($photo);
				}
				$imageGallery->Items = $items;
				$content->ImageGallery = $imageGallery;
			}

			$hotel->Content = $content;

			$address = new HotelAddress();

			$address->City = $hotelFromList->Address->City;

			if (!empty($hotelResp['map'][0])) {
				$address->Latitude = $hotelResp['map'][0]['latitude'];
				$address->Longitude = $hotelResp['map'][0]['longitude'];
				$address->Details = $hotelResp['map'][0]['address'] ?? null;
			}

			$hotel->Address = $address;

			if (!empty($hotelResp['service'])) {
				$facilities = new FacilityCollection();
				foreach ($hotelResp['service'] as $service) {
					foreach ($service['item'] as $serviceItem) {
						$pay = '';
						if ($serviceItem['pay'] != 0) {
							$pay = ' <span style="background-color: #9ad3f0; width: 16px; height: 16px; color: white; border-radius: 50%; display: inline-block; text-align: center; padding-right: 2px;padding-left: 1px; padding-bottom: 2px">€</span>';
						}
						$facility = new Facility();
						$facility->Name = $serviceItem['name'] . $pay;
						$facility->Id = $service['MIGX_id'] . $serviceItem['MIGX_id'];
						$facilities->put($facility->Id, $facility);
					}
				}
				$hotel->Facilities = $facilities;
			}

			if (!empty($hotelResp['site'][0])) {
				$hotel->WebAddress = $hotelResp['site'][0]['site'];
			}
		}

		return $hotel;
	}

	public function apiGetOffers(AvailabilityFilter $filter): array
	{
		if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
			return $this->getIndividualOffers($filter);
		} else {
			return $this->getCharterOrTourOffers($filter);
		}
	}

	private function getIndividualOffers(AvailabilityFilter $filter): array
	{
		AnexValidator::make()->validateUsernameAndPassword($this->post)
			->validateIndividualOffersFilter($filter);

		$availabilities = [];

		$cities = $this->apiGetCities();

		$childrenAges = $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : null;

		$checkIn = (new DateTime($filter->checkIn))->format('Ymd');

		$filter->departureCity = 1;
		$currencyId = $this->getCurrencyId($filter);

		$params = [
			'STATEINC' => $filter->countryId,
			'TOWNFROMINC' => 1,
			'CHECKIN_BEG' => $checkIn,
			'CHECKIN_END' => $checkIn,
			'NIGHTS_FROM' => $filter->days,
			'NIGHTS_TILL' => $filter->days,
			'ADULT' => $filter->rooms->first()->adults,
			'CURRENCY' => $currencyId,
			'PACKET' => 2 // Package type. 1 - only flight. 2 - only hotel. Empty value - holiday package.
		];

		$location = '';
		if (empty($filter->cityId)) {
			$citiesSelected = $cities->filter(fn(City $city) => ($city->County->Id ?? '?') === $filter->regionId);
			foreach ($citiesSelected as $citySelected) {
				$location .= $citySelected->Id . ',';
			}
			$location = rtrim($location, ',');
		} else {
			$location = $filter->cityId;
		}
		$params['TOWNS'] = $location;

		$countries = $this->apiGetCountries();

		$params['STATEFROM'] = $countries->first(fn(Country $country) => $country->Code === 'RO')->Id;

		if (!empty($filter->hotelId)) {
			$params["HOTELS"] = $filter->hotelId;
		}

		if (!empty($post['args'][0]['rooms'][0]['children'])) {
			$params['CHILD'] = $post['args'][0]['rooms'][0]['children'];

			$params['AGES'] = '';
			foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $childrenAge) {
				$params['AGES'] = $params['AGES'] . $childrenAge . ',';
			}

			$params['AGES'] = rtrim($params['AGES'], ",");
		}

		$prices = [];
		$response = $this->requestData('SearchHotel_PRICES', $params);
		$prices[] = $response['prices'];

		if (empty($response['pager'])) {
			return $availabilities;
		}

		$allPages = $response['pager']['total'];
		$nextPage = 2;

		$i = 0;

		while ($nextPage <= $allPages) {
			$params["PRICEPAGE"] = $nextPage;
			$response = $this->requestData('SearchHotel_PRICES', $params);

			$prices[] = $response['prices'];
			$nextPage++;
			$i++;
			if ($i > 350) {
				break;
			}
		}

		$map = $this->getAvailabilityMap();

		foreach ($prices as $priceSet) {
			foreach ($priceSet as $price) {

				$hotelId = $price['hotelKey'];

				$roomId = $price['roomKey'];
				$roomName = $price['room'];

				$mealId = $price['mealKey'];
				$mealName = $price['meal'];

				$priceNet = $price['price'];
				$offerCurrency = $price['currency'];

				$offerCheckInDT = new DateTime($price['checkIn']);
				$offerCheckOutDT = new DateTime($price['checkOut']);

				$adults = $price['adult'];
				$availability  = null;

				if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
					if (isset($map[$price['hotelAvailability']])) {
						$availability = $map[$price['hotelAvailability']];
					} else {
						$availability = Offer::AVAILABILITY_NO;
					}
				} else {
					// hotelAvailability can be also N or NNNN
					if ($price['hotelAvailability'] === 'Y') {
						$availability = Offer::AVAILABILITY_YES;
					} else {
						if ($price['hotelAvailability'] === 'R' || $price['hotelAvailability'] === 'RRRR') {
							$availability = Offer::AVAILABILITY_ASK;
						} else {
							$availability = Offer::AVAILABILITY_NO;
						}
					}
				}

				$roomInfo = $price['programType'];
				$comission = 0;
				$bookingData = [
					'claim' => $price['id'],
					'tour_code' => $price['tourKey'],
					'main_country_code' => $filter->countryId,
					'start_date' => $offerCheckInDT->format('Y-m-d')
				];
				$bookingDataJson = json_encode($bookingData);

				$offer = $this->createIndividualOffer(
					$hotelId,
					$roomId,
					$roomId,
					$roomName,
					$mealId,
					$mealName,
					$offerCheckInDT,
					$offerCheckOutDT,
					$adults,
					$childrenAges,
					$offerCurrency,
					$priceNet,
					$priceNet,
					$priceNet,
					$comission,
					$availability,
					$roomInfo,
					$bookingDataJson
				);

				$availability = $availabilities->get($hotelId);

				if ($availability === null) {
					$availability = new Availability();
					$availability->Id = $hotelId;
					if ($filter->showHotelName) {
						$availability->Name = $price['hotel'];
					}

					$offers = new OfferCollection();
				} else {
					$offers = $availability->Offers;
				}

				$offers->put($offer->Code, $offer);
				$availability->Offers = $offers;
				$availabilities->put($hotelId, $availability);
			}
		}

		return $availabilities;
	}

	private static function GUIDv4(): string
	{
		// OSX/Linux
		if (function_exists('openssl_random_pseudo_bytes') === true) {
			$data = openssl_random_pseudo_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		} else {
			return uniqid();
		}
	}

	public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
	{
		AnexValidator::make()->validateAllCredentials($this->post)
			->validateOfferPaymentPlansFilter($filter);

		$bookingData = $post['args'][0]['OriginalOffer']['bookingDataJson'];
		$bookingData = json_decode($bookingData, true);

		$offerId = $bookingData['claim'];

		if ($this->handle === Handles::ANEX) {

			$options['body'] = 'username=' . $this->username . '&password=' . $this->password;

			$respAuth = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/auth/token', $options)->getBody();

			$respAuth = json_decode($respAuth, true);

			$options['body'] = 'cat_claim=' . $offerId . '&id=' . $this->GUIDv4() . '&currency=4';

			$token = $respAuth['token'];

			$options['headers'] = [
				'Authorization' => 'Bearer ' . $token
			];

			$respStart = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/bron/start', $options);
			$contentStart = $respStart->getBody();

			$respStart = json_decode($contentStart, true);

			$options['body'] = 'id=' . $respStart['bron']['doc']['id'] . '&lang=ro';

			$resp = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/bron/calcfull', $options);

			$content = $resp->getBody();

			$resp = json_decode($content, true);
			$price = $resp['bron']['claim']['claimDocument']['moneys']['money'][0]['price'];

			$currency = new Currency();
			$offCurrency = $resp['bron']['claim']['claimDocument']['moneys']['money'][0]['currency'];

			return [[], [], null, $price, $price, $offCurrency];
		}

		$xmlStrPre = $this->preBook($offerId);
		$xmlPre = simplexml_load_string($xmlStrPre);

		$order = new Booking();

		if (!isset($xmlPre->claim->claimDocument['currency'])) {
			return [$order, 'pre booking error, check logs'];
		}

		$ages = $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : [];

		$priceXmlStr = $this->recalculatePrice($xmlPre, $post['args'][0]['rooms'][0]['adults'], $ages, $post['args'][0]['CheckIn']);
		$priceXml = simplexml_load_string($priceXmlStr);

		if (empty($priceXml->claim->claimDocument->moneys->money[0]['price'])) {
			return [];
		}

		$offPrice = (string) $priceXml->claim->claimDocument->moneys->money[0]['price'];

		$offFees = new OfferCancelFeeCollection();
		$offPayments = new OfferPaymentPolicyCollection();

		$currency = new Currency();
		$offCurrency = (string) $priceXml->claim->claimDocument->moneys->money[0]['currency'];
		$currency->Code = $offCurrency;

		if ($this->handle === Handles::JOINUP_V2 || $this->handle === Handles::JOINUP_TEST) {

			if ($filter->__type__ === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
				$responseCpol = $this->requestData('SearchTour_CANCEL_POLICIES');

				$departureDate = new DateTimeImmutable($bookingData['start_date']);

				$generalPolicies = [];
				$policiesPerCountry = [];
				$policiesPerTours = [];

				foreach ($responseCpol as $response) {
					$tourStartFrom = new DateTimeImmutable($response['start_date_of_tour_from']);
					$tourStartTo = new DateTimeImmutable($response['start_date_of_tour_to']);

					if ($departureDate >= $tourStartFrom && $departureDate <= $tourStartTo) {

						if ($response['tour_code'] === 1 && $response['main_country_code'] === '') {
							$generalPolicies[$response['days_till_tour_start_to']] = $response;
						} elseif ($response['tour_code'] === 1 && $response['main_country_code'] !== '') {
							$policiesPerCountry[$response['main_country_code']]['days_till_tour_start_to'] = $response;
						} elseif ($response['tour_code'] !== 1) {
							$policiesPerTours[$response['tour_code']]['days_till_tour_start_to'] = $response;
						} else {
							throw new Exception($this->handle . ': cancellation policy error');
						}
					}
				}

				krsort($generalPolicies);
				$generalPoliciesSorted = array_values($generalPolicies);

				$policiesPerCountriesSorted = [];
				foreach ($policiesPerCountry as $k => $policiesPerCountry) {
					krsort($policiesPerCountry);
					$policiesPerCountriesSorted[$k] = array_values($policiesPerCountry);
				}

				$policiesPerToursSorted = [];
				foreach ($policiesPerTours as $k => $policiesPerTour) {
					krsort($policiesPerTours);
					$policiesPerToursSorted[$k] = array_values($policiesPerTour);
				}

				$finalPolicies = [];

				$tourCode = $bookingData['tour_code'];
				$countryCode = $bookingData['main_country_code'];

				// find cancel policies with the tour code
				if (isset($policiesPerToursSorted[$tourCode])) {
					$tourPolicies = $policiesPerToursSorted[$tourCode];
					foreach ($tourPolicies as $tourPolicy) {
						$finalPolicies[] = $tourPolicy;
					}
					// check the final policy days
					if (!empty($policy['days_till_tour_start_from'])) {
						// search from other specificities
						if (isset($policiesPerCountriesSorted[$countryCode])) {
							$policiesFromCountry = $policiesPerCountriesSorted[$countryCode];

							// search
							foreach ($policiesFromCountry as $policyFromCountry) {
								if ($tourPolicy['days_till_tour_start_from'] > $policyFromCountry['days_till_tour_start_from']) {
									$finalPolicies[] = $policyFromCountry;
								}
							}
							if (!empty($policyFromCountry['days_till_tour_start_from'])) {
								foreach ($generalPoliciesSorted as $generalPolicy) {
									if ($policyFromCountry['days_till_tour_start_from'] > $generalPolicy['days_till_tour_start_from']) {
										$finalPolicies[] = $generalPolicy;
									}
								}
							}
						}
					}
				}
				// find cancel policies with the country code
				if (empty($finalPolicies) && isset($policiesPerCountriesSorted[$countryCode])) {
					$policiesFromCountry = $policiesPerCountriesSorted[$countryCode];

					// search
					foreach ($policiesFromCountry as $policyFromCountry) {
						$finalPolicies[] = $policyFromCountry;
					}
					if (!empty($policyFromCountry['days_till_tour_start_from'])) {
						foreach ($generalPolicies as $generalPolicy) {
							if ($policyFromCountry['days_till_tour_start_from'] > $generalPolicy['days_till_tour_start_from']) {
								$finalPolicies[] = $generalPolicy;
							}
						}
					}
				}

				if (empty($finalPolicies)) {
					$finalPolicies = $generalPoliciesSorted;
				}

				// if selected general policies
				for ($i = 0; $i < count($finalPolicies); $i++) {
					$cp = new OfferCancelFee();

					if ($finalPolicies[$i]['days_till_tour_start_to'] !== '') {
						$cp->DateStart = $departureDate->modify('-' . $finalPolicies[$i]['days_till_tour_start_to'] . ' days')->format('Y-m-d');
					} else {
						$cp->DateStart = date('Y-m-d');
					}

					if (isset($finalPolicies[$i + 1])) {
						$cp->DateEnd = $departureDate->modify('-' . ($finalPolicies[$i + 1]['days_till_tour_start_to'] + 1) . ' days')->format('Y-m-d');
					} else {
						$cp->DateEnd = $bookingData['start_date'];
					}

					$cp->Currency = $currency;

					$percent = $finalPolicies[$i]['percentage'];

					$cp->Price = $offPrice * $percent / 100;
					$offFees->add($cp);
				}
			}
		}

		$offAvailability = null;
		$offInitialPrice = $offPrice;


		return [$offFees, $offPayments, $offAvailability, $offPrice, $offInitialPrice, $offCurrency];
	}

	private function recalculatePrice(SimpleXMLElement $xml, int $adults, array $childrenAges, string $checkIn)
	{
		$xml->claim->claimDocument['guid'] = '{' . $this->GUIDv4() . '}';

		$xml = $xml->claim->asXML();

		$data =
			'<WorkRequest version="3.0">
				<proc>GET_BRON_PRICE_FOR_AGENT</proc>
				<params>
					' . $xml . '
					<NAME>' . $this->username . '</NAME>
					<PSW>' . $this->password . '</PSW>
				</params>
			</WorkRequest>';

		$crypt = new CryptAes();
		$crypt->setKey($this->bookingApiPassword);

		$encryptedData = base64_encode($crypt->encrypt(gzencode($data)));

		$created = date('Y-m-d\TH:i:s\Z');

		$nonce = uniqid();

		$soapPassword = base64_encode(sha1($nonce . $created . ($this->bookingApiPassword)));

		$xmlString = '<SOAP-ENV:Envelope  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
		xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
		xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
		xmlns:ns6="http://www.samo.ru/xml"
		SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
		   <SOAP-ENV:Header>
			   <wsse:Security SOAP-ENV:actor="http://schemas.xmlsoap.org/soap/actor/next" SOAP-ENV:mustUnderstand="0">
				   <wsse:UsernameToken>
					   <wsse:Username>' . $this->bookingApiUsername . '</wsse:Username>
					   <wsse:Password xsi:type="wsse:PasswordDigest">' . $soapPassword . '</wsse:Password>
					   <wsse:Nonce>' . base64_encode($nonce) . '</wsse:Nonce>
					   <wsu:Created>' . $created . '</wsu:Created>
				   </wsse:UsernameToken>
			   </wsse:Security>
			   <ns6:agentinfo SOAP-ENV:actor="http://schemas.xmlsoap.org/soap/actor/next" SOAP-ENV:mustUnderstand="0">
				   <version xsi:type="xsd:string">3.0</version>
			   </ns6:agentinfo>
		   </SOAP-ENV:Header>
		   <SOAP-ENV:Body>
			   <ns6:WORK>
				   <data xsi:type="xsd:string">' . $encryptedData . '</data>
			   </ns6:WORK>
		   </SOAP-ENV:Body>
	    </SOAP-ENV:Envelope>'; //<answer xsi:type="xsd:string">1</answer>

		$options['body'] = $xmlString;

		$resp = $this->client->request(RequestFactory::METHOD_POST, $this->bookingUrl, $options);
		$options['xmlRequest'] = $xml; // only for logging

		$content = $resp->getBody();

		$contentXml = simplexml_load_string($content);

		$contentBody =  $contentXml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;

		if (!empty($contentBody->children('http://schemas.xmlsoap.org/soap/envelope/')->Fault->children('')->faultstring)) {
			throw new Exception((string) $contentBody->children('http://schemas.xmlsoap.org/soap/envelope/')->Fault->children('')->faultstring);
		}

		$contentEncoded	= (string) $contentBody->children('http://www.samo.ru/xml')->WORKResponse->children('')->return;

		$xml = $crypt->decrypt(base64_decode($contentEncoded));
		$xml = gzinflate(substr($xml, 10, -8));

		$options['xmlResponse'] = $xml; // only for logging

		$this->showRequest(RequestFactory::METHOD_POST, $this->bookingUrl, $options, $content, $resp->getStatusCode());

		return $xml;
	}

	private function getAvailabilityMap(): array
	{
		return [
			'YYYY'	=> 'yes',
			'Y'		=> 'yes',
			'F'		=> 'yes',
			'FFFF'	=> 'yes',
			'R'		=> 'ask',
			'RRRR'	=> 'ask',
			'RYYY'	=> 'ask',
			'RRYY'	=> 'ask',
			'RRRY'	=> 'ask',
			'RYRY'	=> 'ask',
			'RYRR'	=> 'ask',
			'RYYR'	=> 'ask',
			'YRRR'	=> 'ask',
			'YYRR'	=> 'ask',
			'YYYR'	=> 'ask',
			'YRYR'	=> 'ask',
			'YRRY'	=> 'ask',
		];
	}

	private function createCharterOffer(
		string $hotelId,
		string $roomId,
		string $roomTypeId,
		string $roomName,
		string $mealId,
		string $mealName,
		DateTimeInterface $offerCheckInDT,
		DateTimeInterface $offerCheckOutDT,
		string $adults,
		?array $childrenAges,
		string $offerCurrency,
		float $priceNet,
		float $priceInitial,
		float $priceGross,
		float $comission,
		string $availability,
		?string $roomInfo,
		DateTimeInterface $flightDepartureDT,
		DateTimeInterface $flightDepartureArrivalDT,
		DateTimeInterface $flightReturnDepartureDT,
		DateTimeInterface $flightReturnArrivalDT,
		string $departureAirport,
		string $departureArrivalAirport,
		string $returnDepartureAirport,
		string $returnArrivalAirport,
		City $departureCity,
		City $departureArrivalCity,
		City $returnDepartureCity,
		City $returnArrivalCity,
		?string $bookingDataJson
	): Offer {
		$offer = new Offer();

		$offerCheckIn = $offerCheckInDT->format('Y-m-d');
		$offerCheckOut = $offerCheckOutDT->format('Y-m-d');

		$offer->Code = $hotelId . '~' . $roomId . '~' . $mealId . '~' . $offerCheckIn . '~' . $offerCheckOut . '~' . $priceNet . '~' . $adults . ($childrenAges ? '~' . implode('|', $childrenAges) : '');

		$currency = new Currency();
		$currency->Code = $offerCurrency;
		$offer->Currency = $currency;

		$offer->Availability = $availability;

		$offer->Net = $priceNet;
		$offer->InitialPrice = $priceInitial;
		$offer->Gross = $priceGross;
		$offer->Comission = $comission;

		$room = new Room();
		$room->Availability = $availability;
		$room->CheckinAfter = $offerCheckIn;
		$room->CheckinBefore = $offerCheckOut;
		$room->Currency = $currency;
		$room->Id = $roomId;
		$roomMerch = new RoomMerch();
		$roomMerch->Code = $roomId;
		$roomMerch->Id = $roomId;
		$roomMerch->Name = $roomName;
		$roomMerch->Title = $roomName;

		$roomMerchType = new RoomMerchType();
		$roomMerchType->Id = $roomTypeId;
		$roomMerchType->Title = $roomName;

		$roomMerch->Type = $roomMerchType;

		$room->Merch = $roomMerch;
		$room->Code = $roomTypeId;

		$room->InfoTitle = $roomInfo;

		$offer->Item = $room;
		$rooms = new RoomCollection([$room]);
		$offer->Rooms = $rooms;

		$mealItem = new MealItem();
		$mealItem->Currency = $currency;
		$mealMerch = new MealMerch();
		$mealMerch->Id = $mealId;
		$mealMerch->Title = $mealName;

		$mealMerchType = new MealMerchType();
		$mealMerchType->Id = $mealMerch->Id;
		$mealMerchType->Title = $mealMerch->Title;
		$mealMerch->Type = $mealMerchType;
		$mealItem->Merch = $mealMerch;
		$offer->MealItem = $mealItem;


		// departure transport item merch
		$departureTransportMerch = new TransportMerch();
		$departureTransportMerch->Title = 'Dus: ' . $flightDepartureDT->format('d.m.Y');
		$departureTransportMerch->Category = new TransportMerchCategory();
		$departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
		$departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
		$departureTransportMerch->DepartureTime = $flightDepartureDT->format('Y-m-d H:i');
		$departureTransportMerch->ArrivalTime = $flightDepartureArrivalDT->format('Y-m-d H:i');

		$departureTransportMerch->DepartureAirport = $departureAirport;
		$departureTransportMerch->ReturnAirport = $departureArrivalAirport;

		$departureTransportMerch->From = new TransportMerchLocation();
		$departureTransportMerch->From->City = $departureCity;

		$departureTransportMerch->To = new TransportMerchLocation();
		$departureTransportMerch->To->City = $departureArrivalCity;

		$departureTransportItem = new DepartureTransportItem();
		$departureTransportItem->Merch = $departureTransportMerch;
		$departureTransportItem->Currency = $offer->Currency;
		$departureTransportItem->DepartureDate = $flightDepartureDT->format('Y-m-d');
		$departureTransportItem->ArrivalDate = $flightDepartureArrivalDT->format('Y-m-d');

		// return transport item
		$returnTransportMerch = new TransportMerch();
		$returnTransportMerch->Title = "Retur: " . $flightReturnDepartureDT->format('d.m.Y');
		$returnTransportMerch->Category = new TransportMerchCategory();
		$returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
		$returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
		$returnTransportMerch->DepartureTime = $flightReturnDepartureDT->format('Y-m-d H:i');
		$returnTransportMerch->ArrivalTime = $flightReturnArrivalDT->format('Y-m-d H:i');

		$returnTransportMerch->DepartureAirport = $returnDepartureAirport;
		$returnTransportMerch->ReturnAirport = $returnArrivalAirport;

		$returnTransportMerch->From = new TransportMerchLocation();
		$returnTransportMerch->From->City = $returnDepartureCity;

		$returnTransportMerch->To = new TransportMerchLocation();
		$returnTransportMerch->To->City = $returnArrivalCity;

		$returnTransportItem = new ReturnTransportItem();
		$returnTransportItem->Merch = $returnTransportMerch;
		$returnTransportItem->Currency = $offer->Currency;
		$returnTransportItem->DepartureDate = $flightReturnDepartureDT->format('Y-m-d');
		$returnTransportItem->ArrivalDate = $flightReturnArrivalDT->format('Y-m-d');

		$departureTransportItem->Return = $returnTransportItem;

		$offer->DepartureTransportItem = $departureTransportItem;
		$offer->ReturnTransportItem = $returnTransportItem;

		$offer->bookingDataJson = $bookingDataJson;

		return $offer;
	}

	private function createIndividualOffer(
		string $hotelId,
		string $roomId,
		string $roomTypeId,
		string $roomName,
		string $mealId,
		string $mealName,
		DateTimeInterface $offerCheckInDT,
		DateTimeInterface $offerCheckOutDT,
		string $adults,
		?array $childrenAges,
		string $offerCurrency,
		float $priceNet,
		float $priceInitial,
		float $priceGross,
		float $comission,
		string $availability,
		?string $roomInfo,
		?string $bookingDataJson
	): Offer {
		$offer = new Offer();

		$offerCheckIn = $offerCheckInDT->format('Y-m-d');
		$offerCheckOut = $offerCheckOutDT->format('Y-m-d');

		$offer->Code = $hotelId . '~' . $roomId . '~' . $mealId . '~' . $offerCheckIn . '~' . $offerCheckOut . '~' . $priceNet . '~' . $adults . ($childrenAges ? '~' . implode('|', $childrenAges) : '');

		$currency = new Currency();
		$currency->Code = $offerCurrency;
		$offer->Currency = $currency;

		$offer->Availability = $availability;

		$offer->Net = $priceNet;
		$offer->InitialPrice = $priceInitial;
		$offer->Gross = $priceGross;
		$offer->Comission = $comission;

		$room = new Room();
		$room->Availability = $availability;
		$room->CheckinAfter = $offerCheckIn;
		$room->CheckinBefore = $offerCheckOut;
		$room->Currency = $currency;
		$room->Id = $roomId;
		$roomMerch = new RoomMerch();
		$roomMerch->Code = $roomId;
		$roomMerch->Id = $roomId;
		$roomMerch->Name = $roomName;
		$roomMerch->Title = $roomName;

		$roomMerchType = new RoomMerchType();
		$roomMerchType->Id = $roomTypeId;
		$roomMerchType->Title = $roomName;

		$roomMerch->Type = $roomMerchType;

		$room->Merch = $roomMerch;
		$room->Code = $roomTypeId;

		$room->InfoTitle = $roomInfo;

		$offer->Item = $room;
		$rooms = new RoomCollection([$room]);
		$offer->Rooms = $rooms;

		$mealItem = new MealItem();
		$mealItem->Currency = $currency;
		$mealMerch = new MealMerch();
		$mealMerch->Id = $mealId;
		$mealMerch->Title = $mealName;

		$mealMerchType = new MealMerchType();
		$mealMerchType->Id = $mealMerch->Id;
		$mealMerchType->Title = $mealMerch->Title;
		$mealMerch->Type = $mealMerchType;
		$mealItem->Merch = $mealMerch;
		$offer->MealItem = $mealItem;

		// departure transport item merch
		$departureTransportItemMerch = new TransportMerch();
		$departureTransportItemMerch->Title = 'CheckIn: ' . $offerCheckInDT->format('d.m.Y');

		// DepartureTransportItem Return Merch
		$departureTransportItemReturnMerch = new TransportMerch();
		$departureTransportItemReturnMerch->Title = 'CheckOut: ' . $offerCheckOutDT->format('d.m.Y');

		// DepartureTransportItem Return
		$departureTransportItemReturn = new ReturnTransportItem();
		$departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
		$departureTransportItemReturn->Currency = $currency;
		$departureTransportItemReturn->DepartureDate = $offerCheckOut;
		$departureTransportItemReturn->ArrivalDate = $offerCheckOut;

		// DepartureTransportItem
		$departureTransportItem = new DepartureTransportItem();
		$departureTransportItem->Merch = $departureTransportItemMerch;
		$departureTransportItem->Currency = $currency;
		$departureTransportItem->DepartureDate = $offerCheckIn;
		$departureTransportItem->ArrivalDate = $offerCheckIn;
		$departureTransportItem->Return = $departureTransportItemReturn;

		$offer->DepartureTransportItem = $departureTransportItem;
		$offer->ReturnTransportItem = $departureTransportItemReturn;

		$offer->bookingDataJson = $bookingDataJson;

		return $offer;
	}

	private function getCurrencyId(AvailabilityFilter $filter): int
	{

		$params = ['STATEINC' => $filter->countryId, 'TOWNFROMINC' => $filter->departureCity];
		$currencies = $this->requestData('SearchTour_CURRENCIES', $params);

		$id = 0;
		foreach ($currencies as $currency) {
			if ($currency['name'] === 'EUR') {
				$id = $currency['id'];
				break;
			}
		}

		return $id;
	}

	private function getCharterOrTourOffersForJoinUp(AvailabilityFilter $filter): array
	{
		AnexValidator::make()->validateUsernameAndPassword($this->post)
			->validateCharterOffersFilter($filter);

		if (empty($filter->departureCity)) {
			$filter->departureCity = $filter->departureCityId;
		}

		$availabilities = [];

		//$cities = $this->apiGetCities();
		$childrenAges = $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : null;

		$checkInDT = new DateTimeImmutable($filter->checkIn);
		$checkIn = $checkInDT->format('Ymd');
		//$checkOutDT = $checkInDT->modify('+' . $filter->days . ' days');

		$cities = $this->apiGetCities();
		//$regions = $this->apiGetRegions();

		$currencyId = $this->getCurrencyId($filter);


		// todo: countryId trebuie sa vina
		// todo: vine pe circuite?
		$countryId = $filter->countryId;
		// if (empty($filter->countryId)) {
		// 	if (empty($filter->cityId)) {
		// 		$countryId = $regions->get($filter->regionId)->Country->Id;
		// 	} else {
		// 		$countryId = $cities->get($filter->cityId)->Country->Id;
		// 	}
		// }

		// $exoticCountries = [133, 136, 134];

		// $tourType = null;
		// if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
		// 	$tourType = 2;
		// } else {
		// 	$tourType = in_array($countryId, $exoticCountries) ? 15 : 3;
		// }

		$location = '';

		if (empty($filter->cityId)) {
			$citiesSelected = $cities->filter(fn(City $city) => ($city->County->Id ?? '?') === $filter->regionId);
			foreach ($citiesSelected as $citySelected) {
				$location .= $citySelected->Id . ',';
			}
			$location = rtrim($location, ',');
		} else {
			$location = $filter->cityId;
		}

		$params = [
			'STATEINC' => $countryId,
			'TOWNFROMINC' => $filter->departureCity,
			'CHECKIN_BEG' => $checkIn,
			'CHECKIN_END' => $checkIn,
			'NIGHTS_FROM' => $filter->days,
			'NIGHTS_TILL' => $filter->days,
			'ADULT' => $filter->rooms->first()->adults,
			'CURRENCY' => $currencyId,
			'FREIGHT' => 1,
			'STATEFROM' => self::JOIN_UP_ROMANIA,
			'TOWNS' => $location
		];


		if (!empty($filter->hotelId)) {
			$params["HOTELS"] = $filter->hotelId;
		}

		if (!empty($post['args'][0]['rooms'][0]['children'])) {
			$params['CHILD'] = $post['args'][0]['rooms'][0]['children'];

			$params['AGES'] = '';
			foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $childrenAge) {
				$params['AGES'] = $params['AGES'] . $childrenAge . ',';
			}

			$params['AGES'] = rtrim($params['AGES'], ",");
		}

		$prices = [];

		$paramsTours = [
			'STATEINC' => $countryId,
			'TOWNFROMINC' => $filter->departureCity,
			//'TOURTYPE' => $tourType
		];
		$toursResp = $this->requestData('SearchTour_Tours', $paramsTours);

		foreach ($toursResp as $tourResp) {
			$tourName = $tourResp['name'];

			if (strpos($tourName, 'Ukraine') !== false || strpos($tourName, 'Moldova') !== false) {
				continue;
			}

			if (
				$filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER
				&& $tourResp['typeKey'] !== self::TYPE_CHARTER
			) {

				continue;
			}

			$params['TOURINC'] = $tourResp['id'];


			$response = $this->requestData('SearchTour_PRICES', $params);

			if (empty($response['pager'])) {
				continue;
			}
			$prices[] = [$response['prices'], $tourResp];

			$allPages = $response['pager']['total'];
			$currentPage = $response['pager']['current'];

			$nextPage = $currentPage + 1;

			$i = 0;

			while ($nextPage <= $allPages) {
				$params["PRICEPAGE"] = $nextPage;
				$response = $this->requestData('SearchTour_PRICES', $params);
				unset($params["PRICEPAGE"]);

				$prices[] = [$response['prices'], $tourResp];
				$nextPage++;
				$i++;
				if ($i > 350) {
					break;
				}
			}
		}



		//$citiesFilter = [];
		//if ($this->handle !== self::JOIN_UP && $this->handle !== self::JOIN_UP_TEST) {
		//$location = '';

		// if (empty($filter->cityId)) {
		// 	$citiesSelected = $cities->filter(fn(City $city) => 
		// 		($city->County->Id ?? '?') === $filter->regionId);
		// 	foreach ($citiesSelected as $citySelected) {
		// 		$location .= $citySelected->Id . ',';
		// 	}
		// 	$location = rtrim($location, ',');

		//} else {
		//$location = $filter->cityId;
		//}
		//$params['TOWNS'] = $location;
		// } else {
		// 	if (empty($filter->cityId)) {
		// 		$citiesSelected = $cities->filter(fn(City $city) => 
		// 			($city->County->Id ?? '?') === $filter->regionId);
		// 		foreach ($citiesSelected as $citySelected) {
		// 			$citiesFilter[] = $citySelected->Id;
		// 		}

		// 	} else {
		// 		$citiesFilter[] = $filter->cityId;
		// 	}
		// }


		// $countries = $this->apiGetCountries();


		//$countries->first(fn(Country $country) => $country->Code === 'RO')->Id;


		$map = $this->getAvailabilityMap();

		$airportMap = $this->getAirportMap();

		$flights = [];
		foreach ($prices as $priceSet) {
			foreach ($priceSet[0] as $price) {

				// if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
				// 	if (!in_array($price['townKey'], $citiesFilter)) {
				// 		continue;
				// 	}
				// }

				$roomId = $price['roomKey'];
				$roomName = $price['room'];
				$roomInfo = $price['programType'];

				$mealId = $price['mealKey'];
				$mealName = $price['meal'];

				$priceNet = $price['price'];
				$offerCurrency = $price['currency'];
				$comission = 0;

				$offerCheckInDT = new DateTime($price['checkIn']);
				$offerCheckOutDT = new DateTime($price['checkOut']);

				$adults = $price['adult'];
				$availability  = null;


				if (isset($map[$price['hotelAvailability']])) {
					$availability = $map[$price['hotelAvailability']];
				} else {
					$availability = Offer::AVAILABILITY_NO;
				}

				$freightResp = null;
				if (!isset($flights[$price['tourKey']])) {

					$departureFlightInfo = null;
					$returnFlightInfo = null;
					$departureFlightDate = null;
					$returnFlightDate = null;

					$freightsParams = [
						'CATCLAIM' => $price['id']
					];

					$freightResp = $this->requestData('FreightMonitor_FREIGHTSBYPACKET', $freightsParams);

					if (empty($freightResp['routes'])) {
						continue;
					}

					$flights[$price['tourKey']] = $freightResp;
				} else {
					$freightResp = $flights[$price['tourKey']];
				}

				$departureFlightInfo = $freightResp['routes'][0]['freights'][0];
				$departureFlightDate = $freightResp['routes'][0]['info']['date'];

				$returnFlightInfo = $freightResp['routes'][1]['freights'][0];
				$returnFlightDate = $freightResp['routes'][1]['info']['date'];

				$hotelId = $price['hotelKey'];

				$flightDepartureDT = new DateTime($departureFlightDate . ' ' . $departureFlightInfo['departure']['time']);
				$flightDepartureArrivalDT = new DateTime($departureFlightDate . ' ' . $departureFlightInfo['arrival']['time']);
				$flightReturnDepartureDT = new DateTime($returnFlightDate . ' ' . $returnFlightInfo['departure']['time']);
				$flightReturnArrivalDT = new DateTime($returnFlightDate . ' ' . $returnFlightInfo['arrival']['time']);

				$departureAirport = $airportMap[$departureFlightInfo['departure']['portKey']];
				$departureArrivalAirport = $airportMap[$departureFlightInfo['arrival']['portKey']];
				$returnDepartureAirport = $airportMap[$returnFlightInfo['departure']['portKey']];
				$returnArrivalAirport = $airportMap[$returnFlightInfo['arrival']['portKey']];

				$departureCity = $cities->get((string) $departureAirport['town']);
				$departureArrivalCity = $cities->get((string) $departureArrivalAirport['town']);
				$returnDeparturelCity = $cities->get((string) $returnDepartureAirport['town']);
				$returnArrivalCity = $cities->get((string) $returnArrivalAirport['town']);

				$bookingData = [
					'claim' => $price['id'],
					'tour_code' => $price['tourKey'],
					'main_country_code' => $departureArrivalCity->Country->Id,
					'start_date' => $flightDepartureDT->format('Y-m-d')
				];
				$bookingDataJson = json_encode($bookingData);

				$offer = Offer::createCharterOrTourOffer(
					$hotelId,
					$roomId,
					$roomId,
					$roomName,
					$mealId,
					$mealName,
					$offerCheckInDT,
					$offerCheckOutDT,
					$adults,
					$childrenAges,
					$offerCurrency,
					$priceNet,
					$priceNet,
					$priceNet,
					$comission,
					$availability,
					$roomInfo,
					$flightDepartureDT,
					$flightDepartureArrivalDT,
					$flightReturnDepartureDT,
					$flightReturnArrivalDT,
					$departureAirport['alias'],
					$departureArrivalAirport['alias'],
					$returnDepartureAirport['alias'],
					$returnArrivalAirport['alias'],
					$filter->transportTypes->first(),
					$departureCity,
					$departureArrivalCity,
					$returnDeparturelCity,
					$returnArrivalCity,
					$bookingDataJson,
					true
				);

				$availability = $availabilities->get($hotelId);

				if ($availability === null) {
					$availability = new Availability();
					$availability->Id = $hotelId;
					if ($filter->showHotelName) {
						$availability->Name = $price['hotel'];
					}

					$offers = new OfferCollection();
				} else {
					$offers = $availability->Offers;
				}

				$offers->put($offer->Code, $offer);
				$availability->Offers = $offers;
				$availabilities->put($hotelId, $availability);
			}
		}

		return $availabilities;
	}

	private function getCharterOrTourOffersForPrestige(AvailabilityFilter $filter): array
	{
		AnexValidator::make()->validateUsernameAndPassword($this->post)
			->validateCharterOffersFilter($filter);

		if (empty($filter->departureCity)) {
			$filter->departureCity = $filter->departureCityId;
		}

		$availabilities = [];

		//$cities = $this->apiGetCities();
		$childrenAges = $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : null;

		$checkInDT = new DateTimeImmutable($filter->checkIn);
		$checkIn = $checkInDT->format('Ymd');
		//$checkOutDT = $checkInDT->modify('+' . $filter->days . ' days');

		$cities = $this->apiGetCities();
		$regions = $this->apiGetRegions();

		//$currencyId = $this->getCurrencyId($filter);
		$currencyId = 4;

		$countryId = null;

		if (empty($filter->countryId)) {
			if (empty($filter->cityId)) {
				$countryId = $regions->get($filter->regionId)->Country->Id;
			} else {
				$countryId = $cities->get($filter->cityId)->Country->Id;
			}
		} else {
			$countryId = $filter->countryId;
		}

		//$exoticCountries = [133, 136, 134];

		// $tourType = null;
		// if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
		// 	$tourType = 2;
		// } else {
		// 	$tourType = in_array($countryId, $exoticCountries) ? 15 : 3;
		// }

		$location = '';

		if (empty($filter->cityId)) {
			$citiesSelected = $cities->filter(fn(City $city) => ($city->County->Id ?? '?') === $filter->regionId);
			foreach ($citiesSelected as $citySelected) {
				$location .= $citySelected->Id . ',';
			}
			$location = rtrim($location, ',');
		} else {
			$location = $filter->cityId;
		}

		$params = [
			'STATEINC' => $countryId,
			'TOWNFROMINC' => $filter->departureCity,
			'CHECKIN_BEG' => $checkIn,
			'CHECKIN_END' => $checkIn,
			'NIGHTS_FROM' => $filter->days,
			'NIGHTS_TILL' => $filter->days,
			'ADULT' => $filter->rooms->first()->adults,
			'CURRENCY' => $currencyId,
			'FREIGHT' => 1,
			'TOWNS' => $location,
			'STATEFROM' => ($this->handle === Handles::PRESTIGE_V2 || $this->handle === Handles::PRESTIGE_TEST) ?
				self::PRESTIGE_ROMANIA :
				self::ANEX_ROMANIA
		];

		if (!empty($filter->hotelId)) {
			if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
				$params["HOTELS"] = explode('-', $filter->hotelId)[2];
			} else {
				$params["HOTELS"] = $filter->hotelId;
			}
		}

		if (!empty($post['args'][0]['rooms'][0]['children'])) {
			$params['CHILD'] = $post['args'][0]['rooms'][0]['children'];

			$params['AGES'] = '';
			foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $childrenAge) {
				$params['AGES'] = $params['AGES'] . $childrenAge . ',';
			}

			$params['AGES'] = rtrim($params['AGES'], ",");
		}

		$paramsTours = [
			'STATEINC' => $countryId,
			'TOWNFROMINC' => $filter->departureCity,
		];

		/*
		$toursResp = $this->requestData('SearchTour_Tours', $paramsTours);

		$flights = [];
		$prices = [];
		foreach ($toursResp as $tourResp) {

			if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR && !empty($filter->hotelId)) {
				if ($tourResp['id'] != explode('-', $filter->hotelId)[0]) {
					continue;
				}
			}

			if (
				$filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER
				&& $tourResp['typeKey'] !== self::TYPE_CHARTER
			) {

				continue;
			}
			if (
				$filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR
				&& !in_array($tourResp['typeKey'], [self::TYPE_TOUR_EXOTIC, self::TYPE_TOUR_TOUR])
			) {

				continue;
			}

			$params['TOURINC'] = $tourResp['id'];
			*/



		// todo: pricepage?

		//$response = $this->requestData('SearchTour_PRICES', $params);
		$url = $this->apiUrl . '/samo/SearchTour/PRICES?' .
			'TOWNFROMINC=' . $filter->departureCity .
			'&STATEINC=' . $countryId .
			'&CHECKIN_BEG=' . $checkIn .
			'&CHECKIN_END=' . $checkIn .
			'&CURRENCY=4' .
			'&NIGHTS_FROM=' . $filter->days .
			'&NIGHTS_TILL=' . $filter->days .
			'&ADULT=' . $filter->rooms->first()->adults .
			'&TOWNS=' . $location .
			'&FILTER=1';
		$resp = $this->client->request(RequestFactory::METHOD_GET, $url);
		$content = $resp->getBody();
		$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/townfroms?type=json&xdebug=false', [], $content, $resp->getStatusCode());

		$prices = json_decode($content, true)['SearchTour_PRICES'];
		dd($url, $prices);


		/*
			if (empty($response['pager'])) {
				continue;
			}

			$prices[] = [$response['prices'], $tourResp];

			$allPages = $response['pager']['total'];
			$currentPage = $response['pager']['current'];

			$nextPage = $currentPage + 1;

			$i = 0;

			while ($nextPage <= $allPages) {
				$params["PRICEPAGE"] = $nextPage;
				$response = $this->requestData('SearchTour_PRICES', $params);

				$prices[] = [$response['prices'], $tourResp];
				$nextPage++;
				$i++;
				if ($i > 350) {
					break;
				}
			}*/
		//}



		//$citiesFilter = [];
		//if ($this->handle !== self::JOIN_UP && $this->handle !== self::JOIN_UP_TEST) {
		//$location = '';

		// if (empty($filter->cityId)) {
		// 	$citiesSelected = $cities->filter(fn(City $city) => 
		// 		($city->County->Id ?? '?') === $filter->regionId);
		// 	foreach ($citiesSelected as $citySelected) {
		// 		$location .= $citySelected->Id . ',';
		// 	}
		// 	$location = rtrim($location, ',');

		//} else {
		//$location = $filter->cityId;
		//}
		//$params['TOWNS'] = $location;
		// } else {
		// 	if (empty($filter->cityId)) {
		// 		$citiesSelected = $cities->filter(fn(City $city) => 
		// 			($city->County->Id ?? '?') === $filter->regionId);
		// 		foreach ($citiesSelected as $citySelected) {
		// 			$citiesFilter[] = $citySelected->Id;
		// 		}

		// 	} else {
		// 		$citiesFilter[] = $filter->cityId;
		// 	}
		// }


		// $countries = $this->apiGetCountries();


		//$countries->first(fn(Country $country) => $country->Code === 'RO')->Id;

		$this->getLatestCache = false;
		dd($prices);

		$airportMap = $this->getAirportMap();

		$this->getLatestCache = true;

		$map = $this->getAvailabilityMap();

		$flights = [];

		foreach ($prices as $price) {
			//foreach ($priceSet[0] as $price) {

			//$tourResp = $priceSet[1];
			// if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
			// 	if (!in_array($price['townKey'], $citiesFilter)) {
			// 		continue;
			// 	}
			// }

			$roomId = $price['roomKey'];
			$roomName = $price['room'];
			$roomInfo = null;

			$mealId = $price['mealKey'];
			$mealName = $price['meal'];

			$priceNet = $price['price'];
			$offerCurrency = $price['currency'];
			$comission = 0;

			$offerCheckInDT = new DateTime($price['checkIn']);
			$offerCheckOutDT = new DateTime($price['checkOut']);

			$adults = $price['adult'];
			$availability  = null;


			// hotelAvailability can be also N or NNNN
			// if ($price['hotelAvailability'] === 'Y') {
			// 	$availability = Offer::AVAILABILITY_YES;
			// } else {
			// 	if ($price['hotelAvailability'] === 'R' || $price['hotelAvailability'] === 'RRRR') {
			// 		$availability = Offer::AVAILABILITY_ASK;
			// 	} else {
			// 		$availability = Offer::AVAILABILITY_NO;
			// 	}
			// }

			if (isset($map[$price['hotelAvailability']])) {
				$availability = $map[$price['hotelAvailability']];
			} else {
				$availability = Offer::AVAILABILITY_NO;
			}


			// if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER && $tourResp['typeKey'] !== 2) {
			// 	continue;
			// }

			// if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
			// 	if ($tourResp['typeKey'] !== 3 && $tourResp['typeKey'] !== 15) {
			// 		continue;
			// 	}
			// }

			$freightResp = null;
			if (!isset($flights[$price['tourKey']])) {

				$departureFlightInfo = null;
				$returnFlightInfo = null;
				$departureFlightDate = null;
				$returnFlightDate = null;

				$freightsParams = [
					'CATCLAIM' => $price['id']
				];

				$freightResp = $this->requestData('FreightMonitor_FREIGHTSBYPACKET', $freightsParams);

				if (empty($freightResp['routes'])) {
					continue;
				}

				$flights[$price['tourKey']] = $freightResp;
			} else {
				$freightResp = $flights[$price['tourKey']];
			}

			$departureFlightInfo = $freightResp['routes'][0]['freights'][0];
			$departureFlightDate = $freightResp['routes'][0]['info']['date'];

			if (!isset($freightResp['routes'][1]['freights'][0])) {
				continue;
			}

			$returnFlightInfo = $freightResp['routes'][1]['freights'][0];
			$returnFlightDate = $freightResp['routes'][1]['info']['date'];

			$hotelId = $price['hotelKey'];


			$flightDepartureDT = null;
			$flightDepartureArrivalDT = null;
			$flightReturnDepartureDT = null;
			$flightReturnArrivalDT = null;

			//if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
			$flightDepartureDT = new DateTime($departureFlightDate . ' ' . $departureFlightInfo['departure']['time']);
			$flightDepartureArrivalDT = new DateTime($departureFlightDate . ' ' . $departureFlightInfo['arrival']['time']);
			$flightReturnDepartureDT = new DateTime($returnFlightDate . ' ' . $returnFlightInfo['departure']['time']);
			$flightReturnArrivalDT = new DateTime($returnFlightDate . ' ' . $returnFlightInfo['arrival']['time']);
			//} else {
			//$flightDepartureDT = new DateTime($departureFlightInfo['date'] . ' ' . $departureFlightInfo['departure']['time']);
			//$flightDepartureArrivalDT = new DateTime($departureFlightInfo['date'] . ' ' . $departureFlightInfo['arrival']['time']);
			//$flightReturnDepartureDT = new DateTime($returnFlightInfo['date'] . ' ' . $returnFlightInfo['departure']['time']);
			//$flightReturnArrivalDT = new DateTime($returnFlightInfo['date'] . ' ' . $returnFlightInfo['arrival']['time']);
			//}

			$departureAirport = $airportMap[$departureFlightInfo['departure']['portKey']];
			$departureArrivalAirport = $airportMap[$departureFlightInfo['arrival']['portKey']];
			$returnDepartureAirport = $airportMap[$returnFlightInfo['departure']['portKey']];
			$returnArrivalAirport = $airportMap[$returnFlightInfo['arrival']['portKey']];

			$departureCity = $cities->get((string) $departureAirport['town']);
			$departureArrivalCity = $cities->get((string) $departureArrivalAirport['town']);
			$returnDeparturelCity = $cities->get((string) $returnDepartureAirport['town']);
			$returnArrivalCity = $cities->get((string) $returnArrivalAirport['town']);

			$bookingData = ['claim' => $price['id']];
			$bookingDataJson = json_encode($bookingData);

			$offer = Offer::createCharterOrTourOffer(
				$hotelId,
				$roomId,
				$roomId,
				$roomName,
				$mealId,
				$mealName,
				$offerCheckInDT,
				$offerCheckOutDT,
				$adults,
				$childrenAges,
				$offerCurrency,
				$priceNet,
				$priceNet,
				$priceNet,
				$comission,
				$availability,
				$roomInfo,
				$flightDepartureDT,
				$flightDepartureArrivalDT,
				$flightReturnDepartureDT,
				$flightReturnArrivalDT,
				$departureAirport['alias'],
				$departureArrivalAirport['alias'],
				$returnDepartureAirport['alias'],
				$returnArrivalAirport['alias'],
				$filter->transportTypes->first(),
				$departureCity,
				$departureArrivalCity,
				$returnDeparturelCity,
				$returnArrivalCity,
				$bookingDataJson,
				true
			);

			$availability = $availabilities->get($hotelId);

			if ($availability === null) {
				$availability = new Availability();
				$availability->Id = $hotelId;
				if ($filter->showHotelName) {
					$availability->Name = $price['hotel'];
				}

				$offers = new OfferCollection();
			} else {
				$offers = $availability->Offers;
			}

			$offers->put($offer->Code, $offer);
			$availability->Offers = $offers;
			$availabilities->put($hotelId, $availability);
			//}
		}


		return $availabilities;
	}

	private function getCharterOrTourOffers(AvailabilityFilter $filter): array
	{

		if (false) {
			return $this->getCharterOrTourOffersForJoinUp($filter);
		} else {
			return $this->getCharterOrTourOffersForPrestige($filter);
		}
	}

	private function getCharterOrTourOffersForPrestigeNew(AvailabilityFilter $filter): array
	{
		AnexValidator::make()->validateUsernameAndPassword($this->post)
			->validateCharterOffersFilter($filter);

		if (empty($filter->departureCity)) {
			$filter->departureCity = $filter->departureCityId;
		}

		$availabilities = [];

		//$cities = $this->apiGetCities();
		$childrenAges = $post['args'][0]['rooms'][0]['childrenAges'] ? $post['args'][0]['rooms'][0]['childrenAges']->toArray() : null;

		$checkInDT = new DateTimeImmutable($filter->checkIn);
		$checkIn = $checkInDT->format('Ymd');
		//$checkOutDT = $checkInDT->modify('+' . $filter->days . ' days');

		$cities = $this->apiGetCities();
		$regions = $this->apiGetRegions();

		$currencyId = $this->getCurrencyId($filter);

		$countryId = null;

		if (empty($filter->countryId)) {
			if (empty($filter->cityId)) {
				$countryId = $regions->get($filter->regionId)->Country->Id;
			} else {
				$countryId = $cities->get($filter->cityId)->Country->Id;
			}
		} else {
			$countryId = $filter->countryId;
		}

		//$exoticCountries = [133, 136, 134];

		// $tourType = null;
		// if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
		// 	$tourType = 2;
		// } else {
		// 	$tourType = in_array($countryId, $exoticCountries) ? 15 : 3;
		// }

		$location = '';

		if (empty($filter->cityId)) {
			$citiesSelected = $cities->filter(fn(City $city) => ($city->County->Id ?? '?') === $filter->regionId);
			foreach ($citiesSelected as $citySelected) {
				$location .= $citySelected->Id . ',';
			}
			$location = rtrim($location, ',');
		} else {
			$location = $filter->cityId;
		}

		$params = [
			'STATEINC' => $countryId,
			'TOWNFROMINC' => $filter->departureCity,
			'CHECKIN_BEG' => $checkIn,
			'CHECKIN_END' => $checkIn,
			'NIGHTS_FROM' => $filter->days,
			'NIGHTS_TILL' => $filter->days,
			'ADULT' => $filter->rooms->first()->adults,
			'CURRENCY' => $currencyId,
			'FREIGHT' => 1,
			'TOWNS' => $location,
			'STATEFROM' => ($this->handle === Handles::PRESTIGE_V2 || $this->handle === Handles::PRESTIGE_TEST) ?
				self::PRESTIGE_ROMANIA :
				self::ANEX_ROMANIA
		];

		if (!empty($filter->hotelId)) {
			if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
				$params["HOTELS"] = explode('-', $filter->hotelId)[2];
			} else {
				$params["HOTELS"] = $filter->hotelId;
			}
		}

		if (!empty($post['args'][0]['rooms'][0]['children'])) {
			$params['CHILD'] = $post['args'][0]['rooms'][0]['children'];

			$params['AGES'] = '';
			foreach ($post['args'][0]['rooms'][0]['childrenAges'] as $childrenAge) {
				$params['AGES'] = $params['AGES'] . $childrenAge . ',';
			}

			$params['AGES'] = rtrim($params['AGES'], ",");
		}

		$paramsTours = [
			'STATEINC' => $countryId,
			'TOWNFROMINC' => $filter->departureCity,
		];

		$toursResp = $this->requestData('SearchTour_Tours', $paramsTours);

		$flights = [];
		$prices = [];
		foreach ($toursResp as $tourResp) {

			if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR && !empty($filter->hotelId)) {
				if ($tourResp['id'] != explode('-', $filter->hotelId)[0]) {
					continue;
				}
			}

			if (
				$filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER
				&& $tourResp['typeKey'] !== self::TYPE_CHARTER
			) {

				continue;
			}
			if (
				$filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR
				&& !in_array($tourResp['typeKey'], [self::TYPE_TOUR_EXOTIC, self::TYPE_TOUR_TOUR])
			) {

				continue;
			}

			$params['TOURINC'] = $tourResp['id'];

			$response = $this->requestData('SearchTour_PRICES', $params);

			if (empty($response['pager'])) {
				continue;
			}

			$prices[] = [$response['prices'], $tourResp];

			$allPages = $response['pager']['total'];
			$currentPage = $response['pager']['current'];

			$nextPage = $currentPage + 1;

			$i = 0;

			while ($nextPage <= $allPages) {
				$params["PRICEPAGE"] = $nextPage;
				$response = $this->requestData('SearchTour_PRICES', $params);

				$prices[] = [$response['prices'], $tourResp];
				$nextPage++;
				$i++;
				if ($i > 350) {
					break;
				}
			}
		}



		//$citiesFilter = [];
		//if ($this->handle !== self::JOIN_UP && $this->handle !== self::JOIN_UP_TEST) {
		//$location = '';

		// if (empty($filter->cityId)) {
		// 	$citiesSelected = $cities->filter(fn(City $city) => 
		// 		($city->County->Id ?? '?') === $filter->regionId);
		// 	foreach ($citiesSelected as $citySelected) {
		// 		$location .= $citySelected->Id . ',';
		// 	}
		// 	$location = rtrim($location, ',');

		//} else {
		//$location = $filter->cityId;
		//}
		//$params['TOWNS'] = $location;
		// } else {
		// 	if (empty($filter->cityId)) {
		// 		$citiesSelected = $cities->filter(fn(City $city) => 
		// 			($city->County->Id ?? '?') === $filter->regionId);
		// 		foreach ($citiesSelected as $citySelected) {
		// 			$citiesFilter[] = $citySelected->Id;
		// 		}

		// 	} else {
		// 		$citiesFilter[] = $filter->cityId;
		// 	}
		// }


		// $countries = $this->apiGetCountries();


		//$countries->first(fn(Country $country) => $country->Code === 'RO')->Id;

		$this->getLatestCache = false;

		$airportMap = $this->getAirportMap();

		$this->getLatestCache = true;

		$map = $this->getAvailabilityMap();

		$flights = [];

		foreach ($prices as $priceSet) {
			foreach ($priceSet[0] as $price) {

				$tourResp = $priceSet[1];
				// if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
				// 	if (!in_array($price['townKey'], $citiesFilter)) {
				// 		continue;
				// 	}
				// }

				$roomId = $price['roomKey'];
				$roomName = $price['room'];
				$roomInfo = null;

				$mealId = $price['mealKey'];
				$mealName = $price['meal'];

				$priceNet = $price['price'];
				$offerCurrency = $price['currency'];
				$comission = 0;

				$offerCheckInDT = new DateTime($price['checkIn']);
				$offerCheckOutDT = new DateTime($price['checkOut']);

				$adults = $price['adult'];
				$availability  = null;


				// hotelAvailability can be also N or NNNN
				// if ($price['hotelAvailability'] === 'Y') {
				// 	$availability = Offer::AVAILABILITY_YES;
				// } else {
				// 	if ($price['hotelAvailability'] === 'R' || $price['hotelAvailability'] === 'RRRR') {
				// 		$availability = Offer::AVAILABILITY_ASK;
				// 	} else {
				// 		$availability = Offer::AVAILABILITY_NO;
				// 	}
				// }

				if (isset($map[$price['hotelAvailability']])) {
					$availability = $map[$price['hotelAvailability']];
				} else {
					$availability = Offer::AVAILABILITY_NO;
				}


				if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER && $tourResp['typeKey'] !== 2) {
					continue;
				}

				if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
					if ($tourResp['typeKey'] !== 3 && $tourResp['typeKey'] !== 15) {
						continue;
					}
				}

				$freightResp = null;
				if (!isset($flights[$price['tourKey']])) {

					$departureFlightInfo = null;
					$returnFlightInfo = null;
					$departureFlightDate = null;
					$returnFlightDate = null;

					$freightsParams = [
						'CATCLAIM' => $price['id']
					];

					$freightResp = $this->requestData('FreightMonitor_FREIGHTSBYPACKET', $freightsParams);

					if (empty($freightResp['routes'])) {
						continue;
					}

					$flights[$price['tourKey']] = $freightResp;
				} else {
					$freightResp = $flights[$price['tourKey']];
				}

				$departureFlightInfo = $freightResp['routes'][0]['freights'][0];
				$departureFlightDate = $freightResp['routes'][0]['info']['date'];

				if (!isset($freightResp['routes'][1]['freights'][0])) {
					continue;
				}

				$returnFlightInfo = $freightResp['routes'][1]['freights'][0];
				$returnFlightDate = $freightResp['routes'][1]['info']['date'];



				if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_TOUR) {
					$hotelId = $tourResp['id'] . '-' . $price['nights'] . '-' . $price['hotelKey'];
				} else {
					$hotelId = $price['hotelKey'];
				}

				$flightDepartureDT = null;
				$flightDepartureArrivalDT = null;
				$flightReturnDepartureDT = null;
				$flightReturnArrivalDT = null;

				//if ($this->handle === self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
				$flightDepartureDT = new DateTime($departureFlightDate . ' ' . $departureFlightInfo['departure']['time']);
				$flightDepartureArrivalDT = new DateTime($departureFlightDate . ' ' . $departureFlightInfo['arrival']['time']);
				$flightReturnDepartureDT = new DateTime($returnFlightDate . ' ' . $returnFlightInfo['departure']['time']);
				$flightReturnArrivalDT = new DateTime($returnFlightDate . ' ' . $returnFlightInfo['arrival']['time']);
				//} else {
				//$flightDepartureDT = new DateTime($departureFlightInfo['date'] . ' ' . $departureFlightInfo['departure']['time']);
				//$flightDepartureArrivalDT = new DateTime($departureFlightInfo['date'] . ' ' . $departureFlightInfo['arrival']['time']);
				//$flightReturnDepartureDT = new DateTime($returnFlightInfo['date'] . ' ' . $returnFlightInfo['departure']['time']);
				//$flightReturnArrivalDT = new DateTime($returnFlightInfo['date'] . ' ' . $returnFlightInfo['arrival']['time']);
				//}

				$departureAirport = $airportMap[$departureFlightInfo['departure']['portKey']];
				$departureArrivalAirport = $airportMap[$departureFlightInfo['arrival']['portKey']];
				$returnDepartureAirport = $airportMap[$returnFlightInfo['departure']['portKey']];
				$returnArrivalAirport = $airportMap[$returnFlightInfo['arrival']['portKey']];

				$departureCity = $cities->get((string) $departureAirport['town']);
				$departureArrivalCity = $cities->get((string) $departureArrivalAirport['town']);
				$returnDeparturelCity = $cities->get((string) $returnDepartureAirport['town']);
				$returnArrivalCity = $cities->get((string) $returnArrivalAirport['town']);

				$bookingData = ['claim' => $price['id']];
				$bookingDataJson = json_encode($bookingData);

				$offer = Offer::createCharterOrTourOffer(
					$hotelId,
					$roomId,
					$roomId,
					$roomName,
					$mealId,
					$mealName,
					$offerCheckInDT,
					$offerCheckOutDT,
					$adults,
					$childrenAges,
					$offerCurrency,
					$priceNet,
					$priceNet,
					$priceNet,
					$comission,
					$availability,
					$roomInfo,
					$flightDepartureDT,
					$flightDepartureArrivalDT,
					$flightReturnDepartureDT,
					$flightReturnArrivalDT,
					$departureAirport['alias'],
					$departureArrivalAirport['alias'],
					$returnDepartureAirport['alias'],
					$returnArrivalAirport['alias'],
					$filter->transportTypes->first(),
					$departureCity,
					$departureArrivalCity,
					$returnDeparturelCity,
					$returnArrivalCity,
					$bookingDataJson,
					true
				);

				$availability = $availabilities->get($hotelId);

				if ($availability === null) {
					$availability = new Availability();
					$availability->Id = $hotelId;
					if ($filter->showHotelName) {
						$availability->Name = $price['hotel'];
					}

					$offers = new OfferCollection();
				} else {
					$offers = $availability->Offers;
				}

				$offers->put($offer->Code, $offer);
				$availability->Offers = $offers;
				$availabilities->put($hotelId, $availability);
			}
		}


		return $availabilities;
	}

	private function getAirportMap(): array
	{
		$file = 'airports';

		$json = Utils::getFromCache($this, $file);

		if ($json === null) {

			$map = [];
			$airports = $this->getDataFromXmlGate('port');

			foreach ($airports as $airport) {

				$map[(string) $airport['inc']] = [
					'town' => (string) $airport['town'],
					'alias' => (string) $airport['alias']
				];
			}
			Utils::writeToCache($this, $file, json_encode($map));
		} else {
			$map = json_decode($json, true);
		}

		return $map;
	}

	private function getAirportMapOld(): array
	{
		$cities = $this->apiGetCities();
		$departureAirports = $this->requestData('Tickets_SOURCES');

		$map = [];
		foreach ($departureAirports as $departureAirport) {
			dump($departureAirport);
			// $portKey = $departureAirport['portKey'];
			if ($this->handle == self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
				$code = $departureAirport['townIATA'];
			} else {
				$code = $departureAirport['portAlias'];
			}

			$city = $cities->get($departureAirport['townKey']);
			// $map[$portKey] = $city;
			$map[$code] = $city;
		}

		return $map;
	}

	private function parseToArray(DOMXPath $xpath, string $xpathquery): array
	{
		$elements = $xpath->query($xpathquery);

		$resultarray = [];
		foreach ($elements as $element) {
			$nodes = $element->childNodes;
			foreach ($nodes as $node) {
				$resultarray[] = $node->nodeValue;
			}
		}

		return $resultarray;
	}

	public function apiGetTours(): TourCollection
	{
		$cities = $this->apiGetCities();
		$tours = new TourCollection();

		$townFroms = $this->requestData('SearchTour_TOWNFROMS');

		foreach ($townFroms as $townFrom) {

			$params = [
				'TOWNFROMINC' => $townFrom['id']
			];

			$states = $this->requestData('SearchTour_STATES', $params);

			foreach ($states as $state) {

				$params = [
					'STATEINC' => $state['id'],
					'TOWNFROMINC' => $townFrom['id']
				];

				$toursResp = $this->requestData('SearchTour_Tours', $params);


				foreach ($toursResp as $tourResp) {

					if ($tourResp['typeKey'] !== self::TYPE_TOUR_TOUR && $tourResp['typeKey'] !== self::TYPE_TOUR_EXOTIC) {
						continue;
					}

					$url = $tourResp['url'];

					$description = '';
					$img = null;

					if ($url !== '') {

						$req = $this->client->request(RequestFactory::METHOD_GET, $url);

						if ($req->getStatusCode() !== 404) {

							$html = $req->getBody();

							$dom = new DOMDocument();
							@$dom->loadHTML($html);


							$xpath = new DOMXPath($dom);

							// photo
							$query = '//div[contains(@class,"acqua-img-gallery")]//img/@src';

							$elements = $xpath->query($query);

							$img = 'https://www.prestige.ro' . $elements[0]->nodeValue;

							$query = '//div[contains(@class,"hotel-description")]';

							$elements = $xpath->query($query);

							$contents = [];

							foreach ($elements as $element) {

								$nodes = $element->childNodes;

								foreach ($nodes as $node) {
									if ($node->nodeType === XML_COMMENT_NODE) {
										continue;
									}
									// if ($node->nodeType === XML_TEXT_NODE) {
									// 	continue;
									// }
									$content = $node->textContent;

									$content = htmlentities($content);
									$content = str_replace("&nbsp;", "", $content);
									//$content = html_entity_decode($content);

									$content = nl2br($content);
									$content = trim($content);

									if (
										$content === '<br />'
										&& isset($contents[count($contents) - 1])
										&& isset($contents[count($contents) - 2])
										&& $contents[count($contents) - 1] === '<br />'
										&& $contents[count($contents) - 2] === '<br />'
									) {
										continue;
									}

									if (empty($content)) {
										continue;
									}

									$contents[] = $content;

									$description .= $content;
								}
							}
						}
					}

					$params = [
						'STATEINC' =>  $state['id'],
						'TOWNFROMINC' => $townFrom['id'],
						'TOURINC' => $tourResp['id']
					];
					$holidayCitiesData = $this->requestData('SearchTour_TOWNS', $params);

					$params = [
						'STATEINC' => $state['id'],
						'TOWNFROMINC' => $townFrom['id'],
						'TOURINC' => $tourResp['id']
					];
					$hotelsResp = $this->requestData('SearchTour_HOTELS', $params);

					foreach ($hotelsResp as $hotelResp) {
						$params = [
							'HOTEL' => $hotelResp['id'],
						];
						//$hotelDetails = $this->requestData('SearchTour_CONTENT', $params);
						// if ($tourResp['id'] == 434) {
						// 	dump($hotelDetails);
						// 	dump($tourResp);
						// 	die;	
						// }

						$params = [
							'STATEINC' =>  $state['id'],
							'TOWNFROMINC' => $townFrom['id'],
							'TOURINC' => $tourResp['id']
						];
						$holidayCheckinsData = $this->requestData('SearchTour_CHECKIN', $params);

						$nights = [];
						foreach ($holidayCheckinsData['items'] as $holidayCheckin) {
							$params = [
								'STATEINC' => $state['id'],
								'TOWNFROMINC' => $townFrom['id'],
								'CHECKIN_BEG' => $holidayCheckin['checkin'],
								'CHECKIN_END' => $holidayCheckin['checkin'],
								'TOURINC' => $tourResp['id']
							];

							$holidayNightsData = $this->requestData('SearchTour_NIGHTS', $params);

							foreach ($holidayNightsData['nights'] as $night) {
								$nights[$night] = $night;
							}
						}

						foreach ($nights as $night) {

							$tour = new Tour();
							$tour->Id = $tourResp['id'] . '-' . $night . '-' . $hotelResp['id'];
							$tour->Title = $tourResp['name'] . ' ' . $hotelResp['name'];

							$destinations = [];
							$countries = [];

							$locationCity = null;
							foreach ($holidayCitiesData as $holidayCity) {
								$city = $cities->get($holidayCity['id']);
								$locationCity = $city;
								$destinations->add($city);
								$country = $city->Country;
								$countries->put($country->Id, $country);
							}

							$location = new Location();
							$location->City = $locationCity;
							$tour->Location = $location;

							$tour->Destinations = $destinations;
							$tour->Destinations_Countries = $countries;

							$tourContent = new TourContent();

							$tourContent->Content = $description;


							if ($img !== null) {
								$gallery = new TourImageGallery();
								$items = new TourImageGalleryItemCollection();
								$image = new TourImageGalleryItem();
								$image->RemoteUrl = $img;
								$items->add($image);

								$gallery->Items = $items;
								$tourContent->ImageGallery = $gallery;
							}

							$tour->Content = $tourContent;

							$tour->Period = $night;
							$transportTypes = new StringCollection(['plane']);
							$tour->TransportTypes = $transportTypes;

							$tours->add($tour);
						}
					}
				}
			}
		}
		return $tours;
	}

	public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
	{
		AnexValidator::make()
			->validateAllCredentials($this->post)
			->validateApiCode($this->post);

		$adCities = $this->getAvailabilityDates($filter);
		return $adCities;

		$regionMapExists = Utils::cachedFileExists($this, 'region-map-transports');

		// group by region
		$ad = [];
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
				$adCities = $this->getAvailabilityDates($filter);
			} else {
				$filter->type = AvailabilityDatesFilter::CHARTER;
				$adCities = $this->getAvailabilityDates($filter);
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

	public function getAvailabilityDates(AvailabilityDatesFilter $filter): array
	{
		AnexValidator::make()
			->validateAllCredentials($this->post)
			->validateApiCode($this->post);

		$cities = $this->apiGetCities();
		$regions = $this->apiGetRegions();

		$avDates = [];

		$respTownsFrom = $this->client->request(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/townfroms?type=json&xdebug=false');
		$content = $respTownsFrom->getBody();
		$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/townfroms?type=json&xdebug=false', [], $content, $respTownsFrom->getStatusCode());
		$townFroms  = json_decode($content, true)['SearchTour_TOWNFROMS'];

		foreach ($townFroms as $townFrom) {

			if ($townFrom['stateFromName'] !== self::ROMANIA) {
				continue;
			}

			$cityFrom = $cities->get($townFrom['id']);

			$respStates = $this->client->request(
				RequestFactory::METHOD_GET,
				$this->apiUrl . '/samo/searchtour/states?type=json&xdebug=true&TOWNFROMINC=' . $townFrom['id']
			);
			$content = $respStates->getBody();
			$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/samo/searchtour/states?type=json&xdebug=true&TOWNFROMINC=' . $townFrom['id'], [], $content, $respStates->getStatusCode());

			$states = json_decode($content, true)['SearchTour_STATES'];

			foreach ($states as $state) {

				// foreach region in state get the first city
				$citiesTo = [];
				/** @var Region $region */
				foreach ($regions as $region) {
					if ($region->Country->Id == $state['id']) {
						$firstCity = $cities->first(fn(City $city) => !empty($city->County) && ($city->County->Id == $region->Id));
						$citiesTo[] = $firstCity;
					}
				}

				// get checkin dates
				$respCheckin = $this->client->request(
					RequestFactory::METHOD_GET,
					$this->apiUrl . '/search/CheckIn?STATE=' . $state['id'] . '&TOWNFROM=' . $townFrom['id'] . '&touristCount=1'
				);
				$content = $respCheckin->getBody();
				$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/search/CheckIn?STATE=' . $state['id'] . '&TOWNFROM=' . $townFrom['id'] . '&touristCount=1' . $townFrom['id'], [], $content, $respStates->getStatusCode());

				$respCheckin = json_decode($content, true);
				if (count($respCheckin) > 1) {
					die('ok');
				}
				$respCheckin = $respCheckin[0];

				$dateStart = new DateTimeImmutable($respCheckin['datebeg']);
				$i = -1;
				$checkins = str_split($respCheckin['sdays']);

				$dates = new TransportDateCollection();
				// for each checkin get nights
				foreach ($checkins as $seats) {
					$i++;
					if ($seats === '2' || $seats === '3') {
						$checkinDate =  $dateStart->modify('+' . $i . ' days')->format('Y-m-d');

						$nights = $this->client->request(
							RequestFactory::METHOD_GET,
							$this->apiUrl . '/search/Nights?STATE=' . $state['id'] . '&TOWNFROM=' . $townFrom['id'] . '&checkInBeg=' . $checkinDate . '&checkInEnd=' . $checkinDate . '&touristCount=1'
						);
						$content = $nights->getBody();
						$this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/search/Nights?STATE=' . $state['id'] . '&TOWNFROM=' . $townFrom['id'] . '&checkInBeg=' . $checkinDate . '&checkInEnd=' . $checkinDate . '&touristCount=1', [], $content, $respStates->getStatusCode());

						$nights = json_decode($content, true);

						$nightList = new DateNightCollection();

						foreach ($nights as $night) {
							if ($night['places'] === 0) {
								continue;
							}
							$nightObj = DateNight::create($night['nights']);
							$nightList->add($nightObj);
						}
						if (count($nightList) === 0) {
							continue;
						}

						$dateObj = TransportDate::create($checkinDate, $nightList);
						$dates->add($dateObj);
					}
				}

				foreach ($citiesTo as $cityTo) {
					$id = 'plane' . "~city|" . $cityFrom->Id . "~city|" . $cityTo->Id;
					$transportSet = AvailabilityDates::create($id, $cityFrom, $cityTo, 'plane', $dates);
					$avDates->add($transportSet);
				}
			}
		}


		return $avDates;
	}

	public function preBook(string $offerClaim)
	{
		$data = '
		<WorkRequest version="3.0">
			<proc>GET_PACKET_FOR_AGENT</proc>
			<params>
				<CLAIM>' . $offerClaim . '</CLAIM>
				<NAME>' . $this->username . '</NAME>
				<PSW>' . $this->password . '</PSW>
			</params>
		</WorkRequest>';

		$crypt = new CryptAes();
		$crypt->setKey($this->bookingApiPassword);

		$encryptedData = base64_encode($crypt->encrypt(gzencode($data)));

		$created = date('Y-m-d\TH:i:s\Z');
		$nonce = uniqid();
		$soapPassword = base64_encode(sha1($nonce . $created . ($this->bookingApiPassword)));

		$xmlString = '<SOAP-ENV:Envelope  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
		xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
		xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
		xmlns:ns6="http://www.samo.ru/xml"
		SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
		   <SOAP-ENV:Header>
			   <wsse:Security SOAP-ENV:actor="http://schemas.xmlsoap.org/soap/actor/next" SOAP-ENV:mustUnderstand="0">
				   <wsse:UsernameToken>
					   <wsse:Username>' . $this->bookingApiUsername . '</wsse:Username>
					   <wsse:Password xsi:type="wsse:PasswordDigest">' . $soapPassword . '</wsse:Password>
					   <wsse:Nonce>' . base64_encode($nonce) . '</wsse:Nonce>
					   <wsu:Created>' . $created . '</wsu:Created>
				   </wsse:UsernameToken>
			   </wsse:Security>
			   <ns6:agentinfo SOAP-ENV:actor="http://schemas.xmlsoap.org/soap/actor/next" SOAP-ENV:mustUnderstand="0">
				   <version xsi:type="xsd:string">3.0</version>
			   </ns6:agentinfo>
		   </SOAP-ENV:Header>
		   <SOAP-ENV:Body>
			   <ns6:WORK>
				   <data xsi:type="xsd:string">' . $encryptedData . '</data>
			   </ns6:WORK>
		   </SOAP-ENV:Body>
	   </SOAP-ENV:Envelope>';

		$options['body'] = $xmlString;

		if ($this->handle === Handles::ANEX) {
			$this->bookingUrl = $this->apiUrl . '/export/default.php';
		}

		$resp = $this->client->request(RequestFactory::METHOD_POST, $this->bookingUrl, $options);

		$content = $resp->getBody();
		$this->showRequest(RequestFactory::METHOD_POST, $this->bookingUrl, $options, $content, $resp->getStatusCode());


		$contentXml = simplexml_load_string($content);
		$contentEncoded = (string) $contentXml->children('http://schemas.xmlsoap.org/soap/envelope/')
			->Body->children('http://www.samo.ru/xml')
			->WORKResponse->children('')->return;

		$xml = $crypt->decrypt(base64_decode($contentEncoded));
		$xml = gzinflate(substr($xml, 10, -8));

		return $xml;
	}

	public function apiDoBooking(BookHotelFilter $filter): array
	{
		// auth
		$options['body'] = 'username=' . $this->username . '&password=' . $this->password;
		$respAuth = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/auth/token', $options)->getBody();
		$respAuth = json_decode($respAuth, true);

		// get offer id
		$bookingData = $post['args'][0]['Items'][0]['Offer_bookingDataJson'];
		$bookingData = json_decode($bookingData, true);
		$offerId = $bookingData['claim'];

		// start
		$options['body'] = 'cat_claim=' . $offerId . '&id=' . $this->GUIDv4() . '&currency=4';
		$token = $respAuth['token'];
		$options['headers'] = [
			'Authorization' => 'Bearer ' . $token
		];
		$respStart = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/bron/start', $options);
		$contentStart = $respStart->getBody();

		$respStart = json_decode($contentStart, true);

		// calc
		$options['body'] = 'id=' . $respStart['bron']['doc']['id'] . '&lang=ro';
		$resp = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/bron/calcfull', $options);
		$content = $resp->getBody();
		$resp = json_decode($content, true);


		dd($resp);
		$people =

			// set people
			$options['body'] = 'id=' . $respStart['bron']['doc']['id'] . '&lang=ro';
		$resp = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/bron/SetPeople', $options);
		$content = $resp->getBody();
		$resp = json_decode($content, true);


		dd($resp);
		// save reservation
		$options['body'] = 'id=' . $respStart['bron']['doc']['id'] . '&lang=ro';
		$resp = $this->client->request(RequestFactory::METHOD_POST, self::ANEX_WEBAPI_URL . '/bron/save', $options);
		$content = $resp->getBody();
		$resp = json_decode($content, true);







		AnexValidator::make()
			->validateBookHotelFilter($filter);

		$data = $filter->Items->first();
		$bookingData = $data->Offer_bookingDataJson;
		$bookingData = json_decode($bookingData, true);

		$offerId = $bookingData['claim'];

		// from docs - Prepare for the booking
		$xmlStrPre = $this->preBook($offerId);
		$xmlPre = simplexml_load_string($xmlStrPre);

		$order = new Booking();

		if (!isset($xmlPre->claim->claimDocument['currency'])) {
			return [$order, 'pre booking error, response: ' . $xmlStrPre];
		}

		$passengers = $data->Passengers;

		$responseStr = $this->confirmReservation($xmlStrPre, $passengers, $filter);

		$confirmXml = simplexml_load_string($responseStr);

		if (!isset($confirmXml->claim->claimDocument['providerNumber'])) {
			return [$order, 'confirm booking error, response: ' . $responseStr];
		}

		$order->Id = $confirmXml->claim->claimDocument['providerNumber'];

		return [$order, $responseStr];
	}

	private function confirmReservation($xml, $passengers, BookHotelFilter $orderData): string
	{
		$xml = new SimpleXMLElement($xml);

		$xml->claim->claimDocument['guid'] = '{' . $this->GUIDv4() . '}';

		$checkNameValidator = null;
		if (isset($xml->claim->checkFields->people->lname['regular'])) {
			$checkNameValidator = (string) $xml->claim->checkFields->people->lname['regular'];
		}

		$checkNameValidatorMessage = null;
		if (isset($xml->claim->checkFields->people->lname['message'])) {
			$checkNameValidatorMessage = (string) $xml->claim->checkFields->people->lname['message'];
		}

		$useCheckNameValidator = ($checkNameValidator && ($checkNameValidator != 'true') && ($checkNameValidator != 'false'));

		$checkIn = $orderData->Items->first()->Room_CheckinAfter;

		$people = $xml->claim->claimDocument->peoples->people;

		$peopleByType = [];
		foreach ($people as $person) {
			$type = (string) $person['age'];
			$peopleByType[$type][] = $person;
		}

		$passengersTypesTranslations = [
			'adult' => 'ADL',
			'child' => 'CHD',
			'infant' => 'INF'
		];

		$passengersByType = [];
		foreach ($passengers as $passenger) {
			$passengerType = $passengersTypesTranslations[$passenger->Type];

			if ($passengerType == 'CHD') {
				$passengerAge = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($passenger['BirthDate']))->format("%y");
				if ($passengerAge < 2)
					$passengerType = 'INF';
			}

			$passengersByType[$passengerType][] = $passenger;
		}

		$adultType = $passengersTypesTranslations['adult'];
		$childType = $passengersTypesTranslations['child'];
		$infantType = $passengersTypesTranslations['infant'];

		# sort children by age from older to younger
		$childrenByAge = [];

		if (isset($passengersByType[$childType])) {
			foreach ($passengersByType[$childType] as $child) {
				$childYearsOld = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($child->BirthDate))->format("%Y");
				$childrenByAge[$childYearsOld][] = $child;
			}
		}
		krsort($childrenByAge);
		$passengersByType[$childType] = [];

		foreach ($childrenByAge as $children) {
			foreach ($children as $child) {
				$passengersByType[$childType][] = $child;
			}
		}

		ksort($peopleByType);
		$passportExpiryDate = date("Y", strtotime("+2 years")) . '-01-01';
		#the to do:
		#fill adults first
		#fill adults remaining rows with children (from older to younger)
		#fill children rows with remaining children
		$ppos = 0;

		foreach ($peopleByType as $ptype => $peoples) {
			$pByTypePos = 0;
			$peopleAreAdults = ($ptype === $adultType);
			$peopleAreChildren = (($ptype === $childType) || ($ptype === $infantType));

			foreach ($peoples as $person) {
				# to use passenger is the passenger for type and on index

				$toUsePassenger = $passengersByType[$ptype][$pByTypePos];

				#if we don't have the passenger from our system we have 2 cases:
				#1. is adult and we need to send an older child to fill the adult record
				#2. is child and we should have an error for this
				if (!($toUsePassenger)) {
					if ($peopleAreAdults) {
						$toUsePassenger = array_shift($passengersByType[$childType]);
					} else {
						throw new Exception("Nu se poate pune tipul [{$ptype}] pe pasagerul de la pozitia [{$pByTypePos}]");
					}
				}

				if (!($toUsePassenger)) {
					throw new Exception("Nu se poate pune tipul [{$ptype}] pe pasagerul de la pozitia [{$pByTypePos}]");
				}
				$pByTypePos++;

				$isMale = ($toUsePassenger->Gender == 'male');
				$toUsePassengerAge = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($toUsePassenger->BirthDate))->format("%y");
				$isInfant = ($peopleAreChildren && ($toUsePassengerAge < 2));
				$human = $peopleAreChildren ? ($isInfant ? 'INF' : 'CHD') : ($isMale ? 'MR' : 'MRS');

				$person['human'] = $human;
				$person['sex'] = $isMale ? 'MALE' : 'FEMALE';


				$lastName = $toUsePassenger->Lastname;
				$firstname = $toUsePassenger->Firstname;

				if ($checkNameValidator && $useCheckNameValidator) {
					if (!preg_match($checkNameValidator, $lastName)) {
						throw new Exception($checkNameValidatorMessage);
					}
				}

				$ppos++;

				$person['name'] = $firstname . ' ' . $lastName;
				$person['lname'] = $firstname . ' ' . $lastName;

				$person['born'] = $toUsePassenger->BirthDate;
				$person['pserie'] = '887' . $ppos;
				$person['pnumber'] = '27328' . $ppos;
				$person['pexpire'] = $passportExpiryDate;
				$person['pgiven'] = $toUsePassenger->BirthDate;
				$person['pgivenorg'] = 'RO';
				$person['bornplace'] = 'ROMANIA';
				$person['nationality'] = 'ROMANIA';
				$person['bornplaceKey'] = self::PRESTIGE_ROMANIA;
				$person['nationalityKey'] = self::PRESTIGE_ROMANIA;

				if ($this->handle == self::JOIN_UP || $this->handle === self::JOIN_UP_TEST) {
					$person['mobile'] = '+4071111111';
					$person['email'] = 'test@travelfuse.com';

					if ($toUsePassenger->Type == 'adult')
						$person['age'] = 'ADL';
					else if ($isInfant)
						$person['age'] = $person['human'] = 'INF';
					else
						$person['human'] = $person['age'] = 'CHD';

					$person['nationalityKey'] = self::JOIN_UP_ROMANIA;

					unset($person['bornplaceKey']);
				}
			}
		}

		$xml = $xml->claim->asXML();

		$data =
			'<WorkRequest version="3.0">
				<proc>GET_BRON_RESULT_FOR_AGENT</proc>
				<params>
					' . $xml . '
					<NAME>' . $this->username . '</NAME>
					<PSW>' . $this->password . '</PSW>
				</params>
			</WorkRequest>';

		$crypt = new CryptAes();
		$crypt->setKey($this->bookingApiPassword);

		$encryptedData = base64_encode($crypt->encrypt(gzencode($data)));

		$created = date('Y-m-d\TH:i:s\Z');

		$nonce = uniqid();

		$soapPassword = base64_encode(sha1($nonce . $created . ($this->bookingApiPassword)));

		$xmlString = '<SOAP-ENV:Envelope  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
		xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
		xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
		xmlns:ns6="http://www.samo.ru/xml"
		SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
		   <SOAP-ENV:Header>
			   <wsse:Security SOAP-ENV:actor="http://schemas.xmlsoap.org/soap/actor/next" SOAP-ENV:mustUnderstand="0">
				   <wsse:UsernameToken>
					   <wsse:Username>' . $this->bookingApiUsername . '</wsse:Username>
					   <wsse:Password xsi:type="wsse:PasswordDigest">' . $soapPassword . '</wsse:Password>
					   <wsse:Nonce>' . base64_encode($nonce) . '</wsse:Nonce>
					   <wsu:Created>' . $created . '</wsu:Created>
				   </wsse:UsernameToken>
			   </wsse:Security>
			   <ns6:agentinfo SOAP-ENV:actor="http://schemas.xmlsoap.org/soap/actor/next" SOAP-ENV:mustUnderstand="0">
				   <version xsi:type="xsd:string">3.0</version>
				   <answer xsi:type="xsd:string">1</answer>
			   </ns6:agentinfo>
		   </SOAP-ENV:Header>
		   <SOAP-ENV:Body>
			   <ns6:WORK>
				   <data xsi:type="xsd:string">' . $encryptedData . '</data>
			   </ns6:WORK>
		   </SOAP-ENV:Body>
	    </SOAP-ENV:Envelope>'; //<answer xsi:type="xsd:string">1</answer>

		$options['body'] = $xmlString;

		$resp = $this->client->request(RequestFactory::METHOD_POST, $this->bookingUrl, $options);
		$options['xmlRequest'] = $data; // only for logging

		$content = $resp->getBody();

		$contentXml = simplexml_load_string($content);

		$contentBody =  $contentXml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;

		if (!empty($contentBody->children('http://schemas.xmlsoap.org/soap/envelope/')->Fault->children('')->faultstring)) {

			$this->showRequest(RequestFactory::METHOD_POST, $this->bookingUrl, $options, $content, $resp->getStatusCode());
			throw new Exception((string) $contentBody->children('http://schemas.xmlsoap.org/soap/envelope/')->Fault->children('')->faultstring);
		}

		$contentEncoded	= (string) $contentBody->children('http://www.samo.ru/xml')->WORKResponse->children('')->return;

		$xml = $crypt->decrypt(base64_decode($contentEncoded));
		$xml = gzinflate(substr($xml, 10, -8));

		$options['xmlResponse'] = $xml; // only for logging

		$this->showRequest(RequestFactory::METHOD_POST, $this->bookingUrl, $options, $content, $resp->getStatusCode());

		return $xml;
	}
}
