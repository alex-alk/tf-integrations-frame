<?php

namespace Omi\TF;

/**
 * Description of ETrip
 *
 */
class Solvex extends \QStorageEntry implements \QIStorage
{
	use TOStorage, SolvexIndividual, SolvexReservation, TOStorage_CacheAvailability, TOInterface_Util {
        TOInterface_Util::KSortTree insteadof TOStorage;
		SolvexIndividual::saveHotels insteadof TOStorage_CacheAvailability;
    }

	const Availability_Yes = 1;

	const Availability_Ask = 0;

	const Availability_No = 2;

	public static $Exec = false;

	public static $RequestOriginalParams = null;

	public static $RequestData = null;

	public static $RequestsData = [];

	private static $_CacheData = [];

	private static $_LoadedCacheData = [];

	public static $Config = [];

	private static $_Facilities = [];

	private static $SaveCityIfNotFound = true;

	public static $DefaultCurrency = "EUR";

	public function __construct($name = null, \QStorageFolder $parent = null) 
	{
		$this->SOAPInstance = new SolvexApi();
		return parent::__construct($name, $parent);
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

	public function testConnectivity()
	{
		return $this->SOAPInstance->testConnectivity();
	}

	public function initTourOperator($config = [])
	{
		$this->syncStaticData($config);
	}

	public function syncStaticData($config = [])
	{
		$this->resyncCountries();
		$this->saveCounties();
		$this->saveCities();
		$this->saveAllHotels();
	}
	
	public function cacheTopCountries($config = [])
	{
		$this->resyncCountries($config);
	}
	
	public function cacheTOPRegions($config = [])
	{
		$this->saveCounties($config);
	}
	
	public function cacheTOPCities($config = [])
	{
		$this->saveCities($config);
	}
	
	public function cacheTopHotels($config = [])
	{
		$this->saveAllHotels($config);
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

		if ((!$self) || (get_class($self) != get_called_class()) || (!$self->ApiPassword) || (!$self->ApiUsername) || (!$self->ApiUrl))
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
					$ret = $self->GetHotelAvailability($parameters, static::$RequestOriginalParams);
				break;
			}
			case "Countries" :
			{
				$ret = $self->getCountries();
				break;
			}
			case "Cities" :
			{
				$ret = $self->getCities($parameters);
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

				list($installments, $fees) = 
					\Omi\TFuse\Api\TravelFuse::MergePaymentsAndCancelFees($installments, $fees, $fees_params["Price"], $fees_params["CheckIn"], $self, $fees_params, $installments_params);

				$ret = [$fees, $installments];
				break;
			}
			case "TourFeesAndInstallments" :
			{
				$ret = null;
				break;
			}
			case "HotelFees" : 
			case "TourFees" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");

				$fees = null;
				if (!$self->TourOperatorRecord->HasCancelFees)
				{
					$parameters["ApiProvider"] = $self->TourOperatorRecord->getId();
					$fees = \QApi::Call("\Omi\Comm\Offer\CancelFee::GetPartnerFees", $parameters);
				}

				// setup default cancel fees - if the case
				if (static::NoFees($fees))
					$fees = static::GetDefaultFees($parameters);
				
				$ret = $fees;
				
				break;
			}
			case "Installments" :
			case "TourInstallments" :
			{
				if (!$parameters)
					throw new \Exception("Paramters are mandatory!");
				if ($self->TourOperatorRecord && !$self->TourOperatorRecord->HasInstallments)
				{
					$parameters["ApiProvider"] = $self->TourOperatorRecord->getId();
					$ret = \QApi::Call("\Omi\Comm\Payment::GetPartnerInstallments", $parameters);
				}
				break;
			}
			case "Circuits" :
			{
				break;
			}
			case "Hotels" :
			{
				throw new \Exception("Should not be !");
			}
			case "IndividualOffers" :
			case "HotelsWithIndividualOffers" :
			{
				$ret = $self->GetHotelAvailability($parameters, static::$RequestOriginalParams);
				break;
			}
			/**
			 * This is the real Charters request
			 */
			case "PackagesOffers" :
			case "HotelsWithPackages":
			{
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
				return $self->saveAllHotels();
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
		}
	}
	
	public function saveOrdersUpdates()
	{
		// do nothing for now
	}
	/**
	 * 
	 */
	public function setupCachedData()
	{
		// save hotels
		$this->saveAllHotels();
	}
	
	/**
	 * Returns an array with countries
	 * 
	 * @param array $objs
	 * @return array
	 */
	public function getCountries(&$objs = null)
	{
		if (!$objs)
			$objs = [];
		\Omi\TF\TOInterface::markReportStartpoint($objs, 'countries');

		$data = $this->SOAPInstance->getCountries();
	
		if ((!$data) || (q_count($data) === 0))
		{
			\Omi\TF\TOInterface::markReportQuickError($filter, 'No countries provided by tour operator');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			return;
		}

		\Omi\TF\TOInterface::markReportData($objs, 'Count countries: %s', [q_count($data)]);

		$mappedCountries = [
			"BULGARIA"				=> "Bulgaria",
			"SWITZERLAND"			=> "Switzerland",
			"TURKEY"				=> "Turkey",
		];

		$countries = new \QModelArray();
		foreach ($data as $cd)
		{
			\Omi\TF\TOInterface::markReportData($objs, 'Process country: %s', [$cd->ID . ' ' . $cd->Name], 50);

			$cd_name = $mappedCountries[$cd->Name] ?: $cd->Name;
			if (!$cd_name)
			{
				\Omi\TF\TOInterface::markReportData($objs, 'Country does not have a name: %s', [json_encode($cd)], 50, true);
				continue;
			}

			$country = $this->FindExistingItem("Countries", $cd->ID, "Name, InTourOperatorsIds.{TourOperator, Identifier}");

			if (!$country)
			{
				\Omi\TF\TOInterface::markReportData($objs, 'Country not found in db by InTourOperatorId: %s', [$cd->ID . ' ' . $cd->Name], 50, true);
				$__cb = [];
				if (($_bind = trim($cd->Name)))
				{
					$__cb[] = $_bind;
					$__cb[] = $_bind;
				}

				$qctrs = \QQuery("Countries.{Name, Alias, InTourOperatorsIds.{TourOperator, Identifier} WHERE (Name=? OR Alias=?)}", $__cb)->Countries;
				$country = (q_count($qctrs) == 1) ? q_reset($qctrs) : null;
				if (!$country)
				{
					\Omi\TF\TOInterface::markReportData($objs, 'Country not found in db by name eighter: %s', [$cd->ID . ' ' . $cd->Name], 50, true);
					continue;
				}

				if (!$country->InTourOperatorsIds)
					$country->InTourOperatorsIds = new \QModelArray();

				$apiStorageId = new \Omi\TF\ApiStorageIdentifier();
				$apiStorageId->setFromTopAddedDate(date("Y-m-d H:i:s"));
				$apiStorageId->setTourOperator($this->TourOperatorRecord);
				$apiStorageId->setIdentifier($cd->ID);
				$country->InTourOperatorsIds[] = $apiStorageId;
			}
						
			$countries[$cd->ID] = $country;
		}

		\Omi\TF\TOInterface::markReportEndpoint($objs, 'countries');
		return $countries;
	}
	
	public function getCounties(&$objs = null)
	{
		if ($objs === null)
			$objs = [];

		\Omi\TF\TOInterface::markReportStartpoint($objs, 'regions');

		$counties = $this->SOAPInstance->getCounties();
		\Omi\TF\TOInterface::markReportData($objs, 'Count regions: %s', [$counties ? q_count($counties) : 0]);
		$ret = [];

		foreach ($counties as $county)
		{
			\Omi\TF\TOInterface::markReportData($objs, 'Process region: %s', [$county->ID . ' ' . $county->Name], 50);
			$country_id = $county->CountryID;
			$db_country = $this->FindExistingItem("Countries", $country_id, "Name, InTourOperatorsIds.{TourOperator, Identifier}");
			if (!$db_country)
			{
				\Omi\TF\TOInterface::markReportData($objs, 'Country not found in db by id for region: %s', [$county->ID . ' ' . $county->Name], 50, true);
				continue;
			}

			$countyObj = $this->getCountyObj($objs, $county->ID, $county->Name, $county->Code);
			
			$countyObj->setCountry($db_country);
			$ret[] = $countyObj;
		}

		\Omi\TF\TOInterface::markReportEndpoint($objs, 'regions');

		return $ret;
	}

	/**
	 * Returns an array with cities
	 * 
	 * @param array $params -  Array of countries
	 * @param array $objs -  Implemeted only in EuroSite, not here
	 * @return type
	 */
	public function apiGetCities($params = null, &$objs = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($objs, 'cities');

		$cities = $this->SOAPInstance->getCities();
		\Omi\TF\TOInterface::markReportData($objs, 'Count cities: %s', [$cities ? q_count($cities) : 0]);

		$objs = [];
		$ret = [];
		
		foreach ($cities as $city)
		{
			\Omi\TF\TOInterface::markReportData($objs, 'Process city: %s', [$city->ID . ' ' . $city->Name], 50);
			$country_id = $city->CountryID;
			$db_country = $this->FindExistingItem("Countries", $country_id, "Name, InTourOperatorsIds.{TourOperator, Identifier}");
			if (!$db_country)
			{
				\Omi\TF\TOInterface::markReportData($objs, 'Country not found in db by id for city: %s', [$city->ID . ' ' . $city->Name], 50, true);
				continue;
			}

			$county_id = $city->RegionID;
			$db_county = $this->FindExistingItem("Counties", $county_id, "Name, InTourOperatorsIds.{TourOperator, Identifier}");
			if ($county_id && (!$db_county))
			{
				\Omi\TF\TOInterface::markReportData($objs, 'Region ID provided for city but the region not found in db: %s', [$city->ID . ' ' . $city->Name], 50, true);
			}
			

			$cityObj = $this->getCityObj($objs, $city->ID, $city->Name, $city->Code);
			$cityObj->setCountry($db_country);
			if ($db_county)
				$cityObj->setCounty($db_county);
			
			$ret[] = $cityObj;
		}
		
		\Omi\TF\TOInterface::markReportEndpoint($objs, 'cities');

		return $ret;
	}

	public function importHotelsContentFromJsonFile($file)
	{
		if (!file_exists($file))
			throw new \Exception("File [{$file}] not on hdd!");
		$hotelsContent = json_decode(utf8_encode(file_get_contents($file)), true);
		if (!(($hotelsContent && $hotelsContent["data"])))
			throw new \Exception("Hotels content not well formatted!");
		$this->importHotelsContent($hotelsContent["data"]);
	}

	public function saveBooking($data)
	{
		return $this->createBookingRequest($data);
	}
/**
	 * Returns the hotel populated with data
	 * 
	 * @param array $hotelDetails
	 * @param array $objs
	 * @param boolean $force
	 * @return \Omi\Travel\Merch\Hotel
	 */
	private function getHotel($hotelDetails, &$objs = null, $force = false, $binds = [])
	{
		$hotelId = $hotelDetails->HotelKey;

		$useEntity = "ContactPerson.{Email, Phone, Fax},"
			. "Facilities.{Name, Type, Active, Icon, IconHover, ListingOrder},"
			. "Name,"
			. "Stars,"
			. "IsMaster, "
			. "Destination, "
			. "City, "
			. "MasterCity, "
			. "County, "
			. "MasterCounty, "
			. "Country, "
			. "Master.{"
				. "IsMaster, "
				. "Name, "
				. "HasCharterOffers, "
				. "HasIndividualOffers, "
				. "HasHotelsOffers, "

				. "HasCityBreakActiveOffers, "
				. "HasPlaneCityBreakActiveOffers, "
				. "HasBusCityBreakActiveOffers, "

				. "HasChartersActiveOffers, "
				. "HasPlaneChartersActiveOffers, "
				. "HasBusChartersActiveOffers, "

				. "HasIndividualActiveOffers, "
				. "HasHotelsActiveOffers, "
				. "Address.{"
					. "City.{"
						. "IsMaster, "
						. "Name, "
						. "County.{"
							. "IsMaster, "
							. "Name "
						. "}, "
						. "Country.{"
							. "Code, "
							. "Name "
						. "} "
					. "}"
				. "} "
			. "}, "
			. "SyncStatus.{"
				. "LiteSynced, "
				. "LiteSyncedAt, "
				. "FullySynced, "
				. "FullySyncedAt"
			. "}, "
			. "BookingUrl, "
			. "MainImage.{"
				. "Updated, "
				. "Path, "
				. "ExternalUrl, "
				. "Type, "
				. "RemoteUrl, "
				. "Base64Data, "
				. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
				. "InTourOperatorId, "
				. "Alt"
			. "}, "
			. "Active, "
			. "ShortContent, "
			. "LiveVisitors, "
			. "LastReservationDate,	"

			. "HasPlaneCityBreakActiveOffers, "
			. "HasBusCityBreakActiveOffers, "

			. "HasChartersActiveOffers, "
			. "HasPlaneChartersActiveOffers, "
			. "HasBusChartersActiveOffers, "

			. "HasIndividualActiveOffers, "
			. "HasHotelsActiveOffers, "
			. "InCategoriesItems.{Category.{Alias, Name, Active, HideTravelItems}, Type}, "
			. "HasIndividualOffers,"
			. "HasCharterOffers,"
			. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
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
						. "TourOperator.{Handle, Abbr, Caption}, "
						. "InTourOperatorId, "
						. "Order, "
						. "Alt"
					. "}"
				. "}"
			. "},"
			. "Address.{"
				. "City.{"
					. "Name, "
					. "InTourOperatorId,"
					. "IsMaster, "
					. "Master.{"
						. "Name,"
						. "County.{"
							. "Name"
						. "}"
					. "}, "
					. "County.{"
						. "InTourOperatorId,"
						. "Name, "
						. "IsMaster, "
						. "Master.{IsMaster, Name},"
						. "Country.{Name, Alias}"
					. "}, "
					. "Country.{Name, Alias}"
				. "}, "
				. "County.{"
					. "Name, "
					. "InTourOperatorId,"
					. "IsMaster, "
					. "Master.Name,"
					. "Country.Name"
				. "}, "
				. "Country.{Name, Alias}, "
				. "PostCode,"
				. "Latitude, "
				. "Longitude"
			. "}";
		// sanitize selector
		$useEntity = q_SanitizeSelector("Hotels", $useEntity);

		$hotel = $this->FindExistingItem("Hotels", $hotelId, $useEntity);
		
		return $hotel;
	}

	/**
	 * Save all the hotels from Solvex to our database
	 * Used in crons
	 */
	public function saveAllHotels($binds = [])
	{
		\Omi\TF\TOInterface::markReportStartpoint($binds, 'hotels');

		$hotels = new \QModelArray();
		$cities = $this->SOAPInstance->getCities();

		$eHotels = QQuery("Hotels.{InTourOperatorId, Address WHERE TourOperator.Id=?}", $this->TourOperatorRecord->getId())->Hotels;

		$allHotels = [];
		foreach ($eHotels ?: [] as $hotel)
		{
			if (!$hotel->InTourOperatorId)
				continue;
			$allHotels[$hotel->InTourOperatorId] = $hotel;
		}

		foreach ($cities ?: [] as $city)
		{
			$hs = $this->SOAPInstance->getHotels($city->ID);
			\Omi\TF\TOInterface::markReportData($binds, 'Count hotels for city: %s = %s', [
				$city->ID . ' ' . $city->Name,
				($hs ? q_count($hs) : 0)
			]);
			foreach ($hs ?: [] as $h)
			{
				\Omi\TF\TOInterface::markReportData($binds, 'Process hotel %s', [
					$h->InTourOperatorId . ' ' . $h->Name,
				], 50);
				// we must have InTourOperatorId
				if (!$h->InTourOperatorId)
					continue;
				if (($eHotel = $allHotels[$h->InTourOperatorId]))
				{
					$h->setId($eHotel->getId());
					if ($eHotel->Address && $h->Address)
						$h->Address->setId($eHotel->Address->getId());
				}
				else
					$h->setFromTopAddedDate(date("Y-m-d H:i:s"));
				$h->setupDataForQuickList();
				if (!$h->getId())
				{
					$hotelIsActive = true;
					if (function_exists("q_getTopHotelActiveStatus"))
						$hotelIsActive = q_getTopHotelActiveStatus($h);
					$h->setActive($hotelIsActive);
				}

				$hotels[] = $h;
			}
		}
		$mainApp = \QApp::Data();
		$mainApp->Hotels = $hotels;
		$mainApp->save(true);
		\Omi\TF\TOInterface::markReportEndpoint($binds, 'hotels');
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
			$storage->saveAllHotels($params);

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
		//$isIndividual = (($parameters["VacationType"] === "individual") && ($parameters["search_type"] !== "hotel"));
		$isHotel = (($parameters["VacationType"] === "hotel") || ($parameters["search_type"] === "hotel"));

		$forceIndivudalInSamePlace = (\Omi\App::HasIndividualInOnePlace() || $parameters["category"] || $parameters["custDestination"] || 
			$parameters["mainDestination"] || $parameters["dynamic_packages"] || $parameters["for_dynamic_packages"]);
		if (($isHotel && (!$forceIndivudalInSamePlace)) || $isCharter || $isTour)
			return;

		$tourOperator = $child_storage->TourOperatorRecord;

		$params = static::GetRequestsDefParams($parameters);

		$_isCounty = ($parameters && ($parameters["DestinationType"] == "county"));
		
		if (!($destinationID = ($parameters["CityCode"] ?: $parameters["Destination"])))
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
			#if (!$isTour)
				return;
		}

		$travelItms = null;
		// if we have product code
		if ($parameters["ProductCode"])
		{
			// if we don't have the product in current tour operator - we don't have requests
			if ((!($travelItms = static::GetTravelItems($parameters["ProductCode"], $tourOperator, $parameters["VacationType"]))) || (q_count($travelItms) === 0))
				return null;
		}

		$checkIn = date("Y-m-d", strtotime($parameters["CheckIn"]));
		$checkOut = date("Y-m-d", strtotime($parameters["CheckOut"]));
		

		$params["PeriodOfStay"] = [
			["CheckIn" => $checkIn],
			["CheckOut" => $checkOut]
		];
		
		#$params["CurrencyCode"] = $child_storage->getRequestCurrency($parameters);
		#$params["SellCurrency"] = $child_storage->getSellCurrency($parameters);

		$reqs = [];


		$_qparams = [
			"TourOperator" => $tourOperator->getId(),
			"Active" => true,
			($_isCounty ? ($destination->IsMaster ? "MasterCounty" : "County") : ($destination->IsMaster ? "MasterCity" : "City")) => $destinationID
		];

		$from = ($forceIndivudalInSamePlace ? "AllIndividualTransports" : "IndividualTransports");

		$_cachedT = QQuery($from . ".{"
			. "TourOperator.Caption,"
			. "To.{"
				. "City.{"
					. "Name, "
					. "County.{"
						. "Name, "
						. "InTourOperatorId, "
						. "TourOperator, "
						. "Master.Name"
					. "},"
					. "Master.{"
						. "Name,"
						. "County.Name"
					. "},"
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
			$__toSetupReqs = [];

			// if the search is on county then we need to use zones
			$_USE_ZONES = false;

			foreach ($_cachedT as $_ct)
			{
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

				// we may be in exception case 1 or 3 where, we may have a city dettached to its county and we need to do single request on only the city
				// use zone for request if:
				// - we globally use zones
				// - we have county
				// - we don't have city master or we don't have city master county or we don't have city county master or if they are different

				$_USE_ZONE_FOR_REUQEST = ($_USE_ZONES && $city->County && (!$city->Master || !$city->Master->County || !$city->County->Master ||
					($city->Master->County->getId() == $city->County->Master->getId()) || ($city->Master->County->getId() != $destinationID)
				));

				$to = ($_USE_ZONE_FOR_REUQEST && $city->County) ? $city->County : $city;
				if (!$to->InTourOperatorId)
					continue;

				$_req_cache_indx = get_class($to)."~".$to->InTourOperatorId;
				// ids are unique in ETRIP
				//$_req_cache_indx = $to->InTourOperatorId;

				if (!($_creq_indx = $__toSetupReqs[$_req_cache_indx]))
				{
					// copy params that we have so far
					$__params = $params;

					$__params[($to instanceof \Omi\County) ? "Zone" : "CityCode"] = $to->InTourOperatorId;
					if ($city->Country && ($identif = $city->Country->getTourOperatorIdentifier($tourOperator)))
						$__params["CountryCode"] =  $identif;
					ksort($__params);
					$indx = json_encode($__params);

					$__params["__CACHED_TRANSPORTS__"] = [$_ct->getId() => $_ct->getId()];
					$reqs[$indx] = $__params;
					$__toSetupReqs[$_req_cache_indx] = $indx;
				}
				else
				{
					if (!$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"])
						$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"] = [];
					$reqs[$_creq_indx]["__CACHED_TRANSPORTS__"][$_ct->getId()] = $_ct->getId(); 

					// if we have the county that comes after city we need to unset the city and use the zone
					/*
					if (($to instanceof \Omi\County) && $reqs[$_creq_indx]["CityCode"])
					{
						unset($reqs[$_creq_indx]["CityCode"]);
						$reqs[$_creq_indx]["Zone"] = $to->InTourOperatorId;
					}
					*/
				}
			}
		}
		
				// setup search list indx on request
		foreach ($reqs ?: [] as $reqIndx => $req)
		{
			#$reqs[$reqIndx]["searchIndx"] = md5($reqIndx);
			#$reqs[$reqIndx]["searchIndxData"] = $reqIndx;
			$reqCurrency = $child_storage->getRequestCurrency($parameters);
			if (!$reqCurrency)
				$reqCurrency = static::$DefaultCurrency;
			$reqs[$reqIndx]["CurrencyCode"] = $reqCurrency;
			$reqs[$reqIndx]["RequestCurrency"] = $reqCurrency;
			$reqs[$reqIndx]["SellCurrency"] = $child_storage->getSellCurrency($parameters);
		}
		
		// we need to filter the requests to only the ones where the product code applies - based on the destination
		// if we leave them then it may do requests where we will have no results
		if ($travelItms && (q_count($travelItms) > 0))
		{
			$allReqs = [];
			#$ireqs = $reqs;
			foreach ($travelItms ?: [] as $travelItm)
			{
				if (!$travelItm->InTourOperatorId)
					continue;

				foreach ($reqs ?: [] as $indx => $req)
				{
					if ($req['CityCode'])
					{
						if ((!$travelItm->Address) || (!$travelItm->Address->City) || (!$travelItm->Address->City->InTourOperatorId) || 
							($travelItm->Address->City->InTourOperatorId != $req['CityCode']))
							continue;
					}
					else if ($req['Zone'])
					{
						if ((!$travelItm->Address) || (!$travelItm->Address->City) || (!$travelItm->Address->City->County) || 
							(!$travelItm->Address->City->County->InTourOperatorId) || ($travelItm->Address->City->County->InTourOperatorId != $req['Zone']))
							continue;
					}

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
	
	public static function ExecIndividualRawRequest($storageHandle, $countryCode, $zoneCode, $cityCode, $checkIn, $checkOut, $rooms = [])
	{
		throw new \Exception("TO BE CHECKED!");
	
	}

	public static function ResyncActiveHotels()
	{
		//set_time_limit(60 * 10);
		//ini_set('memory_limit', '3072M');

		if (!($childStorage = \QApp::GetStorage('travelfuse')->getChildStorage('solvex')))
			return;

		$resyncBinds = [
			"CountryCode" => "BG",
			"__nmonths" => (12 - (int)date("m")) + 1,
			"__days" => [
				1, 7, 14, 21, 28
			],
			"__periods" => [4, 7]
		];
		
		$individualTransports = QQuery("IndividualTransports.{To.City WHERE TourOperator.Handle=?}", $childStorage->TourOperatorRecord->Handle)->IndividualTransports;
		$cities = [];
		foreach ($individualTransports ?: [] as $indvT)
		{
			if (!$indvT->To || !$indvT->To->City)
				continue;
			$cities[$indvT->To->City->getId()] = $indvT->To->City->getId();
		}

		if (q_count($cities) > 0)
			$resyncBinds["IN"] = [array_keys($cities)];

		// for now only bulgaria
		$childStorage->saveHotels($resyncBinds);
		
		//mail($_GET['mail'] ?: "gheorghe.mihaita87@gmail.com", "ResyncActiveHotels - finished for SOLVEX", "ResyncActiveHotels - finished for SOLVEX");
	}
}