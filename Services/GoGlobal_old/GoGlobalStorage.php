<?php

namespace Omi\TF;

/**
 * Description of GoGlobalStorage
 *
 * @author Omi-Mihai
 */
class GoGlobalStorage extends \QStorageEntry implements \QIStorage
{
	use TOStorage, TOStorage_GetOffers, TOStorage_CacheStaticData, TOStorage_CacheGeography, TOStorage_CacheAvailability, TOStorage_Booking;
	
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
		'goglobal' => [
			'get_hotels_extra_details' => true
		]
	];

	public static $UseRegionsInIndividualRequests = false;

	public function __construct($name = null, \QStorageFolder $parent = null) 
	{
		$this->TOPInstance = new GoGlobal();
		$this->TOPInstance->Storage = $this;
		return parent::__construct($name, $parent);
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
		return $this->TOPInstance->doRestAPI("api_testConnection");
	}

	public function initTourOperator($config = [])
	{
		if (!$config)
			$config = [];

		$config = array_merge($config, static::$Config["init"][$this->TourOperatorRecord->Handle] ?: []);

		// cache top countries
		$this->cacheTOPCountries($config);

		// cache top cities
		$this->cacheTOPCities($config);

		// cache top hotels
		$this->cacheTOPHotels(array_merge($config, ['get_extra_details' => true]), true, ['FORCE' => true]);

		// cache top hotels
		$this->cacheTOPHotelsDetails($config, true);
	}

	public function cacheChartersRequests()
	{
	
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

		if ((!$self) || (get_class($self) != get_called_class()) || (!$self->TourOperatorRecord) || (!$self->TourOperatorRecord->ApiPassword) || 
			(!$self->TourOperatorRecord->ApiUsername) || (!$self->TourOperatorRecord->ApiUrl))
			throw new \Exception("No storage instance provided!");

		$initialParams = static::$RequestOriginalParams;
		if (!$initialParams)
			$initialParams = [];
		if ($parameters["travelItemId"])
			$initialParams["getFeesAndInstallments"] = true;

		switch ($from)
		{
			case "Flights" : 
			{
				return null;
			}

			/*===================  Geography  =======================*/
			case "Countries" :
			case "Counties" :
			case "Cities" :
			{
				return null;
			}

			/*===================  Travel Items data  =======================*/
			case "Hotels" :
			{
				return null;
			}

			case "Tours" :
			{
				return null;
			}

			case "Rooms" : 
			{
				return null;
			}

			case "HotelsFacilities" : 
			{
				return null;
			}

			/*===================  Hotels offers & fees  =======================*/

			case "InternationalHotels" : 
			{
				return $self->getHotelsWithIndividualOffers($parameters, $initialParams);
			}

			case "HotelsWithIndividualOffers" :
			{
				#if (\Omi\App::HasIndividualInOnePlace() || $initialParams["category"] || $initialParams["custDestination"] || 
				#	$initialParams["mainDestination"] || $initialParams["dynamic_packages"] || $initialParams["for_dynamic_packages"])
					return $self->getHotelsWithIndividualOffers($parameters, $initialParams);
				#return null;
			}

			case "HotelsWithPackages" :
			{
				$ret = $self->getChartersOffers($parameters, $initialParams);
				return $ret;
			}

			case "Installments" :
			{
				return $self->getOfferPayments($parameters, $initialParams);
			}

			case "HotelFees" : 
			{
				return $self->getOfferCancelFees($parameters, $initialParams);
			}

			/*===================  Tours offers & fees  =======================*/

			// to be replaced with tours with offers
			case "Circuits" : 
			{
				return null;
			}
			case "TourFees" : 
			{
				return null;
			}
			case "TourInstallments" :
			{
				return null;
			}



			/*===================  Default fees and installments  =======================*/

			case "HotelFeesAndInstallments" :
			{
				list($fees_params, $installments_params) = $parameters;
				$fees = static::ApiQuery($storage_model, "HotelFees", null, null, $fees_params);
				$installments = static::ApiQuery($storage_model, "Installments", null, null, $installments_params);
				list($installments, $fees) = 
					\Omi\TFuse\Api\TravelFuse::MergePaymentsAndCancelFees($installments, $fees, $fees_params["Price"], $fees_params["CheckIn"], $self, $fees_params, $installments_params);
				//qvardump("after normalize fees and installments", $fees, $installments);
				return [$fees, $installments];
			}
			case "TourFeesAndInstallments" :
			{
			
			}



			/*===================  Orders  =======================*/
			case "Orders" : 
			{
				return null;
			}

			// custom and not used data - to be removed on cleanup (make sure that custom data will be renamed
			case "HotelsInfo" :
			case "HotelSupliments" :
			case "TourSupliments" : 
			case "TourCountries" :
			case "Circuits" : 
			case "Packages" :
			case "IndividualOffers" :
			case "OrdersDocuments" : 
			case "PackagesOffers" :
			case "Charters" : 
			case "CharterSupliments" :
			case "CharterFees" : 
			{
				return null;
			}
			default :
			{
				throw new \Exception($from . " not implemented!");
			}
		}
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
			// orders for now is the only active - needs to be checked
			case "Orders" :
			{
				return $self->book($data);
			}

			// to be removed on cleanup
			case "Charters" :
			case "Tours" :
			case "OrdersUpdates" :
			case "Hotels" : 
			case "CachedData" :
			{
				return null;
			}
			default :
			{
				throw new \Exception("Save for [{$from}] not implemented!");
			}
		}
	}

	public static function GetRequests($childStorage, $from, $parameters)
	{
		return static::QGetTourOperatorRequests($childStorage, $from, $parameters);
	}
	
	/**
	 * @api.enable
	 * 
	 * @param type $params
	 */
	public static function PullChartersHotels($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false)
	{
	
	}
	
	/**
	 * @api.enable
	 * 
	 * @param type $params
	 */
	public static function PullTours($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false)
	{
	
	}
}