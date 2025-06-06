<?php

namespace Integrations\Karpaten_old;
use IntegrationTraits\TourOperatorRecordTrait;
use Omi\TF\TOInterface_Util;

/**
 * Travelio API (Karpaten Edition)
 */
class TravelioKarpaten extends \Omi\TF\TOInterface
{
	// ------added---------
	use TourOperatorRecordTrait;
	use TOInterface_Util;
	
	const AirportTaxItmIndx = "7s";

	const TransferItmIndx = "6";

	protected $curl;
	
	protected $infantTillAgeOf = 2;
	/**
	 * Fees and payments requests
	 *
	 * @var type 
	 */
	protected static $FeesAndPaymentsRequests = [];
	/**
	 * Cache limit 7 days (1 week)
	 *
	 * @var type 
	 */
	protected $cacheTimeLimit = 60 * 60 * 24 * 7;
	
	protected $requestCacheTimeLimit = 60 * 60 * 24;
	
	/**
	 * Cache time for avalability dates 
	 * @var type 
	 */
	public $availabilityDatesCacheTimeLimit_packages = 60 * 60 * 24 * 1;

	/**
	 * Cache time for avalability dates 
	 * @var type 
	 */
	public $availabilityDatesCacheTimeLimit = 60 * 60 * 24 * 5;
	/**
	 * Departure Country
	 * @var type 
	 */
	public $departureCountry = 176; // Romania
	/**
	 * Departure cities
	 * @var type 
	 */
	public $departureCities = [
		23 => 'Bucuresti',
		2233 => 'Cluj Napoca',
		2234 => 'Timisoara',
		2235 => 'Iasi',
	];
	/**
	 * Id of tour package type
	 * @var type 
	 */
	public $toursPackageTypeId = 163;

	/**
	 * Id of tour package type
	 * @var type 
	 */
	public $individualPackageTypeId = 143;

	public $chartersPackagesIds = [
		0, 53, 85, 151, 183, 185, 282, 298, 314, 351, 374, 379, 381
	];
	
	public $_lastResponse = null;

	/**
	 * test conn resort id - Atena
	 * @var string
	 */
	public static $TestConnResortId = 1021;

	/**
	 * Test connection
	 * @param array $filter
	 */
	public function api_testConnection(array $filter = null)
	{
		$checkIn = date("Y-m-d", strtotime('+3 day'));
		$checkOut = date("Y-m-d", strtotime('+6 day'));
		
		$adults = 2;
		$children = 0;

		$params = [
			'Currency' => 'EUR',
			'ResortID' => static::$TestConnResortId,
			'CheckIn' => $checkIn,
			'CheckOut' => $checkOut,
			'Rooms' => [
				'Room' => [
					'Adults' => $adults,
					'Children' => $children
				]
			]
		];

		$filter = [];

		try
		{
			list($offersResp, /*$rawResp*/, $rawResult, $info) = $this->request('SearchAvailableHotels', $params, $filter, false, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));
		}
		catch (\Exception $ex)
		{
			echo "<div style='color: red;'>" . $ex->getMessage() . "</div>";
			return false;
		}

		return true;

		/*
		return true;
		// get response
		list($countriesResp) = $this->request('GetCountriesList');

		// load xml string
		$countriesXML = simplexml_load_string($countriesResp);

		// return countries list
		$connected = ($countriesXML && $countriesXML->GetCountriesList && $countriesXML->GetCountriesList->CountriesList && $countriesXML->GetCountriesList->CountriesList->Country);

		if (!$connected)
		{
			echo "<div style='color: red;'>" . htmlspecialchars($countriesResp) . "</div>";
		}

		$sharedResDir = $this->getSharedResourcesDir(true);
		$domain = preg_replace("/[^a-zA-Z0-9]/", "", str_ireplace('www.', '', parse_url(\QWebRequest::GetBaseUrl(), PHP_URL_HOST)));
		file_put_contents($sharedResDir . '/' . q_get_user() . '_' . ($domain ?: 'NO_DOMAIN') . '_test_conn.txt', date('Y-m-d H:i:s') 
			. " - " . ($connected ? 'connected' : 'not_connected') . "\n", FILE_APPEND);

		return $connected;
		*/
	}

	/**
	 * Gets the countries.
	 * Response format: 
	 *		array of: Id,Name,Code
	 * 
	 * @param array $filter Apply a filter like: [Id => , Name => , Code => ]
	 *						For more complex: [Name => ['like' => '...']]
	 * 
	 */
	public function api_getCountries(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'countries');
		list($countriesResp) = $this->request('GetCountriesList', [], $filter, $filter['use_cache']);

		// load xml string
		$countriesXML = simplexml_load_string($countriesResp);
		
		if ((!$countriesXML->GetCountriesList) || (!$countriesXML->GetCountriesList->CountriesList) || (!$countriesXML->GetCountriesList->CountriesList->Country))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'countries not provided by tour operaotr');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			return [false];
		}
		
		// init array
		$countries = [];
		
		// get mapping
		$mapping = $this->getCountriesMapping();
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count countries: %s', [count($countriesXML->GetCountriesList->CountriesList->Country)]);
		
		// go through each country
		foreach ($countriesXML->GetCountriesList->CountriesList->Country as $countryXML)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process country: %s', [(string)$countryXML->ID . ' ' . (string)$countryXML->Name], 50);

			// get identifier
			$countryIdf = trim(strtolower((string)$countryXML->Name));
			
			if (!($countryCODE = $mapping[$countryIdf]))
			{
				#echo "<div style='color: red;'>Skip : " . ((string)$countryXML->Name) . " because not mapped</div>";
				continue;
			}
			
			// new country
			$country = new \stdClass();
			$country->Id = (string)$countryXML->ID;
			$country->Name = (string)$countryXML->Name;
			$country->Code = $countryCODE;
			
			$countries[$country->Id] = $country;
		}
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
		// return countries
		return [$countries];
	}

	/**
	 * Gets the regions.
	 * Response format: 
	 *		array of: Id,Name,Code,CountryId,CountryCode
	 * 
	 * @param array $filter See $filter in general, CountryCode, CountryId
	 */
	public function api_getRegions(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'regions');

		// get countries
		$countries = reset($this->api_getCountries(['skip_report' => true]));

		if (!$countries)
		{
			\Omi\TF\TOInterface::markReportError($filter, 'no countries');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
			return [false];
		}
		
		if ($filter['countryId'])
			$countries = [$countries[$filter['countryId']]];
		
		if (!$countries)
		{
			\Omi\TF\TOInterface::markReportError($filter, 'no countries');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
			return [false];
		}
		
		// init arrays
		$params = [];
		$regions = [];
		
		foreach ($countries as $country)
		{
			$params['CountryID'] = $country->Id;
			
			// get api response
			list($regionsResp) = $this->request('GetRegionsList', $params);
			
			// to xml
			$regionsXML = simplexml_load_string($regionsResp);
			
			\Omi\TF\TOInterface::markReportData($filter, 'Count regions: %s for country: %s', [isset($regionsXML->GetRegionsList->RegionsList->Region) ?
				count($regionsXML->GetRegionsList->RegionsList->Region) : 0, $country->Id . ' ' . $country->Name], 50);
			
			if (!$regionsXML->GetRegionsList || (!$regionsXML->GetRegionsList->RegionsList) || (!$regionsXML->GetRegionsList->RegionsList->Region))
			{
				#echo "<div style='color: red;'>No regions found for country : " . $country->Name . "</div>";
				continue;
			}

			foreach ($regionsXML->GetRegionsList->RegionsList->Region as $regionXML)
			{				
				\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', [(string)$regionXML->ID . ' ' . (string)$regionXML->Name], 50);

				$region = new \stdClass();
				$region->Id = (string)$regionXML->ID;
				$region->Name = (string)$regionXML->Name;
				$region->Country = $country;
				$regions[$region->Id] = $region;
			}
		}
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
		return [$regions];
	}

	/**
	 * Gets the regions.
	 * Response format: 
	 *		array of: Id,Name,Code,IsResort,ParentCity.Id,ParentCity.Code,Region.Code,Region.Id,Country.Code,Country.Id
	 * 
	 * @param array $filter
	 */
	public function api_getCities(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'cities');
		// get cache folder
		$cacheFolder = $this->getResourcesDir();	

		// missing cache folder
		if (!is_dir($cacheFolder))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'missing cache folder');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
			throw new \Exception('Missing cache folder!');
		}

		// get cache file
		$cities_json_file = $cacheFolder . 'cities.json';
		
		// cache limit
		$city_file_cache_limit = (time() - $this->cacheTimeLimit);

		if (((!file_exists($cities_json_file)) || (filemtime($cities_json_file) < $city_file_cache_limit)) || ($filter['force']))
		{
			// get countries
			$countries = reset($this->api_getCountries(['skip_report' => true]));
			
			// get regions
			$regions = reset($this->api_getRegions(['skip_report' => true]));

			// exit if no regions
			if (!$regions)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'regions not provided');
				\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
				return [false];
			}

			// init arrays
			$params = [];		
			$cities = [];

			$allCountriesCities = [];
			// go through each region
			foreach ($regions as $region)
			{
				if ($filter['countryId'] && ($region->Country->Id != $filter['countryId']))
					continue;

				$params['CountryID'] = $region->Country->Id;
				$params['RegionID'] = $region->Id;

				// get api response
				list($citiesResp) = $this->request('GetResortsList', $params);
				
				$citiesXML = simplexml_load_string($citiesResp);
				
				\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s for region: %s', [isset($citiesXML->GetResortsList->ResortsList->Resort) ?
					count($citiesXML->GetResortsList->ResortsList->Resort) : 0, $region->Id . ' ' . $region->Name], 50);

				if ((!$citiesXML->GetResortsList) || (!$citiesXML->GetResortsList->ResortsList) || (!$citiesXML->GetResortsList->ResortsList->Resort))
				{
					#echo "<div style='color: red;'>No resort found on : " . $region->Name . " because no name</div>";
					continue;
				}

				foreach ($citiesXML->GetResortsList->ResortsList->Resort as $resort)
				{
					\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [(string)$resort->ID . ' ' . (string)$resort->Name], 50);

					#echo 'Process city: ' . (string)$resort->ID . '<br/>';
					$city = new \stdClass();
					$city->Id = (string)$resort->ID;
					$city->Name = (string)$resort->Name;
					$city->Country = $region->Country;
					$city->County = $region;

					$allCountriesCities[$region->Country->Id][$city->Id] = $city->Id;
					$cities[$city->Id] = $city;
				}
			}

			foreach ($countries as $country)
			{
				$params_byCountry['CountryID'] = $country->Id;

				// get api response
				list($citiesRespByCountry) = $this->request('GetResortsList', $params_byCountry);
				
				$citiesXMLByCountry = simplexml_load_string($citiesRespByCountry);
				
				\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s for country: %s', [isset($citiesXMLByCountry->GetResortsList->ResortsList->Resort) ?
					count($citiesXMLByCountry->GetResortsList->ResortsList->Resort) : 0, $country->Id . ' ' . $country->Name], 50);

				if ((!$citiesXMLByCountry->GetResortsList) || (!$citiesXMLByCountry->GetResortsList->ResortsList) || (!$citiesXMLByCountry->GetResortsList->ResortsList->Resort))
				{
					#echo "<div style='color: red;'>No resort found on : " . $region->Name . " because no name</div>";
					continue;
				}

				foreach ($citiesXMLByCountry->GetResortsList->ResortsList->Resort as $resort)
				{
					if (isset($allCountriesCities[$country->Id][(string)$resort->Id]))
						continue;

					#echo 'Process city: ' . (string)$resort->ID . '<br/>';

					$resortID = (string)$resort->ID;
					if (!$cities[$resortID])
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$resortID . ' ' . (string)$resort->Name], 50);
						$city = new \stdClass();
						$city->Id = $resortID;
						$city->Name = (string)$resort->Name;
						$city->Country = $country;
						$cities[$city->Id] = $city;
					}
				}
			}

			file_put_contents($cities_json_file, json_encode($cities));
		}
		else
		{
			$cities_data = json_decode(file_get_contents($cities_json_file), true);
			$cities = [];
			$countries = [];
			$regions = [];
			foreach ($cities_data as $city_data)
			{
				if ($filter['countryId'] && ($city_data['Country']['Id'] != $filter['countryId']))
					continue;

				if (!$countries[$city_data['Country']['Id']])
				{
					$country = new \stdClass();
					$country->Id = $city_data['Country']['Id'];
					$country->Name = $city_data['Country']['Name'];
					$country->Code = $city_data['Country']['Code'];
					$countries[$city_data['Country']['Id']] = $country;
				}

				if (!$regions[$city_data['County']['Id']])
				{
					$region = new \stdClass();
					$region->Id = $city_data['County']['Id'];
					$region->Name = $city_data['County']['Name'];
					$region->Country = $countries[$city_data['County']['Country']['Id']];
					$regions[$city_data['County']['Id']] = $region;
				}
				
				\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$city_data['Id'] . ' ' . $city_data['Name']], 50);

				$city = new \stdClass();
				$city->Id = $city_data['Id'];
				$city->Name = $city_data['Name'];
				$city->Country = $countries[$city_data['Country']['Id']];

				if ($city_data['County'])
					$city->County = $regions[$city_data['County']['Id']];

				$cities[$city->Id] = $city;
			}
		}

		if (!($depCountry = $countries[$this->departureCountry]) && (in_array($filter['countryId'], $countries)))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Dep Country not found');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
			throw new \Exception('Dep Country not found!');
		}

		if (in_array($filter['countryId'], $countries))
		{
			foreach ($this->departureCities as $departureCityId => $departureCitiyName)
			{
				\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$departureCityId . ' ' . $departureCitiyName], 50);
				$city = new \stdClass();
				$city->Id = $departureCityId;
				$city->Name = $departureCitiyName;
				$city->Country = $depCountry;
				// $city->County = $regions[$city_data['County']['Id']];

				$cities[$city->Id] = $city;
			}
		}
		
		// return cities
		return [$cities];
	}
	
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getBoardTypes(array $filter = null)
	{
		
	}
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getRoomTypes(array $filter = null)
	{
		
	}
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getRoomsFacilities(array $filter = null)
	{
		
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');
		// exit if no cities
		if (!$filter['CityId'])
		{
			\Omi\TF\TOInterface::markReportError($filter, 'city id not provided');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
			return [false];
		}

		$ccount = $filter['__ccount'];
		$cpos = $filter['__cpos'];

		$debugFile = 'travelio_karpaten_reports/travelio_karpaten_debug_hotels_pull.txt';
		if (($cpos == 0) && file_exists($debugFile))
			unlink($debugFile);

		$force = ($filter && $filter['force']);

		// init array
		$hotels = [];

		// set params
		$params = [
			'ResortID' => $filter['CityId']
		];

		// get cities
		$cities = reset($this->api_getCities(['skip_report' => true]));

		// get city
		$city = $cities[$filter['CityId']];

		$t1 = microtime(true);
		// get api response
		list($hotelsResp) = $this->request('GetHotelsList', $params, $filter, (!$force));

		// to xml
		$hotelsXML = simplexml_load_string($hotelsResp);

		// exit if no structure
		if ((!$hotelsXML->GetHotelsList) || (!$hotelsXML->GetHotelsList->HotelsList) || (!$hotelsXML->GetHotelsList->HotelsList->Hotel))
		{
			$objDateTime = new \DateTime('NOW');
			$execDate = $objDateTime->format("Y-m-d H:i:s.v");
			file_put_contents($debugFile, $execDate . ": [{$cpos}/{$ccount}]GetHotelsList for city [{$city->Id}|{$city->Name}] " 
				. " in " . (microtime(true) - $t1 . ' seconds') . " - city has no hotels\n", FILE_APPEND);
			\Omi\TF\TOInterface::markReportError($filter, 'no hotels for the city provided');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
			return [false];
		}

		$hcnt = 0;
		//go through each hotel
		foreach ($hotelsXML->GetHotelsList->HotelsList->Hotel as $hotelData)
		{
			$hcnt++;
		}

		\Omi\TF\TOInterface::markReportData($filter, 'count hotels: %s', [$hcnt]);

		$objDateTime = new \DateTime('NOW');
		$execDate = $objDateTime->format("Y-m-d H:i:s.v");
		file_put_contents($debugFile, $execDate . ": [{$cpos}/{$ccount}]GetHotelsList for city [{$city->Id}|{$city->Name}] " 
			. " in " . (microtime(true) - $t1 . ' seconds') . " - {$hcnt} hotels found\n", FILE_APPEND);

		//go through each hotel
		$hpos = 0;
		foreach ($hotelsXML->GetHotelsList->HotelsList->Hotel as $hotelData)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', [(string)$hotelData->ID . ' ' . (string)$hotelData->Name], 50);
			$hpos++;
			$hotel = new \stdClass();
			$hotel->Id = (string)$hotelData->ID;
			$hotel->Name = (string)$hotelData->Name;

			// setup hotel address
			$hotel->Address = new \stdClass();
			$hotel->Address->City = $city;

			if ($filter['get_extra_details'])
			{
				try
				{
					// get hotel details
					$hotelDetails = $this->api_getHotelDetails(['HotelId' => $hotel->Id, '__hcnt' => $hcnt, '__hpos' => $hpos, '__city' => $city, 'force' => $force]);

					if ($hotelDetails->Address)
					{
						// setup geo location
						if ($hotelDetails->Address->Latitude || $hotelDetails->Address->Longitude)
						{
							$hotel->Address->Latitude = $hotelDetails->Address->Latitude;
							$hotel->Address->Longitude = $hotelDetails->Address->Longitude;
						}

						// setup details
						if ($hotelDetails->Address->Details)
							$hotel->Address->Details = $hotelDetails->Address->Latitude;
					}

					// setup web address
					if ($hotelDetails->WebAddress)
						$hotel->WebAddress = $hotelDetails->WebAddress;

					// addd stars
					if ($hotelDetails->Stars)
						$hotel->Stars = $hotelDetails->Stars;

					// setup hotel images
					if ($hotelDetails->Content)
						$hotel->Content = new \stdClass();

					if ($hotelDetails->Content && $hotelDetails->Content->ImageGallery)
						$hotel->Content->ImageGallery = $hotelDetails->Content->ImageGallery;

					// setup hotel description
					if ($hotelDetails->Content->Content)
						$hotel->Content->Content = $hotelDetails->Content->Content;

					if ($hotelDetails->JustForWebsite)
						$hotel->JustForWebsite = $hotelDetails->JustForWebsite;

					if ($hotelDetails->JustForPackages)
						$hotel->JustForWebsite = $hotelDetails->JustForPackages;
				}
				catch (\Exception $ex) 
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Content not pulled for hotel: %s, error message: %s', [$hotel->Id . ' ' . $hotel->Name, $ex->getMessage()], 50);
				}
			}

			// index hotels by id
			$hotels[$hotel->Id] = $hotel;
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
		// return hotels in array
		return $hotels;
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelDetails(array $filter = null)
	{
		// exit if no hotel id on filter
		if (!$filter['HotelId'])
			return [false];

		$debugFile = 'travelio_karpaten_reports/travelio_karpaten_debug_hotels_pull.txt';

		// setup api params
		$params = [
			'HotelID' => $filter['HotelId']
		];

		$force = ($filter && $filter['force']);

		// get api response
		$t1 = microtime(true);
		list($hotelDetailsResp) = $this->request('GetHotelDetails', $params, $filter, (!$force));
		$objDateTime = new \DateTime('NOW');
		$execDate = $objDateTime->format("Y-m-d H:i:s.v");

		file_put_contents($debugFile, $execDate . ": [{$filter['__hpos']}/{$filter['__hcnt']}]GetHotelDetails for hotel [{$filter['HotelId']}] " 
			. ($filter['__city'] ? "from city: [{$filter['__city']->Id}|{$filter['__city']->Name}]" : "") . " in " . (microtime(true) - $t1 . ' seconds') . "\n", FILE_APPEND);
		
		// to xml
		$hotelDetailsXML = simplexml_load_string($hotelDetailsResp);
		
		// exit if different structure
		if (!$hotelDetailsXML->GetHotelDetails)
			return [false];
		
		$hotelDetails = new \stdClass();
		$hotelDetails->Id = (string)$hotelDetailsXML->GetHotelDetails->ID;
		
		$hotelLongitude = (string)$hotelDetailsXML->GetHotelDetails->Longitude;
		$hotelLatitude = (string)$hotelDetailsXML->GetHotelDetails->Latitude;
		
		// setup address
		$hotelDetails->Address = new \stdClass();
		
		
		// setup geo location
		if (floatval($hotelLongitude) || floatval($hotelLatitude))
		{
			$hotelDetails->Address->Latitude = floatval($hotelLatitude);
			$hotelDetails->Address->Longitude = floatval($hotelLongitude);
		}
		
		// setup address details
		$address = (string)$hotelDetailsXML->GetHotelDetails->Address;
		if ($address)
			$hotelDetails->Address->Details = $address;
		
		// setup webaddress
		$website = (string)$hotelDetailsXML->GetHotelDetails->Website;
		if ($website)
			$hotelDetails->WebAddress = $website;
		
		// setup hotel gallery images
		if ($hotelDetailsXML->GetHotelDetails->Images && $hotelDetailsXML->GetHotelDetails->Images->Image)
		{
			if (!$hotelDetails->Content)
				$hotelDetails->Content = new \stdClass();
			$hotelDetails->Content->ImageGallery = new \stdClass();
			$hotelDetails->Content->ImageGallery->Items = [];
			
			foreach ($hotelDetailsXML->GetHotelDetails->Images->Image as $hotelImage)
			{
				$photo_obj = new \stdClass();
				$photo_obj->RemoteUrl = (string)$hotelImage;
				$hotelDetails->Content->ImageGallery->Items[] = $photo_obj;
			}
		}
		
		// setup hotel description
		$description = (string)$hotelDetailsXML->GetHotelDetails->Description;
		if ($description)
		{
			if (!$hotelDetails->Content)
				$hotelDetails->Content = new \stdClass();
			$hotelDetails->Content->Content = $description;
		}
		
		// doar pentru site ???
		$doarPentruSite = (string)$hotelDetailsXML->GetHotelDetails->Doarptsite->Value;
		if ($doarPentruSite)
		{
			$hotelDetails->JustForWebsite = true;
			// qvar_dump("\$doarPentruSite :: ", $doarPentruSite); 
			// q_die('doar pentru site');
		}
		
		// doar pentru site ???
		$doarPachete = (string)$hotelDetailsXML->GetHotelDetails->Doarpachete->Value;
		if ($doarPachete)
		{
			$hotelDetails->JustForPackages = true;
			// qvar_dump("\$doarPachete", $doarPachete); 
			// q_die('doar pachete');
		}
		
		if ($hotelDetailsXML->GetHotelDetails->Classification)
		{
			foreach ($hotelDetailsXML->GetHotelDetails->Classification as $hotelClassification)
			{
				$classificationName = (string)$hotelClassification->Name;
				$classificationValue = (string)$hotelClassification->Value;
				
				// skip empty strings
				if (!$classificationName)
					continue;
				
				if ($classificationName == 'stele')
				{
					// round up the stars
					$hotelDetails->Stars = ceil($classificationValue);
				}
				else
				{
					qvar_dump($classificationName, $classificationValue); 
					//q_die('classification');
				}
			}
		}
		
		$specialOffer = (string)$hotelDetailsXML->GetHotelDetails->SpecialOffer;
		if ($specialOffer)
		{
			#qvar_dump($specialOffer); 
			//q_die('special offer!');
		}
		
		$specialOfferText = (string)$hotelDetailsXML->GetHotelDetails->SpecialOfferText;
		if ($specialOfferText)
		{
			#qvar_dump($specialOfferText); 
			//q_die('special offer text!');
		}
		
		$earlyBooking = (string)$hotelDetailsXML->GetHotelDetails->EarlyBooking;
		if ($earlyBooking)
		{
			#qvar_dump($earlyBooking);
			//q_die('early booking');
		}
		
		/* CategoryID ???
		$categoryId = (string)$hotelDetailsXML->GetHotelDetails->CategoryID;
		if ($categoryId)
		{
			qvar_dump($categoryId);
			q_die('category id');
		}
		*/

		if ((string)$hotelDetailsXML->GetHotelDetails->AdditionalInfo)
		{
			//q_die('additional info');
		}
		
		// Rooms[] ???
		// Prices[] ???
		// CheckInHour ???
		// CheckOutHour ???
		// AdditionalInfo[] ???

		// AgePolicy[] ? ramane - vin 0 ???
		// cod_furnizor[Value] ???
		// company_id[Value] ???
		
		return $hotelDetails;
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsCategories(array $filter = null)
	{
		
	}

	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsFacilities(array $filter = null)
	{
		
	}
	
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getRates(array $filter = null)	
	{
		
	}

	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsRooms(array $filter = null)
	{
		
	}
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsBoards(array $filter = null)
	{
		
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getTours(array $filter = null)
	{
		// get package list
		$packagesByCity = $this->getPackagesListForCities();

		// get all cities
		list($cities) = $this->api_getCities();

		// init transports array
		$transports = [];
		$transportType = 'plane';
		$tours = [];

		// get packags type
		list($packagesType) = $this->getPackagesType();

		$packageTypeTours = [];
		foreach ($packagesType as $packageType)
		{
			if (($packageType->Id == $this->toursPackageTypeId) || ($packageType->ParentId == $this->toursPackageTypeId))
				$packageTypeTours[$packageType->Id] = $packageType->Id;
		}

		foreach ($packagesByCity as $cityId => $packagesList)
		{
			foreach ($packagesList as $package)
			{				
				if (in_array($package['PackageTypeId'], $packageTypeTours))
				{		
					if (!$cities[$package['DepartureResortId']] || !$package['DepartureDates'])
						continue;
					
					foreach ($package['DepartureDates'] as $packageDepartureDate)
					{
						$tourId = $package['Id'] . '|' . $packageDepartureDate['Nights'];
						if ($tours[$tourId])
							continue;
						
						$tour = new \stdClass();
						$tour->Id = $tourId;
						$tour->Title = $package['Name'];

						$tour->Destinations = [];
						$tour->Destinations[] = $tour->Location = $cities[$package['DestinationResortId']];
						$tour->Destinations_Countries = [];
						$tour->Destinations_Countries[] = $cities[$package['DestinationResortId']]->Country;

						// content
						$tour->Content = new \stdClass();
						$tour->Content->Content = $package['Description'];

						$servicesText = '';
						foreach ($package['Services'] as $packageService)
							$servicesText .= '<p>' . $packageService['Name'] . '</p>';

						$tour->Services = $servicesText;
						
						$tour->Period = $packageDepartureDate['Nights'];
						
						$tour->TransportTypes = [$transportType => $transportType];

						// index by id
						$tours[$tour->Id] = $tour;
					}
				}
			}
		}
		
		// return tours
		return $tours;
	}

	/**
	 * Array of: charter, tours, hotel
	 */
	public function api_getServiceTypes()
	{
		
	}
	/**
	 * $filter: Array of: charter, tours, hotel
	 * 
	 * Returns Array of: bus, plane, individual
	 */
	public function api_getTransportTypes(array $filter = null)
	{
		
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers(array $filter = null)
	{
		// check filter and get service type
		$serviceType = $this->checkFilters($filter);
		$ret = null;
		$rawRequests = [];
		switch ($serviceType)
		{
			case 'hotel':
			case 'individual' : 
			{
				// get offers index by hotels
				$hotels = $this->getIndividualOffers($filter);
				// return hotels
				return [$hotels];
			}

			// charter
			case 'charter' : 
			{
				list($charters, $ex, $rawRequests) = $this->getCharterOffers($filter);
				return [$charters, $ex, false, $rawRequests];
			}

			// tour
			case 'tour':
			{
				list($tours, $ex, $rawRequests) = $this->getTourOffers($filter);
				return [$tours, $ex, false, $rawRequests];
			}
		}
		return [null];
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferAvailability(array $filter = null)
	{
		
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferDetails(array $filter = null)
	{
		
	}

	/**
	 * 
	 */
	public function api_getOfferCancelFees(array $filter = null)
	{
		if (!($originalOffer = $filter['OriginalOffer']))
			return null;
		
		$originalOfferArr = is_array($originalOffer) ? $originalOffer : (is_object($originalOffer) ? json_decode(json_encode($originalOffer), true) : null);
		
		if (!$originalOfferArr)
			return null;
		
		$checkIn = $originalOfferArr['DepartureTransportItem'] ? $originalOfferArr['DepartureTransportItem']['DepartureDate'] : null;
		
		if ((!($idPlanTarifar = $originalOfferArr['IdPlanTarif'])) || (!$checkIn) || 
			(!($price = $filter["SuppliedPrice"])) || (!($currency = $filter["SuppliedCurrency"])))
			return;
		
		$feesParams = [
			"check_in" => $checkIn,
			"id_plan" => $idPlanTarifar
		];
		
		$feesParamsIndx = md5(json_encode($feesParams));
		
		if (!static::$FeesAndPaymentsRequests[$feesParamsIndx])
		{
			$feesResp = $this->requestJson('GetPolicy', $feesParams);
			
			static::$FeesAndPaymentsRequests[$feesParamsIndx] = $feesResp ?: false;
		}
		else
			$feesResp = static::$FeesAndPaymentsRequests[$feesParamsIndx];
		
		$feesArr = json_decode($feesResp, true);
		
		if (!$feesArr['cancellation_terms'])
			return;
		
		$cancelFees = [];
		$currencies = [];
		foreach ($feesArr['cancellation_terms'] as $cancellationTerm)
		{
			if ($cancellationTerm['value'] == 0)
				continue;
			
			$cancelFee = new \stdClass();
			$cancelFee->DateStart = date("Y-m-d", strtotime($cancellationTerm['from_data']));
			$cancelFee->DateEnd = date("Y-m-d", strtotime($cancellationTerm['to_data']));
			
			if ($cancellationTerm['type'] == 'percentage')
			{
				$percentage = $cancellationTerm['value'];
				
				if ($cancellationTerm['aditional_vat'])
					$percentage = $cancellationTerm['value'] + 1.19;
				
				$cancelFee->Price = ($price * $percentage) / 100;
			}
			else
				$cancelFee->Price = $cancellationTerm['value'];
			
			$currencyObj = ($currencies[$currency] ?: ($currencies[$currency] = new \stdClass()));
			$currencyObj->Code = $currency;
			$cancelFee->Currency = $currencyObj;
			
			$cancelFees[] = $cancelFee;
		}
		
		return $cancelFees;
	}

	/**
	 * 
	 */
	public function api_getOfferPaymentsPlan(array $filter = null)
	{		
		if (!($originalOffer = $filter['OriginalOffer']))
			return null;
		
		$originalOfferArr = is_array($originalOffer) ? $originalOffer : (is_object($originalOffer) ? json_decode(json_encode($originalOffer), true) : null);
		
		if (!$originalOfferArr)
			return null;
		
		$checkIn = $originalOfferArr['DepartureTransportItem'] ? $originalOfferArr['DepartureTransportItem']['DepartureDate'] : null;
		
		if ((!($idPlanTarifar = $originalOfferArr['IdPlanTarif'])) || (!$checkIn) || 
			(!($price = $filter["SuppliedPrice"])) || (!($currency = $filter["SuppliedCurrency"])))
			return;
		
		$feesParams = [
			"check_in" => $checkIn,
			"id_plan" => $idPlanTarifar
		];
		
		
		$feesParamsIndx = md5(json_encode($feesParams));

		if (!static::$FeesAndPaymentsRequests[$feesParamsIndx])
		{
			$feesResp = $this->requestJson('GetPolicy', $feesParams);
		
			static::$FeesAndPaymentsRequests[$feesParamsIndx] = $feesResp ?: false;
		}
		else
			$feesResp = static::$FeesAndPaymentsRequests[$feesParamsIndx];

		$feesArr = json_decode($feesResp, true);
		
		if (!$feesArr['payment_terms'])
			return;
		
		$paymentFees = [];
		$prevDateTime = null;
		$currencies = [];
		$todayTime = strtotime(date("Y-m-d"));
		foreach ($feesArr['payment_terms'] as $paymentTerm)
		{
			if ($paymentTerm['value'] == 0)
				continue;
			
			$paymentFee = new \stdClass();
			$paymentFee->PayUntil = date("Y-m-d", strtotime($paymentTerm['date_limit']));
			
			if ($paymentTerm['type'] == 'percentage')
			{
				$percentage = $paymentTerm['value'];
				
				if ($paymentTerm['aditional_vat'])
					$percentage = $paymentTerm['value'] + 1.19;
		
				$paymentFee->Amount = ($price * $percentage) / 100;
			}
			else
				$paymentFee->Amount = $paymentTerm['value'];
			
			if ($prevDate !== null)
				$prevDateTime = strtotime("+1 day", strtotime($prevDate));						

			if (($prevDateTime === null) || ($prevDateTime < $todayTime))
				$prevDateTime = $todayTime;
			
			$paymentFee->PayAfter = date("Y-m-d", $prevDateTime);
			
			$currencyObj = ($currencies[$currency] ?: ($currencies[$currency] = new \stdClass()));
			$currencyObj->Code = $currency;
			$paymentFee->Currency = $currencyObj;
			
			$prevDate = $paymentFee->PayUntil;
			
			$paymentFees[] = $paymentFee;
		}
		
		return $paymentFees;
	}

	/**
	 * 
	 */
	public function api_getOfferCancelFeesPaymentsAvailabilityAndPrice(array $filter = null)
	{

	}

	/**
	 * 
	 */
	public function api_getOfferExtraServices(array $filter = null)
	{
		
	}

	/**
	 * Get availabilities dates
	 */
	public function api_getAvailabilityDates(array $filter = null)
	{
		if ((!($isCharter = ($filter['type'] == 'charter'))) && (!($isTour = ($filter['type'] == 'tour'))))
			return;

		// get package list
		$t1 = microtime(true);
		$packagesByCity = $this->getPackagesListForCities($filter);

		// get all cities
		list($cities) = $this->api_getCities([/*'force' => $filter['force'] ?: false*/]);

		// init transports array
		$transports = [];

		// get packags type
		list($packagesType, /*$ptypesTree*/) = $this->getPackagesType();

		$packageTypeTours = [];
		foreach ($packagesType as $packageType)
		{
			if (($packageType->Id == $this->toursPackageTypeId) || ($packageType->ParentId == $this->toursPackageTypeId))
				$packageTypeTours[$packageType->Id] = $packageType->Id;
		}

		$toCitiesByPackageTypes = [];
		foreach ($packagesByCity ?: [] as $packagesList)
		{
			foreach ($packagesList as $package)
			{
				$packageType = $packagesType[$package['PackageTypeId']];

				if ($packageType && (($packageType->Id == $this->individualPackageTypeId) || 
					($packageType->Parents && in_array($this->individualPackageTypeId, $packageType->Parents))))
				{
					echo '<div style="color: red;">SKIP INDIVIDUAL TYPE</div>';
					continue;
				}
				
				$packageIsTour = in_array($package['PackageTypeId'], $packageTypeTours);
				$packageIsCharter = in_array($package['PackageTypeId'], $this->chartersPackagesIds);

				$usePackageTypeId = $package['PackageTypeId'] ?: 0;

				if (($isCharter && $packageIsCharter) || ($isTour && $packageIsTour))
				{
					$transportType = ($package['Transportation'] == 'autocar') ? 'bus' : 'plane';

					if ((!$cities[$package['DepartureResortId']]) || (!$package['DepartureDates']) || (!$package['ResortId']))
					{
						echo '<div style="color: red;">Package skipped: ' . $package['Id'] . ' : DepartureResortId|DepartureDates|ResortId is missing</div>';
						continue;
					}
					
					$toCityId = $isCharter ? $package['ResortId'] : $package['DestinationResortId'];

					if (!($toCity = $cities[$toCityId]))
					{
						#qvardump('$package', $package);
						echo '<div style="color: red;">Package skipped: ' . $package['Id'] . ' : To city is missing</div>';
						continue;
					}
					
					$toCitiesByPackageTypes[$usePackageTypeId][$toCity->Id] = $toCity->Name . ($toCity->Country ? ", " . $toCity->Country->Name : "");

					#if (!$package['PackageTypeId'])
					#	qvardump('$package', $package);

					if (!($fromCity = $cities[$package['DepartureResortId']]))
					{
						echo '<div style="color: red;">Package skipped: ' . $package['Id'] . ' : From city is missing</div>';
						continue;
					}

					$transportId = $transportType . "~city|" . $fromCity->Id . "~city|" . $toCity->Id;

					if (!isset($transports[$transportId]))
					{
						$transports[$transportId] = new \stdClass();
						$transports[$transportId]->Id = $transportId;
						$transports[$transportId]->Content = new \stdClass();
						$transports[$transportId]->Content->Active = true;
						$transports[$transportId]->Dates = [];
					}

					if (!isset($transports[$transportId]->TransportType))
						$transports[$transportId]->TransportType = $transportType;

					if (!isset($transports[$transportId]->From))
					{
						$transports[$transportId]->From = new \stdClass();
						$transports[$transportId]->From->City = $fromCity;
					}

					if (!isset($transports[$transportId]->To))
					{
						$transports[$transportId]->To = new \stdClass();
						$transports[$transportId]->To->City = $toCity;
					}

					foreach ($package['DepartureDates'] as $packagedepartureDate)
					{
						if (!isset($transports[$transportId]->Dates[$packagedepartureDate['DepartureDate']]))
						{
							$dateObj = new \stdClass();
							$dateObj->Date = date('Y-m-d', strtotime($packagedepartureDate['DepartureDate']));
							$dateObj->Nights = [];
							$transports[$transportId]->Dates[$packagedepartureDate['DepartureDate']] = $dateObj;
						}
						$days = $packagedepartureDate['Nights'];
						if (!isset($transports[$transportId]->Dates[$packagedepartureDate['DepartureDate']]->Nights[$days]))
						{
							$nightsObj = new \stdClass();
							$nightsObj->Nights = $days;

							$transports[$transportId]->Dates[$packagedepartureDate['DepartureDate']]->Nights[$days] = $nightsObj;
						}
					}
				}
			}
		}

		/*
		foreach ($transports ?: [] as $transportId => $transportData)
		{
			echo ' from [' . $transportData->From->City->Id . '] ' . $transportData->From->City->Name . ' (' 
				. $transportData->From->City->Country->Code . '|' . $transportData->From->City->Country->Name . ')' 
				. ' - to [' . $transportData->To->City->Id . '] ' . $transportData->To->City->Name . ' ('
				. $transportData->To->City->Country->Code . '|' . $transportData->To->City->Country->Name . ')' 
				. ' transport cu ' . $transportType . '<br/>';

			foreach ($transportData->Dates ?: [] as $d)
			{
				echo '<div style="padding-left: 40px;">' . $d->Date . '</div>';
			}
		}

		#q_die();
		*/

		return [$transports, null, [], [], $toCitiesByPackageTypes];
	}

	/**
	 * @param array $filter
	 */
	public function api_prepareBooking(array $filter = null)
	{
		
	}

	/**
	 * Do Booking
	 * @param array $data
	 */
	public function api_doBooking(array $filter = null)
	{		
		$offer = $filter["Items"] ? reset($filter["Items"]) : null;
		$currency = $filter['Currency']['Code'];
		
		if (!$offer)
			throw new \Exception("Offer not found!");
		
		#if (!$offer["Room_Code"] || !$offer['Room_CheckinAfter'] || !$offer['Room_CheckinBefore'] || !$currency || !$offer['Offer_Package_Id'])
		if (!$offer["Room_Code"] || !$offer['Room_CheckinAfter'] || !$offer['Room_CheckinBefore'] || !$currency)
			throw new \Exception("Offer cannot be identified because no room combination price id!");

		// get passengers
		$passengers = $filter['Passengers'];

		$billingTo = $filter['BillingTo'];

		$titularData = [
			'nume' => $billingTo['Lastname'],
			'prenume' => $billingTo['Firstname'],
			//'email' => $billingTo['Email'],
			'email' => 'test@test.com'
		];

		$touristsData = [];
		$diffDate = date_create($offer['Room_CheckinBefore']);
		foreach ($passengers as $key => $passenger)
		{
			$age = date_diff($diffDate, date_create($passenger['BirthDate']))->y;

			// if ($age < $this->infantTillAgeOf)
			// 	$passengerTitle = 'INF';
			// else			
				$passengerTitle = (($passenger['Type'] == 'adult') ? 'ADT' : 'CHD');
			
			$touristsData[] = [
				'item' => [
					'nr' => ($key + 1),
					'titlu' => $passengerTitle,
					'nume' => $passenger['Lastname'],
					'prenume' => $passenger['Firstname'],
					'data_nasterii' => $passenger['BirthDate']
				]
			];
		}
		
		$roomTypeId = reset(explode('~', $offer["Room_Code"]));
		
		$roomData = [
			'item' => [
				'room_info' => [
					'id_camera' => $roomTypeId,
					'id_pensiune' => $offer["Board_Type_InTourOperatorId"]
				],
				'turist' => [
					$touristsData
				]
			]
		];

		// set params
		$params = [
			'v2' => 1,
			'factura_pe' => ($billingTo['Company'] ? 'pj' : 'pf'),
			'id_hotel' => $offer['Offer_Tour_Hotel_Id'] ?: $offer["Hotel"]['Code'],
			// 'id_camera' => $roomTypeId,
			// 'id_pensiune' => $offer["Board_Type_InTourOperatorId"],
			'id_pachet' => $offer['Offer_Package_Id'] ?: null,
			'data_start' => $offer['Room_CheckinAfter'], 
			'data_sfarsit' => $offer['Room_CheckinBefore'],
			'titular' => $titularData,
			'nr_camere' => 1, 
			'total_turisti' => count($passengers),
			'camera' => $roomData
		];
		
		if ($offer['Offer_Partaj'])
		{
			$params['extra'] = [
				'partaj' => $offer['Offer_Partaj']
			];
		}
		
		// get api response
		list($reservationResp) = $this->request('Reservation', $params, $filter, false, true);

		// to xml
		$reservationXML = simplexml_load_string($reservationResp);

		if ((!$reservationXML->Reservation))
		{
			throw new \Exception('Rezervarea nu a putut fi efectuata. Raspunsul complet din sistemul tur operatorului este: ' . $reservationResp);
		}

		if (isset($reservationXML->Reservation->detalii_err))
		{
			throw new \Exception('Rezervarea nu fost efectuata. Mesajul de eroare este: ' . $reservationXML->Reservation->detalii_err);
		}

		if (!$reservationXML->Reservation->reservationID)
		{
			throw new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
		}

		$order = new \stdClass();
		$order->Id = $reservationXML->Reservation->reservationID;
		$order->InTourOperatorRef = $reservationXML->Reservation->reservationID;
		
		return [$order, $reservationResp];
	}

	/**
	 * @param array $filter
	 */
	public function api_getBookings(array $filter = null)
	{
		
	}

	/**
	 * @param array $filter
	 */
	public function api_cancelBooking(array $filter = null)
	{
		
	}
	
	/* ----------------------------plane tickets operators---------------------*/

	public function api_getCarriers(array $filter = null)
	{
		
	}

	public function api_getAirports(array $filter = null)
	{
		
	}

	public function api_getRoutes(array $filter = null)
	{
		
	}
	
	public function api_getChartersRequests()
	{
		
	}

	
	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "travelio_karpaten";
	}

	public function getSimpleCacheFileForUrl($url, $xml_str, $format = "xml")
	{		
		$cacheDir = $this->getResourcesDir() . "cache/";
		if (!is_dir($cacheDir))
			//qmkdir($cacheDir); modificat
			mkdir($cacheDir);
		return $cacheDir . "cache_" . md5($url . "|" . $xml_str . "|" . $format) . "." . $format;
	}

	/**
	 * First will try to login and then prepare params for the SOAP request.
	 * 
	 * @param type $module
	 * @param type $method
	 * @param type $params
	 * 
	 * @return type
	 * 
	 * @throws \Exception
	 */
	public function request($method, $params = [], $filter = [], $useCache = false, $logData = false)
	{
		$this->_lastResponse = null;

		if (!$logData)
			$logData = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));

		if (substr($this->ApiUrl, -1) !== '/')
			$this->ApiUrl = $this->ApiUrl . '/';

		$useJson = $filter['req_json'];

		$t1 = microtime(true);
		$mainParams = [];
		$mainParams['head'] = [
			'auth' => [
				'username' => ($this->ApiUsername__ ?: $this->ApiUsername),
				'password' => ($this->ApiPassword__ ?: $this->ApiPassword)
			],
			'service' => $method
		];

		$mainParams['main'] = [
			'v2' => '1'
		];

		if ($params)
		{
			foreach ($params as $key => $param)
				$mainParams['main'][$key] = $param;
		}

		// creating object of SimpleXMLElement
		$xml_data = new \SimpleXMLElement('<?xml version="1.0"?><root></root>');

		// function call to convert array to xml
		$this->array_to_xml($mainParams, $xml_data);

		$toSendReqXml = $xml_data->asXML();

		$cache_file = null;

		$result = null;
		$useCacheResult = false;

		if ($useCache)
		{
			// get cache file path
			$cache_file = $this->getSimpleCacheFileForUrl(($this->ApiUrl__ ?: $this->ApiUrl), json_encode([
				'params' => $params,
				'filter' => $filter,
				'toSendReqXml' => $toSendReqXml
			]), ($useJson ? 'json' : 'xml'));
			
			// last modified
			$cf_last_modified = ($f_exists = file_exists($cache_file)) ? filemtime($cache_file) : null;
			$cache_time_limit = time() - $this->requestCacheTimeLimit;

			// if exists - last modified
			if (($f_exists) && ($cf_last_modified >= $cache_time_limit))
			{
				#qvardump('use cache: \$f_exists', $cache_file, $f_exists, date("Y-m-d H:i:s", $cache_time_limit), date("Y-m-d H:i:s", $cf_last_modified));
				$result = file_get_contents($cache_file);
				$useCacheResult = true;
			}
		}

		if (!$useCacheResult)
		{dump($toSendReqXml);
			// open connection
			$ch = q_curl_init_with_log();

			// file_put_contents('request_' . $method . '.xml', $xml_data->asXML());

			// set the url, number of POST vars, POST data
			q_curl_setopt_with_log($ch, CURLOPT_URL, ($this->ApiUrl__ ?: $this->ApiUrl));
			q_curl_setopt_with_log($ch, CURLOPT_POST, true);
			q_curl_setopt_with_log($ch, CURLOPT_POSTFIELDS, $toSendReqXml);
			q_curl_setopt_with_log($ch, CURLOPT_HTTPHEADER, array($useJson ? 'Content-Type: application/json' : 'Content-Type: application/xml'));
			q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, true);
			// ------------------added-------------------------
			q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYPEER, false);

			/*
			if(q_curl_exec_with_log($ch) === false)
			{
				echo 'Curl error: ' . curl_error($ch); 
				q_die();
			}
			else
			{
				echo 'Operation completed without any errors';
			}
			*/

			// Execute the POST request
			$t1 = microtime(true);
			$result = q_curl_exec_with_log($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			$_trq = (microtime(true) - $t1);
			$this->_lastResponse = $rawResult = $result;

			if ($logData)
			{
				// log paradise data
				$this->logData("request." . ($filter && $filter["cityId"] ? $filter["cityId"] : "_") . "." . $method, [
					"\$url" => ($this->ApiUrl__ ?: $this->ApiUrl),
					"\$method" => $method,
					'\$params' => $params,
					'\$filter' => $filter,
					"respXML" => $result,
					"reqXML" => $toSendReqXml,
					"duration" => (microtime(true) - $t1) . " seconds"
				]);
			}

			// file_put_contents('response_' . $method . '.xml', $result);

			// show error
			if ($result === false)
			{
				#echo 'Curl error: ' . curl_error($ch);
				//q_die();
				$ex = new \Exception("Invalid response from server - " . curl_error($ch));
				$this->logError([
					"\$url" => ($this->ApiUrl__ ?: $this->ApiUrl),
					"\$method" => $method,
					'\$params' => $params,
					'\$filter' => $filter,
					"respXML" => $result,
					"reqXML" => $toSendReqXml,
					"duration" => (microtime(true) - $t1) . " seconds"
				], $ex);

				throw $ex;
			}

			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($httpcode >= 400)
			{
				# we have an error
				$ex = new \Exception($result);
				$this->logError([
					"\$url" => ($this->ApiUrl__ ?: $this->ApiUrl),
					"\$method" => $method,
					'\$params' => $params,
					'\$filter' => $filter,
					"respXML" => $result,
					"reqXML" => $toSendReqXml,
					"duration" => (microtime(true) - $t1) . " seconds"
				], $ex);
				throw $ex;
			}
		}

		if (!$useJson)
		{
			$srxml = simplexml_load_string($result);
			$result_Decoded = $this->simpleXML2Array($srxml);
		}
		else
		{
			$result_Decoded = json_decode($result, true);
			if ($result_Decoded === false)
				$result_Decoded = "Error in decoding response " . json_last_error_msg();
		}

		if (is_scalar($result_Decoded) || isset($result_Decoded['main']['error']))
		{
			$exMessage = is_scalar($result_Decoded) ? $result_Decoded : (isset($result_Decoded['main']['error'][0]) ? $result_Decoded['main']['error'][0] : 
				json_encode($result_Decoded['main']['error']));

			if (empty($exMessage) && \QAutoload::GetDevelopmentMode())
			{
				#qvardump('$result_Decoded', $result_Decoded, $result);
			}

			$ex = new \Exception($exMessage);
			$this->logError([
				"\$url" => ($this->ApiUrl__ ?: $this->ApiUrl),
				"\$method" => $method,
				'\$params' => $params,
				'\$filter' => $filter,
				"respXML" => $result,
				"reqXML" => $toSendReqXml,
				"duration" => (microtime(true) - $t1) . " seconds"
			], $ex);
			throw $ex;
		}

		if ($useCache && $cache_file && (!$useCacheResult))
		{
			#qvardump('save cache: ', $cache_file);
			file_put_contents($cache_file, $result);
		}

		$reqData = [
			'\$url' => ($this->ApiUrl__ ?: $this->ApiUrl),
			'\$toSendPostData' => $params,
			'\$topRetInfo' => $info,
		];

		$callKeyIdf = md5(json_encode($reqData));

		$toProcessRequest = [
			$method,
			json_encode($reqData),
			$result,
			$callKeyIdf,
			$_trq
		];

		return [$result, $toProcessRequest, $rawResult, $info, $toSendReqXml];
	}

	/**
	 * First will try to login and then prepare params for the SOAP request.
	 * 
	 * @param type $module
	 * @param type $method
	 * @param type $params
	 * 
	 * @return type
	 * 
	 * @throws \Exception
	 */
	public function requestJson($method, $params = [], $filter = [], $logData = false)
	{
		if (!$logData)
			$logData = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));

		$arrayData = [
			'root' => [
				'head' => [
					'auth' => [
						'username' => ($this->ApiUsername__ ?: $this->ApiUsername),
						'password' => ($this->ApiPassword__ ?: $this->ApiPassword)
					],
					'service' => $method
				],
				'main' => $params
			]
		];
		
		$jsonData = json_encode($arrayData);
		
		// open connection
		$ch = q_curl_init_with_log();
		
		// set the url, number of POST vars, POST data
		q_curl_setopt_with_log($ch, CURLOPT_URL, ($this->ApiUrl__ ?: $this->ApiUrl) . 'json');
		q_curl_setopt_with_log($ch, CURLOPT_POST, true);
		q_curl_setopt_with_log($ch, CURLOPT_POSTFIELDS, $jsonData);
		q_curl_setopt_with_log($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, true);
		
		// Execute the POST request
		$result = q_curl_exec_with_log($ch);

		// show error
		if ($result === false)
		{
			echo 'Curl error: ' . curl_error($ch);
			#q_die();
		}

		if ($logData)
		{
			$this->logDataSimple($method, [
				"\$method" => $method,
				"\$params" => $params,
				"\$filter" => $filter,
				"reqJSON" => $jsonData,
				"respJSON" => $result,
				"\$url" => ($this->ApiUrl__ ?: $this->ApiUrl) . 'json',
				"\$arrayData" => $arrayData
			]);
		}
		
		return $result;
	}
	
	/**
	 * Get charter offers based on a filter
	 * 
	 * @param array $filter
	 * @throws \Exception
	 */
	public function getCharterOffers(array $filter = null)
	{
		$chartersEx = null;
		$rawReqs = [];

		try
		{
			if (!$filter['cityId'])
				throw new \Exception('Missing city id!');

			// get checkin
			$checkIn = $filter["checkIn"];

			// calculate checkout date
			$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

			// get only first room
			$room = reset($filter['rooms']);

			$adults = $room['adults'];
			$children = $room['children'] ?: 0;
			$roomChildrenAges = $room['childrenAges'];

			$transportType = $filter['transportTypes'][array_key_first($filter['transportTypes'] ?? [])];

			$params = [
				'Currency' => 'EUR',
				'ResortID' => $filter['cityId'],
				'CheckIn' => $checkIn,
				'CheckOut' => $checkOut,
				'DepartureCityId' => $filter['departureCity'],
				'Rooms' => [
					'Room' => [
						'Adults' => $adults,
						'Children' => $children,
					]
				]
			];

			if ($filter['travelItemId'])
				$params['HotelID'] = $filter['travelItemId'];

			if (($children > 0) && (count($roomChildrenAges) == $children))
			{
				$params['Rooms']['Room']['ChildrenAges'] = [];
				foreach ($roomChildrenAges as $roomChildAge)
					$params['Rooms']['Room']['ChildrenAges'][] = ['ChildAge' => $roomChildAge];
			}

			$t1 = microtime(true);
			$useAsync = true;

			$isRawResponse = ($filter && $filter['rawResponse']);
			$respForRawReq = [];
			
			$useCache = false;

			if ($useAsync)
			{
				$params['SearchType'] = 'packages';
				if (!$filter)
					$filter = [];
				$filter['req_json'] = true;
				list($startAsyncResp, $toSaveRequest) = $this->request('StartAsyncSearch', $params, $filter, $useCache, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

				if ($toSaveRequest && $filter['_in_resync_request'])
					$rawReqs[] = $toSaveRequest;
				
				$startAsyncData = json_decode($startAsyncResp, true);

				$qresps = [];
				$hotelsResps = [];
				if (($searchId = $startAsyncData['searchId']))
				{
					$finished = false;
					$checkAtLeastSecs = 1;
					$checkTiming = 60;
					$dopos = 0;
					$t1 = microtime(true);
					do 
					{
						$filter['_dopos_'] = $dopos;
						list($resultsResp, $toSaveRequest) = $this->request('GetSearchResults', ["searchId" => $searchId], $filter, $useCache, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

						if ($toSaveRequest && $filter['_in_resync_request'])
							$rawReqs[] = $toSaveRequest;

						if ((!($resultsDecoded = json_decode($resultsResp, true))) || (!($status = $resultsDecoded['status'])))
							break;

						if ($status == 'running')
						{
							$qresps[] = $resultsDecoded;
						}
						
						if (isset($resultsDecoded['searchResults']))
						{
							$sres = $resultsDecoded['searchResults'];
							if (is_array($sres) && isset($sres['id']))
								$sres = [$sres];
							foreach ($sres ?: [] as $h)
								$hotelsResps[] = $h;
						}

						$finished = ($status == 'finished');

						if (!$useCache)
							sleep($checkAtLeastSecs);

						if ($dopos > $checkTiming)
							break;
						$dopos++;
					}
					while (!$finished);

					$respForRawReq = [
						[
							"SearchPackages" => [
								"PackagesList" => [
									"Package" => $hotelsResps
								]
							]
						]
					];

				}
			}
			else
			{
				$params['SearchTimeout'] = 80;
				
				// get offers
				list($offersPackagesResp, $toSaveRequest) = $this->request('SearchPackages', $params, $filter, false, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

				if ($toSaveRequest && $filter['_in_resync_request'])
					$rawReqs[] = $toSaveRequest;

				// get xml
				$offersPackagesXML = simplexml_load_string($offersPackagesResp);

				if ($isRawResponse)
				{
					$respForRawReq[] = $this->simpleXML2Array($offersPackagesXML);
				}

				if ((!$offersPackagesXML->SearchPackages) || (!$offersPackagesXML->SearchPackages->PackagesList) || (!$offersPackagesXML->SearchPackages->PackagesList->Package))
					return [false, $chartersEx, $rawReqs];
			}

			if ($isRawResponse)
			{
				$byHotels = [];
				foreach ($respForRawReq ?: [] as $respForRReq)
				{
					if (isset($respForRReq['SearchPackages']['PackagesList']['Package']) && ($package = $respForRReq['SearchPackages']['PackagesList']['Package']) && 
						is_array($package))
					{
						if (isset($package['ID']))
							$package = [$package];
						else if (isset($package['id']))
							$package = [$package];
						foreach ($package ?: [] as $p)
						{
							
							if (!($hotelData = (isset($p['Hotel']) ? $p['Hotel'] : $p['hotel'])))
							{
								continue;
							}
							if (!($hotelId = isset($hotelData['ID']) ? $hotelData['ID'] : $hotelData['id']))
							{
								continue;
							}
							$byHotels[$hotelId]['Hotel'] = $hotelData;
							$byHotels[$hotelId]['Packages'][] = $p;
						}
					}
				}

				return [$byHotels, $respForRawReq];
			}

			$hotels = [];
			$indexedHotels = [];
			
			$eoffs = [];

			if ($useAsync)
			{
				#if (isset($respForRawReq['SearchPackages']['PackagesList']['Package']))
				foreach ($respForRawReq ?: [] as $respForRawReq_pd)
				{
					if (isset($respForRawReq_pd['SearchPackages']['PackagesList']['Package']))
					{
						foreach ($respForRawReq_pd['SearchPackages']['PackagesList']['Package'] ?: [] as $packageData)
						{
							foreach ($packageData ?: [] as $k => $v)
							{
								if (is_array($v))
								{
									foreach ($v ?: [] as $kk => $vv)
									{
										// go 3 levels
										if (is_array($vv))
										{
											foreach ($vv ?: [] as $kkk => $vvv)
											{
												$packageData[$k][$kk][strtolower($kkk)] = $vvv;
											}
										}
										$packageData[$k][strtolower($kk)] = $vv;
									}
								}
								$packageData[strtolower($k)] = $v;
							}

							if ((!isset($packageData['hotel']['id']))  || (!isset($packageData['transportation'])) || (!isset($packageData['prices'])))
								continue;

							$departureTransportData = $packageData['transportation']['departure'];
							$returnTransportData = $packageData['transportation']['return'];

							$dep_departureDate = date('Y-m-d', strtotime((string)$departureTransportData["DepartureDate"]));
							$dep_arrivalDate = date('Y-m-d', strtotime((string)$departureTransportData["ArrivalDate"]));

							$ret_departureDate = date('Y-m-d', strtotime((string)$returnTransportData["DepartureDate"]));
							$ret_arrivalDate = date('Y-m-d', strtotime((string)$returnTransportData["ArrivalDate"]));

							$departureRouteName = (string)$departureTransportData["RouteName"];
							$returnRouteName = (string)$returnTransportData["RouteName"];

							$dep_departureAirportCode = reset(explode('-', $departureRouteName));
							$dep_arrivalAirportCode = end(explode('-', $departureRouteName));

							$ret_departureAirportCode = reset(explode('-', $returnRouteName));
							$ret_arrivalAirportCode = end(explode('-', $returnRouteName));

							// departure transport item
							$departureTransportMerch = new \stdClass();
							// $departureTransportMerch->Title = "Dus: ".($toUseCheckIn ? date("d.m.Y", strtotime($toUseCheckIn)) : "");
							$departureTransportMerch->Title = 'Dus: ' . $dep_departureAirportCode . ' ' 
								. $dep_arrivalAirportCode . ' ' . (string)$departureTransportData["DepartureDate"] . ' ' 
								. (string)$departureTransportData["DepartureTime"] . ' - ' . (string)$departureTransportData["ArrivalDate"] . ' ' 
								. (string)$departureTransportData["ArrivalTime"];

							$departureTransportMerch->DepartureTime = $dep_departureDate . ' ' . (string)$departureTransportData["DepartureTime"];
							$departureTransportMerch->ArrivalTime = $dep_arrivalDate . ' ' . (string)$departureTransportData["ArrivalTime"];
							$departureTransportMerch->TransportType = $transportType;
							$departureTransportMerch->Category = new \stdClass();
							$departureTransportMerch->Category->Code = 'other-outbound';

							$departureTransportMerch->From = new \stdClass();
							$departureTransportMerch->From->City = new \stdClass();
							$departureTransportMerch->From->City->Id = $filter['departureCity'];
							
							$departureTransportMerch->To = new \stdClass();
							$departureTransportMerch->To->City = new \stdClass();
							$departureTransportMerch->To->City->Id = $filter['cityId'];

							// departure transport merch
							$departureTransportMerch->DepartureAirport = $dep_departureAirportCode;
							$departureTransportMerch->ReturnAirport = $dep_arrivalAirportCode;

							$currency = new \stdClass();
							$currency->Code = $params['Currency'];

							// departure transport itm
							$departureTransportItm = new \stdClass();
							$departureTransportItm->Merch = $departureTransportMerch;
							$departureTransportItm->Quantity = 1;
							$departureTransportItm->Currency = $currency;
							$departureTransportItm->UnitPrice = 0;
							$departureTransportItm->Gross = 0;
							$departureTransportItm->Net = 0;
							$departureTransportItm->InitialPrice = 0;
							$departureTransportItm->DepartureDate = $dep_departureDate;
							$departureTransportItm->ArrivalDate = $dep_arrivalDate;

							// for identify purpose
							$departureTransportItm->Id = $departureTransportMerch->Id;

							// return transport item
							$returnTransportMerch = new \stdClass();
							$returnTransportMerch->Title = 'Retur: ' . $ret_departureAirportCode . ' ' . $ret_arrivalAirportCode . ' ' 
								. (string)$returnTransportData["DepartureDate"] . ' ' . (string)$returnTransportData["DepartureTime"] . ' - ' 
								. (string)$returnTransportData["ArrivalDate"] . ' ' . (string)$returnTransportData["ArrivalTime"];

							$returnTransportMerch->DepartureTime = $ret_departureDate . ' ' . (string)$returnTransportData["DepartureTime"];
							$returnTransportMerch->ArrivalTime = $ret_arrivalDate . ' ' . (string)$returnTransportData["ArrivalTime"];
							$returnTransportMerch->TransportType = $transportType;
							$returnTransportMerch->Category = new \stdClass();
							$returnTransportMerch->Category->Code = 'other-inbound';
							$returnTransportMerch->DepartureAirport = $ret_departureAirportCode;
							$returnTransportMerch->ReturnAirport = $ret_arrivalAirportCode;
							
							$returnTransportMerch->From = new \stdClass();
							$returnTransportMerch->From->City = new \stdClass();
							$returnTransportMerch->From->City->Id = $filter['cityId'];
							
							$returnTransportMerch->To = new \stdClass();
							$returnTransportMerch->To->City = new \stdClass();
							$returnTransportMerch->To->City->Id = $filter['departureCity'];

							$returnTransportItm = new \stdClass();
							$returnTransportItm->Merch = $returnTransportMerch;
							$returnTransportItm->Quantity = 1;
							$returnTransportItm->Currency = $currency;
							$returnTransportItm->UnitPrice = 0;
							$returnTransportItm->Gross = 0;
							$returnTransportItm->Net = 0;
							$returnTransportItm->InitialPrice = 0;
							$returnTransportItm->DepartureDate = $ret_departureDate;
							$returnTransportItm->ArrivalDate = $ret_arrivalDate;
							
							$hasTransferIncluded = false;
							$hasAirportTaxesIncluded = false;
							if ($packageData['services'])
							{								
								foreach ($packageData['services'] as $packageServices)
								{
									if (($packageServices['nume'] == 'Transfer aeroport - hotel - aeroport') && ($packageServices['tip_oblig'] == 'Inclus'))
										$hasTransferIncluded = true;
									
									if (($packageServices['nume'] == 'Taxe de aeroport') && ($packageServices['tip_oblig'] == 'Inclus'))
										$hasAirportTaxesIncluded = true;
								}
							}							

							// for identify purpose
							$returnTransportItm->Id = $returnTransportMerch->Id;
							$departureTransportItm->Return = $returnTransportItm;

							$hotelId = $packageData['hotel']['id'];
							// init hotel object
							$hotel = $indexedHotels[$hotelId] ?: ($indexedHotels[$hotelId] = new \stdClass());

							// set hotel id as the code from amara
							$hotel->Id = (string)$hotelId;

							if (isset($packageData["prices"]['rooms']))
								$packageData["prices"] = [];

							foreach ($packageData["prices"] as $offerResponse)
							{

								if (!isset($offerResponse['rooms']))
								{
									// not well formed
									continue;
								}

								if (isset($offerResponse['rooms']['room']))
									$offerResponse['rooms'] = [$offerResponse['rooms']];

								foreach ($offerResponse['rooms'] ?: [] as $room)
								{
									if (!($roomData = $room['room']))
										continue;

									$offerCode = $hotel->Id . '~' . (string)$roomData['id'] . '~' . (string)$roomData['boardId'] . '~' . $checkIn . '~' . $checkOut;

									#echo $offerCode . '<br/>';

									// init offer
									$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
									
									if ($offer->Gross && ($offer->Gross > (float)$offerResponse["total"]))
									{
										// net price
										$offer->Net = $offerResponse["totalNet"] ? (float)$offerResponse["totalNet"] : (float)$offerResponse["total"];

										// offer total price
										$offer->Gross = (float)$offerResponse["total"];

										$totalDiscount = (float)$offerResponse["totalDiscount"];
										$agencyDiscount = (float)$offerResponse["agencyDiscount"];

										// get initial price
										$offer->InitialPrice = $totalDiscount ? ($offer->Gross + $totalDiscount) : $offer->Gross;
										// $offer->InitialPrice = $agencyDiscount ? ($offer->InitialPrice - $agencyDiscount) : $offer->InitialPrice;
										
										continue;
									}
									
									$offer->Code = $offerCode;

									$offer->Package_Id = (string)$packageData['id'];

									// set offer currency
									$offer->Currency = new \stdClass();
									$offer->Currency->Code = (string)$offerResponse["currency"];

									// net price
									$offer->Net = $offerResponse["totalNet"] ? (float)$offerResponse["totalNet"] : (float)$offerResponse["total"];

									// offer total price
									$offer->Gross = (float)$offerResponse["total"];

									$totalDiscount = (float)$offerResponse["totalDiscount"];
									$agencyDiscount = (float)$offerResponse["agencyDiscount"];

									// get initial price
									$offer->InitialPrice = $totalDiscount ? ($offer->Gross + $totalDiscount) : $offer->Gross;
									// $offer->InitialPrice = $agencyDiscount ? ($offer->InitialPrice - $agencyDiscount) : $offer->InitialPrice;

									// number of days needed for booking process
									$offer->Days = (string)$offerResponse["nights"];

									// room
									$roomType = new \stdClass();
									$roomType->Id = (string)$roomData['id'];
									$roomType->Title = (string)$roomData['name'];

									$roomMerch = new \stdClass();
									$roomMerch->Title = (string)$roomData['name'];
									$roomMerch->Type = $roomType;
									$roomMerch->Code = (string)$roomData['id'];
									$roomMerch->Name = (string)$roomData['name'];

									$roomItm = new \stdClass();
									$roomItm->Merch = $roomMerch;
									$roomItm->Id = (string)$roomData['id'];

									//required for indexing
									$roomItm->Code = (string)$roomData['id'];
									#$roomItm->CheckinAfter = $checkIn;
									#$roomItm->CheckinBefore = $checkOut;

									$roomItm->CheckinAfter = $departureTransportItm->ArrivalDate;
									$roomItm->CheckinBefore = $returnTransportItm->DepartureDate;

									$roomItm->Currency = $offer->Currency;
									$roomItm->Quantity = 1;

									// set ne price on room
									$roomItm->Net = (string)$roomData["totalRoomPrice"];

									// Q: set initial price :: priceOld
									$roomItm->InitialPrice = (string)$roomData["totalRoomPrice"];

									$roomDisponibility = (string)$roomData["availability"];
									$offer->Availability = ($roomDisponibility == 'Available') ? 'yes' : 'ask';

									$roomItm->Availability = $offer->Availability;

									if (!$offer->Rooms)
										$offer->Rooms = [];

									$offer->Rooms[] = $roomItm;

									// board
									$boardType = new \stdClass();
									$boardType->Id = (string)$roomData["boardId"];

									$boardName = (string)$roomData["boardName"];
									$boardNiceName = (string)$roomData["boardNiceName"];
									$boardType->Title = $boardNiceName ?: $boardName;

									$boardMerch = new \stdClass();
									$boardMerch->Title = $boardNiceName ?: $boardName;
									$boardMerch->Type = $boardType;

									$boardItm = new \stdClass();
									$boardItm->Merch = $boardMerch;
									$boardItm->Currency = $offer->Currency;
									$boardItm->Quantity = 1;
									$boardItm->UnitPrice = 0;
									$boardItm->Gross = 0;
									$boardItm->Net = 0;
									$boardItm->InitialPrice = 0;

									// for identify purpose
									$boardItm->Id = $boardMerch->Id;

									// add items to offer
									$offer->Item = $roomItm;
									$offer->MealItem = $boardItm;

									$offer->DepartureTransportItem = $departureTransportItm;
									$offer->ReturnTransportItem = $returnTransportItm;
									
									$offer->Items = [];
									
									if ($hasTransferIncluded)
									{
										$transferCategory = new \stdClass();
										$transferCategory->Id = static::TransferItmIndx;
										$transferCategory->Code = static::TransferItmIndx;
										
										$transferMerch = new \stdClass();
										$transferMerch->Category = $transferCategory;
										$transferMerch->Code = uniqid();
										$transferMerch->Title = "Transfer inclus";
										$transferItem = new \stdClass();
										$transferItem->Merch = $transferMerch;
										$transferItem->Currency = $currency;
										$transferItem->Quantity = 1;
										$transferItem->UnitPrice = 0;
										$transferItem->Availability = "yes";
										$transferItem->Gross = 0;
										$transferItem->Net = 0;
										$transferItem->InitialPrice = 0;

										// for identify purpose
										$transferItem->Id = $transferMerch->Id;

										$offer->Items[] = $transferItem;
									}
									
									if ($hasAirportTaxesIncluded)
									{
										$airportTaxesCategory = new \stdClass();
										$airportTaxesCategory->Id = static::AirportTaxItmIndx;
										$airportTaxesCategory->Code = static::AirportTaxItmIndx;
										
										$airportTaxesMerch = new \stdClass();
										$airportTaxesMerch->Title = "Taxe aeroport";
										$airportTaxesMerch->Code = uniqid();
										$airportTaxesMerch->Category = $airportTaxesCategory;
										$airportTaxesItem = new \stdClass();
										$airportTaxesItem->Merch = $airportTaxesMerch;
										$airportTaxesItem->Currency = $currency;
										$airportTaxesItem->Quantity = 1;
										$airportTaxesItem->UnitPrice = 0;
										$airportTaxesItem->Availability = "yes";
										$airportTaxesItem->Gross = 0;
										$airportTaxesItem->Net = 0;
										$airportTaxesItem->InitialPrice = 0;
										
										// for identify purpose
										$airportTaxesItem->Id = $airportTaxesItem->Id;
										
										$offer->Items[] = $airportTaxesItem;
									}

									if (!$hotel->Offers)
										$hotel->Offers = [];
									// save offer on hotel
									$hotel->Offers[] = $offer;
								}
							}

							// index hotels
							$hotels[$hotel->Id] = $hotel;
						}
					}
				}
			}
			else
			{

				foreach ($offersPackagesXML->SearchPackages->PackagesList->Package as $packageData)
				{
					if (!$packageData->Hotel || !$packageData->Hotel->ID || !$packageData->Transportation || !$packageData->Transportation->Transport
						|| !$packageData->Response)
						continue;

					$transportationData = $packageData->Transportation->Transport;
					$departureTransportData = $transportationData->Departure;
					$returnTransportData = $transportationData->Return;

					$dep_departureDate = date('Y-m-d', strtotime((string)$departureTransportData->DepartureDate));
					$dep_arrivalDate = date('Y-m-d', strtotime((string)$departureTransportData->ArrivalDate));

					$ret_departureDate = date('Y-m-d', strtotime((string)$returnTransportData->DepartureDate));
					$ret_arrivalDate = date('Y-m-d', strtotime((string)$returnTransportData->ArrivalDate));

					$departureRouteName = (string)$departureTransportData->RouteName;
					$returnRouteName = (string)$returnTransportData->RouteName;

					$dep_departureAirportCode = reset(explode('-', $departureRouteName));
					$dep_arrivalAirportCode = end(explode('-', $departureRouteName));

					$ret_departureAirportCode = reset(explode('-', $returnRouteName));
					$ret_arrivalAirportCode = end(explode('-', $returnRouteName));

					// departure transport item
					$departureTransportMerch = new \stdClass();
					// $departureTransportMerch->Title = "Dus: ".($toUseCheckIn ? date("d.m.Y", strtotime($toUseCheckIn)) : "");
					$departureTransportMerch->Title = 'Dus: ' . $dep_departureAirportCode . ' ' . $dep_arrivalAirportCode . ' ' . (string)(string)$departureTransportData->DepartureDate . ' ' . (string)$departureTransportData->DepartureTime . ' - ' . (string)$departureTransportData->ArrivalDate . ' ' . (string)$departureTransportData->ArrivalTime;
					$departureTransportMerch->DepartureTime = $dep_departureDate . ' ' . (string)$departureTransportData->DepartureTime;
					$departureTransportMerch->ArrivalTime = $dep_arrivalDate . ' ' . (string)$departureTransportData->ArrivalTime;
					$departureTransportMerch->TransportType = $transportType;
					$departureTransportMerch->Category = new \stdClass();
					$departureTransportMerch->Category->Code = 'other-outbound';

					$departureTransportMerch->From = new \stdClass();
					$departureTransportMerch->From->City = new \stdClass();
					$departureTransportMerch->From->City->Id = $filter['departureCity'];

					// departure transport merch
					$departureTransportMerch->DepartureAirport = $dep_departureAirportCode;
					$departureTransportMerch->ReturnAirport = $dep_arrivalAirportCode;

					$currency = new \stdClass();
					$currency->Code = $params['Currency'];

					// departure transport itm
					$departureTransportItm = new \stdClass();
					$departureTransportItm->Merch = $departureTransportMerch;
					$departureTransportItm->Quantity = 1;
					$departureTransportItm->Currency = $currency;
					$departureTransportItm->UnitPrice = 0;
					$departureTransportItm->Gross = 0;
					$departureTransportItm->Net = 0;
					$departureTransportItm->InitialPrice = 0;
					$departureTransportItm->DepartureDate = $dep_departureDate;
					$departureTransportItm->ArrivalDate = $dep_arrivalDate;

					// for identify purpose
					$departureTransportItm->Id = $departureTransportMerch->Id;

					// return transport item
					$returnTransportMerch = new \stdClass();
					$returnTransportMerch->Title = 'Retur: ' . $ret_departureAirportCode . ' ' . $ret_arrivalAirportCode . ' ' 
						. (string)$returnTransportData->DepartureDate . ' ' . (string)$returnTransportData->DepartureTime . ' - ' 
						. (string)$returnTransportData->ArrivalDate . ' ' . (string)$returnTransportData->ArrivalTime;
					$returnTransportMerch->DepartureTime = $ret_departureDate . ' ' . (string)$returnTransportData->DepartureTime;
					$returnTransportMerch->ArrivalTime = $ret_arrivalDate . ' ' . (string)$returnTransportData->ArrivalTime;
					$returnTransportMerch->TransportType = $transportType;
					$returnTransportMerch->Category = new \stdClass();
					$returnTransportMerch->Category->Code = 'other-inbound';
					$returnTransportMerch->DepartureAirport = $ret_departureAirportCode;
					$returnTransportMerch->ReturnAirport = $ret_arrivalAirportCode;

					$returnTransportItm = new \stdClass();
					$returnTransportItm->Merch = $returnTransportMerch;
					$returnTransportItm->Quantity = 1;
					$returnTransportItm->Currency = $currency;
					$returnTransportItm->UnitPrice = 0;
					$returnTransportItm->Gross = 0;
					$returnTransportItm->Net = 0;
					$returnTransportItm->InitialPrice = 0;
					$returnTransportItm->DepartureDate = $ret_departureDate;
					$returnTransportItm->ArrivalDate = $ret_arrivalDate;

					// for identify purpose
					$returnTransportItm->Id = $returnTransportMerch->Id;
					$departureTransportItm->Return = $returnTransportItm;

					// init hotel object
					$hotel = $indexedHotels[$packageData->Hotel->ID] ?: ($indexedHotels[$packageData->Hotel->ID] = new \stdClass());

					// set hotel id as the code from amara
					$hotel->Id = (string)$packageData->Hotel->ID;

					foreach ($packageData->Response as $offerResponse)
					{
						if (!$offerResponse->Rooms || (!$offerResponse->Rooms->Room))
							continue;

						$roomData = $offerResponse->Rooms->Room;

						$offerCode = $hotel->Id . '~' . (string)$roomData->ID . '~' . (string)$roomData->BoardId . '~' . $checkIn . '~' . $checkOut;

						// init offer
						$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
						$offer->Code = $offerCode;

						$offer->Package_Id = (string)$packageData->ID;

						// set offer currency
						$offer->Currency = new \stdClass();
						$offer->Currency->Code = (string)$offerResponse->Currency;

						// net price
						$offer->Net = $offerResponse->TotalNet ? (float)$offerResponse->TotalNet : (float)$offerResponse->Total;

						// offer total price
						$offer->Gross = (float)$offerResponse->Total;

						$totalDiscount = (float)$offerResponse->TotalDiscount;
						$agencyDiscount = (float)$offerResponse->AgencyDiscount;

						// get initial price
						$offer->InitialPrice = $totalDiscount ? ($offer->Gross + $totalDiscount) : $offer->Gross;
						// $offer->InitialPrice = $agencyDiscount ? ($offer->InitialPrice - $agencyDiscount) : $offer->InitialPrice;

						// number of days needed for booking process
						$offer->Days = (string)$offerResponse->Nights;				

						// room
						$roomType = new \stdClass();
						$roomType->Id = (string)$roomData->ID;
						$roomType->Title = (string)$roomData->Name;

						$roomMerch = new \stdClass();
						$roomMerch->Title = (string)$roomData->Name;
						$roomMerch->Type = $roomType;
						$roomMerch->Code = (string)$roomData->ID;
						$roomMerch->Name = (string)$roomData->Name;

						$roomItm = new \stdClass();
						$roomItm->Merch = $roomMerch;
						$roomItm->Id = (string)$roomData->ID;

						//required for indexing
						$roomItm->Code = (string)$roomData->ID;
						#$roomItm->CheckinAfter = $checkIn;
						#$roomItm->CheckinBefore = $checkOut;

						$roomItm->CheckinAfter = $departureTransportItm->ArrivalDate;
						$roomItm->CheckinBefore = $returnTransportItm->DepartureDate;

						$roomItm->Currency = $offer->Currency;
						$roomItm->Quantity = 1;

						// set ne price on room
						$roomItm->Net = (string)$roomData->TotalRoomPrice;

						// Q: set initial price :: priceOld
						$roomItm->InitialPrice = (string)$roomData->TotalRoomPrice;

						$roomDisponibility = (string)$roomData->Disponibility;
						$offer->Availability = ($roomDisponibility == 'Available') ? 'yes' : 'ask';

						$roomItm->Availability = $offer->Availability;

						if (!$offer->Rooms)
							$offer->Rooms = [];

						$offer->Rooms[] = $roomItm;

						// board
						$boardType = new \stdClass();
						$boardType->Id = (string)$roomData->BoardId;

						$boardName = (string)$roomData->BoardName;
						$boardNiceName = (string)$roomData->BoardNiceName;
						$boardType->Title = $boardNiceName ?: $boardName;

						$boardMerch = new \stdClass();
						// $boardMerch->Id = (string)$roomData->BoardId;
						$boardMerch->Title = $boardNiceName ?: $boardName;
						$boardMerch->Type = $boardType;

						$boardItm = new \stdClass();
						$boardItm->Merch = $boardMerch;
						$boardItm->Currency = $offer->Currency;
						$boardItm->Quantity = 1;
						$boardItm->UnitPrice = 0;
						$boardItm->Gross = 0;
						$boardItm->Net = 0;
						$boardItm->InitialPrice = 0;

						// for identify purpose
						$boardItm->Id = $boardMerch->Id;

						// add items to offer
						$offer->Item = $roomItm;
						$offer->MealItem = $boardItm;

						$offer->DepartureTransportItem = $departureTransportItm;
						$offer->ReturnTransportItem = $returnTransportItm;

						if (!$hotel->Offers)
							$hotel->Offers = [];

						// save offer on hotel
						$hotel->Offers[] = $offer;
					}

					// index hotels
					$hotels[$hotel->Id] = $hotel;
				}
			}
		}
		catch (\Exception $ex)
		{
			$chartersEx = $ex;
			qvardump('$ex->getMessage()', $ex->getMessage(), $ex->getFile(), $ex->getLine());
			echo "<div style='color: red;'>" . $ex->getMessage() . "</div>";
			q_die('--');
		}

		// return hotels
		return [$hotels, $chartersEx, $rawReqs];
	}

	/**
	 * Get charter offers based on a filter
	 * @param array $filter
	 * @throws \Exception
	 */
	public function getTourOffers(array $filter = null)
	{
		$toursEx = null;
		$rawReqs = [];

		try
		{

			if (!$filter['cityId'])
				throw new \Exception('Missing city id!');

			// get checkin
			$checkIn = $filter["checkIn"];

			// calculate checkout date
			$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

			// get only first room
			$room = reset($filter['rooms']);

			$adults = $room['adults'];
			$children = $room['children'] ?: 0;
			$roomChildrenAges = $room['childrenAges'];

			$transportType = reset($filter['transportTypes']);

			$params = [
				'Currency' => 'EUR',
				'ResortID' => $filter['cityId'],
				'CheckIn' => $checkIn,
				'CheckOut' => $checkOut,
				'DepartureCityId' => $filter['departureCityId'],
				'Rooms' => [
					'Room' => [
						'Adults' => $adults,
						'Children' => $children,
					]
				]
			];

			//if ($filter['travelItemId'])
				// $params['HotelID'] = reset(explode('|', $filter['travelItemId']));

			if (($children > 0) && (count($roomChildrenAges) == $children))
			{
				$params['Rooms']['Room']['ChildrenAges'] = [];
				foreach ($roomChildrenAges as $roomChildAge)
					$params['Rooms']['Room']['ChildrenAges'][] = ['ChildAge' => $roomChildAge];
			}

			// get offers
			list($offersPackagesResp, $toSaveRequest) = $this->request('SearchPackages', $params, $filter, false, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));		

			if ($toSaveRequest && $filter['_in_resync_request'])
				$rawReqs[] = $toSaveRequest;

			// get xml
			$offersPackagesXML = simplexml_load_string($offersPackagesResp);
			if (!$offersPackagesXML->SearchPackages || !$offersPackagesXML->SearchPackages->PackagesList || !$offersPackagesXML->SearchPackages->PackagesList->Package)
				return [false];

			$tours = [];
			$indexedTours = [];
			foreach ($offersPackagesXML->SearchPackages->PackagesList->Package as $packageData)
			{
				if (!$packageData->Hotel || !$packageData->Hotel->ID || !$packageData->Transportation || !$packageData->Transportation->Transport
					|| !$packageData->Response)
					continue;

				$transportationData = $packageData->Transportation->Transport;
				$departureTransportData = $transportationData->Departure;
				$returnTransportData = $transportationData->Return;

				$dep_departureDate = date('Y-m-d', strtotime((string)$departureTransportData->DepartureDate));
				$dep_arrivalDate = date('Y-m-d', strtotime((string)$departureTransportData->ArrivalDate));

				$ret_departureDate = date('Y-m-d', strtotime((string)$returnTransportData->DepartureDate));
				$ret_arrivalDate = date('Y-m-d', strtotime((string)$returnTransportData->ArrivalDate));

				$departureRouteName = (string)$departureTransportData->RouteName;
				$returnRouteName = (string)$returnTransportData->RouteName;

				$dep_departureAirportCode = reset(explode('-', $departureRouteName));
				$dep_arrivalAirportCode = end(explode('-', $departureRouteName));

				$ret_departureAirportCode = reset(explode('-', $returnRouteName));
				$ret_arrivalAirportCode = end(explode('-', $returnRouteName));

				// departure transport item
				$departureTransportMerch = new \stdClass();
				// $departureTransportMerch->Title = "Dus: ".($toUseCheckIn ? date("d.m.Y", strtotime($toUseCheckIn)) : "");
				$departureTransportMerch->Title = 'Dus: ' . $dep_departureAirportCode . ' ' . $dep_arrivalAirportCode . ' ' . (string)(string)$departureTransportData->DepartureDate . ' ' . (string)$departureTransportData->DepartureTime . ' - ' . (string)$departureTransportData->ArrivalDate . ' ' . (string)$departureTransportData->ArrivalTime;
				$departureTransportMerch->DepartureTime = $dep_departureDate . ' ' . (string)$departureTransportData->DepartureTime;
				$departureTransportMerch->ArrivalTime = $dep_arrivalDate . ' ' . (string)$departureTransportData->ArrivalTime;
				$departureTransportMerch->TransportType = $transportType;
				$departureTransportMerch->Category = new \stdClass();
				$departureTransportMerch->Category->Code = 'other-outbound';

				$departureTransportMerch->From = new \stdClass();
				$departureTransportMerch->From->City = new \stdClass();
				$departureTransportMerch->From->City->Id = $filter['departureCity'];

				// departure transport merch
				$departureTransportMerch->DepartureAirport = $dep_departureAirportCode;
				$departureTransportMerch->ReturnAirport = $dep_arrivalAirportCode;

				$currency = new \stdClass();
				$currency->Code = $params['Currency'];

				// departure transport itm
				$departureTransportItm = new \stdClass();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Quantity = 1;
				$departureTransportItm->Currency = $currency;
				$departureTransportItm->UnitPrice = 0;
				$departureTransportItm->Gross = 0;
				$departureTransportItm->Net = 0;
				$departureTransportItm->InitialPrice = 0;
				$departureTransportItm->DepartureDate = $dep_departureDate;
				$departureTransportItm->ArrivalDate = $dep_arrivalDate;

				// for identify purpose
				$departureTransportItm->Id = $departureTransportMerch->Id;

				// return transport item
				$returnTransportMerch = new \stdClass();
				$returnTransportMerch->Title = 'Retur: ' . $ret_departureAirportCode . ' ' . $ret_arrivalAirportCode . ' ' 
					. (string)$returnTransportData->DepartureDate . ' ' . (string)$returnTransportData->DepartureTime . ' - ' 
					. (string)$returnTransportData->ArrivalDate . ' ' . (string)$returnTransportData->ArrivalTime;
				$returnTransportMerch->DepartureTime = $ret_departureDate . ' ' . (string)$returnTransportData->DepartureTime;
				$returnTransportMerch->ArrivalTime = $ret_arrivalDate . ' ' . (string)$returnTransportData->ArrivalTime;
				$returnTransportMerch->TransportType = $transportType;
				$returnTransportMerch->Category = new \stdClass();
				$returnTransportMerch->Category->Code = 'other-inbound';
				$returnTransportMerch->DepartureAirport = $ret_departureAirportCode;
				$returnTransportMerch->ReturnAirport = $ret_arrivalAirportCode;

				$returnTransportItm = new \stdClass();
				$returnTransportItm->Merch = $returnTransportMerch;
				$returnTransportItm->Quantity = 1;
				$returnTransportItm->Currency = $currency;
				$returnTransportItm->UnitPrice = 0;
				$returnTransportItm->Gross = 0;
				$returnTransportItm->Net = 0;
				$returnTransportItm->InitialPrice = 0;
				$returnTransportItm->DepartureDate = $ret_departureDate;
				$returnTransportItm->ArrivalDate = $ret_arrivalDate;

				// for identify purpose
				$returnTransportItm->Id = $returnTransportMerch->Id;
				$departureTransportItm->Return = $returnTransportItm;

				$tourId = (string)$packageData->ID . '|' . $filter['days'];

				// init tour4 object
				$tour = $indexedHotels[$tourId] ?: ($indexedHotels[$tourId] = new \stdClass());

				// set hotel id as the code from amara
				$tour->Id = $tourId;

				$tour->Title = (string)$packageData->Name;

				foreach ($packageData->Response as $offerResponse)
				{
					if (!$offerResponse->Rooms || !$offerResponse->Rooms->Room)
						continue;

					$roomData = $offerResponse->Rooms->Room;
					$partaj = (string)$roomData->Partaj;
					$totalPrice = (float)$offerResponse->Total;

					$offerCode = $tour->Id . '~' . (string)$roomData->ID . '~' . (string)$roomData->BoardId . '~' . $checkIn . '~' . $checkOut . '~' . $totalPrice;

					// init offer
					$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
					$offer->Code = $offerCode;

					$offer->Package_Id = (string)$packageData->ID;
					$offer->Partaj = $partaj ?: 0;
					$offer->Tour_Hotel_Id = ($packageData->Hotel && $packageData->Hotel->ID) ? (string)$packageData->Hotel->ID : null;

					// set offer currency
					$offer->Currency = new \stdClass();
					$offer->Currency->Code = (string)$offerResponse->Currency;

					// net price
					$offer->Net = $offerResponse->TotalNet ? (float)$offerResponse->TotalNet : (float)$offerResponse->Total;

					// offer total price
					$offer->Gross = (float)$offerResponse->Total;

					$totalDiscount = (float)$offerResponse->TotalDiscount;
					// $agencyDiscount = (float)$offerResponse->AgencyDiscount;

					// get initial price
					$offer->InitialPrice = $totalDiscount ? ($offer->Gross + $totalDiscount) : $offer->Gross;
					// $offer->InitialPrice = $agencyDiscount ? ($offer->InitialPrice - $agencyDiscount) : $offer->InitialPrice;

					// number of days needed for booking process
					$offer->Days = (string)$offerResponse->Nights;				

					// room
					$roomType = new \stdClass();
					$roomType->Id = (string)$roomData->ID . '|' . $partaj;
					$roomType->Title = (string)$roomData->Name;

					$roomMerch = new \stdClass();
					$roomMerch->Title = (string)$roomData->Name;
					$roomMerch->Type = $roomType;
					$roomMerch->Code = (string)$roomData->ID;
					$roomMerch->Name = (string)$roomData->Name;

					$roomItm = new \stdClass();
					$roomItm->Merch = $roomMerch;
					$roomItm->Id = (string)$roomData->ID;

					if ($partaj == 2)
						$roomItm->InfoTitle = 'Partaj garantat';
					else if ($partaj == 1)
						$roomItm->InfoTitle = 'Partaj negarantat';

					//required for indexing
					$roomItm->Code = (string)$roomData->ID;
					#$roomItm->CheckinAfter = $checkIn;
					#$roomItm->CheckinBefore = $checkOut;
					
					$roomItm->CheckinAfter = $departureTransportItm->ArrivalDate;
					$roomItm->CheckinBefore = $returnTransportItm->DepartureDate;

					$roomItm->Currency = $offer->Currency;
					$roomItm->Quantity = 1;

					// set ne price on room
					$roomItm->Net = (string)$roomData->TotalRoomPrice;

					// Q: set initial price :: priceOld
					$roomItm->InitialPrice = (string)$roomData->TotalRoomPrice;

					$roomDisponibility = (string)$roomData->Disponibility;
					$offer->Availability = ($roomDisponibility == 'Available') ? 'yes' : 'ask';

					$roomItm->Availability = $offer->Availability;

					if (!$offer->Rooms)
						$offer->Rooms = [];

					$offer->Rooms[] = $roomItm;

					// board
					$boardType = new \stdClass();
					$boardType->Id = (string)$roomData->BoardId;

					$boardName = (string)$roomData->BoardName;
					$boardNiceName = (string)$roomData->BoardNiceName;
					$boardType->Title = $boardNiceName ?: $boardName;

					$boardMerch = new \stdClass();
					// $boardMerch->Id = (string)$roomData->BoardId;
					$boardMerch->Title = $boardNiceName ?: $boardName;
					$boardMerch->Type = $boardType;

					$boardItm = new \stdClass();
					$boardItm->Merch = $boardMerch;
					$boardItm->Currency = $offer->Currency;
					$boardItm->Quantity = 1;
					$boardItm->UnitPrice = 0;
					$boardItm->Gross = 0;
					$boardItm->Net = 0;
					$boardItm->InitialPrice = 0;

					// for identify purpose
					$boardItm->Id = $boardMerch->Id;

					// add items to offer
					$offer->Item = $roomItm;
					$offer->MealItem = $boardItm;

					$offer->DepartureTransportItem = $departureTransportItm;
					$offer->ReturnTransportItem = $returnTransportItm;

					if (!$tour->Offers)
						$tour->Offers = [];

					// save offer on hotel
					$tour->Offers[] = $offer;
				}

				// index tours
				$tours[$tour->Id] = $tour;
			}
		} catch (\Exception $ex) {
			$toursEx = $ex;
		}

		// return tours
		return [$tours, $toursEx, $rawReqs];
	}

	/**
	 * Get individual offers based on a filter from the ajuniper api
	 * 
	 * @param array $filter
	 * @throws \Exception
	 */
	public function getIndividualOffers(array $filter = null)
	{
		if (!$filter['cityId'])
			throw new \Exception('Missing city id!');

		// get checkin
		$checkIn = $filter["checkIn"];

		// calculate checkout date
		$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

		// get only first room
		$room = reset($filter['rooms']);

		$adults = $room['adults'];
		$children = $room['children'] ?: 0;
		$roomChildrenAges = $room['childrenAges'];

		$params = [
			'Currency' => 'EUR',
			'ResortID' => $filter['cityId'],
			'CheckIn' => $checkIn,
			'CheckOut' => $checkOut,
			'Rooms' => [
				'Room' => [
					'Adults' => $adults,
					'Children' => $children
				]
			]
		];

		if ($filter['travelItemId'])
			$params['HotelID'] = $filter['travelItemId'];

		if (($children > 0) && (count($roomChildrenAges) == $children))
		{
			$params['Rooms']['Room']['ChildrenAges'] = [];
			foreach ($roomChildrenAges as $roomChildAge)
				$params['Rooms']['Room']['ChildrenAges'][] = ['ChildAge' => $roomChildAge];
		}

		list($offersResp) = $this->request('SearchAvailableHotels', $params, $filter, false, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

		if (!$offersResp)
			return [false];

		// get xml
		$offersXML = simplexml_load_string($offersResp);
		
		if (!$offersXML->SearchAvailableHotels || !$offersXML->SearchAvailableHotels->HotelsList || !$offersXML->SearchAvailableHotels->HotelsList->Hotel)
			return [false];
		
		$hotels = [];
		$indexedHotels = [];
		foreach ($offersXML->SearchAvailableHotels->HotelsList->Hotel as $offerData)
		{
			if (!$offerData->Response || !$offerData->Response->ID)
				continue;

			$currency = new \stdClass();
			$currency->Code = $params['Currency'];

			// departure transport item
			$departureTransportMerch = new \stdClass();
			$departureTransportMerch->Title = "CheckIn: ".($filter['checkIn'] ? date("d.m.Y", strtotime($filter['checkIn'])) : "");

			$departureTransportItm = new \stdClass();
			$departureTransportItm->Merch = $departureTransportMerch;
			$departureTransportItm->Quantity = 1;
			$departureTransportItm->Currency = $currency;
			$departureTransportItm->UnitPrice = 0;
			$departureTransportItm->Gross = 0;
			$departureTransportItm->Net = 0;
			$departureTransportItm->InitialPrice = 0;
			$departureTransportItm->DepartureDate = $checkIn;
			$departureTransportItm->ArrivalDate = $checkIn;
			
			// for identify purpose
			$departureTransportItm->Id = $departureTransportMerch->Id;

			// return transport item
			$returnTransportMerch = new \stdClass();
			$returnTransportMerch->Title = "CheckOut: ".($checkOut ? date("d.m.Y", strtotime($checkOut)) : "");

			$returnTransportItm = new \stdClass();
			$returnTransportItm->Merch = $returnTransportMerch;
			$returnTransportItm->Quantity = 1;
			$returnTransportItm->Currency = $currency;
			$returnTransportItm->UnitPrice = 0;
			$returnTransportItm->Gross = 0;
			$returnTransportItm->Net = 0;
			$returnTransportItm->InitialPrice = 0;
			$returnTransportItm->DepartureDate = $checkOut;
			$returnTransportItm->ArrivalDate = $checkOut;

			// for identify purpose
			$returnTransportItm->Id = $returnTransportMerch->Id;
			$departureTransportItm->Return = $returnTransportItm;
			
			// init hotel object
			$hotel = $indexedHotels[(string)$offerData->Response->ID] ?: ($indexedHotels[(string)$offerData->Response->ID] = new \stdClass());

			// set hotel id as the code from amara
			$hotel->Id = (string)$offerData->Response->ID;
			
			foreach ($offerData->Response as $offerResponse)
			{
				if (!$offerResponse->Rooms || !$offerResponse->Rooms->Room)
					continue;
				
				$roomData = $offerResponse->Rooms->Room;
				$boardData = $offerResponse->BoardType;
				
				$offerCode = $hotel->Id . '~' . (string)$roomData->ID . '~' . (string)$boardData->ID . '~' . $checkIn . '~' . $checkOut;
			
				// init offer
				$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
				$offer->Code = $offerCode;
				
				// set offer currency
				$offer->Currency = new \stdClass();
				$offer->Currency->Code = (string)$offerResponse->Currency;
				
				// net price
				$offer->Net = (float)$offerResponse->TotalPrice;

				// offer total price
				$offer->Gross = (float)$offerResponse->TotalPrice;
				
				$totalDiscount = (float)$offerResponse->Discounts;
				// $agencyDiscount = (float)$offerResponse->AgencyDiscount;
				
				// get initial price
				$offer->InitialPrice = $totalDiscount ? ($offer->Gross + $totalDiscount) : $offer->Gross;
				// $offer->InitialPrice = $agencyDiscount ? ($offer->InitialPrice - $agencyDiscount) : $offer->InitialPrice;

				// number of days needed for booking process
				$offer->Days = (string)$offerResponse->Nights;				
				
				// room
				$roomType = new \stdClass();
				$roomType->Id = (string)$roomData->ID;
				$roomType->Title = (string)$roomData->NiceName;

				$roomMerch = new \stdClass();
				$roomMerch->Title = (string)$roomData->Name;
				$roomMerch->Type = $roomType;
				$roomMerch->Code = (string)$roomData->ID;
				$roomMerch->Name = (string)$roomData->NiceName;

				$roomItm = new \stdClass();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = (string)$roomData->ID;

				//required for indexing
				$roomItm->Code = (string)$roomData->ID;
				$roomItm->CheckinAfter = $checkIn;
				$roomItm->CheckinBefore = $checkOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Quantity = 1;
				
				// set ne price on room
				$roomItm->Net = (string)$roomData->TotalRoomPrice;

				// Q: set initial price :: priceOld
				$roomItm->InitialPrice = (string)$roomData->TotalRoomPrice;

				$roomDisponibility = (string)$roomData->Availability;
				$offer->Availability = ($roomDisponibility == 'Disponibil') ? 'yes' : 'ask';

				$roomItm->Availability = $offer->Availability;

				if (!$offer->Rooms)
					$offer->Rooms = [];

				$offer->Rooms[] = $roomItm;

				// board
				$boardType = new \stdClass();
				$boardType->Id = (string)$boardData->ID;
				$boardType->Title = (string)$boardData->NiceName;

				$boardMerch = new \stdClass();
				// $boardMerch->Id = (string)$boardData->ID;
				$boardMerch->Title = (string)$boardData->NiceName;
				$boardMerch->Type = $boardType;

				$boardItm = new \stdClass();
				$boardItm->Merch = $boardMerch;
				$boardItm->Currency = $offer->Currency;
				$boardItm->Quantity = 1;
				$boardItm->UnitPrice = 0;
				$boardItm->Gross = 0;
				$boardItm->Net = 0;
				$boardItm->InitialPrice = 0;

				// for identify purpose
				$boardItm->Id = $boardMerch->Id;

				// add items to offer
				$offer->Item = $roomItm;
				$offer->MealItem = $boardItm;

				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;
				
				if (!$hotel->Offers)
					$hotel->Offers = [];

				// save offer on hotel
				$hotel->Offers[] = $offer;
			}
			
			// index hotels
			$hotels[$hotel->Id] = $hotel;
		}
		
		// return hotels
		return $hotels;
	}
	
	/**
	 * Check filters for get offers function.
	 * 
	 * @param type $filter
	 * @return type
	 * @throws \Exception
	 */
	public function checkFilters($filter)
	{
		$serviceType = ($filter && $filter['serviceTypes']) ? reset($filter['serviceTypes']) : null;

		if (!$serviceType)
			throw new \Exception("Service type is mandatory!");
		
		// check in is mandatory
		if (!$filter["checkIn"])
			throw new \Exception("CheckIn date is mandatory!");
		
		// check in is mandatory
		if (!($destination = ($filter["cityId"] ?: $filter["countryId"])))
			throw new \Exception("City is mandatory!");

		// number of days / nights are mandatory
		if (empty($filter["days"]))
			throw new \Exception("Duration is mandatory!");

		// rooms are mandatory
		if (!$filter["rooms"])
			throw new \Exception("Rooms are mandatory");

		// adults are mandatory
		if (isset($filter["rooms"]["adults"]))
			$filter["rooms"] = [$filter["rooms"]];
		
		// number of adults are mandatory
		foreach ($filter["rooms"] ?: [] as $room)
		{
			if (!isset($room["adults"]))
				throw new \Exception("Adults count is mandatory!");
		}
	
		// return service type
		return $serviceType;
	}
	
	/**
	 * Mapped countries
	 * 
	 * @return type
	 * @throws \Exception
	 */
	protected function getCountriesMapping()
	{
		$mapping = [
			"Anguilla" => "AI",
			"Albania" => "AL",
			"Andora" => "AD",
			"Argentina" => "AR",
			"Armenia" => "AM",
			"Armenia" => "AM",
			"Aruba" => "AW",
			"Australia" => "AU",
			"Austria" => "AT",
			"Azerbaidjan" => "AZ",
			"Bahamas" => "BS",
			"Bahrain" => "BH",
			"Barbados" => "BB",
			"Belarus" => "BY",
			"Belgia" => "BE",
			"Bolivia" => "BO",
			"Botswana" => "BW",
			"Belize" => "BZ",
			"Brazilia" => "BR",
			"Virginele Britanice" => "VG",
			"Bulgaria" => "BG",
			"Cambodgia" => "KH",
			"Camerun" => "CM",
			"Canada" => "CA",
			"Cayman" => "KY",
			"Chile" => "CL",
			"China" => "CN",
			"Columbia" => "CO",
			"Congo (Republica)" => "CG",
			"Rep Democrata Congo" => "CD",
			"Insulele Cook" => "CK",
			"Costa Rica" => "CR",
			"Cuba" => "CU",
			"Cipru" => "CY",
			"Cipru de Nord" => "NY",
			"Cehia" => "CZ",
			"Danemarca" => "DK",
			// "Rep Dominicana" => "DO",
			"Republica Dominicana" => "DO",
			"Ecuador" => "EC",
			"Egipt" => "EG",
			"Estonia" => "EE",
			"Etiopia" => "ET",
			"Fidji" => "FJ",
			"Finlanda" => "FI",
			"Franta" => "FR",
			"Gabon" => "GA",
			"Gambia" => "GM",
			"Georgia" => "GE",
			"Germania" => "DE",
			"Ghana" => "GH",
			"Grecia" => "GR",
			"Groenlanda" => "GL",
			"Guatemala" => "GT",
			"Haiti" => "HT",
			"Honduras" => "HN",
			"Hong Kong" => "HK",
			"Ungaria" => "HU",
			"Islanda" => "IS",
			"India" => "IN",
			"Indonezia" => "ID",
			"Iran" => "IR",
			"Iraq" => "IQ",
			"Israel" => "IL",
			"Italia" => "IT",
			"Jamaica" => "JM",
			"Japonia" => "JP",
			"Iordania" => "JO",
			"Kazakhstan" => "KZ",
			"Kenya" => "KE",
			"Kuwait" => "KW",
			// Kyrgyzstan
			//"Kârgâzstan" => "KG",
			"Kyrghistan" => "KG" ,
			
			"Laos" => "LA",
			"Letonia" => "LV",
			"Liban" => "LB",
			"Libia" => "LY",
			"Liechtenstein" => "LI",
			"Lituania" => "LT",
			"Luxemburg" => "LU",
			"Macedonia" => "MK",
			"Maldive" => "MV",
			"Mali" => "ML",
			"Malta" => "MT",
			"Mauritius" => "MU",
			"Mexic" => "MX",
			"Monaco" => "MC",
			"Mongolia" => "MN",
			"Muntenegru" => "ME",
			"Maroc" => "MA",
			"Mozambic" => "MZ",
			"Myanmar" => "MM",
			"Namibia" => "NA",
			"Nepal" => "NP",
			//"Netherlands" => "NL",
			"Noua Zeelanda" => "NZ",
			"Nicaragua" => "NI",
			"Nigeria" => "NG",
			"Coreea R P D" => "KP",
			"Norvegia" => "NO",
			"Oman" => "OM",
			"Pakistan" => "PK",
			"Palau" => "PW",
			"Panama" => "PA",
			"Paraguay" => "PY",
			"Peru" => "PE",
			"Filipine" => "PH",
			"Polonia" => "PL",
			"Portugalia" => "PT",
			"Puerto Rico" => "PR",
			"Qatar" => "QA",
			"Capul Verde" => "CV",
			"Reunion" => "RE",
			"Romania" => "RO",
			"Rusia" => "RU",
			"San Marino" => "SM",
			"Arabia Saudita" => "SA",
			"Senegal" => "SN",
			"Serbia" => "RS",
			"Seychelles" => "SC",
			"Slovacia" => "SK",
			"Slovenia" => "SI",
			"Africa de Sud" => "ZA",
			"Coreea Sud" => "KR",
			"Spania" => "ES",
			"Sri Lanka" => "LK",
			"Swaziland" => "SZ",
			"Suedia" => "SE",
			"Elvetia" => "CH",
			"Siria" => "SY",
			"Taiwan" => "TW",
			"Tanzania" => "TZ",
			"Thailanda" => "TH",
			"Togo" => "TG",
			"Tonga" => "TO",
			"Tunisia" => "TN",
			"Turcia" => "TR",
			"Turkmenistan" => "TM",
			"Uganda" => "UG",
			"Ucraina" => "UA",
			"Emiratele Arabe Unite" => "AE",
			"Marea Britanie" => "GB",
			"Uruguay" => "UY",
			"Vanuatu" => "VU",
			"Venezuela" => "VE",
			"Vietnam" => "VN",
			"Zambia" => "ZM",
			"Zimbabweeeee" => "ZW",
			"Afganistan" => "AF",
			"Algeria" => "DZ",
			"Angola" => "AO",
			"Bangladesh" => "BD",
			"Benin" => "BJ",
			"Bhutan" => "BT",
			"Bonaire" => "BK",
			"Burkina Faso" => "BF",
			"Burundi" => "BI",
			"Ciad" => "TD",
			"Djibouti" => "DJ",
			"Dominica" => "DM",
			"East Timor" => "TP",
			"El Salvador" => "SV",
			"Guineea Ecuatoriala" => "GQ",
			"Eritreea" => "ER",
			"Feroe" => "FO",
			"Guineea" => "GN",
			"Guineea Bissau" => "GW",
			"Coasta de Fildes" => "CI",
			"Kiribati" => "KI",
			"Lesotho" => "LS",
			"Liberia" => "LR",
			"Madagascar" => "MG",
			"Malawi" => "MW",
			"Marshall" => "MH",
			"Martinica" => "MQ",
			"Mauritania" => "MR",
			"Montserrat" => "MS",
			"Nauru" => "NR",
			"Niue" => "NU",
			"Papua" => "PG",
			"Sierra Leone" => "SL",
			"Solomon" => "SB",
			"Somalia" => "SO",
			"Sudan" => "SD",
			"Surinam" => "SR",
			"Trinidad Tobago" => "TT",
			"Tuvalu" => "TV",
			"Croatia" => "HR",
			"Polinezia Franceza" => "PF",
			"GRENADA" => "GD",
			"GUAM" => "GU",
			"Malaezia" => "MY",
			"Moldova" => "MD",
			//Antigua și Barbuda
			"Antigua" => "AG",
			"Ins Bermude" => "BM",
			"Bosnia-Herzegovina" => "BA",
			"brunei" => "BN",
			"Africa Centrala" => "CF",
			"Guiana Franceză" => "GF",
			"Irlanda" => "IE",
			"MACAO" => "MO",
			"YEMEN" => "YE",
			"Insulele Mariane de Nord" => "MP",
			"RWANDA" => "RW",
			"Saint Barthélemy" => "BL",
			"St.Kitts" => "KN",
			"St Lucia" => "LC",
			"St. Maarten" => "MF",
			"St Vincent" => "VC",
			"Samoa Vest" => "WS",
			"Sao Tome" => "ST",
			"SINGAPORE" => "SG",
			"Olanda" => "NL",
			"Antilele Olandeze" => "AN",
			"Turks si Caicos" => "TC",
			"Virgin Islands-United States" => "VI",
			"Statele Unite ale Americii" => "US",
			"UZBEKISTAN" => "UZ",

			"Tokelau" => "TK",
			"Niger" => "NE",
			"Guernsey" => "GG",
			"Vatican" => "VA",
			"Guyana" => "GY",
			"Insula Norfolk" => "NF",
			"Wallis si Futuna" => "WF",
			"Samoa Americana" => "AS",
			"Mayotte" => "YT",
			"Insula Jersey" => "JE",
			"Guadelupa" => "GP",
			"Falkland" => "FK",
			"Gibraltar" => "GI",
			"Insula Bouvet" => "BV",
			"Micronezia" => "FM",
			"Noua Caledonie" => "NC",
			"Comore" => "KM",
			"St Pierre Miq" => "PM",
			//Tajikistan
			//"Tadjikistan" => "TJ",
			"Tadjikistan" => "TJ",

			"Teritoriile Palestiniene Ocupate" => "PS",
			"Pitcairn" => "PN",
			"Svalbard și Jan Mayen" => "SJ",
			"U.S. Minor Outlying Islands" => "UM",
			"Insula Man" => "IM",
			"Insulele Åland" => "AX",
			"Insula Heard și Insulele McDonald" => "HM",
			"Timor Leste" => "TL",
			"Georgia de Sud și Insulele Sandwich de Sud" => "GS",
			"Antarctica" => "AQ",
			"Cocos Islands" => "CC",
			"Christmas Island" => "CX",
			"Teritoriul Britanic din Oceanul Indian" => "IO",
			"Western Sahara" => "EH"
			//"Sfânta Elena" => ""
		];

		$codes = [];
		$ret = [];
		foreach ($mapping ?:  [] as $k => $v)
		{
			if (isset($codes[$v]))
			{
				throw new \Exception("Duplicate code [{$v}]");
			}
			$codes[$v] = $v;
			$ret[trim(strtolower($k))] = $v;
		}
		
		return $ret;
	}
	
	/**
	 * **NOT USED**
	 * 
	 * Get country details
	 * 
	 * @param array $filter
	 * 
	 * @return boolean
	 */
	public function getCountryDetails(array $filter = null)
	{
		// exit if no country id on filter
		if (!$filter['CountryId'])
			return false;
		
		// set country id on params
		$params['CountryID'] = $filter['CountryId'];
		
		// get api response
		list($countryDetailsResp) = $this->request('GetCountryDetails', $params);
		
		// to xml
		$countryDetailsXML = simplexml_load_string($countryDetailsResp);
		
		// make sure it has the structure
		if (!$countryDetailsXML->GetCountryDetails)
			return false;
		
		// is searchable?
		$searchable = (string)$countryDetailsXML->GetCountryDetails->Searchable;
		
		// echo 'Is thi Country searchable??' . $searchable;
	}
	
	/**
	 * **NOT USED**
	 * 
	 * Get region details
	 * 
	 * @param array $filter
	 * 
	 * @return boolean
	 */
	public function getRegionDetails(array $filter = null)
	{
		if (!$filter['CountyId'])
			return false;
		
		$params['RegionID'] = $filter['CountyId'];
		
		list($regionDetailsResp) = $this->request('GetRegionDetails', $params);
		
		$countryDetailsXML = simplexml_load_string($countryDetailsResp);
	}
	
	/**
	 * **NOT USED**
	 * 
	 * Get city details
	 * 
	 * @param array $filter
	 * 
	 * @return boolean
	 */
	public function getCityDetails(array $filter = null)
	{
		if (!$filter['CityId'])
			return false;
		
		$params['ResortID'] = $filter['CityId'];
		
		list($regionDetailsResp) = $this->request('GetResortDetails', $params);
		
		$countryDetailsXML = simplexml_load_string($countryDetailsResp);
	}
	
	/**
	 * **NOT USED**
	 * 
	 * Get hotel rate plans
	 * 
	 * @param array $filter
	 * 
	 * @return boolean
	 */
	public function getHotelRatePlans(array $filter = null)
	{
		if (!$filter['HotelId'])
			return false;
		
		$params['HotelID'] = $filter['HotelId'];
		
		list($hotelRatePlansResp) = $this->request('GetHotelRatePlans', $params);
		
		$hotelRatePlansXML = simplexml_load_string($hotelRatePlansResp);
		
		
		// Hotel[]
		// Rooms[]
		
		if (!$hotelRatePlansXML->GetHotelRatePlans || !$hotelRatePlansXML->GetHotelRatePlans->Rooms || !$hotelRatePlansXML->GetHotelRatePlans->Rooms->Room)
			return false;
		
		foreach ($hotelRatePlansXML->GetHotelRatePlans->Rooms->Room as $hotelRoom)
		{
			// ID
			// Name
			
			echo (string)$hotelRoom->ID;
			echo (string)$hotelRoom->Nume;
			
			// Pensions[]
			
			if ($hotelRoom->Pensions && $hotelRoom->Pensions->Pension)
			{
				foreach ($hotelRoom->Pensions->Pension as $hotelPension)
				{
					echo '<br />';
					echo (string)$hotelPension->ID;
					echo (string)$hotelPension->Name;
				}
			}
			
			// RatePlans[]
			
			if ($hotelRoom->RatePlans && $hotelRoom->RatePlans->RatePlan)
			{
				foreach ($hotelRoom->RatePlans->RatePlan as $hotelRatePlan)
				{
					echo '<br />';
					
					qvar_dump($hotelRatePlan);
					
					echo (string)$hotelRatePlan->ID;
					echo '<br />';
					echo (string)$hotelRatePlan->Name;
					echo '<br />';
					echo (string)$hotelRatePlan->PlanType;
					echo '<br />';
					echo (string)$hotelRatePlan->DateStartReservation;
					echo '<br />';
					echo (string)$hotelRatePlan->DateEndReservation;
					echo '<br />';
					echo (string)$hotelRatePlan->ForMinimNumberOfDaysBeforeChckin;
					echo '<br />';
					echo (string)$hotelRatePlan->MinimDays;
					echo '<br />';
					echo (string)$hotelRatePlan->MaximDays;
					echo '<br />';
					echo (string)$hotelRatePlan->ForTheseDays;
					echo '<br />';
					echo (string)$hotelRatePlan->SpecialOffer;
					echo '<br />';
					echo (string)$hotelRatePlan->AllotmentType;
					echo '<br />';
					echo (string)$hotelRatePlan->Supplier;
					echo '<br />';
					
					// Pensions[]
					
					
					if ($hotelRatePlan->Prices && $hotelRatePlan->Prices->Price)
					{
						foreach ($hotelRatePlan->Prices->Price as $hotelRatePrice)
						{
							echo '<hr />';
							echo '<br />';
							
							echo (string)$hotelRatePrice->Value;
							echo '<br />';
							echo (string)$hotelRatePrice->StartDate;
							echo '<br />';
							echo (string)$hotelRatePrice->EndDate;
							echo '<br />';
							echo (string)$hotelRatePrice->PriceBreakfast;
							echo '<br />';
							echo (string)$hotelRatePrice->PriceAccountSheet;
							echo '<br />';
							echo (string)$hotelRatePrice->PriceDescription;
							
							// qvar_dump($hotelRatePrice); q_die();
						}
						
						//q_die();
					}
				}
			}
		}
	}
	
	/**
	 * Get packages type
	 * 
	 * @param array $filter
	 * 
	 * @return boolean
	 */
	public function getPackagesType(array $filter = null)
	{
		// get response

		//$method, $params = [], $filter = [], $useCache = false, $logData = false
		$t1 = microtime(true);
		list($packagesTypeResp) = $this->request('GetPackagesType', [], $filter, true);
	
		// get xml
		$packagesTypeXML = simplexml_load_string($packagesTypeResp);

		// check xml structure
		if ((!$packagesTypeXML->GetPackagesType) || (!$packagesTypeXML->GetPackagesType->PackagesType) || (!$packagesTypeXML->GetPackagesType->PackagesType->Type))
			return false;

		$packagesType = [];
		$packagesTypesTree = [];
		foreach ($packagesTypeXML->GetPackagesType->PackagesType->Type as $_packageType)
		{
			$packageType = new \stdClass();
			$packageType->Id = (string)$_packageType->ID;
			$packageType->ParentId = (string)$_packageType->ParentID;
			$packageType->Name = (string)$_packageType->Name;
			$packageType->Description = (string)$_packageType->Description;
			$packageType->Order = (string)$_packageType->Order;
			$packageType->Transport = (string)$_packageType->Transport;
			$packageType->Active = (string)$_packageType->active;
			$packageType->Public = (string)$_packageType->public;

			if (isset($packagesType[$packageType->Id]))
			{
				$pp = $packagesType[$packageType->Id];
				foreach ($packageType ?: [] as $k => $v)
				{
					$pp->{$k} = $v;
				}
			}
			else
				$packagesType[$packageType->Id] = $packageType;

			#echo $packageType->Id . " -> " . ($packageType->ParentId ?: 'NO_PARENT') . '<br/>';
			if (isset($packagesTypesTree[$packageType->Id]))
			{
				$pp = $packagesTypesTree[$packageType->Id];
				foreach ($packageType ?: [] as $k => $v)
				{
					$pp->{$k} = $v;
				}

				if ($packageType->ParentId && $pp->Children)
				{
					foreach ($pp->Children ?: [] as $child)
					{
						if (!$child->Parents)
							$child->Parents = [];
						$child->Parents[$packageType->ParentId] = $packageType->ParentId;
					}
				}
			}

			if ($packageType->ParentId)
			{				
				if (!$packageType->Parents)
					$packageType->Parents = [];
				$packageType->Parents[$packageType->ParentId] = $packageType->ParentId;

				if (isset($packagesTypesTree[$packageType->ParentId]))
				{
					$ppp = $packagesTypesTree[$packageType->ParentId];
				}
				else
				{
					$ppp = new \stdClass();
					$ppp->Id = $packageType->ParentId;
					$packagesTypesTree[$packageType->ParentId] = $ppp;
					$packagesType[$packageType->ParentId] = $ppp;
				}

				if (!$ppp->Children)
					$ppp->Children = [];

				$ppp->Children[$packageType->Id] = $packageType;
			}
			else if (!isset($packagesTypesTree[$packageType->Id]))
				$packagesTypesTree[$packageType->Id] = $packageType;
		}

		return [$packagesType, $packagesTypesTree];
	}
	
	/**
	 * **NOT USED**
	 * 
	 * Get packages list
	 * 
	 * @param array $filter
	 * 
	 * @return boolean
	 */
	public function getPackagesListForCities(array $filter = null)
	{
		// get cities
		#list($cities) = $this->api_getCities();

		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		// missing cache folder
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		$packages = [];
		$force = ($filter && $filter['force']);

		$debugFile = 'travelio_karpaten_reports/travelio_karpaten_debug_packages_pull_' . date("Y_m_d_H") . '.txt';
		#if (file_exists($debugFile))
		#	unlink($debugFile);

		// go through each city(resort)
		$countPackages = 0;

		// countries
		$countries = ($filter && $filter['countries']) ? $filter['countries'] : null;

		if ($countries === null)
			list($countries) = $this->api_getCountries(['use_cache' => true]);

		$c_countries = count($countries);

		$toPullPackages = [];
		$c_pos = 0;
		foreach ($countries ?: [] as $country)
		{
			$c_pos++;
			// get cache file
			$packages_country_xml_file = $cacheFolder . 'packages_country_' . $country->Id . '.xml';

			// cache limit
			$packages_country_file_cache_limit = (time() - $this->availabilityDatesCacheTimeLimit_packages);

			$doRequest = ($force || ((!file_exists($packages_country_xml_file)) || (filemtime($packages_country_xml_file) < $packages_country_file_cache_limit)));

			$treqsinit = microtime(true);
			if ($doRequest)
			{
				#qvar_dump('$package_details_xml_file', $packages_country_xml_file, file_exists($packages_country_xml_file), 
				#date("Y-m-d H:i:s", filemtime($packages_country_xml_file)), date("Y-m-d H:i:s", $packages_country_file_cache_limit));
				#q_die('-request on country-');

				try
				{
					// get response
					list($packagesTypeListResp) = $this->request('GetPackagesList', [
						'CountryID' => $country->Id
					]);

					file_put_contents($packages_country_xml_file, $packagesTypeListResp);
				}
				catch (\Exception $ex)
				{
					$packages_country_xml_file_werr = $cacheFolder . 'packages_country_' . $country->Id . '.with_err.xml';
					file_put_contents($packages_country_xml_file_werr, $this->_lastResponse);
					throw $ex;
				}
			}
			else
			{
				$packagesTypeListResp = file_get_contents($packages_country_xml_file);
			}

			// get xml
			$packagesTypeListXML = simplexml_load_string($packagesTypeListResp);

			$objDateTime = new \DateTime('NOW');
			$execDate = $objDateTime->format("Y-m-d H:i:s.v");

			if ((!$packagesTypeListXML->GetPackagesList) || (!$packagesTypeListXML->GetPackagesList->PackagesList) || (!$packagesTypeListXML->GetPackagesList->PackagesList->Package))
			{
				file_put_contents($debugFile, $execDate . ": [{$c_pos}/{$c_countries}]GetPackagesList for country [{$country->Code}|{$country->Name}] " 
					. ($doRequest ? "executed" : "pulled_from_cache") 
					. (file_exists($packages_country_xml_file) ? " - we have cache file [{$packages_country_xml_file}]" : "")
					. (file_exists($packages_country_xml_file) ? " - cache file last update date: " . date("Y-m-d H:i:s", filemtime($packages_country_xml_file)) : "")
					. (file_exists($packages_country_xml_file) ? " - compared to cache limit: " . date("Y-m-d H:i:s", $packages_country_file_cache_limit) : "")
					. " - in " . (microtime(true) - $treqsinit . ' seconds') . " - Country has no packages\n", FILE_APPEND);

				continue;
			}

			$countryPackages = 0;
			#fwrite($fk_h, "country " . $country->Id . " | " . $country->Name . " has " . $cpackages . " packages\n");
			foreach ($packagesTypeListXML->GetPackagesList->PackagesList->Package ?: [] as $packageListData)
			{
				$toPullPackages[$country->Id][] = $packageListData;
				$countPackages++;
				$countryPackages++;
			}
			file_put_contents($debugFile, $execDate . ": [{$c_pos}/{$c_countries}]GetPackagesList for country [{$country->Code}|{$country->Name}] " 
				. ($doRequest ? "executed" : "pulled_from_cache") . " in " . (microtime(true) - $treqsinit . ' seconds') . " - " . $countryPackages . " packages found\n", FILE_APPEND);
		}

		$c_packs_pos = 0;
		foreach ($countries ?: [] as $country)
		{
			if (($packs = $toPullPackages[$country->Id]))
			{
				foreach ($packs ?: [] as $packageListData)
				{
					$package = [];
					$package['Id'] = (string)$packageListData->ID;
					$package['CountryID'] = (string)$packageListData->CountryID;
					$package['RegionID'] = (string)$packageListData->RegionID;
					$package['ResortId'] = (string)$packageListData->ResortID;
					$package['HotelId'] = (string)$packageListData->HotelID;
					$package['Name'] = (string)$packageListData->Name;

					$c_packs_pos++;
					$packageData = $this->getPackageDetails($package['Id'], $package, $force, [
							"count_packages" => $countPackages,
							"c_packs_pos" => $c_packs_pos,
							"country" => $country
						]);

					if ($packageData)
						$packages[(string)$packageListData->ResortID][$package['Id']] = $package;
				}
			}
		}

		return $packages;
	}
	
	/**
	 * Get package details
	 * 
	 * @param type $packageId
	 * @param type $package
	 * 
	 * @return boolean
	 */
	public function getPackageDetails($packageId, &$package = null, $force = false, $xtraData = [])
	{
		if (!$packageId)
			return false;

		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		// get cache file
		$package_details_xml_file = $cacheFolder . 'package_details_' . $packageId . '.xml';

		// cache limit
		$package_details_file_cache_limit = (time() - $this->availabilityDatesCacheTimeLimit);
		$doRequest = ($force || ((!file_exists($package_details_xml_file)) || (filemtime($package_details_xml_file) < $package_details_file_cache_limit)));

		#$fk_df = 'travelio_karpaten_packages_details.txt';
		#$fk_h = fopen($fk_df, 'a+');

		#$debugFile = 'travelio_karpaten_debug_packages_pull.txt';
		$debugFile = 'travelio_karpaten_reports/travelio_karpaten_debug_packages_pull_' . date("Y_m_d_H") . '.txt';
		$treqsinit = microtime(true);

		$resp_fpc = null;
		$request_finalied = false;
		$ex = null;
		if ($doRequest)
		{
			#qvar_dump('$package_details_xml_file', $package_details_xml_file, file_exists($package_details_xml_file), 
			#	date("Y-m-d H:i:s", filemtime($package_details_xml_file)), date("Y-m-d H:i:s", $package_details_file_cache_limit));
			#q_die('request for this!');

			// get resp
			try
			{
				list($packageDetailsResp) = $this->request('GetPackageDetails', [
					'PackageID' => $packageId
				]);
				$resp_fpc = file_put_contents($package_details_xml_file, $packageDetailsResp);
				$request_finalied = true;
			}
			catch (\Exception $ex)
			{
				$package_details_xml_file_werr = $cacheFolder . 'package_details_' . $packageId . '.with_err.xml';
				$resp_fpc = file_put_contents($package_details_xml_file_werr, $this->_lastResponse);
				$packageDetailsResp = null;
			}
		}
		else
		{
			$packageDetailsResp = file_get_contents($package_details_xml_file);
		}

		// get xml
		$packageDetailsXML = simplexml_load_string($packageDetailsResp);

		$objDateTime = new \DateTime('NOW');
		$execDate = $objDateTime->format("Y-m-d H:i:s.v");

		if ($doRequest)
		{
			ob_start();
			var_dump('$resp_fpc', $resp_fpc, $request_finalied, ($ex ? $ex->getMessage() : null));
			$str_fpc = ob_get_clean();
		}

		file_put_contents($debugFile, $execDate . ": [{$xtraData["c_packs_pos"]}/{$xtraData["count_packages"]}]GetPackageDetails for id [{$packageId}] " 
			. (($country = $xtraData['country']) ? " for country [{$country->Code}|{$country->Name}] " : "") 
			. ($doRequest ? "executed - " . $str_fpc : "pulled_from_cache") 
			. (file_exists($package_details_xml_file) ? " - we have cache file [{$package_details_xml_file}]" : "")
			. (file_exists($package_details_xml_file) ? " - cache file last update date: " . date("Y-m-d H:i:s", filemtime($package_details_xml_file)) : "")
			. (file_exists($package_details_xml_file) ? " - compared to cache limit: " . date("Y-m-d H:i:s", $package_details_file_cache_limit) : "")		
			. " in " . (microtime(true) - $treqsinit . ' seconds') . "\n", FILE_APPEND);

		if ((!$packageDetailsXML->GetPackageDetails) || (!$packageDetailsXML->GetPackageDetails->ID))
		{
			#echo '<div style="color: red;">ERR: cannot pull package data for [' . $packageId . '] - no GetPackageDetails</div>';
			return false;
		}

		$packageDetails = $packageDetailsXML->GetPackageDetails;

		if ((!$packageDetails->DepartureDates) || (!$packageDetails->DepartureDates->DepartureDate))
		{
			#echo '<div style="color: red;">ERR: cannot pull package data for [' . $packageId . '] - no DepartureDates</div>';
			return false;
		}

		$package['ShortDescription'] = (string)$packageDetails->ShortDescription;
		$package['Description'] = (string)$packageDetails->Description;
		$package['PackageTypeId'] = (string)$packageDetails->PackageTypeID;
		$package['Order'] = (string)$packageDetails->Order;

		if ($packageDetails->Outward)
		{
			if ($packageDetails->Outward->ResortDepartureID)
				$package['DepartureResortId'] = (string)$packageDetails->Outward->ResortDepartureID;
			
			if ($packageDetails->Outward->ResortDestinationID)
				$package['DestinationResortId'] = (string)$packageDetails->Outward->ResortDestinationID;
			
			if ($packageDetails->Outward->DepartureHour)
				$package['DepartureHour'] = (string)$packageDetails->Outward->DepartureHour;
			
			if ($packageDetails->Outward->ArrivalHour)
				$package['ArrivalHour'] = (string)$packageDetails->Outward->ArrivalHour;
		}
		else 
		{
			$package['DepartureResortId'] = (string)$packageDetails->ResortDepartureID;
			$package['DestinationResortId'] = (string)$packageDetails->ResortDestinationID;
		}
		
		/*
		if (!$package['DepartureResortId'] || !$package['DestinationResortId'])
		{
			qvar_dump($package, $packageDetailsXML, $packageDetailsResp); q_die();
		}
		*/
		
		$package['Transportation'] = (string)$packageDetails->Transportation;
		$package['PriceMode'] = (string)$packageDetails->PriceMode;
		$package['StartingPrice'] = (string)$packageDetails->StartingPrice;
		$package['StartingPrice'] = (string)$packageDetails->StartingPrice;
		
		if ($packageDetails->DepartureDates && $packageDetails->DepartureDates->DepartureDate)
		{
			$package['DepartureDates'] = [];
			$package['DepartureDates_Use'] = [];
			
			foreach ($packageDetails->DepartureDates->DepartureDate as $departureDateData)
			{
				$departureDate = [];
				$departureDate['RoomId'] = (string)$departureDateData->RoomID;
				$departureDate['RoomName'] = (string)$departureDateData->RoomName;
				$departureDate['RoomName'] = (string)$departureDateData->RoomName;
				$departureDate['MealId'] = (string)$departureDateData->MealID;
				$departureDate['MealName'] = (string)$departureDateData->MealName;
				$departureDate['DepartureDate'] = (string)$departureDateData->DepartureDate;
				$departureDate['CheckIn'] = (string)$departureDateData->CheckIn;
				$departureDate['CheckOut'] = (string)$departureDateData->CheckOut;
				$departureDate['Nights'] = (string)$departureDateData->Nights;
				$departureDate['HotelNights'] = (string)$departureDateData->HotelNights;

				$package['DepartureDates'][] = $departureDate;

				$package['DepartureDates_Use'][(string)$departureDateData->DepartureDate][(string)$departureDateData->Nights][] = [
					"DepartureDate" => (string)$departureDateData->DepartureDate,
					'CheckIn' => (string)$departureDateData->CheckIn,
					'CheckOut' => (string)$departureDateData->CheckOut,
					'Nights' => (string)$departureDateData->Nights,
				];
			}
		}

		if ($packageDetails->Services && $packageDetails->Services->Service)
		{
			$package['Services'] = [];
			foreach ($packageDetails->Services->Service as $serviceData)
			{
				$service = [];
				$service['Name'] = (string)$serviceData->Name;
				$service['Type'] = (string)$serviceData->Type;
				$service['Cost'] = (string)$serviceData->Cost;
				$service['CostDetails'] = (string)$serviceData->CostDetails;
				
				$package['Services'][] = $service;
			}
		}
		
		// return package
		return $package;
	}
	
	/**
	 * Converts an array to an xml
	 * 
	 * @param type $data
	 * @param type $xml_data
	 */
	public function array_to_xml($data, &$xml_data) 
	{		
		foreach ($data as $key => $value) 
		{
			if (is_array($value)) 
			{
				if(is_numeric($key))
				{
					// $key = 'ChildAGew'; // reset($value); //dealing with <0/>..<n/> issues
					$this->array_to_xml($value, $xml_data);
					
					continue;
				}
            
				$subnode = $xml_data->addChild($key);
				
				$this->array_to_xml($value, $subnode);
			} 
			else
				$xml_data->addChild("$key",htmlspecialchars("$value"));
		}
	}
	
	/**
	 * Get request mode.
	 * 
	 * @return type
	 */
	public function getRequestMode()
	{
		return static::RequestModeCurl;
	}
}