<?php

namespace Integrations\TourVisio_old;
use IntegrationTraits\TourOperatorRecordTrait;
use Omi\TF\TOInterface_Util;

class TourVisioNew extends \Omi\TF\TOInterface
{
	// ------added---------
	use TourOperatorRecordTrait;
	// -------------------
	use TOInterface_Util;
	
	const AirportTaxItmIndx = "7s";

	const TransferItmIndx = "6";

	protected $curl;

	protected $timeLimit = 30;

	public $useMultiInTop = false;

	protected static $DefaultCurrencyCode = 'EUR';

	protected static $Nationality = 'RO';

	protected static $FeesAndPaymentsRequests = [];

	protected $cacheTimeLimit = 60 * 60 * 24;

	protected static $CountriesLoaded = false;

	protected static $Countries = [];

	protected static $CountriesById = [];

	protected $apiActionByMethod = [
		"login" => "api/authenticationservice/login",
		"arrival-autocomplete" => "api/productservice/getarrivalautocomplete",
		"packages-departures" => "api/productservice/GetDepartures",
		"packages-arrivals" => "api/productservice/GetArrivals",
		'packages-departure-dates' => 'api/productservice/GetCheckinDates',
		'packages-departure-dates-duration' => 'api/productservice/GetNights',
		'packages-price-search' => 'api/productservice/PriceSearch',
		'individual-price-search' => 'api/productservice/PriceSearch',
		'get-offers' => 'api/productservice/GetOffers',
		'get-offer-details' => 'api/productservice/GetOfferDetails',
		'get-product-info' => 'api/productservice/GetProductInfo',
		'get-payment-types' => 'api/AgencyService/GetPaymentTypes',
		'booking-transaction-begin' => 'api/bookingservice/BeginTransaction',
		'booking-set-reservation-info' => 'api/bookingservice/setreservationinfo',
		'booking-transaction-commit' => 'api/bookingservice/committransaction'
	];

	public static $FileTypes = [
		'image' => 1,
		'pdf' => 2
	];

	public static $AutocompleteTypes = [
		'city' => 1,
		'hotel' => 2,
		'airport' => 3,
		'town' => 4,
		'village' => 5,
		'excursion' => 6,
		'category' => 7,
		'country' => 8,
		'transfer' => 9,
		'excursion_package' => 10
	];

	public static $ProductTypes = [
		'packages' => 1,
		'hotel' => 2,
		'flight' => 3,
		'excursion' => 4,
		'transfer' => 5,
		'tour' => 6,
		'cruise' => 7,
		'transport' => 8,
		'ferry' => 9,
		'visa' => 10,
		'additional_service' => 11,
		'insurance' => 12,
		'renting' => 13
	];

	public static $PassengerTypes = [
		'adult' => 1,
		'child' => 2,
		'infant' => 3,
		'senior' => 4,
		'student' => 5,
		'young' => 6,
		'military' => 7,
		'teacher' => 8
	];

	public static $MessageTypes = [
		'error' => 1,
		'success' => 2,
		'information' => 3,
		'warning' => 4,
	];

	public static $MessageCodes = [
		'operation_was_completed_succesfully' => 1
	];

	public static $LocationTypes = [
		'country' => 1,
		'city' => 2,
		'town' => 3,
		'village' => 4,
		'airport' => 5
	];

	public static $PaymentOptions = [
		'undefined' => -1,
		'cash' => 0,
		'open_account' => 1,
		'agency_credit' => 2
	];

	public static $PaymentStatus = [
		'none' => 1,
		'unpaid' => 2,
		'partly_paid' => 3,
		'paid' => 4,
		'over' => 5
	];

	public static $ReservationStatus = [
		'new' => 0,
		'modified' => 1,
		'cancel' => 2,
		'cancelx' => 3,
		'drafts' => 4
	];

	public static $ConfirmationStatus = [
		'request' => 0,
		'confirm' => 1,
		'no_confirm' => 2,
		'no_show' => 3
	];
	/**
	 * in destinations_qs we need to have the countries at least
	 */
	public static $Config = [
		'fibula_new' => [
			'destinations_qs' => [
				'bulgaria',
			],
			'reverse_search_correction' => true,
		],
		'kusadasi_new' => [
			'destinations_qs' => [
				'bulgaria',
			],
			# 'reverse_search_correction' => true,
		],
		'soley_tour' => [
			'destinations_qs' => [
				'bulgaria',
			],
			# 'reverse_search_correction' => true,
		],
	];

	public $infantAgeBelow = 2;
	
	protected $_loadedCountries = false;
	
	protected $_countries = null;
	
	protected $_translateCountriesCodes = [
		'TR-2' => '3-1'
	];

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

	/* ----------------------------end plane tickets operators---------------------*/
	
	public function getSoapClientByMethodAndFilter($method, $filter = null)
	{
		return false;
	}
	
	public function getCountriesMapping()
	{
		return [
			"ROM" => "RO",
			"TURCIA" => "TR",
			"EGIPT" => "EG",
			"EGY" => "EG",
			"EG" => "EG",
			"BULGARIA" => "BG",		
			"BUL" => "BG",
			"ES" => "ES",
			"TR" => "TR",
			"BG" => "BG",
			"GRECIA" => "GR",
			"SPAIN" => "ES",
			"CRO" => "HR",
			"TUN" => "TN",
			"GB" => "GB",
			"IT" => "IT",
			"CN" => "CN",
			"ID" => "ID"
		];
	}
	/**
	 * @param array $filter
	 * @return boolean
	 */
	public function api_testConnection(array $filter = null)
	{
		$this->login(true);
		$method = 'packages-departures';
		$params = [
			 "ProductType" => static::$ProductTypes['packages']
		];

		$filter = []; 
		$useCache = false;
		$logData = false;
		$headers = [];

		//string $method, array $params = [], array $filter = null, bool $useCache = false, bool $logData = false, array $headers = []
		list($countries, $info) = $this->requestOnTop($method, $params, $filter, $useCache, $logData, $headers);

		return $countries ? true : false;
	}

	public function getPackagesDeparturesAndArrivals($filter, $force = false, $getCountries = false)
	{
		list($depResp) = $this->request('packages-departures', [
			 "ProductType" => static::$ProductTypes['packages']
		], $filter, (!$force));

		$depLocations = isset($depResp['body']['locations']) ? $depResp['body']['locations'] : null;
		$destLocations = [];
		if ($depLocations && is_array($depLocations))
		{
			if (isset($depLocations['code']))
				$depLocations = [$depLocations];

			foreach ($depLocations ?: [] as $depLocation)
			{
				if ((!$getCountries) && (!in_array($depLocation['type'], [static::$LocationTypes['city'], static::$LocationTypes['town'], static::$LocationTypes['village']])))
				{
					if ($depLocation['type'] != static::$LocationTypes['country'])
						echo '<div style="color: red;">Skip departure - type is not accepted: ' . json_encode($depLocation) . '</div>';
					continue;
				}

				$depLocationsForReq = [[
					"Type" => $depLocation['type'],
					"Id" => $depLocation['id']
				]];

				list($destResp) = $this->request('packages-arrivals', [
					"ProductType" => static::$ProductTypes['packages'],
					'DepartureLocations' => $depLocationsForReq
				], $filter, (!$force));

				$destLocationsRes = isset($destResp['body']['locations']) ? $destResp['body']['locations'] : null;
				if ($destLocationsRes && is_array($destLocationsRes))
				{
					if (isset($destLocationsRes['code']))
						$destLocationsRes = [$destLocationsRes];

					foreach ($destLocationsRes ?: [] as $destLocation)
					{
						if ((!$getCountries) && (!in_array($destLocation['type'], [static::$LocationTypes['city'], static::$LocationTypes['town'], static::$LocationTypes['village']])))
						{
							if ($destLocation['type'] != static::$LocationTypes['country'])
								echo '<div style="color: red;">Skip destination - type is not accepted: ' . json_encode($destLocation) . '</div>';
							continue;
						}
						$destLocations[$destLocation['id']] = $destLocation;
					}
				}
			}
		}
		return [$depLocations, $destLocations];
	}

	public function getCharterCountries(array $filter = null)
	{
		list ($depLocations, $destLocations) = $this->getPackagesDeparturesAndArrivals($filter, false, true);

		$toProcessCountries = [];
		foreach ($depLocations ?: [] as $depLocation)
		{
			if ($depLocation['type'] && ($depLocation['type'] == static::$LocationTypes['country']))
			{
				$toProcessCountries[$depLocation['id']] = $depLocation;
			}
		}
		foreach ($destLocations ?: [] as $destLocation)
		{
			if ($destLocation['type'] && ($destLocation['type'] == static::$LocationTypes['country']))
			{
				$toProcessCountries[$destLocation['id']] = $destLocation;
			}
		}

		$countriesMapping = $this->getCountriesMapping();
		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		$filterCode = ($filter && $filter['Code']) ? $filter['Code'] : null;
		$ret = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count charter countries: %s', [$toProcessCountries ? count($toProcessCountries) : 'no_countries']);

		foreach ($toProcessCountries ?: [] as $c)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process country: %s', [$c['id'] . ' ' . $c['name']], 50);

			if (empty($c) || empty($c['id']) || (!($useCode = ($countriesMapping[$c['id']] ?: ($countriesMapping[$c['code']] ?: $c['code'])))))
			{
				//echo '<div style="color: red;">Skip country - no mapping or not ok format: ' . json_encode($c) . '</div>';
				\Omi\TF\TOInterface::markReportError($filter, 'Skip country because the format is not ok or it is not mapped: %s', [json_encode($c)], 50);
				continue;
			}

			if (($filterId && ($c['id'] != $filterId)) || ($filterCode && ($useCode != $filterCode)))
			{
				//echo '<div style="color: red;">Skip country - Filtered: ' . json_encode($c) . '</div>';
				continue;
			}

			$cObj = new \stdClass();
			$cObj->Id = $useCode;
			$cObj->_prevId = $this->getGeoIdf($c);
			$this->_countries[$cObj->_prevId] = $cObj;
			$cObj->OriginalId = $c["id"];
			$cObj->Provider = $c["provider"];
			$cObj->Code = $useCode;
			$cObj->Name = trim($c['name']);
			$ret[$cObj->Code] = $cObj;
		}
		return $ret;
	}

	function getIndividualCountries(array $filter = null)
	{
		$countries = [];

		if (($cqs = static::$Config[$this->TourOperatorRecord->Handle]['destinations_qs']))
		{
			$countriesMapping = $this->getCountriesMapping();
			foreach ($cqs ?: [] as $qs)
			{
				list($resp) = $this->request('arrival-autocomplete', [
					"ProductType" => static::$ProductTypes['hotel'],
					'Query' => $qs
				], $filter, true);

				$items = isset($resp['body']['items']) ? $resp['body']['items'] : null;

				if ($items && is_array($items))
				{
					if (isset($items['type']))
						$items = [$items];

					\Omi\TF\TOInterface::markReportData($filter, 'Count results: %s for $cqs: %s', [count($items), $cqs]);
					foreach ($items ?: [] as $itm)
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process item: %s', [$itm['country'] . ' ' . $itm['country']['id']], 50);

						if ($itm['country'] && $itm['country']['id'])
						{
							if (($useCode = $countriesMapping[$itm['country']['id']]))
							{
								if (!$itm['country']['type'])
									$itm['country']['type'] = static::$LocationTypes['country'];
								if (!$itm['country']['provider'] && $itm['provider'])
									$itm['country']['provider'] = $itm['provider'];
								$cObj = new \stdClass();
								$cObj->Id = $useCode;
								$cObj->_prevId = $this->getGeoIdf($itm['country']);
								$this->_countries[$cObj->_prevId] = $cObj;
								if ($itm['country']["id"])
									$cObj->OriginalId = $itm['country']["id"];
								$cObj->Provider = $itm['country']["provider"];
								$cObj->Code = $useCode;
								$cObj->Name = trim($itm['country']['name']);
								$countries[$cObj->Code] = $cObj;
							}
							else
							{
								\Omi\TF\TOInterface::markReportError($filter, 'Country not mapped: %s', [json_encode($itm)]);
							}
						}
						else 
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Item does not have country data: %s', [json_encode($itm)]);
						}
					}
				}
			}
		}

		return $countries;
	}

	/**
	 * Gets the countries.
	 * Response format: 
	 *		array of: Id,Name,Code
	 * @param array $filter Apply a filter like: [Id => , Name => , Code => ]
	 *						For more complex: [Name => ['like' => '...']]
	 */
	public function api_getCountries(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'countries');
		$ret = [];
		$charterCountries = $this->getCharterCountries($filter);
		foreach ($charterCountries ?: [] as $cId => $cObj)
			$ret[$cId] = $cObj;
		$individualCountries = $this->getIndividualCountries($filter);
		foreach ($individualCountries ?: [] as $cId => $cObj)
			$ret[$cId] = $cObj;
		
		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		$filterCode = ($filter && $filter['Code']) ? $filter['Code'] : null;
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
		return [($filterId || $filterCode) ? reset($ret) : $ret];
	}

	public function getIndividualRegions(array $filter = null)
	{
		$regions = [];
		if (($cqs = static::$Config[$this->TourOperatorRecord->Handle]['destinations_qs']))
		{
			$countriesMapping = $this->getCountriesMapping();
			foreach ($cqs ?: [] as $qs)
			{
				list($resp) = $this->request('arrival-autocomplete', [
					"ProductType" => static::$ProductTypes['hotel'],
					'Query' => $qs
				], $filter, true);

				$items = isset($resp['body']['items']) ? $resp['body']['items'] : [];

				\Omi\TF\TOInterface::markReportData($filter, 'Count results: %s for $cqs: %s', [count($items), $cqs]);

				if ($items && is_array($items))
				{
					if (isset($items['type']))
						$items = [$items];

					foreach ($items ?: [] as $itm)
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process item: %s', [$itm['city']['id'] . ' ' . $itm['city']['name']], 50);

						if ((!$itm['type']) || (($itm['type'] != static::$LocationTypes['city'])))
						{
							\Omi\TF\TOInterface::markReportError($filter, 
								'Item does not have the right type for region: %s, regions type is: %s', 
								[json_encode($itm), static::$LocationTypes['city']], 50);
							continue;
						}

						$country = null;
						if ($itm['country'] && $itm['country']['id'] && ($useCode = $countriesMapping[$itm['country']['id']]))
						{
							if (!$itm['country']['type'])
								$itm['country']['type'] = static::$LocationTypes['country'];
							if (!$itm['country']['provider'] && $itm['provider'])
								$itm['country']['provider'] = $itm['provider'];
							$cObj = new \stdClass();
							$cObj->Id = $useCode;
							$cObj->_prevId = $this->getGeoIdf($itm['country']);
							if ($itm['country']["id"])
								$cObj->OriginalId = $itm['country']["id"];
							$cObj->Provider = $itm['country']["provider"];
							$cObj->Code = $useCode;
							$cObj->Name = trim($itm['country']['name']);
							$country = $cObj;
						}

						if (!$country)
						{
							\Omi\TF\TOInterface::markReportError($filter, 
								'Item does not have country: %s', 
								[json_encode($itm), static::$LocationTypes['city']], 50);
							//echo '<div style="color: red;">No country for itm: ' . json_encode($itm) . '</div>';
							continue;
						}

						if (!$itm['city']['type'])
							$itm['city']['type'] = static::$LocationTypes['city'];
						if (!$itm['city']['provider'] && $itm['provider'])
							$itm['city']['provider'] = $itm['provider'];
						if (!$itm['city']['countryId'] && $country->OriginalId)
							$itm['city']['countryId'] = $country->OriginalId;

						$region = $itm['city'];
						$cObj = new \stdClass();
						$cObj->Id = $this->getGeoIdf($region);
						$cObj->OriginalId = $region["id"];
						$cObj->Provider = $region["provider"];
						$cObj->CountryId = $region["countryId"];
						$cObj->Name = trim($region['internationalName']) ?: trim($region['name']);
						$cObj->Alias = trim($region['name']);
						$cObj->Country = $country;

						$regions[$cObj->Id] = $cObj;
					}
				}
			}
		}
		return $regions;
	}
	
	public function getCharterRegions(array $filter = null)
	{
		list ($depLocations, $destLocations) = $this->getPackagesDeparturesAndArrivals($filter);

		$filterForCountries = ($hasCountryFilter = ($filter['CountryId'] || $filter['CountryCode'])) ? [
			"Id" => $filter['CountryId'],
			"Code" => $filter['CountryCode'],
		] : null;

		#list($countries) = $this->api_getCountries($filterForCountries);
		$countries = $this->getCharterCountries($filterForCountries);

		if ($countries && ($countries instanceof \stdClass) && $countries->Id)
			$countries = [$countries];

		$countriesById = [];
		foreach ($countries ?: [] as $c)
		{
			if ($c->Id)
				$countriesById[$c->Id] = $c;
		}

		$toProcessRegions = [];
		foreach ($depLocations ?: [] as $depLocation)
		{
			if ($depLocation['type'] && ($depLocation['type'] == static::$LocationTypes['city']))
				$toProcessRegions[$depLocation['id']] = $depLocation;
		}

		foreach ($destLocations ?: [] as $destLocation)
		{
			if ($destLocation['type'] && ($destLocation['type'] == static::$LocationTypes['city']))
				$toProcessRegions[$destLocation['id']] = $destLocation;
		}

		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		$ret = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count charter regions: %s', [$toProcessRegions ? count($toProcessRegions) : 'no_regions']);

		foreach ($toProcessRegions ?: [] as $region)
		{
			
			\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', [$region['id'] . ' ' . $region['name']], 50);
			if (empty($region) || empty($region['id']) || ($filterId && ($region['id'] != $filterId))|| (!$region['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($region)])))
			{
				\Omi\TF\TOInterface::markReportError($filter, "Region has incomplete data or filtered: %s", [json_encode($region)], 50);
				//qvardump($filterId, $countriesById, $region, $this->getCountryIdf($region));
				//echo "<div style='color: red;'>Regions incomplete data, no country or filtered: " . json_encode($region). "</div>";
				continue;
			}
			$cObj = new \stdClass();
			$cObj->Id = $this->getGeoIdf($region);
			$cObj->OriginalId = $region["id"];
			$cObj->Provider = $region["provider"];
			$cObj->CountryId = $region["countryId"];
			$cObj->Name = trim($region['internationalName']) ?: trim($region['name']);
			$cObj->Alias = trim($region['name']);
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}

		return $ret;
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
		$ret = [];
		$charterRegions = $this->getCharterRegions($filter);
		foreach ($charterRegions ?: [] as $rId => $region)
			$ret[$rId] = $region;
		$individualRegions = $this->getIndividualRegions($filter);
		foreach ($individualRegions ?: [] as $rId => $region)
			$ret[$rId] = $region;
		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
		return [($filterId) ? reset($ret) : $ret];
	}
	
	public function getIndividualCities(array $filter = null)
	{
		$toProcessRegions = [];
		$toProcessCities = [];
		$toProcessVillages = [];
		
		if (($cqs = static::$Config[$this->TourOperatorRecord->Handle]['destinations_qs']))
		{
			$countriesMapping = $this->getCountriesMapping();

			foreach ($cqs ?: [] as $qs)
			{
				list($resp) = $this->request('arrival-autocomplete', [
					"ProductType" => static::$ProductTypes['hotel'],
					'Query' => $qs
				], $filter, true);

				$items = isset($resp['body']['items']) ? $resp['body']['items'] : [];
				
				\Omi\TF\TOInterface::markReportData($filter, 'Count results: %s for $cqs: %s', [count($items), $cqs]);

				if ($items && is_array($items))
				{
					if (isset($items['type']))
						$items = [$items];
					
					foreach ($items ?: [] as $itm)
					{
						if ((!$itm['type']) || (!in_array($itm['type'], [static::$LocationTypes['city'], static::$LocationTypes['town'], static::$LocationTypes['village']])))
						{
							continue;
						}

						$country = null;
						if ($itm['country'] && $itm['country']['id'] && ($useCode = $countriesMapping[$itm['country']['id']]))
						{
							if (!$itm['country']['type'])
								$itm['country']['type'] = static::$LocationTypes['country'];
							if (!$itm['country']['provider'] && $itm['provider'])
								$itm['country']['provider'] = $itm['provider'];
							$cObj = new \stdClass();
							#$cObj->Id = $this->getGeoIdf($itm['country']);
							$cObj->_prevId = $this->getGeoIdf($itm['country']);
							$cObj->Id = $useCode;
							if ($itm['country']["id"])
								$cObj->OriginalId = $itm['country']["id"];
							$cObj->Provider = $itm['country']["provider"];
							$cObj->Code = $useCode;
							$cObj->Name = trim($itm['country']['name']);
							$country = $cObj;
						}

						$itm['__country'] = $country;

						if ($itm['city'])
						{
							if (!$itm['city']['type'])
								$itm['city']['type'] = static::$LocationTypes['city'];
							if (!$itm['city']['provider'] && $itm['provider'])
								$itm['city']['provider'] = $itm['provider'];
							if (!$itm['city']['countryId'] && $country->OriginalId)
								$itm['city']['countryId'] = $country->OriginalId;
							$toProcessRegions[$itm['city']['id']] = $itm['city'];
						}

						if ($itm['town'])
						{
							if (!$itm['town']['type'])
								$itm['town']['type'] = static::$LocationTypes['town'];
							if (!$itm['town']['provider'] && $itm['provider'])
								$itm['town']['provider'] = $itm['provider'];
							if (!$itm['town']['countryId'] && $country->OriginalId)
								$itm['town']['countryId'] = $country->OriginalId;
							if (!$itm['town']['parentId'] && $itm['city'])
								$itm['town']['parentId'] = $itm['city']['id'];
							$toProcessCities[$itm['town']['id']] = $itm['town'];
						}

						if ($itm['village'])
						{
							if (!$itm['village']['type'])
								$itm['village']['type'] = static::$LocationTypes['village'];
							if (!$itm['village']['provider'] && $itm['provider'])
								$itm['village']['provider'] = $itm['provider'];
							if (!$itm['village']['countryId'] && $country->OriginalId)
								$itm['village']['countryId'] = $country->OriginalId;
							if (!$itm['village']['parentId'])
							{
								if ($itm['town'])
									$itm['village']['parentId'] = $itm['town']['id'];
								else if ($itm['city'])
									$itm['village']['parentId'] = $itm['city']['id'];
							}
							$toProcessVillages[$itm['village']['id']] = $itm['village'];
						}
					}
				}
			}
		}
		
		$filterForCountries = ($hasCountryFilter = ($filter['CountryId'] || $filter['CountryCode'])) ? [
			"Id" => $filter['CountryId'],
			"Code" => $filter['CountryCode'],
		] : null;

		#list($countries) = $this->api_getCountries($filterForCountries);
		$countries = $this->getIndividualCountries($filterForCountries);

		if ($countries && ($countries instanceof \stdClass) && $countries->Id)
			$countries = [$countries];

		$countriesById = [];
		foreach ($countries ?: [] as $c)
		{
			if ($c->Id)
				$countriesById[$c->Id] = $c;
		}

		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		$ret = [];
		$regions = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count individual cities: %s', [$toProcessCities ? count($toProcessCities) : 'no_cities']);
		
		foreach ($toProcessCities ?: [] as $city)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$city['id'] . ' ' . $city['name']], 50);

			if (empty($city) || empty($city['id']) || ($filterId && ($city['id'] != $filterId))|| (!$city['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($city)])))
			{
				\Omi\TF\TOInterface::markReportError($filter, "City has incomplete data or filtered: %s", [json_encode($city)], 50);
				//qvardump($filterId, $countriesById, $city);
				//echo "<div style='color: red;'>City incomplete data, no country or filtered: " . json_encode($city). "</div>";
				continue;
			}

			$region = null;
			if ($city['parentId'] && ($city['parentId'] != $city['countryId']))
			{
				if (!isset($toProcessRegions[$city['parentId']]))
				{
					//echo "<div style='color: red;'>Region not found for city: " . json_encode($city). "</div>";
					\Omi\TF\TOInterface::markReportError($filter, "Region not found for city: %s", [json_encode($city)], 50);
					continue;
				}
				$regionId = $this->getParentIdf($city);
				$region = $regions[$regionId] ?: ($regions[$regionId] = new \stdClass());
				$region->Id = $regionId;
			}

			$cObj = new \stdClass();
			$cObj->Id = $this->getGeoIdf($city);
			$cObj->OriginalId = $city["id"];
			$cObj->Provider = $city["provider"];
			$cObj->CountryId = $city["countryId"];
			$cObj->Name = trim($city['internationalName']) ?: trim($city['name']);
			$cObj->Alias = trim($city['name']);
			if ($region)
				$cObj->County = $region;
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count individual cities (villages): %s', [$toProcessVillages ? count($toProcessVillages) : 'no_cities']);
		
		foreach ($toProcessVillages ?: [] as $village)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$village['id'] . ' ' . $village['name']], 50);

			if (empty($village) || empty($village['id']) || ($filterId && ($village['id'] != $filterId))|| (!$village['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($village)])))
			{
				\Omi\TF\TOInterface::markReportError($filter, "City has incomplete data or filtered: %s", [json_encode($village)], 50);
				//echo "<div style='color: red;'>Village filtered or no country: " . json_encode($village) . "</div>";
				continue;
			}

			if ((!$village['parentId']) || ($village['parentId'] == $village['countryId']) || (!($city = $ret[$this->getParentIdf($village)])))
			{
				//qvardump('$village', $village, $ret, $this->getParentIdf($village));
				//echo "<div style='color: red;'>Village not well formated: " . json_encode($village) . "</div>";
				\Omi\TF\TOInterface::markReportError($filter, "Village not well formatted: %s", [json_encode($village)], 50);
				continue;
			}

			$cObj = new \stdClass();
			$cObj->Id = $this->getGeoIdf($village);
			$cObj->OriginalId = $village["id"];
			$cObj->Provider = $village["provider"];
			$cObj->CountryId = $village["countryId"];
			$cObj->Name = trim($village['internationalName']) ?: trim($village['name']);
			$cObj->Alias = trim($village['name']);
			$cObj->FakeFromVillage = $cObj->Id;
			if ($city->County)
				$cObj->County = $city->County;
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}

		\Omi\TF\TOInterface::markReportData($filter, 'Count individual cities (regions): %s', [$toProcessVillages ? count($toProcessVillages) : 'no_cities']);

		foreach ($toProcessRegions ?: [] as $region)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', [$region['id'] . ' ' . $region['name']], 50);

			/*
			if (empty($region) || empty($region['id']) || (isset($regions[$region['id']])) || ($filterId && ($region['id'] != $filterId))|| (!$region['countryId']) || 
				(!($country = $countriesById[$region['countryId']])))
			{
				echo "<div style='color: red;'>Region incomplete data or processed or filtered: " . json_encode($region). "</div>";
				continue;
			}
			*/

			if (empty($region) || empty($region['id']) || ($filterId && ($region['id'] != $filterId))|| (!$region['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($region)])))
			{
				//echo "<div style='color: red;'>Region incomplete data or processed or filtered: " . json_encode($region). "</div>";
				\Omi\TF\TOInterface::markReportError($filter, "City has incomplete data or filtered: %s", [json_encode($region)], 50);
				continue;
			}

			$regionObj = new \stdClass();
			$regionObj->Id = $this->getGeoIdf($region);

			$cObj = new \stdClass();
			$cObj->Id = $regionObj->Id;
			$cObj->OriginalId = $region["id"];
			$cObj->Provider = $region["provider"];
			$cObj->CountryId = $region["countryId"];
			$cObj->Name = trim($region['internationalName']) ?: trim($region['name']);
			$cObj->Alias = trim($region['name']);
			$cObj->County = $regionObj;
			$cObj->FakeFromRegion = $regionObj->Id;
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}

		return $ret;
	}

	public function getCharterCities(array $filter = null)
	{
		list ($depLocations, $destLocations) = $this->getPackagesDeparturesAndArrivals($filter);

		$filterForCountries = ($hasCountryFilter = ($filter['CountryId'] || $filter['CountryCode'])) ? [
			"Id" => $filter['CountryId'],
			"Code" => $filter['CountryCode'],
		] : null;

		#list($countries) = $this->api_getCountries($filterForCountries);
		$countries = $this->getCharterCountries($filterForCountries);

		if ($countries && ($countries instanceof \stdClass) && $countries->Id)
			$countries = [$countries];

		$countriesById = [];
		foreach ($countries ?: [] as $c)
		{
			if ($c->Id)
				$countriesById[$c->Id] = $c;
		}

		$toProcessRegions = [];
		$toProcessCities = [];
		$toProcessVillages = [];
		foreach ($depLocations ?: [] as $depLocation)
		{
			if ($depLocation['type'] && ($depLocation['type'] == static::$LocationTypes['city']))
				$toProcessRegions[$depLocation['id']] = $depLocation;
			if ($depLocation['type'] && ($depLocation['type'] == static::$LocationTypes['town']))
				$toProcessCities[$depLocation['id']] = $depLocation;
			if ($depLocation['type'] && ($depLocation['type'] == static::$LocationTypes['village']))
				$toProcessVillages[$depLocation['id']] = $depLocation;
		}

		foreach ($destLocations ?: [] as $destLocation)
		{
			if ($destLocation['type'] && ($destLocation['type'] == static::$LocationTypes['city']))
				$toProcessRegions[$destLocation['id']] = $destLocation;
			if ($destLocation['type'] && ($destLocation['type'] == static::$LocationTypes['town']))
				$toProcessCities[$destLocation['id']] = $destLocation;
			if ($destLocation['type'] && ($destLocation['type'] == static::$LocationTypes['village']))
				$toProcessVillages[$destLocation['id']] = $destLocation;
		}

		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		$ret = [];
		$regions = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count charter cities: %s', [$toProcessCities ? count($toProcessCities) : 'no_cities']);
		
		foreach ($toProcessCities ?: [] as $city)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$city['id'] . ' ' . $city['name']], 50);

			if (empty($city) || empty($city['id']) || ($filterId && ($city['id'] != $filterId))|| (!$city['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($city)])))
			{
				#qvardump($filterId, $countriesById, $city);
				//echo "<div style='color: red;'>City incomplete data, no country or filtered: " . json_encode($city). "</div>";
				\Omi\TF\TOInterface::markReportError($filter, "City has incomplete data or filtered: %s", [json_encode($city)], 50);
				continue;
			}

			$region = null;
			if ($city['parentId'] && ($city['parentId'] != $city['countryId']))
			{
				if (!isset($toProcessRegions[$city['parentId']]))
				{
					\Omi\TF\TOInterface::markReportError($filter, "Region not found for city: %s", [json_encode($city)], 50);
					//echo "<div style='color: red;'>Region not found for city: " . json_encode($city). "</div>";
					continue;
				}
				$regionId = $this->getParentIdf($city);
				$region = $regions[$regionId] ?: ($regions[$regionId] = new \stdClass());
				$region->Id = $regionId;
			}

			$cObj = new \stdClass();
			$cObj->Id = $this->getGeoIdf($city);
			$cObj->OriginalId = $city["id"];
			$cObj->Provider = $city["provider"];
			$cObj->CountryId = $city["countryId"];
			$cObj->Name = trim($city['internationalName']) ?: trim($city['name']);
			$cObj->Alias = trim($city['name']);
			if ($region)
				$cObj->County = $region;
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}

		\Omi\TF\TOInterface::markReportData($filter, 'Count charter cities (from villages): %s', [$toProcessVillages ? count($toProcessVillages) : 'no_cities']);
		
		foreach ($toProcessVillages ?: [] as $village)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$village['id'] . ' ' . $village['name']], 50);
			if (empty($village) || empty($village['id']) || ($filterId && ($village['id'] != $filterId))|| (!$village['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($village)])))
			{
				\Omi\TF\TOInterface::markReportError($filter, "City (village) has incomplete data or filtered: %s", [json_encode($village)], 50);
				//echo "<div style='color: red;'>Village filtered or no country: " . json_encode($village) . "</div>";
				continue;
			}

			if ((!$village['parentId']) || ($village['parentId'] == $village['countryId']) || (!($city = $ret[$this->getParentIdf($village)])))
			{
				#qvardump('$village', $village, $ret, $this->getParentIdf($village));
				//echo "<div style='color: red;'>Village not well formated: " . json_encode($village) . "</div>";
				\Omi\TF\TOInterface::markReportError($filter, "City (village) not well formed: %s", [json_encode($village)], 50);
				continue;
			}

			$cObj = new \stdClass();
			$cObj->Id = $this->getGeoIdf($village);
			$cObj->OriginalId = $village["id"];
			$cObj->Provider = $village["provider"];
			$cObj->CountryId = $village["countryId"];
			$cObj->Name = trim($village['internationalName']) ?: trim($village['name']);
			$cObj->Alias = trim($village['name']);
			$cObj->FakeFromVillage = $cObj->Id;
			if ($city->County)
				$cObj->County = $city->County;
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}

		\Omi\TF\TOInterface::markReportData($filter, 'Count charter cities (from regions): %s', [$toProcessRegions ? count($toProcessRegions) : 'no_cities']);
		
		foreach ($toProcessRegions ?: [] as $region)
		{
			/*
			if (empty($region) || empty($region['id']) || (isset($regions[$region['id']])) || ($filterId && ($region['id'] != $filterId))|| (!$region['countryId']) || 
				(!($country = $countriesById[$region['countryId']])))
			{
				echo "<div style='color: red;'>Region incomplete data or processed or filtered: " . json_encode($region). "</div>";
				continue;
			}
			*/

			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$region['id'] . ' ' . $region['name']], 50);

			if (empty($region) || empty($region['id']) || ($filterId && ($region['id'] != $filterId))|| (!$region['countryId']) || 
				(!($country = $countriesById[$this->getCountryIdf($region)])))
			{
				\Omi\TF\TOInterface::markReportError($filter, "City (region) has incomplete data or filtered: %s", [json_encode($region)], 50);
				//echo "<div style='color: red;'>Region incomplete data or processed or filtered: " . json_encode($region). "</div>";
				continue;
			}

			$regionObj = new \stdClass();
			$regionObj->Id = $this->getGeoIdf($region);

			$cObj = new \stdClass();
			$cObj->Id = $regionObj->Id;
			$cObj->OriginalId = $region["id"];
			$cObj->Provider = $region["provider"];
			$cObj->CountryId = $region["countryId"];
			$cObj->Name = trim($region['internationalName']) ?: trim($region['name']);
			$cObj->Alias = trim($region['name']);
			$cObj->County = $regionObj;
			$cObj->FakeFromRegion = $regionObj->Id;
			$cObj->Country = $country;
			$ret[$cObj->Id] = $cObj;
		}
		return $ret;
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
		$ret = [];
		$chartersCities = $this->getCharterCities($filter);
		foreach ($chartersCities ?: [] as $cId => $city)
			$ret[$cId] = $city;
		$individualCities = $this->getIndividualCities($filter);
		foreach ($individualCities ?: [] as $cId => $city)
			$ret[$cId] = $city;
		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
		return [($filterId) ? reset($ret) : $ret];
	}

	/**
	 * 
	 */
	public function api_getAvailabilityDates(array $filter = null)
	{
		$ret = null;
		if ($filter['type'] == 'charter')
		{
			$ret = $this->getChartersAvailabilityDates($filter);
		}

		return [$ret];
	}

	public function getChartersAvailabilityDates($filter = null)
	{
		$force = false;
		list($depResp) = $this->request('packages-departures', [
			 "ProductType" => static::$ProductTypes['packages']
		], $filter, (!$force));
		
		list($countries) = $this->api_getCountries();

		$shownCountries = [];
		$countriesByOriginalId = [];
		$countriesById = [];
		foreach ($countries ?: [] as $c)
		{
			$countriesByOriginalId[$c->OriginalId] = $c;
			$countriesById[$c->Id] = $c;
		}

		$retTransports = [];
		$transportType = 'plane';
		$depLocations = isset($depResp['body']['locations']) ? $depResp['body']['locations'] : null;
		$destLocations = null;
		if ($depLocations && is_array($depLocations))
		{
			if (isset($depLocations['code']))
				$depLocations = [$depLocations];

			foreach ($depLocations ?: [] as $depLocation)
			{
				if (!in_array($depLocation['type'], [static::$LocationTypes['city'], static::$LocationTypes['town'], static::$LocationTypes['village']]))
				{
					if ($depLocation['type'] != static::$LocationTypes['country'])
						echo '<div style="color: red;">Skip departure - type is not accepted: ' . json_encode($depLocation) . '</div>';
					continue;
				}

				$depLocationsForReq = [[
					"Type" => $depLocation['type'],
					"Id" => $depLocation['id']
				]];

				list($destResp) = $this->request('packages-arrivals', [
					"ProductType" => static::$ProductTypes['packages'],
					'DepartureLocations' => $depLocationsForReq
				], $filter, (!$force));

				$destLocations = isset($destResp['body']['locations']) ? $destResp['body']['locations'] : null;
				if ($destLocations && is_array($destLocations))
				{
					if (isset($destLocations['code']))
						$destLocations = [$destLocations];

					/*
					foreach ($destLocations ?: [] as $destLocation)
					{
						
					}
					*/

					foreach ($destLocations ?: [] as $destLocation)
					{
						if (!in_array($destLocation['type'], [static::$LocationTypes['city'], static::$LocationTypes['town'], static::$LocationTypes['village']]))
						{
							if ($destLocation['type'] != static::$LocationTypes['country'])
								echo '<div style="color: red;">Skip destination - type is not accepted: ' . json_encode($destLocation) . '</div>';
							continue;
						}

						$arrivalLocations = [[
							"Type" => $destLocation['type'],
							"Id" => $destLocation['id']
						]];

						list($datesResp) = $this->request('packages-departure-dates', [
							"ProductType" => static::$ProductTypes['packages'],
							'DepartureLocations' => $depLocationsForReq,
							'ArrivalLocations' => $arrivalLocations
						], $filter, (!$force));

						$departureDates = isset($datesResp['body']['dates']) ? $datesResp['body']['dates'] : null;

						if ($departureDates)
						{
							if (is_scalar($departureDates))
								$departureDates = [$departureDates];

							foreach ($departureDates ?: [] as $departureDate)
							{
								$depDate = date("Y-m-d", strtotime($departureDate));
								list($durationResp) = $this->request('packages-departure-dates-duration', [
									"ProductType" => static::$ProductTypes['packages'],
									'CheckIn' => $depDate,
									'DepartureLocations' => $depLocationsForReq,
									'ArrivalLocations' => $arrivalLocations
								], $filter, (!$force));

								$nightsRet = isset($durationResp['body']['nights']) ? $durationResp['body']['nights'] : null;

								if ($nightsRet)
								{
									if (is_scalar($nightsRet))
										$nightsRet = [$nightsRet];
									
									if (($country = $countriesByOriginalId[$destLocation["countryId"]]))
									{
										if (!$shownCountries[$destLocation["countryId"]])
										{
											#echo "<div style='color: blue;'>Country " . $country->Name . " -> " . $destLocation["countryId"] . "</div>";
										}
										$shownCountries[$destLocation["countryId"]] = $destLocation["countryId"];
									}
									else
									{
										if (!$shownCountries[$destLocation["countryId"]])
										{
											#echo "<div style='color: red;'>No country for " . $destLocation["countryId"] . "</div>";
										}
										$shownCountries[$destLocation["countryId"]] = $destLocation["countryId"];	
									}
									
									foreach ($nightsRet ?: [] as $night)
									{
										$depLocationIdf = $this->getGeoIdf($depLocation);
										$destinationIdf = $this->getGeoIdf($destLocation);

										$transportId = $transportType . "~city|" . $depLocationIdf . "~city|" . $destinationIdf;
										if (!($transport = $retTransports[$transportId]))
										{
											$transport = new \stdClass();
											$transport->Id = $transportId;
											$transport->TransportType = $transportType;
											$transport->From = new \stdClass();
											$transport->From->City = new \stdClass();
											$transport->From->City->Id = $depLocationIdf;
											$transport->To = new \stdClass();
											$transport->To->City = new \stdClass();
											$transport->To->City->Id = $destinationIdf;
											$transport->Dates = [];
											$retTransports[$transportId] = $transport;
										}

										if (!($dateObj = $transport->Dates[$depDate]))
										{
											$dateObj = new \stdClass();
											$dateObj->Date = $depDate;
											$dateObj->Nights = [];
											$transport->Dates[$depDate] = $dateObj;
										}

										$dateObj->Nights[$night] = new \stdClass();
										$dateObj->Nights[$night]->Nights = $night;
									}
								}

								
							}
						}
					}
				}
			}
		}

		#qvardump('$retTransports', $retTransports);
		#q_die();

		return $retTransports;
	}

	public function getCountryIdf($geoData, $provider = null)
	{
		if ($this->_loadedCountries === false)
		{
			$this->api_getCountries();
			$this->_loadedCountries = true;
		}

		$gcid = $geoData['countryId'] . '-' . $geoData['provider'];
		$ret = null;
		if (($country = $this->_countries[$gcid]))
		{
			$ret = $country->Code;
		}
		else if (($gcidt = $this->_translateCountriesCodes[$gcid]) && ($country = $this->_countries[$gcidt]))
		{
			$ret = $country->Code;
		}
		else if ($provider)
		{
			$newgcid = $geoData['countryId'] . '-' . $provider;
			if (($country = $this->_countries[$newgcid]))
			{
				$ret = $country->Code;
			}
			else if (($newgcidt = $this->_translateCountriesCodes[$newgcid]) && ($country = $this->_countries[$newgcidt]))
			{
				$ret = $country->Code;
			}
		}

		return $ret;
	}

	public function getParentIdf($geoData)
	{
		return $geoData['parentId'] . '-' . $geoData['provider'] . ($geoData['countryId'] ? '-' . $geoData['countryId'] : '');
	}

	public function getGeoIdf($geoData)
	{
		return $geoData['id'] . '-' . $geoData['provider'] . (($geoData['type'] != static::$LocationTypes['country']) ? '-' . $geoData['countryId'] : '');
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
	public function api_getHotelDetails(array $filter = null)
	{
		$_mainEx = false;
		$allRequests = null;
		try
		{
			$force = false;
			$showDump = true;
			if (!($filterHotelId = ($filter['hotelId'] ?: $filter['HotelId'])))
				throw new \Exception("Hotel id not provided!");

			list($hotelId, $provider) = explode("-", $filterHotelId);
			if ((!$hotelId) || (!$provider))
				throw new \Exception("Hotel id or provider is missing");

			$productParams = [
				"culture" => "en-US",
				"productType" => static::$ProductTypes['hotel'],
				"product" => $hotelId,
				"ownerProvider" => $provider
			];
			list($productDetails, , $productDetailsEx, $allRequests) = $this->request('get-product-info', $productParams, $filter, (!$force));

			if ($productDetailsEx)
				throw $productDetailsEx;

			if (!($apiHotel = $productDetails['body']['hotel']))
				throw new \Exception('hotel data not provided!');

			$retHotel = new \stdClass();
			$retHotel->Id = $hotelId . '-' . $provider;
			$retHotel->Name = trim($apiHotel['name']);
			$retHotel->Stars = $apiHotel['stars'];

			$locationCountry = $apiHotel['country'];
			$locationCity = $apiHotel['town'];
			$locationZone = $apiHotel['city'];
			$locationVillage = $apiHotel['village'];
			
			if ((!$locationCountry) && $apiHotel['location'] && $apiHotel['location']['countryId'])
			{
				$locationCountry = [
					'id' => $apiHotel['location']['countryId'],
				];
			}

			if ((!$locationCountry) || ((!$locationCity) && (!$locationZone) && (!$locationVillage)))
			{
				if ($showDump)
					echo '<div style="color: red;">Api hotel not ok formatted: missing geo: ' . json_encode($apiHotel) . '</div>';
				return null;
			}

			if (!static::$CountriesLoaded)
			{
				list(static::$Countries) = $this->api_getCountries();
				foreach (static::$Countries ?: [] as $c)
					static::$CountriesById[$c->Id] = $c;
				static::$CountriesLoaded = true;
			}

			#$location = ($locationCity ?: ($locationZone ?: $locationVillage));
			$location = ($locationVillage ?: ($locationCity ?: $locationZone));

			if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $location['provider']))
				$location['provider'] = $apiHotel['location']['provider'];

			if ((!$location['provider']) && $apiHotel['provider'])
				$location['provider'] = $apiHotel['provider'];

			$country = null;
			if (trim($locationCountry['id']) && trim($apiHotel['provider']) && ($cid = $this->getCountryIdf(["countryId" => trim($locationCountry['id'])], trim($apiHotel['provider']))))
				$country = static::$CountriesById[$cid] ?: static::$Countries[$locationCountry['id']];
			else if (($cicode = trim($locationCountry['internationalCode'])))
				$country = static::$Countries[$locationCountry['internationalCode']];

			if (!$country)
			{
				if ($showDump)
					echo '<div style="color: red;">Country not found for location 3. ' . json_encode($locationCountry). "|" . json_encode($location) . '</div>';
				#q_die('-----');
				return;
			}

			$location['countryId'] = $country->OriginalId;
			$locationIdf = $this->getGeoIdf($location);

			#qvardump('$locationVillage, $locationZone, $locationCity', $locationVillage, $locationZone, $locationCity);

			$city = new \stdClass();
			$city->Id = $locationIdf;
			$city->Name = $location['name'];
			if ($locationVillage)
				$city->FakeFromVillage = $locationIdf;
			else if ($locationZone)
				$city->FakeFromRegion = $locationIdf;
			if ($locationZone)
			{
				if (!$locationZone['provider'])
					$locationZone['provider'] = $location['provider'];
				if (!$locationZone['countryId'])
					$locationZone['countryId'] = $country->OriginalId;
				$city->County = new \stdClass();
				$city->County->Id = $this->getGeoIdf($locationZone);
				$city->County->Name = $locationZone['name'];
			}
			$city->Country = $country;

			$retHotel->Address = new \stdClass();
			$retHotel->Address->City = $city;

			$geolocation = $apiHotel['address']['geolocation'] ?: $apiHotel['geolocation'];
			if (isset($geolocation['longitude']) && isset($geolocation['latitude']))
			{
				$retHotel->Address->Longitude = $geolocation['longitude'];
				$retHotel->Address->Latitude = $geolocation['latitude'];
			}

			$addrDetailsParts = [];
			if (isset($apiHotel['address']['street']) && is_scalar($apiHotel['address']['street']) && ($street = trim($apiHotel['address']['street'])))
				$addrDetailsParts[$street] = $street;

			if (isset($apiHotel['address']['addressLines']) && is_array($apiHotel['address']['addressLines']))
			{
				foreach ($apiHotel['address']['addressLines'] ?: [] as $addrLine)
				{
					$addrLine = trim($addrLine);
					if ($addrLine && (!isset($addrDetailsParts[$addrLine])))
						$addrDetailsParts[$addrLine] = $addrLine;
				}
			}

			if (count($addrDetailsParts))
				$retHotel->Address->Details = implode(", ", $addrDetailsParts);

			if (($hp = trim($apiHotel['homePage'])))
			{
				$retHotel->ContactPerson->WebAddress = $hp;
			}

			if (($phone = $apiHotel['phoneNumber']))
			{
				$retHotel->ContactPerson = new \stdClass();
				$retHotel->ContactPerson->Phone = $phone;
			}

			$images = [];
			if ($apiHotel['thumbnailFull'] && is_scalar($apiHotel['thumbnailFull']))
				$images[$apiHotel['thumbnailFull']] = $apiHotel['thumbnailFull'];

			$hotelContent = "";
			if (isset($apiHotel['description']['text']) && is_scalar($apiHotel['description']['text']) && (trim($apiHotel['description']['text'])))
				$hotelContent .= $apiHotel['description']['text'];

			if (($seasons = $apiHotel['seasons']) && is_array($seasons))
			{
				if (isset($seasons['name']))
					$seasons = [$seasons];

				#$seasonsByName = [];
				foreach ($seasons ?: [] as $s)
				{
					#$seasonsByName[$s['name']][] = $s;
					$this->setupGeneralSeasonDataOnHotel($s, $retHotel, $hotelContent, $images);
				}

				#if (isset($seasonsByName['General'][0]) && ($generalSeason = $seasonsByName['General'][0]) && is_array($generalSeason))
				#	$this->setupGeneralSeasonDataOnHotel($generalSeason, $retHotel, $hotelContent, $images);

				#if (isset($seasonsByName['Facility Season'][0]) && ($facilitySeason = $seasonsByName['Facility Season'][0]) && is_array($facilitySeason))
				#	$this->setupGeneralSeasonDataOnHotel($generalSeason, $retHotel, $hotelContent, $images);

				#qvardump($hotelContent);
			}

			if (strlen($hotelContent))
			{
				if (!$retHotel->Content)
					$retHotel->Content = new \stdClass();
				$retHotel->Content->Content = $hotelContent;
			}

			if (count($images))
			{
				if (!$retHotel->Content)
					$retHotel->Content = new \stdClass();
				$retHotel->Content->ImageGallery = new \stdClass();
				$retHotel->Content->ImageGallery->Items = [];
				foreach ($images ?: [] as $image)
				{
					$imgObj = new \stdClass();
					$imgObj->RemoteUrl = $image;
					$retHotel->Content->ImageGallery->Items[] = $imgObj;
				}
			}
		}
		catch (\Exception $ex)
		{
			$_mainEx = $ex;
		}
		
		if ($_mainEx)
			$retHotel = null;

		return [$retHotel, false, $allRequests];
	}

	public function setupGeneralSeasonDataOnHotel($generalSeason, $retHotel, &$hotelContent, &$images = [])
	{
		if ($generalSeason['textCategories'] && is_array($generalSeason['textCategories']))
		{
			if (isset($generalSeason['textCategories']['name']))
				$generalSeason['textCategories'] = [$generalSeason['textCategories']];

			foreach ($generalSeason['textCategories'] ?: [] as $tc)
			{
				$presentations_texts = "";
				if (($presentations = $tc['presentations']) && is_array($presentations))
				{
					if (isset($presentations['text']))
						$presentations = [$presentations];
					foreach ($presentations ?: [] as $pres)
					{
						if (($tt = trim($pres['text'])))
							$presentations_texts .= $tt . '<br/>';
					}
				}

				if (strlen($presentations_texts))
					$hotelContent .= $tc['name'] . '<br/>' . $presentations_texts;
			}
		}

		if (($facilityCategs = $generalSeason['facilityCategories']) && is_array($facilityCategs))
		{
			if (isset($facilityCategs['name']))
				$facilityCategs = [$facilityCategs];

			foreach ($facilityCategs ?: [] as $fc)
			{
				if (isset($fc['facilities']['name']))
					$fc['facilities'] = [$fc['facilities']];
				
				foreach ($fc['facilities'] ?: [] as $facility)
				{
					if (!$facility['isPriced'])
					{
						if (!$retHotel->Facilities)
							$retHotel->Facilities = [];
						$facilityObj = new \stdClass();
						$facilityObj->Name = $facility['name'];
						$facilityObj->Id = md5($facility['name']);
						$retHotel->Facilities[$facilityObj->Id] = $facilityObj;
					}
				}
			}
		}

		$imagesExtensions = ['jpg', 'png'];
		if ($generalSeason['mediaFiles'] && is_array($generalSeason['mediaFiles']))
		{
			if (isset($generalSeason['mediaFiles']['fileType']))
				$generalSeason['mediaFiles'] = [$generalSeason['mediaFiles']];
			foreach ($generalSeason['mediaFiles'] ?: [] as $mf)
			{
				if (($mf['fileType'] == static::$FileTypes['image']) || (($ext = pathinfo($mf['urlFull'], PATHINFO_EXTENSION)) && in_array($ext, $imagesExtensions)))
				{
					$images[$mf['urlFull']] = $mf['urlFull'];
				}
			}
		}
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');
		\Omi\TF\TOInterface::markReportData($filter, 'tour operator does not have static hotels support');
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
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
	public function api_getOfferAvailability(array $filter = null)
	{
		//qvardump("api_getOfferAvailability", $filter);
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers(array $filter = null)
	{
		# RegionId[string]: "23494-2-3"
		# regionId[string]: "23494-2-3"
		
		$serviceType = $this->checkApiOffersFilter($filter);
		$ret = null;
		//$err, $alreadyProcessed, $toSaveProcesses
		$ex = null;
		$t1 = microtime(true);
		$rawRequests = [];
		switch($serviceType)
		{
			case 'charter' : 
			{
				#if (isset($filter['RegionId']) && ($filter['RegionId'] === '23494-2-3'))
				#	$filter['RegionId'] = '4-1-3';
				#if (isset($filter['regionId']) && ($filter['regionId'] === '23494-2-3'))
				#	$filter['regionId'] = '4-1-3';

				
				list($ret, $ex, $rawRequests) =  $this->getCharterOffers($filter);
				
				# if (($_SERVER['REMOTE_ADDR'] === Dev_Ip))
				/*
				{
					ob_start();
					qvar_dump('api_getOffers', $filter, $ret, $ex, $rawRequests);
					file_put_contents("/home/tfc_portal_travelfuse_ro/api_getOffers_dbg_".sha1(json_encode($filter)).".html", ob_get_clean());
					# die;
				}
				*/
				break;
			}
			case 'hotel' :
			case 'individual' :
			{
				list($ret, $ex, $rawRequests) = $this->getIndividualOffers($filter);
				break;
			}
		}

		return [$ret, $ex, false, $rawRequests];
	}

	private function checkApiOffersFilter(&$filter)
	{
		$serviceType = ($filter && $filter['serviceTypes']) ? reset($filter['serviceTypes']) : null;
		if (!$serviceType)
			throw new \Exception("Service type is mandatory!");

		// check in is mandatory
		if (!$filter["checkIn"])
			throw new \Exception("CheckIn date is mandatory!");

		// number of days / nights are mandatory
		if (!$filter["days"] || (!is_numeric($filter["days"])))
			throw new \Exception("Duration is mandatory!");

		// rooms are mandatory
		if (!$filter["rooms"])
			throw new \Exception("Rooms are mandatory");

		// adults are mandatory
		if (isset($filter["rooms"]["adults"]))
			$filter["rooms"] = [$filter["rooms"]];

		// number of adults are mandatory
		// maximum number of children allowed is 3
		foreach ($filter["rooms"] ?: [] as $room)
		{
			if (!isset($room["adults"]))
				throw new \Exception("Adults count is mandatory!");
			if ($room["children"] && ($room["children"] > 3))
				throw new \Exception("Child count must be under 4!");
		}

		return $serviceType;
	}

	public function getCharterOffers(array $filter = null)
	{
		$__ts = microtime(true);

		$_mainEx = null;
		$_allRequests = [];

		$force = true;
		$showDump = $filter['rawResponse'];
		
		if ($filter['rawResponse'])
			ob_start();

		$depLocationsForReq = [];
		$arrivalLocations = [];

		try
		{
			$products = [];

			$cityId = $filter['cityId'];
			$countyId = $filter['regionId'];

			if (is_array($cityId))
			{
				foreach ($cityId ?: [] as $cid)
				{
					$isFakeFromRegion = $filter['fakeFromRegion'][$cid];
					$isFakeFromVillage = $filter['fakeFromVillage'][$cid];
					$arrivalLocations[] = [
						'Id' => $cid,
						'Type' => static::$LocationTypes[($isFakeFromRegion ? 'city' : ($isFakeFromVillage ? 'village' : 'town'))]
					];
				}
			}
			else if (is_scalar($cityId))
			{
				$isFakeFromRegion = ($filter['fakeFromRegion'] && ($filter['fakeFromRegion'] == $cityId));
				$isFakeFromVillage = ($filter['fakeFromVillage'] && ($filter['fakeFromVillage'] == $cityId));
				$arrivalLocations[] = [
						'Id' => $cityId,
						'Type' => static::$LocationTypes[($isFakeFromRegion ? 'city' : ($isFakeFromVillage ? 'village' : 'town'))]
					];
			}
			else if (is_array($countyId))
			{
				foreach ($countyId ?: [] as $cid)
				{
					$arrivalLocations[] = [
						'Id' => $cid,
						'Type' => static::$LocationTypes['city']
					];
				}
			}
			else if (is_scalar($countyId))
			{
				$arrivalLocations[] = [
					'Id' => $countyId,
					'Type' => static::$LocationTypes['city']
				];
			}
			else 
				throw new \Exception('Arrival not provided!');

			if (($departureCityId = $filter['departureCity']))
			{
				$isFakeFromRegion = ($filter['departureCityFakeFromRegion'] && ($filter['departureCityFakeFromRegion'] = $departureCityId));
				$isFakeFromVillage = ($filter['departureCityFakeFromVillage'] && ($filter['departureCityFakeFromVillage'] = $departureCityId));
				$depLocationsForReq[] = [
					'Id' => $departureCityId,
					'Type' => static::$LocationTypes[($isFakeFromRegion ? 'city' : ($isFakeFromVillage ? 'village' : 'town'))]
				];
			}
			else 
				throw new \Exception('Departure not provided!');

			if ($filter['travelItemId'])
			{
				list($hotelId, /*$hotelProvider*/) = explode('-', $filter['travelItemId']);
				$products[] = $hotelId;

				/*
				$products[] = [
					'Id' => $filter['travelItemId'],
					'Type' => static::$ProductTypes['hotel']
				];
				*/
			}

			$transportType = reset($filter['transportTypes']);

			if (!($currency = $filter['RequestCurrency']))
				$currency = static::$DefaultCurrencyCode;

			$adults = 0;
			$children = 0;
			$roomCriteria = [];
			foreach ($filter['rooms'] ?: [] as $room)
			{
				$adults += $room['adults'];
				$roomData = [
					'Adult' => $room['adults'],
					'ChildAges' => []
				];
				if ($room['children'])
				{
					$children += $room['children'];
					for ($i = 0; $i < $room['children']; $i++)
					{
						$roomData['ChildAges'][] = $room['childrenAges'][$i];
					}
				}
				$roomCriteria[] = $roomData;
			}

			$passengersCountForFlightAvailSeats = ($filter && $filter['_in_resync_request']) ? 1 : ($adults + $children);

			$f_depLocationsForReq = [];
			foreach ($depLocationsForReq ?: [] as $depv)
			{
				if (!($depv['Id']))
					continue;
				list($id, $providerId, $countryId) = explode("-", $depv['Id']);
				$depv["Id"] = (int)$id;
				$depv["Provider"] = (int)$providerId;
				$depv["CountryId"] = (int)$countryId;
				$f_depLocationsForReq[] = $depv;

			}
			$depLocationsForReq = $f_depLocationsForReq;

			$f_arrivalLocations = [];
			foreach ($arrivalLocations ?: [] as $arrloc)
			{
				if (!($arrloc['Id']))
					continue;
				list($id, $providerId, $countryId) = explode("-", $arrloc['Id']);
				$arrloc["Id"] = (int)$id;
				$arrloc["Provider"] = (int)$providerId;
				$arrloc["CountryId"] = (int)$countryId;
				$f_arrivalLocations[] = $arrloc;
			}
			$arrivalLocations = $f_arrivalLocations;

			$packageSearchParams = [
				"ProductType" => static::$ProductTypes['packages'],
				// 'CheckIn' => static::cleanup_date_only($filter['checkIn']), changed
				'CheckIn' => $filter['checkIn'],
				'Night' => (int)$filter['days'],
				"IncludeSubLocations" => true,
				'DepartureLocations' => $depLocationsForReq,
				'ArrivalLocations' => $arrivalLocations,
				"RoomCriteria" => $roomCriteria,
				'Products' => $products,
				'CheckStopSale' => false,
				'CheckAllotment' => false,
				#'ShowAllotment' => false,
				#'ShowStopSale' => false,

				'GetOnlyDiscountedPrice' => false,

				#'GetOnlyBestOffers' => true,
				'GetTransportations' => true,
				#'ShowOnlyNonStopFlight' => false,
				#'ForceFlightBundlePackage' => false,
				#'Compulsory' => false,

				/*
				'AdditionalParameters' => [
					'GetCountry' => false,
					'GetTransferLocation' => true
				],
				*/

				#'Customer' => [],
				#'TargetProvider' => 0,
				#'DataSource' => 0,
				'Currency' => $currency,
				#'Culture' => 'en-US',
				#'Provider' => 7,

				'Nationality' => static::$Nationality,
				#'Nationality' => 'TR',
				

				#"Provider" => 1,
				#"TargetProvider" => 0,
				#"DataSource" => 0,
				#"Customer" => new \stdClass(),
			];

			/*
			$packageSearchParams = [
					"ProductType" => 1,
					"CheckIn" => "2021-06-17T00:00:00",
					"Night" => 7,
					"IncludeSubLocations" => false,
					"DepartureLocations" => [
						(object)[
							"Type" => 2,
							"CountryId" => "1",
							"Provider" => 1,
							"Id" => "2"
						]
					],
					"ArrivalLocations" => [
						(object)[
							"Type" => 2,
							"CountryId" => "3",
							"Provider" => 1,
							"Id" => "4"
						]
					],
					"RoomCriteria" => [
						(object)[
							"Adult" => 2
						]
					],
					"CheckStopSale" => true,
					"ShowAllotment" => false,
					"ShowStopSale" => false,
					"GetOnlyDiscountedPrice" => false,
					"GetOnlyBestOffers" => true,
					"GetTransportations" => true,
					"ShowOnlyNonStopFlight" => false,
					"ForceFlightBundlePackage" => false,
					"Compulsory" => false,
					"Customer" => new \stdClass(),
					"TargetProvider" => 0,
					"DataSource" => 0,
					"Currency" => "EUR",
					"Culture" => "en-US",
					"Provider" => 7
			];
			*/

			//string $method, array $params = [], array $filter = null, bool $useCache = false, bool $logData = false
			list($charterOffers, $rawResp, $charterOffersEx, $toSaveRequests, $respOriginalInfo) = 
				$this->request('packages-price-search', $packageSearchParams, $filter, (!$force), ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

			$mainCharterRequest_k = null;
			if ($filter['_in_resync_request'])
			{
				foreach ($toSaveRequests ?: [] as $mainCharterRequest_k => $r)
				{
					$_allRequests[] = $r;
				}
			}

			if ($charterOffersEx)
				throw $charterOffersEx;

			$toUseCheckIn = date('Y-m-d', strtotime($filter["checkIn"]));
			// calculate checkout date
			$toUseCheckOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

			#$offDetailsReq = false;
			#$offDetailsReqsCnt = 0;
			$offDetailsReqsCntLimit = 3;
			#$offDetailsReqsCntLimit = 10;
			$retHotels = [];
			$fOffDetails = null;
			$fOffDetailsErrShown = false;
			$flightsErrShown = false;

			$cities = [];

			if (($hotels = $charterOffers['body']['hotels']) && is_array($hotels))
			{
				$transferCategory = new \stdClass();
				$transferCategory->Id = static::TransferItmIndx;
				$transferCategory->Code = static::TransferItmIndx;

				$airportTaxesCategory = new \stdClass();
				$airportTaxesCategory->Id = static::AirportTaxItmIndx;
				$airportTaxesCategory->Code = static::AirportTaxItmIndx;

				if (isset($hotels['id']) && $hotels['id'])
					$hotels = [$hotels];

				#list($countries) = $this->api_getCountries();
				$countries = $this->getCharterCountries();
				$countriesById = [];
				foreach ($countries ?: [] as $c)
					$countriesById[$c->Id] = $c;

				$indexedHotels = [];
				$currencies = [];

				#$offersBy
				$offsByFlightIndx = [];
				foreach ($hotels ?: [] as $apiHotel)
				{
					if ((!trim($apiHotel['id'])) || (!trim($apiHotel['provider'])) || (!trim($apiHotel['name'])))
					{
						if ($showDump)
						{
							echo '<div style="color: red;">Api hotel not ok formatted: missing id or name: ' . json_encode($apiHotel) . '</div>';
						}
						continue;
					}

					if (($offers = $apiHotel['offers']) && is_array($offers))
					{
						foreach ($offers ?: [] as $apiOffer)
						{
							if (!($offerId = $apiOffer['offerId']))
							{
								if ($showDump)
									echo '<div style="color: red;">Offer not ok formatted: id not found ' . json_encode($apiOffer) . '</div>';
								continue;
							}

							if (!($rooms = $apiOffer['rooms']) || (!is_array($rooms)))
							{
								if ($showDump)
									echo '<div style="color: red;">Offer not ok formatted: rooms not found ' . json_encode($apiOffer) . '</div>';
								continue;
							}

							if (count($rooms) > 1)
							{
								if ($showDump)
									echo '<div style="color: red;">Multiple rooms on offer not accepted ' . json_encode($apiOffer) . '</div>';
								continue;
							}

							foreach ($rooms ?: [] as $room)
							{
								$outboundFlightCode = isset($room['transportation']['outbound']['code']) ? $room['transportation']['outbound']['code'] : null;
								$inboundFlightCode = isset($room['transportation']['return']['code']) ? $room['transportation']['return']['code'] : null;

								if ($outboundFlightCode && $inboundFlightCode)
								{
									$offsByFlightIndx[$outboundFlightCode][$inboundFlightCode][] = $offerId;
								}
							}
						}
					}
				}

				if (count($offsByFlightIndx) > 0)
				{
					$offDetailsForFlightCodes = [];
					foreach ($offsByFlightIndx ?: [] as $depCode => $byReturnCodes)
					{
						foreach ($byReturnCodes ?: [] as $retCode => $offersIds)
						{
							$offPos = 0;
							$offDetails = null;
							foreach ($offersIds ?: [] as $offId)
							{
								$offDetailsParams = ["currency" => $currency, 'offerIds' => [$offId], 'getProductInfo' => true, "culture" => "en-US"];
								$t1 = microtime(true);
								list($offDetails, , $offDetailsEx, $toSaveRequests) = $this->request('get-offer-details', $offDetailsParams, $filter, (!$force), 
									($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));
								
								if ($filter['_in_resync_request'])
								{
									foreach ($toSaveRequests ?: [] as $r)
									{
										$_allRequests[] = $r;
									}
								}
								
								#echo 'Do request for offer: ' . $offId . '. Request took: ' . (microtime(true) - $t1) . ' seconds<br/>';
								$offPos++;

								if (($offDetails && (!$offDetailsEx)) || ($offPos >= $offDetailsReqsCntLimit))
								{
									break;
								}
							}
							if ($offDetails)
								$offDetailsForFlightCodes[$depCode][$retCode] = $offDetails;
						}
					}

					foreach ($hotels ?: [] as $apiHotel)
					{
						if ((!trim($apiHotel['id'])) || (!trim($apiHotel['provider'])) || (!trim($apiHotel['name'])))
						{
							if ($showDump)
								echo '<div style="color: red;">Api hotel not ok formatted: missing id or name: ' . json_encode($apiHotel) . '</div>';
							continue;
						}

						$hotelId = trim($apiHotel['id']) . "-" . trim($apiHotel['provider']);

						if (($filter["travelItemId"] && ($filter["travelItemId"] !== $hotelId)))
						{
							//echo "<div style='color: green;'>" . $hotelCode . "|" . $filter["travelItemId"] . "</div>";
							continue;
						}

						$hotelName = trim($apiHotel['name']);

						$locationCountry = $apiHotel['country'];
						$locationCity = $apiHotel['town'];
						$locationZone = $apiHotel['city'];
						$locationVillage = $apiHotel['village'];
						
						if ((!$locationCountry) && $apiHotel['location'] && $apiHotel['location']['countryId'])
						{
							$locationCountry = [
								'id' => $apiHotel['location']['countryId'],
							];
						}

						if ((!$locationCountry) || ((!$locationCity) && (!$locationZone) && (!$locationVillage)))
						{
							if ($showDump)
								echo '<div style="color: red;">Api hotel not ok formatted: missing geo: ' . json_encode($apiHotel) . '</div>';
							continue;
						}

						if (($offers = $apiHotel['offers']) && is_array($offers))
						{
							if (isset($offers['offerId']) && $offers['offerId'])
								$offers = [$offers];

							$eoffs = [];
							$eapioffs = [];
							foreach ($offers ?: [] as $apiOffer)
							{
								if (!($offerId = $apiOffer['offerId']))
								{
									if ($showDump)
										echo '<div style="color: red;">Offer not ok formatted: id not found ' . json_encode($apiOffer) . '</div>';
									continue;
								}

								if (!($rooms = $apiOffer['rooms']) || (!is_array($rooms)))
								{
									if ($showDump)
										echo '<div style="color: red;">Offer not ok formatted: rooms not found ' . json_encode($apiOffer) . '</div>';
									continue;
								}

								if (count($rooms) === 0)
								{
									if ($showDump)
										echo '<div style="color: red;">No rooms on offer ' . json_encode($apiOffer) . '</div>';
									continue;
								}

								if (count($rooms) > 1)
								{
									if ($showDump)
										echo '<div style="color: red;">Multiple rooms on offer not accepted ' . json_encode($apiOffer) . '</div>';
									continue;
								}

								foreach ($rooms ?: [] as $room)
								{
									#if (!($roomId = $room['roomId']))
									if (!isset($room['roomId']))
									{
										if ($showDump)
											echo '<div style="color: red;">Room id not found on room ' . json_encode($room) . '</div>';
										continue;
									}
									#echo 'process: ' . $offerId . '<br/>';

									$roomId = $room['roomId'];
									$roomName = $room['roomName'];
									$roomId .= '_' . $roomName;
									$roomId = md5($roomId);

									$hasTransferIncluded = false;
									foreach ($room['services'] ?: [] as $srv)
									{
										if ($srv['name'] == 'TRANSFER')
										{
											$hasTransferIncluded = true;
											break;
										}
									}

									$hasAirportTaxesIncluded = true;

									$outboundFlightCode = isset($room['transportation']['outbound']['code']) ? $room['transportation']['outbound']['code'] : null;
									$inboundFlightCode = isset($room['transportation']['return']['code']) ? $room['transportation']['return']['code'] : null;

									$outboundFlightIsAvailable = (isset($room['transportation']['outbound']['availableSeatCount']) && 
										($room['transportation']['outbound']['availableSeatCount'] >= $passengersCountForFlightAvailSeats));

									$inboundFlightIsAvailable = (isset($room['transportation']['return']['availableSeatCount']) && 
										($room['transportation']['return']['availableSeatCount'] >= $passengersCountForFlightAvailSeats));

									if ((!$outboundFlightCode) || (!$inboundFlightCode))
									{
										if ($showDump)
											echo '<div style="color: red;">Outbound/Inbound flight code not found ' . json_encode($room) . '</div>';
									}

									$fOffDetails = $offDetailsForFlightCodes[$outboundFlightCode][$inboundFlightCode];

									if ((!$fOffDetails) || (!($fOffDetailsDec = $fOffDetails['body']['offerDetails'])) || (!is_array($fOffDetailsDec)))
									{
										if ($showDump && (!$fOffDetailsErrShown))
										{
											#echo '<div style="color: red;">Cannot pull offer details data</div>';
											echo "Detaliile de zbor nu au putut fi preluate";
											$fOffDetailsErrShown = true;
										}
										continue;
									}

									if (!isset($fOffDetailsDec['checkIn']))
										$fOffDetailsDec = reset($fOffDetailsDec);

									if ((!($flights = $fOffDetailsDec['flights'])) || (!is_array($flights)) || (count($flights) != 2) || 
										(!($outboundFlight = reset($flights))) || (!($inboundFlight = end($flights))))
									{
										if ($showDump && (!$flightsErrShown))
										{
											echo '<div style="color: red;">Flights not found in offer details: ' . json_encode($fOffDetails) . '</div>';
											$flightsErrShown = true;
										}
										continue;
									}

									if ((!($outboundFlightItm = $outboundFlight['items'])) || (!is_array($outboundFlightItm)) || 
										(!($inboundFlightItm = $inboundFlight['items'])) || (!is_array($inboundFlightItm)))
									{
										if ($showDump && (!$flightsErrShown))
										{
											echo '<div style="color: red;">Cannot decode inbound/outbound flight: ' . json_encode($fOffDetails) . '</div>';
											$flightsErrShown = true;
										}
									}

									if (!isset($outboundFlightItm['flightNo']))
										$outboundFlightItm = reset($outboundFlightItm);

									if (!isset($inboundFlightItm['flightNo']))
										$inboundFlightItm = reset($inboundFlightItm);

									$outboundFlightDepartCity = $outboundFlightItm['departure']['town'];
									$outboundFlightDepartZone = $outboundFlightItm['departure']['city'];
									$outboundFlightDepartVillage = $outboundFlightItm['departure']['village'];

									if ((!$outboundFlightDepartCity) && (!$outboundFlightDepartZone) && (!$outboundFlightDepartVillage))
									{
										if ($showDump)
											echo '<div style="color: red;">Api outbound flight item - departure not ok formatted: missing geo: ' . json_encode($outboundFlightItm) . '</div>';
										continue;
									}

									$outboundFlightArrivalCity = $outboundFlightItm['arrival']['town'];
									$outboundFlightArrivalZone = $outboundFlightItm['arrival']['city'];
									$outboundFlightArrivalVillage = $outboundFlightItm['arrival']['village'];

									if ((!$outboundFlightArrivalCity) && (!$outboundFlightArrivalZone) && (!$outboundFlightArrivalVillage))
									{
										if ($showDump)
											echo '<div style="color: red;">Api outbound flight item - arrival not ok formatted: missing geo: ' . json_encode($outboundFlightItm['arrival']) . '</div>';
										continue;
									}

									$inboundFlightDepartCity = $inboundFlightItm['departure']['town'];
									$inboundFlightDepartZone = $inboundFlightItm['departure']['city'];
									$inboundFlightDepartVillage = $inboundFlightItm['departure']['village'];

									if ((!$inboundFlightDepartCity) && (!$inboundFlightDepartZone) && (!$inboundFlightDepartVillage))
									{
										if ($showDump)
											echo '<div style="color: red;">Api inbound flight item - departure not ok formatted: missing geo: ' . json_encode($inboundFlightItm) . '</div>';
										continue;
									}

									$inboundFlightArrivalCity = $inboundFlightItm['arrival']['town'];
									$inboundFlightArrivalZone = $inboundFlightItm['arrival']['city'];
									$inboundFlightArrivalVillage = $inboundFlightItm['arrival']['village'];

									if ((!$inboundFlightArrivalCity) && (!$inboundFlightArrivalZone) && (!$inboundFlightArrivalVillage))
									{
										if ($showDump)
											echo '<div style="color: red;">Api inbound flight item - arrival not ok formatted: missing geo: ' . json_encode($inboundFlightItm) . '</div>';
										continue;
									}

									if (!($hotel = $indexedHotels[$hotelId]))
									{
										// set hotel id as the code from sejour
										$hotel = new \stdClass();
										$hotel->Id = $hotelId;
										$hotel->Name = $hotelName;
										$hotel->Stars = $apiHotel['stars'];
										$hotel->Address = new \stdClass();

										#$location = ($locationCity ?: ($locationZone ?: $locationVillage));
										$location = ($locationVillage ?: ($locationCity ?: $locationZone));
										if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $location['provider']))
											$location['provider'] = $apiHotel['location']['provider'];

										#if ((!$location['provider']) && $apiHotel['provider'])
										#	$location['provider'] = $apiHotel['provider'];

										$country = null;
										if (trim($locationCountry['id']) && trim($apiHotel['provider']) && ($cid = $this->getCountryIdf(["countryId" => trim($locationCountry['id'])], trim($apiHotel['provider']))))
											$country = $countriesById[$cid] ?: $countries[$locationCountry['id']];
										else if (($cicode = trim($locationCountry['internationalCode'])))
											$country = $countries[$locationCountry['internationalCode']];

										if (!$country)
										{
											#qvardump('$countries, $countriesById', $countries, $countriesById);
											if ($showDump)
												echo '<div style="color: red;">Country not found for location 1. ' . json_encode($locationCountry). "|" . json_encode($location) . '</div>';
											continue;
										}

										$location['countryId'] = $country->OriginalId;
										$locationIdf = $this->getGeoIdf($location);

										if (!($city = $cities[$locationIdf]))
										{
											$city = new \stdClass();
											$city->Id = $locationIdf;
											$city->Name = $location['name'];
											if ($locationVillage)
												$city->FakeFromVillage = $locationIdf;
											else if ($locationZone)
												$city->FakeFromRegion = $locationIdf;
											if ($locationZone)
											{
												if (!$locationZone['provider'])
													$locationZone['provider'] = $location['provider'];
												if (!$locationZone['countryId'])
													$locationZone['countryId'] = $country->OriginalId;
												$city->County = new \stdClass();
												$city->County->Id = $this->getGeoIdf($locationZone);
												$city->County->Name = $locationZone['name'];
											}
											$city->Country = $country;
											$cities[$locationIdf] = $city;
										}

										$hotel->Address->City = $city;

										/*
										if (!($country = $countries[$locationCountry['id']]))
										{
											$country = new \stdClass();
											$country->Name = $locationCountry['name'];
											$country->Id = $locationCountry['id'];
											$countries[$locationCountry['id']] = $country;
										}

										$hotel->Address->City->Country = $country;
										*/

										$hotel->Offers = [];
										$indexedHotels[$hotelId] = $hotel;
									}

									$boardId = $room['boardId'];
									$boardName = $room['boardName'];
									$boardId .= '_' . $boardName;
									$boardId = md5($boardId);

									$accomId = $room['accomId'];

									$cfees_indx = [];
									if ($apiOffer['cancellationPolicies'] && is_array($apiOffer['cancellationPolicies']))
									{
										foreach ($apiOffer['cancellationPolicies'] ?: [] as $cp)
										{
											$cfees_indx[] = [
												'dueDate' => $cp['dueDate'],
												'amount' => $cp['price']['amount'],
												'currency' => $cp['price']['currency'],
											];
										}
									}

									// setup offer code
									$offerCode = md5($hotelId . '~' . $roomId . '~' . $roomName . '~' . $accomId . '~' . $boardId . '~' . $boardName . '~' 
										. $toUseCheckIn . '~' . $toUseCheckOut . json_encode($cfees_indx) . '~' . $hasTransferIncluded . '~' . $hasAirportTaxesIncluded);

									#echo '$offerCode : ' . $offerCode . '<br/>';

									// init new offer
									$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
									$eapioffs[$offerCode][] = $apiOffer;
									$offer->Code = $offerCode;

									$offer->SearchId = $charterOffers['body']['searchId'];
									$offer->OfferId = $offerId;
									$offer->retCurrency = $currency;
									$offer->checkIn = $apiOffer['checkIn'];
									$offer->expiresOn = $apiOffer['expiresOn'];
									$offer->isAvailable = $apiOffer['isAvailable'];
									$offer->night = $apiOffer['night'];
									$offer->availability = $apiOffer['availability'];

									// set offer currency
									if (!($currencyObj = $currencies[$apiOffer['price']['currency']]))
									{
										$currencyObj = new \stdClass();
										$currencyObj->Code = $apiOffer['price']['currency'];
										$currencies[$apiOffer['price']['currency']] = $currencyObj;
									}
									$offer->Currency = $currencyObj;

									// net price
									$offer->Net = (float)$apiOffer['price']['amount'];

									// offer total price
									$offer->Gross = (float)$apiOffer['price']['amount'];

									// get initial price
									$offer->InitialPrice = (float)$apiOffer['price']['oldAmount'] ? (float)$apiOffer['price']['oldAmount'] : (float)$apiOffer['price']['amount'];

									// room
									$roomType = new \stdClass();
									$roomType->Id = $roomId;
									$roomType->Title = $room['roomName'];

									$roomMerch = new \stdClass();
									//$roomMerch->Id = $roomOffer->roomKey;
									$roomMerch->Title = $room['roomName'];
									$roomMerch->Type = $roomType;
									$roomMerch->Code = $roomId;
									$roomMerch->Name = $room['roomName'];

									$roomItm = new \stdClass();
									$roomItm->Merch = $roomMerch;
									$roomItm->Id = $roomId;

									#if ($roomOffer->programType || $roomOffer->programTypeAlt)
									#	$roomItm->InfoTitle = $roomOffer->programType ?: $roomOffer->programTypeAlt;

									//required for indexing
									$roomItm->Code = $roomId;
									$roomItm->CheckinAfter = $toUseCheckIn;
									$roomItm->CheckinBefore = $toUseCheckOut;
									$roomItm->Currency = $offer->Currency;
									$roomItm->Quantity = 1;

									// set ne price on room
									$roomItm->Net = $room['price']['amount'];

									// Q: set initial price :: priceOld
									$roomItm->InitialPrice = $room['price']['amount'];

									// for identify purpose
									// hotelAvailability can be also N or NNNN
									$offer->Availability = $roomItm->Availability = 
										($apiOffer['isAvailable'] && ($outboundFlightIsAvailable && $inboundFlightIsAvailable)) ? 'yes' : 'no';

									if (!$offer->Rooms)
										$offer->Rooms = [];
									$offer->Rooms[$roomItm->Id] = $roomItm;

									// add items to offer
									$offer->Item = $roomItm;

									if ($boardId && $room['boardName'])
									{
										// board
										$boardType = new \stdClass();
										$boardType->Id = $boardId;
										$boardType->Title = $room['boardName'];

										$boardMerch = new \stdClass();
										//$boardMerch->Id = $boardId;
										$boardMerch->Title = $boardType->Title;
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
									}
									else
									{
										$boardItm = $this->getEmptyBoardItem($offer);
									}

									$offer->MealItem = $boardItm;

									$outboundDepartLocation = ($outboundFlightDepartVillage ?: ($outboundFlightDepartCity ?: $outboundFlightDepartZone));

									if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $outboundDepartLocation['provider']))
										$outboundDepartLocation['provider'] = $apiHotel['location']['provider'];

									$outboundDepartLocationIdf = $this->getGeoIdf($outboundDepartLocation);

									if (!($outboundDepartCity = $cities[$outboundDepartLocationIdf]))
									{
										$outboundDepartCity = new \stdClass();
										$outboundDepartCity->Id = $outboundDepartLocationIdf;
										$outboundDepartCity->Name = $outboundDepartLocation['name'];
										if ($outboundFlightDepartVillage)
											$outboundDepartCity->FakeFromVillage = $outboundDepartLocationIdf;
										else if ($outboundFlightDepartZone)
											$outboundDepartCity->FakeFromRegion = $outboundDepartLocationIdf;
									
										$cities[$outboundDepartLocationIdf] = $outboundDepartCity;
									}
									
									$outboundArrivalLocation = ($outboundFlightArrivalVillage ?: ($outboundFlightArrivalCity ?: $outboundFlightArrivalZone));
									
									if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $outboundArrivalLocation['provider']))
										$outboundArrivalLocation['provider'] = $apiHotel['location']['provider'];
									
									$outboundArrivalLocationIdf = $this->getGeoIdf($outboundArrivalLocation);
									
									if (!($outboundArrivalCity = $cities[$outboundArrivalLocationIdf]))
									{
										$outboundArrivalCity = new \stdClass();
										$outboundArrivalCity->Id = $outboundArrivalLocationIdf;
										$outboundArrivalCity->Name = $outboundArrivalLocation['name'];
										if ($outboundFlightArrivalVillage)
											$outboundArrivalCity->FakeFromVillage = $outboundArrivalLocationIdf;
										else if ($outboundFlightArrivalZone)
											$outboundArrivalCity->FakeFromRegion = $outboundArrivalLocationIdf;
									
										$cities[$outboundArrivalLocationIdf] = $outboundArrivalCity;
									}

									$inboundDepartLocation = ($inboundFlightDepartVillage ?: ($inboundFlightDepartCity ?: $inboundFlightDepartZone));

									if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $inboundDepartLocation['provider']))
										$inboundDepartLocation['provider'] = $apiHotel['location']['provider'];

									if ((!$inboundDepartLocation['countryId']) || ($inboundDepartLocation['countryId'] != $country->OriginalId))
										$inboundDepartLocation['countryId'] = $country->OriginalId;

									$inboundDepartLocationIdf = $this->getGeoIdf($inboundDepartLocation);
									
									if (!($inboundDepartCity = $cities[$inboundDepartLocationIdf]))
									{
										$inboundDepartCity = new \stdClass();
										$inboundDepartCity->Id = $inboundDepartLocationIdf;
										$inboundDepartCity->Name = $inboundDepartLocation['name'];
										if ($inboundFlightDepartVillage)
											$inboundDepartCity->FakeFromVillage = $inboundDepartLocationIdf;
										else if ($inboundFlightDepartZone)
											$inboundDepartCity->FakeFromRegion = $inboundDepartLocationIdf;

										$cities[$inboundDepartLocationIdf] = $inboundDepartCity;
									}
									
									$inboundArrivalLocation = ($inboundFlightArrivalVillage ?: ($inboundFlightArrivalCity ?: $inboundFlightArrivalZone));
									
									if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $inboundArrivalLocation['provider']))
										$inboundArrivalLocation['provider'] = $apiHotel['location']['provider'];
									
									$inboundArrivalLocationIdf = $this->getGeoIdf($inboundArrivalLocation);
									
									if (!($inboundArrivalCity = $cities[$inboundArrivalLocationIdf]))
									{
										$inboundArrivalCity = new \stdClass();
										$inboundArrivalCity->Id = $inboundArrivalLocationIdf;
										$inboundArrivalCity->Name = $inboundArrivalLocationIdf['name'];
										if ($inboundFlightArrivalVillage)
											$inboundArrivalCity->FakeFromVillage = $inboundArrivalLocationIdf;
										else if ($inboundFlightArrivalZone)
											$inboundArrivalCity->FakeFromRegion = $inboundArrivalLocationIdf;

										$cities[$inboundArrivalLocationIdf] = $inboundArrivalCity;
									}

									// get departure transport item
									$departureTransportItm = $this->getTransportItem("Dus: ".($toUseCheckIn ? date("d.m.Y", strtotime($toUseCheckIn)) : ""), $transportType, 
										date("Y-m-d H:i:s", strtotime($outboundFlightItm['departure']['date'])), date("Y-m-d H:i:s", strtotime($outboundFlightItm['arrival']['date'])), 
										$outboundDepartCity, $offer->Currency, $outboundFlightItm['departure']['airport']['id'], $outboundFlightItm['arrival']['airport']['id']);
									
									if ($outboundArrivalCity && $departureTransportItm && $departureTransportItm->Merch)
									{
										$departureTransportItm->Merch->To = new \stdClass();
										$departureTransportItm->Merch->To->City = $outboundArrivalCity;
									}
									
									// get return transport item
									$returnTransportItm = $this->getTransportItem("Retur: ".($toUseCheckOut ? date("d.m.Y", strtotime($toUseCheckOut)) : ""), $transportType, 
										date("Y-m-d H:i:s", strtotime($inboundFlightItm['departure']['date'])), date("Y-m-d H:i:s", strtotime($inboundFlightItm['arrival']['date'])), 
										$inboundDepartCity, $offer->Currency, $inboundFlightItm['departure']['airport']['id'], $inboundFlightItm['arrival']['airport']['id'], true);
									
									if ($inboundArrivalCity && $returnTransportItm && $returnTransportItm->Merch)
									{
										$returnTransportItm->Merch->To = new \stdClass();
										$returnTransportItm->Merch->To->City = $inboundArrivalCity;
									}

									$departureTransportItm->Return = $returnTransportItm;
									$offer->DepartureTransportItem = $departureTransportItm;
									$offer->ReturnTransportItem = $returnTransportItm;

									if ($hasTransferIncluded)
										$offer->Items[] = $this->getApiTransferItem($offer, $transferCategory);

									if ($hasAirportTaxesIncluded)
										$offer->Items[] = $this->getApiAirpotTaxesItem($offer, $airportTaxesCategory);

									/*
									if ($apiOffer['cancellationPolicies'] && is_array($apiOffer['cancellationPolicies']))
									{
										if (isset($apiOffer['cancellationPolicies']['dueDate']))
											$apiOffer['cancellationPolicies'] = [$apiOffer['cancellationPolicies']];
										$cancelFees = $this->getCancelFees($apiOffer['cancellationPolicies'], $offer->Gross, $filter['checkIn']);

										if ($cancelFees)
											$offer->CancelFees = $cancelFees;
									}
									*/

									// save offer on hotel
									#echo 'add offer ' . $offer->Code . ' to hotel ' . $hotel->Id . '<br/>';
									$hotel->Offers[$offer->Code] = $offer;

									#qvardump('$offer, $apiOffer', $offer, $apiOffer);
								}
							}

							if ($hotel->Offers)
							{
								#echo 'Add hotel ' . $hotel->Id . '<br/>';
								$retHotels[$hotel->Id] = $hotel;
							}
						}
					}
				}
			}
		}
		catch (\Exception $ex)
		{
			$_mainEx = $ex;
		}

		if ($filter['rawResponse'])
		{
			$messages = ob_get_clean();
			if (($hotels = $charterOffers['body']['hotels']) && is_array($hotels))
			{
				if (isset($hotels['id']) && $hotels['id'])
					$hotels = [$hotels];
				$hotelsById = [];
				foreach ($hotels ?: [] as $h)
				{
					$hotelId = trim($h['id']) . "-" . trim($h['provider']);
					$hotelsById[$hotelId][] = $h;
				}
				$charterOffers['body']['hotels'] = $hotelsById;
			}

			if (($mainCharterRequest_k !== null) && isset($_allRequests[$mainCharterRequest_k]))
			{
				$_allRequests[$mainCharterRequest_k][] = (microtime(true) - $__ts);
			}

			return [
				[
				"Mesaje" => $messages,
				"Hoteluri" => $hotels,
				"Oferte intoarse" => $charterOffers, 
				],
				$_mainEx,
				$_allRequests
			];
		}

		if (($mainCharterRequest_k !== null) && isset($_allRequests[$mainCharterRequest_k]))
		{
			$_allRequests[$mainCharterRequest_k][] = (microtime(true) - $__ts);
		}

		return [$retHotels, $_mainEx, $_allRequests];
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function getIndividualOffers(array $filter = null)
	{
		$_mainEx = null;
		try
		{
			$serviceType = $filter['serviceTypes'] ? reset($filter['serviceTypes']) : null;
			if ((!$serviceType) || ($serviceType != 'hotel'))
				return [null];

			if (!($checkOut = $filter['checkOut']))
			{
				if ($filter['checkIn'] && $filter['days'])
				{
					$checkOut = date("Y-m-d", strtotime("+ " . $filter['days'] . ' day', strtotime($filter['checkIn'])));
				}
				else
					throw new \Exception('Check Out not found!');
			}

			$force = false;
			#$depLocationsForReq = [];
			$arrivalLocations = [];
			$products = [];

			$cityId = $filter['cityId'];
			$countyId = $filter['regionId'];
			if (is_array($cityId))
			{
				foreach ($cityId ?: [] as $cid)
				{
					$isFakeFromRegion = $filter['fakeFromRegion'][$cid];
					$isFakeFromVillage = $filter['fakeFromVillage'][$cid];
					$arrivalLocations[] = [
						'Id' => (int)$cid,
						'Type' => static::$LocationTypes[($isFakeFromRegion ? 'city' : ($isFakeFromVillage ? 'village' : 'town'))]
					];
				}
			}
			else if (is_scalar($cityId))
			{
				$isFakeFromRegion = ($filter['fakeFromRegion'] && ($filter['fakeFromRegion'] == $cityId));
				$isFakeFromVillage = ($filter['fakeFromVillage'] && ($filter['fakeFromVillage'] == $cityId));
				$arrivalLocations[] = [
					'Id' => (int)$cityId,
					'Type' => static::$LocationTypes[($isFakeFromRegion ? 'city' : ($isFakeFromVillage ? 'village' : 'town'))]
				];
			}
			else if (is_array($countyId))
			{
				foreach ($countyId ?: [] as $cid)
				{
					$arrivalLocations[] = [
						'Id' => (int)$cid,
						'Type' => static::$LocationTypes['city']
					];
				}
			}
			else if (is_scalar($countyId))
			{
				$arrivalLocations[] = [
					'Id' => (int)$countyId,
					'Type' => static::$LocationTypes['city']
				];
			}
			else 
				throw new \Exception('Arrival not provided!');

			/*
			if (($departureCityId = $filter['departureCity']))
			{
				$depLocationsForReq[] = [
					'Id' => $departureCityId,
					'Type' => static::$LocationTypes['city']
				];
			}
			else 
				throw new \Exception('Departure not provided!');
			*/

			if ($filter['travelItemId'])
			{
				list($hotelId, /*$hotelProvider*/) = explode('-', $filter['travelItemId']);
				$products[] = $hotelId;

				/*
				$products[] = [
					'Id' => $filter['travelItemId'],
					'Type' => static::$ProductTypes['hotel']
				];
				*/
			}

			if (!($currency = $filter['RequestCurrency']))
				$currency = static::$DefaultCurrencyCode;

			$roomCriteria = [];
			foreach ($filter['rooms'] ?: [] as $room)
			{
				$roomData = [
					'adult' => $room['adults'],
				];
				if ($room['children'])
				{
					for ($i = 0; $i < $room['children']; $i++)
						$roomData['childAges'][] = $room['childrenAges'][$i];
				}
				$roomCriteria[] = $roomData;
			}

			/*
			$f_depLocationsForReq = [];
			foreach ($depLocationsForReq ?: [] as $depv)
			{
				if (!($depv['Id']))
					continue;
				list($id, $providerId, $countryId) = explode("-", $depv['Id']);
				$depv["Id"] = $id;
				$depv["Provider"] = $providerId;
				$depv["CountryId"] = $countryId;
				$f_depLocationsForReq[] = $depv;

			}
			$depLocationsForReq = $f_depLocationsForReq;
			*/

			#qvardump('$arrivalLocations', $arrivalLocations);
			$f_arrivalLocations = [];
			foreach ($arrivalLocations ?: [] as $arrloc)
			{
				if (!($arrloc['Id']))
					continue;
				list($id, $providerId, $countryId) = explode("-", $arrloc['Id']);
				$arrloc["Id"] = $id;
				$arrloc["Provider"] = $providerId;
				$arrloc["CountryId"] = $countryId;
				$f_arrivalLocations[] = $arrloc;
			}
			$arrivalLocations = $f_arrivalLocations;
			
			$duration = $filter['duration'] ?: $filter['days'];
			if (!$duration)
			{
				$interval = date_diff(date_create($filter['checkOut']), date_create($filter['checkIn']));
				$duration = (int)$interval->format("%a");
			}

			$searchParams = [
				"currency" => $currency,
				//"culture" => "en-US",
				"checkAllotment" => false,
				"checkStopSale" => false,
				"getOnlyDiscountedPrice" => false,
				"productType" => static::$ProductTypes['hotel'],
				"arrivalLocations" => $arrivalLocations,
				"roomCriteria" => $roomCriteria,
				'nationality' => static::$Nationality,
				//'checkIn' => static::cleanup_date_only($filter['checkIn']), changed
				'checkIn' => $filter['checkIn'],
				'night' => $duration,
			];

			if ($products)
				$searchParams['Products'] = $products;

			$toUseCheckIn = date('Y-m-d', strtotime($filter["checkIn"]));
			// calculate checkout date
			$toUseCheckOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

			/*
			$searchParams = [
				"ProductType" => 2,
				"CheckIn" => "2021-06-12T00:00:00",
				"Night" => 7,
				"IncludeSubLocations" => true,
				"ArrivalLocations" => [(object)[
					"Name" => "Antalya, Region",
					"Type" => 2,
					"Latitude" => "36.86461",
					"Longitude" => "30.63713",
					"CountryId" => "TR",
					"Provider" => 2,
					"IsTopRegion" => true,
					"Id" => "60208"
				]],
				"RoomCriteria" => [(object)[
					"Adult" => 2
				]],
				"CheckStopSale" => true,
				"ShowAllotment" => false,
				"ShowStopSale" => false,
				"GetOnlyDiscountedPrice" => false,
				"GetOnlyBestOffers" => true,
				"GetTransportations" => false,
				"Nationality" => "RO",
				"ShowOnlyNonStopFlight" => false,
				"SupportedFlightReponseListTypes" => [2, 3],
				"Compulsory" => false,
				"AdditionalParameters" => (object)[
					"GetCountry" => false,
					"GetTransferLocation" => true
				],
				"PriceSearchResponseDetails" =>  new \stdClass(),
				"Customer" => new \stdClass(),
				"TargetProvider" => 0,
				"Id" => "eed9c5c4-b953-4137-a60d-55c92b97d9b7",
				"DataSource" => 0,
				"Currency" => "EUR",
				"Culture" => "en-US",
				"Provider" => 7
			];
			*/

			list($individualOffers) = $this->request('individual-price-search', $searchParams, $filter, (!$force), ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

			if ($filter['rawResponse'])
			{
				if (($hotels = $individualOffers['body']['hotels']) && is_array($hotels))
				{
					if (isset($hotels['id']) && $hotels['id'])
						$hotels = [$hotels];
					$hotelsById = [];
					foreach ($hotels ?: [] as $h)
					{
						$hotelId = trim($h['id']) . "-" . trim($h['provider']);
						$hotelsById[$hotelId][] = $h;
					}
					$individualOffers['body']['hotels'] = $hotelsById;
				}
				return [$individualOffers];
			}

			#$individualOffers =  json_decode(file_get_contents('tourvisio_new_individual_offers.json'), true);

			$showDump = true;
			$ret = [];
			$countries = [];
			$cities = [];

			if (($hotels = $individualOffers['body']['hotels']) && is_array($hotels))
			{
				if (isset($hotels['id']) && $hotels['id'])
					$hotels = [$hotels];

				list($countries) = $this->api_getCountries();
				$countriesById = [];
				foreach ($countries ?: [] as $c)
					$countriesById[$c->Id] = $c;

				$indexedHotels = [];
				foreach ($hotels ?: [] as $apiHotel)
				{
					if ((!trim($apiHotel['id'])) || (!trim($apiHotel['provider'])) || (!trim($apiHotel['name'])))
					{
						if ($showDump)
						{
							echo '<div style="color: red;">Api hotel not ok formatted: missing id or name: ' . json_encode($apiHotel) . '</div>';
						}
						continue;
					}

					$hotelId = trim($apiHotel['id']) . "-" . trim($apiHotel['provider']);

					if (($filter["travelItemId"] && ($filter["travelItemId"] !== $hotelId)))
					{
						//echo "<div style='color: green;'>" . $hotelCode . "|" . $filter["travelItemId"] . "</div>";
						continue;
					}

					$locationCountry = $apiHotel['country'];
					$locationCity = $apiHotel['town'];
					$locationZone = $apiHotel['city'];
					$locationVillage = $apiHotel['village'];
					
					if ((!$locationCountry) && $apiHotel['location'] && $apiHotel['location']['countryId'])
					{
						$locationCountry = [
							'id' => $apiHotel['location']['countryId'],
						];
					}

					if ((!$locationCountry) || ((!$locationCity) && (!$locationZone) && (!$locationVillage)))
					{
						if ($showDump)
							echo '<div style="color: red;">Api hotel not ok formatted: missing geo: ' . json_encode($apiHotel) . '</div>';
						continue;
					}

					if (($offers = $apiHotel['offers']) && is_array($offers))
					{
						if (isset($offers['offerId']) && $offers['offerId'])
							$offers = [$offers];

						$eoffs = [];
						foreach ($offers ?: [] as $apiOffer)
						{
							if (!($offerId = $apiOffer['offerId']))
							{
								if ($showDump)
									echo '<div style="color: red;">Offer not ok formatted: id not found ' . json_encode($apiOffer) . '</div>';
								continue;
							}

							if (!($rooms = $apiOffer['rooms']) || (!is_array($rooms)))
							{
								if ($showDump)
									echo '<div style="color: red;">Offer not ok formatted: rooms not found ' . json_encode($apiOffer) . '</div>';
								continue;
							}

							#foreach ($rooms ?: [] as $room)
							if (($room = reset($rooms)))
							{
								#if (!($roomId = $room['roomId']))
								if (!isset($room['roomId']))
								{
									if ($showDump)
										echo '<div style="color: red;">Room id not found on room ' . json_encode($room) . '</div>';
									continue;
								}

								$roomId = $room['roomId'];
								$roomName = $room['roomName'];
								$roomId .= '_' . $roomName;
								$roomId = md5($roomId);

								if (!($hotel = $indexedHotels[$hotelId]))
								{
									// set hotel id as the code from sejour
									$hotel = new \stdClass();
									$hotel->Id = $hotelId;
									$hotel->Name = trim($apiHotel['name']);
									$hotel->Stars = $apiHotel['stars'];
									$hotel->Address = new \stdClass();

									#$location = ($locationCity ?: ($locationZone ?: $locationVillage));
									$location = $locationVillage ?: ($locationCity ?: $locationZone);

									if (isset($apiHotel['location']['provider']) && $apiHotel['location']['provider'] && ($apiHotel['location']['provider'] != $location['provider']))
										$location['provider'] = $apiHotel['location']['provider'];

									#qvardump('$apiHotel', $apiHotel);
									if ((!$location['provider']) && $apiHotel['provider'])
										$location['provider'] = $apiHotel['provider'];

									$country = null;
									if (trim($locationCountry['id']) && trim($apiHotel['provider']) && ($cid = $this->getCountryIdf(["countryId" => trim($locationCountry['id'])], trim($apiHotel['provider']))))
										$country = $countriesById[$cid] ?: $countries[$locationCountry['id']];
									else if (($cicode = trim($locationCountry['internationalCode'])))
										$country = $countries[$locationCountry['internationalCode']];

									if (!$country)
									{
										#($cid = trim($locationCountry['id']) . "-" . trim($apiHotel['provider']))

										if ($showDump)
										{
											#qvardump($location, $locationCountry, $countries, $countriesById);
											echo '<div style="color: red;">Country not found for location 2. ' . json_encode($locationCountry). "|" . json_encode($location) . '</div>';
											#q_die('--');
										}
										continue;
									}



									$location['countryId'] = $country->OriginalId;
									$locationIdf = $this->getGeoIdf($location);

									if (!($city = $cities[$locationIdf]))
									{
										$city = new \stdClass();
										$city->Id = $locationIdf;
										$city->Name = $location['name'];
										if ($locationVillage)
											$city->FakeFromVillage = $locationIdf;
										else if ($locationZone)
											$city->FakeFromRegion = $locationIdf;
										if ($locationZone)
										{
											if (!$locationZone['provider'])
												$locationZone['provider'] = $location['provider'];
											if (!$locationZone['countryId'])
												$locationZone['countryId'] = $country->OriginalId;

											$city->County = new \stdClass();
											$city->County->Id = $this->getGeoIdf($locationZone);
											$city->County->Name = $locationZone['name'];
										}
										$city->Country = $country;
										$cities[$locationIdf] = $city;
									}
									$hotel->Address->City = $city;
									$hotel->Offers = [];
									$indexedHotels[$hotelId] = $hotel;
								}

								$boardId = $room['boardId'];
								$boardName = $room['boardName'];
								$boardId .= '_' . $boardName;
								$boardId = md5($boardId);

								$accomId = $room['accomId'];

								$cfees_indx = [];
								if ($apiOffer['cancellationPolicies'] && is_array($apiOffer['cancellationPolicies']))
								{
									foreach ($apiOffer['cancellationPolicies'] ?: [] as $cp)
									{
										$cfees_indx[] = [
											'dueDate' => $cp['dueDate'],
											'amount' => $cp['price']['amount'],
											'currency' => $cp['price']['currency'],
										];
									}
								}

								// setup offer code
								$offerCode = md5($hotelId . '~' . $roomId . '~' . $roomName . '~' . $accomId . '~' . $boardId . '~' . $boardName . '~' 
									. $toUseCheckIn . '~' . $toUseCheckOut . json_encode($cfees_indx));

								// init new offer
								$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
								$offer->Code = $offerCode;

								$offer->OfferId = $offerId;
								$offer->retCurrency = $currency;
								$offer->checkIn = $apiOffer['checkIn'];
								#$offer->expiresOn = $apiOffer['expiresOn'];
								$offer->isAvailable = $apiOffer['isAvailable'];
								$offer->night = $apiOffer['night'];
								#$offer->availability = $apiOffer['availability'];
								//$offer->ownOffer = $apiOffer['ownOffer'];

								// set offer currency
								$offer->Currency = new \stdClass();
								$offer->Currency->Code = $apiOffer['price']['currency'];

								// net price
								$offer->Net = (float)$apiOffer['price']['amount'];

								// offer total price
								$offer->Gross = (float)$apiOffer['price']['amount'];

								// get initial price
								$offer->InitialPrice = (float)$apiOffer['price']['amount'];

								// room
								$roomType = new \stdClass();
								$roomType->Id = $roomId;
								$roomType->Title = $room['roomName'];

								$roomMerch = new \stdClass();
								//$roomMerch->Id = $roomOffer->roomKey;
								$roomMerch->Title = $room['roomName'];
								$roomMerch->Type = $roomType;
								$roomMerch->Code = $roomId;
								$roomMerch->Name = $room['roomName'];

								$roomItm = new \stdClass();
								$roomItm->Merch = $roomMerch;
								$roomItm->Id = $roomId;

								#if ($roomOffer->programType || $roomOffer->programTypeAlt)
								#	$roomItm->InfoTitle = $roomOffer->programType ?: $roomOffer->programTypeAlt;

								//required for indexing
								$roomItm->Code = $roomId;
								$roomItm->CheckinAfter = $toUseCheckIn;
								$roomItm->CheckinBefore = $toUseCheckOut;
								$roomItm->Currency = $offer->Currency;
								$roomItm->Quantity = 1;

								// set ne price on room
								$roomItm->Net = $room['price']['amount'];

								// Q: set initial price :: priceOld
								$roomItm->InitialPrice = $room['price']['amount'];

								// for identify purpose
								// hotelAvailability can be also N or NNNN
								$offer->Availability = $roomItm->Availability = $apiOffer['isAvailable'] ? 'yes' : 'no';

								if (!$offer->Rooms)
									$offer->Rooms = [];
								$offer->Rooms[$roomItm->Id] = $roomItm;

								// add items to offer
								$offer->Item = $roomItm;

								if ($boardId && $room['boardName'])
								{
									// board
									$boardType = new \stdClass();
									$boardType->Id = $boardId;
									$boardType->Title = $room['boardName'];

									$boardMerch = new \stdClass();
									//$boardMerch->Id = $boardId;
									$boardMerch->Title = $boardType->Title;
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
								}
								else
								{
									$boardItm = $this->getEmptyBoardItem($offer);
								}

								$offer->MealItem = $boardItm;

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
								#$departureTransportItm->Id = $departureTransportMerch->Id;

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
								#$returnTransportItm->Id = $returnTransportMerch->Id;
								$departureTransportItm->Return = $returnTransportItm;

								$offer->DepartureTransportItem = $departureTransportItm;
								$offer->ReturnTransportItem = $returnTransportItm;

								#qvardump('$offer, $apiOffer', $offer, $apiOffer);

								/*
								if ($apiOffer['cancellationPolicies'] && is_array($apiOffer['cancellationPolicies']))
								{
									if (isset($apiOffer['cancellationPolicies']['dueDate']))
										$apiOffer['cancellationPolicies'] = [$apiOffer['cancellationPolicies']];
									$offerCancelFees = $this->getCancelFees($apiOffer['cancellationPolicies'], $offer->Gross, $filter['CheckIn']);
									if ($offerCancelFees)
										$offer->CancelFees = $offerCancelFees;
								}
								*/

								// save offer on hotel
								$hotel->Offers[$offer->Code] = $offer;
							}
						}

						if ($hotel->Offers)
							$ret[$hotel->Id] = $hotel;
					}
				}
			}
		}
		catch (\Exception $ex)
		{
			$_mainEx = $ex;
		}

		return [$ret, $_mainEx];
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferDetails(array $filter = null)
	{
		return null;
	}

	/**
	 * 
	 */
	public function api_getOfferCancelFees(array $filter = null)
	{
		$force = false;
		if (!($originalOffer = $filter['OriginalOffer']))
			return null;
		$originalOfferArr = is_array($originalOffer) ? $originalOffer : (is_object($originalOffer) ? json_decode(json_encode($originalOffer), true) : null);
		if (!$originalOfferArr)
			return null;

		if ((!($offerId = ($originalOfferArr['offerId'] ?: $originalOfferArr['OfferId']))) || (!($searchId = $originalOfferArr['SearchId'])) || 
			(!($price = $filter["SuppliedPrice"])) || (!($currency = $filter["SuppliedCurrency"])) || 
			(!($hotelIdRaw = (isset($filter['Hotel']['InTourOperatorId']) ? $filter['Hotel']['InTourOperatorId'] : null))))
			return;

		list($hotelId, /*$hotelProvider*/) = explode('-', $hotelIdRaw);

		$offDetailsParams = ["currency" => $currency, 'offerIds' => [$offerId], 'getProductInfo' => true, "culture" => "en-US"];		
		list($offDetails) = $this->request('get-offer-details', $offDetailsParams, $filter, (!$force), ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));
		
		/*
		$offersParams = [
			"currency" => $currency,
			"culture" => "en-US",
			"searchId" => $searchId,
			"productType" => 14,
			"productId" => $hotelId,
			"offerId" => $offerId,
			"offerCount" => 100
		];
		*/

		#list($offDetails) = $this->request('get-offers', $offersParams, $filter, (!$force));
		#qvardump('$offDetails', $offDetails, $offersParams);
		#throw new \Exception('q1');

		if (!($offDet = $offDetails['body']['offerDetails']) || (!is_array($offDet)))
			return null;

		if (!isset($offDet['offerId']))
			$offDet = reset($offDet);

		if ((!($cpolicies = $offDet['cancellationPolicies'])) || (!is_array($cpolicies)))
			return;

		if (isset($cpolicies['dueDate']))
			$cpolicies = [$cpolicies];

		$ret = $this->getCancelFees($cpolicies, $price, $filter['CheckIn']);

		return $ret;
	}

	/**
	 * 
	 */
	public function api_getOfferCancelFeesPaymentsAvailabilityAndPrice(array $filter = null)
	{

	}

	public function getCancelFees($cpolicies, $price, $checkIn)
	{
		$ret = [];
		$cpoliciesDs_Amount = [];
		foreach ($cpolicies ?: [] as $cp)
			$cpoliciesDs_Amount[$cp['dueDate']] = [$cp['price']['amount'], $cp['price']['currency']];
		$prevFee = null;
		$currencies = [];
		$todayTime = strtotime(date("Y-m-d"));

		$dateStart = null;
		foreach ($cpoliciesDs_Amount ?: [] as $dateEnd => $amountData)
		{
			list($amount, $cp_currency) = $amountData;
			$dateEnd = date("Y-m-d", strtotime($dateEnd));

			#echo 'Date Start: ' . $dateStart . '<br/>';
			if (($dateStart === null) || ($todayTime > strtotime($dateStart)))
				$dateStart = date("Y-m-d");

			$cpObj = new \stdClass();
			$cpObj->DateStart = $dateStart;
			$cpObj->DateEnd = $dateEnd;

			#$cpObj->Price = ($amount * $price)/100;
			$cpObj->Price = $amount;
			$currencyObj = ($currencies[$cp_currency] ?: ($currencies[$cp_currency] = new \stdClass()));
			$currencyObj->Code = $cp_currency;
			$cpObj->Currency = $currencyObj;
			$ret[] = $cpObj;
			$prevFee = $cpObj;
			$dateStart = date('Y-m-d', strtotime("+1 day", strtotime($dateEnd)));
		}
		return $ret;
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
	public function api_getOfferExtraServices(array $filter = null)
	{
		
	}

	/**
	 * @param array $data
	 */
	public function api_doBooking(array $data = null)
	{
		if (!($offer = (($data && $data["Items"] && is_array($data["Items"])) ? reset($data["Items"]) : null)))
			throw new \Exception('Offer not found!');

		$force = true;

		if (!($offerId = $offer['Offer_OfferId']))
			throw new \Exception('OfferId not found!');

		if (!($originalCurrency = $offer['Offer_retCurrency']))
			throw new \Exception('Offer original currency not found!');

		$offDetailsParams = ["currency" => $originalCurrency, 'offerIds' => [$offerId], 'getProductInfo' => true, "culture" => "en-US"];
		list($offDetails) = $this->request('get-offer-details', $offDetailsParams, $data, (!$force), true);

		if (!($offDet = $offDetails['body']['offerDetails']) || (!is_array($offDet)))
			throw new \Exception('Offer details not received from partner');

		//string $method, array $params = [], array $filter = null, bool $useCache = false, bool $logData = false
		$beginTransactionParams = ["currency" => $originalCurrency, 'offerIds' => [$offerId], "culture" => "en-US"];
		list($bookingTransaction, , $bookingTransactionEx) = $this->request('booking-transaction-begin', $beginTransactionParams, $data, (!$force), true);

		if ($bookingTransactionEx)
			throw $bookingTransactionEx;

#		$bookingTransaction = json_decode(file_get_contents('fibula_new_booking_begin_transaction_resp.json'), true);

		if ((!$bookingTransaction) || (!($transactionId = $bookingTransaction['body']['transactionId'])) ||
			(!($travelers = $bookingTransaction['body']['reservationData']['travellers'])) || 
			(!($priceToPay = $bookingTransaction['body']['reservationData']['reservationInfo']['priceToPay'])))
			throw new \Exception('cannot determine booking transaction!');

		$checkIn = $data["Params"]["CheckIn"];

		$passengersByType = [];
		foreach ($offer['Passengers'] ?: [] as $passenger)
		{
			$isMale = ($passenger['Gender'] == 'male');
			$toUsePassengerAge = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($passenger['BirthDate']))->format("%y");
			$isInfant = (($toUsePassengerAge < $this->infantAgeBelow));
			$passengerType = static::$PassengerTypes[$passenger['IsAdult'] ? 'adult' : ($isInfant ? 'infant' : 'child')];
			#$passengerTitle = $passenger['IsAdult'] ? ($isMale ? 'Mr' : 'Ms') : ($isMale ? 'Mrs' : '');
			$passengerTitle = $passenger['IsAdult'] ? ($isMale ? 'Mr' : 'Mrs') : 'Mr';
			$passenger['PassengerTitle'] = $passengerTitle;
			$passenger['Age'] = $toUsePassengerAge;
			#$passengersByType[$passengerType][$passengerTitle][] = $passenger;
			$passengersByType[$passengerType][] = $passenger;
		}

		$doReqFieldsCheck = false;
		$toSkipReqFields = ['leaderEmail', 'addressInfo'];
		$toUpdatePassengersData = [];
		
		$travelersByType = [];
		foreach ($travelers ?: [] as $tv)
		{
			if (!($passengerType = $tv['type']))
				throw new \Exception('passenger type not recevied from tour operator system!');
			$travelersByType[$passengerType][] = $tv;
		}

		$passengerTypesFlip = array_flip(static::$PassengerTypes);
		$inUseIndexesByType = [];
		#$inUseIndexesByTypeAndTitle = [];
		#foreach ($travelers ?: [] as $tv)
		foreach ($travelersByType ?: [] as $passengerType => $travelers)
		{
			#if (!($passengerType = $tv['type']))
			#	throw new \Exception('passenger type not recevied from tour operator system!');

			foreach ($travelers ?: [] as $tv)
			{
				$avTitlesById = [];
				$avTitlesByName = [];
				if (($avTitles = $tv['availableTitles']) && is_array($avTitles))
				{
					if (isset($avTitles['id']))
						$avTitles = [$avTitles];
					foreach ($avTitles ?: [] as $avTitle)
					{
						$avTitlesById[$avTitle['id']] = $avTitle;
						$avTitlesByName[$avTitle['name']] = $avTitle['id'];
					}
				}

				/*
				if ((!($useTitleAr = $avTitlesById[$tv['title']])) || (!($useTitle = $useTitleAr['name'])))
					throw new \Exception('Cannot determine passenger title!');

				if ($useTitle == 'Miss')
					$useTitle = 'Ms';

				if (!isset($inUseIndexesByTypeAndTitle[$passengerType][$useTitle]))
					$inUseIndexesByTypeAndTitle[$passengerType][$useTitle] = 0;

				$toUsePassengerIndex = $inUseIndexesByTypeAndTitle[$passengerType][$useTitle];
				$inUseIndexesByTypeAndTitle[$passengerType][$useTitle]++;

				if (!($toUsePassenger = $passengersByType[$passengerType][$useTitle][$toUsePassengerIndex]))
				{
					throw new \Exception('Pasagerul cu tipul ' . $useTitle . ' este obligatoriu si nu a fost gasit!');
				}
				*/

				if (!isset($inUseIndexesByType[$passengerType]))
					$inUseIndexesByType[$passengerType] = 0;

				$toUsePassengerIndex = $inUseIndexesByType[$passengerType];
				$inUseIndexesByType[$passengerType]++;

				$passengerTypeStr = $passengerTypesFlip[$passengerType];

				if (!($toUsePassenger = $passengersByType[$passengerType][$toUsePassengerIndex]))
				{
					throw new \Exception('Numarul de pasageri pentru tipul ' .   $passengerTypeStr
						. ' intors de sistemul tur operatorului este diferit de numarul din sistemul Travelfuse!');
				}

				$avTitleId = null;
				if ((!($avTitleId = $avTitlesByName[$toUsePassenger['PassengerTitle']])) && ($passengerTypeStr == 'adult'))
				{
					throw new \Exception('Titlul pentru pasagerul ' . $passengerTypeStr . ' nu a putut fi determinat!');
				}

				//do the transfer here
				$tv['name'] = $toUsePassenger['Firstname'];
				$tv['surname'] = $toUsePassenger['Lastname'];
				$tv['age'] = $toUsePassenger['Age'];
				$tv['birthDate'] = $toUsePassenger['BirthDate'];
				$tv['gender'] = $passenger['IsAdult'] ? ($isMale ? 0 : 1) : 0;
				if ($avTitleId)
					$tv['title'] = $avTitleId;
				
				$tv['nationality']['twoLetterCode'] = static::$Nationality;

				if ($tv['isLeader'])
				{
					$phone = $data['BillingTo']['Phone'] ?: '+40785345678';
					if ($phone[0] != '+')
						$phone = '+' . $phone;
					$phoneParts = str_split($phone, 3);
					$countryCode = array_shift($phoneParts);
					$areaCode = array_shift($phoneParts);
					$restOfTheNumber = implode($phoneParts, '');
					$tv['address']['contactPhone']['countryCode'] = $countryCode;
					$tv['address']['contactPhone']['areaCode'] = $areaCode;
					$tv['address']['contactPhone']['phoneNumber'] = $restOfTheNumber;
					$tv['address']['contactPhone']['leaderEmail'] = $data['BillingTo']['Email'] ?: 'test@test.com';
				}
				else
					unset($tv['address']['contactPhone']);

				unset($tv['destinationAddress']);
				#unset($tv['destinationAddress']);

				if ($doReqFieldsCheck)
				{
					foreach ($tv['requiredFields'] ?: [] as $reqField)
					{
						if (!($tv[$reqField]))
						{
							// treat custom req fields
							if ($reqField == 'leaderEmail')
							{

							}

							if (!in_array($reqField, $toSkipReqFields))
							{
								throw new \Exception('Req field [' .  $reqField . '] not found on traveler!');
							}
						}
					}
				}
				$toUpdatePassengersData[] = $tv;
			}
		}

		$hasCompany = (isset($data['BillingTo']['Company']['TaxIdentificationNo']) && $data['BillingTo']['Company']['TaxIdentificationNo']);
		$customerData = [
			"isCompany" => $hasCompany,
			//"passportInfo" => [],
			"address" => [
			  "city" => [
				"name" => $data['BillingTo']['Address']['City']['Name']
			  ],
			  "country" => [
				"name" => $data['BillingTo']['Address']['Country']['Name']
			  ],
			  "email" => $data['BillingTo']['Email'],
			  "phone" => $data['BillingTo']['Phone'],
			  "address" => $data['BillingTo']['Address']["Details"],
			  "zipCode" => $data['BillingTo']['Address']["ZipCode"],
			],
			//"taxInfo" => [],
			//"matching" => [],
			//"title" => "1",
			"name" => $data['BillingTo']['Firstname'],
			"surname" => $data['BillingTo']['Name'],
			#"birthDate" => "1996-01-01",
			#"identityNumber" => "11111111111"
		];

		$bookingResInfo = [
			'transactionId' => $transactionId,
			'travellers' => $toUpdatePassengersData,
			//'customerInfo' => $customerData,
			#'reservationNote' => '',
			#'agencyReservationNumber' => ''
		];

		list($bookingUpdate, , $bookingUpdateEx) = $this->request('booking-set-reservation-info', $bookingResInfo, $data, (!$force), true);
				
		if ($bookingUpdateEx)
			throw $bookingUpdateEx;

		if (!$bookingUpdate['body']['transactionId'])
			throw new \Exception('Update data on tour operator system order failed!');

		$commitTransactionParams = [
			"transactionId" => $transactionId,
			"PaymentOption" => static::$PaymentOptions['agency_credit']
		];

		list($bookingTransactionCommit, $bookingTransactionCommitRaw, $bookingTransactionCommitEx) = 
			$this->request('booking-transaction-commit', $commitTransactionParams, $data, (!$force), true);

		/*
		qvardump('$bookingTransaction, $bookingTransactionEx, $beginTransactionParams, $bookingUpdate, $bookingUpdateEx, $bookingResInfo, 
			$bookingTransactionCommit, $bookingTransactionCommitRaw, $bookingTransactionCommitEx, $commitTransactionParams', $bookingTransaction, $bookingTransactionEx, 
			$beginTransactionParams, $bookingUpdate, $bookingUpdateEx, $bookingResInfo, $bookingTransactionCommit, $bookingTransactionCommitRaw, 
			$bookingTransactionCommitEx, $commitTransactionParams);
		*/

		if ($bookingTransactionCommitEx)
			throw $bookingTransactionCommitEx;		

		if ((!$bookingTransactionCommit) || (!($resNumber = $bookingTransactionCommit['body']['reservationNumber'])))
		{
			throw new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!"
				. "\nRaspuns tur operator: " . $bookingTransactionCommitRaw);
		}

		$order = new \stdClass();
		$order->Id = $resNumber;

		return [$order, $bookingTransactionCommitRaw];
	}

	/**
	 * @param array $filter
	 */
	public function api_prepareBooking(array $filter = null)
	{
		
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

	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "tourvisio_new";
	}
	
	public function getRequestMode()
	{
		return static::RequestModeCurl;
	}

	public function getApiUrl($method)
	{
		if (!($apAction = $this->apiActionByMethod[$method]))
			throw new \Exception("Api Action not found for method [{$method}]");
		return rtrim(($this->TourOperatorRecord->ApiUrl__ ?: $this->TourOperatorRecord->ApiUrl), "\\/") . "/" . $apAction;
	}

	public function getSimpleCacheFileForUrl($url, $params, $format = "json")
	{
		$cacheDir = $this->getResourcesDir() . "cache/";
		if (!is_dir($cacheDir))
			mkdir($cacheDir);
		return $cacheDir . "cache_" . md5($url . "|" . json_encode($params) . "|" . $format) . "." . $format;
	}

	public function login($verbose = false)
	{
		$this->login_token = null;

		$ret = $this->request_inner('login', [
			'Agency' => ($this->TourOperatorRecord->ApiContext__ ?: $this->TourOperatorRecord->ApiContext),
			'User' => ($this->TourOperatorRecord->ApiUsername__ ?: $this->TourOperatorRecord->ApiUsername),
			'Password' => ($this->TourOperatorRecord->ApiPassword__ ?: $this->TourOperatorRecord->ApiPassword),
		]);

		list($resp) = $ret;

		if (isset($resp['body']['token']) && $resp['body']['token'])
			$this->login_token = $resp['body']['token'];

		if (!$this->login_token)
			throw new \Exception('Login not succesful' . ($verbose ? ' - ' . json_encode($resp)  : '') . '!');
		
		return $ret;
	}

	public function request(string $method, array $params = [], array $filter = null, bool $useCache = false, bool $logData = false)
	{
		$isLogin = ($method === 'login');
		$respOriginal = null;
		$ex = null;
		$toProcessRequests = [];
		if ((!$isLogin) && (!$this->login_token))
		{
			$t1_login = microtime(true);
			list($ret_login, $respOriginal_login, $ex_login, $toProcessRequest_login) = $this->login();
			$toProcessRequest_login[] = (microtime(true) - $t1_login);
			$toProcessRequests[] = $toProcessRequest_login;
		}

		$headers = [];
		list($ret, $respOriginal, $ex, $toProcessRequest, $respOriginalInfo) = $this->request_inner($method, $params, $filter, $useCache, $logData, $headers);
		$toProcessRequests[] = $toProcessRequest;

		// make sure that the login token is ok
		if (!$isLogin)
		{
			list($resp) = $ret;
			if ((!isset($resp['header']['success'])) || (!$resp['header']['success']))
			{
				if (($errMessages = $resp['header']['messages']))
				{
					if (is_scalar($errMessages))
						$errMessages = [$errMessages];
					$ferrMessage = reset($errMessages);
					if ($ferrMessage['code'] && in_array($ferrMessage['code'], ['TokenRequired']))
					{
						$this->login();

						list($ret, $respOriginal, $ex, $toProcessRequest) = $this->request_inner($method, $params, $filter, $useCache, $logData, $headers);
						$toProcessRequests[] = $toProcessRequest;
					}
				}
			}
		}

		return [$ret, $respOriginal, $ex, $toProcessRequests, $respOriginalInfo];
	}

	public function setupAuthHeader(&$headers = [])
	{
		if (!$headers)
			$headers = [];

		$hasAuthHeader = false;
		foreach ($headers ?: [] as $h)
		{
			if (substr($h, 0, strlen('Authorization: Bearer')) == 'Authorization: Bearer')
			{
				$hasAuthHeader = true;
				break;
			}
		}

		if (!$hasAuthHeader)
		{
			if (!$this->login_token)
			{
				return false;	
			}
			else
			{
				$headers[] = "Authorization: Bearer " . $this->login_token;
			}
		}

		return true;
	}

	public function request_inner(string $method, array $params = [], array $filter = null, bool $useCache = false, bool $logData = false, array $headers = [])
	{	
		if (!($apAction = $this->apiActionByMethod[$method]))
			throw new \Exception("Api Action not found for method [{$method}]");
			
		$headerAuthSetup = ($method === 'login') ? true : $this->setupAuthHeader($headers);
		if (!$headerAuthSetup)
		{
			$ex = new \Exception("Login not executed");
			$this->logError([
				'$method' => $method,
				'$apAction' => $apAction,
				'$params' => $params,
				'$filter' => $filter,
			], $ex);
		}
		else
		{
			if (in_array($method, ["hotel_search_results"]) && $this->useMultiInTop && (!$_GET['_force_exec_call']))
				$topRet = $this->requestOnTop_SetupMulti($method, $params, $filter, $useCache, $logData, $headers);
			else
				list($topRet, $topRetInfo, $toSendPostData, $url, $trqExecTime) = $this->requestOnTop($method, $params, $filter, $useCache, $logData, $headers);

			$ex = null;
			$ret = null;
			$respOriginal = $topRet;

			if ($topRet === false)
			{
				$ex = new \Exception("Invalid response from tour operator server");
				$this->logError([
					'$method' => $method,
					'$apAction' => $apAction,
					'$params' => $params,
					'$filter' => $filter,
				], $ex);
				#throw $ex;
			}

			if ($ex === null)
			{
				$ret = ($topRet && ($topRet !== null)) ? json_decode($topRet, true) : null;

				if (!$ret)
				{
					$ex = new \Exception($respOriginal ? "Tour Operator response cannot be decoded" : "No response from tour operator server");
					$this->logError([
						"\$method" => $method,
						"\$apAction" => $apAction,
						'\$params' => $params,
						'\$filter' => $filter,
					], $ex);
				}

				$ex = null;
				if (!$ret['header']['success'])
				{
					$isErr = (!isset($ret['header']['messages'][0]['code']) || (isset($ret['header']['messages']) && (count($ret['header']['messages']) > 1)) ||
						(($ret['header']['messages'][0]['code'] !== 'ProductPriceNotFound') && ($ret['header']['messages'][0]['code'] !== 'PriceNotFound')));

					if ($isErr)
					{
						$errMessage = "";
						if (($errMessages = $ret['header']['messages']))
						{
							if (is_scalar($errMessages))
								$errMessages = [$errMessages];
							foreach ($errMessages ?: [] as $errm)
							{
								$errMessage .= ((strlen($errMessage) > 0) ? ' | ' : '') . $errm['message'];
							}
						}
						$ex = new \Exception('Sitemul tur operatorului a raspuns cu eroare: ' . $errMessage);
						$this->logError([
							"\$method" => $method,
							"\$apAction" => $apAction,
							'\$params' => $params,
							'\$filter' => $filter,
							"\$logData" => $logData,
							"\$respOriginal" => $respOriginal,
						], $ex);
					}
				}
			}
		}

		//$_texec, $toSendPostData, $url

		$reqData = [
			'\$url' => $url,
			'\$toSendPostData' => $toSendPostData,
			'\$topRetInfo' => $topRetInfo,
		];

		$callKeyIdf = md5(json_encode($reqData));

		$toProcessRequest = [
			$apAction,
			json_encode($reqData),
			$respOriginal,
			$callKeyIdf,
			$trqExecTime
		];

		return [$ret, $respOriginal, $ex, $toProcessRequest, $topRetInfo];
	}

	public function requestOnTop_SetupMulti(string $method, array $params, array $filter = null, bool $useCache = false, bool $logData = false, array $headers = [])
	{
		$logDataSimple = false;
		if (!$logData)
		{
			$logData = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));
			$logDataSimple = true;
		}

		$url = $this->getApiUrl($method);
		if ($params)
			$params = json_encode($params);
		return $this->setupInMulti_CURL($url, $method, $params, $filter, $logData);
	}

	public function requestOnTop(string $method, array $params = [], array $filter = null, bool $useCache = false, bool $logData = false, array $headers = [])
	{
		$logDataSimple = false;
		if (!$logData)
		{
			$logData = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));
			$logDataSimple = true;
		}

		$headerAuthSetup = ($method === 'login') ? true : $this->setupAuthHeader($headers);
		if (!$headerAuthSetup)
		{
			$ex = new \Exception("Login not executed");
			$this->logError([
				'$method' => $method,
				'$useCache' => $useCache,
				'$logData' => $logData,
				'$logDataSimple' => $logDataSimple,
				'$headers' => $headers,
				'$params' => $params,
				'$filter' => $filter,
			], $ex);
			throw $ex;
		}
		
		$url = $this->getApiUrl($method);
		$cache_file = null;
		if ($useCache)
		{
			// get cache file path
			$cache_file = $this->getSimpleCacheFileForUrl($url, $params);
			// last modified
			$cf_last_modified = ($f_exists = file_exists($cache_file)) ? filemtime($cache_file) : null;
			$cache_time_limit = time() - $this->cacheTimeLimit;
			// if exists - last modified
			if (($f_exists) && ($cf_last_modified >= $cache_time_limit))
			{
				return [file_get_contents($cache_file), null];
			}
		}

		$curlHandle = $this->_curl_handle = q_curl_init_with_log();

		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $url);

		// send xml request to a server
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
		q_curl_setopt_with_log($curlHandle, CURLINFO_HEADER_OUT, true);

		if (!empty($params))
		{
			$toSendPostData = json_encode($params);
			
			if (!is_array($headers))
				$headers = [];

			$headers[] = 'Content-Type: application/json';
			$headers[] = 'Content-Length: ' . strlen($toSendPostData);

			q_curl_setopt_with_log($curlHandle, CURLOPT_POST, 1);
			q_curl_setopt_with_log($curlHandle, CURLOPT_POSTFIELDS, $toSendPostData);
		}

		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER, true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION, true);
		q_curl_setopt_with_log($curlHandle, CURLOPT_CONNECTTIMEOUT, 10);

		q_curl_setopt_with_log($curlHandle, CURLOPT_VERBOSE, 1);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HTTPHEADER, $headers);

		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);

		$this->_reqHeaders = [];
		$headers_out = &$this->_reqHeaders;
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers_out) {
			$len = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) < 2) // ignore invalid headers
				return $len;

			$name = strtolower(trim($header[0]));
			if (!array_key_exists($name, $headers_out))
				$headers_out[$name] = [trim($header[1])];
			else
				$headers_out[$name][] = trim($header[1]);
			return $len;
		});

		$t1 = microtime(true);
		$ret = q_curl_exec_with_log($curlHandle);
		
		$info = curl_getinfo($curlHandle);
		curl_close($curlHandle);
		$_trq = (microtime(true) - $t1);

		/*
		if ($method === 'get-offer-details')
		{
			echo $toSendPostData . '<br/><br/><br/>' . $ret . '<br/><br/><br/>';
			qvardump('$curl_info', $info);
		}
		*/

		/*
		if (($method === 'packages-price-search') || ($method === 'individual-price-search'))
		{
			echo $toSendPostData . '<br/><br/><br/>' . $ret . '<br/><br/><br/>';
			qvardump('$curl_info', $info);
		}
		*/

		if ($logData)
		{
			$toCallMethod = $logDataSimple ? 'logDataSimple' : 'logData';

			// log tourvisio data
			$this->{$toCallMethod}("request." . ($filter && $filter["cityId"] ? $filter["cityId"] : "_") . "." . $method, [
				"\$url" => $url,
				"\$method" => $method,
				'\$params' => $params,
				'\$filter' => $filter,
				"reqJSON" => $toSendPostData,
				"respJSON" => $ret,
				"duration" => (microtime(true) - $t1) . " seconds"
			]);
		}

		if ($ret === false)
		{
			$ex = new \Exception("Invalid response from server - " . curl_error($curlHandle));
			$this->logError([
				"\$url" => $url,
				"\$method" => $method,
				'\$params' => $params,
				'\$filter' => $filter,
			], $ex);
			throw $ex;
		}

		if ($useCache && $cache_file)
		{
			file_put_contents($cache_file, $ret);
		}

		$httpcode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		if ($httpcode >= 400)
		{
			# we have an error
			$ex = new \Exception($ret);
			$this->logError([
				"\$url" => $url,
				"\$method" => $method,
				'\$params' => $params,
				'\$filter' => $filter,
				"respJSON" => $ret,
				"duration" => (microtime(true) - $t1) . " seconds"
			], $ex);
			throw $ex;
		}

		$resp = [$ret, $info, $toSendPostData, $url, $_trq];

		if ($method != 'login')
		{
			$ex = null;
			try
			{
				throw new \Exception('test ex');
			} catch (\Exception $ex) {

			}
			#qvardump('$toSendPostData, $httpcode, $url, $method, $resp', $toSendPostData, $httpcode, $url, $method, $resp, $ex ? $ex->getTraceAsString() : "no_ex");
			#q_die();
		}
		
		return $resp;
	}
}
