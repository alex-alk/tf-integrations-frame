<?php

namespace Omi\TF;

/**
 * Description of ETrip
 *
 * @author Csabi
 */
class ETrip extends \QStorageEntry implements \QIStorage
{
	use TOStorage, ETripBase, ETripIndividual, ETripCharters, ETripCircuits, ETripReqs, ETrip_CacheAvailability, 
		ETrip_GetOffers, ETrip_CacheGeography, Etrip_CacheStaticData, TOStorage_CacheAvailability, TOStorage_CacheGeography, 
		TOStorage_CacheStaticData, TOStorage_GetOffers {
			ETrip_CacheGeography::cacheTOPCountries insteadof TOStorage_CacheGeography;
			ETrip_CacheGeography::cacheTOPRegions insteadof TOStorage_CacheGeography;
			ETrip_CacheGeography::cacheTOPCities insteadof TOStorage_CacheGeography;
			Etrip_CacheStaticData::cacheTOPHotels insteadof TOStorage_CacheStaticData;
			Etrip_CacheStaticData::cacheTOPTours insteadof TOStorage_CacheStaticData;
			ETrip_CacheAvailability::cacheChartersDepartures insteadof TOStorage_CacheAvailability;
			ETrip_CacheAvailability::cacheToursDepartures insteadof TOStorage_CacheAvailability;
			ETrip_CacheAvailability::saveCharters insteadof TOStorage_CacheAvailability;
			ETrip_CacheAvailability::saveTours insteadof TOStorage_CacheAvailability;
		}

	public static $Exec = false;

	public static $RequestOriginalParams = null;

	public static $RequestData = null;

	public static $RequestsData = [];

	public static $DefaultCurrency = "EUR";

	public $geoNodes = [];

	public $translatedReversedPeriods = null;

	public static $Config = [
		"christian_tour" => [
			"countries_names_translations" => [
				"Rusia" => "Russia",
				"Africa de Sud" => "South Africa",
				"Etiopia" => "Ethiopia",
				"Statele Unite" => "United States of America",
				"Maroc" => "Morocco",
				"Indonezia" => "Indonesia",
				"Japonia" => "Japan",
				"Irlanda" => "Ireland (Republic Of)",
				'Finlanda' => 'Finland'
			],
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
			"force_reqs_on_cities" => [
				// antalya
				5941 => 5941,
				// riviera olimpului
				39694 => 39694,
				// creta
				53666 => 53666,
				// turcia egee
				53893 => 53893
			],
			"CUSTOM_DESTS" => [
				39803 => 39803
			]
		],
		"cocktail_holidays" => [
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
			"force_reqs_on_cities" => [
				// bodrum
				5945 => 5945,
			],

			"comission" => [
				"charter" => [
					0 => 5,
					1 => 7
				],
				"tour" => [
					0 => 5.4,
					1 => 7
				]
			]
		],
		"holiday_office" => [
			"countries_names_translations" => [
				"Rusia" => "Russia",
				"Africa de Sud" => "South Africa",
				"Etiopia" => "Ethiopia",
				"Statele Unite" => "United States of America",
				"Maroc" => "Morocco",
				"Indonezia" => "Indonesia",
				"Japonia" => "Japan",
				"Irlanda" => "Ireland (Republic Of)",
				"Olanda" => "Netherlands"
			],
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
			"force_reqs_on_cities" => [
				// 
				5941 => 5941,
				6125 => 6125
			],
			'translated_periods' => [
				"plane" => [
					"2019-12-29" => [
						"5687" => [
							"9646" => [5 => 6],
							"9651" => [5 => 6],
							"9648" => [5 => 6],
							"9645" => [5 => 6],
							"9725" => [5 => 6],
							"9647" => [5 => 6],
							"9649" => [5 => 6],
							"9657" => [5 => 6],
							"9650" => [5 => 6],
							"9726" => [5 => 6],
							"9802" => [5 => 6],
							"6122" => [5 => 6],
							"6125" => [5 => 6],
							"9696" => [5 => 6],
							"9494" => [5 => 6],
							"6127" => [5 => 6],
						]
					],
					"2019-12-29" => [
						"5687" => [
							"9646" => [6 => 7],
							"9651" => [6 => 7],
							"9648" => [6 => 7],
							"9645" => [6 => 7],
							"9725" => [6 => 7],
							"9647" => [6 => 7],
							"9649" => [6 => 7],
							"9657" => [6 => 7],
							"9650" => [6 => 7],
							"9726" => [6 => 7],
							"9802" => [6 => 7],
							"6122" => [6 => 7],
							"6125" => [6 => 7],
							"9696" => [6 => 7],
							"9494" => [6 => 7],
							"6127" => [6 => 7],
						] 
					]
				]
			],
			"force_reqs_on_county" => [
				// kos
				5405 => 5405
			]
		],
		"hello_holidays" => [
			"countries_names_translations" => [
				"Rusia" => "Russia",
				"Africa de Sud" => "South Africa",
				"Etiopia" => "Ethiopia",
				"Statele Unite" => "United States of America",
				"Maroc" => "Morocco",
				"Indonezia" => "Indonesia",
				"Japonia" => "Japan",
				"Irlanda" => "Ireland (Republic Of)",
			],
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
			"force_reqs_on_cities" => [
				// riviera olimpului
				9330 => 9330,
				// halkidiki
				9325 => 9325
			],
			"sync_travel_items_on" => [
				"charter" => [
					"bus" => true
				]
			],
			"no_requests_on" => [
				"charter" => [
					"bus" => true
				]
			],
		],
		"tui_travelcenter" => [
			"countries_names_translations" => [
				"Rusia" => "Russia",
				"Africa de Sud" => "South Africa",
				"Etiopia" => "Ethiopia",
				"Statele Unite" => "United States of America",
				"Maroc" => "Morocco",
				"Indonezia" => "Indonesia",
				"Japonia" => "Japan",
				"Irlanda" => "Ireland (Republic Of)",
				"Grecia" => "Greece",
				"Egipt" => "Egypt",
				"Turcia" => "Turkey",
				"Spania" => "Spain"
			],
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
			/*
			"force_reqs_on_cities" => [
				// riviera olimpului
				9330 => 9330,
				// halkidiki
				9325 => 9325
			],
			*/
			"sync_travel_items_on" => [
				"charter" => [
					"bus" => true
				]
			],
			"no_requests_on" => [
				"charter" => [
					"bus" => true
				]
			]
		],
		
		"holiday_plan" => [
			"countries_names_translations" => [
				"Rusia" => "Russia",
				"Africa de Sud" => "South Africa",
				"Etiopia" => "Ethiopia",
				"Statele Unite" => "United States of America",
				"Maroc" => "Morocco",
				"Indonezia" => "Indonesia",
				"Japonia" => "Japan",
				"Irlanda" => "Ireland (Republic Of)",
				"Grecia" => "Greece",
				"Egipt" => "Egypt",
				"Turcia" => "Turkey",
				"Muntenegru" => "Montenegro",
				"Spania" => "Spain",
				"Cipru" => "Cyprus"
			],
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
		],
		"mario_viajes" => [
			"countries_names_translations" => [
				"Rusia" => "Russia",
				"Africa de Sud" => "South Africa",
				"Etiopia" => "Ethiopia",
				"Statele Unite" => "United States of America",
				"Maroc" => "Morocco",
				"Indonezia" => "Indonesia",
				"Japonia" => "Japan",
				"Irlanda" => "Ireland (Republic Of)",
				"Grecia" => "Greece",
				"Egipt" => "Egypt",
				"Turcia" => "Turkey",
				"Muntenegru" => "Montenegro",
				"Spania" => "Spain",
				"Cipru" => "Cyprus"
			],
			"charter" => [
				"airport_tax_included" => true
			],
			"tour" => [
				"airport_tax_included" => true
			],
		],
	];

	public static $_CacheData = array();

	private static $_LoadedCacheData = array();

	private static $_Facilities = [];

	private static $SaveCityIfNotFound = true;

	protected static $_IMAGES_URLS = [
		"cocktail_holidays" => [
			"http://etripstaging.cocktailholidays.ro/" => "http://etripstaging.cocktailholidays.ro/file.php?file=__IMG_SOURCE__&ext=.png",
			"http://etrip.cocktailholidays.ro/" => "http://etrip.cocktailholidays.ro/file.php?file=__IMG_SOURCE__&ext=.png",
			"https://etrip.cocktailholidays.ro/" => "https://etrip.cocktailholidays.ro/file.php?file=__IMG_SOURCE__&ext=.png"
		],

		"christian_tour" => [
			"http://etripstaging.christiantour.ro/" => "http://b2bstaging.christiantour.ro/get_file.php?id=__IMG_SOURCE__",
			"http://etrip.christiantour.ro/" => "http://b2bchr.christiantour.ro/server/get_image.php?action=getimage&file=__IMG_SOURCE__",
			"https://etrip.christiantour.ro/" => "https://b2bchr.christiantour.ro/server/get_image.php?action=getimage&file=__IMG_SOURCE__"
		],

		"holiday_office" => [
			"http://etriptest.holidayoffice.ro/" => "http://b2b.holidayoffice.ro/server/get_image.php?action=getimage&file=__IMG_SOURCE__",
			"http://etrip.holidayoffice.ro/" => "http://b2b.holidayoffice.ro/server/get_image.php?action=getimage&file=__IMG_SOURCE__",
			"https://etrip.holidayoffice.ro/" => "https://b2b.holidayoffice.ro/server/get_image.php?action=getimage&file=__IMG_SOURCE__"
		],

		"hello_holidays" => [
			"http://etripstaging.helloholidays.ro/" => "http://b2b.helloholidays.ro/server/get_image.php?file=__IMG_SOURCE__",
			"http://etrip.helloholidays.ro/" => "http://b2b.helloholidays.ro/server/get_image.php?file=__IMG_SOURCE__",
			"https://etrip.helloholidays.ro/" => "https://b2b.helloholidays.ro/server/get_image.php?file=__IMG_SOURCE__"
		],
		
		"europa_travel" => [
			"http://etriptest.europatravel.ro/" => "http://b2b.europatravel.ro/server/get_image.php?file=__IMG_SOURCE__",
			"http://etrip.europatravel.ro/" => "http://b2b.europatravel.ro/server/get_image.php?file=__IMG_SOURCE__",
			"https://etrip.europatravel.ro/" => "https://b2b.europatravel.ro/server/get_image.php?file=__IMG_SOURCE__"
		],

		"tui_travelcenter" => [
			"http://etriptest.tuitravelcenter.ro/" => "https://etrip.tuitravelcenter.ro/file.php?file=__IMG_SOURCE__",
			"http://etrip.tuitravelcenter.ro/" => "https://etrip.tuitravelcenter.ro/file.php?file=__IMG_SOURCE__",
			"https://etrip.tuitravelcenter.ro/" => "https://etrip.tuitravelcenter.ro/file.php?file=__IMG_SOURCE__"
		],
		
		"holiday_plan" => [
			"http://etriptest.holidayplan.ro/" => "https://etrip.holidayplan.ro/file.php?file=__IMG_SOURCE__",
			"http://etrip.holidayplan.ro/" => "https://etrip.holidayplan.ro/file.php?file=__IMG_SOURCE__",
			"https://etrip.holidayplan.ro/" => "https://etrip.holidayplan.ro/file.php?file=__IMG_SOURCE__"
		],
		"mario_viajes" => [
			"http://etriptest.marioviajes.com/" => "https://etrip.marioviajes.com/file.php?file=__IMG_SOURCE__",
			"http://etrip.marioviajes.com/" => "https://etrip.marioviajes.com/file.php?file=__IMG_SOURCE__",
			"https://etrip.marioviajes.com/" => "https://etrip.marioviajes.com/file.php?file=__IMG_SOURCE__"
		],
	];

	public static $DoCitiesCountiesCrossSearch = true;

	public function __construct($name = null, \QStorageFolder $parent = null) 
	{
		$this->SOAPInstance = new ETripApi();			
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

	public function getSoapInstance()
	{
		return $this->SOAPInstance->client;
	}

	/*===================================SETUP HERE STORAGE METHODS=============================================*/
	
	public static function GetDataClass()
	{
		return \QApp::GetDataClass();
	}

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

	public function getTranslatedPeriod($transportType, $date, $departure, $destination, $duration)
	{
		return ((($translatedPeriods = static::$Config[$this->TourOperatorRecord->Handle]['translated_periods']) && 
			(isset($translatedPeriods[$transportType][$date][$departure][$destination][$duration]))) ?
		$translatedPeriods[$transportType][$date][$departure][$destination][$duration] : null);		
	}
	
	public function getToCallPeriod_FromTranslated(&$params)
	{
		if ((!($transportType = ($params['IsFlight'] ? 'plane' : ($params['IsBus'] ? 'bus' : null)))) || 
			(!($departure = ($params['Departure']))) || 
			(!($destination = ($params['Destination']))) || 
			(!($departureDate = ($params['DepartureDate']))) || 
			(!($duration = ($params['Duration'])))
		)
			return false;
		if ($this->translatedReversedPeriods === null)
		{
			$this->translatedReversedPeriods = [];
			if (($translatedPeriods = static::$Config[$this->TourOperatorRecord->Handle]['translated_periods']))
			{
				foreach ($translatedPeriods ?: [] as $tp_transportType => $periodsByDate)
				{
					foreach ($periodsByDate ?: [] as $tp_departureDate => $periodsByDepartures)
					{
						foreach ($periodsByDepartures ?: [] as $tp_departure => $periodsByDestinations)
						{
							foreach ($periodsByDestinations ?: [] as $tp_destination => $periodsByCallPeriod)
							{
								foreach ($periodsByCallPeriod ?: [] as $tp_callPeriod => $tp_translatedPeriod)
								{
									$this->translatedReversedPeriods[$tp_transportType][$tp_departureDate][$tp_departure][$tp_destination][$tp_translatedPeriod] = $tp_callPeriod;
								}
							}
						}
					}
				}
			}
		}

		if (($callDuration = $this->translatedReversedPeriods[$transportType][$departureDate][$departure][$destination][$duration]))
			$params["Duration"] = $callDuration;
	}

	public function initTourOperator($config = [])
	{
		if (!is_array($config))
			$config = [];
		$config = array_merge([
			"MERGE_TRANSP" => true,
			"SAVE_REQUESTS_ONLY_ON_NEW" => true,
		], $config);
		$this->syncStaticData($config);
		$this->setupCachedData($config);
	}

	public function testConnectivity()
	{		
		return $this->SOAPInstance->testConnectivity();
	}

	public function getHotelsWithIndividualOffers($parameters)
	{
		if (!$parameters)
			throw new \Exception("Paramters are mandatory!");
		/*
		if (!$parameters["CityCode"] && !$parameters["CityName"])
			throw new \Exception("City Code or City Name must be provided");
		*/
		if (!$parameters["PeriodOfStay"] || !$parameters["PeriodOfStay"][0] || !$parameters["PeriodOfStay"][1] || 
			!$parameters["PeriodOfStay"][0]["CheckIn"] || !$parameters["PeriodOfStay"][1]["CheckOut"])
			throw new \Exception("Stay period must be provided!");

		//$parameters["Destination"] = (int)$self->translateCityCode($parameters["CityCode"]); //6125 - code for Dubai
		$parameters["Destination"] = $parameters["CityCode"] ?: $parameters["Zone"];
		$interval = date_diff(date_create($parameters["PeriodOfStay"][1]["CheckOut"]), date_create($parameters["PeriodOfStay"][0]["CheckIn"]));

		$childAges = [];
		$room = $parameters["Rooms"][0]["Room"];
		if ($room["NoChildren"] > 0)
		{
			for ($i = 0; $i < $room["NoChildren"]; $i++)
				$childAges[] = ($room["Children"] && $room["Children"][$i]) ? $room["Children"][$i]["Age"] : 0;
		}

		$params = [
			"Destination" => $parameters["Destination"], 
			"CheckIn" => $parameters["PeriodOfStay"][0]["CheckIn"], 
			"Stay" => (int)$interval->format('%a'),
			"Rooms" => array("Room" => array("Adults" => $room["NoAdults"] ? (int)$room["NoAdults"] : 1, "ChildAges" => $childAges)),
			"MinStars" => 0,
			"ForPackage" => false,
			"PricesAsOf" => null,
			"ShowBlackedOut" => true,
			"Currency" => $parameters["CurrencyCode"],
			"__request_data__" => $parameters["__request_data__"]
		];

		if ($parameters["ProductCode"])
		{
			$source = null;
			$hotelId = $parameters["ProductCode"];
			if (strpos($hotelId, "|") !== false)
				list($hotelId, $source) = explode("|", $hotelId);
			$hotelId = (int)$hotelId;
			$params["Hotel"] = $hotelId;
			if ($source)
				$params["HotelSource"] = $source;
		}

		return $this->GetHotelAvailability($params, $parameters);
	}
	
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

		if (!$self || (get_class($self) != get_called_class()) || !$self->ApiPassword || (!$self->ApiUsername) || (!$self->ApiUrl))
			throw new \Exception("No storage instance provided!");

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
			case "InternationalHotels" : 
			{
				$ret = null;
				if (\Omi\App::HasIndividualInOnePlace() || $parameters["category"] || $parameters["custDestination"] || 
					$parameters["mainDestination"] || $parameters["dynamic_packages"] || $parameters["for_dynamic_packages"])
					$ret = $self->getHotelsWithIndividualOffers($parameters);
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
			case "Nationalities" :
			{
				$ret = null;
				break;
			}
			case "HotelsFacilities" : 
			{
				$ret = null;
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

				#qvardump('$fees, $installments, $fees_params, $installments_params', $fees, $installments, $fees_params, $installments_params);

				list($installments, $fees) = 
					\Omi\TFuse\Api\TravelFuse::MergePaymentsAndCancelFees($installments, $fees, $fees_params["Price"], $fees_params["CheckIn"], $self, $fees_params, $installments_params);

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

				$fees = null;
				$feesParams = $parameters;
				$feesParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				$feesParams['type'] = 'hotel';
				
				$will_do_api_getCancelFees = false;
				try
				{
					if (($feesDefinitions = \Omi\Comm\Offer\CancelFee::GetPartnerFeesDefinitions($feesParams)) && count($feesDefinitions))
					{
						$fees = \QApi::Call("\Omi\Comm\Offer\CancelFee::GetPartnerFees", $feesParams);			
					}
					else if ($self->TourOperatorRecord->HasCancelFees)
					{
						$will_do_api_getCancelFees = true;
						$fees = $self->api_getCancelFees($feesParams);
					}
				}
				finally
				{
					q_remote_log_sub_entry([
						[
							'Timestamp_ms' => (string)microtime(true),
							'Tags' => ['tag' => "etrip - HotelFees"],
							'Traces' => (new \Exception())->getTraceAsString(),
							'Data' => ['$feesDefinitions' => $feesDefinitions, '$fees' => $fees, 'to_rec' => $self->TourOperatorRecord, '$will_do_api_getCancelFees' => $will_do_api_getCancelFees],
						]
					]);
				}

				// setup default cancel fees - if the case
				if (static::NoFees($fees))
					$fees = static::GetDefaultFees($feesParams);

				$ret = $fees;
				break;
			}
			case "TourFees" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				$fees = null;
				$feesParams = $parameters;
				$feesParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				$feesParams['type'] = 'tour';

				if (($feesDefinitions = \Omi\Comm\Offer\CancelFee::GetPartnerFeesDefinitions($feesParams)) && count($feesDefinitions))
				{
					$fees = \QApi::Call("\Omi\Comm\Offer\CancelFee::GetPartnerFees", $feesParams);			
				}
				else if ($self->TourOperatorRecord->HasCancelFees)
				{
					$fees = $self->api_getCancelFees($feesParams);
				}
				// setup default cancel fees - if the case
				if (static::NoFees($fees))
					$fees = static::GetDefaultFees($feesParams);
				$ret = $fees;
				break;
			}
			case "Installments" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				$paymentParams = $parameters;
				$paymentParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				$paymentParams['type'] = 'hotel';
				if (($paymentsDefintions = \Omi\Comm\Payment::GetPartnerInstallmentsDefinitions($paymentParams)) && count($paymentsDefintions))
				{
					$ret = \QApi::Call("\Omi\Comm\Payment::GetPartnerInstallments", $paymentParams);
				}
				else if ($self->TourOperatorRecord->HasInstallments)
				{
					$ret = $self->api_getPayments($paymentParams);
				}
				else
					$ret = null;
				break;
			}
			case "TourInstallments" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				$paymentParams = $parameters;
				$paymentParams["ApiProvider"] = $self->TourOperatorRecord->getId();
				$paymentParams['type'] = 'tour';
				if (($paymentsDefintions = \Omi\Comm\Payment::GetPartnerInstallmentsDefinitions($paymentParams)) && count($paymentsDefintions))
				{	
					$ret = \QApi::Call("\Omi\Comm\Payment::GetPartnerInstallments", $paymentParams);			
				}
				else if ($self->TourOperatorRecord->HasInstallments)
				{
					$ret = $self->api_getPayments($paymentParams);
				}
				else
					$ret = null;
				break;
			}
			case "Circuits" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				if (!$parameters["CityCode"] && !$parameters["CityName"] && !$parameters["Zone"] && !$parameters["Destination"])
					throw new \Exception("City Code or City Name must be provided");
				if (!$parameters["DepCityCode"] && !$parameters["DepCityCode"])
					throw new \Exception("DepCityCode must be provided");
				if (!$parameters["PeriodOfStay"] || !$parameters["PeriodOfStay"][0] || !$parameters["PeriodOfStay"][0]["CheckIn"])
					throw new \Exception("Stay period must be provided!");

				if (!$parameters["Destination"])
					$parameters["Destination"] = ($parameters["Zone"] ?: $parameters["CityCode"]);

				$interval = date_diff(date_create($parameters["PeriodOfStay"][1]["CheckOut"]), date_create($parameters["PeriodOfStay"][0]["CheckIn"]));

				$childAges = [];
				$room = $parameters["Rooms"][0]["Room"];
				if ($room["NoChildren"] > 0)
				{
					for ($i = 0; $i < $room["NoChildren"]; $i++)
						$childAges[] = ($room["Children"] && $room["Children"][$i]) ? $room["Children"][$i]["Age"] : 0;
				}

				$params = [
					"Destination" => (int)($parameters["Destination"]), //2909,// 
					"IsTour" => true,
					"IsFlight" => ($parameters["Transport"] && ($parameters["Transport"] === "plane")),
					"IsBus" => ($parameters["Transport"] && ($parameters["Transport"] === "bus")),
					"DepartureDate" => $parameters["PeriodOfStay"][0]["CheckIn"], //"2016-04-02", // 
					"Departure" => (int)$parameters["DepCityCode"], //Bucuresti
					"Duration" => (int)$interval->format('%a'), //$parameters["Duration"], //(int)$interval->format('%a'),
					"Rooms" => array("Room" => array("Adults" => $room["NoAdults"] ? (int)$room["NoAdults"] : 1, "ChildAges" => $childAges)),
					"MinStars" => 0,
					//"Tour" => $parameters["ProductCode"],
					"ShowBlackedOut" => true,
					"Currency" => $parameters["CurrencyCode"],
					"__request_data__" => $parameters["__request_data__"]
				];

				if ($parameters["ProductCode"])
					$params["Tour"] = $parameters["ProductCode"];

				$av = $self->GetPackageTourSearch($params, $parameters);
				
				$ret = $av;
				break;
			}
			case "Hotels" :
			{
				$parameters = $id;

				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");

				if (!$parameters["CityCode"] && !$parameters["CityName"])
					throw new \Exception("City Code or City Name must be provided");

				$parameters["Destination"] = (int)$parameters["CityCode"];
				$interval = date_diff(date_create($parameters["CheckOut"]), date_create($parameters["CheckIn"]));

				$childAges = [];
				$room = $parameters["Rooms"][0]["Room"];
				if ($room["NoChildren"] > 0)
				{
					for ($i = 0; $i < $room["NoChildren"]; $i++)
						$childAges[] = ($room["Children"] && $room["Children"][$i]) ? $room["Children"][$i]["Age"] : 0;
				}

				$params = [
					"Destination" => $parameters["Destination"], 
					"CheckIn" => $parameters["CheckIn"], 
					"Stay" => (int)$interval->format('%a'),
					"Rooms" => array("Room" => array("Adults" => $room["NoAdults"] ? (int)$room["NoAdults"] : 1, "ChildAges" => $childAges)),
					"Hotel" => $parameters["ProductCode"],
					"MinStars" => 0,
					"ForPackage" => false,
					"PricesAsOf" => null,
					"ShowBlackedOut" => true,
					"Currency" => $parameters["CurrencyCode"],
					"__request_data__" => $parameters["__request_data__"]
				];

				$av = $self->GetHotelAvailability($params, $parameters);

				$ret = $av[0];
				break;
			}
			case "IndividualOffers" :
			case "HotelsWithIndividualOffers" :
			{
				$ret = $self->getHotelsWithIndividualOffers($parameters);
				break;
			}
			/**
			 * This is the real Charters request
			 */
			case "PackagesOffers" :
			case "HotelsWithPackages":
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				if (!$parameters["CityCode"] && !$parameters["CityName"] && !$parameters["Zone"])
					throw new \Exception("City Code or City Name must be provided");
				if (!$parameters["PeriodOfStay"] || !$parameters["PeriodOfStay"][0] || !$parameters["PeriodOfStay"][1] || 
					!$parameters["PeriodOfStay"][0]["CheckIn"] || !$parameters["PeriodOfStay"][1]["CheckOut"])
					throw new \Exception("Stay period must be provided!");

				$parameters["Destination"] = (int)$parameters["CityCode"] ?: (int)$parameters["Zone"];

				$interval = date_diff(date_create($parameters["PeriodOfStay"][1]["CheckOut"]), date_create($parameters["PeriodOfStay"][0]["CheckIn"]));
				$childAges = [];
				$room = $parameters["Rooms"][0]["Room"];
				if ($room["NoChildren"] > 0)
				{
					for ($i = 0; $i < $room["NoChildren"]; $i++)
						$childAges[] = ($room["Children"] && $room["Children"][$i]) ? $room["Children"][$i]["Age"] : 0;
				}

				$params = [
					"Destination" => $parameters["Destination"], //2909,// 
					"IsTour" => false,
					"IsFlight" => ($parameters["Transport"] && ($parameters["Transport"] === "plane")),
					"IsBus" => ($parameters["Transport"] && ($parameters["Transport"] === "bus")),
					"DepartureDate" => $parameters["PeriodOfStay"][0]["CheckIn"], //"2016-04-02", // 
					"Departure" => (int)$parameters["DepCityCode"], //Bucuresti
					"Duration" => (int)$interval->format('%a'),
					"Rooms" => array("Room" => array("Adults" => $room["NoAdults"] ? (int)$room["NoAdults"] : 1, "ChildAges" => $childAges)),
					"MinStars" => 0,
					"ShowBlackedOut" => true,
					"Currency" => $parameters["CurrencyCode"],
					"__request_data__" => $parameters["__request_data__"]
				];

				if ($parameters["ProductCode"])
				{
					$source = null;
					$hotelId = $parameters["ProductCode"];
					if (strpos($hotelId, "|") !== false)
						list($hotelId, $source) = explode("|", $hotelId);
					$hotelId = (int)$hotelId;
					$params["Hotel"] = $hotelId;
					if ($source)
						$params["HotelSource"] = $source;
				}

				$av = $self->GetPackageSearch($params, $parameters);
				$ret = $av;
				break;
			}
			default: 
			{
				throw new \Exception($from." not implemented!");
				//break;
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
			case "Hotels" : 
			{
				break;
			}
			case "Charters" : 
			{
				break;
			}
			case "Tours" :
			{
				break;
			}
			case "Orders" :
			{
				return $self->saveBooking($data);
			}
			case "OrdersUpdates" :
			{
				break;
			}
			case "CachedData" :
			{
				
				break;
			}
		}
	}
	
	public function saveOrdersUpdates()
	{
		// do nothing for now
	}
	/**
	 * 
	 */
	public function setupCachedData(array $config = [])
	{
		$this->cacheTopCountries($config);
		$this->cacheTopHotels($config);
		$this->cacheChartersDepartures($config);
		$this->cacheToursDepartures($config);
	}
	
	public function api_getCancelFees($feesParams)
	{
		$resp = $this->api_getPaymentsFromTopSystem($feesParams);		
		$ret = [];
		if ($resp)
		{
			if (($resp instanceof \stdClass) && isset($resp->Date))
				$resp = [$resp];

			$switchWithDateStart = true;

			$prevFee = null;
			$currencies = [];
			$todayTime = strtotime(date("Y-m-d"));
			$dateStart = date("Y-m-d");
			$totalAmount = 0;
			$currency = $feesParams['SellCurrency'];
			foreach ($resp ?: [] as $r_data)
			{
				if ($switchWithDateStart)
					$dateStart = $r_data->Date;
				else
					$dateEnd = $r_data->Date;
				$amount = $r_data->Amount;
				#$dateStart = date("Y-m-d", strtotime('-1 day'));

				$dateStartTime = strtotime($dateStart);
				if ($todayTime > $dateStartTime)
					$dateStart = date("Y-m-d");

				$cpObj = new \stdClass();
				$cpObj->DateStart = $dateStart;

				if ($switchWithDateStart)
				{
					if ($prevFee)
					{
						$prevFeeEndDateTime = strtotime("-1 day", $dateStartTime);
						$prevFee->DateEnd = ($todayTime > $prevFeeEndDateTime) ? date("Y-m-d") : date("Y-m-d", $prevFeeEndDateTime);	
					}
					$prevFee = $cpObj;
				}
				else {
					$cpObj->DateEnd = $dateEnd;
				}

				
				$totalAmount += $amount;
				$cpObj->Price = $totalAmount;
				$currencyObj = ($currencies[$currency] ?: ($currencies[$currency] = new \stdClass()));
				$currencyObj->Code = $currency;
				$cpObj->Currency = $currencyObj;
				$ret[] = $cpObj;
				if (!$switchWithDateStart)
					$dateStart = date("Y-m-d", strtotime("+1 day", strtotime($dateEnd)));
			}
		}

		if ($switchWithDateStart)
			$cpObj->DateEnd = $feesParams['CheckIn'];

		//qvardump('$ret, $resp, $feesParams', $ret, $resp, $feesParams);
		//throw new \Exception('qqq1');

		$decFees = $this->getOfferCancelFees_Decoded($ret, $feesParams);

		#qvardump('$decFees, $ret, $resp', $decFees, $ret, $resp);
		#throw new \Exception('tt');

		return $decFees;
	}

	public function api_getPaymentsFromTopSystem($params)
	{
		ob_start();
		$ex = null;
		try
		{
			$reqType = ($params['__type__'] == 'charter') ? 
					'charter-request' : (($params['__type__'] == 'tour') ? 'tour-request' : 'individual-request');	
			$this->startTrackReport($reqType);

			#return null;
			if ((!isset($params['ETripIndexOffer'])) || (!isset($params['ETripIndexMeal'])) || ((!$params["TFREQID"]) && (!$params["TFLISTREQID"]))) 
			{
				echo 'Request-ul nu poate fi facut: lipseste un parametru din cei obligatorii: ETripIndexOffer ' 
					. '| ETripIndexMeal | TFREQID | TFLISTREQID';
				return;
			}

			$offerIndex = (int)$params['ETripIndexOffer'];
			$mealIndex = (int)$params['ETripIndexMeal'];

			$toUseReqId = null;
			if (Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle . '-' . $params["TFREQID"]))
				$toUseReqId = $params["TFREQID"];
			else if ($params["TFLISTREQID"] && Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle . '-' . $params["TFLISTREQID"]))
				$toUseReqId = $params["TFLISTREQID"];

			// if we don't have the cookies in this session it means that the result is pulled from cache
			// do again the request to get the php session id within cookies (requests in etrip are identified based on it)
			// PHPSESSID - this is the name of the property in the cookie
			// this should be done all the time because the offers indexes may

			// we need the package hash and the plan hash to identify the offer - we should do this each time because
			// we will get out of sync at some point and the indexes will be different

			$searchResponse = null;
			if ((!$toUseReqId) && $params['packageHash'] && $params['planHash'])
			{
				$offerIndex = null;
				$mealIndex = null;
				if (($reqFullParams = $params['reqFullParams'])) 
				{
					if (isset($reqFullParams['Rooms']['Room']) && !isset($reqFullParams['Rooms']['Room']['ChildAges']))
						$reqFullParams['Rooms']['Room']['ChildAges'] = [];
					if (($apiCode = ($this->TourOperatorRecord->ApiCode__ ?: $this->TourOperatorRecord->ApiCode)))
						$reqFullParams["AgentCode"] = $apiCode;

					$toCallMethod = ($params['__type__'] === 'individual') ? 'HotelSearch' : 'PackageSearch';
					$searchResponse = $this->SOAPInstance->request($toCallMethod, $reqFullParams);
					$offerIndxTmp = 0;
					foreach ($searchResponse ?: [] as $package)
					{
						$originalPackage = json_decode(json_encode($package), true);
						unset($originalPackage['OptionExpiryDate']);
						static::KSortTree($originalPackage);
						$originalPackageHash = md5(json_encode($originalPackage));
						
						if ($params['packageHash'] == $originalPackageHash) 
						{
							$offerIndex = $offerIndxTmp;
							if (isset($originalPackage['HotelInfo']['MealPlans']))
							{
								$mealPlansIndxTmp = 0;
								foreach ($originalPackage['HotelInfo']['MealPlans'] as $mp)
								{
									if (md5(json_encode($mp)) === $params['planHash'])
									{		
										$mealIndex = $mealPlansIndxTmp;
										break;
									}
									$mealPlansIndxTmp++;
								}
							}
							else if (isset($originalPackage['MealPlans']))
							{
								$mealPlansIndxTmp = 0;
								foreach ($originalPackage['MealPlans'] as $mp)
								{
									if (md5(json_encode($mp)) === $params['planHash'])
									{		
										$mealIndex = $mealPlansIndxTmp;
										break;
									}
									$mealPlansIndxTmp++;
								}
							}
							break;
						}
						$offerIndxTmp++;
					}
					$rawCookies = $this->SOAPInstance->client->__getCookies();
					$soapInstanceCookies = [];
					foreach ($rawCookies ?: [] as $cookieKey => $cookieValue) 
						$soapInstanceCookies[$cookieKey] = is_array($cookieValue) ? reset($cookieValue) : $cookieValue;
					$this->SOAPInstance->_cookies = $soapInstanceCookies;

					call_user_func($this->SOAPInstance->client->_x_cookies_callback, $this->SOAPInstance->_cookies, $this->SOAPInstance, 
							null, ($params["TFREQID"] ?? $params["TFLISTREQID"]));
				}

				if (Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle . '-' . $params["TFREQID"]))
					$toUseReqId = $params["TFREQID"];
				else if ($params["TFLISTREQID"] && Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle . '-' . $params["TFLISTREQID"]))
					$toUseReqId = $params["TFLISTREQID"];

				if (!$toUseReqId) {
					echo 'A fost facut request-ul de cautare dar sistemul nu a putut identifica ' 
						. 'sessId-ul din Etrip pentru acest request.';
					return;
				}
			}

			// if we don't have the indexes then don't do the plan request
			if (($offerIndex === null) || ($mealIndex === null))
			{
				echo 'A fost facut request-ul de cautare dar oferta nu a mai putut fi identificata. Cel mai probabil ea nu mai este valabila.';
				qvardump('$params', $params);
				foreach ($searchResponse ?: [] as $package) 
				{
					$originalPackage = json_decode(json_encode($package), true);
					unset($originalPackage['OptionExpiryDate']);
					static::KSortTree($originalPackage);
					qvardump('$originalPackage', json_encode($originalPackage), md5(json_encode($originalPackage)));	
				}
				return;
			}

			$new_params = array();
			$new_params["ResultIndex"] = $offerIndex;
			$new_params["HotelOptions"] = array("MealPlanIndex" => $mealIndex);
			$new_params["FlightOptions"] = array("OutboundIndex" => 0, "InboundIndex" => 0, 'JourneyIndices' => []);

			$paymentsReqID = md5(json_encode($toUseReqId . '_' . json_encode($new_params)));
			if (isset(static::$_CacheData['payments_plan'][$paymentsReqID])) {
				echo "Payment plan-ul a fost intors din cache!";
				return static::$_CacheData['payments_plan'][$paymentsReqID];
			}

			#$new_params["Notes"] = null;
			$resp = null;

			$this->SOAPInstance->setupRequestCookies($toUseReqId);
			$resp = $this->SOAPInstance->request('GetPaymentDates', $new_params);
			$this->SOAPInstance->restorePrevCookies();
			echo "Pentru a vedea datele va rog sa deschideti raportul cu view page source.\n
				Request: " . $this->SOAPInstance->client->__getLastRequest() . "\n\nResponse: " . $this->SOAPInstance->client->__getLastResponse();
			
		}
		catch (\Exception $ex)
		{
			// here we will let the system do the default thing
		}
		finally
		{
			$paymentsDates = ob_get_clean();
			$this->closeTrackReport($paymentsDates, $ex);
		}

		return (static::$_CacheData['payments_plan'][$paymentsReqID] = $resp);
	}

	public function api_getPayments($paymentsParams)
	{
		$resp = $this->api_getPaymentsFromTopSystem($paymentsParams);
		$ret = [];
		if ($resp)
		{
			if (($resp instanceof \stdClass) && isset($resp->Date))
				$resp = [$resp];

			$prevDate = null;
			$currencies = [];
			$prevInstallment = null;
			$todayTime = strtotime(date("Y-m-d"));
			#$totalPercent = 0;
			$currency = $paymentsParams['SellCurrency'];
			foreach ($resp ?: [] as $r_data)
			{
				$dateEnd = $r_data->Date;
				$amount = $r_data->Amount;

				$pObj = new \stdClass();
				$pObj->PayUntil = $dateEnd;
				$prevDateTime = null;
				if ($prevDate !== null)
					$prevDateTime = strtotime("+1 day", strtotime($prevDate));						

				if (($prevDateTime === null) || ($prevDateTime < $todayTime))
					$prevDateTime = $todayTime;

				$pObj->PayAfter = date("Y-m-d", $prevDateTime);
				$pObj->Amount = $amount;
				$currencyObj = ($currencies[$currency] ?: ($currencies[$currency] = new \stdClass()));
				$currencyObj->Code = $currency;
				$pObj->Currency = $currencyObj;
				$ret[] = $pObj;
				$prevDate = $dateEnd;
				$prevInstallment = $pObj;
			}
		}

		$decPayments = $this->getOfferPayments_Decoded($ret, $paymentsParams);

		return $decPayments;
	}

	/**
	 * Tours
	 * 
	 * @param type $params
	 * @param type $initialParams
	 * @return type
	 */
	public function GetPackageTourSearch($params, $initialParams)
	{
		if (!$initialParams)
			$initialParams = [];
		$initialParams["__REQ_INDX__"] = static::$RequestData["__REQ_INDX__"];

		$tour = $initialParams["ProductCode"];
		$countryCode = $initialParams["CountryCode"];

		$ret = new \QModelArray();
		$ret_tours = new \QModelArray();

		$this->getToCallPeriod_FromTranslated($params);
		$res = $this->SOAPInstance->PackageSearch($params);
		
		$reqParams = static::GetRequestParams($initialParams);

		//$showDecisions = ($res && is_array($res));
		$showDecisions = false;

		// Group offers by Hotel
		$offers_desc = array();
		$_hotels_ids = [];
		$_tours_ids = [];
		if ($res && is_array($res))
		{
			$offsIndx = 0;
			foreach ($res as $h_offer)
			{
				$h_offer->_index_offer = $offsIndx++;
				$mealPlansIndx = 0;
				if (isset($h_offer->HotelInfo->MealPlans))
				{
					foreach ($h_offer->HotelInfo->MealPlans ?: [] as $mp)
						$mp->_index_meal_plan = $mealPlansIndx++;
				}

				// make sure that we only return the requested tour - otherwise we can have problems due to caching
				if ((!$h_offer->PackageId) || ($tour && ($tour != $h_offer->PackageId)))
				{
					if ($showDecisions)
					{
						echo "<div style='color: red;'>" . (!$h_offer->PackageId ? 'offer filtered because no package id' : 
							'offer not for current tour [' . $tour .'|' . $h_offer->PackageId . ']') . "</div>";
					}
					continue;
				}

				if ($h_offer->HotelInfo->Source)
				{
					$hidparts = (strrpos($h_offer->HotelInfo->HotelId, '|') !== false) ? explode('|', $h_offer->HotelInfo->HotelId) : [];
					$hasSource = ((count($hidparts) > 1) && (end($hidparts) == $h_offer->HotelInfo->Source));
					if (!$hasSource)
						$h_offer->HotelInfo->HotelId .= "|" . $h_offer->HotelInfo->Source;
				}

				$_hotels_ids[$h_offer->HotelInfo->HotelId] = $h_offer->HotelInfo->HotelId;
				$_tours_ids[$h_offer->PackageId] = $h_offer->PackageId;
				$offers_desc[$h_offer->HotelInfo->HotelId][] = $h_offer;
			}
		}

		if ($showDecisions)
			qvardump("top|params|top resp|filtered resp", $this->TourOperatorRecord->Handle, $params, $res, $offers_desc);

		$hotels = (count($_hotels_ids) > 0) ? $this->GetETripHotelFromDB($_hotels_ids) : [];
		$existingTours = (count($_tours_ids) > 0) ? $this->GetETripTourFromDB($_tours_ids) : [];
		#qvardump('$existingTours', $existingTours, $_tours_ids);

		if ($offers_desc)
		{
			$isMainInstace = (defined('IS_MAIN_INSTANCE') && IS_MAIN_INSTANCE);

			#Do the tf sync if we are using transfer and we are not on master instance
			$DO_TF_SYNC = (\Omi\App::UseTransferStorage() && (!$isMainInstace));

			# if we have offer req it means that we are beyond the hotel page - on checkout/add offer & other
			$iparams_full = static::$RequestOriginalParams;
			$hasPickedOffer = ($iparams_full && $iparams_full['_req_off_']);
			$onOrderSearch = ($iparams_full && $iparams_full['__on_order__']);

			// don't do the sync in this case
			if ($hasPickedOffer || $onOrderSearch)
				$DO_TF_SYNC = false;

			if ($DO_TF_SYNC)
			{
				$isTourSearch = $initialParams['ProductCode'] ? true : false;
				$toPullToursFromTF = [];
				foreach ($offers_desc as $hotel_id => $hDesc)
				{
					foreach ($hDesc as $hD)
					{
						$tourObj = $existingTours[$hD->PackageId];
						if (!($tourObj))
						{
							$toPullToursFromTF[$hD->PackageId] = $hD->PackageId;
						}

						if ($isTourSearch && $tourObj && $tourObj->SyncStatus && $tourObj->SyncStatus->LiteSynced && (!$tourObj->SyncStatus->FullySynced))
						{
							$toPullToursFromTF[$hD->PackageId] = $hD->PackageId;
						}
					}
				}

				if ($toPullToursFromTF && $this->TourOperatorRecord->Handle)
				{
					list($from_TF_Tours) = \Omi\Travel\Merch\Tour::TourSync_SyncFromTF($this->TourOperatorRecord->Handle, $toPullToursFromTF, $isTourSearch);
					foreach ($from_TF_Tours ?: [] as $TF_Tour)
					{
						if ($TF_Tour->getId() && $TF_Tour->InTourOperatorId)
						{
							$existingTours[$TF_Tour->InTourOperatorId] = $TF_Tour;
							if ($TF_Tour->Hotels)
							{
								foreach ($TF_Tour->Hotels as $tourHotel)
								{
									if ($tourHotel)
										$hotels[$tourHotel->InTourOperatorId] = $tourHotel;
								}
							}
						}
					}
				}
			}

			foreach ($offers_desc as $hotel_id => $hDesc)
			{
				//$hotel = $this->GetETripHotelFromDB($hotel_id);
				$hotel = $hotels[$hotel_id];

				if ((!$hotel) && (!$hotel->Id))
				{
					if ($showDecisions)
					{
						echo "<div style='color: red;'>skip - hotel not in db : " . $hotel_id . "</div>";
					}
					continue; // skip offers with hotels we do not have cahced in our database
				}

				//$transport = $this->GetTourTransport("city", $hotel->Address->City, $initialParams["DepCityId"]);
				$transport = null;

				// Loop through each offer
				$tour_has_offers = false;

				foreach ($hDesc as $hD)
				{
					#$tourObj = isset($tours[$hD->PackageId]) ? $tours[$hD->PackageId] : ($tours[$hD->PackageId] = $this->GetETripTourFromDB($hD->PackageId));
					$tourObj = $existingTours[$hD->PackageId];
					if (!($tourObj))
					{
						if ($showDecisions)
						{
							echo "<div style='color: red;'>skip - tour not in db : " . $hD->PackageId . "</div>";
						}
						continue;
					}

					if ($showDecisions)
						echo "Passed tour: [" . $hD->PackageId . "] " . $tourObj->Title . "<br/>";

					$tourObj->setCountryCode($countryCode);

					$getFeesAndInstallments = (
						($tour && ($tourObj->InTourOperatorId == $tour)) || 
						(isset($initialParams["getFeesAndInstallments"]) && (!$initialParams["getFeesAndInstallmentsFor"])) || 
						($initialParams["getFeesAndInstallmentsFor"] && ($initialParams["getFeesAndInstallmentsFor"] == $tourObj->getId()))
					);

					// we need to make sure that the offer is bookable
					if ((!$hD->IsBookable) && (!$_GET['BOOK']))
					{
						if ($showDecisions)
						{
							echo "<div style='color: red;'>skip - not bookable</div>";
							qvardump($hD);
						}
						continue;
					}

					$dt = strtotime($hD->Date);
					$date = date("Y-m-d H:i:s", $dt);

					$params["DateRange"] = [
						"Start" => $date,
						"End" => date("Y-m-d", strtotime("+" . $hD->Duration . " days", $dt))
					];

					// make sure that the period is right
					$tourObj->setPeriod($hD->Duration);

					// loop through each meal plan
					if ($hD->HotelInfo->MealPlans)
					{
						if (!$tourObj->Offers)
							$tourObj->Offers = new \QModelArray();

						foreach ($hD->HotelInfo->MealPlans as $plan)
						{
							$offers = $this->buildOffer([$plan, $hDesc, $transport, $hD], $params, $hD->HotelInfo, $hD->Price, true, 
								$tourObj, $getFeesAndInstallments, $initialParams);

							foreach ($offers ?: [] as $offer)
							{
								if (!$offer)
								{
									if ($showDecisions)
										echo "<div style='color: red;'>skip - null offer</div>";
									continue;
								}

								$tour_has_offers = true;

								if ($offer->isAvailable())
									$tourObj->_has_available_offs = true;
								else if ($offer->isOnRequest())
									$tourObj->_has_onreq_offs = true;

								if ($showDecisions)
									echo "<div style='color: green;'>add offer to [{$offer->Code}] to {$tourObj->getModelCaption()}</div>";

								// offers
								$tourObj->Offers[$offer->Code] = $offer;
								$offer->ReqParams = $reqParams;
							}
						}
					}
					
					if ($tour_has_offers)
						$ret_tours[$tourObj->getId()] = $tourObj;
					else if ($showDecisions)
						echo "<div style='color: red;'>Tour {$tourObj->getModelCaption()} does not have offers</div>";
				}				
			}
			
			$appData = \QApp::NewData();
			if ($ret && count($ret))
			{
				$appData->Hotels = new \QModelArray();
				$appData->Hotels = $ret;
			}

			if ($ret_tours && count($ret_tours))
			{
				$appData->Tours = new \QModelArray();
				$appData->Tours = $ret_tours;
			}
		}

		$ret = ($ret_tours && count($ret_tours)) ? $ret_tours : $ret;

		if ($ret)
			$this->flagToursTravelItems($ret);

		// add the callback tu update tours status
		if ($ret)
		{
			\QApp::AddCallbackAfterResponseLast(function ($tours) {
				// sync tours status
				\Omi\TFuse\Api\TravelFuse::UpdateToursStatus($tours);
			}, [$ret]);
		}
	
		// if the request was not pulled from cache - attach the callback to refresh travel items cache data
		if (($tf_request_id = $params["__request_data__"]["ID"]) && \Omi\TFuse\Api\TravelFuse::Can_RefreshTravelItemsCacheData(static::$RequestOriginalParams) && 
			(!\Omi\TFuse\Api\TravelFuse::DataWasPulledFromCache($tf_request_id)))
		{
			\QApp::AddCallbackAfterResponseLast(function ($tf_request_id) {
				// refresh travel items cache data
				\Omi\TFuse\Api\Travelfuse::RefreshTravelItemsCacheData([
					"TravelfuseReqID" => $tf_request_id
				], false);
			}, [$tf_request_id]);	
		}
		
		return $ret;
	}
	/**
	 * Charters
	 * 
	 * @param type $params
	 * @param type $initialParams
	 * @return type
	 */
	public function GetPackageSearch($params, $initialParams)
	{
		if (!$initialParams)
			$initialParams = [];
		$initialParams["__REQ_INDX__"] = static::$RequestData["__REQ_INDX__"];

		$__QDATA = $this->getAppClone();
		$__QDATA->Hotels = new \QModelArray();

		// we can have a duration that needs to be translated - from the interface we get 7 nights but for the actual request we should use only 6
		$this->getToCallPeriod_FromTranslated($params);

		if (PHP_SAPI === 'cli')
			echo q_date_micro() . " - " . __CLASS__ ."::".__METHOD__." PackageSearch BEFORE\n";
		// do the package search
		$res = $this->SOAPInstance->PackageSearch($params);
		if (PHP_SAPI === 'cli')
			echo q_date_micro() . " - " . __CLASS__ ."::".__METHOD__." PackageSearch AFTER\n";

		#if ($res)
		#	qvardump("\$res", $res);

		if ($params["HotelSource"])
			$params["Hotel"] = $params["Hotel"] . "|" . $params["HotelSource"];

		// req params
		$reqParams = static::GetRequestParams($initialParams);

		#if (static::$Exec || $_GET["RESP_PS"])
			#qvardump("Results", $res, $this->TourOperatorRecord->Handle, $params);

		// Group offers by Hotel
		$offers_desc = array();
		$_to_load_hotels = [];

		if ($res && is_array($res))
		{
			$offsIndx = 0;
			foreach ($res as $h_offer)
			{
				$h_offer->_index_offer = $offsIndx++;
				$mealPlansIndx = 0;
				if (isset($h_offer->HotelInfo->MealPlans))
				{
					foreach ($h_offer->HotelInfo->MealPlans ?: [] as $mp)
						$mp->_index_meal_plan = $mealPlansIndx++;
				}

				//if (isset($offers_desc[$h_offer->HotelInfo->HotelId]))
				//	throw new \Exception("QWQEQWEQW");
				if (!$h_offer->HotelInfo->HotelId)
					continue;

				if ($h_offer->HotelInfo->Source)
				{
					$hidparts = (strrpos($h_offer->HotelInfo->HotelId, '|') !== false) ? explode('|', $h_offer->HotelInfo->HotelId) : [];
					$hasSource = ((count($hidparts) > 1) && (end($hidparts) == $h_offer->HotelInfo->Source));
					if (!$hasSource)
						$h_offer->HotelInfo->HotelId .= "|" . $h_offer->HotelInfo->Source;
				}

				// make sure that we only return the requested hotel - otherwise we can have problems due to caching
				if (($params["Hotel"] && ($params["Hotel"] !== $h_offer->HotelInfo->HotelId)))
					continue;

				$_to_load_hotels[$h_offer->HotelInfo->HotelId] = $h_offer->HotelInfo->HotelId;
				$offers_desc[$h_offer->HotelInfo->HotelId][] = $h_offer;
			}
		}

		if (PHP_SAPI === 'cli')
			echo q_date_micro() . " - " . __CLASS__ ."::".__METHOD__." GetETripHotelFromDB START\n";
		// get existing hotels
		$hotels = (count($_to_load_hotels) > 0) ? $this->GetETripHotelFromDB($_to_load_hotels) : [];
		if (PHP_SAPI === 'cli')
			echo q_date_micro() . " - " . __CLASS__ ."::".__METHOD__." GetETripHotelFromDB END\n";

		$isMainInstace = (defined('IS_MAIN_INSTANCE') && IS_MAIN_INSTANCE);

		#Do the tf sync if we are using transfer and we are not on master instance
		$DO_TF_SYNC = (\Omi\App::UseTransferStorage() && (!$isMainInstace));

		# if we have offer req it means that we are beyond the hotel page - on checkout/add offer & other
		$iparams_full = static::$RequestOriginalParams;
		$hasPickedOffer = ($iparams_full && $iparams_full['_req_off_']);
		$onOrderSearch = ($iparams_full && $iparams_full['__on_order__']);

		// don't do the sync in this case
		if ($hasPickedOffer || $onOrderSearch)
			$DO_TF_SYNC = false;

		# if we are on a hotel search then do the full sync if the case
		$isHotelSearch = $initialParams['ProductCode'] ? true : false;

		if (PHP_SAPI === 'cli')
			echo q_date_micro() . " - " . __CLASS__ ."::".__METHOD__." (\$offers_desc=".($offers_desc ? 'yes' : 'no').") \n";
		if ($offers_desc)
		{
			$toPullHotelsFromTF = [];
			$toProcessOffers = [];
			foreach ($offers_desc as $hotel_id => $hDesc)
			{
				$hotel = $hotels[$hotel_id];

				if ((!$hotel) && (!$hotel->Id))
				{
					$toPullHotelsFromTF[$hotel_id] = $hotel_id;
					// skip offers with hotels we do not have cahced in our database
					#echo "<div style='color: red;'>[{$this->TourOperatorRecord->Handle}] HOTEL {$hotel_id} not in database</div>";
					continue;
				}

				# if we have the hotel and it was just light synced then we must do a full sync
				if ($isHotelSearch && $hotel && $hotel->SyncStatus && $hotel->SyncStatus->LiteSynced && (!$hotel->SyncStatus->FullySynced))
				{
					$toPullHotelsFromTF[$hotel_id] = $hotel_id;
				}
				$toProcessOffers[$hotel_id] = $hDesc;
			}

			# DO THE SYNC
			if ($DO_TF_SYNC && $toPullHotelsFromTF && $this->TourOperatorRecord->Handle)
			{
				list($from_TF_hotels) = \Omi\Travel\Merch\Hotel::HotelSync_SyncFromTF($this->TourOperatorRecord->Handle, $toPullHotelsFromTF, $isHotelSearch);
				foreach ($from_TF_hotels ?: [] as $TF_Hotel)
				{
					if ($TF_Hotel->getId() && $TF_Hotel->InTourOperatorId)
						$hotels[$TF_Hotel->InTourOperatorId] = $TF_Hotel;
				}
			}

			foreach ($toProcessOffers as $hotel_id => $hDesc)
			{
				$hotel = $hotels[$hotel_id];

				if ((!$hotel) && (!$hotel->Id))
				{
					echo "<div style='color: red;'>[{$this->TourOperatorRecord->Handle}] HOTEL {$hotel_id} not in database</div>";
					continue; // skip offers with hotels we do not have cahced in our database
				}

				//$transport = $this->GetCharterTransport("city", $hotel->Address->City, $initialParams["DepCityId"]);
				$transport = null;

				$getFeesAndInstallments = (
					($params["Hotel"] && ($params["Hotel"] == $hotel_id)) || 
					isset($initialParams['getFeesAndInstallments'])
				);
				
				#qvardump('$hDesc', $hDesc);

				try
				{
					// Loop through each offer
					if ($hDesc)
					{
						$hotel->Offers = new \QModelArray();
						$hotel_has_offers = false;
						foreach ($hDesc as $hD)
						{
							// we need to make sure that the offer is bookable
							if (!$hD->IsBookable && !$_GET["BOOK"])
							{
								//continue;
							}

							$dt = strtotime($hD->Date);
							$date = date("Y-m-d H:i:s", $dt);

							$params["DateRange"] = [
								"Start" => $date,
								"End" => date("Y-m-d", strtotime("+" . $hD->Duration . " days", $dt))
							];

							// loop through each meal plan
							if ($hD->HotelInfo->MealPlans)
							{
								foreach ($hD->HotelInfo->MealPlans as $plan)
								{
									//echo "DO BUILD OFFER FOR {$hotel_id}!!!<br/>";
									$offers = $this->buildOffer([$plan, $hDesc, $transport, $hD], $params, $hD->HotelInfo, 
											$hD->Price, false, null, $getFeesAndInstallments, $initialParams);
									if ($offers)
									{
										foreach ($offers as $offer)
										{
											if (!$offer)
												continue;
											if ($offer->isAvailable())
												$hotel->_has_available_offs = true;
											else if ($offer->isOnRequest())
												$hotel->_has_onreq_offs = true;
											$hotel->Offers[$offer->Code] = $offer;

											$offer->ReqParams = $reqParams;
										}
										$hotel_has_offers = true;
									}
								}
							}
						}
					}
				}
				catch (\Exception $ex)
				{
					#qvardump("EX MESSAGE: " . $ex->getMessage() . " | " . $ex->getFile() . " | " . $ex->getLine() . " | " . $ex->getTraceAsString());
					throw $ex;
				}

				if ($hotel_has_offers)
					$__QDATA->Hotels[] = $hotel;
			}
		}

		// flag geography that has travel items
		if ($__QDATA->Hotels)
			$this->flagHotelsTravelItems($__QDATA->Hotels);

		// update hotels status - flags like: HasChartersActiveOffers, HasPlaneChartersActiveOffers, HasBusChartersActiveOffers
		if ($__QDATA->Hotels)
		{
			\QApp::AddCallbackAfterResponseLast(function ($hotels, $type) {
				// sync hotels status
				\Omi\TFuse\Api\TravelFuse::UpdateHotelsStatus($hotels, $type);
			}, [$__QDATA->Hotels, "charter"]);
		}

		// if the request was not pulled from cache - attach the callback to refresh travel items cache data
		if (($tf_request_id = $params["__request_data__"]["ID"]) && \Omi\TFuse\Api\TravelFuse::Can_RefreshTravelItemsCacheData(static::$RequestOriginalParams) && 
			(!\Omi\TFuse\Api\TravelFuse::DataWasPulledFromCache($tf_request_id)))
		{
			\QApp::AddCallbackAfterResponseLast(function ($tf_request_id) {
				// refresh travel items cache data
				\Omi\TFuse\Api\Travelfuse::RefreshTravelItemsCacheData([
					"TravelfuseReqID" => $tf_request_id
				], false);
			}, [$tf_request_id]);	
		}

		return $__QDATA->Hotels;
	}

	public function saveBooking($data)
	{
		$order = qis_array($data) ? reset($data) : $data;		
		if (!$order || (!($order instanceof \Omi\Comm\Order)))
			throw new \Exception("Order not provided!");

		$params = [];
		$params["BookingName"] = $order->BookingReference;
		$params["BookingClientId"] = $order->getBookingClientId();

		if (!$order->OrderOffers || (count($order->OrderOffers) === 0))
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

		$bookingClient = "";
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
		
		$params["BookingItems"] = [];
		$offer = null;
		foreach ($order->OrderOffers as $orderOffer)
		{
			if (!($offer = $orderOffer->Offer))
				throw new \Exception("Offer not found for order!");

			/*
			$callOffer = ($callOffers && isset($callOffers[$orderOffer->Offer->Code])) ? $callOffers[$orderOffer->Offer->Code] : null;
			if (!$callOffer)
				throw new \Exception("Offer expired!");
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
			//$bookingItm[] = ["TourOpCode" => "P45"];

			$bookingItm[$itm] = [];
			$bookingItm[$itm][] = ["BookingAgent" => "{$bookingAgent},{$bookingAgency},{$bookingEmail}"];
			$bookingItm[$itm][] = ["BookingClient" => $bookingClient];
			if ($hotel)
			{
				if (!$orderOffer->Offer->PackageVariantId)
					throw new \Exception("Offer PackageVariantId not found!");
				
				if (!$orderOffer->Offer->PackageId)
					throw new \Exception("Offer PackageId not found!");

				if (!$hotel->Address)
					throw new \Exception("Address not found for hotel!");

				if (!$hotel->Address->City || !$hotel->Address->City->Country)
					throw new \Exception("Hotel destination not found!");

				$bookingItm[$itm][] = ["CountryCode" => $hotel->Address->City->Country->Code];
				$bookingItm[$itm][] = ["CityCode" => $hotel->Address->City->Code];
				$bookingItm[$itm][] = ["ProductCode" => $hotel->Code];
				$bookingItm[$itm][] = ["Language" => "RO"];
				$bookingItm[$itm][] = ["PeriodOfStay" => [
					["CheckIn" => $roomObj->OfferItem->CheckinAfter],
					["CheckOut" => $roomObj->OfferItem->CheckinBefore]
				]];

				$bookingItm[$itm][] = ["PackageId" => $orderOffer->Offer->PackageId];
				$bookingItm[$itm][] = ["VariantId" => $orderOffer->Offer->PackageVariantId];
			}
			else
			{
				if (!$orderOffer->Offer->UniqueId)
					throw new \Exception("Offer UniqueId not found!");

				//if (!$orderOffer->Offer->DepartureCharter)
				//	throw new \Exception("Departure charter not found!");
				
				$bookingItm[$itm][] = ["CircuitId" => $tour->Code];
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
						$room["PaxNames"][] = [
							"PaxName" => [
								"PaxType" => "adult", 
								"TGender" => $gender, 
								"Name" => $adult->Name,
								"Firstname" => $adult->Firstname,
								"DOB" => $adult->BirthDate, 
								[$adult->getFullName()]
							]
						];
					}
				}

				if ($hasChildren)
				{
					foreach ($roomObj->Info->Children as $child)
						$room["PaxNames"][] = [
							"PaxName" => [
								"PaxType" => "child", 
								"TGender" => "C", 
								"DOB" => $child->BirthDate, 
								"Name" => $child->Name,
								"Firstname" => $child->Firstname,
								[$child->getFullName()]
							]
						];
				}
			}
			$bookingItm[$itm][] = ["Rooms" => ["Room" => $room]];

			// add the booking item
			$params["BookingItems"][] = ["BookingItem" => $bookingItm];
		}

		$params["IndexOffer"] = $offer->ETripIndexOffer;
		$params["IndexMeal"] = $offer->ETripIndexMeal;

		$self = new static();
		$data = $this->SOAPInstance->MakeReservation($params, $self, $order);

		// $data = self::GetResponseData(self::Request(["AddBookingRequest", "CurrencyCode" => $order->Currency->Code], $params, true), "AddBookingResponse");
		if (!$data)
		{
			throw new \Exception("Rezervarea nu a putut fi procesata!");
		}

		if ($data->Reference)
			$order->InTourOperatorId = $data->Reference;

		$order->setInTourOperatorRef($data->Reference);
		$order->setInTourOperatorStatus($data->Status);

		$order->setApiOrderData(json_encode($data));

		// save data
		$order->save("InTourOperatorId, ApiOrderData, InTourOperatorRef, InTourOperatorStatus");

		$order->_top_response_message = "A fost efecutata comanda cu "
			. ($order->InTourOperatorRef ? "numarul [{$order->InTourOperatorRef}] si cu " : "")
			. "referinta [{$order->InTourOperatorId}].\n"
			. ($order->InTourOperatorStatus ? "Statusul comenzii este [{$order->InTourOperatorStatus}]" : "") . ".";

		// return the order
		return $order;
	}

	public static function CountObjects(\QIModel $model, int &$the_count = 0, $selector = true)
	{
		if ($model->_processed === 1)
			return;

		$model->_processed = 1;
		$the_count++;

		$selector_isarr = is_array($selector);
		if (!(($selector === true) || $selector_isarr))
			return;

		$is_collection = ($model instanceof \QIModelArray);

		if ($is_collection)
		{
			foreach ($model as $item)
			{
				if ($item instanceof \QIModel)
				{
					static::CountObjects($item, $the_count, $selector);
				}
			}
		}
		else
		{
			// prepare merge by

			$props = $model->getModelType()->properties;
			$loop_by_selector = ($selector_isarr && (count($selector_isarr) < count($props)));
			$loop_by = $loop_by_selector ? $selector : $props;
			
			if ($loop_by && $props)
			{
				foreach ($loop_by as $loop_k => $loop_v)
				{
					$prop = $loop_by_selector ? $props[$loop_k] : $loop_v;
					$p_name = $loop_k;
					// if we have a selector and not selected, skip
					if (($prop->storage["none"]) || ($selector_isarr && ($selector[$p_name] === null) && ($selector["*"] === null)))
						continue;
					
					$item = $model->{$p_name};
					if (($item instanceof \QIModel) && ($item->_processed !== 1))
					{
						static::CountObjects($item, $the_count, $selector);
					}
				}
			}
		}
	}

	public static function SetupOnLiveImages()
	{
		
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

	public static function GetRequests($child_storage, $from, $parameters)
	{
		if (($dynamicPackages = $parameters["dynamic_packages"]))
			return null;

		$isCharter = ($parameters["VacationType"] === "charter");
		$isTour = ($parameters["VacationType"] === "tour");
		$isIndividual = (($parameters["VacationType"] === "individual") && ($parameters["search_type"] !== "hotel"));
		$isHotel = (($parameters["VacationType"] === "hotel") || ($parameters["search_type"] === "hotel"));

		$forceIndivudalInSamePlace = (\Omi\App::HasIndividualInOnePlace() || $parameters["category"] || $parameters["custDestination"] || 
			$parameters["mainDestination"] || $parameters["dynamic_packages"] || $parameters["for_dynamic_packages"]);

		if ($isHotel && (!$forceIndivudalInSamePlace))
			return null;
		$SEND_TO_SYSTEM = $parameters["__send_to_system__"];
		$filterIndx = null;
		if ($SEND_TO_SYSTEM)
		{
			if (!($bookingTravelItemID = $parameters["__travel_itm__"]))
				throw new \Exception("\$bookingTravelItemID not found!");
			$filterIndx = $parameters["__USE_REQ_INDX__"];
		}
		$tourOperator = $child_storage->TourOperatorRecord;
		$params = static::GetRequestsDefParams($parameters);
		$bookingTravelItem = null;
		$travelItms = null;
		// if we have product code
		if ($parameters["ProductCode"])
		{
			// if we don't have the product in current tour operator - we don't have requests
			if ((!($travelItms = static::GetTravelItems($parameters["ProductCode"], $tourOperator, $parameters["VacationType"]))) || (count($travelItms) === 0))
				return null;
			if ($SEND_TO_SYSTEM)
			{
				$filteredTravelItems = [];
				foreach ($travelItms ?: [] as $travelItm)
				{
					if ($travelItm->getId() == $bookingTravelItemID)
					{
						$filteredTravelItems[] = $travelItm;
						$bookingTravelItem = $travelItm;
						break;
					}
				}
				$travelItms = $filteredTravelItems;
			}
		}
		if ((!$isTour) && (!($destinationID = ($parameters["CityCode"] ?: $parameters["Destination"]))))
		{
			//qvardump("\$parameters", $parameters);
			//throw new \Exception("Destination cannot be determined!");
		}
		$checkIn = date("Y-m-d", strtotime($parameters["CheckIn"]));
		$checkOut = date("Y-m-d", strtotime($parameters["CheckOut"]));
		$_isCounty = ($parameters && ($parameters["DestinationType"] == "county"));
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
		$params["PeriodOfStay"] = [
			["CheckIn" => $checkIn],
			["CheckOut" => $checkOut],
		];

		// it does not support other currency than default
		#if (!($requestCurrencyCode = $child_storage->getRequestCurrency($parameters)))
		#	$requestCurrencyCode = static::$DefaultCurrency;
		#$params["CurrencyCode"] = $requestCurrencyCode;
		//$params["CurrencyCode"] = static::$DefaultCurrency;
		#$params["SellCurrency"] = $child_storage->getSellCurrency($parameters);

		$filterRequestsForTravelItems = static::QFilterRequestsForTravelItems($tourOperator, $parameters);

		$reqs = [];
		// SETUP INDIVIDUAL REQUESTS
		if ($isIndividual || $isHotel)
		{
			$_qparams = [
				($_isCounty ? "County" : "City") => $destinationID,
				"TourOperator" => $tourOperator->getId(),
				"Active" => true
			];
			$from = (($forceIndivudalInSamePlace || $parameters["for_dynamic_packages"]) ? "AllIndividualTransports" : "IndividualTransports");
			$_cachedT = QQuery($from . ".{"
				. "TourOperator.Caption,"
				. "To.{"
					. "City.{*, "
						. "County.{*, TourOperator, Master.Name},"
						. "Master.{"
							. "Name,"
							. "County.Name"
						. "},"
						. "InTourOperatorId, "
						. "TourOperator.Caption, "
						. "IsMaster, "
						. "Country.{"
							. "Name, "
							. "Code, "
							. "Alias, "
							. "InTourOperatorsIds.{"
								. "TourOperator, "
								. "Identifier"
							. "}"
						. "}"
					. "}"
				. "}"
				. " WHERE 1 "
					. "??Active?<AND[Content.Active=?]"
					. "??TourOperator?<AND[TourOperator.Id=?]"
					. "??County?<AND[To.{City.{(County.Id=? OR County.Master.Id=? OR Master.County.Id=?)}}]"
					. "??City?<AND[To.{City.{(Id=? OR Master.Id=?)}}]"
				. " GROUP BY Id "
			. "}", $_qparams)->{$from};
			
			if ($_cachedT)
			{
				$__toSetupReqs = [];
				// if the search is on county then we need to use zones
				$_USE_ZONES = $_isCounty;
				foreach ($_cachedT as $_ct)
				{
					$city = $_ct->To ? $_ct->To->City : null;
					// we must have city for request
					if (!$city)
						continue;
					/*
					if ($SEND_TO_SYSTEM && (!$filterIndx) && (!($bookingTravelItem && $bookingTravelItem->Address && $bookingTravelItem->Address->City && 
						($bookingTravelItem->Address->City->InTourOperatorId == $city->InTourOperatorId))))
					{
						// if we send to system filter by booking travel item destination
						continue;
					}
					*/
					// we may be in exception case 1 or 3 where, we may have a city dettached to its county and we need to do single request on only the city
					// use zone for request if:
					// - we globally use zones
					// - we have county
					// - we don't have city master or we don't have city master county or we don't have city county master or if they are different
					$_USE_ZONE_FOR_REUQEST = ($_USE_ZONES && $city->County && ((!$city->Master) || (!$city->Master->County) || (!$city->County->Master) ||
						($city->Master->County->getId() == $city->County->Master->getId()) || ($city->Master->County->getId() != $destinationID)
					));

					$to = ($_USE_ZONE_FOR_REUQEST && $city->County) ? $city->County : $city;

					if (!$to->InTourOperatorId)
						continue;

					$requestPassedTravelItemsFilter = static::QRequestPassedTravelItemsFilter($filterRequestsForTravelItems, $travelItms, $to);
					if (!$requestPassedTravelItemsFilter)
						continue;

					$_req_cache_indx = get_class($to) . "~" . $to->InTourOperatorId;
					// ids are unique in ETRIP
					//$_req_cache_indx = $to->InTourOperatorId;
					if (!($_creq_indx = $__toSetupReqs[$_req_cache_indx]))
					{
						// copy params that we have so far
						$__params = $params;
						unset($__params["Zone"]);
						unset($__params["CityCode"]);
						$__params[($to instanceof \Omi\County) ? "Zone" : "CityCode"] = $to->InTourOperatorId;
						if ($city->Country && ($identif = $city->Country->getTourOperatorIdentifier($tourOperator)))
							$__params["CountryCode"] =  $identif;
						unset($__params["__CACHED_TRANSPORTS__"]);
						ksort($__params);
						$indx = json_encode($__params);
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
					}
				}
			}
		}
		else if ($isTour)
		{
			//$params["CurrencyCode"] = static::$DefaultCurrency;
			// if we don't have departure we don't have requests
			$departures = static::GetTourOperatorDepartures($parameters["DepCityCode"], $tourOperator);
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
					. "Country.{"
						. "Name, "
						. "Code, "
						. "Alias, "
						. "InTourOperatorsIds.{"
							. "TourOperator, "
							. "Identifier"
						. "}"
					. "}, "
					. "Destination.{"
						. "Name, "
						. "InTourOperatorId, "
						. "TourOperator.Caption, "
						. "IsMaster"
					. "},"
					. "County.{"
						. "Name, "
						. "InTourOperatorId, 
						TourOperator, "
						. "Country.{"
							. "Code, "
							. "Alias, "
							. "InTourOperatorsIds.{"
								. "TourOperator, "
								. "Identifier"
							. "}"
						. "}"
					. "},"
					. "City.{"
						. "Name, "
						. "InTourOperatorId, "
						. "TourOperator.Caption, "
						. "IsMaster, "
						. "County.{
							InTourOperatorId, 
							TourOperator, "
							. "Country.{"
								. "Code, "
								. "Name, "
								. "Alias, "
								. "InTourOperatorsIds.{"
									. "TourOperator, "
									. "Identifier"
								. "}"
							. "}"
						. "},"
						. "Country.{"
							. "Name, "
							. "Code, "
							. "Alias, "
							. "InTourOperatorsIds.{"
								. "TourOperator, "
								. "Identifier"
							. "}"
						. "}"
					. "}"
				. "},"
				. "Dates.{Date, "
					. "Nights.{"
						. "Nights "
						. "WHERE 1 "
							. "??Active?<AND[Active=?]"
					. "}"
					. "WHERE 1 "
						. "??MonthAndYear?<AND[CONCAT(YEAR(Date), '-', MONTH(Date))=?]"
						//. "??Active?<AND[Active=?]"
				. "} "
				. " WHERE 1 "
					//. "??Active?<AND[Content.Active=?]"
					. "??Active?<AND[Dates.Nights.Active=?]"
					. "??TourOperator?<AND[TourOperator.Id=?]"
					. "??TransportType?<AND[TransportType=?]"
					. "??From?<AND[From.City.{(Id=? OR Master.Id=?)}]"
					. "??Country?<AND[To.{(City.Country.Id=? OR Country.Id=?)}]"
					. "??Destination?<AND[To.Destination.{(Id=? OR Master.Id=?)}]"
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
					$__params["Transport"] = $parameters["Transport"];
					$__toSetupReqs = [];
					foreach ($_cachedT as $_ct)
					{
						if (!$_ct->To || ((!$_ct->To->Destination) && (!$_ct->To->City) && (!$_ct->To->County) && (!$_ct->To->Country)))
							continue;
						$to = $_ct->To->Destination ?: ($_ct->To->City ? ($_ct->To->City->County ?: $_ct->To->City) : ($_ct->To->County ?: $_ct->To->Country));
						if ((!$_ct->Dates) || (!$to))
							continue;
						$dest_indx = ($_is_country = ($to instanceof \Omi\Country)) ? $to->getTourOperatorIdentifier($tourOperator) : $to->InTourOperatorId;

						if (!$dest_indx)
							continue;

						/*
						if ($SEND_TO_SYSTEM && (!$filterIndx))
						{
							if (!($tourDest = (($bookingTravelItem && $bookingTravelItem->Location) ? 
								($bookingTravelItem->Location->City ?: ($bookingTravelItem->Location->Destination ?: 
								($bookingTravelItem->Location->Country ?: $bookingTravelItem->Location->County))) : null)))
							{
								// if we send to system filter by booking travel item destination
								continue;
							}
							if ((!($tourDestIndx = (($tourDest instanceof \Omi\Country) ? $tourDest->getTourOperatorIdentifier($tourOperator) : $tourDest->InTourOperatorId))) || 
								($tourDestIndx != $dest_indx))
								continue;
						}
						*/
						$countryTOPId = ($to && $to->Country) ? $to->Country->getTourOperatorIdentifier($tourOperator) : null;
						foreach ($_ct->Dates ?: [] as $dateObj)
						{
							if (!$dateObj->Date || !$dateObj->Nights)
								continue;
							$dtime = strtotime($dateObj->Date);
							$_fdate = date("Y-m-d", $dtime);
							if (($SEND_TO_SYSTEM && (!$filterIndx)) && ($_fdate != $parameters["CheckIn"]))
							{
								continue;
							}
							foreach ($dateObj->Nights ?: [] as $_nightObj)
							{
								$_night = $_nightObj->Nights;
								if (($SEND_TO_SYSTEM && (!$filterIndx)) && ($_night != $parameters["Duration"]))
								{
									continue;
								}
								// copy initial params
								$_req_cache_indx = $dest_indx . "~" . $dtime . "~" . $_night;
								$req_uniq_idf = null;
								if (!($_creq_indx = $__toSetupReqs[$_req_cache_indx]))
								{
									$__params["PeriodOfStay"] = [
										["CheckIn" => date("Y-m-d", $dtime)],
										["CheckOut" => date("Y-m-d", strtotime("+ {$_night} days ", $dtime))]
									];
									unset($__params["Zone"]);
									unset($__params["CityCode"]);
									unset($__params["Destination"]);
									$__params[($to instanceof \Omi\County) ? "Zone" : (($to instanceof \Omi\TF\Destination) ? "Destination" : "CityCode")] = $dest_indx;
									if ($countryTOPId)
										$__params["CountryCode"] =  $countryTOPId;
									unset($__params["__CACHED_TRANSPORTS__"]);
									ksort($__params);
									$indx = json_encode($__params);
									$__params["__CACHED_TRANSPORTS__"] = [$_ct->getId() => $_ct->getId()];
									$reqs[$indx] = $__params;
									$__toSetupReqs[$_req_cache_indx] = $indx;
									$_creq_indx = $indx;
								}
								if (!$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"])
									$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"] = [];
								$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"][$_ct->getId()] = $_ct->getId(); 
								if (!static::$RequestsData[$_creq_indx])
									static::$RequestsData[$_creq_indx] = [];
								static::$RequestsData[$_creq_indx]["Departure"] = $departure;
								static::$RequestsData[$_creq_indx]["Destination"] = $to;
							}
						}
					}
				}
			}
		}
		// SETUP CHARTERS REQUESTS
		else if ($isCharter)
		{
			// if we don't have departure we don't have requests
			$departures = static::GetTourOperatorDepartures($parameters["DepCityCode"], $tourOperator);			
			$interval = date_diff(date_create($checkOut), date_create($checkIn));
			$_qparams = [
				//($_isCounty ? "County" : "City") => $destinationID,
				($_isCounty ? ($destination->IsMaster ? "MasterCounty" : "County") : ($destination->IsMaster ? "MasterCity" : "City")) => $destinationID,
				"From" => $parameters["DepCityCode"],
				"TourOperator" => $tourOperator->getId(),
				"TransportType" => $parameters["Transport"],
				"DateAndDuration" => [$checkIn." 00-00-00", (int)$interval->format('%a')],
				"Active" => true
			];
			$_cachedT = QQuery("ChartersTransports.{"
				. "TourOperator.Caption,"
				. "To.{"
					. "City.{*, "
						. "County.{*, TourOperator, Master.Name},"
						. "TourOperator.Caption, "
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
				//. " GROUP BY Id "
			. "}", $_qparams)->ChartersTransports;
			
			// if the search is on county then we need to use zones
			$_USE_ZONES = $_isCounty;
			if ($_cachedT)
			{
				foreach ($departures ?: [] as $departure)
				{
					$__params = $params;
					// setup departure in params
					$__params["DepCityCode"] = $departure->InTourOperatorId;
					if (($departure->Country && ($identif = $departure->Country->getTourOperatorIdentifier($tourOperator))))
						$__params["DepCountryCode"] = $identif;
					$__params["Days"] = 0;
					$__params["Transport"] = $parameters["Transport"];
					$__toSetupReqs = [];
					foreach ($_cachedT as $_ct)
					{
						// we must have city for request
						if (!($city = $_ct->To ? $_ct->To->City : null))
							continue;
						/*
						if ($SEND_TO_SYSTEM && (!$filterIndx) && (!($bookingTravelItem && $bookingTravelItem->Address && $bookingTravelItem->Address->City && 
							($bookingTravelItem->Address->City->InTourOperatorId == $city->InTourOperatorId))))
						{
							// if we send to system filter by booking travel item destination
							continue;
						}
						*/
						// if we have county we must filter results
						if ($_isCounty)
						{
							$passed = false;
							if (
								(
									(!$destination->IsMaster) && 
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
										((!$city->Master) && $city->County && $city->County->Master && ($city->County->Master->getId() === $destination->getId())) || 
										($city->Master && $city->Master->County && $city->Master->County && ($city->Master->County->getId() === $destination->getId()))
									)
								)
							)
								$passed = true;
							if (!$passed)
								continue;
						}
						// we may be in exception case 1 or 3 where, we may have a city dettached to its county and we need to do single request on only the city
						// use zone for request if:
						// - we globally use zones
						// - we have county
						// - we don't have city master or we don't have city master county or we don't have city county master or if they are different
						$forceReqOnCities_Explicit = ($city->County && $city->County->InTourOperatorId && 
							isset(static::$Config[$tourOperator->Handle]["force_reqs_on_cities"][$city->County->InTourOperatorId]));
						$forceReqOnZone = ($_USE_ZONES && isset(static::$Config[$tourOperator->Handle]['force_reqs_on_county'][$city->County->InTourOperatorId]));
						$_USE_ZONE_FOR_REUQEST = ($forceReqOnZone || (!$forceReqOnCities_Explicit && $_USE_ZONES && $city->County && 
							(
								(!$city->Master) || 
								(!$city->Master->County) || 
								(!$city->County->Master) ||
								($city->Master->County->getId() == $city->County->Master->getId()) || 
								($city->Master->County->getId() != $destinationID)
							)
						));

						$to = ($_USE_ZONE_FOR_REUQEST && $city->County) ? $city->County : $city;
						if (!$to->InTourOperatorId)
							continue;

						$requestPassedTravelItemsFilter = static::QRequestPassedTravelItemsFilter($filterRequestsForTravelItems, $travelItms, $to);
						if (!$requestPassedTravelItemsFilter)
							continue;
						
						$_req_cache_indx = get_class($to) ."~" . $to->InTourOperatorId;
						// ids are unique in ETRIP
						//$_req_cache_indx = $to->InTourOperatorId;
						$req_uniq_idf = null;
						if (!($_creq_indx = $__toSetupReqs[$_req_cache_indx]))
						{
							// copy params that we have so far
							unset($__params["Zone"]);
							unset($__params["CityCode"]);
							$__params[($to instanceof \Omi\County) ? "Zone" : "CityCode"] = $to->InTourOperatorId;
							if ($city->Country && ($identif = $city->Country->getTourOperatorIdentifier($tourOperator)))
								$__params["CountryCode"] =  $identif;
							unset($__params["__CACHED_TRANSPORTS__"]);
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

						if (!static::$RequestsData[$req_uniq_idf])
							static::$RequestsData[$req_uniq_idf] = [];
						static::$RequestsData[$req_uniq_idf]["Departure"] = $departure;
						static::$RequestsData[$req_uniq_idf]["Destination"] = $to;

					}
				}
			}
		}

		// set the req indx
		foreach ($reqs ?: [] as $indx => $req)
		{
			//echo $indx . md5(trim($indx)) . " <br/>";
			$reqs[$indx]["__REQ_INDX__"] = md5(trim($indx));
			if (!($reqCurrency = $child_storage->getRequestCurrency($parameters)))
				$reqCurrency = static::$DefaultCurrency;
			$reqs[$indx]["CurrencyCode"] = $reqCurrency;
			$reqs[$indx]["RequestCurrency"] = $reqCurrency;
			$reqs[$indx]["SellCurrency"] = $child_storage->getSellCurrency($parameters);				
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

		if ($SEND_TO_SYSTEM && $filterIndx)
		{
			$useReqs = [];
			foreach ($reqs ?: [] as $reqIndx => $req)
			{
				//echo $reqIndx . " || " . md5($reqIndx) . " || " . $req["__REQ_INDX__"] . "<br/>";
				if ($filterIndx == $req["__REQ_INDX__"])
					$useReqs[$reqIndx] = $req;
			}

			$reqs = $useReqs;
			if (($c_reqs = count($reqs)) !== 1)
			{
				#throw new \Exception("Err: when the booking is send to the tour operator system only one request must be executed ({$c_reqs})!");
				$reqs = [];
			}
		}

		return $reqs;
	}
	
	/*=================================================================EXEC RAW REQUESTS====================================================*/
	/*
	 *  THIS SECTION NEEDS TO BE IMPLEMENTED IN EACH TOUR OPERATOR
	 */
	/*======================================================================================================================================*/

	public static function ExecCharterRawRequest($storageHandle, $departureCountryCode, $departureCityCode, 
		$countryCode, $zoneCode, $cityCode, $checkIn, $duration, $transportType = "plane", $rooms = [], $currencyCode = "EUR")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiCode, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword))
			throw new \Exception("Storage not configured [{$storageHandle}]!");
		
		$url = $storage->ApiUrl;
		$user = $storage->ApiUsername;
		$pass = $storage->ApiPassword;
		
		if (empty($rooms))
		{
			$rooms = [
				"Room" => [
					"Adults" => 2,
					"Children" => 0,
					"ChildrenAges" => []
				]
			];
		}
		
		foreach ($rooms ?: [] as $k => $room)
		{
			$rooms[$k]["ChildAges"] = $room["ChildrenAges"];
			unset($rooms[$k]["Children"]);
			unset($rooms[$k]["ChildrenAges"]);
		}

		$params = [
			"Destination" => $cityCode ?: ($zoneCode ?: $countryCode),
			"IsTour" => false,
			"IsFlight" => ($transportType == "plane"),
			"IsBus" => ($transportType == "bus"),
			"DepartureDate" => $checkIn,
			"Departure" => $departureCityCode,
			"Duration" => $duration,
			"Rooms" => $rooms,
			"MinStars" => 0,
			"ShowBlackedOut" => true,
			"Currency" => $currencyCode,
		];

		if (($apiCode = ($storage->ApiCode__ ?: $storage->ApiCode)))
			$params["AgentCode"] = $apiCode;

		$t1 = microtime(true);
		$soapObj = null;
		try
		{
			$soapClientOptions = ["login" => $user, "password" => $pass, "trace" => 1];
			list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($storage);
			if ($proxyUrl)
				$soapClientOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
			#if ($proxyPort)
			#	$soapClientOptions["proxy_port"] = $proxyPort;
			if ($proxyUsername)
				$soapClientOptions["proxy_login"] = $proxyUsername;
			if ($proxyPassword)
				$soapClientOptions["proxy_password"] = $proxyPassword;
			$soapObj = new \Omi\Util\SoapClient_Wrap($url, $soapClientOptions);
			$res = $soapObj->PackageSearch($params);
		} catch (\Exception $ex) {

			qvardump(
				"PackageSearch",
				$params,
				$ex->getMessage(),
				$ex->getLine(),
				$ex->getFile(),
				$ex->getTraceAsString()
			);
		}

		if ($soapObj)
			var_dump($soapObj->__getLastRequestHeaders(), $soapObj->__getLastRequest(), $soapObj->__getLastResponseHeaders(), $soapObj->__getLastResponse());

		qvardump($res, ((microtime(true) - $t1) . " seconds"));
	}

	public static function ExecTourRawRequest($storageHandle, $departureCountryCode, $departureCityCode, 
		$countryCode, $zoneCode, $cityCode, $checkIn, $duration, $transportType = "plane", $rooms = [], $currencyCode = "EUR")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiCode, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword))
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

		foreach ($rooms ?: [] as $k => $room)
		{
			$rooms[$k]["ChildAges"] = $room["ChildrenAges"];
			unset($rooms[$k]["Children"]);
			unset($rooms[$k]["ChildrenAges"]);
		}

		$params = [
			"Destination" => $cityCode ?: ($zoneCode ?: $countryCode),
			"IsTour" => true,
			"IsFlight" => ($transportType == "plane"),
			"IsBus" => ($transportType == "bus"),
			"DepartureDate" => $checkIn,
			"Departure" => $departureCityCode,
			"Duration" => $duration,
			"Rooms" => $rooms,
			"MinStars" => 0,
			"Currency" => $currencyCode,
			"ShowBlackedOut" => true
		];

		if (($apiCode = ($storage->ApiCode__ ?: $storage->ApiCode)))
			$params["AgentCode"] = $apiCode;

		$soapClientOptions = ["login" => $user, "password" => $pass, "trace" => 1];
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($storage);
		if ($proxyUrl)
			$soapClientOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$soapClientOptions["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$soapClientOptions["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$soapClientOptions["proxy_password"] = $proxyPassword;

		$soapObj = new \Omi\Util\SoapClient_Wrap($url, $soapClientOptions);

		$resp = $soapObj->PackageSearch($params);
		var_dump($params, $resp, $url);
	}

	public static function ExecIndividualRawRequest($storageHandle, $countryCode, $zoneCode, $cityCode, $checkIn, $checkOut, $rooms = [], $currencyCode = "EUR")
	{
		$storage = \QQuery("Storages.{ApiUrl, ApiCode, ApiContext, ApiUsername, ApiPassword WHERE Handle=? LIMIT 1}", $storageHandle)->Storages[0];
		if ((!$storage) || (!$storage->ApiUrl) || (!$storage->ApiUsername) || (!$storage->ApiPassword))
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
		
		foreach ($rooms ?: [] as $k => $room)
		{
			$rooms[$k]["ChildAges"] = $room["ChildrenAges"];
			unset($rooms[$k]["Children"]);
			unset($rooms[$k]["ChildrenAges"]);
		}
		
		$interval = date_diff(date_create($checkIn), date_create($checkOut));

		$params = [
			"Destination" => ($cityCode ?: ($zoneCode ?: $countryCode)), 
			"CheckIn" => $checkIn, 
			"Stay" => (int)$interval->format('%a'),
			"Rooms" => $rooms,
			"Currency" => $currencyCode,
			"MinStars" => 0,
			"ForPackage" => false,
			"PricesAsOf" => null,
			"ShowBlackedOut" => true
		];

		if (($apiCode = ($storage->ApiCode__ ?: $storage->ApiCode)))
			$params["AgentCode"] = $apiCode;
		$soapClientOptions = ["login" => $user, "password" => $pass, "trace" => 1];
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($storage);
		if ($proxyUrl)
			$soapClientOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$soapClientOptions["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$soapClientOptions["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$soapClientOptions["proxy_password"] = $proxyPassword;
		$soapObj = new \Omi\Util\SoapClient_Wrap($url, $soapClientOptions);

		$res = $soapObj->HotelSearch($params);
		qvardump($res);
	}

	public static function FixTours_ToM2m()
	{
		$mysqli = \QApp::GetStorage()->connection;

		$res = $mysqli->query("SELECT `Hotels`.`\$id` AS `hotel_id`, `Hotels`.`\$Tour` AS `tour` FROM 
			`Hotels`
				JOIN `ApiStorages` ON (`Hotels`.`\$TourOperator`=`ApiStorages`.`\$id`)
				JOIN `Merch` ON (`Hotels`.`\$Tour`=`Merch`.`\$id`)
				LEFT JOIN 
					`Hotels_Tours` ON (`Hotels`.`\$id`=`Hotels_Tours`.`\$Hotel` AND `Hotels_Tours`.`\$Tour`=`Merch`.`\$id`)
		WHERE 
			`ApiStorages`.`StorageClass`='Omi\\\\TF\\\\ETrip'  AND ISNULL(`Hotels_Tours`.`\$id`);");

		if (!$res)
			throw new \Exception("Structure not yet ok!");

		if ($res->num_rows === 0)
			return false;

		$query = "INSERT INTO `Hotels_Tours` (`\$Hotel`, `\$Tour`) VALUES ";
		$pos = 0;
		while (($r = $res->fetch_assoc()))
		{
			$query .= (($pos > 0) ? ", " : "") . "({$r['hotel_id']}, {$r['tour']})";
			$pos++;
		}
		$query .= ";";

		$r = $mysqli->query($query);

		if ($r === false)
			throw new \Exception($mysqli->error);

		return true;
	}
	
	public function refresh_CacheData(\Omi\TF\CacheFileTracking $cacheDataRecord, \QModelArray $nightsObjs, string $cacheDataContent, bool $showDiagnosis = false)
	{	
		$dec_data = $this->SOAPInstance->simpleXML2Array(simplexml_load_string($cacheDataContent), true);
		$data = json_decode(json_encode($dec_data));

		$packages = isset($data->Body->PackageSearchResponse->PackageSearchResponse) ? $data->Body->PackageSearchResponse->PackageSearchResponse : [];
		
		if (empty($packages))
			return;

		$tours = ($cacheDataRecord->VacationType === "tour");

		list($ehotelsByTopIds, $eToursByTopIds) = static::PullCacheData_ProcessHotelsAndTours($this, $packages, $tours);

		$params = $cacheDataRecord->RequestData ? json_decode($cacheDataRecord->RequestData, true) : [];
		
		# Added by Alex S. - fixing missing hotels ids (no longer available)
		{
			foreach ($nightsObjs ?: [] as $nobj)
			{
				foreach ($packages ?: [] as $p)
				{
					$tp_hotel_in_top_id = $p->HotelInfo->HotelId;
					if (!$tp_hotel_in_top_id)
						continue;
					if (!($hotel = $ehotelsByTopIds[$tp_hotel_in_top_id]))
						continue;
					$nobj->_hotels_ids[$hotel->getId()] = $hotel->getId();
				}
			}
		}
		# Added by Alex S. - fixing missing hotels ids (no longer available)
		{
			foreach ($nightsObjs ?: [] as $nobj)
			{
				foreach ($packages ?: [] as $p)
				{
					$tourInTopId = ($p->Id ?: $p->PackageId);
					if (!$tourInTopId)
						continue;
					if (!($tour = $eToursByTopIds[$tourInTopId]))
						continue;
					$nobj->_tours_ids[$tour->getId()] = $tour->getId();
				}
			}
		}

		$hotelsNightsObjs = [];
		foreach ($nightsObjs ?: [] as $nobj)
		{
			foreach ($nobj->_hotels_ids ?: [] as $hid)
				$hotelsNightsObjs[$hid][] = $nobj;
		}

		$toursNightsObjs = [];
		foreach ($nightsObjs ?: [] as $nobj)
		{
			foreach ($nobj->_tours_ids ?: [] as $tid)
				$toursNightsObjs[$tid][] = $nobj;
		}

		$toCacheData = [];
		foreach ($packages ?: [] as $p)
		{
			if ($p->HotelInfo->Source)
			{
				$hidparts = (strrpos($p->HotelInfo->HotelId, '|') !== false) ? explode('|', $p->HotelInfo->HotelId) : [];
				$hasSource = ((count($hidparts) > 1) && (end($hidparts) == $p->HotelInfo->Source));
				if (!$hasSource)
					$p->HotelInfo->HotelId .= "|" . $p->HotelInfo->Source;
			}

			$hotel = $ehotelsByTopIds[($hotelId = $p->HotelInfo->HotelId)];
			$tour = $tours ? $eToursByTopIds[($tourId = ($p->Id ?: $p->PackageId))] : null;
			if ((!$hotel) || ($tours && (!$tour)))
			{
				if ($showDiagnosis)
					echo "<div style='color: red;'>Skip " . ($tours ? ' tour [' . $tourId . ']' : ' hotel [' . $hotelId . ']') . " because it was not found in db</div>";
				continue;
			}

			$toProcessNightsObjs = $tours ? $toursNightsObjs[$tour->getId()] : $hotelsNightsObjs[$hotel->getId()];
			foreach ($toProcessNightsObjs ?: [] as $nightsObj)
			{
				try
				{
					$this->setupTransportsCacheData_topCust($toCacheData, $nightsObj, $p, $tours, $hotel, $tour, $params, null, $showDiagnosis);
				}
				catch (\Exception $ex)
				{
					// if can't cache data then just continue
					if ($showDiagnosis)
						echo "<div style='color: green;'>Cannot do the cache [{$ex->getMessage()}]</div>";
					throw $ex;
				}
			}
		}

		// save transports cache data
		$this->saveTransportsCacheData($toCacheData, $tours, $cacheDataRecord->TravelfuseReqID, $cacheDataRecord->CacheReqID, $cacheDataRecord->CacheFileRealPath);
	}
}