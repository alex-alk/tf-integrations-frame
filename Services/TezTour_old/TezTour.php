<?php

namespace Omi\TF;

class TezTour extends \Omi\TF\TOInterface
{
	use TOInterface_Util;

	const AirportTaxItmIndx = "7s";

	const TransferItmIndx = "6";

	protected $curl;

	protected $offsLimit = 100;
	
	protected static $DatabaseName = "TEZ_TOUR";
	
	public $cachedData = [];

	public $flightsFileCacheTimeLimit = 60 * 60 * 24;
	
	public $flightsAviaCacheTimeLimit = 60 * 0;
	
	public $cacheTimeLimit = 60 * 60 * 24;

	public $hotelClassesTimeLimit = 60 * 60 * 24;
	
	public $freeSeatsStatuses = ['Есть', 'Мало']; // exists, few

	// each week refresh map data
	public $mapDataCacheLimit = 60 * 60 * 24 * 7;
	
	public $ApiSearchUrl = 'http://www.travelio.ro/proxy-tez';

	public static $FlightsCheckUrl = 'https://www.tez-tour.com/avia-reference/flights';
	
	public static $Currency = 18864;
	
	//public static $ROAirports = ['OTP', 'TSR', 'IAS', 'BBU', 'CLJ', 'SBZ', 'BCM', 'ARW', 'KIV'];
	//public static $ROAirports = ['OTP', 'TSR', 'IAS', 'BBU', 'CLJ', 'SBZ', 'BCM', 'ARW'];
	
	public static $BucharestID = '9001185';

	public static $MedicalInsurenceFile = 'Conditii-asigurare-CITY-tez.pdf';
	
	public static $RoCountryID_ForAirports = 156505;
	
	public static $CheckedFlights = [];
	
	public static $MissingFlights = [];
	
	/**
	 * get SOAP Cliient method and filter
	 * 
	 * @param type $method
	 * @param type $filter
	 * @return boolean
	 */
	public function getSoapClientByMethodAndFilter($method, $filter = null)
	{
		return false;
	}
	
	/**
	 * Test connection
	 * 
	 * @param array $filter
	 */
	public function api_testConnection(array $filter = null)
	{
		$roomFilter = [
			"adults" => 2,
			"children" => 0,
			"childrenAges" => []
		];

		$accommodationId = $this->getAccommodationId($roomFilter);
		if (!$accommodationId)
			return false;

		$cacheFolder = $this->getResourcesDir();
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		list($city) = $this->api_getCities(["onlyFirst" => true, "for_connection_testing" => true]);
		
		if (!$city || !$city->County || !$city->Country)
			throw new \Exception('Missing first city data!');

		$filter = [
			"departureCity" => static::$BucharestID,
			"countryId" => $city->Country->Id,
			"checkIn" => date("Y-m-d", strtotime("+ 10 days")),
			"regionId" => $city->County->Id,
			"days" => 7
		];

		// get min accepted hotel category
		$minAcceptedHotelCategory = $this->getMinAcceptableCategory($filter);

		if (!$minAcceptedHotelCategory)
			throw new \Exception('Missing hotel category!');

		$params = [
			'xml'				=> 'true',
			'locale'			=> 'ro',
			'tariffsearch'		=> 1,
			'currency'			=> static::$Currency,
			'cityId'			=> (int)$filter['departureCity'],		// id of the departure city
			'countryId'			=> (int)$filter['countryId'],			// id of destination country
			'after'				=> date('d.m.Y', strtotime($filter['checkIn'])),
			'before'			=> date('d.m.Y', strtotime($filter['checkIn'])),
			'nightsMin'			=> (int)$filter['days'],
			'nightsMax'			=> (int)$filter['days'],
			'priceMin'			=> 0,
			'priceMax'			=> 999999,
			//'priceMax'			=> 13300,
			'tourType'			=> 3, // for individual
			'accommodationId'	=> $accommodationId,
			'hotelClassId'		=> (int)$minAcceptedHotelCategory['Id'],	// min accepted hotel category
			'hotelClassBetter'	=> true,							// min accepted hotel category and better
			'hotelInStop'		=> true,							// also hotels with unavailable rooms
			'tourId'			=> (int)$filter['regionId']				// id of the region
		];
		
		$minRAndBs = $this->getMinRAndBs($filter);
		if ($minRAndBs && $minRAndBs["Id"])
			$params["rAndBId"] = $minRAndBs["Id"];

		$this->requestOffers($params);

		$params = null;
		$flight = null;
		$doLogging = false;
		$forTestConn = true;
		$this->getAviaReferenceFlights($params, $flight, $doLogging, $forTestConn);

		return true;
	}

	public function getAviaReferenceFlights($params = null, $flight = null, $doLogging = false, $forTestConn = false)
	{
		if ($doLogging)
			qvar_dump('getAviaReferenceFlights - args', func_get_args());
		
		$useUrl = static::$FlightsCheckUrl . ($params ? "?" . http_build_query($params) : "");	
		$requestToAvia = false;
		if (!($data = $this->cachedData["aviaFlights"][$useUrl]))
		{
			$up_lock = null;
			$_fLock = "temp/avia_result_" . md5($useUrl) . ".txt";
			if (!file_exists($_fLock))
				file_put_contents($_fLock, "getAviaReferenceFlights");

			// wait 20 seconds - it may be in another call
			$pos = 0;
			do {
				$up_lock = \QFileLock::lock($_fLock, 1);
				sleep(1);
				$pos++;
				if ($pos > 20)
					break;
			}
			while (!$up_lock);
			
			try
			{
				$requestToAvia = true;

				if (!(defined('USE_FGC_FOR_TEZ_AVIA') && USE_FGC_FOR_TEZ_AVIA))
				{
					$curlHandle = q_curl_init_with_log();
					q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $useUrl);
					q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
					q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
					q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
					q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
					q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
					q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);
					// exec curl
					$data = q_curl_exec_with_log($curlHandle);
					curl_close($curlHandle);
				}
				else
				{
					$contextOpts = [
						"ssl" => [
							"verify_peer" => false,
							"verify_peer_name" => false,
						],
					];
					if ($doLogging)
						qvar_dump('getAviaReferenceFlights', $useUrl);
					$data = file_get_contents($useUrl, false, stream_context_create($contextOpts));
					if ($doLogging)
						qvar_dump('getAviaReferenceFlights - $data', $data, json_decode($data));
				}

				if ($data === false)
				{
					$ex = new \Exception("Cannot connect to flights check url" . ($forTestConn ? ' [' . $useUrl . ']' : '') . "!");
					$this->logError(["params" => $params, "useUrl" => $useUrl, "\$flight" => $flight], $ex);
					throw $ex;
				}
				$this->cachedData["aviaFlights"][$useUrl] = $data;
				if ($doLogging)
				{
					$this->logDataSimple("avia_ref_flights_request", ["params" => $params, 
						"useUrl" => $useUrl, 
						"\$flight" => $flight, 
						"\$data" => $data, 
						"\$this->cachedData[\"aviaFlights\"]" => $this->cachedData["aviaFlights"]
					]);
				}
			}
			catch (\Exception $ex)
			{
				throw $ex;
			}
			finally
			{
				if ($up_lock)
					$up_lock->unlock();
			}
		}

		if ($doLogging)
			$this->logDataSimple("avia_ref_flights", ["params" => $params, "useUrl" => $useUrl, "\$flight" => $flight, "\$data" => $data]);

		$dataDec = json_decode($data, true);

		
		
		$ret = null;
		if ($flight !== null)
		{
			$flightsById = $this->getFlightsById(true);
			
			if ($doLogging)
				qvar_dump('getAviaReferenceFlights - $flightsById', $flightsById);
			
			//$mainIndx = $flight['departureDatetime'] . "|" . $flight['arrivalDatetime'];
			if ($dataDec && ($flights = $dataDec["flights"]) && (($fullFlight = $flightsById[$flight["Flight"]]) && ($flightNumber = $fullFlight["number"])))
			{
				if (isset($flights['airCompany']))
					$flights = [$flights];
				
				$indexedFlights = [];
				foreach ($flights ?: [] as $flight)
				{
					//$flightIndx = $flight["depDateTime"] . "|" . $flight["arrDateTime"];
					if (isset($indexedFlights[$flight["flightNumber"]]))
					{
						$ex = new \Exception("multiple flights with same flight number!");
						$this->logError(["params" => $params, "useUrl" => $useUrl, "\$flight" => $flight, "\$indexedFlights" => $indexedFlights, "\$fullFlight" => $fullFlight], $ex);
						throw $ex;
					}
					$indexedFlights[$flight["flightNumber"]] = $flight;
				}
				
				$ret = $indexedFlights[$flightNumber];
				if ($doLogging)
					qvar_dump('getAviaReferenceFlights - $indexedFlights, $flights', $indexedFlights, $flights);
			}
		}
		else if ($dataDec)
			$ret = $dataDec["flights"];
		
		if ($doLogging)
			qvar_dump('getAviaReferenceFlights - return', $ret);

		return $ret;
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

		$resynced = $this->ensureMapData();

		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		if (!is_dir($cacheFolder))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'missing cache folder');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			throw new \Exception('Missing cache folder!');
		}
		
		// get cache file
		$countriesCacheFile = $cacheFolder . 'countries.json';
		
		// get airports cache file
		$airportsCacheFile = $cacheFolder . 'airports.json';
		
		// exception if nof cache file
		if (!file_exists($countriesCacheFile))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing countries cache file');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			throw new \Exception('Missing countries cache file!');
		}
		
		// exception if no airports cache file
		if (!file_exists($airportsCacheFile))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing airports cache file');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			throw new \Exception('Missing airports cache file');
		}
		
		// get mapped data
		$countrieMappedDataJSON = file_get_contents($countriesCacheFile);

		// get airports mapped data
		$airportsMappedDataJSON = file_get_contents($airportsCacheFile);
	
		// decode json
		$countrieMappedData = json_decode($countrieMappedDataJSON, true);
		
		// decode json
		$airportsMappedData = json_decode($airportsMappedDataJSON, true);
		
		// get countries file
		$countriesFromFile = $this->getCountriesFromFile();
		
		// init roairports
		$roAirports = [];

		// go through each airport
		foreach ($airportsMappedData as $airportData)
		{
			// get only romanian airports
			#if (in_array($airportData['IATA'], static::$ROAirports))
			if ($airportData["Country"] == static::$RoCountryID_ForAirports)
				$roAirports[$airportData['Id']] = $airportData;
		}

		$airportsCountries = [];
		foreach ($roAirports as $roAirportData)
		{
			if ($countriesFromFile[$roAirportData['Country']])
			{
				if (strpos($countriesFromFile[$roAirportData['Country']]['Name'], '(INCOMING)'))
					continue;
				$airportsCountries[$roAirportData['Country']] = $countriesFromFile[$roAirportData['Country']];
			}
		}

		// --------------- disabled --------------------
		/*
		// get api response
		$countriesResp = $this->request('getTari');
		
		// load xml
		$countriesXml = simplexml_load_string($countriesResp);
		
		// exit if no countries
		if (!$countriesXml->DataSet->Body->Tari->Tara)
		{
			\Omi\TF\TOInterface::markReportError($filter, 'No countries returned by tour operator');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			return [false, true];
		}
		*/
		// -------------- added --------------------------------

		if (empty($countriesFromFile))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'No countries returned by tour operator');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			return [false, true];
		}
		
		// get mapped countries
		$countriesMapping = $this->getCountriesMapping();
		
		// init countries array
		$countries = [];
		\Omi\TF\TOInterface::markReportData($filter, 'Count countries from airports: %s', [count($airportsCountries)]);

		foreach ($airportsCountries as $airportCountry)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process country: %s', [$airportCountry['Id'] . ' ' . $airportCountry['Name']], 50);
			// new country object
			$country = new \stdClass();
			$country->Id = $airportCountry['Id'];
			$country->Name = $airportCountry['Name'];
			$country->Code = $countriesMapping[strtolower($airportCountry['Name'])];

			// index countries by id
			$countries[$country->Id] = $country;
		}
		// ---------------- disabled ------------------------
		// \Omi\TF\TOInterface::markReportData($filter, 'Count countries: %s', [count($countriesXml->DataSet->Body->Tari->Tara)]);
		\Omi\TF\TOInterface::markReportData($filter, 'Count countries: %s', [count($countriesFromFile)]);
		
		// go through each country
		// ------------------- added ------------------------------
		foreach ($countriesFromFile as $countryData)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process country: %s', [$countryData['Id'] . ' ' . $countryData['Name']], 50);
			// new country object
			$country = new \stdClass();

			$country->Id = $countryData['Id'];
			$country->Name = $countryData['Name'];
			$countryNameTrimmed = trim(strtolower(str_replace('(INCOMING)', '', $country->Name)));
			if (!isset( $countriesMapping[$countryNameTrimmed])) {
				continue;
			}

			$country->Code = $countriesMapping[$countryNameTrimmed];
			// index countries by id
			$countries[$country->Id] = $country;
			
		}

		// --------------------------- disabled --------------------------
		/*
		foreach ($countriesXml->DataSet->Body->Tari->Tara as $countryData)
		{
			if ($countrieMappedData[(string)$countryData['id']])
			{
				\Omi\TF\TOInterface::markReportData($filter, 'Process country: %s', [(string)$countryData['id'] . ' ' . (string)$countryData], 50);
				// new country object
				$country = new \stdClass();
				$country->Id = $countrieMappedData[(string)$countryData['id']];
				$country->Name = (string)$countryData;
				$country->Code = $countriesMapping[strtolower($country->Name)];

				// index countries by id
				$countries[$country->Id] = $country;
			}
		}*/
		
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
		$this->ensureMapData();
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
		
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');
		
		// get cache file
		$regionsCacheFile = $cacheFolder . 'regions.json';
		
		// exception if nof cache file
		if (!file_exists($regionsCacheFile))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing regions cache file');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
			throw new \Exception('Missing regions cache file!');
		}
		
		// ---------------- disabled ---------------------------------
		/*
		// get mapped data
		$regionsMappedDataJSON = file_get_contents($regionsCacheFile);
		
		// decode json
		$regionsMappedData = json_decode($regionsMappedDataJSON, true);
		*/

		// ----------------- added ---------------------
		$regionsMappedData = $this->getRegionsFromFile();
		// -------------------------------------------------

		// get countries
		list($countries) = $this->api_getCountries(['skip_report' => true]);
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count regions: %s', [$regionsMappedData ? count($regionsMappedData) : 'no_regions']);
		
		// init regions
		$regions = [];
		
		// go through each region
		// ------------ added -----------------------------
		foreach ($regionsMappedData as $regionId => $regionData)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', [$regionId . ' ' . $regionData->Name], 50);
			// new region object
			$region = new \stdClass();
			$region->Id = $regionId;
			$region->Name = $regionData->Name;
			if (!isset($countries[$regionData->CountryExternalId])) {
				continue;
			}
			$region->Country = $countries[$regionData->CountryExternalId];
			
			// index regions by region id
			$regions[$region->Id] = $region;
		}

		// ---------------- disabled ---------------------
		/*
		foreach ($regionsMappedData as $regionId => $regionData)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', [$regionId . ' ' . $regionData['Name']], 50);
			// new region object
			$region = new \stdClass();
			$region->Id = $regionId;
			$region->Name = $regionData['Name'];
			
			$region->Country = $countries[$regionData['CountryExternalId']];
			
			// index regions by region id
			$regions[$region->Id] = $region;
		}*/
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'endpoint');
		// return regions
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
		$this->ensureMapData();
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
		
		if (!is_dir($cacheFolder))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing cache folder');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
			throw new \Exception('Missing cache folder!');
		}
		
		// get cache file
		$citiesCacheFile = $cacheFolder . 'cities.json';
		
		// exception if nof cache file
		if (!file_exists($citiesCacheFile))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing cities cache file');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
			throw new \Exception('Missing cities cache file!');
		}
		// -------------- disabled ---------------------------
		/*
		// get mapped data
		$citiesMappedDataJSON = file_get_contents($citiesCacheFile);
		
		// decode json
		$citiesMappedData = json_decode($citiesMappedDataJSON, true);
		*/

		// ----------------- added --------------------------------
		$citiesMappedData = $this->getCitiesFromFile();
		// ----------------------------------------------------

		// get regions
		list($regions) = $this->api_getRegions(['skip_report' => true]);
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s', [count($citiesMappedData)]);

		// init cities
		$cities = [];

		// -------------------- added ----------------------------
		foreach ($citiesMappedData as $externalId => $cityData)
		{
			// new city object
			$city = new \stdClass();
			$city->Id = $externalId;
			$city->Name = $cityData['Name'];
			
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$externalId . ' ' . $cityData['Name']], 50);

			if ((!($region = $regions[$cityData['RegionId']])) || (!($region->Country)))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Skipped: country not found for city', [json_encode($cityData)], 50);
				//throw new \Exception("Region is mandatory for city/Region does not have country!");
				continue;
			}

			$city->County = $region;
			$city->Country = $region->Country;

			if ($filter["countryId"] && ($filter["countryId"] != $region->Country->Id))
				continue;

			if ($filter["onlyFirst"])
				return [$city];

			// index cities by id
			$cities[$city->Id] = $city;
		}
		
		// ------------------ disabled ----------------
		/*
		foreach ($citiesMappedData as $cityId => $cityData)
		{
			// new city object
			$city = new \stdClass();
			$city->Id = $cityData['ExternalId'];
			$city->Name = $cityData['Name'];
			
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$cityData['ExternalId'] . ' ' . $cityData['Name']], 50);

			if ((!($region = $regions[$cityData['RegionId']])) || (!($region->Country)))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Skipped: country not found for city', [json_encode($cityData)], 50);
				//throw new \Exception("Region is mandatory for city/Region does not have country!");
				continue;
			}

			$city->County = $region;
			$city->Country = $region->Country;

			if ($filter["countryId"] && ($filter["countryId"] != $region->Country->Id))
				continue;

			if ($filter["onlyFirst"])
				return [$city];

			// index cities by id
			$cities[$city->Id] = $city;
		}*/
		
		$airportCities = $this->getAirportCities();
		
		$cities += $airportCities;
		
		if ($filter["countryId"])
		{
			$countryRet = [];
			foreach ($cities ?: [] as $city)
			{
				if ((!$city->Country) || ($city->Country->Id != $filter["countryId"]))
					continue;

				if ($filter["onlyFirst"])
					return [$city];
				
				$countryRet[$city->Id] = $city;
			}
			$cities = $countryRet;
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
		// return $cities
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

		$this->ensureMapData();
		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		if (!is_dir($cacheFolder))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing cache folder');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
			throw new \Exception('Missing cache folder!');
		}

		// get cache file
		$hotelsCacheFile = $cacheFolder . 'hotels.json';

		// exception if nof cache file
		if (!file_exists($hotelsCacheFile))
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Missing hotels cache file');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
			throw new \Exception('Missing hotels cache file');
		}

		// -------------- disabled ---------------------------
		/*
		// get mapped data
		$hotelsMappedDataJSON = file_get_contents($hotelsCacheFile);

		// decode json
		$hotelsMappedData = json_decode($hotelsMappedDataJSON, true);
		*/

		// get countries
		list($countries) = $this->api_getCountries(['skip_report' => true]);

		// get regions
		list($regions) = $this->api_getRegions(['skip_report' => true]);

		// get cities
		list($cities) = $this->api_getCities(['skip_report' => true]);

		// init hotels
		$hotels = [];

		// ------------- added ---------------------------
		$hotelsFromFile = $this->getHotelsFromFile();
		$countriesFromB2B = ['1104', '5732', '5733', '7067149', '7067498', '7067673'];

		foreach ($hotelsFromFile as $hotelFromFile) {
			$externalId = $hotelFromFile->Id;

			if (!in_array($hotelFromFile->CountryId, $countriesFromB2B)) {
				continue;
			}

			\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', $externalId . ' ' . $hotelFromFile->Name, 50);

			// new hotel object
			$hotel = new \stdClass();
			$hotel->Id = $externalId;
			$hotel->Name = $hotelFromFile->Name;
			$hotel->Stars = 0;

			$hotel->Content = new \stdClass();
			$hotel->Content->Content = '';

			$hotel->Address = new \stdClass();

			/*
			$isFromBadCountries = $hotelFromFile->CountryId != 26414 && // kiev
				$hotelFromFile->CountryId != 150601 && // russia
				$hotelFromFile->CountryId != 276544 &&  // abhaziya
				$hotelFromFile->CountryId != 526525 && // uzbekistan
				$hotelFromFile->CountryId != 531639; // kirgizia
			*/

			$hotel->Address->Country = $countries[$hotelFromFile->CountryId];
			$hotel->Address->City = $cities[$hotelFromFile->CityId];
			$hotel->Address->County = $regions[$hotelFromFile->CountyId];

			if ($hotel->Address->City)
			{
				$hotel->Address->City->County = $regions[$hotelFromFile->CountyId];
				$hotel->Address->City->Country = $countries[$hotelFromFile->CountryId];
			}

			if ($hotel->Address->County)
				$hotel->Address->County->Country = $countries[$hotelFromFile->CountryId];

			$hotels[$hotel->Id] = $hotel;
		}

		// -------------- disabled ---------------------------
		/*
		foreach ($hotelsMappedData as $hotelId => $hotelData)
		{
			//echo $hotelId . " | " . $hotelData["ExternaId"] . " | " . $hotelData["Name"] . "<br/>";
			// fix of the year
			if (!trim($hotelData['ExternaId']))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Hotel does not have external id: %s', [json_encode($hotelData)], 50);
				continue;
			}

			$loadedFromCache = false;
			$hotelCacheFile = $cacheFolder . 'hotel-' . $hotelData["ExternaId"] . '.xml';
			$cf_last_modified = ($f_exists = file_exists($hotelCacheFile)) ? filemtime($hotelCacheFile) : null;
			$cache_time_limit = time() - $this->cacheTimeLimit;

			// if exists - last modified
			if (($f_exists) && (($cf_last_modified >= $cache_time_limit) || (!$filter["get_extra_details"])))
			{
				$hotelInfoResp = file_get_contents($hotelCacheFile);
				$loadedFromCache = true;
			}

			if ((!$loadedFromCache) && ($filter["get_extra_details"]) && (!$filter["skip_getInfoRequest"]))
			{
				// get hotel info response
				$hotelInfoResp = $this->request('getInfoHotel', ['id_hotel' => $hotelId]);
				file_put_contents($hotelCacheFile, $hotelInfoResp);
			}

			if (!$hotelInfoResp)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'No hotel info response: %s', [json_encode($hotelData)], 50);
				continue;
			}
			// load xml
			$hotelInfoXml = simplexml_load_string($hotelInfoResp);
			if (!$hotelInfoXml->DataSet->Body->Hoteluri->Hotel)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Hotel data cannot be decoded: %s', [json_encode($hotelInfoXml)], 50);
				continue;
			}
			$hotelDataXml = $hotelInfoXml->DataSet->Body->Hoteluri->Hotel;

			\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', [$hotelData['ExternaId'] . ' ' . (string)$hotelDataXml->Nume], 50);

			//echo "<div style='color: green;'>PROCESS: [{$hotelDataXml->Nume}|{$hotelData['ExternaId']}]</div>";

			// new hotel object
			$hotel = new \stdClass();
			$hotel->Id = $hotelData['ExternaId'];
			$hotel->Name = (string)$hotelDataXml->Nume;
			$hotelStars = (string)$hotelDataXml->Stele;
			if (($hotelStars == '1') || ($hotelStars == '2') || ($hotelStars == '3') || ($hotelStars == '4') || ($hotelStars == '5')
					|| ($hotelStars == '1+') || ($hotelStars == '2+') || ($hotelStars == '3+') || ($hotelStars == '4+') || ($hotelStars == '5+'))
				$hotel->Stars = str_replace('+', '', $hotelStars);

			$hotel->Content = new \stdClass();
			$hotel->Content->Content = '';

			if ($hotelDataXml->Descriere)
				$hotel->Content->Content .= '<h5>Descriere hotel</h5>' . (string)$hotelDataXml->Descriere . '<br />';

			if ($hotelDataXml->Camera)
				$hotel->Content->Content .= '<h5>Camera hotel</h5>' . (string)$hotelDataXml->Camera . '<br />';

			if ($hotelDataXml->Teritoriu)
				$hotel->Content->Content .= '<h5>Spatiu exterior</h5>' . (string)$hotelDataXml->Teritoriu . '<br />';

			if ($hotelDataXml->Relaxare)
				$hotel->Content->Content .= '<h5>Relaxare si sport</h5>' . (string)$hotelDataXml->Relaxare . '<br />';

			if ($hotelDataXml->Copii)
				$hotel->Content->Content .= '<h5>Pentru copii</h5>' . (string)$hotelDataXml->Copii . '<br />';

			if ($hotelDataXml->Plaja)
				$hotel->Content->Content .= '<h5>Plaja</h5>' . (string)$hotelDataXml->Plaja . '<br />';

			if ($hotelDataXml->Comentariu)
				$hotel->Content->Content .= '<h5>Comentariul nostru</h5>' . (string)$hotelDataXml->Comentariu . '<br />';

			$hotel->Address = new \stdClass();
			$hotel->Address->Country = $countries[$hotelData['CountryId']];
			$hotel->Address->City = $cities[$hotelData['CityId']];
			$hotel->Address->County = $regions[$hotelData['CountyId']];

			if ($hotel->Address->City)
			{
				$hotel->Address->City->County = $regions[$hotelData['CountyId']];
				$hotel->Address->City->Country = $countries[$hotelData['CountryId']];
			}

			if ($hotel->Address->County)
				$hotel->Address->County->Country = $countries[$hotelData['CountryId']];

			if ($hotelDataXml->Imagini->Imagine)
			{
				$hotel->Content->ImageGallery = new \stdClass();
				foreach ($hotelDataXml->Imagini->Imagine as $hotelPicture)
				{
					$photo_obj = new \stdClass();
					$photo_obj->RemoteUrl = (string)$hotelPicture;
					$hotel->Content->ImageGallery->Items[] = $photo_obj;
				}
			}
			$hotels[$hotel->Id] = $hotel;
		}*/
		
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
		return $hotels;
	}
	
	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelDetails(array $filter = null)
	{
		
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
	public function api_getHotelsRooms(array $filter = null)
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
		$ret = null;
		try
		{
			$this->ensureMapData();
			$serviceType = $this->checkFilters($filter);

			switch ($serviceType)
			{
				case 'hotel':
				case 'individual':
				{
					// get offers index by hotels
					$ret = $this->getIndividualOffers($filter);
					break;
				}
				case 'charter' : 
				{
					$ret = $this->getCharterOffers($filter);
					break;
				}
			}
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		return [$ret];
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
		
	}

	/**
	 * 
	 */
	public function api_getOfferPaymentsPlan(array $filter = null)
	{
		
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
	 * 
	 */
	public function __api_getAvailabilityDates(array $filter = null)
	{
		if ((!($type = $filter['type'])) || ($type !== "charter"))
		{
			throw new \Exception("Only charters supported!");
		}
	}

	public function getChartersAvailabilityDates(array $filter = null)
	{
		list($cities) = $this->api_getCities();

		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		// get cache file
		$airportsCacheFile = $cacheFolder . 'airports.json';

		// exception if nof cache file
		if (!file_exists($airportsCacheFile))
			throw new \Exception('Missing airports cache file!');

		// get mapped data
		$airportsMappedDataJSON = file_get_contents($airportsCacheFile);

		// decode json
		$airportsMappedData = json_decode($airportsMappedDataJSON, true);

		// init roairports
		$roAirports = [];

		// go through each airport
		foreach ($airportsMappedData as $airportData)
		{
			// get only romanian airports
			#if (in_array($airportData['IATA'], static::$ROAirports))
			if ($airportData["Country"] == static::$RoCountryID_ForAirports)
				$roAirports[$airportData['Id']] = $airportData['IATA'];
		}

		// get flight departures from file
		$flightDeparturesFromFile = $this->getFlightDepartures(true, true);

		// init ro departures
		$roDepartures = [];
		$returnDates = [];


		// go though each departure flight
		foreach ($flightDeparturesFromFile as $flightDepartureData)
		{
			$fromAirport = $airportsMappedData[$flightDepartureData['departureAirport']];
			$toAirport = $airportsMappedData[$flightDepartureData['arrivalAirport']];

			//echo $flightDepartureData['departureAirport'] . "<br/>";
			if (array_key_exists($flightDepartureData['departureAirport'], $roAirports))
			{
				$roDepartures[date('Y-m-d', strtotime($flightDepartureData['departureDatetime']))][] = [
					'FlightData' => $flightDepartureData, 
					'FromAirport' => $fromAirport,
					'ToAirport' => $toAirport
				];
			}

			//echo "LEAVES FROM AIRPORT [{$fromAirport["IATA"]}] TO AIRPORT [{$toAirport["IATA"]}] - " . $flightDepartureData['departureDatetime'] . "<br/>";

			if (!isset($returnDates[$flightDepartureData['departureAirport']]))
				$returnDates[$flightDepartureData['departureAirport']] = [];

			if (!isset($returnDates[$flightDepartureData['departureAirport']][$flightDepartureData['arrivalAirport']]))
				$returnDates[$flightDepartureData['departureAirport']][$flightDepartureData['arrivalAirport']] = [];

			$returnDates[$flightDepartureData['departureAirport']][$flightDepartureData['arrivalAirport']][$flightDepartureData['departureDatetime']] = $flightDepartureData;
		}

		// init transports array
		$transports = [];

		$transportType = 'plane';

		foreach ($roDepartures as $date => $roDeparturesRows)
		{				
			foreach ($roDeparturesRows as $roDeparture)
			{
				$flightData = $roDeparture['FlightData'];
				$fromAirport = $roDeparture['FromAirport'];
				$toAirport = $roDeparture['ToAirport'];

				$backDates = $returnDates[$flightData['arrivalAirport']][$flightData['departureAirport']];

				$backDatePlusOneDay = new \DateTime($date . ' +2 day');
				$backDatePlus2Weeks = new \DateTime($date . ' +16 day');

				$newArr = [];

				if (!$cities[$toAirport['City']])
					continue;

				$allDestinationCities = [$cities[$toAirport['City']]];

				foreach ($allDestinationCities as $cityByRegion)
				{
					$transportId = $transportType . "~city|" . $fromAirport['City'] . "~city|" . $cityByRegion->Id;

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
						$transports[$transportId]->From->City = $cities[$fromAirport['City']];
					}

					if (!isset($transports[$transportId]->To))
					{
						$transports[$transportId]->To = new \stdClass();
						$transports[$transportId]->To->City = $cityByRegion;
					}

					if (!isset($transports[$transportId]->Dates[$date]))
					{
						$dateObj = new \stdClass();
						$dateObj->Date = date('Y-m-d', strtotime($date));
						$dateObj->Nights = [];

						$transports[$transportId]->Dates[$date] = $dateObj;
					}

					foreach ($backDates as $backDate => $flightDepartureData)
					{
						$backDateObj = new \DateTime($backDate);

						if (($backDateObj > $backDatePlusOneDay) && ($backDateObj < $backDatePlus2Weeks))
						{
							$newArr[$date][] = $backDateObj->format('d.m.Y');					

							$days = $backDateObj->diff(new \DateTime($date))->format('%a');

							if (!isset($transports[$transportId]->Dates[$date]->Nights[$days]))
							{
								$nightsObj = new \stdClass();
								$nightsObj->Nights = $days;

								$transports[$transportId]->Dates[$date]->Nights[$days] = $nightsObj;
							}
						}
					}
				}
			}					
		}
		return $transports;
	}

	/**
	 * 
	 */
	public function api_getAvailabilityDates(array $filter = null)
	{
		$ret = null;
		if ($filter['type'] == 'charter')
		{
			$ret = $this->getChartersAvailabilityDates();
		}

		// return transports
		return [$ret];
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
	public function api_doBooking(array $data = null)
	{
		// get offer
		$offer = reset($data['Items']);
		// exception if no offer details
		if (!$offer || !$offer['Board_Type_InTourOperatorId'] || !$offer['Room_Type_InTourOperatorId'] || !$offer['Room_CheckinAfter'] || !$offer['Room_CheckinBefore'])
			throw new \Exception('Missing offer data!');
		// get hotel
		$hotel = $offer['Hotel'];
		// exception if no hotel details
		if (!$hotel || !$hotel['InTourOperatorId'] || !$hotel['Country_InTourOperatorId'] || !$hotel['City_InTourOperatorId'])
			throw new \Exception('Missing hotel data');
		// get passengers
		$passengers = $data['Passengers'];
		// exception if no passengers
		if (!$passengers || (count($passengers) == 0))
			throw new \Exception('Missing Passengers');
		// get session id
		$sessionId = $this->getSessionId();
		// exit if no session id
		if (!$sessionId)
			throw new \Exception("Eroare tur operator: id-ul de sesiune nu a putut fi determinat!");
		// init services
		$services = [];
		$services[1] = 
			'<Residence>
				<serviceId>1</serviceId>
				<checkIn>' . date('d.m.Y', strtotime($offer['Room_CheckinAfter'])) . '</checkIn>
				<checkOut>' . date('d.m.Y', strtotime($offer['Room_CheckinBefore'])) . '</checkOut>
				<hotel>' . $hotel['InTourOperatorId'] . '</hotel>
				<hotelPansion>' . $offer['Board_Type_InTourOperatorId'] . '</hotelPansion>
				<hotelRoom>' . $offer['Room_Type_InTourOperatorId'] . '</hotelRoom>
				<regionId>' . $hotel['City_InTourOperatorId'] . '</regionId>
			</Residence>';
		if ($offer['Offer_FlightDeparture'] && $offer['Offer_SeatTypeFlight'])
		{
			$services[2] = 
				'<Ticket>
					<serviceId>2</serviceId>
					<flightDeparture>' . $offer['Offer_FlightDeparture'] . '</flightDeparture>
					<seatType>' . $offer['Offer_SeatTypeFlight'] . '</seatType>
				</Ticket>';
		}
		if ($offer['Offer_FlightDepartureBack'] && $offer['Offer_SeatTypeFlightBack'])
		{
			$services[3] = 
				'<Ticket>
					<serviceId>3</serviceId>
					<flightDeparture>' . $offer['Offer_FlightDepartureBack'] . '</flightDeparture>
					<seatType>' . $offer['Offer_SeatTypeFlightBack'] . '</seatType>
				</Ticket>';
		}
		if ($offer['Offer_FlightDeparture'] && $offer['Offer_TransferAirportId'] && $offer['Offer_TransferTypeId'])
		{
			$services[4] = 
				'<Transfer>
					<serviceId>4</serviceId>
					<date>' . date("d.m.Y", strtotime($offer['Room_CheckinAfter'])) . '</date>
					<fromId>' . $offer['Offer_TransferAirportId'] . '</fromId>
					<toId>' . $hotel['InTourOperatorId'] . '</toId>
					<type>' . $offer['Offer_TransferTypeId'] . '</type>
					<flightDeparture>' . $offer['Offer_FlightDeparture'] . '</flightDeparture>
				</Transfer>';
		}
		if ($offer['Offer_FlightDepartureBack'] && $offer['Offer_TransferAirportId'] && $offer['Offer_BackTransferTypeId'])
		{
			$services[5] = 
				'<Transfer>
					<serviceId>5</serviceId>
					<date>' . date('d.m.Y', strtotime($offer['Room_CheckinBefore'])) . '</date>
					<fromId>' . $hotel['InTourOperatorId'] . '</fromId>
					<toId>' . $offer['Offer_TransferAirportId'] . '</toId>
					<type>' . $offer['Offer_BackTransferTypeId'] . '</type>
					<flightDeparture>' . $offer['Offer_FlightDepartureBack'] . '</flightDeparture>
				</Transfer>';
		}
		// get genders
		$gendersFromFile = $this->getGendersFromFile();
		// create xml of services
		$servicesXml = '';
		foreach ($services as $key => $service)
			$servicesXml .= $service;
		// go through each passenger
		$passengersXml = '';
		$serviceTouristXml = '';
		$count = 1;
		foreach ($passengers as $passenger)
		{
			$passengerBirtDate = date('d.m.Y', strtotime($passenger['BirthDate']));
			if ($passenger['Type'] == 'adult')
			{
				if ($passenger['Gender'] == 'male')
					$genderId = $gendersFromFile['MR.']['Id'];
				else if ($passenger['Gender'] == 'female')
					$genderId = $gendersFromFile['MRS.']['Id'];
			}
			else if ($passenger['Type'] == 'child')
			{
				$birthDate = explode("-", $passenger['BirthDate']);
			
				//get age from date or birthdate
				$age = (date("md", date("U", mktime(0, 0, 0, $birthDate[1], $birthDate[2], $birthDate[0]))) > date("md")
				  ? ((date("Y") - $birthDate[0]) - 1)
				  : (date("Y") - $birthDate[0]));
				
				if ($age <= 2)
					$genderId = $gendersFromFile['INF.']['Id'];
				else
					$genderId = $gendersFromFile['CHD.']['Id'];
			}
			$passengersXml .= '<Tourist>
					<touristId>' . $count . '</touristId>
					<surname>' . $passenger['Lastname'] . '</surname>
					<name>' . $passenger['Firstname'] . '</name>
					<gender>' . $genderId . '</gender>
					<birthday>' . $passengerBirtDate . '</birthday>
				</Tourist>';
			foreach ($services as $key => $service)
			{
				$serviceTouristXml .= '<ServiceTourist>
					<serviceId>' . $key . '</serviceId>
					<touristId>' . $count . '</touristId>
				</ServiceTourist>';
			}
			$count++;
		}
		$requestXml = '<?xml version="1.0"?>
			<order>
				<OrderType>
					<type>Touristic</type>
				</OrderType>
				<Country>
					<id>' . $hotel['Country_InTourOperatorId'] . '</id>
				</Country>
				' . $passengersXml .'
				' . $servicesXml . '
				' . $serviceTouristXml . '
			</order>';
		// calculate booking
		list($calculateBooking, $calculateBookingRaw, $calculateBookignUrl, $requestXml) = $this->calculateBooking($sessionId, $requestXml);
		// exit if no price
		if (!($calculateBooking->price))
		{
			$ex = new \Exception("Pretul nu poate fi calculat!");
			$this->logError([
				"calculateBookingUrl" => $calculateBookignUrl,
				"reqXML" => $requestXml, 
				"respXML" => $calculateBookingRaw,
				"\$calculateBooking" => $this->simpleXML2Array($calculateBooking)], $ex);
			throw $ex;
		}
		// create booking
		$bookOrderResponse = $this->createBooking($sessionId, $requestXml);
		if ((!$bookOrderResponse) || (!$bookOrderResponse->orderId))
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError(["\$bookOrderResponse" => $bookOrderResponse], $ex);
			throw $ex;
		}
		$order = new \stdClass();
		$order->Id = $bookOrderResponse->orderId;
		// return order and xml confrm reservation
		return [$order, $bookOrderResponse];
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
	
	/**
	 * Get session id.
	 * 
	 * @return boolean
	 */
	public function getSessionId()
	{
		// build authorization url
		$authorizationUrl = rtrim($this->TourOperatorRecord->BookingUrl, "\\/") . '/auth_data.jsp?j_login_request=1&j_login=' 
			. $this->TourOperatorRecord->BookingApiUsername . '&j_passwd=' . $this->TourOperatorRecord->BookingApiPassword;
		// init curl
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $authorizationUrl);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);
		// exec curl
		$data = q_curl_exec_with_log($curlHandle);
		$this->logData("book:getSessionId", ["authorizationUrl" => $authorizationUrl, "respXML" => $data]);
		if ($data === false)
		{
			$ex = new \Exception("Invalid response from server - " . curl_error($curlHandle));
			$this->logError(["\$authorizationUrl" => $authorizationUrl], $ex);
			throw $ex;
		}
		// load xml
		$dataXml = simplexml_load_string($data);
		// get session id
		$sessionId = (string)$dataXml->sessionId;
		
		// return session id
		return $sessionId;
	}
	
	/**
	 * Calculate booking
	 * 
	 * @param type $sessionId
	 * @param type $requestXml
	 * @return boolean
	 */
	public function calculateBooking($sessionId, $requestXml)
	{
		// build calculate booking url
		$calculateBookignUrl = rtrim($this->TourOperatorRecord->BookingUrl, "\\/") . '/order/calculate?aid=' . $sessionId;

		// setup headers
		$headers = ['Content-Type: application/xml'];

		// init curl
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $calculateBookignUrl);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_POST, 1);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HTTPHEADER , $headers);
		q_curl_setopt_with_log($curlHandle, CURLOPT_POSTFIELDS, $requestXml);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);
		
		// exec curl
		$data = q_curl_exec_with_log($curlHandle);
		$this->logData("book:calculateBooking", ["calculateBookignUrl" => $calculateBookignUrl, "reqXML" => $requestXml, "respXML" => $data]);
		if ($data === false)
		{
			$ex = new \Exception("Invalid response from server - " . curl_error($curlHandle));
			$this->logError(["\$calculateBookignUrl", $calculateBookignUrl, "reqXML" => $requestXml], $ex);
			throw $ex;
		}
		
		$dataXml = simplexml_load_string($data);
		
		// return xml
		return [$dataXml, $data, $calculateBookignUrl, $requestXml];
	}
	
	/**
	 * Create booking
	 * 
	 * @param type $sessionId
	 * @param type $requestXml
	 * @return type
	 */
	public function createBooking($sessionId, $requestXml)
	{		
		// build booking url
		$bookingUrl = rtrim($this->TourOperatorRecord->BookingUrl, "\\/") . '/order/book?aid=' . $sessionId;
		// setup headers
		$headers = ['Content-Type: application/xml'];
		// init curl
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $bookingUrl);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_POST, 1);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HTTPHEADER , $headers);
		q_curl_setopt_with_log($curlHandle, CURLOPT_POSTFIELDS, $requestXml);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);

		// exec curl
		$data = q_curl_exec_with_log($curlHandle);
		$this->logData("book:createBooking", ["bookingUrl" => $bookingUrl, "sessionId" => $sessionId, "reqXML" => $requestXml, "respXML" => $data]);
		if ($data === false)
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError(["bookingUrl" => $bookingUrl, "sessionId" => $sessionId, "reqXML" => $requestXml, "respXML" => $data], $ex);
			throw $ex;
		}
		if (!($this->xmlIsValid($data)))
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError(["No_VALIDXML" => true, "bookingUrl" => $bookingUrl, "sessionId" => $sessionId, "reqXML" => $requestXml, "respXML" => $data], $ex);
			throw $ex;
		}
		// load xml
		return simplexml_load_string($data);
	}

	public function ensureMapData($force = false)
	{
		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		// get country cache file
		$countriesCacheFile = $cacheFolder . 'countries.json';

		// get cities cache file
		$citiesCacheFile = $cacheFolder . 'cities.json';

		// gte regions cache file
		$regionsCacheFile = $cacheFolder . 'regions.json';

		// get hotels cache file
		$hotelsCacheFile = $cacheFolder . 'hotels.json';

		// get airports cache file
		$airportsCacheFile = $cacheFolder . 'airports.json';

		$map_cache_time_limit = (time() - $this->mapDataCacheLimit);

		$resynced = false;
		// if we don't have map data connections
		if ($force || (
			(
				(!file_exists($countriesCacheFile)) || 
				(!file_exists($citiesCacheFile)) || 
				(!file_exists($regionsCacheFile)) || 
				(!file_exists($hotelsCacheFile))|| 
				(!file_exists($airportsCacheFile))
			) || 
			(
				(filemtime($countriesCacheFile) < $map_cache_time_limit) || 
				(filemtime($citiesCacheFile) < $map_cache_time_limit) || 
				(filemtime($regionsCacheFile) < $map_cache_time_limit) ||
				(filemtime($hotelsCacheFile) < $map_cache_time_limit) || 
				(filemtime($airportsCacheFile) < $map_cache_time_limit)
			)
		))
		{
			$this->mapData();
			$resynced = true;
		}
		return $resynced;
	}

	public function mapData()
	{		
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
		
		// create directory if it doesn't exist
		if (!is_dir($cacheFolder))
			qmkdir($cacheFolder);
		
		// get country cache file
		$countriesCacheFile = $cacheFolder . 'countries.json';
		
		// get cities cache file
		$citiesCacheFile = $cacheFolder . 'cities.json';
		
		// gte regions cache file
		$regionsCacheFile = $cacheFolder . 'regions.json';
		
		// get hotels cache file
		$hotelsCacheFile = $cacheFolder . 'hotels.json';
		
		// get airports cache file
		$airportsCacheFile = $cacheFolder . 'airports.json';

		// get countries from file
		// $countriesFromFile = $this->getCountriesFromFile();

		// get cities from file (russia proxy)
		$citiesFromFile = $this->getCitiesFromFile();
		
		// get regions from file (russia proxy)
		$regionsFromFile = $this->getRegionsFromFile();
		
		// get hotels from file (russia proxy)
		$hotelsFromFile = $this->getHotelsFromFile();

		// get airports from file
		$airportsFromFile = $this->getAirportsFromFile();
		
		// get countries
		$countriesResp = $this->request('getTari');
		
		// load xml
		$countriesXml = simplexml_load_string($countriesResp);
		
		// exit if no countries
		if (!$countriesXml->DataSet->Body->Tari->Tara)
			return false;
		
		// init countries
		$countries = [];
		
		// init cities
		$cities = [];
		
		// init $regions
		$regions = [];
		
		// init hotels
		$hotels = [];

		// go through each country
		foreach ($countriesXml->DataSet->Body->Tari->Tara as $countryData)
		{
			// get country id and name
			$countryId = (string)$countryData['id'];
			
			// get cities for country
			$citiesResp = $this->request('getStatiuni', ['id_tara' => $countryId]);
			
			// load xml
			$citiesXml = simplexml_load_string($citiesResp);
			
			// continue if no city
			if (!$citiesXml->DataSet->Body->Statiuni->Statiune)
				continue;
			
			// init country external id
			$countryExternalId = false;
			
			// go through each city
			foreach ($citiesXml->DataSet->Body->Statiuni->Statiune as $cityData)
			{
				// get city id and name
				$cityId = (string)$cityData['id'];
				$cityName = (string)$cityData;
								
				// get hotel for each city
				$hotelsResp = $this->request('getHoteluri', ['id_statiune' => $cityId]);
				
				// load hotels xml
				$hotelsXml = simplexml_load_string($hotelsResp);

				// exit if no hoteluri
				if (!$hotelsXml->DataSet->Body->Hoteluri->Hotel)
					continue;
				
				// init city external id
				$cityExternalIds = [];
				
				// init region external id
				$regionExternalIds = [];
				
				// keep regions for cities
				$citiesRegions = [];

				// go trough each hotel
				foreach ($hotelsXml->DataSet->Body->Hoteluri->Hotel as $hotelData)
				{
					// hotel id
					$hotelId = (string)$hotelData['id'];
					
					// hotel id from proxy
					$hotelIdExtern = (string)$hotelData['id_extern'];
					
					if (!$hotelIdExtern)
						continue;
					
					if (!$hotelsFromFile[$hotelIdExtern])
						continue;
					
					//echo $hotelId . " | " . $hotelIdExtern . " | " . $hotelData["name"] . "<br/>";
					
					// match hotel with hotel from proxy
					$hotelItem = $hotelsFromFile[$hotelIdExtern];
					
					// index city ids
					$cityExternalIds[$hotelItem->CityId] = $hotelItem->CityId;

					// index county ids
					$regionExternalIds[$hotelItem->CountyId] = $hotelItem->CountyId;

					if (!isset($citiesRegions[$hotelItem->CityId]))
						$citiesRegions[$hotelItem->CityId] = [];
					
					$citiesRegions[$hotelItem->CityId][$hotelItem->CountyId] = $hotelItem->CountyId;
					
					if (!$countryExternalId)
						$countryExternalId = $hotelItem->CountryId;
					
					// map hotels id => external id
					$hotels[$hotelId] = [
						'ExternaId' => $hotelIdExtern,
						'CityId' => $hotelItem->CityId,
						'CountyId' => $hotelItem->CountyId,
						'CountryId' => $hotelItem->CountryId
					];
				}
				
				// go through each city indexed
				foreach ($cityExternalIds as $cityExternalId)
				{
					if ($citiesFromFile[$cityExternalId])
					{
						$regionIds = $citiesRegions[$cityExternalId];
						
						if (count($regionIds) > 1)
							throw new \Exception('A city cannot be in 2 regions!');
						
						$regionId = reset($regionIds);
						
						$cities[$cityExternalId] = [
							'Name' => $citiesFromFile[$cityExternalId]['Name'],
							'ExternalId' => $cityExternalId,
							'RegionId' => $regionId	
						];
					}
				}
				
				// go through each region
				foreach ($regionExternalIds as $regionExternalId)
				{
					if ($regionsFromFile[$regionExternalId])
					{
						$regions[$regionExternalId] = [
							'CountryId' => $countryId,
							'CountryExternalId' => $countryExternalId,
							'Name' => $regionsFromFile[$regionExternalId]->Name
						];
					}
				}
			}
			
			// add country id linked with country external id (from proxy)
			if ($countryExternalId)
				$countries[$countryId] = $countryExternalId;
		}
		
		// create cache files
		file_put_contents($countriesCacheFile, json_encode($countries));
		file_put_contents($citiesCacheFile, json_encode($cities));
		file_put_contents($regionsCacheFile, json_encode($regions));
		file_put_contents($hotelsCacheFile, json_encode($hotels));
		file_put_contents($airportsCacheFile, json_encode($airportsFromFile));
	}

	/* ----------------------------end plane tickets operators---------------------*/
	
	/**
	 * Get Files (Services)
	 * 
	 * @param type $serviceName
	 * @return boolean
	 */
	public function getFiles($serviceName = null)
	{
		// get api response
		if (!$this->_filesResp)
		{
			$this->_filesResp = $this->request('fileList');
		}
		
		//echo $this->_filesResp;
		//q_die();

		// load xml
		$filesXml = simplexml_load_string($this->_filesResp);
		
		// exit if no service
		if (!$filesXml->DataSet->Body->files->service)
			return false;
		
		// init services array
		$services = [];
		
		// go through each service
		foreach ($filesXml->DataSet->Body->files->service as $serviceFile)
		{
			// get service info
			$_serviceName = (string)$serviceFile->name;
			$_serviceUrl = (string)$serviceFile->url;
			#echo $_serviceName . " => " . $_serviceUrl . "<br/>";
			// index services url by name
			$services[$_serviceName] = $_serviceUrl;	
		}
		// return service
		if ($serviceName)
			return $services[$serviceName];
		// return all services
		else
			return $services;
	}

	/**
	 * Get cities
	 */
	public function getCitiesFromFile()
	{
		// get cities file
		$citiesFile = $this->getFiles('cities');
		
		// get file content
		$citiesContent = file_get_contents($citiesFile);
		
		// load xml
		$citiesXml = simplexml_load_string($citiesContent);
		
		// exit if no item
		if (!$citiesXml->item)
			return false;
		
		// init cities array
		$cities = [];
		
		// go through each item
		foreach ($citiesXml->item as $cityData)
		{			
			$cityId = (string)$cityData->id;
			$cityName = trim((string)$cityData->name);
			
			// indexed city by id
			$cities[$cityId] = [
				'Name' => $cityName,
				'RegionId' => trim((string)$cityData->region)
			];
		}
		
		// return cities
		return $cities;
	}
	
	public function getHotelsFromFile()
	{
		// get cities file
		$hotelsFile = $this->getFiles('hotels');
		
		// get file content
		$hotelsContent = file_get_contents($hotelsFile);
		
		// load xml
		$hotelsXml = simplexml_load_string($hotelsContent);
		
		// exit if no item
		if (!$hotelsXml->item)
			return false;
		
		$hotels = [];
		
		foreach ($hotelsXml->item as $hotelData)
		{
			$hotel = new \stdClass();
			$hotel->Id = (string)$hotelData->id;
			$hotel->Name = (string)$hotelData->name;
			
			// get hotel properties			
			$hotelProps = $hotelData->prop;
			
			// we have properties: Country, City, Region
			if ($hotelProps)
			{				
				// go though each property
				foreach ($hotelProps as $hotelProperty)
				{
					// get property name and value
					$propName = (string)$hotelProperty['name'];
					$propValue = (string)$hotelProperty;
					
					// set region
					if ($propName == 'Region')
						$hotel->CountyId = $propValue;
					
					// set country
					if ($propName == 'Country')
						$hotel->CountryId = $propValue;
					
					// set city
					if ($propName == 'City')
						$hotel->CityId = $propValue;
					
					if ($propName == 'type')
						$hotel->TypeId = $propValue;
				}
			}
			
			$hotels[$hotel->Id] = $hotel;
		}
		
		return $hotels;
	}
	
	public function getRegionsFromFile()
	{
		// get regions file
		$regionsFile = $this->getFiles('regions');
		
		// get content of the file
		$regionsContent = file_get_contents($regionsFile);
		
		// load xml
		$regionsXml = simplexml_load_string($regionsContent);
		
		// exit if no item
		if (!$regionsXml->item)
			return false;
		
		// init regions array
		$regions = [];
		
		// go through each region
		foreach ($regionsXml->item as $regionData)
		{
			// new region object
			$region = new \stdClass();
			$region->Id = (string)$regionData->id;
			$region->Name = (string)$regionData->name;
			// ---------- added ------------------
			$region->CountryExternalId = (string) $regionData->prop;
			// -----------------------------------
			
			// get regions property
			$regionProp = $regionData->prop;
			
			// get prop name and value
			$propName = (string)$regionProp['name'];
			$propValue = (string)$regionProp;
			
			// index regions by id
			$regions[$region->Id] = $region;
		}
		
		// return regions
		return $regions;
	}
	
	public function getCountriesFromFile()
	{
		// get countries file
		$countriesFile = $this->getFiles('countries');
		
		// get content of the file
		$countriesContent = file_get_contents($countriesFile);
		
		// load xml
		$countriesXml = simplexml_load_string($countriesContent);
		
		// exit if no countries item
		if (!$countriesXml->item)
			return false;
		
		// init countries
		$countries = [];
		
		// go thourgh each country
		foreach ($countriesXml->item as $countryItem)
		{
			$country = [
				'Id' => (string)$countryItem->id,
				'Name' => (string)$countryItem->name
			];
			
			// index countries by id
			$countries[$country['Id']] = $country;
		}
		
		// return countries
		return $countries;
	}

	public function getFlightsById(bool $force_request = false)
	{
		if (isset($this->cachedData['loadedFlightsById']))
			return $this->cachedData['loadedFlightsById'];	
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
		// get cache file
		$flightsCacheFile = $cacheFolder . 'cached_flights.json';
		$cf_last_modified = ($f_exists = file_exists($flightsCacheFile)) ? filemtime($flightsCacheFile) : null;
		$cache_time_limit = (time() - $this->cacheTimeLimit);

		// if exists - last modified
		if ((!$force_request) && ($f_exists) && ($cf_last_modified >= $cache_time_limit))
		{
			//return ($this->cachedData['loadedFlightsById'] = file_get_contents($flightsCacheFile));
			$flightsContent = file_get_contents($flightsCacheFile);
		}
		else
		{
			// get flights file
			$flightsFile = $this->getFiles('flights');
			// get content from file
			$flightsContent = file_get_contents($flightsFile);
			// file put contents
			file_put_contents($flightsCacheFile, $flightsContent);
		}

		// convert string to xml
		$flightsXml = simplexml_load_string($flightsContent);
		// flights data
		$flightsData = $this->simpleXML2Array($flightsXml);

		$flightsDataItems = $flightsData["item"];
		if ($flightsDataItems && isset($flightsDataItems["@attrs"]))
			$flightsDataItems = [$flightsDataItems];

		$this->cachedData['loadedFlightsById'] = [];
		foreach ($flightsDataItems ?: [] as $flightItm)
		{
			if (($flightItm["@attrs"]["type"] === "Flight") && $flightItm["id"])
			{
				$flightData = [];
				$flightItmProps = $flightItm["prop"];
				if ($flightItmProps && isset($flightItmProps["@attrs"]))
					$flightItmProps = [$flightItmProps];
				foreach ($flightItmProps ?: [] as $fp)
				{
					if (!($propName = $fp["@attrs"]["name"]))
						continue;
					$flightData[$propName] = $fp[0];
				}
				$this->cachedData['loadedFlightsById'][$flightItm["id"]] = $flightData;
			}
		}
		return $this->cachedData['loadedFlightsById'];
	}
	
	/**
	 * Get flights from file indexed by flight id. Skip flight that doesn't have departureAirport
	 * 
	 * @return boolean
	 */
	public function getFlightsFromFile()
	{
		// get flights file
		$flightsFile = $this->getFiles('flights');
		
		// get content from file
		$flightsContent = file_get_contents($flightsFile);
		
		// convert string to xml
		$flightsXml = simplexml_load_string($flightsContent);
		
		// exit if no items
		if (!$flightsXml->item)
			return false;
		
		// init flights
		$flights = [];
		
		// go through each flight item
		foreach ($flightsXml->item as $flightItem)
		{
			// new flight array
			$flight = [
				'Id' => (string)$flightItem->id
			];
			
			// skip flight if no properties
			if (!$flightItem->prop)
				continue;
			
			// init has departure airport
			$hasDepartureAirport = true;
			
			// got through each property of the flight item
			foreach ($flightItem->prop as $flightProp)
			{
				// get property name and value
				$flightPropName = (string)$flightProp['name'];
				$flightPropValue = (string)$flightProp;
				
				// has no departure airport
				if (($flightPropName == 'departureAirport') && !$flightPropValue)
					$hasDepartureAirport = false;
				
				// add property value and name to array of flights
				$flight[$flightPropName] = $flightPropValue;
			}
			
			// skip flights with no departure airport
			if (!$hasDepartureAirport)
				continue;
			
			// index flights by id
			$flights[$flight['Id']] = $flight;
		}
		
		// return flights
		return $flights;
	}
	
	/**
	 * Get airports from file
	 * 
	 * @return boolean
	 */
	public function getAirportsFromFile()
	{
		// get airports file
		$airportsFile = $this->getFiles('airports');
		
		// get content from file
		$airportsContent = file_get_contents($airportsFile);
		
		// load xml
		$airportsXml = simplexml_load_string($airportsContent);
		
		// exit if no items
		if (!$airportsXml->item)
			return false;
		
		// init airports
		$airports = [];
		
		// go through each airport item
		foreach ($airportsXml->item as $airportItem)
		{
			// new airport array
			$airport = [
				'Id' => (string)$airportItem->id,
				'Name' => (string)$airportItem->name
			];
			
			// go through each property of airport
			foreach ($airportItem->prop as $airportProp)
			{
				// get property name and value
				$airportPropName = (string)$airportProp['name'];
				$airportPropValue = (string)$airportProp;
				
				// add property name and value to airport array
				$airport[$airportPropName] = $airportPropValue;
			}
			
			$airports[$airport['Id']] = $airport;
		}
		// return airports
		return $airports;
	}

	public function getFlightDepartures($all = false, $force = false)
	{
		$cacheFolder = $this->getResourcesDir();
		$getFlightDepartures_cacheFile = $cacheFolder . "flight_departures.xml";
		
		# $getFlightDepartures_cacheFile
		q_remote_log_sub_entry([
				[
					'Timestamp_ms' => (string)microtime(true),
					'Tags' => ['tag' => 'teztour::getFlightDepartures',],
					# 'Is_Error' => $is_error ? true : null,
					'Traces' => (new \Exception())->getTraceAsString(),
					'Data' => ['$getFlightDepartures_cacheFile' => $getFlightDepartures_cacheFile,],
				]
			]);
		
		if ((!file_exists($getFlightDepartures_cacheFile)) || $force)
		{
			$flightDeparturesContent = file_get_contents(($flightDeparturesFile = $this->getFiles('flightDepartures')));
			file_put_contents($getFlightDepartures_cacheFile, $flightDeparturesContent);
		}
		else
			$flightDeparturesContent = file_get_contents($getFlightDepartures_cacheFile);

		$flightDeparturesXml = simplexml_load_string($flightDeparturesContent);
		if (!$flightDeparturesXml->item)
			return false;
		$flightDepartures = [];

		foreach ($flightDeparturesXml->item as $flightDeparturesItem)
		{
			$flightDeparture = [
				'Id' => (string)$flightDeparturesItem->id
			];
			
			if (!$flightDeparturesItem->prop)
				continue;
			
			$isActive = true;
			
			foreach ($flightDeparturesItem->prop as $flightDeparturesItemProp)
			{
				$flightDeparturesItemPropName = (string)$flightDeparturesItemProp['name'];
				$flightDeparturesItemPropValue = (string)$flightDeparturesItemProp;
				
				if (($flightDeparturesItemPropName == 'active') && ($flightDeparturesItemPropValue == 'false'))
					$isActive = false;	
				$flightDeparture[$flightDeparturesItemPropName] = $flightDeparturesItemPropValue;
			}
			if (!$isActive && !$all)
				continue;
			$flightDeparture["IsActive"] = $isActive;
			$flightDepartures[$flightDeparture['Id']] = $flightDeparture;
		}
		
		// return flight departures
		return $flightDepartures;
	}

	public function getSeatTypesFromFile($force = false)
	{
		$seatTypesFile = $this->getFiles('seatSets');
		
		// skip proxy and get file from teztour
		// $seatTypesContent = file_get_contents('http://xml.tez-tour.com/xmlgate/list/seatSets.xml');
		
		$cacheFolder = $this->getResourcesDir();
		$getFlightDepartures_cacheFile = $cacheFolder . "seatSets_" . md5($seatTypesFile) . ".xml";
		if ((!file_exists($getFlightDepartures_cacheFile)) || $force)
		{
			$seatTypesContent = file_get_contents($seatTypesFile);
			file_put_contents($getFlightDepartures_cacheFile, $seatTypesContent);
		}
		else 
			$seatTypesContent = file_get_contents($getFlightDepartures_cacheFile);

		$seatTypesXml = simplexml_load_string($seatTypesContent);
		
		if (!$seatTypesXml->item)
			return false;
		
		$seatTypes = [];
		
		foreach ($seatTypesXml->item as $seatTypeItem)
		{
			$seatType = [
				'Id' => (string)$seatTypeItem->id
			];
			
			if (!$seatTypeItem->prop)
				continue;
			
			foreach ($seatTypeItem->prop as $seatTypeItemProp)
			{
				$seatTypeItemPropName = (string)$seatTypeItemProp['name'];
				$seatTypeItemPropValue = (string)$seatTypeItemProp;
				
				$seatType[$seatTypeItemPropName] = $seatTypeItemPropValue;
			}
			
			$seatTypes[$seatType['Id']] = $seatType;
		}
		
		// return seat types
		return $seatTypes;
	}
	
	public function getGendersFromFile()
	{
		$gendersContent = file_get_contents('http://xml.tez-tour.com/xmlgate/list/genders.xml');
		
		$gendersXml = simplexml_load_string($gendersContent);
		
		if (!$gendersXml->item)
			return false;
		
		$genders = [];
		
		foreach ($gendersXml->item as $genderItem)
		{
			$gender = [
				'Id' => (string)$genderItem->id,
				'Name' => (string)$genderItem->name
			];	
			$genders[$gender['Name']] = $gender;
		}
		// return genders
		return $genders;
	}
	/**
	 * @param array $aviaRefFlight
	 * @return boolean
	 */
	public function isAviaFlightAvailable($aviaRefFlight)
	{
		return ($aviaRefFlight && 
			(
				$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberF']) || 
				$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberB']) ||
				$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberC']) ||
				$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberE'])
			));
	}
	/**
	 * @param string $freeSeatNumber
	 * @return boolean
	 */
	public function isAviaFlightAvailable_checkField($freeSeatNumber)
	{
		return (is_numeric($freeSeatNumber) || (in_array($freeSeatNumber, $this->freeSeatsStatuses)));
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
		if (!($destination = ($filter["regionId"] ?: $filter["countryId"])))
			throw new \Exception("Region is mandatory!");

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
		return $serviceType;
	}
	
	/**
	 * Get individual offers
	 * 
	 * @param array $filter
	 * @return type
	 * @throws \Exception
	 */
	public function getIndividualOffers(array $filter = null)
	{
		$_t1 = microtime(true);
	
		if (!$filter['regionId'])
			throw new \Exception('Missing region id!');

		// get checkin
		$checkIn = $filter["checkIn"];
		
		// calculate checkout date
		$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));
		
		// get only first room
		$roomFilter = reset($filter['rooms']);

		$accommodationId = $this->getAccommodationId($roomFilter);

		if (!$accommodationId)
			throw new \Exception('Missing accomodation id!');

		// first setup departure city - this is for individual only (id of Bucharest city)
		if (!$filter['departureCity'])
			$filter['departureCity'] = '9001185';
		
		// get min accepted hotel category
		$minAcceptedHotelCategory = $this->getMinAcceptableCategory($filter);

		// error if no hotel category
		if (!$minAcceptedHotelCategory)
			throw new \Exception('Missing hotel category!');

		// setup params
		$params = [
			'xml'				=> 'true',
			'locale'			=> 'ro',
			'tariffsearch'		=> 1,
			'currency'			=> static::$Currency,
			'cityId'			=> (int)$filter['departureCity'],		// id of the departure city
			'countryId'			=> (int)$filter['countryId'],			// id of destination country
			'after'				=> date('d.m.Y', strtotime($filter['checkIn'])),
			'before'			=> date('d.m.Y', strtotime($filter['checkIn'])),
			'nightsMin'			=> (int)$filter['days'],
			'nightsMax'			=> (int)$filter['days'],
			'priceMin'			=> 0,
			'priceMax'			=> 999999,
			//'priceMax'			=> 13300,
			'tourType'			=> 3, // for individual
			'accommodationId'	=> $accommodationId,
			'hotelClassId'		=> (int)$minAcceptedHotelCategory['Id'],	// min accepted hotel category
			'hotelClassBetter'	=> true,							// min accepted hotel category and better
			'hotelInStop'		=> true,							// also hotels with unavailable rooms
			'tourId'			=> (int)$filter['regionId']				// id of the region
		];

		for ($i = 0; $i < $roomFilter["children"]; $i++)
		{
			$childPos = $i + 1;
			$childAge = $roomFilter["childrenAges"][$i];
			$params["birthday" . $childPos] = date("d.m.Y", strtotime("- " . $childAge . " years"));
		}

		if ($filter["travelItemId"])
			$params["hotelId"] = (int)$filter["travelItemId"];

		$minRAndBs = $this->getMinRAndBs($filter);
		if ($minRAndBs && $minRAndBs["Id"])
			$params["rAndBId"] = $minRAndBs["Id"];

		$inRawRequest = isset($filter["rawResponse"]);
		$rawOffers = [];

		// init finished with false
		$finished = false;
		// init count
		$count = 0;
		$offerXmlItems = [];
		
		$lastPriceMin = null;
		// search until no more items
		while (!$finished)
		{
			// increase count
			$count++;

			if ($count > $this->offsLimit)
			{
				//echo "<div style='color: red;'>100 steps done</div>";
				break;
			}

			// get response
			$offersResp = $this->requestOffers($params, $filter);

			try 
			{
				// load xml from string
				$offersXml = simplexml_load_string($offersResp);
			}
			catch (\Exception $ex)
			{
				//echo "<div style='color: red;'>Cannot load response as xml: terminate get offers loop</div>";
				//throw $ex;
				# @TODO - we should log this
				$finished = true;
				continue;
			}

			if ($offersXml === false)
			{
				//throw new \Exception("Something went wrong!");
				# @TODO - we should log this
				//echo "<div style='color: red;'>No Offers XML: terminate get offers loop</div>";
				$finished = true;
				continue;
			}

			// exit if no item
			if (!$offersXml->data->item)
			{
				//echo "<div style='color: red;'>No offers data item: terminate get offers loop</div>";
				break;
			}

			if ($inRawRequest)
				$rawOffers[] = $this->simpleXML2Array($offersXml);

			foreach ($offersXml->data->item as $offerXmlItem)
			{
				$offerXmlItems[] = $offerXmlItem;
			}

			if (($offersXmlArray = (array)$offersXml->data) && ($lastOfferXml = end($offersXmlArray['item'])) && ($price = (float)$lastOfferXml->price->total))
			{
				// increase price min and do the rest of requests
				$params['priceMin'] = floor($price);
				
				if ($lastPriceMin === $params['priceMin'])
				{
					$finished = true;
					//echo "<div style='color: red;'>Same price min detected (break to avoid recurssion)</div>";
					break;
				}
				$lastPriceMin = $params['priceMin'];
			}
			else
			{
				$finished = true;
				//echo "<div style='color: red;'>Cannot determine min pricing: terminate get offers loop</div>";
				break;
			}
		}
		
		// if in raw request
		if ($inRawRequest)	
		{
			$byHotelResp = [];
			foreach ($rawOffers ?: [] as $rawOffers_c)
			{
				if (isset($rawOffers_c["data"]["item"]["hotel"]))
					$rawOffers_c["data"]["item"] = [$rawOffers_c["data"]["item"]];
				foreach ($rawOffers_c["data"]["item"] ?: [] as $itm)
				{
					$hotelIndx = $itm["hotel"]["id"] . "|" . $itm["hotel"]["name"];
					if (!isset($byHotelResp[$hotelIndx]))
						$byHotelResp[$hotelIndx] = ["Offers" => [], "Hotel" => $itm["hotel"]];
					$byHotelResp[$hotelIndx]["Offers"][] = $itm;
				}
			}
			return [$byHotelResp, $rawOffers];
		}

		// init hotels
		$hotels = [];

		$indexedHotels = [];

		$eoffs = [];

		$medialInsurenceFileName = static::$MedicalInsurenceFile;
		$medicalEnsurenceFile = "uploads/util/" . $medialInsurenceFileName;
		if (!file_exists($medicalEnsurenceFile) && defined('TRAVELFUSE_WEB_PATH'))
			$medicalEnsurenceFile = rtrim(TRAVELFUSE_WEB_PATH, "\\/") . "/res/docs/" . $medialInsurenceFileName;

		foreach ($offerXmlItems as $offerXmlItem)
		{
			//echo $offerXmlItem->asXml() . "\n-------------------------------------------------------\n";
			// get hotel from offer
			$offerXmlHotel = $offerXmlItem->hotel;

			// get hotel id
			$offerXmlHotelId = (string)$offerXmlHotel->id;

			// ignore offers with no hotel code
			if ((!($hotelCode = trim($offerXmlHotelId))) || ($filter["TravelItemId"] && ($filter["TravelItemId"] !== $hotelCode)))
				continue;

			// init hotel object
			$hotel = $indexedHotels[$hotelCode] ?: ($indexedHotels[$hotelCode] = new \stdClass());

			// set hotel id as the code from sejour
			$hotel->Id = $hotelCode;
			$hotel->Name = (string)$offerXmlHotel->name;

			//echo "<div style='color: green;'>[{$hotel->Id}] - {$hotel->Name}</div>";

			$re = preg_match_all("/(\\d+)\\s*(?:\\+?)\\s*\\*+\\s*/uis", trim($hotel->Name), $matches);

			if ($matches && count($matches > 0))
			{
				if ($matches[1] && (count($matches) > 1))
					$hotel->Stars = $matches[1][0];
			}
			
			// get region xml
			$offerXmlRegion = $offerXmlItem->region;
			$offerXmlRegionId	= (string)$offerXmlRegion->id;
			
			// get meal xml (pansion)
			$offerXmlPansion = $offerXmlItem->pansion;
			$offerXmlPansionId = (string)$offerXmlPansion->id;
			$offerXmlPansionName = (string)$offerXmlPansion->name;
			
			$offerXmlRoomType = $offerXmlItem->hotelRoomType;
			$offerXmlRoomTypeId = (string)$offerXmlRoomType->id;
			$offerXmlRoomTypeName = (string)$offerXmlRoomType->name;
			
			$offerXmlAgeGroupType = $offerXmlItem->ageGroupType;
			$offerXmlAgeGroupTypeId = (string)$offerXmlAgeGroupType->id;
			
			// get xml early booking
			$offerXmlEarlyBookingObj = $offerXmlItem->icons->earlyBooking;
			$offerXmlEarlyBookingValue = (string)$offerXmlEarlyBookingObj->value;
			
			$isEarlyBooking = 0;
			if ($offerXmlEarlyBookingValue == 'true')
				$isEarlyBooking = 1;
			
			// setup offer code
			//$offerCode = $hotel->Id . '~' . $offerXmlRegionId . '~' . $offerXmlRoomTypeId . '~' 
			//	. $offerXmlPansionId . '~' . $offerXmlAgeGroupTypeId . '~' . $isEarlyBooking . '~' . $filter['checkIn'] . '~' . $checkOut;

			//echo $offerXmlItem->asXml() . "\n------------------------------------------------------\n";

			//$bookingUrlIdf = md5($offerXmlItem->bookingUrl->bookingUrl->url . "");
			$bookingUrlIdf = "";
			$offerCode = $bookingUrlIdf . "~" . $hotel->Id . '~' . $offerXmlRegionId . '~' . $offerXmlRoomTypeId . '~' 
				. $offerXmlPansionId . '~' . $offerXmlAgeGroupTypeId . '~' . $filter['checkIn'] . '~' . $checkOut;

			$offerXmlPriceObj = $offerXmlItem->price;
			$offerXmlPriceTotalPrice = (float)$offerXmlPriceObj->total;
			$offerXmlPriceCurrency = (string)$offerXmlPriceObj->currency;

			if (($alreadyProcessedOffer = $eoffs[$offerCode]))
			{
				if ($alreadyProcessedOffer->Price > $offerXmlPriceTotalPrice)
				{
					$alreadyProcessedOffer->Price = $offerXmlPriceTotalPrice;
					$alreadyProcessedOffer->IsEarlyBooking = $isEarlyBooking;
				}

				if ($alreadyProcessedOffer->InitialPrice < $offerXmlPriceTotalPrice)
					$alreadyProcessedOffer->InitialPrice = $offerXmlPriceTotalPrice;

				continue;
			}

			$offer = ($eoffs[$offerCode] = new \stdClass());
			$offer->IsEarlyBooking = $isEarlyBooking;
			$offer->Code = $offerCode;
			
			
			$offerXmlPriceInsurancePrice = (string)$offerXmlPriceObj->insurance;
			$offerXmlPriceOtherPrice = (string)$offerXmlPriceObj->other;
			
			// set offer currency
			$offer->Currency = new \stdClass();
			$offer->Currency->Code = ($offerXmlPriceCurrency == 'EUR') ? 'EUR' : $offerXmlPriceCurrency;
			
			// net price
			$offer->Net = (float)$offerXmlPriceTotalPrice;
			
			if ($offerXmlPriceInsurancePrice)
				throw new \Exception('We have insurance price');
			
			if ($offerXmlPriceOtherPrice)
				throw new \Exception('We have other price');
			
			// offer total price
			$offer->Gross = (float)$offerXmlPriceTotalPrice;

			// get initial price
			$offer->InitialPrice = $offer->Gross;
			
			// get availability :: Ok, Wait, Stop
			$offer->Availability = ((string)$offerXmlItem->existsRoom == 'true') ? 'yes' : 'no';

			// number of days needed for booking process
			$offer->Days = $filter['days'];
			
			// room
			$roomType = new \stdClass();
			$roomType->Id = $offerXmlRoomTypeId;
			$roomType->Title = $offerXmlRoomTypeName;
			
			$roomMerch = new \stdClass();
			$roomMerch->Title = $offerXmlRoomTypeName;
			$roomMerch->Type = $roomType;
			
			$roomItm = new \stdClass();
			$roomItm->Merch = $roomMerch;
			//$roomItm->Id = $offerXmlRoomTypeId;
			
			//required for indexing
			$roomItm->Code = $offerXmlRoomTypeId;
			$roomItm->CheckinAfter = $filter['checkIn'];
			$roomItm->CheckinBefore = $checkOut;
			$roomItm->Currency = $offer->Currency;
			$roomItm->Quantity = 1;
			$roomItm->Availability = $offer->Availability;

			if ($offer->IsEarlyBooking)
				$roomItm->InfoTitle = 'Early booking';
			
			if (!$offer->Rooms)
				$offer->Rooms = [];

			$offer->Rooms[] = $roomItm;

			// board
			$boardType = new \stdClass();
			$boardType->Id = $offerXmlPansionId;
			$boardType->Title = $offerXmlPansionName;

			$boardMerch = new \stdClass();
			//$boardMerch->Id = $offerXmlPansionId;
			$boardMerch->Title = $offerXmlPansionName;
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
			
			if (!$offerXmlPansionId)
				$offer->MealItem = $this->getBoardItem($offer);
			else
				$offer->MealItem = $boardItm;
			
			if (!$offer->Items)
				$offer->Items = [];

			/*
			$new_item = new \stdClass();
			$new_item->Code = 'medical_insurance';
			$new_item->Quantity = 1;
			$new_item->Document = $medicalEnsurenceFile;
			$new_item->UnitPrice = 0;
			$new_item->Currency = $offer->Currency;
			$new_item->Merch = new \stdClass();
			$new_item->Merch->Title = pathinfo(static::$MedicalInsurenceFile, PATHINFO_FILENAME);
			$new_item->Merch->Code = $new_item->Code;
			$new_item->Merch->Category = new \stdClass();
			$new_item->Merch->Category->Code = "MI";
			$new_item->Merch->Category->Name = "Medical Insurence";

			$offer->Items[] = $new_item;
			*/

			// departure transport item
			$departureTransportMerch = new \stdClass();
			$departureTransportMerch->Title = "CheckIn: ".($filter['checkIn'] ? date("d.m.Y", strtotime($filter['checkIn'])) : "");

			$departureTransportItm = new \stdClass();
			$departureTransportItm->Merch = $departureTransportMerch;
			$departureTransportItm->Quantity = 1;
			$departureTransportItm->Currency = $offer->Currency;
			$departureTransportItm->UnitPrice = 0;
			$departureTransportItm->Gross = 0;
			$departureTransportItm->Net = 0;
			$departureTransportItm->InitialPrice = 0;
			$departureTransportItm->DepartureDate = $filter['checkIn'];
			$departureTransportItm->ArrivalDate = $filter['checkIn'];

			// for identify purpose
			$departureTransportItm->Id = $departureTransportMerch->Id;

			// return transport item
			$returnTransportMerch = new \stdClass();
			$returnTransportMerch->Title = "CheckOut: ".($checkOut ? date("d.m.Y", strtotime($checkOut)) : "");

			$returnTransportItm = new \stdClass();
			$returnTransportItm->Merch = $returnTransportMerch;
			$returnTransportItm->Quantity = 1;
			$returnTransportItm->Currency = $offer->Currency;
			$returnTransportItm->UnitPrice = 0;
			$returnTransportItm->Gross = 0;
			$returnTransportItm->Net = 0;
			$returnTransportItm->InitialPrice = 0;
			$returnTransportItm->DepartureDate = $checkOut;
			$returnTransportItm->ArrivalDate = $checkOut;
			// for identify purpose
			$returnTransportItm->Id = $returnTransportMerch->Id;
			$departureTransportItm->Return = $returnTransportItm;

			// add items to offer
			$offer->Item = $roomItm;
			$offer->DepartureTransportItem = $departureTransportItm;
			$offer->ReturnTransportItem = $returnTransportItm;

			if (isset($hotel->Offers[$offer->Code]))
			{

			}

			$hotel->Offers[$offer->Code] = $offer;
			
			$hotels[$hotel->Id] = $hotel;
		}

		// return hotels
		return $hotels;
	}
	
	public function api_getChartersRequests()
	{
		list($teztourCountries) = $this->api_getCountries();
		$roCountry = null;
		foreach ($teztourCountries ?: [] as $tezCountry)
		{
			if ($tezCountry->Code == "RO")
			{
				$roCountry = $tezCountry;
				break;
			}
		}

		if (!$roCountry)
			throw new \Exception("Romania country not found!");

		list($roCities) = $this->api_getCities(["countryId" => $roCountry->Id]);

		$requests = [];
		foreach ($roCities ?: [] as $roCity)
		{
			foreach ($teztourCountries ?: [] as $tez_country)
			{
				$ret = $this->requestDepartureDates("", ['cityId' => $roCity->Id, 'countryId' => $tez_country->Id, 
								'formatResult' => 'true', 'locale' => 'ro', 'xml' => 'false']);

				$ret_data = json_decode($ret, true);

				if (!isset($ret_data['data']))
					continue;

				$transport_dates = [];
				foreach ($ret_data['data'] ?: [] as $r_data)
				{
					$year = $r_data[0];
					$months_dates = [];
					for ($i = 1; $i <= 12; $i++)
					{
						foreach ($r_data[$i] ?: [] as $r_day)
						{
							$date = $year.'-'.str_pad($i, 2, "0", STR_PAD_LEFT).'-'.str_pad($r_day, 2, "0", STR_PAD_LEFT);
							$transport_dates[$date] = $date;
						}
					}
				}
				ksort($transport_dates);

				foreach ($transport_dates ?: [] as $date)
				{
					$request1 = (object)[
						"TransportType" => "plane",
						"DepartureIndex" => $roCity->Id,
						"DestinationIndex" => $tez_country->Id,
						"DepartureDate" => $date,
						"Duration" => 3
					];

					$request2 = (object)[
						"TransportType" => "plane",
						"DepartureIndex" => $roCity->Id,
						"DestinationIndex" => $tez_country->Id,
						"DepartureDate" => $date,
						"Duration" => 12
					];
					
					$request3 = (object)[
						"TransportType" => "plane",
						"DepartureIndex" => $roCity->Id,
						"DestinationIndex" => $tez_country->Id,
						"DepartureDate" => $date,
						"Duration" => 20
					];

					$requests[] = $request1;
					$requests[] = $request2;
					$requests[] = $request3;
				}
			}
		}
		return $requests;
	}

	public function getCharterOffers(array $filter = null)
	{
		$t1 = microtime(true);
		$tinit = microtime(true);
		$doLogging = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle])) || $_GET['tez_charters_debug'];
		$doDebug = $_GET['tez_charters_debug'] ? true : false;
		
		# $doDebug = true;
		# $doLogging = true;
		$log_data = [];
		
		if ($doDebug)
		{
			qvar_dump('getCharterOffers::$filter', $filter);
		}
		
		if (!($destination = ($filter['regionId'] ?: $filter['countryId'])))
		{
			$ex = new \Exception('Missing region id!');
			#if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}

		if (!($filter['departureCity']))
		{
			$ex = new \Exception('Missing departure city!');
			#if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}

		$_ttmp = microtime(true);
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
				
		if (!is_dir($cacheFolder))
		{
			$this->mapData();
			//throw new \Exception('Missing cache folder!');
		}
		if ($doDebug)
		{
			var_dump("map data took: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}
		if (!is_dir($cacheFolder))
		{
			$ex = new \Exception('Missing cache folder!');
			#if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}

		// get checkin
		$checkIn = $filter["checkIn"];

		// calculate checkout date
		$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

		// get only first room
		$roomFilter = reset($filter['rooms']);

		$_ttmp = microtime(true);
		$accommodationId = $this->getAccommodationId($roomFilter);
		
		if ($doDebug)
		{
			var_dump("getting accom id took: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}

		if (!$accommodationId)
		{
			$ex = new \Exception("Accomodation id not returned!");
			#if ($doLogging)
				$this->logError(["filter" => $filter, "roomFilter" => $roomFilter], $ex);
			throw $ex;
		}
		
		// get min accepted hotel category
		$_ttmp = microtime(true);
		$minAcceptedHotelCategory = $this->getMinAcceptableCategory($filter);
		
		if ($doDebug)
		{
			var_dump("getting min accepted hotels category: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}

		// error if no hotel category
		if (!$minAcceptedHotelCategory)
		{
			$ex = new \Exception('Missing hotel category!');
			if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}

		$_ttmp = microtime(true);
		// get cities
		$citiesFromFile = $this->getCitiesFromFile();
		
		if ($doDebug)
		{
			var_dump("getting cities from file took: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}
		
		if ((!($depCity = $citiesFromFile[$filter['departureCity']])) || (!($depCityID = $depCity['RegionId'])))
		{
			$ex = new \Exception("Departure city not found in cities file!");
			#if ($doLogging)
				$this->logError(["filter" => $filter, "roomFilter" => $roomFilter], $ex);
			throw $ex;
		}

		// get airports cache file
		$airportsCacheFile = $cacheFolder . 'airports.json';
		
		// exception if no airports cache file
		if (!file_exists($airportsCacheFile))
		{
			$ex = new \Exception('Missing airports cache file');
			#if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}
		
		$_ttmp = microtime(true);
		// get airports mapped data
		$airportsMappedDataJSON = file_get_contents($airportsCacheFile);

		if ($doDebug)
		{
			var_dump("load airports took: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}

		// get flight departures from file
		$flightDeparturesFromFile = $this->getFlightDepartures(true, true);
		
		q_remote_log_sub_entry([
					[
						'Timestamp_ms' => (string)microtime(true),
						'Tags' => ['tag' => 'teztour-getCharterOffers-getFlightDepartures',],
						'Traces' => ($ex ?? new \Exception())->getTraceAsString(),
						'Data' => ['$flightDeparturesFromFile' => ($flightDeparturesFromFile ? 'yes' : 'no')],
					]
				]);
		
		if (empty($flightDeparturesFromFile))
		{
			$ex = new \Exception('Missing departure flights!');
			#if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}

		$_ttmp = microtime(true);
		// decode json
		$airportsMappedData = json_decode($airportsMappedDataJSON, true);
		
		if ($doDebug)
		{
			var_dump("load airports as object took: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}

		$airportsByRegion = [];
		foreach ($airportsMappedData ?: [] as $airport)
			$airportsByRegion[$airport["Region"]][$airport["Id"]] = $airport;

		if (!($depCityAirports = $airportsByRegion[$depCityID]))
		{
			$ex = new \Exception('Missing departure airports');
			#if ($doLogging)
				$this->logError(["filter" => $filter], $ex);
			throw $ex;
		}
		
		if (!is_array($filter["days"]))
			$filter["days"] = [$filter["days"], $filter["days"]];

		list($nightsMin, $nightsMax) = $filter['days'];

		$params = [
			'xml' => 'true',
			'locale' => 'ro',
			'tariffsearch' => 1,
			'cityId' => (int)$filter['departureCity'], // Bucuresti - doesn't matter for individual
			'countryId' => (int)$filter['countryId'],
			'after' => date('d.m.Y', strtotime($filter['checkIn'])),
			'before' => date('d.m.Y', strtotime($filter['checkIn'])),
			'nightsMin' => (int)$nightsMin,
			'nightsMax' => (int)$nightsMax,
			'priceMin' => 0,
			'priceMax' => 999999,
			'tourType' => 1, // for charters
			'currency' => static::$Currency,
			'accommodationId' => (int)$accommodationId,
			'hotelClassId'		=> (int)$minAcceptedHotelCategory['Id'],	// min accepted hotel category
			'hotelClassBetter'	=> true,							// min accepted hotel category and better
			'hotelInStop' => true, // also hotels with unavailable rooms	
		];

		for ($i = 0; $i < $roomFilter["children"]; $i++)
		{
			$childPos = $i + 1;
			$childAge = $roomFilter["childrenAges"][$i];
			$params["birthday" . $childPos] = date("d.m.Y", strtotime("- " . $childAge . " years"));
		}

		if ($filter['regionId'])
			$params["tourId"] = $filter['regionId'];
		else if ($filter["countryId"])
			$params["countryId"] = $filter['countryId'];

		if ($filter["travelItemId"])
			$params["hotelId"] = $filter["travelItemId"];
		
		$minRAndBs = $this->getMinRAndBs($filter);
		if ($minRAndBs && $minRAndBs["Id"])
			$params["rAndBId"] = $minRAndBs["Id"];

		$_ttmp = microtime(true);
		// get flights departures
		#$flightDeparturesFromFile = $this->getFlightDepartures();
		if ($doDebug)
		{
			var_dump("get flights departures: " . (microtime(true) - $_ttmp) . " seconds");
			echo "<br/>";
		}

		$_ttmp = microtime(true);
		// get seat types - force it when the request is done for a specific hotel - it will refresh the left number for the flight
		$seatTypesFromFile = $this->getSeatTypesFromFile((!empty($filter["travelItemId"])) || $filter["_in_resync_request"]);

		$_ttmp = microtime(true);
		
		$seatTypesFromFile_byFlightIDs = [];
		foreach ($seatTypesFromFile ?: [] as $stffId => $stffData)
		{
			//echo $stffId . " => " . json_encode($stffData) . "<hr/>";
			$seatTypesFromFile_byFlightIDs[$stffData["FlightDeparture"]][$stffId] = $stffData;
		}
		
		// in raw request
		$inRawRequest = isset($filter["rawResponse"]);
		$rawOffers = [];
		$finished = false;
		$count = 0;
		$offerXmlItems = [];
		$lastPriceMin = null;
		$priceMinRepeated = 0;
		// search until no more items
		$rawRequestProcessSteps = [];
		
		while (!$finished)
		{
			// increase count
			$count++;

			if ($count > $this->offsLimit)
			{
				$rawRequestProcessSteps[] = "100 steps done";
				break;
			}

			$_ttmp = microtime(true);
			// get response
			$offersResp = $this->requestOffers($params, $filter, $doLogging);
			
			if ($doDebug)
			{
				var_dump("get offers step [{$count}]: " . (microtime(true) - $_ttmp) . " seconds");
				echo "<br/>";
			}


			$req_url = 'http://www.travelio.ro/proxy-tez/?' . q_url_encode($params);
			$rawRequestProcessSteps[] = $req_url;

			try
			{
				$offersXml = simplexml_load_string($offersResp);
				if ($offersXml === false)
					# try adding xml
					$offersXml = simplexml_load_string('<?xml version="1.0"?>'.$offersResp);
				
				if ($doDebug)
				{
					if ($offersXml !== false)
					{
						echo "XML looks ok!\n";
					}
				}
			}
			catch (\Exception $ex)
			{
				if (strlen($offersResp) > 0)
				{
					try 
					{
						$tryIT = '<?xml version="1.0"?><testResp>'  . $offersResp . '</testResp>';
						$offersTestXml = simplexml_load_string($tryIT);
						if ($offersTestXml && ($offersTestXmlDecoded = $this->simpleXML2Array($offersTestXml)) && isset($offersTestXmlDecoded["success"]) 
							&& (!filter_var($offersTestXmlDecoded["success"], FILTER_VALIDATE_BOOLEAN)))
						{
							$ex = new \Exception($offersTestXmlDecoded["message"]);
							#if ($doLogging)
								$this->logError(["\$offersTestXmlDecoded" => $offersTestXmlDecoded, "\$req_url" =>$req_url, "\$filter" => $filter], $ex);
							throw $ex;
						}
					}
					catch (\Exception $ex) {
						$rawRequestProcessSteps[] = "Teztour Error: " . $ex->getMessage();
					}
				}
				$rawRequestProcessSteps[] = "Cannot load response as xml: terminate get offers loop";
				//throw $ex;
				# @TODO - we should log this
				$finished = true;
				continue;
			}

			if ($offersXml === false)
			{
				//throw new \Exception("Something went wrong!");
				# @TODO - we should log this
				$rawRequestProcessSteps[] = "No Offers XML: terminate get offers loop";
				$finished = true;
				continue;
			}
			
			if ($doDebug)
			{
				qvardump("offs at pos [{$count}]", $this->simpleXML2Array($offersXml));
			}

			if (!($offersXml->data->item))
			{
				$rawRequestProcessSteps[] = "No offers data item: terminate get offers loop";
				break;
			}

			if ($inRawRequest)
				$rawOffers[] = $this->simpleXML2Array($offersXml);

			foreach ($offersXml->data->item as $offerXmlItem)
				$offerXmlItems[] = $offerXmlItem;
			
			$offersXmlItms = ($offersXmlArray = (array)$offersXml->data) ? $offersXmlArray['item'] : null;
			if ($offersXmlItms && is_object($offersXmlItms) && isset($offersXmlItms->hotel))
				$offersXmlItms = [$offersXmlItms];
			$lastOfferXml = $offersXmlItms ? end($offersXmlItms) : null;

			if ($lastOfferXml && ($price = (float)$lastOfferXml->price->total))
			{
				// increase price min and do the rest of requests
				$params['priceMin'] = floor($price);
				if ($lastPriceMin === $params['priceMin'])
				{
					$params['priceMin'] = $params['priceMin'] + 1;
					$priceMinRepeated++;
					if ($priceMinRepeated > 3)
					{
						$finished = true;
						$rawRequestProcessSteps[] = "Same price min detected (break to avoid recurssion)";
						break;
					}
				}
				else
					$priceMinRepeated = 0;
				$lastPriceMin = $params['priceMin'];
			}
			else
			{
				$rawRequestProcessSteps[] = "Cannot determine min pricing: terminate get offers loop";
				break;
			}
		}
		
		// if in raw request
		if ($inRawRequest)
		{
			$byHotelResp = [];
			$departureFlightsSets = [];
			$returnFlightsSets = [];
			foreach ($rawOffers ?: [] as $rawOffers_c)
			{
				if (isset($rawOffers_c["data"]["item"]["hotel"]))
					$rawOffers_c["data"]["item"] = [$rawOffers_c["data"]["item"]];		
				foreach ($rawOffers_c["data"]["item"] ?: [] as $itm)
				{
					$offerCheckIn = implode("-", array_reverse(explode(".", $itm["checkIn"])));
					$offerNightsCount = $itm["nightCount"];
					$offerCheckIn = implode("-", array_reverse(explode(".", $offerCheckIn)));
					$offerCheckOut = date("Y-m-d", strtotime("+" . $offerNightsCount . " days", strtotime($offerCheckIn)));
					$hotelIndx = $itm["hotel"]["id"] . "|" . $itm["hotel"]["name"];
					if (!isset($byHotelResp[$hotelIndx]))
						$byHotelResp[$hotelIndx] = ["Offers" => [], "Hotel" => $itm["hotel"]];
					$byHotelResp[$hotelIndx]["Offers"][] = $itm;
					if ($itm["region"]["resortArrivalRegionId"] && $offerCheckIn && $offerCheckOut)
					{
						if (!($possibleArrivalAirports = $airportsByRegion[$itm["region"]["resortArrivalRegionId"]]))
						{
							try
							{
								throw new \Exception("Possible arrival airports not found!");
							} catch (\Exception $ex) {
								$this->logError(["\$offerXmlItem" => $offerXmlItem], $ex);
							}
						}
						$departureFlightsSetsIndx = json_encode(array_keys($depCityAirports)) . "-" . json_encode(array_keys($possibleArrivalAirports)) . "-" . $offerCheckIn;
						$ff_cf_dep = $cacheFolder . "avia_flight_" . md5($departureFlightsSetsIndx) . ".json";
						if (isset(static::$CheckedFlights[$departureFlightsSetsIndx]))
						{
							$departureFlightsSets[] = static::$CheckedFlights[$departureFlightsSetsIndx];
						}
						else if (file_exists($ff_cf_dep) && (filemtime($ff_cf_dep) > (time() - $this->flightsAviaCacheTimeLimit)))
						{
							$departureFlightsSets[] = static::$CheckedFlights[$departureFlightsSetsIndx] = json_decode(file_get_contents($ff_cf_dep), true);
						}
						else
						{
							$departureFlightsSets[] = static::$CheckedFlights[$departureFlightsSetsIndx] = $this->checkFlights($depCityAirports, $possibleArrivalAirports, $offerCheckIn, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging);
							file_put_contents($ff_cf_dep, json_encode(static::$CheckedFlights[$departureFlightsSetsIndx]));
						}
						/*
						$departureFlightsSets[] = $checkedFlights[$departureFlightsSetsIndx] ?: ($checkedFlights[$departureFlightsSetsIndx] = 
							$this->checkFlights($depCityAirports, $possibleArrivalAirports, $offerCheckIn, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging));
						*/

						$returnFlightsSetsIndx = json_encode(array_keys($possibleArrivalAirports)) . "-" . json_encode(array_keys($depCityAirports)) . "-" . $offerCheckOut;
						$ff_cf_ret = $cacheFolder . "avia_flight_" . md5($returnFlightsSetsIndx) . ".json";
						if (isset(static::$CheckedFlights[$returnFlightsSetsIndx]))
						{
							$returnFlightsSets[] = static::$CheckedFlights[$returnFlightsSetsIndx];
						}
						else if (file_exists($ff_cf_ret) && (filemtime($ff_cf_ret) > (time() - $this->flightsAviaCacheTimeLimit)))
						{
							$returnFlightsSets[] = static::$CheckedFlights[$returnFlightsSetsIndx] = json_decode(file_get_contents($ff_cf_ret), true);
						}
						else
						{
							$returnFlightsSets[] = static::$CheckedFlights[$returnFlightsSetsIndx] = $this->checkFlights($possibleArrivalAirports, $depCityAirports, $offerCheckOut, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging);
							file_put_contents($ff_cf_ret, json_encode(static::$CheckedFlights[$returnFlightsSetsIndx]));
						}
						/*
						$returnFlightsSets[] = $checkedFlights[$returnFlightsSetsIndx] ?: ($checkedFlights[$returnFlightsSetsIndx] = 
							$this->checkFlights($possibleArrivalAirports, $depCityAirports, $offerCheckOut, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging));
						*/
					}
					
				}
			}

			return [
				"Hotels" => $byHotelResp, 
				"RawOffers" => $rawOffers, 
				"DepartureFlightsSets" => $departureFlightsSets, 
				"ReturnFlightsSets" => $returnFlightsSets,
				"RawRequests" => $rawRequestProcessSteps
			];
		}

		// init hotels
		$hotels = [];
		$indexedHotels = [];
		$eoffs = [];
		
		$transportType = reset($filter['transportTypes']);

		$medialInsurenceFileName = static::$MedicalInsurenceFile;
		$medicalEnsurenceFile = "uploads/util/" . $medialInsurenceFileName;
		if (!file_exists($medicalEnsurenceFile) && defined('TRAVELFUSE_WEB_PATH'))
			$medicalEnsurenceFile = rtrim(TRAVELFUSE_WEB_PATH, "\\/") . "/res/docs/" . $medialInsurenceFileName;

		$transferCategory = new \stdClass();
		$transferCategory->Id = static::TransferItmIndx;
		$transferCategory->Code = static::TransferItmIndx;

		$airportTaxesCategory = new \stdClass();
		$airportTaxesCategory->Id = static::AirportTaxItmIndx;
		$airportTaxesCategory->Code = static::AirportTaxItmIndx;

		$departureFlights = [];
		$returnFlights = [];

		$hotelsPos = 0;
		foreach ($offerXmlItems ?: [] as $offerXmlItem)
		{
			try
			{
				//echo $offerXmlItem->asXml();
				// get hotel from offer
				$offerXmlHotel = $offerXmlItem->hotel;

				// get hotel id
				$offerXmlHotelId = (string)$offerXmlHotel->id;

				// ignore offers with no hotel code
				if ((!($hotelCode = trim($offerXmlHotelId))) || ($filter["TravelItemId"] && ($filter["TravelItemId"] !== $hotelCode)))
					continue;

				if (!($hotel = $indexedHotels[$hotelCode]))
				{
					$hotel = ($indexedHotels[$hotelCode] = new \stdClass());
					$hotelsPos++;
					//echo "<div style='color: blue;'>{$hotelsPos}. Process hotel [{$offerXmlHotel->name}]</div>";
				}

				// set hotel id as the code from sejour
				$hotel->Id = $hotelCode;
				$hotel->Name = (string)$offerXmlHotel->name;

				//echo "<div style='color: green;'>[{$hotel->Id}] - {$hotel->Name}</div>";

				$re = preg_match_all("/(\\d+)\\s*(?:\\+?)\\s*\\*+\\s*/uis", trim($hotel->Name), $matches);

				if ($matches && count($matches > 0))
				{
					if ($matches[1] && (count($matches) > 1))
						$hotel->Stars = $matches[1][0];
				}

				// get region xml
				$offerXmlRegion = $offerXmlItem->region;
				$offerXmlRegionId	= (string)$offerXmlRegion->id;

				$offerCheckIn = (string)$offerXmlItem->checkIn;
				//$offerCheckOut = (string)$offerXmlItem->checkOut;
				$offerNightsCount = (string)$offerXmlItem->nightCount;

				$offerCheckIn = implode("-", array_reverse(explode(".", $offerCheckIn)));
				$offerCheckOut = date("Y-m-d", strtotime("+" . $offerNightsCount . " days", strtotime($offerCheckIn)));
				
				if (!($possibleArrivalAirports = $airportsByRegion[(string)$offerXmlRegion->resortArrivalRegionId]))
				{
					try
					{
						throw new \Exception("Possible arrival airports not found!");
					} catch (\Exception $ex) {
						$this->logError(["\$offerXmlItem" => $offerXmlItem], $ex);
						if ($doLogging)
							echo "Possible arrival airports not found!\n";
					}
					continue;
				}

				$departureFlightsSetsIndx = json_encode(array_keys($depCityAirports)) . "-" . json_encode(array_keys($possibleArrivalAirports)) . "-" . $offerCheckIn;
				$ff_cf_dep = $cacheFolder . "avia_flight_" . md5($departureFlightsSetsIndx) . ".json";
				$ff_dep_flight_pulled_from = null;
				if (isset(static::$CheckedFlights[$departureFlightsSetsIndx]))
				{
					if ($doDebug)
						qvar_dump('$CheckedFlights A');
					$departureFlightsSets = static::$CheckedFlights[$departureFlightsSetsIndx];
					$ff_dep_flight_pulled_from = "cache_mem";
				}
				else if ((!$filter["__booking_search__"]) && (!$filter["__on_setup_search__"]) && file_exists($ff_cf_dep) && (filemtime($ff_cf_dep) > (time() - $this->flightsAviaCacheTimeLimit)))
				{
					if ($doDebug)
						qvar_dump('$CheckedFlights B');
					$departureFlightsSets = static::$CheckedFlights[$departureFlightsSetsIndx] = json_decode(file_get_contents($ff_cf_dep), true);
					$ff_dep_flight_pulled_from = "cache_file";
				}
				else
				{
					if ($doDebug)
						qvar_dump('$CheckedFlights C');
					$departureFlightsSets = static::$CheckedFlights[$departureFlightsSetsIndx] = $this->checkFlights($depCityAirports, $possibleArrivalAirports, $offerCheckIn, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging);
					if ((!$departureFlightsSets) && $doLogging && (!isset(static::$MissingFlights[$departureFlightsSetsIndx . "|"])))
					{
						$tmp_data = [
							"\$departureFlightsSetsIndx" => $departureFlightsSetsIndx,
							"\$returnFlightsSetsIndx" => $returnFlightsSetsIndx,
							"\$depCityAirports" => $depCityAirports,
							"\$possibleArrivalAirports" => $possibleArrivalAirports,
							"\$departureFlightsSets" => $departureFlightsSets,
							"\$returnFlightsSets" => $returnFlightsSets,
							"\$offerCheckOut" => $offerCheckOut,
							"\$offerCheckIn" => $offerCheckIn,
						];
						$this->logDataSimple("missing_departure_flights", $tmp_data);
						
						if ($doDebug)
							qvar_dump($tmp_data);
						
						static::$MissingFlights[$departureFlightsSetsIndx . "|"] = $departureFlightsSetsIndx . "|";
					}
					file_put_contents($ff_cf_dep, json_encode($departureFlightsSets));
					$ff_dep_flight_pulled_from = "request";
					/*
					if ($_SERVER["REMOTE_ADDR"] == "86.125.118.86")
					{
						qvardump("do request for departure flight: ", $departureFlightsSetsIndx);
					}
					*/
				}
				
				if ($doDebug)
					qvar_dump('$departureFlightsSets', $departureFlightsSets, $departureFlightsSetsIndx, $ff_cf_dep, file_exists($ff_cf_dep));

				/*
				$departureFlightsSets = $checkedFlights[$departureFlightsSetsIndx] ?: ($checkedFlights[$departureFlightsSetsIndx] = 
					$this->checkFlights($depCityAirports, $possibleArrivalAirports, $offerCheckIn, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging));
				*/

				foreach ($departureFlightsSets ?: [] as $departureFlightSet)
					list($flight, $departureAviaFlight, $seatTypeFlight) = $departureFlightSet;

				$ff_ret_flight_pulled_from = null;
				$returnFlightsSetsIndx = json_encode(array_keys($possibleArrivalAirports)) . "-" . json_encode(array_keys($depCityAirports)) . "-" . $offerCheckOut;
				$ff_cf_ret = $cacheFolder . "avia_flight_" . md5($returnFlightsSetsIndx) . ".json";
				if (isset(static::$CheckedFlights[$returnFlightsSetsIndx]))
				{
					$returnFlightsSets = static::$CheckedFlights[$returnFlightsSetsIndx];
					$ff_ret_flight_pulled_from = "cache_mem";
				}
				else if ((!$filter["__booking_search__"])  && (!$filter["__on_setup_search__"]) && file_exists($ff_cf_ret) && (filemtime($ff_cf_ret) > (time() - $this->flightsAviaCacheTimeLimit)))
				{
					$returnFlightsSets = static::$CheckedFlights[$returnFlightsSetsIndx] = json_decode(file_get_contents($ff_cf_ret), true);
					$ff_ret_flight_pulled_from = "cache_file";
				}
				else 
				{
					$returnFlightsSets = static::$CheckedFlights[$returnFlightsSetsIndx] = $this->checkFlights($possibleArrivalAirports, $depCityAirports, $offerCheckOut, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging);
					if ((!$returnFlightsSets) && $doLogging && (!isset(static::$MissingFlights["|" . $returnFlightsSetsIndx])))
					{
						$this->logDataSimple("missing_return_flights", [
							"\$departureFlightsSetsIndx" => $departureFlightsSetsIndx,
							"\$returnFlightsSetsIndx" => $returnFlightsSetsIndx,
							"\$depCityAirports" => $depCityAirports,
							"\$possibleArrivalAirports" => $possibleArrivalAirports,
							"\$departureFlightsSets" => $departureFlightsSets,
							"\$returnFlightsSets" => $returnFlightsSets,
							"\$offerCheckOut" => $offerCheckOut,
							"\$offerCheckIn" => $offerCheckIn,
						]);
						static::$MissingFlights["|" . $returnFlightsSetsIndx] = "|" . $returnFlightsSetsIndx;
					}
					file_put_contents($ff_cf_ret, json_encode($returnFlightsSets));
					$ff_ret_flight_pulled_from = "request";
					/*
					if ($_SERVER["REMOTE_ADDR"] == "86.125.118.86")
					{
						qvardump("do request for return flight: ", $returnFlightsSetsIndx);
					}
					*/
				}

				/*
				if ($_SERVER["REMOTE_ADDR"] == "86.125.118.86")
				{
					qvardump("flights: ", $departureFlightsSets, $returnFlightsSets, $departureFlightsSetsIndx, $returnFlightsSetsIndx);
				}
				*/

				/*
				$returnFlightsSets = $checkedFlights[$returnFlightsSetsIndx] ?: ($checkedFlights[$returnFlightsSetsIndx] = 
					$this->checkFlights($possibleArrivalAirports, $depCityAirports, $offerCheckOut, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging));
				*/

				foreach ($returnFlightsSets ?: [] as $departureFlightSet)
					list($flightBack, $returnAviaFlight, $seatTypeFlightBack) = $departureFlightSet;

				//qvardump("\$departureFlights", $departureFlights);
				if (count($departureFlights) > 1)
				{
					//qvardump("\$departureFlights", $departureFlights);
				}

				//qvardump("\$returnFlights", $returnFlights);
				if (count($returnFlights) > 1)
				{
					//qvardump("\$returnFlights", $returnFlights);
				}

				// get departure flight
				#$flight = reset($departureFlights);
				// get back flight
				#$flightBack = reset($returnFlights);

				// skip offers with no flights
				if ((!$flight) || (!$flightBack))
				{
					if ((\QAutoload::In_Debug_Mode() || ($doLogging && (!isset(static::$MissingFlights[$departureFlightsSetsIndx . "|" . $returnFlightsSetsIndx])))))
					{
						$tmp_lg_data = [
							"\$departureFlightsSetsIndx" => $departureFlightsSetsIndx,
							"\$returnFlightsSetsIndx" => $returnFlightsSetsIndx,
							"\$depCityAirports" => $depCityAirports,
							"\$possibleArrivalAirports" => $possibleArrivalAirports,
							"\$departureFlightsSets" => $departureFlightsSets,
							"\$returnFlightsSets" => $returnFlightsSets,
							"\$offerCheckOut" => $offerCheckOut,
							"\$offerCheckIn" => $offerCheckIn,
							"\$checked_flights" => static::$CheckedFlights,
							"\$ff_cf_dep" => $ff_cf_dep,
							"\$ff_cf_dep_exists" => file_exists($ff_cf_dep),
							"\$ff_cf_ret" => $ff_cf_ret,
							"\$ff_cf_ret_exists" => file_exists($ff_cf_ret),
							"\$ff_dep_flight_pulled_from" => $ff_dep_flight_pulled_from,
							"\$ff_ret_flight_pulled_from" => $ff_ret_flight_pulled_from,
						];
						
						$this->logDataSimple("missing_flights", $tmp_lg_data);
						
						$log_data['missing_flights'][$departureFlightsSetsIndx . "|" . $returnFlightsSetsIndx] = 
								[
									"\$departureFlightsSets" => $departureFlightsSets,
									"\$returnFlightsSets" => $returnFlightsSets,
									"\$offerCheckOut" => $offerCheckOut,
									"\$offerCheckIn" => $offerCheckIn,
									"\$ff_cf_dep" => $ff_cf_dep,
									"\$ff_cf_dep_exists" => file_exists($ff_cf_dep),
									"\$ff_cf_ret" => $ff_cf_ret,
									"\$ff_cf_ret_exists" => file_exists($ff_cf_ret),
									"\$ff_dep_flight_pulled_from" => $ff_dep_flight_pulled_from,
									"\$ff_ret_flight_pulled_from" => $ff_ret_flight_pulled_from,
								];
					}
					
					static::$MissingFlights[$departureFlightsSetsIndx . "|" . $returnFlightsSetsIndx] = $departureFlightsSetsIndx . "|" . $returnFlightsSetsIndx;
					
					if ($doLogging)
						echo "<div style='color: red;'>No flight/flight back</div>";
					
					continue;
				}
	
				// skip offers with no seat type
				if ((!$seatTypeFlight) || (!$seatTypeFlightBack))
				{
					if ($doLogging)
					{
						$this->logDataSimple("missing_seat_types", [
							"\$flightBack" => $flightBack,
							"\$flight" => $flight
						]);
						
						echo "missing_seat_types\n";
					}
					//echo "<div style='color: red;'>No seat type flight/seat type flight back</div>";
					continue;
				}

				/*
				qvardump([
					"\$departureFlights" => $departureFlights,
					"\$returnFlights" => $returnFlights,
					"\$depSeatsTypes" => $depSeatsTypes,
					"\$retSeatsTypes" => $retSeatsTypes
				]);
				q_die("-q-");
				*/

				// get meal xml (pansion)
				$offerXmlPansion = $offerXmlItem->pansion;
				$offerXmlPansionId = (string)$offerXmlPansion->id;
				$offerXmlPansionName = (string)$offerXmlPansion->name;

				$offerXmlRoomType = $offerXmlItem->hotelRoomType;
				$offerXmlRoomTypeId = (string)$offerXmlRoomType->id;
				$offerXmlRoomTypeName = (string)$offerXmlRoomType->name;

				$offerXmlAgeGroupType = $offerXmlItem->ageGroupType;
				$offerXmlAgeGroupTypeId = (string)$offerXmlAgeGroupType->id;

				// get xml early booking
				$offerXmlEarlyBookingObj = $offerXmlItem->icons->earlyBooking;
				$offerXmlEarlyBookingValue = (string)$offerXmlEarlyBookingObj->value;

				$isEarlyBooking = 0;
				if ($offerXmlEarlyBookingValue == 'true')
					$isEarlyBooking = 1;

				//$bookingUrlIdf = md5($offerXmlItem->bookingUrl->bookingUrl->url . "");
				$bookingUrlIdf = "";
				// setup offer code
				$offerCode = $bookingUrlIdf . "~" . $hotel->Id . '~' . $offerXmlRegionId . '~' 
					. $offerXmlRoomTypeId . '~' . $offerXmlPansionId . '~' . $offerXmlAgeGroupTypeId . '~' . $offerCheckIn . '~' . $offerCheckOut;
			
				$offerXmlPriceObj = $offerXmlItem->price;
				$offerXmlPriceTotalPrice = (float)$offerXmlPriceObj->total;

				if (($alreadyProcessedOffer = $eoffs[$offerCode]))
				{
					if ($alreadyProcessedOffer->Price > $offerXmlPriceTotalPrice)
					{
						$alreadyProcessedOffer->Price = $offerXmlPriceTotalPrice;
						$alreadyProcessedOffer->IsEarlyBooking = $isEarlyBooking;
					}

					if ($alreadyProcessedOffer->InitialPrice < $offerXmlPriceTotalPrice)
						$alreadyProcessedOffer->InitialPrice = $offerXmlPriceTotalPrice;

					continue;
				}

				$offer = ($eoffs[$offerCode] = new \stdClass());
				$offer->IsEarlyBooking = $isEarlyBooking;
				$offer->Code = $offerCode;
				//$eoffsMinPrices[$offerCode] = $offerXmlPriceTotalPrice;

				$offerXmlPriceCurrency = (string)$offerXmlPriceObj->currency;
				$offerXmlPriceInsurancePrice = (string)$offerXmlPriceObj->insurance;
				$offerXmlPriceOtherPrice = (string)$offerXmlPriceObj->other;

				// set offer currency
				$offer->Currency = new \stdClass();
				$offer->Currency->Code = ($offerXmlPriceCurrency == 'EUR') ? 'EUR' : $offerXmlPriceCurrency;

				$offer->FlightDeparture = $flight['Id'];
				$offer->FlightDepartureBack = $flightBack['Id'];
				$offer->SeatTypeFlight = $seatTypeFlight['SeatTypeId'];
				$offer->SeatTypeFlightBack = $seatTypeFlightBack['SeatTypeId'];

				// transfer from airport
				$offer->TransferAirportId = $flight['arrivalAirport'];

				$offerXmlTransferTypes = $offerXmlItem->transferTypes;

				$offer->TransferTypeId = (string)$offerXmlTransferTypes->firstTransferTypeId;
				$offer->BackTransferTypeId = (string)$offerXmlTransferTypes->lastTransferTypeId;

				// net price
				$offer->Net = (float)$offerXmlPriceTotalPrice;

				if ($offerXmlPriceInsurancePrice)
					throw new \Exception('We have insurance price');

				if ($offerXmlPriceOtherPrice)
					throw new \Exception('We have other price');

				// offer total price
				$offer->Gross = (float)$offerXmlPriceTotalPrice;

				// get initial price
				$offer->InitialPrice = $offer->Gross;

				// get availability :: Ok, Wait, Stop
				$offer->Availability = ((string)$offerXmlItem->existsRoom == 'true') ? 'yes' : 'no';

				// number of days needed for booking process
				$offer->Days = $filter['days'];

				// room
				$roomType = new \stdClass();
				$roomType->Id = $offerXmlRoomTypeId;
				$roomType->Title = $offerXmlRoomTypeName;

				$roomMerch = new \stdClass();
				$roomMerch->Title = $offerXmlRoomTypeName;
				$roomMerch->Type = $roomType;

				$roomItm = new \stdClass();
				$roomItm->Merch = $roomMerch;
				# $roomItm->Id = $offerXmlRoomTypeId;

				//required for indexing
				$roomItm->Code = $offerXmlRoomTypeId;
				$roomItm->CheckinAfter = $offerCheckIn;
				$roomItm->CheckinBefore = $offerCheckOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Quantity = 1;
				$roomItm->Availability = $offer->Availability;

				// set check in/ check out dates
				$roomItm->CheckIn = $offerCheckIn;
				$roomItm->CheckOut = $offerCheckOut;
				$roomItm->Duration = $offerNightsCount;

				if ($offer->IsEarlyBooking)
					$roomItm->InfoTitle = 'Early booking';

				if (!$offer->Rooms)
					$offer->Rooms = [];

				$offer->Rooms[] = $roomItm;

				// board
				$boardType = new \stdClass();
				$boardType->Id = $offerXmlPansionId;
				$boardType->Title = $offerXmlPansionName;

				$boardMerch = new \stdClass();
				//$boardMerch->Id = $offerXmlPansionId;
				$boardMerch->Title = $offerXmlPansionName;
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

				if (!$offerXmlPansionId)
					$offer->MealItem = $this->getBoardItem($offer);
				else
					$offer->MealItem = $boardItm;

				if (!$offer->Items)
					$offer->Items = [];
				
				/*
				$new_item = new \stdClass();
				$new_item->Code = 'medical_insurance';
				$new_item->Quantity = 1;
				$new_item->Document = $medicalEnsurenceFile;
				$new_item->UnitPrice = 0;
				$new_item->Currency = $offer->Currency;
				$new_item->Merch = new \stdClass();
				$new_item->Merch->Title = pathinfo(static::$MedicalInsurenceFile, PATHINFO_FILENAME);
				$new_item->Merch->Code = $new_item->Code;
				$new_item->Merch->Category = new \stdClass();
				$new_item->Merch->Category->Code = "MI";
				$new_item->Merch->Category->Name = "Medical Insurence";
				$offer->Items[] = $new_item;
				*/

				// departure transport item
				$departureTransportMerch = new \stdClass();
				$departureTransportMerch->Title = "Dus: ".($checkIn ? date("d.m.Y", strtotime($checkIn)) : "");
				$departureTransportMerch->Category = new \stdClass();
				$departureTransportMerch->Category->Code = 'other-outbound';
				$departureTransportMerch->TransportType = $transportType;
				$departureTransportMerch->DepartureTime = date('Y-m-d', strtotime($flight['departureDatetime'])) . ' ' . date('H:i', strtotime($flight['departureDatetime']));
				$departureTransportMerch->ArrivalTime = date('Y-m-d', strtotime($flight['arrivalDatetime'])) . ' ' . date('H:i', strtotime($flight['arrivalDatetime']));
				$departureTransportMerch->DepartureAirport = $airportsMappedData[$flight['departureAirport']]["IATA"];
				$departureTransportMerch->ReturnAirport = $airportsMappedData[$flight['arrivalAirport']]["IATA"];

				$departureTransportMerch->From = new \stdClass();
				$departureTransportMerch->From->City = new \stdClass();
				$departureTransportMerch->From->City->Id = $airportsMappedData[$flight['departureAirport']]["City"];
				
				$departureTransportMerch->To = new \stdClass();
				$departureTransportMerch->To->City = new \stdClass();
				$departureTransportMerch->To->City->Id = $airportsMappedData[$flight['arrivalAirport']]["City"];

				$departureTransportItm = new \stdClass();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Quantity = 1;
				$departureTransportItm->Currency = $offer->Currency;
				$departureTransportItm->UnitPrice = 0;
				$departureTransportItm->Gross = 0;
				$departureTransportItm->Net = 0;
				$departureTransportItm->InitialPrice = 0;
				$departureTransportItm->DepartureDate = $offerCheckIn;
				$departureTransportItm->ArrivalDate = $offerCheckIn;

				// for identify purpose
				$departureTransportItm->Id = $departureTransportMerch->Id;

				// return transport item
				$returnTransportMerch = new \stdClass();
				$returnTransportMerch->Title = "Retur: ".($offerCheckOut ? date("d.m.Y", strtotime($offerCheckOut)) : "");
				$returnTransportMerch->Category = new \stdClass();
				$returnTransportMerch->Category->Code = 'other-inbound';
				$returnTransportMerch->TransportType = $transportType;
				$returnTransportMerch->DepartureTime = date('Y-m-d', strtotime($flightBack['departureDatetime'])) . ' ' . date('H:i', strtotime($flightBack['departureDatetime']));
				$returnTransportMerch->ArrivalTime = date('Y-m-d', strtotime($flightBack['arrivalDatetime'])) . ' ' . date('H:i', strtotime($flightBack['arrivalDatetime']));

				$returnTransportMerch->DepartureAirport = $airportsMappedData[$flightBack['departureAirport']]["IATA"];
				$returnTransportMerch->ReturnAirport = $airportsMappedData[$flightBack['arrivalAirport']]["IATA"];
				
				$returnTransportMerch->From = new \stdClass();
				$returnTransportMerch->From->City = new \stdClass();
				$returnTransportMerch->From->City->Id = $airportsMappedData[$flightBack['departureAirport']]["City"];
				
				$returnTransportMerch->To = new \stdClass();
				$returnTransportMerch->To->City = new \stdClass();
				$returnTransportMerch->To->City->Id = $airportsMappedData[$flightBack['arrivalAirport']]["City"];

				$returnTransportItm = new \stdClass();
				$returnTransportItm->Merch = $returnTransportMerch;
				$returnTransportItm->Quantity = 1;
				$returnTransportItm->Currency = $offer->Currency;
				$returnTransportItm->UnitPrice = 0;
				$returnTransportItm->Gross = 0;
				$returnTransportItm->Net = 0;
				$returnTransportItm->InitialPrice = 0;
				$returnTransportItm->DepartureDate = $offerCheckOut;
				$returnTransportItm->ArrivalDate = $offerCheckOut;

				// for identify purpose
				$returnTransportItm->Id = $returnTransportMerch->Id;
				$departureTransportItm->Return = $returnTransportItm;

				// add items to offer
				$offer->Item = $roomItm;
				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;

				$offer->Items[] = $this->getApiTransferItem($offer, $transferCategory);
				$offer->Items[] = $this->getApiAirpotTaxesItem($offer, $airportTaxesCategory);

				// save offer on hotel
				$hotel->Offers[] = $offer;
				$hotels[$hotel->Id] = $hotel;
			}
			catch (\Exception $ex)
			{
				// if we have an issue on a hotel skip it but leave the rest to pass
				//@TO DO - log the error
				$this->logError(["\$offerXmlItem" => $offerXmlItem], $ex);
				
				if ($doLogging)
					echo $ex->getMessage(), "\n", $ex->getTraceAsString(), " \n";
				if (\QAutoload::GetDevelopmentMode())
				{
					q_remote_log_sub_entry([
						[
							'Timestamp_ms' => (string)microtime(true),
							'Tags' => ['tag' => 'teztour-getCharterOffers-log_data'],
							'Traces' => $ex->getTraceAsString(),
							'Is_Error' => true,
							'Data' => ['error-message' => $ex->getMessage()],
						]
					]);
				}
				
				continue;
			}
		}

		if ($doLogging)
			$this->logDataSimple("resp-hotels", ["\$hotels" => $hotels]);
		
		if ($log_data)
		{
			q_remote_log_sub_entry([
					[
						'Timestamp_ms' => (string)microtime(true),
						'Tags' => ['tag' => 'teztour-getCharterOffers-log_data'],
						'Traces' => (new \Exception())->getTraceAsString(),
						'Data' => ['$log_data' => $log_data],
					]
				]);
			
			file_put_contents("/home/tf_startup/tez_log_data.json", json_encode($log_data), FILE_APPEND);
			file_put_contents("/home/tf_startup/tez_log_data.json", "\n", FILE_APPEND);
		}
		
		if ($doLogging)
			qvar_dump($hotels);

		// return hotels
		return $hotels;
	}
	
	public function getMinRAndBs(array $filter = null)
	{
		// setup params
		$acceptableRAndBs = $this->getMinRAndBs_getAllAcceptable($filter);
		$min = null;
		$minIndex = null;
		
		// go through each acceptable hotel category
		foreach ($acceptableRAndBs as $classId => $hotelClass)
		{
			// set minimum if not set
			if (!isset($min))
				$min = $hotelClass['Weight'];
			// set index of minimum in no set
			if (!isset($minIndex))
				$minIndex = $classId;
			if ($min > $hotelClass['Weight'])
			{
				$min = $hotelClass['Weight'];
				$minIndex = $classId;
			}
		}
		// return min acceptable category
		return $acceptableRAndBs[$minIndex];
	}
	
	public function getMinRAndBs_getAllAcceptable(array $filter = null, $force = false)
	{
		$params = [
			'locale' => 'ro',
			'xml' => 'true',
			'countryId' => $filter['countryId'],
			'cityId' => $filter['departureCity'] ?: '9001185'
		];
		$randBsRsp = $this->requestTariffSearch('randbs', $params, $force);
		
		try 
		{
			$randBsRspXml = simplexml_load_string($randBsRsp);
		}
		catch(\Exception $ex)
		{
			return [];
		}
		
		if ($randBsRspXml === false)
			return [];
		
		if ((!$randBsRspXml->rAndBs) || (!$randBsRspXml->rAndBs->rAndB))
			return [];
		$hotelRAndBIds = [];
		foreach ($randBsRspXml->rAndBs->rAndB as $randbData)
		{
			$hotelRAndBId_data = [
				'Id' => (string)$randbData->rAndBId,
				'Name' => (string)$randbData->name,
				'Weight' => (string)$randbData->weight,
				"Group" => (string)$randbData->group,
				"SortOrder" => (string)$randbData->sortOrder,
			];
			// index hotel classes
			$hotelRAndBIds[$hotelRAndBId_data['Id']] = $hotelRAndBId_data;
		}
		return $hotelRAndBIds;
	}
	
	public function checkFlights($depCityAirports, $possibleArrivalAirports, $departureDate, $flightDeparturesFromFile, $seatTypesFromFile_byFlightIDs, $doLogging = false)
	{
		$allAvailableFlights = [];
		$toUse_seatTypesFromFile = [];
		#$departureDatePrevDay = date('Y-m-d', strtotime("- 1 day", strtotime($departureDate)));
		// check the availability on avia reference flights for the following combinations: airports from departure region to airports from arrival region
		foreach ($depCityAirports ?: [] as $departureAirport)
		{
			foreach ($possibleArrivalAirports ?: [] as $arrivalAirport)
			{
				// identify the flights for ids - we identify flights by departure airport/arrival airport & departure date
				$identifiedFlights = [];
				foreach ($flightDeparturesFromFile ?: [] as $fd)
				{
					$depDate = date('Y-m-d', strtotime($fd['departureDatetime']));
					if (($fd["departureAirport"] == $departureAirport["Id"]) && ($fd["arrivalAirport"] == $arrivalAirport["Id"]) && 
						($depDate == $departureDate))
						//(($depDate == $departureDate) || ($depDate == $departureDatePrevDay)))
					{
						$identifiedFlights[$depDate][] = $fd;
						if ($seatTypesFromFile_byFlightIDs[$fd["Id"]])
							$toUse_seatTypesFromFile[$fd["Id"]] = $seatTypesFromFile_byFlightIDs[$fd["Id"]];
					}
				}
				ksort($identifiedFlights);

				if ((!$identifiedFlights) && $doLogging)
				{
					$this->logDataSimple("cannot_identify_flight", [
						"\$departureAirport" => $departureAirport,
						"\$arrivalAirport" => $arrivalAirport,
						"\$depDate" => $depDate,
						#"\$flightDeparturesFromFile" => $flightDeparturesFromFile,
					]);
				}

				foreach ($identifiedFlights ?: [] as $dateFlights)
				{
					// use only flights from first date - we may need to go
					foreach ($dateFlights ?: [] as $dateFlight)
					{
						$aviaRefFlightsParams = [
							"depIds" => $dateFlight["departureAirport"],
							"arrIds" => $dateFlight["arrivalAirport"],
							"depDate" => date("d.m.Y", strtotime($dateFlight["departureDatetime"])),
							"arrDate" => date("d.m.Y", strtotime($dateFlight["arrivalDatetime"])),
							"deviation" => 0
						];

						// get flights from avia reference
						$aviaRefFlight = $this->getAviaReferenceFlights($aviaRefFlightsParams, $dateFlight, $doLogging);
						// is available means that it has available seats
						if ($aviaRefFlight && $this->isAviaFlightAvailable($aviaRefFlight))
						{
							// check seat types if available
							$departureSeatSets = $toUse_seatTypesFromFile[$dateFlight["Id"]];
							$toUseDepartSet = null;
							foreach ($departureSeatSets ?: [] as $dss)
							{
								if (($dss["leftNumber"] === "ok") || (is_numeric($dss["leftNumber"]) && ((int)$dss["leftNumber"] > 0)))
								{
									$toUseDepartSet = $dss;
									break;
								}
							}
							// we found the first seat set available
							if ($toUseDepartSet)
								$allAvailableFlights[$dateFlight["Id"]] = [$dateFlight, $aviaRefFlight, $toUseDepartSet];
						}
					}
				}
			}
		}
		
		return $allAvailableFlights;
	}
	
	/**
	 * Get min acceptable hotel category for search / hotelClassId
	 * 
	 * @param array $filter
	 * @return type
	 */
	public function getMinAcceptableCategory(array $filter = null)
	{
		// get acceptable hotel categories
		$acceptableHotelCategories = $this->getAcceptableHotelCategories($filter);
		
		$min = null;
		$minIndex = null;
		
		// go through each acceptable hotel category
		foreach ($acceptableHotelCategories as $classId => $hotelClass)
		{
			// set minimum if not set
			if (!isset($min))
				$min = $hotelClass['Weight'];
			
			// set index of minimum in no set
			if (!isset($minIndex))
				$minIndex = $classId;
			
			if ($min > $hotelClass['Weight'])
			{
				$min = $hotelClass['Weight'];
				$minIndex = $classId;
			}
		}		
		
		// return min acceptable category
		return $acceptableHotelCategories[$minIndex];
	}

	public function getAcceptableHotelCategories(array $filter = null, bool $force = false)
	{
		try
		{
			// setup params
			$params = [
				'locale' => 'ro',
				'xml' => 'true',
				'countryId' => $filter['countryId'] ?: '1104',
				'cityId' => $filter['departureCity'] ?: '9001185'
			];

			// request acce
			$hotelClassesResp = $this->requestTariffSearch('hotelClasses', $params, $force);

			try 
			{
				$hotelClassesXml = simplexml_load_string($hotelClassesResp);
			}
			catch(\Exception $ex)
			{	
				return [];
			}

			if ($hotelClassesXml === false)
				return [];

			if (!$hotelClassesXml->hotelClasses || !$hotelClassesXml->hotelClasses->hotelClass)
				return [];

			$hotelClasses = [];

			foreach ($hotelClassesXml->hotelClasses->hotelClass as $hotelClassData)
			{
				$hotelClass = [
					'Id' => (string)$hotelClassData->classId,
					'Name' => (string)$hotelClassData->name,
					'Weight' => (string)$hotelClassData->weight
				];

				// index hotel classes
				$hotelClasses[$hotelClass['Id']] = $hotelClass;
			}

			return $hotelClasses;
		}
		finally
		{
			if (!$hotelClasses)
			{
				# do some logging
				ob_start();
				qvar_dump(['filter' => $filter, '$force' => $force, '$params' => $params, '$hotelClasses' => $hotelClasses, '$hotelClassesXml' => $hotelClassesXml, 
								'ex' => $ex ? [$ex->getMessage(), $ex->getTraceAsString()] : null]);
				$out = ob_get_clean();
				file_put_contents("../teztour.html", $out);
			}
		}
	}

	public function requestTariffSearch($type, $params, $force = false)
	{
		// setup url for curl
		$url = 'https://xml.tez-tour.com/tariffsearch/' . $type . '?' . http_build_query($params);

		// get cache file path
		$cache_file = $this->getSimpleCacheFileForUrl($url, $params);

		// last modified
		$cf_last_modified = ($f_exists = file_exists($cache_file)) ? filemtime($cache_file) : null;
		$cache_time_limit = time() - $this->hotelClassesTimeLimit;

		// if exists - last modified
		if ((!$force) && (($f_exists) && ($cf_last_modified >= $cache_time_limit)))
		{
			return file_get_contents($cache_file);
		}

		if (!(defined('USE_FGC_FOR_TEZ_TARRIFFSEARCH') && USE_FGC_FOR_TEZ_TARRIFFSEARCH))
		{
			// init curl
			$curlHandle = q_curl_init_with_log();
			q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $url);
			q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
			q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
			q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
			q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
			q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
			q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);

			list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
			if ($proxyUrl)
				q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));

			#if ($proxyPort)
			#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
			if ($proxyUsername)
				q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
			if ($proxyPassword)
				q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);

			// exec curl
			$data = q_curl_exec_with_log($curlHandle);
		}
		else
		{
			$contextOpts = [
				"ssl" => [
					"verify_peer" => false,
					"verify_peer_name" => false,
				],
			]; 

			$data = file_get_contents($url, false, stream_context_create($contextOpts));
		}

		if ($data === false)
		{
			//throw new \Exception("Invalid response from server - " . curl_error($curlHandle));
			throw new \Exception("Invalid response from server!");
		}

		file_put_contents($cache_file, $data);

		return $data;
	}
	
	public function requestDepartureCities($type, $params)
	{
		// setup url for curl
		# http://search.tez-tour.com/tariffsearch/byCountry?countryId=5732&locale=ru&xml=true
		# $url =  'https://xml.tez-tour.com/tariffsearch/hotelClasses?' . http_build_query($params);
		$url = 'http://search.tez-tour.com/tariffsearch/byCountry?' . http_build_query($params);
		
		// init curl
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $url);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);
		
		// exec curl
		$data = q_curl_exec_with_log($curlHandle);
		if ($data === false)
			throw new \Exception("Invalid response from server - " . curl_error($curlHandle));
		
		return $data;
	}
	
	public function requestDepartureDates($type, $params)
	{
		// setup url for curl
		# http://search.tez-tour.com/tariffsearch/getFlightDeparture?cityId=345&countryId=5733&formatResult=true&xml=true
		$url = 'http://search.tez-tour.com/tariffsearch/getFlightDeparture?' . http_build_query($params);
		
		// init curl
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $url);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);
		
		// exec curl
		$data = q_curl_exec_with_log($curlHandle);
		if ($data === false)
			throw new \Exception("Invalid response from server - " . curl_error($curlHandle));
		
		return $data;
	}
	
	/**
	 * 
	 *	[X] SGL - 1 AD
		[X] SGL+CHD - 1AD + 1CHD
		[X] SGL+2CHD - 1AD + 2 CHD
		[X] DBL - 2 AD
		[X] DBL+CHD - 2AD + 1CHD
		[X] DBL+2CHD - 2AD + 2 CHD
		[X] DBL+EXB - 3AD
		[X] DBL+EXB+CHD - 3AD + 1 CHD
		[X] TRPL+2CHD - 3AD + 2 CHD
		[X] 4PAX - 4AD
		[X] 4PAX+CHD - 4AD + 1CHD
		[X] 4PAX+2CHD - 4AD + 2 CHD
		[X] 5PAX - 5AD
		[X] 5PAX+CHD - 5AD + 1CHD
		[X] 5PAX+2CHD - 5AD + 2 CHD
		[X] 6PAX - 6AD
		[X] 6PAX+CHD - 6AD + 1CHD
		[X] 6PAX+2CHD - 6AD + 2 CHD
	 *	[X] 7PAX - 7AD
	 *	7PAX+CHD - 7AD + 1CHD
	 *	[X] 8PAX - 8AD
	 * 
	 * @param type $roomFilter
	 */
	public function getAccommodationId($roomFilter)
	{
		$adults = $roomFilter['adults'];
		$children = $roomFilter['children'];
		$childrenAges = $roomFilter['childrenAges'];
		
		$accommodationTypes = $this->getAccommodationTypes();
		
		// build accommodation string
		$accString = '';
		
		if ($adults == 1)
		{
			$accString = 'SGL';
			
			if ($children <= 2)
			{
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString .= '+2CHD';
			}
			else
				$accString = '';
		}
		else if ($adults == 2)
		{
			$accString = 'DBL';
			
			if ($children <= 2)
			{
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString .= '+2CHD';
			}
			else
				$accString = '';
		}
		else if ($adults == 3)
		{
			$accString = 'DBL+EXB';
			
			if ($children <= 2)
			{
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString = 'TRPL+2CHD';
			}
			else
				$accString = '';
		}
		else if (($adults > 3) && ($adults <= 8))
		{
			$accString = $adults . 'PAX';
			
			if ($children <= 2)
			{
				if ($children == 1)
					$accString .= '+CHD';
				else if ($children == 2)
					$accString .= '+2CHD';
			}
			else
				$accString = '';
			
			if (($children > 0) && ($adults == 8))
				$accString = '';
			
			if (($adults == 7) && ($children > 1))
				$accString = '';
		}
		
		// search for code in accommodation types
		$accommodationId = array_search(strtolower($accString), $accommodationTypes);
		
		// return accomodation id
		return $accommodationId;
	}
	
	/**
	 * Get accommodation types
	 * 
	 * @return boolean
	 */
	public function getAccommodationTypes()
	{
		$accommodationsFile = $this->getFiles('stayTypes');

		// get content of the file
		$accommodationsContent = file_get_contents($accommodationsFile);

		// load xml
		$accommodationsXml = simplexml_load_string($accommodationsContent);

		// exit if no item
		if (!$accommodationsXml->item)
			return false;

		// init accommodations array
		$accommodations = [];
		
		// go through each region
		foreach ($accommodationsXml->item as $accommodationData)
			$accommodations[(string)$accommodationData->id] = str_replace(' ', '', strtolower ((string)$accommodationData->name));
		
		// return accommodations
		return $accommodations;
	}

	public function getBoardItem($offer)
	{
		return $this->getEmptyBoardItem($offer);
	}
	
	public function getAirportCities()
	{
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
		
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		// get cache file
		$airportsCacheFile = $cacheFolder . 'airports.json';
		
		// exception if no airports cache file
		if (!file_exists($airportsCacheFile))
			throw new \Exception('Missing airports cache file');
		
		// get airports mapped data
		$airportsMappedDataJSON = file_get_contents($airportsCacheFile);
		
		// decode json
		$airportsMappedData = json_decode($airportsMappedDataJSON, true);
		
		// get cities file
		$citiesFile = $this->getFiles('cities');
		
		// get file content
		$citiesContent = file_get_contents($citiesFile);
		
		// load xml
		$citiesXml = simplexml_load_string($citiesContent);
		
		list($countries) = $this->api_getCountries();
		
		// exit if no item
		if (!$citiesXml->item)
			return [];
		
		// init cities array
		$cities = [];
		
		// go through each item
		foreach ($citiesXml->item as $cityData)
		{			
			$cityId = (string)$cityData->id;
			$cityName = trim((string)$cityData->name);
			
			$countryId = null;
			foreach ($cityData->prop as $cityProperty)
			{
				$propName = (string)$cityProperty['name'];
				$propValue = (string)$cityProperty;
				
				if ($propName == 'Country')
					$countryId = $propValue;
			}		
			
			// indexed city by id
			$cities[$cityId] = [
				'CityName' => $cityName,
				'CountryId' => $countryId
			];
		}
		
		// init roairports
		$roAirports = [];

		// go through each airport
		foreach ($airportsMappedData as $airportData)
		{
			// get only romanian airports
			#if (in_array($airportData['IATA'], static::$ROAirports))
			if ($airportData["Country"] == static::$RoCountryID_ForAirports)
				$roAirports[$airportData['Id']] = $airportData;
		}
		
		$airportsCities = [];
		foreach ($roAirports as $roAirportData)
		{
			if ($cities[$roAirportData['City']])
			{
				$city = new \stdClass();
				$city->Id = $roAirportData['City'];
				$city->Name = $cities[$roAirportData['City']]['CityName'];
				
				if (strpos($city->Name, '(INCOMING)'))
						continue;
				
				$city->Country = $countries[$cities[$roAirportData['City']]['CountryId']];
				
				$airportsCities[$city->Id] = $city;
			}
		}
		
		return $airportsCities;
	}
	
	/**
	 * Map countries by ISO code.
	 * 
	 * @return type
	 * @throws \Exception
	 */
	protected function getCountriesMapping()
	{
		$mapping = [
			// --------------- disabled --------------------
			// "Austria" => "AT",
			// "Bulgaria" => "BG",
			// "Cipru" => "CY",
			// "Cuba" => "CU",
			// "Egipt" => "EG",
			// "EAU" => "AE",
			// "Grecia" => "GR",
			// "Irlanda" => "IE",
			// "Italia" => "IT",
			// "Maldive" => "MV",
			// "Spania" => "ES",
			// "Thailanda" => "TH",
			// "Turcia" => "TR",
			// "Moldova" => "MD",
			// "Romania" => "RO",
			// ---------------added ----------------------
			'Andorra' => 'AD',
            'United Arab Emirates' => 'AE',
            'Uae' => 'AE',
            'Emiratele Arabe Unite' => 'AE',
            'UAE' => 'AE',
            'Afghanistan' => 'AF',
            'Antigua & Barbuda' => 'AG',
            'Anguilla' => 'AI',
            'Albania' => 'AL',
            'Armenia' => 'AM',
            'Netherlands Antilles' => 'AN',
            'Angola' => 'AO',
            'Antarctica' => 'AQ',
            'Argentina' => 'AR',
            'American Samoa' => 'AS',
            'Austria' => 'AT',
            'Australia' => 'AU',
            'Aruba' => 'AW',
            'Aland Islands' => 'AX',
            'Azerbaijan' => 'AZ',
            'Bosnia & Herzegovina' => 'BA',
			'BOSNIA AND HERZEGOVINA' => 'BA',
            'Barbados' => 'BB',
            'Bangladesh' => 'BD',
            'Belgium' => 'BE',
            'Burkina Faso' => 'BF',
            'Bulgaria' => 'BG',
            'Bahrain' => 'BH',
            'Burundi' => 'BI',
            'Benin' => 'BJ',
            'Caribbean Netherlands' => 'BK',
            'St. Barthélemy' => 'BL',
            'Bermuda' => 'BM',
            'Brunei' => 'BN',
            'Bolivia' => 'BO',
            'Brazil' => 'BR',
            'Bahamas' => 'BS',
            'Bhutan' => 'BT',
            'Bouvet Island' => 'BV',
            'Botswana' => 'BW',
            'Belarus' => 'BY',
            'Belize' => 'BZ',
            'Canada' => 'CA',
            'Cocos Islands' => 'CC',
            'Congo (DRC)' => 'CD',
            'Central African Republic' => 'CF',
            'Congo - Brazzaville' => 'CG',
            'Switzerland' => 'CH',
            'Ivory Coast' => 'CI',
            'Cook Islands' => 'CK',
            'Chile' => 'CL',
            'Cameroon' => 'CM',
            'China' => 'CN',
            'Colombia' => 'CO',
            'Costa Rica' => 'CR',
            'Cuba' => 'CU',
            'Cape Verde' => 'CV',
            'Curaçao' => 'CW',
            'Christmas Island' => 'CX',
            'Cyprus' => 'CY',
            'Czech Republic' => 'CZ',
            'Germany' => 'DE',
            'Germania' => 'DE',
            'Djibouti' => 'DJ',
            'Denmark' => 'DK',
            'Dominica' => 'DM',
            'Dominican Republic' => 'DO',
            'Algeria' => 'DZ',
            'Ecuador' => 'EC',
            'Estonia' => 'EE',
            'Egypt' => 'EG',
            'Western Sahara' => 'EH',
            'Eritrea' => 'ER',
            'Spain' => 'ES',
            'Spania' => 'ES',
            'Ethiopia' => 'ET',
            'Finland' => 'FI',
            'Fiji' => 'FJ',
            'Falkland Islands' => 'FK',
            'Federated States of Micronesia' => 'FM',
            'Faroe Islands' => 'FO',
            'France' => 'FR',
            'Gabon' => 'GA',
            'United Kingdom' => 'GB',
            'Great Britain' => 'GB',
            'Grenada' => 'GD',
            'Georgia' => 'GE',
            'French Guiana' => 'GF',
            'Guernsey' => 'GG',
            'Ghana' => 'GH',
            'Gibraltar' => 'GI',
            'Greenland' => 'GL',
            'Gambia' => 'GM',
            'Guinea' => 'GN',
            'Guadeloupe' => 'GP',
            'Equatorial Guinea' => 'GQ',
            'Greece' => 'GR',
            'South Georgia And The South Sandwich Islands' => 'GS',
            'Guatemala' => 'GT',
            'Guam' => 'GU',
            'Guinea-Bissau' => 'GW',
            'Guyana' => 'GY',
            'Hong Kong' => 'HK',
            'Heard and McDonald Islands' => 'HM',
            'Honduras' => 'HN',
            'Croatia' => 'HR',
            'Haiti' => 'HT',
            'Hungary' => 'HU',
            'Indonesia' => 'ID',
            'Ireland' => 'IE',
            'Israel' => 'IL',
            'Isle Of Man' => 'IM',
            'India' => 'IN',
            'British Indian Ocean Territory' => 'IO',
            'Iraq' => 'IQ',
            'Iran' => 'IR',
            'Iceland' => 'IS',
            'Italy' => 'IT',
            'Italia' => 'IT',
            'Jersey' => 'JE',
            'Jamaica' => 'JM',
            'Jordan' => 'JO',
            'Iordania' => 'JO',
            'Japan' => 'JP',
            'Kenya' => 'KE',
            'Kyrgyzstan' => 'KG',
            'Cambodia' => 'KH',
            'Kiribati' => 'KI',
            'Comoros' => 'KM',
            'St. Kitts & Nevis' => 'KN',
            'North Korea' => 'KP',
            'South Korea' => 'KR',
            'Kuwait' => 'KW',
            'Cayman Islands' => 'KY',
            'Kazakhstan' => 'KZ',
            'Laos' => 'LA',
            'Lebanon' => 'LB',
            'St. Lucia' => 'LC',
            'Liechtenstein' => 'LI',
            'Sri Lanka' => 'LK',
            'Liberia' => 'LR',
            'Lesotho' => 'LS',
            'Lithuania' => 'LT',
            'Luxembourg' => 'LU',
            'Latvia' => 'LV',
            'Libya' => 'LY',
            'Morocco' => 'MA',
            'Monaco' => 'MC',
            'Moldova' => 'MD',
            'Montenegro' => 'ME',
            'Muntenegru' => 'ME',
            'San Martin (f)' => 'MF',
            'Madagascar' => 'MG',
            'Marshall Islands' => 'MH',
            'Macedonia (FYROM)' => 'MK',
            'Mali' => 'ML',
            'Myanmar (Burma)' => 'MM',
            'Mongolia' => 'MN',
            'Macau' => 'MO',
            'Northern Mariana Islands' => 'MP',
            'Martinique' => 'MQ',
            'Mauritania' => 'MR',
            'Montserrat' => 'MS',
            'Malta' => 'MT',
            'Mauritius' => 'MU',
            'Maldives' => 'MV',
            'Maldive' => 'MV',
            'Malawi' => 'MW',
            'Mexico' => 'MX',
            'Malaysia' => 'MY',
            'Mozambique' => 'MZ',
            'Namibia' => 'NA',
            'New Caledonia' => 'NC',
            'Niger' => 'NE',
            'Norfolk Island' => 'NF',
            'Nigeria' => 'NG',
            'Nicaragua' => 'NI',
            'Netherlands' => 'NL',
            'Norway' => 'NO',
            'Nepal' => 'NP',
            'Nauru' => 'NR',
            'Niue' => 'NU',
            'Northern Cyprus' => 'NY',
            'New Zealand' => 'NZ',
            'Oman' => 'OM',
            'Panama' => 'PA',
            'Peru' => 'PE',
            'French Polynesia' => 'PF',
            'Papua New Guinea' => 'PG',
            'Philippines' => 'PH',
            'Pakistan' => 'PK',
            'Poland' => 'PL',
            'St. Pierre and Miquelon' => 'PM',
            'Pitcairn Island' => 'PN',
            'Puerto Rico' => 'PR',
            'State of Palestine' => 'PS',
            'Portugal' => 'PT',
            'Portugalia' => 'PT',
            'Palau' => 'PW',
            'Paraguay' => 'PY',
            'Qatar' => 'QA',
            'Réunion' => 'RE',
            'Romania' => 'RO',
            'Serbia' => 'RS',
			'SERBIA INCOMING' => 'RS',
            'Russia' => 'RU',
            'Rwanda' => 'RW',
            'Saudi Arabia' => 'SA',
            'Solomon Islands' => 'SB',
            'Seychelles' => 'SC',
            'Sudan' => 'SD',
            'Sweden' => 'SE',
            'Singapore' => 'SG',
            'Slovenia' => 'SI',
            'Svalbard' => 'SJ',
            'Slovakia' => 'SK',
            'Sierra Leone' => 'SL',
            'San Marino' => 'SM',
            'Senegal' => 'SN',
            'Somalia' => 'SO',
            'St. Helena' => 'SH',
            'Suriname' => 'SR',
            'South Sudan' => 'SS',
            'São Tomé & Príncipe' => 'ST',
            'El Salvador' => 'SV',
            'Sint Maarten' => 'SX',
            'Syria' => 'SY',
            'Swaziland' => 'SZ',
            'Turks & Caicos Islands' => 'TC',
            'Chad' => 'TD',
            'French Southern and Antarctic Territories' => 'TF',
            'Togo' => 'TG',
            'Thailand' => 'TH',
            'Tajikistan' => 'TJ',
            'Tokelau' => 'TK',
            'Timor Leste' => 'TL',
            'Turkmenistan' => 'TM',
            'Tunisia' => 'TN',
            'Tonga' => 'TO',
            'East Timor' => 'TP',
            'Turkey' => 'TR',
            'Trinidad & Tobago' => 'TT',
            'Tuvalu' => 'TV',
            'Taiwan' => 'TW',
            'Tanzania' => 'TZ',
            'Ukraine' => 'UA',
            'Uganda' => 'UG',
            'US Minor Outlying Islands' => 'UM',
            'United States' => 'US',
			'USA' => 'US',
            'Uruguay' => 'UY',
            'Uzbekistan' => 'UZ',
            'Vatican City' => 'VA',
            'St. Vincent & Grenadines' => 'VC',
            'Venezuela' => 'VE',
            'British Virgin Islands' => 'VG',
            'U.S. Virgin Islands' => 'VI',
            'Vietnam' => 'VN',
            'Vanuatu' => 'VU',
            'Wallis and Futuna' => 'WF',
            'Samoa' => 'WS',
            'Kosovo' => 'XK',
            'Yemen Republic' => 'YE',
            'Mayotte' => 'YT',
            'South Africa' => 'ZA',
			'REPUBLIC OF SOUTH AFRICA' => 'ZA',
            'Zambia' => 'ZM',
            'Zanzibar' => 'ZN',
            'Zimbabwe' => 'ZW',
            // will be ignored
            'Asia' => '?',
            'Africa' => '?',
            'Europe' => '?',
            'North America' => '?',
            'Oceania' => '?',
            'South America' => '?',
            'Australasia' => '?',
            'Caribbean' => '?',
            'Central Africa' => '?',
            'Central America' => '?',
            'Central Asia' => '?',
            'Central Europe' => '?',
            'Eastern Africa' => '?',
            'Eastern Asia' => '?',
            'Eastern Europe' => '?',
            'Melanesia' => '?',
            'Micronesia' => '?',
            'Northern Africa' => '?',
            'Northern America' => '?',
            'Northern Europe' => '?',
            'Polynesia' => '?',
            'South-Eastern Asia' => '?',
            'Southern Africa' => '?',
            'Southern America' => '?',
            'Southern Asia' => '?',
            'Southern Europe' => '?',
            'Western Africa' => '?',
            'Western Asia' => '?',
            'Western Europe' => '?',
            'Scandinavia' => '?',
			'Andora' => 'AD',
			'Azerbaidjan' => 'AZ',
			'Belgia' => 'BE',
			'Brazilia' => 'BR',
			'Virginele Britanice' => 'VG',
			'Cambodgia' => 'KH',
			'Camerun' => 'CM',
			'Cayman' => 'KY',
			'Columbia' => 'CO',
			'Congo (Republica)' => 'CG',
			'Rep Democrata Congo' => 'CD',
			'Insulele Cook' => 'CK',
			'Cipru' => 'CY',
			'Cipru de Nord' => 'NY',
			'Cehia' => 'CZ',
			'Danemarca' => 'DK',
			// 'Rep Dominicana' => 'DO',
			'Republica Dominicana' => 'DO',
			'DOMINICANA' => 'DO',
			'Egipt' => 'EG',
			'Etiopia' => 'ET',
			'Fidji' => 'FJ',
			'Finlanda' => 'FI',
			'Franta' => 'FR',
			'Grecia' => 'GR',
			'Groenlanda' => 'GL',
			'Ungaria' => 'HU',
			'Islanda' => 'IS',
			'Indonezia' => 'ID',
			'Japonia' => 'JP',
			// Kyrgyzstan
			//'Kârgâzstan' => 'KG',
			'Kyrghistan' => 'KG' ,
			'Letonia' => 'LV',
			'Liban' => 'LB',
			'Libia' => 'LY',
			'Lituania' => 'LT',
			'Luxemburg' => 'LU',
			'Macedonia' => 'MK',
			'Mexic' => 'MX',
			'Maroc' => 'MA',
            'Moroc' => 'MA',
			'Mozambic' => 'MZ',
			'Myanmar' => 'MM',
			//'Netherlands' => 'NL',
			'Noua Zeelanda' => 'NZ',
			'Coreea R P D' => 'KP',
			'Norvegia' => 'NO',
			'Filipine' => 'PH',
			'Polonia' => 'PL',
			'Capul Verde' => 'CV',
			'Reunion' => 'RE',
			'Rusia' => 'RU',
			'Arabia Saudita' => 'SA',
			'Slovacia' => 'SK',
			'Africa de Sud' => 'ZA',
			'Coreea Sud' => 'KR',
			'Suedia' => 'SE',
			'Elvetia' => 'CH',
			'Siria' => 'SY',
			'Thailanda' => 'TH',
			'Turcia' => 'TR',
			'Ucraina' => 'UA',
			'Marea Britanie' => 'GB',
			'Zimbabweeeee' => 'ZW',
			'Afganistan' => 'AF',
			'Bonaire' => 'BK',
			'Ciad' => 'TD',
			'Guineea Ecuatoriala' => 'GQ',
			'Eritreea' => 'ER',
			'Feroe' => 'FO',
			'Guineea' => 'GN',
			'Guineea Bissau' => 'GW',
			'Coasta de Fildes' => 'CI',
			'Marshall' => 'MH',
			'Martinica' => 'MQ',
			'Papua' => 'PG',
			'Solomon' => 'SB',
			'Surinam' => 'SR',
			'Trinidad Tobago' => 'TT',
			'Polinezia Franceza' => 'PF',
			'GRENADA' => 'GD',
			'GUAM' => 'GU',
			'Malaezia' => 'MY',
			//Antigua și Barbuda
			'Antigua' => 'AG',
			'Ins Bermude' => 'BM',
			'Bosnia-Herzegovina' => 'BA',
			'brunei' => 'BN',
			'Africa Centrala' => 'CF',
			'Guiana Franceză' => 'GF',
			'Irlanda' => 'IE',
			'MACAO' => 'MO',
			'YEMEN' => 'YE',
			'Insulele Mariane de Nord' => 'MP',
			'RWANDA' => 'RW',
			'Saint Barthélemy' => 'BL',
			'St.Kitts' => 'KN',
			'St Lucia' => 'LC',
			'St. Maarten' => 'MF',
			'St Vincent' => 'VC',
			'Samoa Vest' => 'WS',
			'Sao Tome' => 'ST',
			'SINGAPORE' => 'SG',
			'Olanda' => 'NL',
			'Antilele Olandeze' => 'AN',
			'Turks si Caicos' => 'TC',
			'Virgin Islands-United States' => 'VI',
			'Statele Unite ale Americii' => 'US',
			'UZBEKISTAN' => 'UZ',
			'Vatican' => 'VA',
			'Insula Norfolk' => 'NF',
			'Wallis si Futuna' => 'WF',
			'Samoa Americana' => 'AS',
			'Insula Jersey' => 'JE',
			'Guadelupa' => 'GP',
			'Falkland' => 'FK',
			'Insula Bouvet' => 'BV',
			'Micronezia' => 'FM',
			'Noua Caledonie' => 'NC',
			'Comore' => 'KM',
			'St Pierre Miq' => 'PM',
			//Tajikistan
			//'Tadjikistan' => 'TJ',
			'Tadjikistan' => 'TJ',

			'Teritoriile Palestiniene Ocupate' => 'PS',
			'Pitcairn' => 'PN',
			'Svalbard și Jan Mayen' => 'SJ',
			'U.S. Minor Outlying Islands' => 'UM',
			'Insula Man' => 'IM',
			'Insulele Åland' => 'AX',
			'Insula Heard și Insulele McDonald' => 'HM',
			'Georgia de Sud și Insulele Sandwich de Sud' => 'GS',
			'Teritoriul Britanic din Oceanul Indian' => 'IO',
		];

		// ---------------- added -------------------------
		$ret = [];
		foreach ($mapping ?:  [] as $k => $v)
		{
			$ret[trim(strtolower($k))] = $v;
		}

		// ---------------------- disabled --------------
		/*
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
		}*/
		return $ret;
	}
	
	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "tez_tour";
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
	public function request($method, $params = [], $filter = [])
	{
		if (!$this->ApiUrl)
			throw new \Exception('Api Url is missing!');
		
		$urlParams = [
			'auth' => [
				'user' => ($this->ApiUsername__ ?: $this->ApiUsername),
				'parola' => ($this->ApiPassword__ ?: $this->ApiPassword)
			],
			'cerere' => [
				'serviciu' => $method
			]
		];
		if (is_array($params))
		{
			foreach ($params as $key => $value)
				$urlParams['cerere']['param'][$key] = $value;
		}
		$user_agent = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727; .NET CLR 1.1.4322; .NET CLR 3.0.04506.30)";
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, ($this->ApiUrl__ ?: $this->ApiUrl));
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_POST, 1);
		q_curl_setopt_with_log($curlHandle, CURLOPT_POSTFIELDS, $this->p($urlParams));
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_USERAGENT, $user_agent);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);
		
		$data = q_curl_exec_with_log($curlHandle);
		if ($data === false)
		{
			$this->logError(["url" => $this->ApiUrl, "\$method" => $method, "params" => $params, "\$urlParams" => $urlParams]);
			throw new \Exception("Invalid response from server - " . curl_error($curlHandle));
		}

		return $data;
	}

	public function requestOffers($params, $filter = null, $doLogging = false)
	{
		//echo q_url_encode($params);
		// setup url for curl
		$url = 'http://www.travelio.ro/proxy-tez/?' . q_url_encode($params);

		/*
		$file = $this->getSimpleCacheFile($params, $url);
		if (file_exists($file))
		{	
			$data = file_get_contents($file);
			return $data;
		}
		else 
		{
			//qvardump($file);
			//q_die('block the request!');
		}
		*/

		$curlHeaders = [
			//"Host: www.travelio.ro",
			//"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			//"Accept-Encoding: gzip, deflate",
			//"Connection: keep-alive",
			//"Upgrade-Insecure-Requests: 1",
			//"Cache-Control: max-age=0",
			//"User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:63.0) Gecko/20100101 Firefox/63.0",
			//"Accept-Language: en-US,en;q=0.5"
		];

		// init curl
		$curlHandle = q_curl_init_with_log();
		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $url);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HTTPHEADER, $curlHeaders);
		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION , true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FRESH_CONNECT, true);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);

		//Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
		/*
		Host: www.travelio.ro
		Accept-Encoding: gzip, deflate
		Connection: keep-alive
		Upgrade-Insecure-Requests: 1
		Cache-Control: max-age=0
		User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:63.0) Gecko/20100101 Firefox/63.0
		Accept-Language: en-US,en;q=0.5
		*/

		// exec curl
		$data = q_curl_exec_with_log($curlHandle);

		if ($data === false)
		{
			$this->logError(["url" => $url, "params" => $params]);
			throw new \Exception("Invalid response from server - " . curl_error($curlHandle));
		}
		if ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"]))
			$this->logData(($filter["__booking_search__"] ? 'book' : 'setup') . ":search", ["url" => $url, "params" => $params, "respXML" => $data]);
		if ($doLogging)
			$this->logDataSimple("requestOffers", ["url" => $url, "params" => $params, "respXML" => $data]);

		/*
		$this->setRequestDumpData($filter, [
			"\$filter" => $filter,
			"\$url" => $url,
			"xmlRESP" => $data
		]);
		*/

		return $data;
	}
	
	public function getSimpleCacheFileForUrl($url, $params, $format = "xml")
	{
		$cacheDir = $this->getResourcesDir() . "cache/";
		if (!is_dir($cacheDir))
			qmkdir($cacheDir);
		return $cacheDir . "cache_" . md5($url . "|" . json_encode($params) . "|" . $format) . "." . $format;
	}
	
	public function getRequestMode()
	{
		return static::RequestModeCurl;
	}
	
	public function p($a,$cale=[])
	{
		$s='';
		if(is_array($a)){
			foreach ($a as $k=>$v){
				$nc=$cale;
				$nc[]=$k;
				$s.=$this->p($v,$nc);
			}
		}
		else{
			$key=array_shift($cale);
			while($k=array_shift($cale)){
				$key.="[{$k}]";
			}
			$s="{$key}=".urlencode($a).'&';
		}
		return $s;
	}
}