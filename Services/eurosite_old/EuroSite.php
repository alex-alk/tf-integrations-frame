<?php

namespace Omi\TF;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EuroSite
 * 
 * @author Mihaita
 */
class EuroSite extends \QStorageEntry implements \QIStorage
{
	use TOStorage, EuroSiteCharters, EuroSitePackages, EuroSiteTours, EuroSiteIndividual, EuroSiteReqs, EuroSite_CacheAvailability, EuroSite_GetOffers, 
		EuroSite_CacheGeography,EuroSite_CacheStaticData, TOStorage_CacheAvailability, TOStorage_CacheGeography, TOStorage_CacheStaticData, TOStorage_GetOffers {
			EuroSite_CacheGeography::cacheTOPCountries insteadof TOStorage_CacheGeography;
			EuroSite_CacheGeography::cacheTOPRegions insteadof TOStorage_CacheGeography;
			EuroSite_CacheGeography::cacheTOPCities insteadof TOStorage_CacheGeography;
			EuroSite_CacheStaticData::cacheTOPHotels insteadof TOStorage_CacheStaticData;
			EuroSite_CacheStaticData::cacheTOPTours insteadof TOStorage_CacheStaticData;
			EuroSite_CacheAvailability::cacheChartersDepartures insteadof TOStorage_CacheAvailability;
			EuroSite_CacheAvailability::cacheToursDepartures insteadof TOStorage_CacheAvailability;
			EuroSiteCharters::saveCharters insteadof TOStorage_CacheAvailability;
			EuroSiteIndividual::getHotelsOffers insteadof TOStorage_GetOffers;
			EuroSiteIndividual::updateHotelDetails insteadof TOStorage_CacheAvailability;
			EuroSiteIndividual::saveHotels insteadof TOStorage_CacheAvailability;
		}

	public static $Exec = false;

	public static $RequestOriginalParams = null;

	public static $RequestData = null;

	public static $RequestsData = [];

	private static $_CacheData = [];

	private static $_LoadedCacheData = [];

	private static $_Facilities = [];

	public static $LOG_DATA = false;

	public static $DefaultCurrency = "EUR";

	public static $Config = [
		'_price_discount_only' => true,
		"nova_travel" => [
			"charter" => [
				//"FORCE_FEE_PERCENT" => true
			],
			"individual" => [
				//"FORCE_FEE_PERCENT" => true
			]
		],
		"paralela_45" => [
			"ToursDepartureCities" => [
				"ROBCH1", "ROBC", "MDCHS", "ROCRV", "ROCLJNPC", "ROIS", "ROTMS", "ROCNS", "ROSB", "MDCHS"
			],
			'skip_code_filtering_on_search' => true,
		],
		"cistour" => [
			"ToursDepartureCities" => [
				"ROBCH1", "ROBC", "MDCHS", "ROCRV", "ROCLJNPC", "ROIS", "ROTMS", "ROCNS", "ROSB"
			],
			"init" => [
				"CountryCodes" => [
					'BG' => 'BG',
					'GR' => 'GR',
					'TR' => 'TR',
					'RO' => 'RO'
				]
			],
			'skip_cities_on_tours' => [
				'ERPCRC' => 'Europa - Circuite',
				'LULXM' => 'Luxemburg'
			]
		],
		"expert_travel" => [
			//"reqs_on_city" => true,
			"ToursDepartureCities" => [
				"ROBCH1", "ROBC", "MDCHS", "ROCRV", "ROCLJNPC", "ROIS", "ROTMS", "ROCNS", "ROSB", "MDCHS"
			],
			"force_reqs_on_cities" => [
				"GRRVROLM" => "GRRVROLM"
			]
		],
		"bibi_touring" => [
			"charter" => [
				"use_noredd_price" => true
			]
		],
		"paradis" => [
			"charter" => [
				"use_comission" => true,
				"comission_type" => 8,
				"comission_code" => 1,
				"comission_is_negative" => true
			]
		],
		"malta_travel" => [
			"use_grila" => true,
			"reqs_on_city" => true
		],
		"holiday_store" => [
			"init" => [
				"CountryCodes" => [
					"BG" => "BG",
					"HR" => "HR",
					"ME" => "ME",
					"RO" => "RO",
				]
			]
		],
		"laguna_tour" => [
			"init" => [
				"CountryCodes" => [
					"BG" => "BG",
					"RO" => "RO"
				]
			]
		],
		"etalon" => [
			"init" => [
				"CountryCodes" => [
					"MD" => "MD",
					"RO" => "RO"
				]
			]
		],
		"bibi_touring" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
				]
			]
		],
		"paradis" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
				]
			]
		],
		"sinditour" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
				]
			]
		],
		"transilvania_es" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
				]
			]
		],
		"inter_tour" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"MD" => "MD"
				]
			],
			"tour" => [
				//"use_product_price" => true
			]
		],
		"tramp_travel" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"GR" => "GR",
				]
			]
		],
		"busola_travel" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"GR" => "GR",
				]
			]
		],
		"buburuza_travel" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"GR" => "GR",
				]
			]
		],
		"buburuza_travel_wh" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"GR" => "GR",
				]
			]
		],
		"book_and_travel" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO", 
					"BG" => "BG", 
				]
			]
		],
		// don't save cities for countries
		"holidayways" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO", 
					"MD" => "MD"
				]
			]
		],
		"aerotravel" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO", 
					"EG" => "EG"
				],
			],
			"use_services_in_charters_offs_indx" => true,
			"charters_grilla_services_categories" => [
				6
			],
			"charters_grilla_services_codes" => [
				#12187, 12188, 12284, 475, 12276
			],
			"force_reqs_on_cities" => [
				"GRSPR" => "GRSPR"
			]
		],
		"iri_travel" => [
			"init" => [
				"CountryCodes" => [
					"AT" => "AT",
					"BG" => "BG",
					"CZ" => "CZ",
					"HR" => "HR",
					"GR" => "GR",
					"IT" => "IT",
					"MD" => "MD",
					"RO" => "RO",
					"RS" => "RS",
					"SK" => "SK",
					"UA" => "UA",
					"HU" => "HU",
					"RS" => "RS",
				],
				"ToAddCountries" => [
					"RS" => ["CountryCode" => "RS", "CountryName" => "Serbia"],
				]
			],
			'skip_code_filtering_on_search' => true
		],
		"ultramarin" => [
			"init" => [
				"CountryCodes" => [
					"AT" => "AT",
					"BA" => "BA",
					"BG" => "BG",
					"CZ" => "CZ",
					"HR" => "HR",
					"CH" => "CH",
					"DE" => "DE",
					"IS" => "IS",
					"EG" => "EG",
					"AE" => "AE",
					"FR" => "FR",
					"JO" => "JO",
					"IT" => "IT",
					"GB" => "GB",
					"MA" => "MA",
					"RO" => "RO",
					"ES" => "ES",
					"ID" => "ID",
					"IL" => "IL",
					"JP" => "JP",
					"MY" => "MY",
					"MV" => "MV",
					"NL" => "NL",
					"PT" => "PT",
					"RU" => "RU",
					"SB" => "SB",
					"SG" => "SG",
					"SI" => "SI",
					"LK" => "LK",
					"US" => "US",
					"TW" => "TW",
					"TZ" => "TZ",
					"TH" => "TH",
					"TR" => "TR",
					"HU" => "HU",
					"RS" => "RS",
					"CL" => "CL"
				],
				"ToAddCountries" => [
					"RS" => ["CountryCode" => "RS", "CountryName" => "Serbia"],
					"CL" => ["CountryCode" => "CL", "CountryName" => "Chile"],
				],
				"GetStaticCitiesForCountries" => [
					"RO" => "RO",
					"RS" => "RS"
				]
			]
		],

		// don't save cities for countries
		"soleytour_es" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO", 
					"TR" => "TR"
				]
			]
		],

		"air_tour_travel" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"BG" => "BG",
					"TR" => "TR",
				]
			]
		],
		
		'eximtur_eurosite' => [
			# on init
			'init' => [
				# Get data from countries
				'CountryCodes' => [
					'RO' => 'RO',	# Romania
					'HU' => 'HU',	# Hungary
					'DE' => 'DE',	# Germany
					'IT' => 'IT',	# Italy
					'PL' => 'PL',	# Poland
					'ES' => 'ES',	# Sain
					'FR' => 'FR',	# France
					'GR' => 'GR',	# Greece
					'PT' => 'PT',	# Portugal
					'AT' => 'AT',	# Austria
					'BE' => 'BE',	# Belgium
					'HR' => 'HR',	# Croatia
					'CY' => 'CY',	# Cyprus
					'CZ' => 'CZ',	# Czech Republic
					'GB' => 'GB',	# United Kingdom
					'CH' => 'CH',	# Switzerland
					'SI' => 'SI',	# Slovenia
					'NL' => 'NL',	# Netherlands
					'IE' => 'IE',	# Ireland
				]
			],
			
			'prefix_hotels_with_reseller_code' => true,
			
			# allowed touroperators
			'allowed_touroperators_code' => [
				'ET2',		# Eurotours
				'ETKNSE',	# Knossos
				'H2B',		# Hotel2Business
				'AST',		# As Tour
				'BR',		# Bros
				'UN2',		# Uniline
				'WHE',		# Web Hotelier
			],
			
			# skip code filtering
			'skip_code_filtering_on_search' => false
		],
		"3bis" => [
			"init" => [
				"CountryCodes" => [
					"RO" => "RO",
					"BG" => "BG",
				]
			]
		],
	];
	
	public function __construct($name = null, \QStorageFolder $parent = null) 
	{
		$this->SOAPInstance = new EuroSiteApi();
		return parent::__construct($name, $parent);
	}
	
	public function initStorage_doAfter()
	{
		$this->TOPInstance = $this;
	}
	
	/**
	 * Do rest api
	 * @param string $method
	 * @param array $filter
	 * @return type
	 */
	public function doRestAPI($method, $filter = null)
	{
		return $this->{$method}($filter);
	}
	
	/**
	 * Get SOAP API instance
	 * 
	 * @return type
	 */
	public function getSoapInstance()
	{
		return $this->SOAPInstance->client;
	}

	/**
	 * Do SOAP API request.
	 * 
	 * @return type
	 * 
	 * @throws \Exception
	 */
	public function doRequest($requestType, $params = null, $saveAttrs = false, $useCache = false)
	{
		// error no soap api instance found
		if (!$this->SOAPInstance)
			throw new \Exception("No Api instance found!");
		
		// keep request / response in ret for dump purpose
		$ret = $this->SOAPInstance->doRequest($requestType, $params, $saveAttrs, $useCache);		
		
		// return ret
		return $ret;
	}
	
	/*===================================SETUP HERE STORAGE METHODS=============================================*/
	/**
	 * Gets the storage containers that contain a certain type
	 * In some cases a model may be contained in more than one container (to have the full information), or different instances can be in more than one container
	 *
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 * 
	 * @return QIStorageContainer[]
	 */
	public function getStorageContainersForType($model_type)
	{

	}
	/**
	 * Gets the default storage container for the specified type
	 *
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 */
	public function getDefaultStorageContainerForType($model_type)
	{
		
	}
	/**
	 * Queries the storage to get the needed data
	 *
	 * @param QModelQuery $query
	 * @param QIModel[] $instances Must be indexed by ID (UID should be forced somehow, if ot available use: '$id/$type')
	 */
	public function queryStorage(\QModelQuery $query, $instances = null)
	{
		
	}
	/**
	 * Ask the storage to create storage container(s) for the specified data types
	 *
	 * @param QModelTypeSqlInfo[] $model_info
	 * @param (string|QModelType)[] $data_types
	 * @param boolean $as_default
	 * @param QIStorageFolder $parent
	 * @param QIStorageContainer $containers
	 * @param string $prefix
	 * @param string $sufix
	 * 
	 * @return QIStorageContainer[]
	 */
	public function syncStorageContainersForDataTypes($model_info = null, $data_types = null, $as_default = false, \QIStorageFolder $parent = null, $containers = null, $prefix = "", $sufix = "")
	{
		
	}

	public function linkGeo()
	{
		$storages = \QApi::Query("Storages", null, [
			"Active" => true, 
			"Class" => get_called_class(),
			"NOT" => $this->TourOperatorRecord->getId(),
			"LIMIT" => [0, 1]
		]);

		$storage = $storages ? reset($storages) : null;

		if (!$storage)
			return;

		$mysqli = \QApp::GetStorage()->connection;

		$mysqli->query("UPDATE `Cities` AS `TOP_Cities` JOIN `Cities` AS `LTOP_Cities` ON "
			. "(`TOP_Cities`.`InTourOperatorId`=`LTOP_Cities`.`InTourOperatorId` AND `TOP_Cities`.`\$Country`=`LTOP_Cities`.`\$Country`) "
			. "SET `TOP_Cities`.`\$Master`=`LTOP_Cities`.`\$Master` "
			. "WHERE "
				. "`TOP_Cities`.`\$TourOperator`='{$this->TourOperatorRecord->getId()}' AND "
				. "`LTOP_Cities`.`\$TourOperator`='{$storage->getId()}' AND "
				. "ISNULL(`TOP_Cities`.`\$Master`) AND !ISNULL(`LTOP_Cities`.`\$Master`)");
	}
	
	public function testConnectivity()
	{
		return $this->SOAPInstance->testConnectivity();
	}
	
	public function getAvailableCountries()
	{
		$__charters_rspData = [
			"plane" => static::GetResponseData($this->doRequest("getPackageNVRoutesRequest", array("Transport" => "plane"), true), "getPackageNVRoutesResponse"),
			"bus" => static::GetResponseData($this->doRequest("getPackageNVRoutesRequest", array("Transport" => "bus"), true), "getPackageNVRoutesResponse")
		];

		$useCountries = [];
		foreach ($__charters_rspData as $respData)
		{
			$countries = ($respData && $respData["Country"]) ? $respData["Country"] : null;
			if ($countries["CountryCode"])
				$countries = [$countries];

			foreach ($countries ?: [] as $country)
			{
				if (!$country["CountryCode"])
					continue;
				$useCountries[$country["CountryCode"]] = $country["CountryCode"];
			}
		}

		$__tours_rspData = static::GetResponseData($this->doRequest("CircuitSearchCityRequest"), "CircuitSearchCityResponse");
		$countries = ($__tours_rspData && $__tours_rspData["Country"]) ? $__tours_rspData["Country"] : null;
		if ($countries && isset($countries["CountryCode"]))
			$countries = [$countries];

		// go thorugh countries and setup needed data
		foreach ($countries ?: [] as $country)
		{
			if (empty($country["CountryCode"]))
				continue;
			$useCountries[$country["CountryCode"]] = $country["CountryCode"];
		}

		return $useCountries;
	}

	public function initTourOperator($config = [])
	{
		if (!is_array($config))
			$config = [];

		$this->syncStaticData($config);

		// link geo
		$this->linkGeo();
		
		$this->cacheTOPCitiesWithIndividualOffers();
	}
	
	public function getHotelsWithIndividualOffers($parameters)
	{
		$from = $parameters["__from__"];
		unset($parameters["__from__"]);

		$objs = null;
		if (!$parameters)
			throw new \Exception("Paramters are mandatory!");
		if ((!$parameters["CityCode"] && !$parameters["CityName"]))
			throw new \Exception("City Code or City Name must be provided");

		if (!$parameters["PeriodOfStay"] || !$parameters["PeriodOfStay"][0] || !$parameters["PeriodOfStay"][1] || 
			!$parameters["PeriodOfStay"][0]["CheckIn"] || !$parameters["PeriodOfStay"][1]["CheckOut"])
			throw new \Exception("Stay period must be provided!");
		if ($parameters["ProductCode"] && (!\Omi\Util\SoapClientAdvanced::$InBookingSearchRequest))
			$parameters["getFeesAndInstallments"] = true;

		return $this->getIndividualOffers($parameters, $objs, (($from === "HotelsWithIndividualOffers") || ($from === "InternationalHotels")));
	}

	// ($data_types, $actions = null
	// public function sync($action = null, $parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, $as_simulation = false);
	// how about sync on a collection ... because this is what is about ... 
	
	/**
	 * @api.enable
	 * 
	 * @param string $from
	 * @param string $selector
	 * @param array $parameters
	 * 
	 * @return QIModel
	 */
	public static function ApiQuery($storage_model, $from, $from_type, $selector = null, $parameters = null, $only_first = false, $id = null)
	{
		if (!is_string($from))
			throw new \Exception("From type mysmatch!");

		$self = $storage_model;

		if ((!$self) || (get_class($self) != get_called_class()) || (!$self->ApiPassword) || (!$self->ApiUsername) || (!$self->ApiUrl))
			throw new \Exception("No storage instance provided!");

		//qvardump("API QUERY ON EURSITE", static::$Exec, $from, $from_type, $selector, $parameters, $only_first, $id);
		
		//$idf = \Omi\TFuse\Api\TravelFuse::ApiQuery_GetIdf($storage_model, $from, $from_type, $selector, $parameters, $only_first, $id);
		//if (($apiQueryCachedData = \Omi\TFuse\Api\TravelFuse::ApiQuery_GetCachedResult($idf)) !== null)
		//	return $apiQueryCachedData;
		
		$ret = null;
		switch ($from)
		{
			case "Flights" : 
			{
				$ret = null;
				break;
			}
			case "Countries" :
			{
				break;
			}
			case "Cities" :
			{
				break;
			}
			case "HotelsFacilities" : 
			{
				$ret = $self->getHotelsFacilities($parameters);
				break;
			}
			case "EurositeRooms" : 
			{
				$ret = $self->getRoomsTypes();
				break;
			}
			case "Installments" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");

				$installmentsParams = $parameters;
				$installmentsParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				if (($installmentsDefintions = \Omi\Comm\Payment::GetPartnerInstallmentsDefinitions($installmentsParams)) && count($installmentsDefintions))
				{
					$installments = \QApi::Call("\Omi\Comm\Payment::GetPartnerInstallments", $installmentsParams);
				}
				else if ($self->TourOperatorRecord->HasInstallments)
				{
					$hasVariantId = false;
					$hasPeriodOfStay = false;
					$hasRooms = false;

					foreach ($parameters as $param)
					{
						if (!is_array($param))
							continue;

						if ($param["VariantId"])
							$hasVariantId = true;

						if (!$param["PeriodOfStay"] || !$param["PeriodOfStay"][0] || !$param["PeriodOfStay"][1] || 
								!$param["PeriodOfStay"][0]["CheckIn"] || !$param["PeriodOfStay"][1]["CheckOut"])
							$hasPeriodOfStay = true;

						if ($param["Rooms"])
						{
							$hasRooms = true;
							foreach ($param["Rooms"] as $room)
							{
								$hpn = false;
								foreach ($room["Room"] as $r_val)
								{
									if (is_array($r_val) && $r_val["PaxNames"])
									{
										$hpn = true;
										break;
									}
								}
								if (!$hpn)
									throw new \Exception("Pax names not provided!");
							}
						}
					}

					if (!$hasVariantId)
						throw new \Exception("Variant ID not provided!");

					if (!$hasPeriodOfStay)
						throw new \Exception("Stay period must be provided!");

					if (!$parameters["TourOpCode"])
						throw new \Exception("TourOpCode must be provided!");

					if (!$hasRooms)
						throw new \Exception("Rooms not provided!");
					$installments = $self->getHotelInstallments($parameters);
				}

				$ret = $installments;
				break;
			}
			case "InternationalHotels" : 
			{
				$ret = null;
				if (\Omi\App::HasIndividualInOnePlace() || $parameters["category"] || $parameters["custDestination"] || 
					$parameters["mainDestination"] || $parameters["dynamic_packages"] || $parameters["for_dynamic_packages"])
				{
					$parameters["__from__"] = $from;
					$ret = $self->getHotelsWithIndividualOffers($parameters);
				}
				break;
			}
			case "IndividualOffers" :
			case "HotelsWithIndividualOffers" :
			{
				$parameters["__from__"] = $from;
				$ret = $self->getHotelsWithIndividualOffers($parameters);
				break;
			}
			case "Hotels" :
			{
				if (!$id || !is_array($id))
					throw new \Exception("Paramters are mandatory!");
				if (!$id["ProductCode"])
					throw new \Exception("ProductCode not provided!");
				if (!$id["TourOpCode"])
					throw new \Exception("TourOpCode not provided!");
				$ret = $self->getHotelInfo($id);
				break;
			}
			case "HotelsInfo" :
			{
				if (!$id || !is_array($id))
					throw new \Exception("Paramters are mandatory!");
				if (!$id["ProductCode"])
					throw new \Exception("ProductCode not provided!");
				if (!$id["TourOpCode"])
					throw new \Exception("TourOpCode not provided!");
				$ret = $self->getHotelUpdateInfo($id);
				break;
			}
			case "HotelSupliments" :
			{
				if ($id)
				{
					if (!is_array($id))
						throw new \Exception("Paramters are mandatory!");
					if (!$id["ProductCode"])
						throw new \Exception("Hotel code must be provided!");
					if (!$id["Services"] || !$id["Services"]["Service"] || !$id["Services"]["Service"][0])
						throw new \Exception("Service must be provided!");

					$serviceData = $id["Services"]["Service"];
					$hasServiceType = false;
					$hasServiceCode = false;
					$hasPeriodOfStay = false;
					$hasPaxNames = false;

					foreach ($serviceData as $srv)
					{
						foreach ($srv as $key => $value)
						{
							if ($key === "ServiceType")
								$hasServiceType = true;
							else if ($key === "ServiceCode")
								$hasServiceCode = true;
							else if (($key === "PeriodOfStay") && $value[0] && $value[1] && $value[0]["CheckIn"] && $value[1]["CheckOut"])
								$hasPeriodOfStay = true;
							else if ($key === "PaxNames")
							{
								$hasPaxNames = true;
								foreach ($value as $pname)
								{
									if (!$pname["PaxName"])
										throw new \Exception("Pax Name not provided!");
								}
							}
						}
					}

					if (!$hasServiceType)
						throw new \Exception("ServiceType must be provided!");

					if (!$hasServiceCode)
						throw new \Exception("ServiceCode must be provided!");

					if (!$hasPeriodOfStay)
						throw new \Exception("Period of stay must be provided!");

					if (!$hasPaxNames)
						throw new \Exception("Pax Names must be provided!");			

					$ret = $self->getHotelSupliment($id);
				}
				else
				{
					if (!$parameters)
						throw new \Exception("Paramters are mandatory!");
					if (!$parameters["ProductCode"])
						throw new \Exception("Hotel code must be provided!");
					if (!$parameters["PeriodOfStay"] || !$parameters["PeriodOfStay"][0] || !$parameters["PeriodOfStay"][1] || 
						!$parameters["PeriodOfStay"][0]["CheckIn"] || !$parameters["PeriodOfStay"][1]["CheckOut"])
						throw new \Exception("Stay period must be provided!");
					$ret = $self->getHotelSupliments($parameters);
				}
				break;
			}
			case "OffersForDoubleChecking" :
			{
				$ret = null;
				break;
			}
			case "HotelFeesAndInstallments" : 
			{
				list($fees_params, $installments_params) = $parameters;
				$fees = static::ApiQuery($storage_model, "HotelFees", null, null, $fees_params);
				$installments = static::ApiQuery($storage_model, "Installments", null, null, $installments_params);
				list($installments, $fees) = 
					\Omi\TFuse\Api\TravelFuse::MergePaymentsAndCancelFees($installments, $fees, $fees_params["Price"], $fees_params["CheckIn"], $self, $fees_params, $installments_params);
				//qvardump("after normalize fees and installments", $fees, $installments);
				$ret = [$fees, $installments];
				break;
			}
			case "TourFeesAndInstallments" :
			{
				list($fees_params, $installments_params) = $parameters;
				$fees = static::ApiQuery($storage_model, "TourFees", null, null, $fees_params);
				$installments = static::ApiQuery($storage_model, "TourInstallments", null, null, $installments_params);

				list($installments, $fees) = 
					\Omi\TFuse\Api\TravelFuse::MergePaymentsAndCancelFees($installments, $fees, $fees_params["Price"], $fees_params["CheckIn"], $self, $fees_params, $installments_params);
				$ret = [$fees, $installments];
				break;
			}
			case "HotelFees" : 
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");

				$feesParams = $parameters;
				$feesParams["ApiProvider"] = $self->TourOperatorRecord->getId();

				if (($feesDefinitions = \Omi\Comm\Offer\CancelFee::GetPartnerFeesDefinitions($feesParams)) && count($feesDefinitions))
				{
					$fees = \QApi::Call("\Omi\Comm\Offer\CancelFee::GetPartnerFees", $feesParams);			
				}
				else if ($self->TourOperatorRecord->HasCancelFees)
				{
					$hasVariantId = false;
					$hasPeriodOfStay = false;
					$hasRooms = false;

					foreach ($parameters as $param)
					{
						if (!is_array($param))
							continue;

						if ($param["VariantId"])
							$hasVariantId = true;

						if (!$param["PeriodOfStay"] || !$param["PeriodOfStay"][0] || !$param["PeriodOfStay"][1] || 
								!$param["PeriodOfStay"][0]["CheckIn"] || !$param["PeriodOfStay"][1]["CheckOut"])
							$hasPeriodOfStay = true;

						if ($param["Rooms"])
						{
							$hasRooms = true;
							foreach ($param["Rooms"] as $room)
							{
								$hpn = false;
								foreach ($room["Room"] as $r_val)
								{
									if (is_array($r_val) && $r_val["PaxNames"])
									{
										$hpn = true;
										break;
									}
								}
								if (!$hpn)
									throw new \Exception("Pax names not provided!");
							}
						}
					}

					if (!$hasVariantId)
						throw new \Exception("Variant ID not provided!");

					if (!$hasPeriodOfStay)
						throw new \Exception("Stay period must be provided!");

					if (!$hasRooms)
						throw new \Exception("Rooms not provided!");

					if (!$parameters["TourOpCode"])
						throw new \Exception("TourOpCode must be provided!");

					$fees = $self->getHotelFees($parameters);
				}

				if (static::NoFees($fees))
					$fees = static::GetDefaultFees($parameters);
				$ret = $fees;
				break;
			}
			case "Packages" :
			{
				if (!$parameters || !$parameters["Transport"])
					throw new \Exception("Transport not defined!");
				$ret = $self->getPackages($parameters);
				break;
			}
			/**
			 * This is the real Charters request
			 */
			case "PackagesOffers" :
			case "HotelsWithPackages" :
			{
				//qvardump("CALL FOR Charters: ", $parameters);
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");

				// why??
				#unset($parameters["Transport"]);
				if (!$parameters["CityCode"] && !$parameters["CityName"] && !$parameters["Zone"])
					throw new \Exception("City Code|City Name|ZONE must be provided!");
				if (!$parameters["PeriodOfStay"] || !$parameters["PeriodOfStay"][0] || !$parameters["PeriodOfStay"][1] || 
					!$parameters["PeriodOfStay"][0]["CheckIn"] || !$parameters["PeriodOfStay"][1]["CheckOut"])
					throw new \Exception("Stay period must be provided!");
				if (!$parameters["DepCityCode"])
					throw new \Exception("Departure City Code not provided!");
				/*
				if (!$parameters["Days"])
					throw new \Exception("Days must be provided!");
				*/
				if (!$parameters["Rooms"])
					throw new \Exception("Rooms must be provided!");
				foreach ($parameters["Rooms"] as $room)
				{
					if (!$room["Room"]["Code"])
						throw new \Exception("Room Code is mandatory!");
				}

				unset($parameters["Destination"]);
				if ($parameters["ProductCode"])
				{
					if (!\Omi\Util\SoapClientAdvanced::$InBookingSearchRequest)
						$parameters["getFeesAndInstallments"] = true;
					unset($parameters["ProductName"]);
					unset($parameters["ProductType"]);
				}
				$ret = $self->getPackagesOffers($parameters, $objs, ($from === "HotelsWithPackages"));
				break;
			}
			case "CharterCountries" :
			{
				$ret = $self->getChartersLocations($parameters);
				break;
			}
			case "Charters" : 
			{
				if ($id)
				{
					if (!is_array($id))
						throw new \Exception("Paramters are mandatory!");
					if (!$id["ProductCode"])
						throw new \Exception("ProductCode not provided!");
					if (!$id["TourOpCode"])
						throw new \Exception("TourOpCode not provided!");
					$ret = $self->getCharter($id);
				}
				else
				{
					if (!$parameters)
						throw new \Exception("Paramters are mandatory!");
					if (!$parameters["Departure"] || !$parameters["Departure"][0] || !$parameters["Departure"][0]["CityCode"])
						throw new \Exception("Departure not provided!");
					if (!$parameters["Destination"] || !$parameters["Destination"][0] || !$parameters["Destination"][0]["CityCode"])
						throw new \Exception("Destination not provided!");
					if (!$parameters["DepartureDate"])
						throw new \Exception("Departure date not provided!");
					if (!$parameters["CurrencyCode"])
						throw new \Exception("Currency not provided!");
					if (!$parameters["Returndate"])
						throw new \Exception("Return date not provided!");
					if (!$parameters["PaxNames"])
						throw new \Exception("Pax Names not provided!");

					foreach ($parameters["PaxNames"] as $pn)
					{
						if (!$pn["PaxName"])
							throw new \Exception("Pax name not provided!");
					}
					$ret = $self->getCharters($parameters);
				}
				break;
			}
			case "CharterSupliments" :
			{
				if ($id)
				{
					if (!is_array($id))
						throw new \Exception("Paramters are mandatory!");
					if (!$id["CharterId"])
						throw new \Exception("CharterId must be provided!");
					if (!$id["ServiceId"])
						throw new \Exception("ServiceId must be provided!");
					if (!$id["PaxNames"])
						throw new \Exception("PaxNames must be provided!");
					if (!$parameters["TourOpCode"])
						throw new \Exception("TourOpCode not provided!");
					foreach ($id["PaxNames"] as $pname)
					{
						if (!$pname["PaxName"])
							throw new \Exception("Pax Name not provided!");
					}
					$ret = $self->getCharterSupliment($id);
				}
				else
				{
					if (!$parameters)
						throw new \Exception("Paramters are mandatory!");
					if (!$parameters["CharterId"])
						throw new \Exception("Charter ID not provided!");
					if (!$parameters["Date"])
						throw new \Exception("Date not provided!");
					if (!$parameters["TourOpCode"])
						throw new \Exception("TourOpCode not provided!");
					$ret = $self->getCharterSupliments($parameters);
				}
				break;
			}
			case "CharterFees" : 
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");

				$hasCharterId = false;
				$hasDeparture = false;
				$hasDestination = false;
				$hasPaxNames = false;

				foreach ($parameters as $param)
				{
					if (!is_array($param))
						continue;

					if ($param["CharterId"])
						$hasCharterId = true;
					if ($param["Departure"])
					{
						$hasDepartCity = false;
						foreach ($param["Departure"] as $departData)
						{
							if ($departData["CityCode"] || $departData["CityName"])
							{
								$hasDepartCity = true;
								break;
							}
						}
						if ($hasDepartCity)
							$hasDeparture = true;
					}
					if ($param["Destination"])
					{
						$hasDestCity = false;
						foreach ($param["Destination"] as $destData)
						{
							if ($destData["CityCode"] || $destData["CityName"])
							{
								$hasDestCity = true;
								break;
							}
						}
						if ($hasDestCity)
							$hasDestination = true;
					}

					if ($param["PaxNames"])
					{
						$hasPaxNames = true;
						foreach ($param["PaxNames"] as $pn)
						{
							if (!$pn["PaxName"])
								throw new \Exception("Pax name not provided!");
						}
					}
				}

				if (!$hasCharterId)
					throw new \Exception("CharterId not provided!");

				if (!$hasDeparture)
					throw new \Exception("Charter Departure not provided!");

				if (!$hasDestination)
					throw new \Exception("Charter Destination not provided!");

				if (!$hasPaxNames)
					throw new \Exception("Pax Names not provided!");

				$ret = $self->getCharterFees($parameters);
				break;
			}
			case "TourCountries" :
			{
				$ret = $self->getCountriesWithTours();
				break;
			}
			case "Circuits" :
			{
				unset($parameters["Destination"]);
				unset($parameters["DestinationType"]);

				if ($id)
				{
					if (is_string($id))
						$id = ["ProductCode" => $id];
					if (!is_array($id))
						throw new \Exception("Paramters are mandatory!");
					if (!$id["ProductCode"])
						throw new \Exception("ProductCode not provided!");
					if (!$parameters["TourOpCode"])
						throw new \Exception("TourOpCode not provided!");
					if ($parameters["ProductCode"] && !\Omi\Util\SoapClientAdvanced::$InBookingSearchRequest)
						$parameters["getFeesAndInstallments"] = true;
					$ret = $self->getTour($id);
				}
				else
				{
					if (!$parameters)
						throw new \Exception("Paramters are mandatory!");
					if (!$parameters["CityCode"])
						throw new \Exception("City Code is mandatory!");
					if (!$parameters["Year"])
						throw new \Exception("Year not provided!");
					if (!$parameters["Month"])
						throw new \Exception("Rooms not provided!");
					if (!$parameters["Rooms"])
						throw new \Exception("Rooms not provided!");
					if ($parameters["ProductCode"] && !\Omi\Util\SoapClientAdvanced::$InBookingSearchRequest)
						$parameters["getFeesAndInstallments"] = true;
					$ret = $self->getTours($parameters);
				}
				break;
			}
			case "TourSupliments" : 
			{
				if ($id)
				{
					if (!is_array($id))
						throw new \Exception("Paramters are mandatory!");
					if (!$id["CircuitId"])
						throw new \Exception("CircuitId is mandatory!");
					if (!$id["CircuitDep"])
						throw new \Exception("CircuitDep not provided!");
					if (!$id["Service"])
						throw new \Exception("Service not provided!");

					$hasPaxNames = false;
					foreach ($id as $data)
					{
						if (!is_array($data))
							continue;

						if ($data["PaxNames"])
						{
							$hasPaxNames = true;
							foreach ($data["PaxNames"] as $pname)
							{
								if (!$pname["PaxName"])
									throw new \Exception("Pax Name not provided!");
							}
						}
					}

					if (!$hasPaxNames)
						throw new \Exception("PaxNames must be provided!");
					$ret = $self->getTourSupliment($id);
				}
				else
				{
					if (!$parameters)
						throw new \Exception("Paramters are mandatory!");
					if (!$parameters["CircuitId"])
						throw new \Exception("CircuitId is mandatory!");
					if (!$parameters["CircuitDep"])
						throw new \Exception("CircuitDep not provided!");
					$ret = $self->getTourSupliments($parameters);
				}
				break;
			}
			case "TourFees" : 
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				$feesParams = $parameters;
				$feesParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				if (($feesDefinitions = \Omi\Comm\Offer\CancelFee::GetPartnerFeesDefinitions($feesParams)) && count($feesDefinitions))
					$fees = \QApi::Call("\Omi\Comm\Offer\CancelFee::GetPartnerFees", $feesParams);
				else if ($self->TourOperatorRecord->HasCancelFees)
				{
					unset($parameters["Search_ResultCode"]);
					unset($parameters["PackageCode"]);
					unset($parameters["PackageRoomCode"]);
					unset($parameters["RoomCode"]);

					if (!$parameters["UniqueId"])
						throw new \Exception("UniqueId is mandatory!");			
					$fees = $self->getTourFees($parameters);
				}

				// setup default cancel fees - if the case
				if (static::NoFees($fees))
					$fees = static::GetDefaultFees($parameters);
				$ret = $fees;
				break;
			}
			case "TourInstallments" : 
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				
				$installmentsParams = $parameters;
				$installmentsParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				//if (($installmentsDefintions = \QApi::Call("\Omi\Comm\Payment::GetPartnerInstallmentsDefinitions", $installmentsParams)))
				if (($installmentsDefintions = \Omi\Comm\Payment::GetPartnerInstallmentsDefinitions($installmentsParams)) && count($installmentsDefintions))
					$installments = \QApi::Call("\Omi\Comm\Payment::GetPartnerInstallments", $parameters);
				else if ($self->TourOperatorRecord->HasInstallments)
				{
					$installments = null;
				}
				$ret = $installments;
				break;
			}
			case "Orders" : 
			{
				if (!$id)
					throw new \Exception("Order id not provided!");
				$ret = $self->getBookingRequest($id);
				break;
			}
			case "OrdersDocuments" : 
			{
				if (!$id)
					throw new \Exception("Order id not provided!");
				$ret = $self->getOrderDocuments($id);
				break;
			}
			default :
			{
				throw new \Exception($from." not implemented!");
			}
		}

		//\Omi\TFuse\Api\TravelFuse::ApiQuery_SetCachedResult($idf, $ret);
		return $ret;
	}
	/**
	 * @api.enable
	 * 
	 * @param QIModel $data
	 * @param integer $state
	 * @param string $from
	 * @return mixed
	 * @throws \Exception
	 */
	public static function ApiSave($storage_model, $from, $from_type, $data, $state = null)
	{
		if (!is_string($from))
			throw new \Exception("From type mysmatch!");

		$self = $storage_model;

		if ((!$self) || (get_class($self) != get_called_class()) || (!$self->ApiPassword) || (!$self->ApiUsername) || (!$self->ApiUrl))
			throw new \Exception("No storage instance provided!");

		// set here the code for saving
		switch($from)
		{
			/*
			case "StaysDiscounts" :
			{
				return $self->saveStaysDiscounts();
			}
			case "EurositeRooms" :
			{
				return $self->saveRooms();
			}
			case "ChartersDiscounts" : 
			{
				return $self->saveChartersDiscounts();
			}
			*/
			case "Hotels" : 
			{
				return $self->saveHotels();
			}
			case "Charters" :
			{
				return $self->saveCharters();
			}
			case "Tours" :
			{
				return $self->saveTours();
			}
			case "Orders" :
			{
				return $self->saveBooking($data);
			}
			case "OrdersUpdates" :
			{
				return $self->saveOrdersUpdates();
			}
			case "CachedData" :
			{
				$self->setupCachedData();
				break;
			}
			default :
			{
				throw new \Exception("Save for [{$from}] not implemented!");
			}
		}
	}

	/**
	 * 
	 */
	public function setupCachedData(array $config = [])
	{
		$this->cacheTOPCountries($config);
		$this->cacheTOPCities($config);
		$this->cacheChartersDepartures($config);
		$this->cacheToursDepartures($config);
	}

	public function saveCharters_setupCountry($countryCode, $dbCountriesByCode, &$countries = [])
	{
		if (!($country = $dbCountriesByCode[$countryCode]))
			throw new \Exception('Country not found [' . $countryCode . '] in database - Import static data must be setup');
		if (!($countryInTopId = $country->getTourOperatorIdentifier($this->TourOperatorRecord)))
		{
			if (!$country->InTourOperatorsIds)
				$country->InTourOperatorsIds = new \QModelArray();
			if ($this->TrackReport)
			{
				if (!$this->TrackReport->NewCountries)
					$this->TrackReport->NewCountries = 0;
				$this->TrackReport->NewCountries++;
			}
			$apiStorageId->__added_to_system__ = true;
			$apiStorageId = new \Omi\TF\ApiStorageIdentifier();
			$apiStorageId->setTourOperator($this->TourOperatorRecord);
			$apiStorageId->setIdentifier($countryCode);
			$apiStorageId->setFromTopAddedDate(date("Y-m-d H:i:s"));
			$country->InTourOperatorsIds[] = $apiStorageId;
			$countries[$country->Code] = $country;
		}
		return $country;
	}

	public function saveCharters_setupCounty($destination, $countryObj, $dbCountiesByInTopIds, &$counties = [], $moveCounty = false)
	{
		$saveCounty = false;
		if (!($county = $dbCountiesByInTopIds[$destination["ZoneCode"]]))
		{
			$county = new \Omi\County();
			$county->setName($destination["ZoneName"]);
			$county->setInTourOperatorId($destination["ZoneCode"]);
			$county->setTourOperator($this->TourOperatorRecord);
			$county->setFromTopAddedDate(date("Y-m-d H:i:s"));
			$county->setCountry($countryObj);
			$saveCounty = true;
			if ($this->TrackReport)
			{
				if (!$this->TrackReport->NewCounties)
					$this->TrackReport->NewCounties = 0;
				$this->TrackReport->NewCounties++;
			}
			echo "<div style='color: green;'>Add new zone [{$county->InTourOperatorId}|{$county->Name}] to system on country [{$countryObj->Code}|{$countryObj->Name}]</div>";
			$county->__added_to_system__ = true;
		}
		else if ($county->Name != $destination["ZoneName"])
		{
			echo "<div style='color: red;'>Update county name from [{$county->Name}] to [{$destination["ZoneName"]}]</div>";
			$county->setName($destination["ZoneName"]);
			$county->setFromTopModifyDate(date("Y-m-d H:i:s"));
			$saveCounty = true;
		}
		// we may not move a city from a country to another
		if ($county->Country->Code != $countryObj->Code)
		{
			if (!$moveCounty)
				throw new \Exception("County was moved from country [{$county->Country->Code}] to country [{$countryObj->Code}]");
			else
			{
				$county->setCountry($countryObj);
				$saveCounty = true;
			}
		}
		if ($saveCounty)
			$counties[$county->InTourOperatorId] = $county;
		return $county;
	}

	public function saveCharters_setupCity($destination, $countryObj, $dbCitiesByInTopIds, &$cities, $countyObj = null)
	{
		if (!empty($destination["CityName"]))
			$destination["CityName"] = trim($destination["CityName"]);

		$saveCity = false;
		if (!($city = $dbCitiesByInTopIds[$destination["CityCode"]]))
		{
			$city = new \Omi\City();
			$city->setName($destination["CityName"]);
			$city->setInTourOperatorId($destination["CityCode"]);
			$city->setTourOperator($this->TourOperatorRecord);
			$city->setFromTopAddedDate(date("Y-m-d H:i:s"));
			$city->setCountry($countryObj);
			if ($countyObj)
				$city->setCounty($countyObj);
			$saveCity = true;
			if ($this->TrackReport)
			{
				if (!$this->TrackReport->NewCity)
					$this->TrackReport->NewCity = 0;
				$this->TrackReport->NewCity++;
			}
			echo "<div style='color: green;'>Add new city [{$city->InTourOperatorId}|{$city->Name}] to system on country [{$countryObj->Code}|{$countryObj->Name}]</div>";
			$city->__added_to_system__ = true;
		}
		else if ($city->Name != $destination["CityName"])
		{
			echo "<div style='color: red;'>Update city name from [{$city->Name}] to [{$destination["CityName"]}]</div>";
			$city->setName($destination["CityName"]);
			$city->setFromTopModifyDate(date("Y-m-d H:i:s"));
			$saveCity = true;
		}
		// we may not move a city from a country to another
		if ($city->Country->Code != $countryObj->Code)
			throw new \Exception("City was moved from country [{$city->Name}] [{$city->Country->Code}] to country [{$countryObj->Code}]");
		// set or reset the county

		if (($city->County ? $city->County->getId() : 0) != ($countyObj ? $countyObj->getId() : 0))
		{
			if ($city->Country && $countyObj->Country && ($city->Country->getId() !== $countyObj->Country->getId()))
				throw new \Exception("Cannot link city  [{$city->Name}]");
			$city->setCounty($countyObj);
			$saveCity = true;
		}

		if ($saveCity)
			$cities[$city->InTourOperatorId] = $city;
		
		return $city;
	}

	public function getBookingRequest($bookingId, $sourceType = "api")
	{
		$params = ["BookingReference" => [$bookingId, "Source" => $sourceType]];
		$data = static::GetResponseData($this->doRequest(["getBookingRequest"], $params, true), "getBookingResponse");

		if (!$data || (count($data) === 0))
			return null;

		$order = new \Omi\Comm\Order();
		$bookingReferences = ($data["BookingReferences"] && $data["BookingReferences"]["BookingReference"]) ? $data["BookingReferences"]["BookingReference"] : null;
		
		$order->InTourOperatorId = ($bookingReferences && $bookingReferences[0] && $bookingReferences[0]["@attributes"] && 
			($bookingReferences[0]["@attributes"]["Source"] === "api")) ? $bookingReferences[0][0] : null;
		
		$bookingItems = ($data["BookingItems"] && $data["BookingItems"]["BookingItem"]) ? $data["BookingItems"]["BookingItem"] : null;

		if (!$bookingItems)
			return;

		if ($bookingItems["User"])
			$bookingItems = [$bookingItems];

		foreach ($bookingItems as $bookingItm)
		{
			$statusCode = ($bookingItm["ItemStatus"] && is_array($bookingItm["ItemStatus"]) && $bookingItm["ItemStatus"]["@attributes"] && 
				$bookingItm["ItemStatus"]["@attributes"]["Code"]) ? $bookingItm["ItemStatus"]["@attributes"]["Code"] : null;

			$order->Voucher = $bookingItm["VchLink"];

			if ($statusCode)
			{
				if ($statusCode === "C")
					$order->setStatus(\Omi\Comm\Order::Confirmed);
				else if ($statusCode === "RJ")
					$order->setStatus(\Omi\Comm\Order::Rejected);
				else if ($statusCode === "CP")
					$order->setStatus(\Omi\Comm\Order::PendingConfirm);
				else if ($statusCode === "XP")
					$order->setStatus(\Omi\Comm\Order::PendingCancel);
				else if ($statusCode === "X")
					$order->setStatus(\Omi\Comm\Order::Cancelled);

				/*
				else if ($statusCode === "ER")
					$order->setStatus(\Omi\Comm\Order::Error);
				*/
			}
		}
		return $order;
	}

	public function getBookingFees($bookingId)
	{
		$params = ["BookingReference" => [$bookingId, "Source" => "api"]];
		return static::GetResponseData($this->doRequest(["getBookingFeesRequest"], $params, true), "getBookingFeesResponse");
	}

	public function cancelBooking($bookingId)
	{
		$params = ["BookingReference" => [$bookingId, "Source" => "api"]];
		return static::GetResponseData($this->doRequest(["CancelBookingRequest"], $params), "CancelBookingResponse");
	}

	/*===================================END SETUP HERE STORAGE METHODS=============================================*/
	/*===================================GET DATABASE CACHED DATA=============================================*/
		
	/*===================================GET GENERAL DATA=============================================*/

	private function getHotelsFacilities($parameters)
	{
		if (static::$_Facilities)
			return static::$_Facilities;

		static::$_Facilities = [];

		if (!$parameters)
			$parameters = [];

		$params_str = $this->TourOperatorRecord->Handle . "_" . implode("|", $parameters);

		$cache_dir = \Omi\App::GetResourcesDir('eurosite_facilities');
		$cache_file = rtrim($cache_dir, "\\/") . "/es_facilities_" . sha1($params_str) . ".php";
		$cf_time = file_exists($cache_file) ? filemtime($cache_file) : null;
		$Es_Facilities_Data = null;

		if ($cf_time && (strtotime("+ 1 day", $cf_time)) > time())
		{
			require_once($cache_file);
		}

		$doReq = ($Es_Facilities_Data === null);

		$data = $doReq ? $Es_Facilities_Data : static::GetResponseData($this->doRequest("getUnitFacilitiesRequest", $parameters, true), "getUnitFacilitiesResponse");

		if (!$doReq)
			file_put_contents($cache_file, qArrayToCode($data, "Es_Facilities_Data"));

		$hotels = ($data && $data["Hotels"] && $data["Hotels"]["Hotel"]) ? $data["Hotels"]["Hotel"] : null;

		if (!$hotels)
			return;

		if ($hotels["HotelCode"])
			$hotels = [$hotels];
		
		$useNewFacilitiesFunctionality = (defined('USE_NEW_FACILITIES') && USE_NEW_FACILITIES);

		if ($useNewFacilitiesFunctionality)
		{
			$facilitiesByName = [];
			foreach ($hotels as $hotel)
			{
				$facilities = ($hotel["Facilities"] && $hotel["Facilities"]["Facility"]) ? $hotel["Facilities"]["Facility"] : null;
				if (!$facilities)
					continue;
				if ($facilities["Name"])
					$facilities = [];

				foreach ($facilities as $facility)
				{
					if ($facility["Name"])
					{
						$facilitiesByName[]	= $facility["Name"];
					}
				}
			}

			$existingFacilities = $this->getExistingHotelsFacilitiesByNames($facilitiesByName);
		}

		$app = \QApp::NewData();
		$app->HotelsFacilities = new \QModelArray();

		static::$_Facilities = [];
		foreach ($hotels as $hotel)
		{
			$facilities = ($hotel["Facilities"] && $hotel["Facilities"]["Facility"]) ? $hotel["Facilities"]["Facility"] : null;
			if (!$facilities)
				continue;

			if ($facilities["Name"])
				$facilities = [];

			if (!static::$_Facilities[$hotel["HotelCode"]])
				static::$_Facilities[$hotel["HotelCode"]] = [];

			foreach ($facilities as $facility)
			{
				if ($useNewFacilitiesFunctionality)
				{
					if ($facility["Name"] && ($facilityIdf = static::GetFacilityIdf($facility["Name"])))
					{
						if (!($facilityObj = $existingFacilities[$facilityIdf]))
						{
							$facilityObj = $app->HotelsFacilities[$facilityIdf] ?: new \Omi\Travel\Merch\HotelFacility();
							$facilityObj->Type = $facility["Type"];
							$facilityObj->Name = $facility["Name"];
							if (!isset($app->HotelsFacilities[$facilityIdf]))
								$app->HotelsFacilities[$facilityIdf] = $facilityObj;			
						}
						static::$_Facilities[$hotel["HotelCode"]][] = $facilityObj;
					}
				}
				else
				{
					$facilityObj = new \Omi\Travel\Merch\HotelFacility();
					$facilityObj->Type = $facility["Type"];
					$facilityObj->Name = $facility["Name"];
					static::$_Facilities[$hotel["HotelCode"]][] = $facilityObj;

				}
			}
		}

		if ($useNewFacilitiesFunctionality && count($app->HotelsFacilities))
			$app->save('HotelsFacilities.*');

		return static::$_Facilities;
	}
	/**
	 * 
	 * @param array $params
	 * @param type $objs
	 * @return type
	 */
	public function getRoomsTypes(&$objs = null)
	{
		$data = static::GetResponseData($this->doRequest("getRoomRequest", null, true), "getRoomResponse");

		$rooms = $data["Room"] ? $data["Room"] : null;

		if (!$rooms || (count($rooms) === 0))
			return [];

		if (!$objs)
			$objs = [];

		$ret = [];
		foreach ($rooms as $room)
		{
			$roomCode = $room["@attributes"] ? reset($room["@attributes"]) : null;
			if (!$roomCode)
				continue;

			$roomPersons = $objs[$roomCode]["\Omi\Travel\Merch\RoomPersons"] ?: ($objs[$roomCode]["\Omi\Travel\Merch\RoomPersons"] = new \Omi\Travel\Merch\RoomPersons());
			$roomPersons->Code = $roomCode;
			$roomPersons->Title = $room[0];
			$ret[] = $roomPersons;
		}
		return $ret;
	}

	/*===================================END GET GENERAL DATA=============================================*/
	
	/*===================================COMMON=============================================*/
	/**
	 * Returns the fee with some type
	 * 
	 * @param array $fee
	 * @param array $objs
	 * @return \Omi\Comm\Offer\Offer
	 */
	private function getFee($fee, $price, $type, $currencyCode, $sellCurrencyCode, $force_percent = false, &$objs = null)
	{
		if (!$objs)
			$objs = [];

		$this->loadCachedMerchCategories($objs);

		$type = $fee["Type"];

		$offer = new \Omi\Comm\Offer\CancelFee();

		// setup 
		if (!($offer->SuppliedCurrency = static::GetCurrencyByCode($currencyCode)))
		{
			throw new \Exception("Undefined currency [{$currencyCode}]!");
		}

		if (!($offer->Corrency = static::GetCurrencyByCode($sellCurrencyCode)))
		{
			throw new \Exception("Undefined currency [{$sellCurrencyCode}]!");
		}

		$offer->DateStart = $fee["FromDate"];
		$offer->DateEnd = $fee["ToDate"];

		$offer->Item = new \Omi\Comm\Offer\OfferItem();
		$offer->setTourOperator($this->TourOperatorRecord);
		$offer->Item->Quantity = 1;

		$percent = $force_percent;
		if (is_array($fee["Value"]))
		{
			if ($fee["Value"]["@attributes"])
			{
				$percent = $fee["Value"]["@attributes"] ? filter_var($fee["Value"]["@attributes"]["Procent"], FILTER_VALIDATE_BOOLEAN) : false;
				//qvardump($percent, $fee["Value"]["@attributes"]["Procent"]);
				unset($fee["Value"]["@attributes"]);
			}
			$fee["Value"] = reset($fee["Value"]);
		}

		// we need to know if we are on charters to apply the correct comission;
		$fee_price = $percent ? ($price * $fee["Value"]/100) : $fee["Value"];

		$offer->Item->setUnitPrice($fee_price);
		$offer->setPrice($fee_price);

		if ($fee["CurrencyCode"])
			$offer->Item->Currency = $objs[$fee["CurrencyCode"]]["\Omi\Comm\Currency"] ?: ($objs[$fee["CurrencyCode"]]["\Omi\Comm\Currency"] = new \Omi\Comm\Currency());

		$offer->Item->Merch = new \Omi\Comm\Merch\Merch();

		if (!$type)
			return $offer;

		$offer->Item->Merch->Title = $type;
		$offer->Item->Merch->Code = $type;
		$offer->Item->Merch->Category = $objs[$type]["\Omi\Comm\Merch\MerchCategory"] ?: ($objs[$type]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
		$offer->Item->Merch->Category->Name = $type;

		$offer->Items = new \QModelArray();
		$offer->Items[] = $offer->Item;

		// on eurosite we don't have currency on items - we have it only on offer - so we need to setup the supplied currency on all items
		foreach ($offer->Items ?: [] as $itm)
			$itm->SuppliedCurrency = $offer->SuppliedCurrency;

		// setup offer currency
		//($currency, $offer, $segment, $setComission = false, $applyDBComission = true, $skipConversion = false)
		$this->setupOfferPriceByCurrencySettings([], $sellCurrencyCode, $offer, $type, false, (!$percent), true);

		return $offer;
	}
	/**
	 * Returns countries with cities where we have charters/tous
	 * 
	 * @param array $countries
	 * @param array $objs
	 * @return array
	 */
	private function getCountriesWithServices($countries, &$objs = null)
	{
		if (!$countries || (count($countries) === 0))
			return [];

		if (!$objs)
			$objs = [];

		$ret = [];
		foreach ($countries as $country)
		{
			if (!$country["CountryCode"])
				continue;

			$countryObj = $this->getCacheCountry($country["CountryCode"]);
			
			if (!$countryObj)
			{
				$countryObj = $objs[$country["CountryCode"]]["\Omi\Country"] ?: ($objs[$country["CountryCode"]]["\Omi\Country"] = new \Omi\Country());
				$countryObj->Code = $country["CountryCode"];
				$countryObj->Name = $country["CountryName"];
			}
			$ret[] = $countryObj;
			$cities = ($country["Cities"] && $country["Cities"]["City"]) ? $country["Cities"]["City"] : null;

			if (!$cities || (count($cities) === 0))
				continue;

			if (!$countryObj->Cities)
				$countryObj->Cities = new \QModelArray();

			if (isset($cities["CityCode"]))
				$cities = array($cities);

			foreach ($cities as $city)
			{
				if (!$city["CityCode"])
					continue;

				$cityObj = $this->GetCacheCity($city["CityCode"]);
				if (!$cityObj)
				{
					$cityObj = $objs[$city["CityCode"]]["\Omi\City"] ?: ($objs[$city["CityCode"]]["\Omi\City"] = new \Omi\City());
					$cityObj->Code = $city["CityCode"];
					$cityObj->Name = $city["CityName"];
				}
				$countryObj->Cities[] = $cityObj;
			}
		}
		return $ret;
	}
	/**
	 * Returns services as an array of \Omi\Comm\Offer\OfferItem
	 * 
	 * @param type $services
	 * @param array $objs
	 * @return array
	 */
	private function getServices($services, &$objs = null)
	{
		if (!$services || (count($services) === 0))
			return [];

		if (!$objs)
			$objs = [];

		if (isset($services['Code']))
			$services = array($services);

		$ret = [];
		foreach ($services as $service)
			$ret[] = $this->getService($service, $objs);
		return $ret;
	}
	/**
	 * 
	 * @param array $service
	 * @param array $objs
	 * @return \Omi\Comm\Offer\OfferItem
	 */
	private function getService($service, &$objs = null)
	{
		if (!$objs)
			$objs = [];

		// load cached data
		$this->loadCachedCurrencies($objs);
		$this->loadCachedMerchCategories($objs);
		$this->loadCachedCompanies($objs);

		$srv = new \Omi\Comm\Offer\OfferItem();
		$srv->Merch = new \Omi\Comm\Merch\Merch();

		if ($service['Provider'])
		{
			$srv->SuppliedBy = $objs[$service['Provider']]["\Omi\Company"] ?: ($objs[$service['Provider']]["\Omi\Company"] = new \Omi\Company());
			$srv->SuppliedBy->Name = $service['Provider'];
		}

		if ($service['Type'])
		{
			$srv->Merch->Category = $objs[$service['Type']]["\Omi\Comm\Merch\MerchCategory"] ?: 
				($objs[$service['Type']]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
			$srv->Merch->Category->Name = $service['Type'];
		}

		$srv->Merch->Title = $service["Name"];
		$srv->Merch->Code = $service["Code"];

		if ($service["CharterId"])
			$srv->Merch->CharterId = $service["CharterId"];

		$currencyCode = ($service["@attributes"] && $service["@attributes"]["CurrencyCode"]) ? $service["@attributes"]["CurrencyCode"] : null;
		if ($currencyCode)
		{
			$srv->Currency = $objs[$currencyCode]["\Omi\Comm\Currency"] ?: ($objs[$currencyCode]["\Omi\Comm\Currency"] = new \Omi\Comm\Currency());
			$srv->Currency->Code = $currencyCode;
		}

		$srv->Quantity = 1;
		if ($service["Price"])
			$srv->UnitPrice = $service["Price"];
		return $srv;
	}

	public function saveRooms()
	{
		$eurositeRooms = $this->getRoomsTypes();
		$dbRooms = \QApi::Query("EurositeRooms");

		$existingRooms = [];
		if ($dbRooms && (count($dbRooms) > 0))
		{
			foreach ($dbRooms as $room)
			{
				if (!$room->Code)
					continue;
				$existingRooms[$room->Code] = $room;
			}
		}

		$toSaveRooms = new \QModelArray();
		$toRemoveRooms = new \QModelArray();

		$processedRooms = [];
		if ($eurositeRooms && (count($eurositeRooms) > 0))
		{
			foreach ($eurositeRooms as $room)
			{
				$processedRooms[$room->Code] = $room;
				if (isset($existingRooms[$room->Code]))
					continue;
				$toSaveRooms[] = $room;
			}
		}

		foreach ($existingRooms as $room)
		{
			if ($processedRooms[$room->Code])
				continue;
			$toRemoveRooms[] = $room;
		}

		if (count($toSaveRooms) > 0)
			\QApi::Save("EurositeRooms", $toSaveRooms, null, "Code, Title");

		if (count($toRemoveRooms) > 0)
			\QApi::Delete("EurositeRooms", $toRemoveRooms, "Id");
	}

	private function getOrderDocuments($id)
	{
		$eurositeOrder = $this->getBookingRequest($id);
		if (!$eurositeOrder || ($eurositeOrder->Status !== \Omi\Comm\Order::Confirmed))
			return [];

		return [
			["Voucher", $eurositeOrder->Voucher]
		];
	}

	public function saveBooking($data)
	{
		$order = qis_array($data) ? reset($data) : $data;		
		if (!$order || (!($order instanceof \Omi\Comm\Order)))
			throw new \Exception("Order not provided!");

		$params = [];
		$params["BookingName"] = $order->BookingReference;
		$params["BookingClientId"] = $order->getBookingClientId();

		if ((!$order->OrderOffers) || (count($order->OrderOffers) === 0))
			throw new \Exception("Order items missing!");

		if (!$order->Currency)
			throw new \Exception("Currency is missing");

		$firstOrderOffer = reset($order->OrderOffers);
		$merch = ($firstOrderOffer && $firstOrderOffer->Offer && $firstOrderOffer->Offer->Item && $firstOrderOffer->Offer->Item->Merch) ? 
			$firstOrderOffer->Offer->Item->Merch : null;

		$tour = ($merch instanceof \Omi\Travel\Merch\Tour) ? $merch : null;
		$hotel = ($merch instanceof \Omi\Travel\Merch\Room) ? $merch->Hotel : null;

		$travelItm = $hotel ? $hotel : $tour;

		if (!$travelItm)
			throw new \Exception("Main travel item not found!");

		$bookingAgent = \QApi::Call("Omi\Setting::GetByKey", "booking_agent_name", "Value");
		$bookingAgency = \QApi::Call("Omi\Setting::GetByKey", "booking_agency", "Value");
		$bookingEmail = \QApi::Call("Omi\Setting::GetByKey", "booking_email", "Value");

		$bookingClient = $bookingAgency;

		/*
		if ($order->BillingTo)
		{
			if ($order->BillingTo->Company)
			{
				$bookingClient = $order->BillingTo->Company->Name.",{$order->BillingTo->Company->TaxIdentificationNo},{$order->BillingTo->Company->RegistrationNo},"
					. "{$order->BillingTo->Company->Bank},{$order->BillingTo->Company->BankAccount}"
					. ($order->BillingTo->Company->HeadOffice ? ",".$order->BillingTo->Company->HeadOffice->Details : "");
			}
			else
			{
				$bookingClient = $order->BillingTo->getFullName().",{$order->BillingTo->UniqueIdentifier},{$order->BillingTo->IdentityCardSeries}/"
					. "{$order->BillingTo->IdentityCardNumber},{$order->BillingTo->Phone},{$order->BillingTo->Email},{$order->BillingTo->Address->Details}";
			}
		}
		*/

		$params["BookingItems"] = [];
		foreach ($order->OrderOffers as $orderOffer)
		{
			if (!$orderOffer->Offer)
				throw new \Exception("Offer not found for order!");
			
			$rsCode = ($orderOffer->Offer && $orderOffer->Offer->TourOperator) ? $orderOffer->Offer->TourOperator->ApiContext : null;

			/*
			$callOffer = ($callOffers && isset($callOffers[$orderOffer->Offer->Code])) ? $callOffers[$orderOffer->Offer->Code] : null;
			if (!$callOffer)
				throw new \Exception("Call offer not found!");
			
			$orderOffer->Offer->PackageId = $callOffer->PackageId;
			$orderOffer->Offer->UniqueId = $callOffer->UniqueId;
			$orderOffer->Offer->DepartureCharter = $callOffer->DepartureCharter;
			$orderOffer->Offer->PackageVariantId = $callOffer->PackageVariantId;
			*/
			
			$roomObj = $orderOffer->getRoomItem();
			if (!$roomObj || !$roomObj->OfferItem || !$roomObj->OfferItem->Merch || !$roomObj->Info)
				throw new \Exception("Room not found for order!");

			$bookingItm = [];
			$bookingItm["ProductType"] = $tour ? "circuit" : "hotel";
			$itm = $tour ? "CircuitItem" : "HotelItem";

			$bookingItm[] = ["ItemClientId" => 1];
			$bookingItm[] = ["TourOpCode" => $rsCode ?: ($hotel ? $hotel->ResellerCode : $tour->ResellerCode)];

			$bookingItm[$itm] = [];
			$bookingItm[$itm][] = ["BookingAgent" => "{$bookingAgent},{$bookingAgency},{$bookingEmail}"];
			$bookingItm[$itm][] = ["BookingClient" => $bookingClient];

			if ($hotel)
			{
				if (!$orderOffer->Offer->PackageVariantId)
					throw new \Exception("Offer PackageVariantId not found!");
				
				//if (!$orderOffer->Offer->PackageId)
				//	throw new \Exception("Offer PackageId not found!");
				
				if (!$hotel->Address)
					throw new \Exception("Address not found for hotel!");
				
				if (!$hotel->Address->City || !$hotel->Address->City->Country)
					throw new \Exception("Hotel destination not found!");

				$bookingItm[$itm][] = ["CountryCode" => $hotel->Address->City->Country->Code];
				$bookingItm[$itm][] = ["CityCode" => $hotel->Address->City->InTourOperatorId];
				$bookingItm[$itm][] = ["ProductCode" => $hotel->InTourOperatorId];
				$bookingItm[$itm][] = ["Language" => "RO"];
				$bookingItm[$itm][] = ["PeriodOfStay" => [
					["CheckIn" => $orderOffer->Offer->Item->CheckinAfter],
					["CheckOut" => $orderOffer->Offer->Item->CheckinBefore]
				]];

				if ($orderOffer->Offer->PackageId)
					$bookingItm[$itm][] = ["PackageId" => $orderOffer->Offer->PackageId];

				$bookingItm[$itm][] = ["VariantId" => $orderOffer->Offer->PackageVariantId];
			}
			else
			{
				if (!$orderOffer->Offer->UniqueId)
					throw new \Exception("Offer UniqueId not found!");

				if (!$orderOffer->Offer->DepartureCharter)
					throw new \Exception("Departure charter not found!");

				$bookingItm[$itm][] = ["CircuitId" => $tour->InTourOperatorId];
				$bookingItm[$itm][] = ["SearchId" => $orderOffer->Offer->UniqueId];
				$bookingItm[$itm][] = ["DepartureCharter" => $orderOffer->Offer->DepartureCharter];
			}

			$cAdults = $roomObj->Info->Adults ? count($roomObj->Info->Adults) : 0;
			$cChildren = $roomObj->Info->Children ? count($roomObj->Info->Children) : 0;

			$hasAdults = ($cAdults > 0);
			$hasChildren = ($cChildren > 0);

			$room = ["Code" => $roomObj->OfferItem->Code];
			if ($hasAdults || $hasChildren)
			{
				if ($hasAdults)
					$room["NoAdults"] = $cAdults;
				if ($hasChildren)
					$room["NoChildren"] = $cChildren;
				$room["PaxNames"] = [];
				if ($hasAdults)
				{
					foreach ($roomObj->Info->Adults as $adult)
					{
						$gender = ($adult->Gender == \Omi\Person::Male) ? "B" : "F";
						$room["PaxNames"][] = ["PaxName" => ["PaxType" => "adult", "TGender" => $gender, "DOB" => $adult->BirthDate, [$adult->Name." / ".$adult->Firstname]]];
					}
				}

				if ($hasChildren)
				{
					foreach ($roomObj->Info->Children as $child)
						$room["PaxNames"][] = ["PaxName" => ["PaxType" => "child", "TGender" => "C", "DOB" => $child->BirthDate, [$child->Name." / ".$child->Firstname]]];
				}
			}

			$bookingItm[$itm][] = ["Rooms" => ["Room" => $room]];

			// add the booking item
			$params["BookingItems"][] = ["BookingItem" => $bookingItm];
		}

		$data = self::GetResponseData($this->doRequest(["AddBookingRequest", "CurrencyCode" => $order->Currency->Code], $params, true), "AddBookingResponse");

		if (!$data)
			throw new \Exception("Rezervarea nu a putut fi procesata!");

		try
		{
			if ($data['BookingItems'] && $data['BookingItems']['BookingItem'] && $data['BookingItems']['BookingItem']['Error'])
			{
				$err = is_array($data['BookingItems']['BookingItem']['Error']['ErrorText']) ? 
					reset($data['BookingItems']['BookingItem']['Error']['ErrorText']) : 
					$data['BookingItems']['BookingItem']['Error']['ErrorText'];
				throw new \Exception($err);
			}
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}

		$bookingReferences = ($data["BookingReferences"] && $data["BookingReferences"]["BookingReference"]) ? $data["BookingReferences"]["BookingReference"] : null;

		// set tour operator
		$order->InTourOperatorId = ($bookingReferences && $bookingReferences[0] && $bookingReferences[0]["@attributes"] && 
			($bookingReferences[0]["@attributes"]["Source"] === "api")) ? $bookingReferences[0][0] : null;
		$order->setApiOrderData(json_encode($data));

		// save data
		$order->save("InTourOperatorId, ApiOrderData");

		// b2b reservation
		$this->getB2bReservation($order);

		$order->_top_response_message = "A fost efecutata comanda cu "
			. ($order->InTourOperatorRef ? "numarul [{$order->InTourOperatorRef}] si cu " : "")
			. "referinta [{$order->InTourOperatorId}].\n"
			. ($order->InTourOperatorStatus ? "Statusul comenzii este [{$order->InTourOperatorStatus}]" : "") . ".";

		return $order;
	}

	public function getB2bReservation($order)
	{
		$order->populate("InTourOperatorId, BookingReference");

		$data = static::GetResponseData($this->doRequest(["getBookingRequest"], 
			["BookingReference" => [$order->getBookingClientId(), "Source" => "client"]], true), "getBookingResponse");

		$bookingItm = $data["BookingItems"]["BookingItem"];

		$order->setInTourOperatorRef($bookingItm["Confirmation"]);
		$order->setInTourOperatorStatus($bookingItm["ItemStatus"][0]);

		$order->save("InTourOperatorRef, InTourOperatorStatus");
	}

	public function saveOrdersUpdates()
	{
		$orders = QQuery("Orders.{Status, InTourOperatorId, BookingReference WHERE TourOperator.Id=?}", $this->TourOperatorRecord->getId());

		if (!$orders || (count($orders) === 0))
			return;

		foreach ($orders as $order)
		{
			if (!$order->InTourOperatorId)
				continue;

			$eurositeOrder = null;
			try 
			{
				$eurositeOrder = $this->getBookingRequest($order->getBookingClientId());
			}
			catch (\Exception $ex)
			{
				
			}

			if (!$eurositeOrder || ($eurositeOrder->Status === $order->Status))
				continue;

			$order->setStatus($eurositeOrder->Status);
			$order->save("Status");
		}
	}

	//======================================================CRONS REQUESTS====================================================================
	/**
	 * @api.enable
	 * 
	 * @param array $request
	 * @param string $handle
	 * @param array $params
	 * @throws \Exception
	 */
	public static function SetupHotels($request, $handle, $params = [])
	{
		try
		{
			$storage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
			if (!$storage)
				throw new \Exception("There was a problem");
			$storage->saveHotels($params);

		}
		catch (\Exception $ex)
		{
			$request->setFailed(true);
			$request->setLog($ex->getMessage()." % ". $ex->getFile() . "%" . $ex->getLine() . " % " . $ex->getTraceAsString());
		}

		$request->setExecuted(true);
		$request->setExecStarted(false);
		$request->setDate(date("Y-m-d H:i:s"));

		// save the request
		$request->save("Executed, ExecStarted, Date, Failed, Log");

		if ($request->_req_file)
			unlink($request->_req_file);
	}
	/**
	 * @api.enable
	 * 
	 * @param array $request
	 * @param string $handle
	 * @param array $params
	 * @throws \Exception
	 */
	public static function SetupTours($request, $handle, $params = [])
	{
		try
		{
			$storage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
			if (!$storage)
				throw new \Exception("There was a problem");
			$storage->saveTours($params);
		}
		catch (\Exception $ex)
		{
			$request->setFailed(true);
			$request->setLog($ex->getMessage()." % ". $ex->getFile() . "%" . $ex->getLine() . " % " . $ex->getTraceAsString());
		}

		$request->setExecuted(true);
		$request->setExecStarted(false);
		$request->setDate(date("Y-m-d H:i:s"));

		// save the request
		$request->save("Executed, ExecStarted, Date, Failed, Log");

		if ($request->_req_file)
			unlink($request->_req_file);
	}
	//======================================================END CRONS REQUESTS====================================================================

	/**
	 * Returns response (a bit formated and checked)
	 * 
	 * @param array $response
	 * 
	 * @return array
	 * 
	 * @throws \Exception
	 */
	public static function GetResponseData($response, $responseMethod)
	{
		// exit if no response
		if (!$response)
			return null;
		
		// wtf is this? (already exited above)
		if (!$response)
			throw new \Exception("Response failed!");
		
		// and another (might wanna put them all in an if)
		if (is_string($response))
			throw new \Exception($response);

		$success = $response['success'];
		$data = $response['data'];

		if (!$success || (!$data))
			return null;
		
		$resp = ($data["ResponseDetails"] && $data["ResponseDetails"][$responseMethod]) ? $data["ResponseDetails"][$responseMethod] : null;
		if ($resp && $resp["Error"])
			throw new \Exception("ERROR! (".$resp["Error"]["ErrorId"].")".$resp["Error"]["ErrorText"]);
		
		return $resp;
	}

	public static function GetRequests($child_storage, $from, $parameters)
	{		
		if (($dynamicPackages = $parameters["dynamic_packages"]))
			return null;

		$isCharter = ($parameters["VacationType"] === "charter");
		$isTour = ($parameters["VacationType"] === "tour");
		$isIndividual = (($parameters["VacationType"] === "individual") && ($parameters["search_type"] !== "hotel"));
		$isHotel = (($parameters["VacationType"] === "hotel") || ($parameters["search_type"] === "hotel"));
		
		$SEND_TO_SYSTEM = $parameters["__send_to_system__"];

		//echo "<div style='color: blue;'>{$child_storage->TourOperatorRecord->Handle}</div>";
		
		$forceIndivudalInSamePlace = (\Omi\App::HasIndividualInOnePlace() || $parameters["category"] || $parameters["custDestination"] || 
			$parameters["mainDestination"] || $parameters["dynamic_packages"] || $parameters["for_dynamic_packages"]);

		if ($isHotel && (!$isIndividual) && (!$forceIndivudalInSamePlace))
			return;

		$tourOperator = $child_storage->TourOperatorRecord;

		$params = static::GetRequestsDefParams($parameters);
		
		$travelItms = null;
		// if we have product code
		if ($parameters["ProductCode"])
		{
			// if we don't have the product in current tour operator - we don't have requests
			if ((!($travelItms = static::GetTravelItems($parameters["ProductCode"], $tourOperator, $parameters["VacationType"]))) || (count($travelItms) === 0))
				return null;
		}
		else if ($SEND_TO_SYSTEM)
			throw new \Exception("Cannot send to system if we don't know the hotel/tour!");
		

		$checkIn = date("Y-m-d", strtotime($parameters["CheckIn"]));
		$checkOut = $parameters["CheckOut"] ? date("Y-m-d", strtotime($parameters["CheckOut"])) : null;
		$_isCounty = ($parameters && ($parameters["DestinationType"] == "county"));

		if (!$isTour && (!($destinationID = ($parameters["CityCode"] ?: $parameters["Destination"]))))
		{
			//throw new \Exception("Destination cannot be determined!");
		}

		if ((!$destinationID) || (!($destination = ($_isCounty ? 
			(isset(\Omi\TFuse\Api\Travelfuse::$_CacheData['requests_county'][$destinationID]) ? 
				\Omi\TFuse\Api\Travelfuse::$_CacheData['requests_county'][$destinationID] : 
				(\Omi\TFuse\Api\Travelfuse::$_CacheData['requests_county'][$destinationID] = QQuery("Counties.{*, Master WHERE Id=?}", $destinationID)->Counties[0])) :
			(isset(\Omi\TFuse\Api\Travelfuse::$_CacheData['requests_city'][$destinationID]) ? 
				\Omi\TFuse\Api\Travelfuse::$_CacheData['requests_city'][$destinationID] : 
				(\Omi\TFuse\Api\Travelfuse::$_CacheData['requests_city'][$destinationID] = QQuery("Cities.{*, Master WHERE Id=?}", $destinationID)->Cities[0]))))))
		{
			if (!$isTour)
				return;
		}

		//if ($tourOperator->Handle === "bibi_touring")
		//	qvardump($parameters);

		$params["PeriodOfStay"] = [
			["CheckIn" => $checkIn],
			["CheckOut" => $checkOut]
		];

		// setup transport
		if ($parameters["Transport"])
			$params["Transport"] = $parameters["Transport"];
		
		#$params["CurrencyCode"] = $child_storage->getRequestCurrency($parameters);
		#$params["SellCurrency"] = $child_storage->getSellCurrency($parameters);

		/*
		EXCEPTIONS : 
		=======

		1. Master city is changed for tour operator city
		- Kusadasi from paralela is linked to Antalya master city
		- In interface we will see only Antalya
		- When search - do separate search for city kusadasi (only city) and append results

		covered by search - it only needs filtering


		2. Master county is changed for tour operator county
		- Antalya master county is set as master for Bodrum tour operator county
		- In interface we will see only Antalya
		- When search - do separate search for county Bodrum and append results


		3. Master county is changed for master city
		- Kusadasi B master county set up for Kusadasi master city (ex in Bodrum master county)
		- In interface we will see only Kusadasi
		- When search - do separate search for kusadasi city (only city) and append results
		*/

		$reqs = [];

		$filterRequestsForTravelItems = static::QFilterRequestsForTravelItems($tourOperator, $parameters);
	
		// SETUP INDIVIDUAL REQUESTS
		if ($isIndividual || $isHotel)
		{
			$_qparams = [
				"Active" => true,
				($_isCounty ? ($destination->IsMaster ? "MasterCounty" : "County") : ($destination->IsMaster ? "MasterCity" : "City")) => $destinationID,
				"TourOperator" => $tourOperator->getId(),
			];

			$from = (($forceIndivudalInSamePlace || $parameters["for_dynamic_packages"]) ? "AllIndividualTransports" : "IndividualTransports");

			$_cachedT = QQuery($from . ".{"
				. "TourOperator.Caption,"
				. "To.{"
					. "City.{"
						. "County.{Name, Master.Name},"
						. "InTourOperatorId, "
						. "TourOperator.Caption, "
						. "IsMaster, "
						. "Master.{"
							. "Name,"
							. "County.Name"
						. "},"
						. "Country.{*, "
							. "InTourOperatorsIds.{"
								. "TourOperator.*, "
								. "Identifier"
							. "}"
						. "}"
					. "}"
				. "}"
				. " WHERE 1 "
					. "??Active?<AND[Content.Active=?]"
					. "??TourOperator?<AND[TourOperator.Id=?]"
					. "??MasterCounty?<AND[To.{City.{(County.Master.Id=? OR Master.County.Id=?)}}]"
					. "??County?<AND[To.City.County.Id=?]"

					. "??City?<AND[To.City.Id=?]"
					. "??MasterCity?<AND[To.City.Master.Id=?]"
				. " GROUP BY Id "
			. "}", $_qparams)->{$from};

			if ($_cachedT)
			{
				foreach ($_cachedT as $_ct)
				{
					if ((!($city = $_ct->To ? $_ct->To->City : null)) || (!$city->InTourOperatorId))
						continue;

					// if we have county we must filter results
					if ($_isCounty)
					{
						$passed = false;
						if (
							(
								!$destination->IsMaster && 
								(
									(
										!$city->Master || 
										($city->Master->Conunty && $destination->Master->County && ($city->Master->Conunty->getId() === $destination->Master->County->getId()))
									)
								)
							) || 
							(
								$destination->IsMaster && 
								(
									(!$city->Master && $city->County && $city->County->Master && ($city->County->Master->getId() === $destination->getId())) || 
									($city->Master && $city->Master->County && $city->Master->County && ($city->Master->County->getId() === $destination->getId()))
								)
							)
						)
							$passed = true;

						if (!$passed)
							continue;
					}

					$requestPassedTravelItemsFilter = (static::QRequestPassedTravelItemsFilter($filterRequestsForTravelItems, $travelItms, $city) || 
						($city->County && static::QRequestPassedTravelItemsFilter($filterRequestsForTravelItems, $travelItms, $city->County)));
					if (!$requestPassedTravelItemsFilter)
						continue;

					$__params = $params;
					$__params['Transport'] = 'car';
					$__params["CityCode"] = $city->InTourOperatorId;
					if ($city->Country && ($identif = $city->Country->getTourOperatorIdentifier($tourOperator)))
						$__params["CountryCode"] =  $identif;
					ksort($__params);
					$indx = json_encode($__params);
					$__params["__CACHED_TRANSPORTS__"] = [$_ct->getId() => $_ct->getId()];

					$reqs[$indx] = $__params;
					// save destination
					//$reqs[$indx]["__CACHED_TRANSPORTS_DESTINATIONS__"][$city->InTourOperatorId] = $city->InTourOperatorId;
				}
			}
		}

		// SETUP TOURS REQUESTS
		else if ($isTour)
		{
			// if we don't have departure we don't have requests
			$departures = static::GetTourOperatorDepartures($parameters["DepCityCode"], $tourOperator);

			if ($checkIn)
			{
				$params["Year"] = date("Y", ($t = strtotime($checkIn)));
				$params["Month"] = date("m", $t);
			}

			if (!$params["Year"])
				$params["Year"] = date("Y");
			if (!$params["Month"])
				$params["Month"] = "13";
			
			$_isDestination = ($parameters["DestinationType"] && ($parameters["DestinationType"] == "destination"));

			$_qparams = [
				"Active" => true,
				($_isDestination ? "Destination" : "Country") => $parameters["TourCountryCode"],
				"TourOperator" => $tourOperator->getId(),
				"TransportType" => $parameters["Transport"],
				"From" => $parameters["DepCityCode"],
				"MonthAndYear" => date("Y-n", strtotime($checkIn)),
				"Active" => true
			];

			$_cachedT = QQuery("ToursTransports.{"
				. "TourOperator.Caption,"
				. "To.{"
					. "City.{"
						. "InTourOperatorId, "
						. "TourOperator.Caption, "
						. "IsMaster, "
						. "Country.{"
							. "Code, "
							. "Name, "
							. "Alias, "
							. "InTourOperatorsIds.{"
								. "TourOperator, "
								. "Identifier"
							. "}"
						. "}"
					. "}"
				. "},"
				. "Dates.{"
					. "Date, "
					. "Nights.{"
						. "Nights "
						. "WHERE 1 "
							. "??Active?<AND[Active=?]"
					. "} "
					. "WHERE 1 "
						. "??Active?<AND[Active=?]"
						. "??MonthAndYear?<AND[CONCAT(YEAR(Date), '-', MONTH(Date))=?]"
				. "}"
				. " WHERE 1 "
					//. "??Active?<AND[Content.Active=?]"
					. "??Active?<AND[Dates.Nights.Active=?]"
					. "??TourOperator?<AND[TourOperator.Id=?]"
					. "??Country?<AND[To.City.Country.Id=?]"
					. "??Destination?<AND[0]"
					. "??From?<AND[From.City.{(Id=? OR Master.Id=?)}]"
					. "??TransportType?<AND[TransportType=?]"
					. "??MonthAndYear?<AND[Dates.{CONCAT(YEAR(Date), '-', MONTH(Date))=? AND Nights.Active=1}]"
				. " GROUP BY Id "
			. "}", $_qparams)->ToursTransports;

			if ($_cachedT)
			{
				foreach ($departures ?: [] as $departure)
				{
					$__params = $params;

					// setup departure in params
					$__params["DepCityCode"] = $departure->InTourOperatorId;
					if (($departure->Country && ($identif = $departure->Country->getTourOperatorIdentifier($tourOperator))))
						$__params["DepCountryCode"] = $identif;

					$productDestinations = [];

					if ($filterRequestsForTravelItems)
					{
						foreach ($travelItms ?: [] as $tourOpProduct)
						{
							if ($tourOpProduct->Location && $tourOpProduct->Location->City)
								$productDestinations[$tourOpProduct->Location->City->getId()] = $tourOpProduct->Location->City->getId();
							foreach ($tourOpProduct->Destinations ?: [] as $dest)
								$productDestinations[$dest->getId()] = $dest->getId();
						}
					}

					foreach ($_cachedT as $_ct)
					{
						if ((!$_ct->To) || (!$_ct->To->City) || (!$_ct->To->City->InTourOperatorId) || 
							(!empty($productDestinations) && (!isset($productDestinations[$_ct->To->City->getId()]))))
							continue;

						$__params["CityCode"] = $_ct->To->City->InTourOperatorId;
						if ($_ct->To->City->Country && ($identif = $_ct->To->City->Country->getTourOperatorIdentifier($tourOperator)))
							$__params["CountryCode"] =  $identif;

						$indx_params = $__params;
						$indx_params["TourOpCode"] = ($tourOperator->ApiContext__ ?: $tourOperator->ApiContext);
						#unset($indx_params["DepCityCode"]);
						unset($indx_params["DepCountryCode"]);
						unset($indx_params["PeriodOfStay"]);
						#unset($indx_params["Transport"]);
						ksort($indx_params);
						$indx = json_encode($indx_params);
						$__params["__CACHED_TRANSPORTS__"] = [$_ct->getId() => $_ct->getId()];

						$dateObj = $_ct->Dates ? reset($_ct->Dates) : null;
						$__params["__CACHED_TRANSPORTS_NIGHTS__"] = [];

						foreach (($dateObj && $dateObj->Nights) ? $dateObj->Nights : [] as $nightsObj)
							$__params["__CACHED_TRANSPORTS_NIGHTS__"][$nightsObj->Nights] = $nightsObj->Nights;

						$reqs[$indx] = $__params;

						// save destination
						//$reqs[$indx]["__CACHED_TRANSPORTS_DESTINATIONS__"][$_ct->To->City->InTourOperatorId] = $_ct->To->City->InTourOperatorId;

						if (!static::$RequestsData[$indx])
							static::$RequestsData[$indx] = [];
						static::$RequestsData[$indx]["Departure"] = $departure;
						static::$RequestsData[$indx]["Destination"] = $_ct->To->City;
					}
				}
			}
		}
		// SETUP CHARTERS REQUESTS
		else if ($isCharter)
		{
			$interval = date_diff(date_create($checkOut), date_create($checkIn));
			$_qparams = [
				"Active" => true,
				//($_isCounty ? "County" : "City") => $destinationID,
				($_isCounty ? ($destination->IsMaster ? "MasterCounty" : "County") : ($destination->IsMaster ? "MasterCity" : "City")) => $destinationID,
				"From" => $parameters["DepCityCode"],
				"TourOperator" => $tourOperator->getId(),
				"TransportType" => $parameters["Transport"],
				"DateAndDuration" => [$checkIn . " 00-00-00", (int)$interval->format('%a')],
			];

			$_cachedT = QQuery("ChartersTransports.{"
				. "TourOperator.Caption,"
				. "To.{"
					. "City.{"
						. "County.{"
							. "Code, "
							. "Name, "
							. "Alias, "
							. "InTourOperatorId, "
							. "TourOperator, "
							. "Master.Name"
						. "},"
						. "InTourOperatorId, "
						. "TourOperator.Caption, "
						. "IsMaster, "
						. "Master.{"
							. "Name,"
							. "County.Name"
						. "},"
						. "Country.{"
							. "Code, "
							. "Name, "
							. "Alias, "
							. "InTourOperatorsIds.{"
								. "TourOperator, "
								. "Identifier"
							. "}"
						. "}"
					. "}"
				. "}"
				. " WHERE 1 "
					//. "??Active?<AND[Content.Active=?]"
					. "??Active?<AND[Dates.Nights.Active=?]"
					. "??TourOperator?<AND[TourOperator.Id=?]"
						//. "??County?<AND[To.{City.{(County.Id=? OR County.Master.Id=? OR Master.County.Id=?)}}]"
						//. "??City?<AND[To.{City.{(Id=? OR Master.Id=?)}}]"
						
						. "??MasterCounty?<AND[To.{City.{(County.Master.Id=? OR Master.County.Id=?)}}]"
						. "??County?<AND[To.City.County.Id=?]"

						. "??City?<AND[To.City.Id=?]"
						. "??MasterCity?<AND[To.City.Master.Id=?]"
						
					. "??From?<AND[From.City.{(Id=? OR Master.Id=?)}]"
					. "??TransportType?<AND[TransportType=?]"
					. "??DateAndDuration?<AND[Dates.{Date=? AND Nights.{Nights=? AND Active=1}}]"
				. " GROUP BY Id "
			. "}", $_qparams)->ChartersTransports;
			
			if ($_cachedT)
			{
				$departures = static::GetTourOperatorDepartures($parameters["DepCityCode"], $tourOperator);	
				foreach ($departures ?: [] as $departure)
				{
					$esExtraCFG = (defined("EUROSITE_EXTRA_CFG") && EUROSITE_EXTRA_CFG) ? EUROSITE_EXTRA_CFG : null;
					$forceReqOnCitiesForCounty = ($_isCounty && $esExtraCFG && $esExtraCFG[$tourOperator->Handle]["force_reqs_on_cities"][$destinationID]);
					$_USE_ZONES = ($_isCounty && !isset(static::$Config[$tourOperator->Handle]['reqs_on_city']) && !$forceReqOnCitiesForCounty);

					$__toSetupReqs = [];

					foreach ($_cachedT as $_ct)
					{
						$__params = $params;
						// setup departure in params
						$__params["DepCityCode"] = $departure->InTourOperatorId;
						if (($departure->Country && ($identif = $departure->Country->getTourOperatorIdentifier($tourOperator))))
							$__params["DepCountryCode"] = $identif;

						$__params["Days"] = 0;

						$city = $_ct->To ? $_ct->To->City : null;

						// we must have city for request
						if (!$city)
							continue;
						

						// if we have county we must filter results
						if ($_isCounty)
						{
							$passed = false;
							if (
								(
									!$destination->IsMaster && 
									(
										(
											!$city->Master || 
											($city->Master->Conunty && $destination->Master->County && ($city->Master->Conunty->getId() === $destination->Master->County->getId()))
										)
									)
								) || 
								(
									$destination->IsMaster && 
									(
										(!$city->Master && $city->County && $city->County->Master && ($city->County->Master->getId() === $destination->getId())) || 
										($city->Master && $city->Master->County && $city->Master->County && ($city->Master->County->getId() === $destination->getId()))
									)
								)
							)
								$passed = true;

							if (!$passed)
								continue;
						}
						

						// we may be in exception case 1 or 3 where, 
						//	we may have a city dettached to its county and we need to do single request on only the city
						// use zone for request if:
						// - we globally use zones
						// - we have county
						// - we don't have city master or we don't have city master county or we don't have city county master or if they are different

						//var_dump("\$city->County->InTourOperatorId", $city->County->InTourOperatorId, $tourOperator->Handle);
						//echo "<br/>";

						$forceReqOnCities_Explicit = ($city->County && $city->County->InTourOperatorId && 
							isset(static::$Config[$tourOperator->Handle]["force_reqs_on_cities"][$city->County->InTourOperatorId]));

						$_USE_ZONE_FOR_REUQEST = (!$forceReqOnCities_Explicit && $_USE_ZONES && $city->County && 
							(!$city->Master || !$city->Master->County || !$city->County->Master ||
								($city->Master->County->getId() == $city->County->Master->getId()) || 
								($city->Master->County->getId() != $destinationID)
							));

						$to = ($_USE_ZONE_FOR_REUQEST && $city->County) ? $city->County : $city;

						if (!$to->InTourOperatorId)
							continue;

						$requestPassedTravelItemsFilter = static::QRequestPassedTravelItemsFilter($filterRequestsForTravelItems, $travelItms, $to);
						if (!$requestPassedTravelItemsFilter)
							continue;

						$_req_cache_indx = get_class($to)."~".$to->InTourOperatorId;

						$req_uniq_idf = null;
						if (!($_creq_indx = $__toSetupReqs[$_req_cache_indx]))
						{
							// copy params that we have so far

							$__params[($to instanceof \Omi\County) ? "Zone" : "CityCode"] = $to->InTourOperatorId;
							if ($city->Country && ($identif = $city->Country->getTourOperatorIdentifier($tourOperator)))
								$__params["CountryCode"] =  $identif;
							ksort($__params);
							$req_uniq_idf = $indx = json_encode($__params);

							$__params["__CACHED_TRANSPORTS__"] = [$_ct->getId() => $_ct->getId()];
							$reqs[$indx] = $__params;
							$__toSetupReqs[$_req_cache_indx] = $indx;
							$_creq_indx = $indx;
						}
						else
						{
							if (!$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"])
								$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"] = [];
							$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"][$_ct->getId()] = $_ct->getId(); 
							$req_uniq_idf = $_creq_indx;
						}

						// save destination
						//$reqs[$_creq_indx]["__CACHED_TRANSPORTS_DESTINATIONS__"][$city->InTourOperatorId] = $city->InTourOperatorId;

						if (!static::$RequestsData[$req_uniq_idf])
							static::$RequestsData[$req_uniq_idf] = [];

						static::$RequestsData[$req_uniq_idf]["Departure"] = $departure;
						static::$RequestsData[$req_uniq_idf]["Destination"] = $to;
					}
				}
			}
		}
		
		// setup search list indx on request
		foreach ($reqs ?: [] as $reqIndx => $req)
		{
			#$reqs[$reqIndx]["searchIndx"] = md5($reqIndx);
			#$reqs[$reqIndx]["searchIndxData"] = $reqIndx;
			#$reqs[$reqIndx]["RequestCurrency"] = $child_storage->getRequestCurrency($parameters);
			$reqCurrency = $child_storage->getRequestCurrency($parameters);
			if (!$reqCurrency)
				$reqCurrency = static::$DefaultCurrency;
			$reqs[$reqIndx]["CurrencyCode"] = $reqCurrency;
			$reqs[$reqIndx]["RequestCurrency"] = $reqCurrency;
			$reqs[$reqIndx]["SellCurrency"] = $child_storage->getSellCurrency($parameters);
		}

		// we need to filter the requests to only the ones where the product code applies - based on the destination
		// if we leave them then it may do requests where we will have no results
		if ($travelItms && (count($travelItms) > 0))
		{
			$allReqs = [];
			foreach ($travelItms ?: [] as $travelItm)
			{
				if (!$travelItm->InTourOperatorId)
					continue;

				foreach ($reqs ?: [] as $indx => $req)
				{
					$req["ProductCode"] = $travelItm->InTourOperatorId;
					$req["ProductName"] = $travelItm->Name ?: $travelItm->Title;
					$req["ProductType"] = $travelItm->getType();
					$newIndx = $indx . "__" . $travelItm->InTourOperatorId;
					if ((($listIndxParams = json_decode($indx, true)) !== null) && is_array($listIndxParams))
					{
						unset($listIndxParams['ProductCode']);
						unset($listIndxParams['ProductName']);
						$req['__list_index__'] = json_encode($listIndxParams);
					}
					static::$RequestsData[$newIndx] = static::$RequestsData[$indx];
					$allReqs[$newIndx] = $req;
				}
			}
			$reqs = $allReqs;
		}

		return $reqs;
	}

	/*=================================================================EXEC RAW REQUESTS====================================================*/
	/*
	 *  THIS SECTION NEEDS TO BE IMPLEMENTED IN EACH TOUR OPERATOR
	 */
	/*======================================================================================================================================*/

	public static function ExecCharterRawRequest($storageHandle, $departureCountryCode, $departureCityCode, 
		$countryCode, $zoneCode, $cityCode, $checkIn, $duration, $transportType = "plane", $productCode = null, $rooms = [], $currencyCode = "EUR")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword) || (!$storage->ApiContext))
			throw new \Exception("Storage not configured [{$storageHandle}]!");

		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;

		if (empty($rooms))
		{
			$rooms = [
				[
				"Adults" => 2,
				"Children" => 0,
				"ChildrenAges" => []
				]
			];
		}

		$checkOut = date("Y-m-d", strtotime("+ {$duration} days", strtotime($checkIn)));

		$roomsStr = static::GetRequestRooms($rooms);

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="getPackageNVPriceRequest">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'. htmlspecialchars($user) . '</RequestUser>
					<RequestPass>' . htmlspecialchars($pass) . '</RequestPass>
					<RequestTime>' . date(DATE_ATOM) . '</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<getPackageNVPriceRequest>
						<CountryCode>' . $countryCode . '</CountryCode>
						' . (
							$zoneCode ? 
								'<Zone>' . $zoneCode . '</Zone>' : 
								'<CityCode>' . $cityCode . '</CityCode>'
						) . '
						<DepCountryCode>' . $departureCountryCode . '</DepCountryCode>
						<DepCityCode>' . $departureCityCode . '</DepCityCode>
						<Transport>' . $transportType . '</Transport>
						<TourOpCode>' . $storage->ApiContext . '</TourOpCode>'
						. ($productCode ? '<ProductCode>' . $productCode . '</ProductCode>' : '') .
						'<CurrencyCode>' . $currencyCode . '</CurrencyCode>
						<PeriodOfStay>
							<CheckIn>' . $checkIn . '</CheckIn>
							<CheckOut>' . $checkOut . '</CheckOut>
						</PeriodOfStay>
						<Days>0</Days>
						<Rooms>
							' . $roomsStr . '
						</Rooms>
					</getPackageNVPriceRequest>
				</RequestDetails>
			</Request>';
		return [$xmlRequest, static::ExecRawRequest($url, $xmlRequest, "getPackageNVPriceRequest")];
	}

	public static function ExecHotelRawRequest($storageHandle, $countryCode, $cityCode, $productCode)
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword) || (!$storage->ApiContext))
			throw new \Exception("Storage not configured [{$storageHandle}]!");

		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="getProductInfoRequest">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'. htmlspecialchars($user) . '</RequestUser>
					<RequestPass>' . htmlspecialchars($pass) . '</RequestPass>
					<RequestTime>' . date(DATE_ATOM) . '</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<getProductInfoRequest>
						
						<CountryCode>' . $countryCode . '</CountryCode>
						<CityCode>' . $cityCode . '</CityCode>
						<TourOpCode>' . $storage->ApiContext . '</TourOpCode>
						<ProductCode>' . $productCode . '</ProductCode>
						<ProductType>hotel</ProductType>
					</getProductInfoRequest>
				</RequestDetails>
			</Request>';

		return static::ExecRawRequest($url, $xmlRequest, "getProductInfoRequest");
	}

	public static function ExecTourRawRequest($storageHandle, $departureCountryCode, $departureCityCode, 
		$countryCode, $zoneCode, $cityCode, $checkIn = null, $duration = null, $transportType = null, $rooms = [], $currencyCode = "EUR")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword) || (!$storage->ApiContext))
			throw new \Exception("Storage not configured [{$storageHandle}]!");

		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;

		if (empty($rooms))
		{
			$rooms = [
				[
				"Adults" => 2,
				"Children" => 0,
				"ChildrenAges" => []
				]
			];
		}

		$month = $checkIn ? date("m", strtotime($checkIn)) : null;
		$year = $checkIn ? date("Y", strtotime($checkIn)) : null;

		$roomsStr = static::GetRequestRooms($rooms);

		$transportsTypesAliases = [
			"plane" => "Avion",
			"bus" => "Autocar"
		];

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="CircuitSearchRequest">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'. htmlspecialchars($user) . '</RequestUser>
					<RequestPass>' . htmlspecialchars($pass) . '</RequestPass>
					<RequestTime>' . date(DATE_ATOM) . '</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<CircuitSearchRequest>
						' . ($countryCode ? '<CountryCode>' . $countryCode . '</CountryCode>' : '') . '
						' . ($cityCode ? '<CityCode>' . $cityCode . '</CityCode>' : '') . '
						<CurrencyCode>' . $currencyCode . '</CurrencyCode>'.
						($transportType ?  '<Transport>' . $transportsTypesAliases[$transportType] . '</Transport>' : '').
						'<Year>' . ($year ?: date("Y")) . '</Year>
						<Month>' . ($month ?: '13') . '</Month>
						<Rooms>
							' . $roomsStr . '
						</Rooms>
					</CircuitSearchRequest>
				</RequestDetails>
			</Request>';

		return static::ExecRawRequest($url, $xmlRequest, "CircuitSearchRequest");
	}

	public static function ExecIndividualRawRequest($storageHandle, $countryCode, $zoneCode, $cityCode, $checkIn, $checkOut, 
		$productCode = null, $rooms = [], $currencyCode = "EUR")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword) || (!$storage->ApiContext))
			throw new \Exception("Storage not configured [{$storageHandle}]!");

		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;

		if (empty($rooms))
		{
			$rooms = [
				[
				"Adults" => 2,
				"Children" => 0,
				"ChildrenAges" => []
				]
			];
		}

		$roomsStr = static::GetRequestRooms($rooms);

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="getHotelPriceRequest">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'. htmlspecialchars($user) . '</RequestUser>
					<RequestPass>' . ($pass) . '</RequestPass>
					<RequestTime>' . date(DATE_ATOM) . '</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<getHotelPriceRequest>					
						<CountryCode>' . $countryCode . '</CountryCode>
						<CityCode>' . $cityCode . '</CityCode>
						<TourOpCode>' . $storage->ApiContext . '</TourOpCode>'
						. ($productCode ? '<ProductCode>' . $productCode . '</ProductCode>' : '')
						. '
						<CurrencyCode>' . $currencyCode . '</CurrencyCode>
						<PeriodOfStay>
							<CheckIn>' . $checkIn . '</CheckIn>
							<CheckOut>' . $checkOut . '</CheckOut>
						</PeriodOfStay>
						<Rooms>
							' . $roomsStr . '
						</Rooms>
					</getHotelPriceRequest>
				</RequestDetails>
			</Request>';

		$ret = static::ExecRawRequest($url, $xmlRequest, "getHotelPriceRequest");
		
		return [$ret, $xmlRequest];
	}

	public static function GetRequestRooms($rooms)
	{
		$roomsStr = "";
		foreach ($rooms ?: [] as $room)
		{
			$roomsStr .= "<Room Code=\"DB\" NoAdults=\"{$room['Adults']}\" NoChildren=\"{$room['Children']}\"";
			if (($hasChildren = ($room['Children'] > 0)))
				$roomsStr .= ">";

			for ($i = 0; $i < $room['Children']; $i++)
			{
				$roomsStr .= "<Children>
					<Age>{$room['ChildrenAges'][$i]}</Age>
				  </Children>";
			}

			$roomsStr .= $hasChildren ? " </Room>" : " />";
		}
		return $roomsStr;
	}

	public static function ExecChartersRoutesRequest($storageHandle, $transportType = "plane")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword) || (!$storage->ApiContext))
			throw new \Exception("Storage not configured [{$storageHandle}]!");

		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="getPackageNVRoutesRequest">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'. htmlspecialchars($user) . '</RequestUser>
					<RequestPass>' . htmlspecialchars($pass) . '</RequestPass>
					<RequestTime>' . date(DATE_ATOM) . '</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<getPackageNVRoutesRequest>
						<Transport>' . $transportType . '</Transport>
					</getPackageNVRoutesRequest>
				</RequestDetails>
			</Request>';
		return static::ExecRawRequest($url, $xmlRequest, "getPackageNVRoutesRequest");
	}

	public static function ExecToursRoutesRequest($storageHandle)
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword) || (!$storage->ApiContext))
			throw new \Exception("Storage not configured [{$storageHandle}]!");
			
		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="CircuitSearchCityRequest">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'. htmlspecialchars($user) . '</RequestUser>
					<RequestPass>' . htmlspecialchars($pass) . '</RequestPass>
					<RequestTime>' . date(DATE_ATOM) . '</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<CircuitSearchCityRequest/>
				</RequestDetails>
			</Request>';

		return static::ExecRawRequest($url, $xmlRequest, "CircuitSearchCityRequest");
	}

	public static function GetRawRequestHeaders()
	{
		return [
			"Content-type: text/xml;charset=\"utf-8\"",
			"Accept: text/xml",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"SOAPAction: \"run\""
		];
	}

	public static function ExecRawRequest($url, $xmlRequest, $req_method = null)
	{
		$curl_handle = q_curl_init_with_log();
		q_curl_setopt_with_log($curl_handle, CURLOPT_URL, $url);
		q_curl_setopt_with_log($curl_handle, CURLOPT_POST, 1);

		$euapi = new \Omi\TF\EuroSiteApi();

		// send xml request to a server
		q_curl_setopt_with_log($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
		q_curl_setopt_with_log($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
		q_curl_setopt_with_log($curl_handle, CURLINFO_HEADER_OUT, true);

		q_curl_setopt_with_log($curl_handle, CURLOPT_POSTFIELDS, $xmlRequest);
		q_curl_setopt_with_log($curl_handle, CURLOPT_RETURNTRANSFER, 1);

		q_curl_setopt_with_log($curl_handle, CURLOPT_VERBOSE, 0);
		q_curl_setopt_with_log($curl_handle, CURLOPT_HTTPHEADER, static::GetRawRequestHeaders());
		q_curl_setopt_with_log($curl_handle, CURLOPT_HEADER, 1);

		$t1 = microtime(true);
		
		$data = q_curl_exec_with_log($curl_handle);

		if ($data === false)
			throw new \Exception("Invalid response from server - " . curl_error($curl_handle));

		$curl_info = curl_getinfo($curl_handle);

		// $resp_header = substr($data, 0, $curl_info['header_size']);
		$resp_body = substr($data, $curl_info['header_size']);
		#var_dump($url, $xmlRequest, $resp_body);
		$decoded = $euapi->decodeResponse($resp_body, true, true, $req_method);

		return [$decoded, $data];
	}

	public function resync_hotels_content($hotels)
	{
		$objs = [];
		$__ttime = strtotime(date("Y-m-d"));
		
		$useNewFacilitiesFunctionality = (defined('USE_NEW_FACILITIES') && USE_NEW_FACILITIES);

		if ($useNewFacilitiesFunctionality)
		{
			$facilitiesForTopsCodesReqs = [];
			foreach ($hotels ?: [] as $hotel)
			{
				if ($hotel->ResellerCode)
					$facilitiesForTopsCodesReqs[$hotel->ResellerCode] = $hotel->ResellerCode;
			}

			$facilitiesForTopsCodes = [];
			foreach ($facilitiesForTopsCodesReqs ?: [] as $ft)
			{
				$facilitiesForTopsCodes[$ft] = $this->getHotelsFacilities(["TourOpCode" => $ft]);	
			}
		}

		foreach ($hotels ?: [] as $hotel)
		{
			if ((!$hotel->MTime || (strtotime(date("Y-m-d", strtotime($hotel->MTime))) != $__ttime)))
			{ 
				$reqParams = [
					"TourOpCode" => $hotel->ResellerCode,
					"CityCode" => $hotel->Address->City->InTourOperatorId,
					"CountryCode" => $hotel->Address->City->Country->Code,
					"ProductType" => "hotel",
					"ProductCode" => $hotel->InTourOperatorId,
					//"ProductName" => $hotel->Name,
				];

				try
				{
					$respData = static::GetResponseData($this->doRequest("getProductInfoRequest", $reqParams), "getProductInfoResponse");

					if (!$respData)
						continue;

					list($hotelObj) = ($respData && $respData["Product"]) ? $this->getHotel($respData["Product"], $objs, true, false) : [null];

					if ($useNewFacilitiesFunctionality)
					{
						if ($facilitiesForTopsCodes && $facilitiesForTopsCodes[$hotel->ResellerCode][$hotelObj->Code])
						{
							if (!$hotelObj->Facilities)
								$hotelObj->Facilities = new \QModelArray();

							$ef = [];
							foreach ($hotelObj->Facilities as $hf)
							{
								if ($hf->Name)
									$ef[static::GetFacilityIdf($hf->Name)] = $hf;
							}

							foreach ($facilitiesForTopsCodes[$hotel->ResellerCode][$hotelObj->Code] ?: [] as $facility)
							{
								if (isset($ef[static::GetFacilityIdf($facility->Name)]))
									continue;
								$hotelObj->Facilities[] = $facility;
							}
						}
					}

					if ($hotelObj)
					{
						$hotelObj->setMTime(date("Y-m-d H:i:s"));
						$this->saveInBatchHotels([$hotelObj], 
							"FromTopAddedDate, "
							. "MTime,"
							. "Code, "
							. "Name,"
							. "Stars,"
							. "BookingUrl, "
							. "MainImage.{"
								. "Updated, "
								. "Path, "
								. "ExternalUrl, "
								. "Type, "
								. "RemoteUrl, "
								. "Base64Data, "
								. "TourOperator.{Handle, Caption, Abbr}, "
								. "InTourOperatorId, "
								. "Alt"
							. "}, "
							. "Active, "
							. "ShortContent, "
							. "Master.{"
								. "HasCharterOffers,"
								. "HasChartersActiveOffers, "
								. "HasPlaneChartersActiveOffers, "
								. "HasBusChartersActiveOffers, "
							. "},"
							. "HasIndividualOffers,"
							. "HasCharterOffers,"
							. "HasChartersActiveOffers, "
							. "HasPlaneChartersActiveOffers, "
							. "HasBusChartersActiveOffers, "
							. "TourOperator,"
							. "InTourOperatorId,"
							. "ResellerCode,"
							. "Content.{"
								. "Order, "
								. "Active, "
								. "ShortDescription, "
								. "Content, "
								. "ImageGallery.{"
									. "Items.{"
										. "Updated, "
										. "Path, "
										. "Type, "
										. "ExternalUrl,"
										. "RemoteUrl, "
										. "Base64Data, "
										. "TourOperator.{Handle, Caption, Abbr}, "
										. "InTourOperatorId, "
										. "Order, "
										. "Alt"
									. "}"
								. "}"
							. "},"
							. "Address.{"
								. "City.{"
									. "FromTopAddedDate, "
									. "Code, "
									. "Name, "
									. "County.{"
										. "FromTopAddedDate, "
										. "Code, "
										. "Name, "
										. "Country.{"
											. "Code, "
											. "Name "
										. "}"
									. "}, "
									. "Country.{"
										. "Code, "
										. "Name"
									. "}"
								. "}, "
								. "County.{"
									. "Code, "
									. "Name, "
									. "Country.{"
										. "Code, "
										. "Name"
									. "}"
								. "}, "
								. "Country.{"
									. "Code, "
									. "Name"
								. "}, "
								. "Latitude, "
								. "Longitude"
							. "}");
					}
				}
				catch (\Exception $ex)
				{
					echo "<div style='color: red;'>Continutul hotelului: " . $hotel->getId() . "|" . $hotel->Name . " nu a putut fi updatat. Mesajul de eroare: " 
						. $ex->getMessage() . "</div>";
				}
				#file_put_contents($this->TourOperatorRecord->Handle . "_saved.txt", $hotelObj->getId() . "\n", FILE_APPEND);
			}	
		}
	}
	
	public function getExistingHotelsByTopsIds_Cust(array $retHotels, string $useEntity = null)
	{
		$hotelsTopsIds = [];
		$toProcessHotels = [];
		foreach ($retHotels ?: [] as $hotelData)
		{
			if (!$hotelData["Product"] || !$hotelData["Product"]["TourOpCode"] || 
				($hotelData["Product"]["TourOpCode"] != $this->TourOperatorRecord->ApiContext) || !$hotelData["Product"]["ProductCode"])
				continue;
			$hotelsTopsIds[$hotelData["Product"]["ProductCode"]] = $hotelData["Product"]["ProductCode"];
			$toProcessHotels[$hotelData["Product"]["ProductCode"]] = $hotelData;
		}
		return [$this->getExistingHotelsByTopsIds($hotelsTopsIds, $useEntity), $toProcessHotels];
	}
	
	public function getExistingToursByTopsIds_Cust(array $retTours, string $useEntity = null)
	{
		$toursTopsIds = [];
		$toProcessTours = [];
		foreach ($retTours ?: [] as $retTour)
		{
			if ((!$retTour["CircuitId"]) || (!$retTour["TourOpCode"]) || 
				($retTour["TourOpCode"] != $this->TourOperatorRecord->ApiContext))
				continue;
			$toursTopsIds[$retTour["CircuitId"]] = $retTour["CircuitId"];
			$toProcessTours[$retTour["CircuitId"]] = $retTour;
		}
		return [$this->getExistingToursByTopsIds($toursTopsIds, $useEntity), $toProcessTours];
	}

	public function refresh_CacheData(\Omi\TF\CacheFileTracking $cacheDataRecord, \QModelArray $nightsObjs, string $cacheDataContent, bool $showDiagnosis = false)
	{
		if ($cacheDataRecord->VacationType === "charter")
		{
			$response = [
				"success" => true, 
				"data" => $this->SOAPInstance->decodeResponse($cacheDataContent, true, true, "getPackageNVPriceResponse"), 
				"rawResp" => $cacheDataContent
			];

			$packages = static::GetResponseData($response, "getPackageNVPriceResponse");

			$hotels = ($packages && $packages["Hotel"]) ? $packages["Hotel"] : null;
			if ($hotels && $hotels["Product"])
				$hotels = [$hotels];

			list($existingHotels, $toProcessHotels) = $hotels ? $this->getExistingHotelsByTopsIds_Cust($hotels, "Name") : [];

			$params = $cacheDataRecord->RequestData ? json_decode($cacheDataRecord->RequestData, true) : [];

			
			# Added by Alex S. - fixing missing hotels ids (no longer available)
			{
				foreach ($nightsObjs ?: [] as $nobj)
				{
					foreach ($toProcessHotels ?: [] as $tp_hotel_in_top_id => $tp_hotel_data)
					{
						if (!($hotel = $existingHotels[$tp_hotel_in_top_id]))
							continue;
						$nobj->_hotels_ids[$hotel->getId()] = $hotel->getId();
					}
				}
			}
			
			$hotelsNightsObjs = [];
			foreach ($nightsObjs ?: [] as $nobj)
			{
				foreach ($nobj->_hotels_ids ?: [] as $hid)
					$hotelsNightsObjs[$hid][] = $nobj;
			}

			$toCacheData = [];
			foreach ($toProcessHotels ?: [] as $hotelInTopId => $hotelData)
			{
				if (!($hotel = $existingHotels[$hotelInTopId]))
					continue;
				
				if (!($hotelNightsObjs = $hotelsNightsObjs[$hotel->getId()]))
					continue;

				foreach ($hotelNightsObjs ?: [] as $nightsObj)
				{
					try
					{
						$this->setupTransportsCacheData_Hotels_topCust($toCacheData, $nightsObj, $hotelData, $hotel, $params, null, $showDiagnosis);
					}
					catch (\Exception $ex)
					{
						// if can't cache data then just continue
						#if ($showDiagnosis)
						#	echo "<div style='color: green;'>Cannot do the cache [{$ex->getMessage()}]</div>";
						throw $ex;
					}
				}
			}

			// save transports cache data
			$this->saveTransportsCacheData($toCacheData, false, $cacheDataRecord->TravelfuseReqID, $cacheDataRecord->CacheReqID, $cacheDataRecord->CacheFileRealPath);

		}
		else if ($cacheDataRecord->VacationType === "tour")
		{
			$response = [
				"success" => true, 
				"data" => $this->SOAPInstance->decodeResponse($cacheDataContent, true, true, "CircuitSearchRequest"), 
				"rawResp" => $cacheDataContent
			];

			$tours = static::GetResponseData($response, "CircuitSearchResponse");
			
			$tours = isset($tours["Circuit"]) ? $tours["Circuit"] : null;
			if ($tours && isset($tours["CircuitId"]))
				$tours = [$tours];
			
			if (!$tours)
				return;
			
			
			list($existingTours, $toProcessTours) = $this->getExistingToursByTopsIds_Cust($tours, "Title");
			
			$params = $cacheDataRecord->RequestData ? json_decode($cacheDataRecord->RequestData, true) : [];
			
			# Added by Alex S. - fixing missing hotels ids (no longer available)
			{
				foreach ($nightsObjs ?: [] as $nobj)
				{
					foreach ($toProcessTours ?: [] as $tourInTopId => $tp_tour_data)
					{
						if (!($tour = $existingTours[$tourInTopId]))
							continue;
						$nobj->_tours_ids[$tour->getId()] = $tour->getId();
					}
				}
			}

			$toursNightsObjs = [];
			foreach ($nightsObjs ?: [] as $nobj)
			{
				foreach ($nobj->_tours_ids ?: [] as $tid)
					$toursNightsObjs[$tid][] = $nobj;
			}

			$toCacheData = [];
			foreach ($toProcessTours ?: [] as $tourInTopId => $tourData)
			{
				if (!($tour = $existingTours[$tourInTopId]))
					continue;
				
				if (!($tourNightsObjs = $toursNightsObjs[$tour->getId()]))
					continue;
				
				$tourDataOffs = isset($tourData["Variants"]["Variant"]) ? $tourData["Variants"]["Variant"] : null;
				if ($tourDataOffs && isset($tourDataOffs["UniqueId"]))
					$tourDataOffs = [$tourDataOffs];

				if (!$tourDataOffs)
					continue;

				foreach ($tourNightsObjs ?: [] as $nightsObj)
				{
					try
					{
						foreach ($tourDataOffs ?: [] as $variant)
							$this->setupTransportsCacheData_Tours_topCust($toCacheData, $nightsObj, $variant, $tour, $params, null, $showDiagnosis);
					}
					catch (\Exception $ex)
					{
						// if can't cache data then just continue
						#if ($showDiagnosis)
						#	echo "<div style='color: green;'>Cannot do the cache [{$ex->getMessage()}]</div>";
						throw $ex;
					}
				}
			}

			// save transports cache data
			$this->saveTransportsCacheData($toCacheData, true, $cacheDataRecord->TravelfuseReqID, $cacheDataRecord->CacheReqID, $cacheDataRecord->CacheFileRealPath);
		}
	}
}