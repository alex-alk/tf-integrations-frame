<?php

namespace Integrations\TourVisio_old;

/**
 * Description of SejourStorage
 *
 * @author Omi-Mihai
 */
class TourVisioNewStorage extends \QStorageEntry implements \QIStorage
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
		'fibula_new' => [
			'charter' => [
				'api_getHotelDetailsEnabled' => true,
				'activate_request_transports' => true,
				'skip_dynamic_transports_create' => true
			],
			'individual' => [
				'api_getHotelDetailsEnabled' => true
			],
			'to_map_top' => 'fibula',
			'use_db_hotels_in_details_request' => true,
			'reverse_search_correction' => true,
		],
		'soley_tour' => [
			'charter' => [
				'api_getHotelDetailsEnabled' => true,
				'skip_dynamic_transports_create' => true
			],
			'individual' => [
				'api_getHotelDetailsEnabled' => true
			],
			'use_db_hotels_in_details_request' => true
		],
		'kusadasi_new' => [
			'charter' => [
				'api_getHotelDetailsEnabled' => true,
				'skip_dynamic_transports_create' => true
			],
			'individual' => [
				'api_getHotelDetailsEnabled' => true
			],
			'use_db_hotels_in_details_request' => true
		],
	];

	public static $UseRegionsInIndividualRequests = false;

	public function __construct($name = null, \QStorageFolder $parent = null) 
	{
		$this->TOPInstance = new TourVisioNew();
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

	public function mapNewDestinations()
	{
		if (!($mapTop = static::$Config[$this->TourOperatorRecord->Handle]['to_map_top']))
			throw new \Exception('Cannot map hotels because map top not added to config');

		$file = 'fibula_link/location-export-withpaxID.csv';
		if (!file_exists($file))
			throw new \Exception('Destinations map csv file not found!');

		$topMapDestinations = [];
		if (($handle = fopen($file, 'r')) !== false)
		{
			while (($row = fgetcsv($handle)) !== false)
			{
				#echo implode(',', $row) . '<br/>';
				if (empty($row[0]) || ((strpos($row[0], 'RecID')) !== false))
					continue;

				list($recId) = $row;
				$topMapDestinations[$recId][] = $row;
			}
		}

		if (count($topMapDestinations))
		{
			$existingDestinationsToBeMapped = QQuery('Cities.{*, Master.* WHERE !ISNULL(Master.Id) ' 
				. "??InTourOperatorIdIn?<AND[InTourOperatorId IN (?)]"
				. "??TourOperator?<AND[TourOperator.Handle=?]"
			. '}', [
				'InTourOperatorIdIn' => [array_keys($topMapDestinations)],
				'TourOperator' => $mapTop
			])->Cities;

			$existingDestinationsToBeMappedByInTopId = [];
			foreach ($existingDestinationsToBeMapped ?: [] as $ehtmp)
				$existingDestinationsToBeMappedByInTopId[$ehtmp->InTourOperatorId] = $ehtmp;

			$toMapTopDestsInTopIds = [];
			foreach ($topMapDestinations ?: [] as $toMapDestTopId => $tmphs)
			{
				foreach ($tmphs ?: [] as $tmph)
				{
					#list(,,,,,,,,,,$paxTopId) = $tmph;
					$paxTopId = end($tmph);
					$toMapTopDestsInTopIds[$paxTopId] = $toMapDestTopId;
				}
			}

			$existingDestsToMap = QQuery('Cities.{*, WHERE ISNULL(Master.Id) ' 
				#. "??InTourOperatorIdIn?<AND[InTourOperatorId IN (?)]"
				. "??TourOperator?<AND[TourOperator.Handle=?]"
			. '}', [
				'TourOperator' => $this->TourOperatorRecord->Handle
			])->Cities;

			/*
			qvardump('$existingDestsToMap, $toMapTopDestsInTopIds, $existingDestinationsToBeMappedByInTopId', 
				$existingDestsToMap, $toMapTopDestsInTopIds, $existingDestinationsToBeMappedByInTopId);
			*/

			foreach ($existingDestsToMap ?: [] as $ehtmp)
			{
				$inTopId = $ehtmp->InTourOperatorId;
				if (strpos($ehtmp->InTourOperatorId, '-') !== false)
					$inTopId = reset(explode('-', $ehtmp->InTourOperatorId));
				//echo $ehtmp->Name . '::' . $inTopId . '<br/>';
				if (($toMapInTopId = $toMapTopDestsInTopIds[$inTopId]) && ($eh = $existingDestinationsToBeMappedByInTopId[$toMapInTopId]))
				{
					if ($eh->Master)
						echo 'MAP CITY ' . $inTopId . ' TO ' . $toMapInTopId . '<br/>';
					else
						echo 'CANNOT MAP CITY ' . $inTopId . ' TO ' . $toMapInTopId . ' - no master<br/>';
					#\Omi\Travel\Merch\Hotel::SetupMasterHotel($ehtmp->InTourOperatorId, $eh->Master->getId());
				}
			}
		}
	}

	public function resync_hotels_content($hotels)
	{
		$force = false;
		$dumpData = true;
		$__ttime = strtotime(date("Y-m-d"));

		foreach ($hotels ?: [] as $hotel)
		{
			if ($force || ((!$hotel->MTime) || (strtotime(date("Y-m-d", strtotime($hotel->MTime))) != $__ttime)))
			{
				try
				{
					$getHotelDetailsParams = [
						"apiContext" => $this->TourOperatorRecord->ApiContext,
						"hotelId" => $hotel->InTourOperatorId,
						"hotelName" => $hotel->Name,
						"force" => $force
					];

					list($retHotel_withDetails, /*$alreadyProcessed, $toSaveProcesses*/) = $this->TOPInstance->doRestAPI("api_getHotelDetails", $getHotelDetailsParams);

					list($existingCountries, $existingCounties, $existingCities, $existingDestinations, $existingHotels, $existingRooms, $existingRoomsTypes, $existingFacilities) = 
						$this->__cacheTOPHotels_getExistingData([$retHotel_withDetails]);

					$app = \QApp::NewData();
					$app->Countries = new \QModelArray();
					$app->Counties = new \QModelArray();
					$app->Cities = new \QModelArray();
					$app->Destinations = new \QModelArray();
					$app->Hotels = new \QModelArray();
					$app->TourOperatorsHotelsFacilities = new \QModelArray();
					$app->HotelsFacilities = new \QModelArray();
					$bag = [];

					#list($toProcessHotel, $saveHotel, $toSaveProcesses_On_Hotel) = $this->updateHotelDetails($retHotel_withDetails, $dbHotel, $force);

					list($toProcessHotel, $saveHotel, /*$toSaveProcesses_On_Hotel*/) = $this->updateHotelDetails($retHotel_withDetails, $hotel, $force, $existingCountries, 
						$existingCounties, $existingCities, $existingDestinations, $existingHotels, $existingRooms, $existingRoomsTypes, $existingFacilities, $app, $bag, $dumpData);

					$this->__cacheTOPHotels_saveData($app);

					if ($saveHotel)
					{
						$toProcessHotel->setFromTopModifiedDate(date("Y-m-d H:i:s"));
						$this->saveInBatchHotels([$toProcessHotel], true);
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

	public function mapNewHotels()
	{
		if (!($mapTop = static::$Config[$this->TourOperatorRecord->Handle]['to_map_top']))
			throw new \Exception('Cannot map hotels because map top not added to config');

		$file = 'fibula_link/hotel-export.csv';
		if (!file_exists($file))
			throw new \Exception('Hotel map csv file not found!');

		$toMapHotels = [];
		if (($handle = fopen($file, 'r')) !== false)
		{
			while (($row = fgetcsv($handle)) !== false)
			{
				if (empty($row[0]) || ($row[0] === '﻿RecID'))
					continue;

				list(, $hotelCode) = $row;
				$toMapHotels[$hotelCode][] = $row;
			}
		}

		if (count($toMapHotels))
		{
			$existingHotelsToBeMapped = QQuery('Hotels.{*, Master.* WHERE !ISNULL(Master.Id) ' 
				. "??InTourOperatorIdIn?<AND[InTourOperatorId IN (?)]"
				. "??TourOperator?<AND[TourOperator.Handle=?]"
			. '}', [
				'InTourOperatorIdIn' => [array_keys($toMapHotels)],
				'TourOperator' => $mapTop
			])->Hotels;

			$existingHotelsToBeMappedByInTopId = [];
			foreach ($existingHotelsToBeMapped ?: [] as $ehtmp)
				$existingHotelsToBeMappedByInTopId[$ehtmp->InTourOperatorId] = $ehtmp;

			$toMapTopHotelsInTopIds = [];
			foreach ($toMapHotels ?: [] as $toMapHotelTopId => $tmphs)
			{
				foreach ($tmphs ?: [] as $tmph)
				{
					list(,,,,,,,,,,$paxTopId) = $tmph;
					$toMapTopHotelsInTopIds[$paxTopId] = $toMapHotelTopId;
				}
			}

			$existingHotelsToMap = QQuery('Hotels.{*, WHERE ISNULL(Master.Id) ' 
				#. "??InTourOperatorIdIn?<AND[InTourOperatorId IN (?)]"
				. "??TourOperator?<AND[TourOperator.Handle=?]"
			. '}', [
				'TourOperator' => $this->TourOperatorRecord->Handle
			])->Hotels;

			foreach ($existingHotelsToMap ?: [] as $ehtmp)
			{
				$inTopId = $ehtmp->InTourOperatorId;
				if (strpos($ehtmp->InTourOperatorId, '-') !== false)
					$inTopId = reset(explode('-', $ehtmp->InTourOperatorId));
				if (($toMapInTopId = $toMapTopHotelsInTopIds[$inTopId]) && ($eh = $existingHotelsToBeMappedByInTopId[$toMapInTopId]) && $eh->Master)
				{
					echo 'MAP HOTEL ' . $inTopId . ' TO ' . $toMapInTopId . '<br/>';
					#\Omi\Travel\Merch\Hotel::SetupMasterHotel($ehtmp->InTourOperatorId, $eh->Master->getId());
				}
			}
		}
	}
	
	public function cacheTOPTours()
	{
		
	}

	public function initTourOperator($config = [])
	{
		if (!$config)
			$config = [];

		$config = array_merge($config, static::$Config["init"][$this->TourOperatorRecord->Handle] ?: []);

		$topInst = $this->TOPInstance;
		$topInst::$Config[$this->TourOperatorRecord->Handle]['destinations_qs'] = [];

		$bgCities = QQuery('Cities.{*, Country.{Name, Alias} WHERE IsMaster=? AND Country.Code=?}', [true, 'BG'])->Cities;
		foreach ($bgCities ?: [] as $bgCity)
			$topInst::$Config[$this->TourOperatorRecord->Handle]['destinations_qs'][] = $bgCity->Country->getModelCaption() . ' ' . $bgCity->Name;

		// cache top countries
		$this->cacheTOPCountries($config);

		// cache top counties
		$this->cacheTOPRegions($config);

		// cache top cities
		$this->cacheTOPCities($config);

		// cache top hotels
		#$this->cacheTOPHotels($config, false);

		// cache top hotels
		#$this->cacheTOPHotelsDetails($config, false);

		// cache top tours
		#$this->cacheTOPTours($config);

		// charter departures
		$this->cacheChartersDepartures();

		// tours departures
		#$this->cacheToursDepartures();
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
				//break;
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
				if (\Omi\App::HasIndividualInOnePlace() || $initialParams["category"] || $initialParams["custDestination"] || 
					$initialParams["mainDestination"] || $initialParams["dynamic_packages"] || $initialParams["for_dynamic_packages"])
					return $self->getHotelsWithIndividualOffers($parameters, $initialParams);
				return null;
			}

			case "HotelsWithIndividualOffers" :
			{
				return $self->getHotelsWithIndividualOffers($parameters, $initialParams);
			}

			case "HotelsWithPackages" :
			{
				//$t1 = microtime(true);
				$ret = $self->getChartersOffers($parameters, $initialParams);
				//echo "<div>" . (microtime(true) - $t1) . " seconds took getting the charter offers!</div>";
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
			
			case "OffersForDoubleChecking" :
			{
				return null;
			}

			/*===================  Tours offers & fees  =======================*/

			// to be replaced with tours with offers
			case "Circuits" : 
			{
				return null;
			}
			case "TourFees" : 
			{
				return $self->getOfferCancelFees($parameters, $initialParams);
			}
			case "TourInstallments" :
			{
				return $self->getOfferPayments($parameters, $initialParams);
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
				list($fees_params, $installments_params) = $parameters;
				$fees = static::ApiQuery($storage_model, "TourFees", null, null, $fees_params);
				$installments = static::ApiQuery($storage_model, "TourInstallments", null, null, $installments_params);
				//qvardump($fees, $installments);
				//q_die();
				list($installments, $fees) = 
					\Omi\TFuse\Api\TravelFuse::MergePaymentsAndCancelFees($installments, $fees, $fees_params["Price"], $fees_params["CheckIn"], $self, $fees_params, $installments_params);
				return [$fees, $installments];
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
	 * @param type $params
	 */
	public static function PullChartersHotels($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false, $force = false)
	{
		try
		{
			$childStorage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
		}
		catch (\Exception $ex)
		{
			return;
		}
		if ($childStorage)
			$childStorage->__pullChartersHotels($request, $params, $_nights, $do_cleanup, $skip_cache, $force);
		else
			throw new \Exception("No storage for [{$handle}]");
	}
	
	/**
	 * @api.enable
	 * 
	 * @param type $params
	 */
	public static function PullTours($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false)
	{
		try
		{
			$childStorage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
		}
		catch (\Exception $ex)
		{
			throw $ex;
			//return;
		}
		$childStorage->__pullTours($request, $params, $_nights, $do_cleanup, $skip_cache);
	}
}