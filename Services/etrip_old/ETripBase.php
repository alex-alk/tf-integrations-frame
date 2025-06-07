<?php

namespace Omi\TF;

/**
 * Basic ETrip Functions
 */
trait ETripBase
{
	protected function findDestination($destId, $destinations)
	{
		if (empty($destinations))
			return null;

		foreach ($destinations as $dest)
		{
			if ($dest->Id == $destId)
				return $dest;
			$fd = $this->findDestination($destId, $dest->Children);
			if ($fd)
				return $fd;
		}
		return null;
	}

	/**
	 * Get destination from API
	 * 
	 * @param type $destination
	 * @param type $objs
	 * @param type $is_destination
	 * 
	 * @return \Omi\TF\Destination
	 */
	public function getDestination($destination, $objs = null, $is_destination = false)
	{		
		if (!$objs)
			$objs = [];

		$dests = static::$_CacheData["geographies"][$this->TourOperatorRecord->getId()][$destination] ?: 
			(static::$_CacheData["geographies"][$this->TourOperatorRecord->getId()][$destination] = $this->SOAPInstance->getGeographyItems($destination)); //find the destination city

		list($city, $country, $misc_dest) = $dests;

		$county = null;
		if ($is_destination && (!static::$_CacheData["ALREADY_SHOWN_DESTS"][$destination]))
		{
			static::$_CacheData["ALREADY_SHOWN_DESTS"][$destination] = true;
		}
	
		if ((!$city) || (!$country))
		{
			// deal with custom destinations - 
			if (isset(self::$Config[$this->TourOperatorRecord->Handle]["CUSTOM_DESTS"][$misc_dest->Id]))
			{
				$misc_dest_obj = $this->findDestination($destination, [$misc_dest]);
				if (!$misc_dest_obj)
				{
					echo "<div style='color: red;'>Custom destination cannot be determined for destination id [{$destination}]</div>";
					return null;
				}
				// just try to match the country if possible - if not then leave the custom destination fucntionality to take over
				$country = QQuery("Countries.{Name, Alias, InTourOperatorsIds.{Identifier, TourOperator} "
					. "WHERE Name=? OR Alias=? OR Name=? OR Alias=?}", [$misc_dest_obj->Name, $misc_dest_obj->Name, 
					$misc_dest_obj->IntName, $misc_dest_obj->IntName])->Countries[0];

				if ($country && ($country->getTourOperatorIdentifier($this->TourOperatorRecord) == $misc_dest_obj->Id))
					return $country;

				$destObj = \QQuery("Destinations.{TourOperator, Name, InTourOperatorId WHERE InTourOperatorId=? AND TourOperator.Handle=?}", 
					[$misc_dest_obj->Id, $this->TourOperatorRecord->Handle])->Destinations[0];

				if (!$destObj)
				{
					$destObj = new \Omi\TF\Destination();
					$destObj->setFromTopAddedDate(date("Y-m-d H:i:s"));
					$destObj->setTourOperator($this->TourOperatorRecord);
					$destObj->setName($misc_dest_obj->Name);
					$destObj->setInTourOperatorId($misc_dest_obj->Id);
					
					if ($this->TrackReport)
					{
						if (!$this->TrackReport->NewCustomDestinations)
							$this->TrackReport->NewCustomDestinations = 0;
						$this->TrackReport->NewCustomDestinations++;
					}

					$_app = \QApp::NewData();
					$_app->Destinations = new \QModelArray();
					$_app->Destinations[] = $destObj;
					$_app->save("Destinations.{Name, TourOperator, InTourOperatorId, FromTopAddedDate}");
					echo "<div style='color: green;'>Add new custom destination [{$destObj->InTourOperatorId}|{$destObj->Name}] to system</div>";
					$destObj->__added_to_system__ = true;
				}
				// update name for destination to match tour operator name
				else if ($destObj->Name != $misc_dest_obj->Name)
				{
					$destObj->setName($misc_dest_obj->Name);
					$destObj->query("UPDATE Name=?", $misc_dest_obj->Name);
				}
				return $destObj;
			}
			else
			{
				$foundDest = $this->findDestination($destination, [$country]);
				$foundDestType = ($foundDest && isset($foundDest->Parent)) ? $foundDest->Parent->ChildLabel : null;
				if ($foundDestType === "City")
					$city = $foundDest;
				else if ($foundDestType === "Country")
					$country = $foundDest;
				else if (($foundDestType === "State") || ($foundDestType === "Region") || ($foundDestType === "County"))
					$county = $foundDest;

				if (!($country))
				{
					if ($city)
						$country = $this->SOAPInstance->getGeographyItmByType($city, "Country");
					else if ($county)
						$country = $this->SOAPInstance->getGeographyItmByType($county, "Country");
				}
			}
		}

		if ((!$country) && (!$county) && (!$city))
		{
			echo "<div style='color: red;'>Country/County/City cannot be determined for destination id [{$destination}]</div>";
			return null;
		}

		if ($country)
		{
			$countryObj = $this->FindExistingItem("Countries", $country->Id, "Code, Name, InTourOperatorsIds.{TourOperator, Identifier}");
			if (!$countryObj)
			{
				$countryUseName = $country->Name;
				if (($cfgCountryUseName = static::$Config[$this->TourOperatorRecord->Handle]["countries_names_translations"][$country->Name]))
				{
					if (is_scalar($cfgCountryUseName))
						$countryUseName = $cfgCountryUseName;
					else if (is_array($cfgCountryUseName) && $cfgCountryUseName[$country->Id])
						$countryUseName = $cfgCountryUseName[$country->Id];
				}
				$countryObj = \QQuery("Countries.{Name WHERE Name=?}", $countryUseName)->Countries[0];

				if ($countryObj)
				{
					$countryObj->InTourOperatorsIds = new \QModelArray();
					$apiStorageId = new \Omi\TF\ApiStorageIdentifier();
					$apiStorageId->setTourOperator($this->TourOperatorRecord);
					$apiStorageId->setIdentifier($country->Id);
					$countryObj->InTourOperatorsIds[] = $apiStorageId;
					if ($this->TrackReport)
					{
						if (!$this->TrackReport->NewCountries)
							$this->TrackReport->NewCountries = 0;
						$this->TrackReport->NewCountries++;
					}
					echo "<div style='color: green;'>Add new country [{$countryObj->Code}|{$countryObj->Name}] to system</div>";
					$countryObj->save("InTourOperatorsIds.{TourOperator, Identifier, FromTopAddedDate}");
					$apiStorageId->__added_to_system__ = true;
				}
				else
				{
					echo "<div style='color: red;'>Country not found for destination id [{$destination}] -> [{$country->Id}|{$country->Name}]</div>";
					return null;
				}
			}
		}

		$destRetObj = null;
		if ($city)
		{
			$countyObj = null;
			$city_data = $city;
			if ($city->Id != ($destination))
			{
				$city_data = null;
				if ($city->Children)
				{
					foreach ($city->Children as $child)
					{
						if ($child->Id == $destination)
						{
							$city_data = $child;
							break;
						}
					}
				}

				$countyObj = $this->getCountyObj($objs, $city->Id, $city->Name, $city->Id);
				if (trim($countyObj->Name) != trim($city->Name))
				{
					$countyObj->Name = $city->Name;
				}

				if ($countryObj)
					$countyObj->setCountry($countryObj);

				if (!($countyObj->getId()))
				{
					if ($this->TrackReport)
					{
						if (!$this->TrackReport->NewCounties)
							$this->TrackReport->NewCounties = 0;
						$this->TrackReport->NewCounties++;
					}
					echo "<div style='color: green;'>Add new zone [{$countyObj->InTourOperatorId}|{$countyObj->Name}] to system</div>";
					$countyObj = $objs[$city->Id]["\Omi\County"] = $this->saveCounty($countyObj);
					$countyObj->__added_to_system__ = true;
				}
			}
			else if (isset($city->Parent))
			{
				$parentType = isset($city->Parent->Parent) ? $city->Parent->Parent->ChildLabel : null;
				// if parent is not country - we may need to store the parent as county - to sort it out
				$parentIsCountry = ($parentType == "Country");
				if ($parentType && (!$parentIsCountry))
				{
					$countyObj = $this->getCountyObj($objs, $city->Parent->Id, $city->Parent->Name, $city->Parent->Id);
					if (trim($countyObj->Name) != trim($city->Parent->Name))
					{
						$countyObj->Name = $city->Name;
					}
					if ($countryObj)
						$countyObj->setCountry($countryObj);
					if (!($countyObj->getId()))
					{
						if ($this->TrackReport)
						{
							if (!$this->TrackReport->NewCounties)
								$this->TrackReport->NewCounties = 0;
							$this->TrackReport->NewCounties++;
						}
						echo "<div style='color: green;'>Add new zone [{$countyObj->InTourOperatorId}|{$countyObj->Name}] to system</div>";
						$countyObj = $objs[$city->Id]["\Omi\County"] = $this->saveCounty($countyObj);
						$countyObj->__added_to_system__ = true;
					}
				}
			}
			if ((!$city_data) || (!(trim($city_data->Name, "-_"))))
			{
				echo "<div style='color: red;'>No city data for destination id [{$destination}]</div>";
				return null;
			}
			$cityObj = $this->getCityObj($objs, $city_data->Id, $city_data->Name, $city_data->Id);

			if (trim($cityObj->Name) != trim($city_data->Name))
			{
				$cityObj->Name = $city_data->Name;
			}
			if ($countryObj)
				$cityObj->setCountry($countryObj);
			if ($countyObj)
				$cityObj->setCounty($countyObj);
			if (!$cityObj->getId())
			{
				if ($this->TrackReport)
				{
					if (!$this->TrackReport->NewCities)
						$this->TrackReport->NewCities = 0;
					$this->TrackReport->NewCities++;
				}
				echo "<div style='color: green;'>Add new city [{$cityObj->InTourOperatorId}|{$cityObj->Name}] to system</div>";
				$cityObj = $objs[$city_data->Id]["\Omi\City"] = $this->saveCity($cityObj);
				$cityObj->__added_to_system__ = true;
			}
			$destRetObj = $cityObj;
		}
		else if ($county)
		{
			$countyObj = $this->getCountyObj($objs, $county->Id, $county->Name, $county->Id);
			if (trim($countyObj->Name) != trim($county->Name))
				$countyObj->Name = $county->Name;
			if ($countryObj)
				$countyObj->setCountry($countryObj);
			if (!($countyObj->getId()))
			{
				if ($this->TrackReport)
				{
					if (!$this->TrackReport->NewCounties)
						$this->TrackReport->NewCounties = 0;
					$this->TrackReport->NewCounties++;
				}
				echo "<div style='color: green;'>Add new zone [{$countyObj->InTourOperatorId}|{$countyObj->Name}] to system</div>";
				$countyObj = $objs[$county->Id]["\Omi\County"] = $this->saveCounty($countyObj);
				$countyObj->__added_to_system__ = true;
			}
			$destRetObj = $countyObj;
		}
		else
			$destRetObj = $countryObj;

		return $destRetObj;
	}
	/**
	 * Return the hotel from our database
	 * ETrip hotel info must be stored in the database
	 * 
	 * @param int $hotel_id
	 * @return Hotel
	 * @throws \Exception
	 */
	public function GetETripHotelFromDB($hotel_id)
	{
		if (!$hotel_id)
			throw new \Exception("Hotel ID not provided for ETrip!");

		if (!($_req_hotels_arr = is_array($hotel_id)))
			$hotel_id = [$hotel_id];

		$_to_load = [];
		$_ret_hotels = [];

		foreach ($hotel_id as $_hid)
		{
			if (isset(static::$_CacheData[$this->TourOperatorRecord->getId()]["Hotels"][$_hid]))
				$_ret_hotels[$_hid] = static::$_CacheData[$this->TourOperatorRecord->getId()]["Hotels"][$_hid];
			else
				$_to_load[] = $_hid;
		}

		if (($cnt = count($_to_load)) === 0)
			return $_req_hotels_arr ? $_ret_hotels : $_ret_hotels[reset($hotel_id)];

		$useEntity = "Code, "
			. "InTourOperatorId, "
			. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
			. "InCategoriesItems.{Category.{Alias, Name, Active, HideTravelItems}, Type}, "
			. "Name, "

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
				
			. "ShowOnlyInCategory,"

			. "LiveVisitors, "
			. "LastReservationDate,	"
			
			. "HasCityBreakActiveOffers, "
			. "HasPlaneCityBreakActiveOffers, "
			. "HasBusCityBreakActiveOffers, "

			. "HasChartersActiveOffers, "
			. "HasPlaneChartersActiveOffers, "
			. "HasBusChartersActiveOffers, "

			. "HasIndividualActiveOffers, "
			. "HasHotelsActiveOffers, "

			. "HasCharterOffers,"
			. "HasIndividualOffers,"
			
			. "TextFacilities,"
			. "Facilities.{"
				. "Name, "
				. "Icon, "
				. "IconHover, "
				. "ListingOrder"
			. "},"
			. "Stars, "
			. "Address.{"
				. "Latitude,"
				. "Longitude, "
				. "City.{"
					. "Code, "
					. "Name, "
					. "InTourOperatorId,"
					. "IsMaster,"
					. "Master.{"
						. "Name,"
						. "IsMaster,"
						. "County.{"
							. "IsMaster,"
							. "Name,"
							. "Country.{Alias, Code, Name}"
						. "}"
					. "},"
					. "County.{"
						. "InTourOperatorId,"
						. "Name,"
						. "IsMaster,"
						. "Master.{"
							. "Name,"
							. "IsMaster,"
							. "Country.{Alias, Code, Name}"
						. "},"
						. "Country.{Alias, Code, Name}"
					. "},"		
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "County.{"
					. "InTourOperatorId,"
					. "Name,"
					. "IsMaster,"
					. "Master.{"
						. "Name,"
						. "IsMaster,"
						. "Country.{Alias, Code, Name}"
					. "},"
					. "Country.{Alias, Code, Name}"
				. "},"
				. "Country.{"
					. "Alias,"
					. "Code, "
					. "Name"
				. "}"
			. "}, "
			. "Content.{"
				. "Seo.{BrowserTitle, Description, Keywords, OgTitle, OgDescription, OgImage}, "
				. "ShortDescription, "
				. "Content,"
				. "Active,"
				. "ImageGallery.{"
					. "Items.{"
						. "Updated,"
						. "Path, "
						. "ExternalUrl, "
						. "RemoteUrl, "
						. "Base64Data, "
						. "TourOperator.{Handle, Abbr, Caption}, "
						. "InTourOperatorId, "
						. "Order, "
						. "Alt"
					. "}"
				. "}"
			. "}, "
			. "Rooms.{InTourOperatorId, Title, TourOperator.*}";

		// sanitize selector
		$useEntity = q_SanitizeSelector("Hotels", $useEntity);

		$_q = $_to_load ? \QQuery($c = "Hotels.{" . $useEntity
			. " WHERE TourOperator.Id='".$this->TourOperatorRecord->getId()."' AND InTourOperatorId " . 
				(($cnt === 1) ? "= '".reset($_to_load)."'" : "IN ('" . implode("','", $_to_load) . "')}"))->Hotels : [];
		
		if (PHP_SAPI === 'cli')
			echo q_date_micro() . " - " . __CLASS__ ."::".__METHOD__." GetETripHotelFromDB QUERY {$c}\n";

		$hotels = $_q;

		if (static::$_CacheData[$this->TourOperatorRecord->getId()])
			static::$_CacheData[$this->TourOperatorRecord->getId()] = [];

		if (!static::$_CacheData[$this->TourOperatorRecord->getId()]["Hotels"])
			static::$_CacheData[$this->TourOperatorRecord->getId()]["Hotels"] = [];

		if ($hotels && (count($hotels) > 0))
		{
			foreach ($hotels as $hotel)
			{
				$rooms = new \QModelArray();
				foreach ($hotel->Rooms ?: [] as $r)
				{
					if (!$r->TourOperator)
						continue;
					$rooms[] = $r;
				}

				$hotel->Rooms = $rooms;
				$_ret_hotels[$hotel->InTourOperatorId] = static::$_CacheData[$this->TourOperatorRecord->getId()]["Hotels"][$hotel->InTourOperatorId] = $hotel;
			}
			//(($indx = $_push_hotels[$hotel->getId()])) ? ($_ret_hotels[$indx] = $hotel) : $_ret_hotels[$_h_pos++] = $hotel;		
		}

		//qvardump($_req_hotels_arr, ($_req_hotels_arr ? $_ret_hotels : $_ret_hotels[reset($hotel_id)]));
		return $_req_hotels_arr ? $_ret_hotels : $_ret_hotels[reset($hotel_id)];
	}

	public function GetETripTourFromDB($tour_id)
	{
		if (!$tour_id)
			throw new \Exception("Tour ID not provided for ETrip!");

		if (!($_req_tours_arr = is_array($tour_id)))
			$tour_id = [$tour_id];

		$_to_load = [];
		$_ret_tours = [];

		foreach ($tour_id as $_tid)
		{
			if (isset(static::$_CacheData[$this->TourOperatorRecord->getId()]["Tours"][$_tid]))
				$_ret_tours[$_tid] = static::$_CacheData[$this->TourOperatorRecord->getId()]["Tours"][$_tid];
			else
				$_to_load[] = $_tid;
		}

		if (($cnt = count($_to_load)) === 0)
			return $_req_tours_arr ? $_ret_tours : $_ret_tours[reset($tour_id)];
		
		$useEntity = "MTime, "
				. "DetailsDocument, "
				. "Code, "
				. "Title, "
				. "Recommended, "
				. "HasSpecialOffer, "
				. "HasBestDeal, "
				
				. "LiveVisitors, "
				. "LastReservationDate,	"

				. "HasActiveOffers, "
				. "HasPlaneActiveOffers, "
				. "HasBusActiveOffers, "
				. "Price, "
				. "Period, "
				. "ShowPeriod, "
				. "TourOperator.{Handle, Caption, Abbr}, "
				. "InTourOperatorId, "
				. "InCategoriesItems.{Category.{Alias, Name, Active, HideTravelItems}, Type}, "
				. "Content.{"
					. "Active,"
					. "Seo.{"
						. "BrowserTitle, "
						. "Description, "
						. "Keywords "
					. "}, "
					. "Title, "
					. "Image, "
					. "ShortDescription, "
					. "Content, "
					. "ImageGallery.{Items.{Updated, ExternalUrl, Type, Path, RemoteUrl, TourOperator.{Handle, Abbr, Caption}}}, "
					. "VideoGallery.{Items.{Updated, Path, Poster, TourOperator.{Handle, Abbr, Caption}}}"
				. "}, "
				. "Location.{"
					. "Street, "
					. "PostCode, "
					. "Details, "
					. "Destination.Name, "
					. "City.{Code, Name}, "
					. "County.{Code, Name}, "
					. "Country.{Code, Name}, "
					. "Latitude, "
					. "Longitude"
				. "}, "
				. "Stages.{"
					. "Content.{Title, ShortDescription, Content, Image}, "
					. "Location.{Street, PostCode, Details, City.{Id, Code, Name}, County.{Id, Code, Name}, Country.{Id, Code, Name}, Latitude, Longitude} "
				. "}";
		// sanitize selector
		$useEntity = q_SanitizeSelector("Tours", $useEntity);

		//qvardump("EXEC QUERY FOR TOUR ", $tour_id);
		$_q = \QQuery($c = "Tours.{" . $useEntity
			. "WHERE TourOperator.Id='".$this->TourOperatorRecord->getId()."' AND InTourOperatorId " . 
				(($cnt === 1) ? "= '".reset($_to_load)."'" : "IN (".implode(",", $_to_load).")}"));

		$tours = $_q->Tours;

		if (static::$_CacheData[$this->TourOperatorRecord->getId()])
			static::$_CacheData[$this->TourOperatorRecord->getId()] = [];

		if (!static::$_CacheData[$this->TourOperatorRecord->getId()]["Tours"])
			static::$_CacheData[$this->TourOperatorRecord->getId()]["Tours"] = [];

		foreach ($tours ?: [] as $tour)
			$_ret_tours[$tour->InTourOperatorId] = static::$_CacheData[$this->TourOperatorRecord->getId()]["Tours"][$tour->InTourOperatorId] = $tour;

		//qvardump($_req_hotels_arr, ($_req_hotels_arr ? $_ret_hotels : $_ret_hotels[reset($hotel_id)]));
		return $_req_tours_arr ? $_ret_tours : $_ret_tours[reset($tour_id)];
	}
	
	public function getGeographyById()
	{
		$fullGeo = $this->SOAPInstance->client->GetGeography();
		$geoById = [];
		$this->geoNodes = [];
		$this->getGeographyById__Rec($fullGeo, $geoById);
		return $geoById;
	}

	protected function getGeographyById__Rec($geoNode, &$collector = [])
	{
		if (isset($collector[$geoNode->Id]))
			throw new \Exception("Duplicate geo id: " . $geoNode->Id);
		
		$newGeoNode = new \stdClass();
		$newGeoNode->Id = $geoNode->Id;
		$newGeoNode->Name = $geoNode->Name;
		$newGeoNode->ChildLabel = $geoNode->ChildLabel;
		$collector[$geoNode->Id] = $newGeoNode;
		$this->geoNodes[$geoNode->Id] = $geoNode;

		foreach ($geoNode->Children ?: [] as $child)
		{
			$childNode = $this->getGeographyById__Rec($child, $collector);
			if (!$newGeoNode->Children)
				$newGeoNode->Children = [];
			$newGeoNode->Children[$childNode->Id] = $childNode;
			$childNode->Parent = $newGeoNode;
		}
		return $collector[$geoNode->Id];
	}

	/**
	 * 
	 * @param boolean $tours
	 * @return type
	 * @throws \Exception
	 */
	protected function saveCachedData($tours = false, $force = false, $config = [])
	{
		/**
		 * CONFIG ZONE
		 */

		if (!$this->TourOperatorRecord)
			throw new \Exception("Tour OP not found!");

		$doDebug = $_GET['debug'] ?? false;
		$t_init = microtime(true);
		$t1 = microtime(true);

		try
		{
			// first we must resync countries
			#$this->resyncCountries();

			$transportCacheCls = $tours ? "\Omi\TF\TourTransport" : "\Omi\TF\CharterTransport";
			$transportCacheProp = $tours ? "ToursTransports" : "ChartersTransports";
			$requestMethod = $tours ? "PullTours" : "PullChartersHotels";
			$transportsType = ($tours ? 'tour' : 'charter');

			$callMethod = "GetPackages";
			$callKeyIdf = $tours ? "tours" : "charters";
			$callParams = [];

			$refThis = $this;
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
				$reqEX = null;
				try
				{
					$return = $refThis->SOAPInstance->request($method, $params);
				}
				catch (\Exception $ex)
				{
					$reqEX = $ex;
				}
				return [$return, $refThis->SOAPInstance->client->__getLastRequest(), $refThis->SOAPInstance->client->__getLastResponse(), $reqEX];
			}, $callMethod, $callParams, $callKeyIdf, $force);
			
			if ((!$return) || ($alreadyProcessed && (!$force)))
			{
				if ($this->TrackReport)
				{
					if (!$return)
						$this->TrackReport->_noResponse = true;
					if ($alreadyProcessed)
						$this->TrackReport->_responseNotChanged = true;
				}
				echo $alreadyProcessed ? "Nothing changed from last request!" : "No return";
				return;
			}

			$packages = $return;

			$initialTime = microtime(true);	

			$appData = \QApp::NewData();
			$appData->Countries = new \QModelArray();
			$appData->{$transportCacheProp} = new \QModelArray();
			$objs = [];

			$__todayTime = strtotime(date("Y-m-d"));

			#$reqs_out = "";

			$showTopData = ($_GET["show_top_data"] || ($config && $config["show_top_data"]));
			#$showAllPackages = ($showTopData && $_GET["with_extra_source_hotels"]);
			$showAllTransports = ($showTopData && $_GET["with_extra_transports"]);
			
			$hotelsToBeProcessed = [];
			$toursToBeProcessed = [];
			$cacheDestinations = [];
			$respTransports = [];

			$packagesById = [];

			$toLoadHotels = [];
			$toLoadTours = [];

			foreach ($packages ?: [] as $package)
			{
				// filter by type
				$passes = (($tours && $package->IsTour) || ((!$tours) && (!$package->IsTour)));

				// filter by all data like destination, hotel, departure points, etc
				if ((!$passes) || (!$package->Hotel) || (!$package->Destination) || (!$package->DeparturePoints))
				{
					if ((!$package->Hotel) || (!$package->Destination) || (!$package->DeparturePoints))
						echo "<div style='color: red;'>Skip package [{$package->Id}] - missing one of the following: hotel/destination/departure point</div>";
					continue;
				}

				if ($package->HotelSource)
					$package->Hotel .= "|" . $package->HotelSource;

				// determine transport type
				$transportType = $package->IsBus ? "bus" : ($package->IsFlight ? "plane" : "individual");

				// individual not ok!
				if ((!(in_array($transportType, ["plane", "bus"]))) && (!$showAllTransports))
				{
					echo "<div style='color: red;'>Skip package [{$package->Id}] - transport type [{$transportType}] is not accepted</div>";
					continue;
				}

				$destinationObj = null;
				if (!isset($cacheDestinations[$package->Destination]))
				{
					$destinationObj = $this->getDestination($package->Destination, $objs, true);
					$cacheDestinations[$package->Destination] = $destinationObj;
				}
				else
				{
					$destinationObj = $cacheDestinations[$package->Destination];
				}
				
				if ((!$destinationObj) || (!$destinationObj->getId()))
				{
					#qvardump('$cacheDestinations', $package->Destination, $cacheDestinations);
					echo "<div style='color: red;'>Destination not found [{$package->Destination}]</div>";
					continue;
				}

				$syncTravelItems = static::$Config[$this->TourOperatorRecord->Handle]["sync_travel_items_on"][$transportsType][$transportType];

				$packagesById[$package->Id] = $package;
				
				$destIsCity = false;
				$destIsCounty = false;
				$destIsCountry = false;
				$destIsCustom = false;

				$departIsCity = false;
				$departIsCounty = false;
				$departIsCountry = false;
				$departIsCustom = false;

				// populate destination city with data
				if (($destIsCity = ($destinationObj instanceof \Omi\City)))
				{

				}
				else if (($destIsCounty = ($destinationObj instanceof \Omi\County)))
				{
					
				}
				else if (($destIsCountry = ($destinationObj instanceof \Omi\Country)))
				{
					
				}
				else if (($destIsCustom = ($destinationObj instanceof \Omi\TF\Destination)))
				{
					
				}

				$destinationIndx = ($destinationObj instanceof \Omi\Country) ? 
					$destinationObj->getTourOperatorIdentifier($this->TourOperatorRecord) : $destinationObj->InTourOperatorId;

				$hotelsToBeProcessed[$package->Hotel][] = [$package, $destinationObj, $destIsCity, $destIsCounty, $destIsCountry];
				if ($tours)
				{
					$tourId = $package->Id ?: $package->PackageId;
					$toursToBeProcessed[$tourId][] = $package;
				}

				foreach ($package->DeparturePoints ?: [] as $departure)
				{
					
					$departureObj = null;
					if (!isset($cacheDestinations[$departure]))
					{
						$departureObj = $this->getDestination($departure, $objs, true);
						$cacheDestinations[$departure] = $departureObj;
					}
					else
					{
						$departureObj = $cacheDestinations[$departure];
					}

					#$departureObj = $cacheDestinations[$departure] ?: ($cacheDestinations[$departure] = $this->getDestination($departure, $objs, true));

					if ((!$departureObj) || (!$departureObj->getId()))
					{
						echo "<div style='color: red;'>Departure not found [{$departure}]</div>";
						continue;
					}

					// populate destination city with data
					if (($departIsCity = ($departureObj instanceof \Omi\City)))
					{

					}
					else if (($departIsCounty = ($departureObj instanceof \Omi\County)))
					{

					}
					else if (($departIsCountry = ($departureObj instanceof \Omi\Country)))
					{

					}
					else if (($departIsCustom = ($departureObj instanceof \Omi\TF\Destination)))
					{

					}

					if (!$departIsCity)
					{
						//echo "<div style='color: red;'>Date [{$date}] from package [{$package->Id}] was skipped because it had passed</div>";
						if (\QAutoload::GetDevelopmentMode())
							qvardump('$package', $package);
						echo "<div style='color: red;'>Departure can only be city</div>";
						continue;
					}

					$departureIndx = ($departureObj instanceof \Omi\Country) ? 
							$departureObj->getTourOperatorIdentifier($this->TourOperatorRecord) : $departureObj->InTourOperatorId;

					/*
					$ctInTourOperatorId = $tours ? 
						$this->TourOperatorRecord->Handle . "|" 
							. $transportType . "|" . $departureObj->getType() . "::" . $departureIndx . "|" 
							. $destinationObj->getType() . "::" . $destinationIndx : 
						$transportType . "~" . $departureObj->getType() . "|" . $departureIndx . "~" 
							. $destinationObj->getType() . "|" . $destinationIndx;
					*/

					$ctInTourOperatorId = $tours ? 
						$this->TourOperatorRecord->Handle . "|" 
							. $transportType . "|" . $departureObj->getType() . "::" . $departureIndx . "|" 
							. $destinationObj->getType() . "::" . $destinationIndx : 
						$transportType . "~" . $departureObj->getType() . "|" . $departureIndx . "~" 
							. $destinationObj->getType() . "|" . $destinationIndx;

					foreach ($package->DepartureDates ?: [] as $date)
					{
						$isArr = is_array($date);
						$date = $isArr ? $date[0] : $date;
						// we may have dates that passed - skip them
						if (strtotime($date) < $__todayTime)
						{
							//echo "<div style='color: red;'>Date [{$date}] from package [{$package->Id}] was skipped because it had passed</div>";
							continue;
						}

						$useDuration = $package->Duration;
						if (($translatedDuration = $this->getTranslatedPeriod($transportType, $date, $departure, $package->Destination, $package->Duration)))
							$useDuration = $translatedDuration;

						$originalDeparture = $departure;
						$originalDestination = $package->Destination;

						#echo $originalDeparture . " -> " . $originalDestination . "<br/>";

						if (!($retTransport = $respTransports[$ctInTourOperatorId]))
						{
							$retTransport = new \stdClass();
							$retTransport->Id = $ctInTourOperatorId;
							$retTransport->InTourOperatorId = $ctInTourOperatorId;
							$retTransport->TransportType = $transportType;
							
							$retTransport->From = new \stdClass();
							if ($departIsCity)
							{
								$retTransport->From->City = ($objs['cache_cities'][$departureIndx] ?: ($objs['cache_cities'][$departureIndx] = new \stdClass()));
								$retTransport->From->City->Id = $departureIndx;
								if ($departureObj && $departureObj->Name)
									$retTransport->From->City->Name = $departureObj->Name;
							}
							else if ($departIsCounty)
							{
								$retTransport->From->County = ($objs['cache_counties'][$departureIndx] ?: ($objs['cache_counties'][$departureIndx] = new \stdClass()));
								$retTransport->From->County->Id = $departureIndx;
								if ($departureObj && $departureObj->Name)
									$retTransport->From->County->Name = $departureObj->Name;
							}
							else if ($departIsCountry)
							{
								$retTransport->From->Country = ($objs['cache_countries'][$departureIndx] ?: ($objs['cache_countries'][$departureIndx] = new \stdClass()));
								$retTransport->From->Country->Id = $departureIndx;
								if ($departureObj)
								{
									if ($departureObj->Code)
										$retTransport->From->Country->Code = $departureObj->Code;
									if ($departureObj->Name)
										$retTransport->From->Country->Name = $departureObj->Name;
								}
							}
							else if ($departIsCustom)
							{
								$retTransport->From->Destination = ($objs['cache_destination'][$departureIndx] ?: ($objs['cache_destination'][$departureIndx] = new \stdClass()));
								$retTransport->From->Destination->Id = $departureIndx;
								if ($departureObj && $departureObj->Name)
									$retTransport->From->County->Name = $departureObj->Name;
							}

							$retTransport->To = new \stdClass();
							if ($destIsCity)
							{
								$retTransport->To->City = ($objs['cache_cities'][$destinationIndx] ?: ($objs['cache_cities'][$destinationIndx] = new \stdClass()));
								$retTransport->To->City->Id = $destinationIndx;
								if ($destinationObj && $destinationObj->Name)
									$retTransport->To->City->Name = $destinationObj->Name;
							}
							else if ($destIsCounty)
							{
								$retTransport->To->County = ($objs['cache_counties'][$destinationIndx] ?: ($objs['cache_counties'][$destinationIndx] = new \stdClass()));
								$retTransport->To->County->Id = $destinationIndx;
								if ($destinationObj && $destinationObj->Name)
									$retTransport->To->County->Name = $destinationObj->Name;
							}
							else if ($destIsCountry)
							{
								$retTransport->To->Country = ($objs['cache_countries'][$destinationIndx] ?: ($objs['cache_countries'][$destinationIndx] = new \stdClass()));
								$retTransport->To->Country->Id = $destinationIndx;
								if ($destinationObj)
								{
									if ($destinationObj->Code)
										$retTransport->To->Country->Code = $destinationObj->Code;
									if ($destinationObj->Name)
										$retTransport->To->Country->Name = $destinationObj->Name;
								}
							}
							else if ($destIsCustom)
							{
								$retTransport->To->Destination = ($objs['cache_destination'][$destinationIndx] ?: ($objs['cache_destination'][$destinationIndx] = new \stdClass()));
								$retTransport->To->Destination->Id = $destinationIndx;
								if ($destinationObj && $destinationObj->Name)
									$retTransport->To->Destination->Name = $destinationObj->Name;
							}
						}
						
						$retTransport->FromOriginalID = $originalDeparture;
						$retTransport->ToOriginalID = $originalDestination;

						if (!$retTransport->Dates)
							$retTransport->Dates = [];

						if (!($dateObj = $retTransport->Dates[$date]))
						{
							$dateObj = new \stdClass();
							$dateObj->Date = $date;
							$retTransport->Dates[$date] = $dateObj;
						}

						if (!$dateObj->Nights)
							$dateObj->Nights = [];

						if (!($nightsObj = $dateObj->Nights[$useDuration]))
						{
							$nightsObj = new \stdClass();
							$nightsObj->Nights = $useDuration;
							$dateObj->Nights[$useDuration] = $nightsObj;
						}

						if (!$nightsObj->PackagesIds)
							$nightsObj->PackagesIds = [];
						$nightsObj->PackagesIds[$package->Id] = $package->Id;

						if ($syncTravelItems)
						{
							if ($tours)
							{
								if ($tourId)
								{
									if (!($tObj = $toLoadTours[$tourId]))
									{
										$tObj = new \stdClass();
										$tObj->Id = $tourId;
										$toLoadTours[$tourId] = $tObj;
									}
									if (!$nightsObj->Tours)
										$nightsObj->Tours = [];
									$nightsObj->Tours[$tObj->Id] = $tObj;
								}
							}
							else
							{
								if ($package->Hotel)
								{
									if (!($hObj = $toLoadHotels[$package->Hotel]))
									{
										$hObj = new \stdClass();
										$hObj->Id = $package->Hotel;
										$toLoadHotels[$package->Hotel] = $hObj;
									}
									if (!$nightsObj->Hotels)
										$nightsObj->Hotels = [];
									$nightsObj->Hotels[$hObj->Id] = $hObj;
								}
							}
						}

						if (!isset($respTransports[$ctInTourOperatorId]))
							$respTransports[$ctInTourOperatorId] = $retTransport;
					}
				}
			}
			
			if ($doDebug)
			{
				qvardump('after process packages', "Took: " . (microtime(true) - $t1) . ' seconds', 
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB"
				);
				$t1 = microtime(true);
			}
			
			//$debug = true;
			if ($showTopData)
			{
				$geoById = $this->getGeographyById();
				#$transportsByDate = [];
				foreach ($respTransports ?: [] as $respTranspInTopId => $respTransport)
				{
					/*
					$destinationId = $respTransport->To ? 
						($respTransport->To->City ? $respTransport->To->City->Id : 
							($respTransport->To->County ? $respTransport->To->County->Id : 
								($respTransport->To->Country ? $respTransport->To->Country->Id : 
									($respTransport->To->Destination ? $respTransport->To->Destination->Id : null)
								)
							)
						) : null;
					*/
					
					$destinationId = $respTransport->ToOriginalID;

					if ((!$destinationId) || (!($_dest = $geoById[$destinationId])))
					{
						echo "<div style='color: red;'>Destination not found for id [{$destinationId}] on transport: [{$respTranspInTopId}]</div>";
						continue;
					}

					/*
					$departureId = $respTransport->From ? 
						($respTransport->From->City ? $respTransport->From->City->Id : 
							($respTransport->From->County ? $respTransport->From->County->Id : 
								($respTransport->From->Country ? $respTransport->From->Country->Id : 
									($respTransport->From->Destination ? $respTransport->From->Destination->Id : null)
								)
							)
						) : null;
					*/

					$departureId = $respTransport->FromOriginalID;

					if (!($_depart = $geoById[$departureId]))
					{
						echo "<div style='color: red;'>Departure not found for id [{$departureId}] on transport: [{$respTranspInTopId}]</div>";
						continue;
					}

					$fromGeoItm = $respTransport->From ? 
						($respTransport->From->City ?: ($respTransport->From->County ?: ($respTransport->From->Country ?: $respTransport->From->Destination))) : null;
					$toGeoItm = $respTransport->To ? 
						($respTransport->To->City ?: ($respTransport->To->County ?: ($respTransport->To->Country ?: $respTransport->To->Destination))) : null;

					echo "<div style='color: green;'>[{$respTranspInTopId}] {$fromGeoItm->Name} to {$toGeoItm->Name}</div>";

					ksort($respTransport->Dates);
					foreach ($respTransport->Dates ?: [] as $date => $dateObj)
					{
						$checkin = date("Y-m-d", strtotime($date));
						echo "<div style='color: green; padding-left: 50px;'>{$checkin}</div>";
						ksort($dateObj->Nights);
						foreach ($dateObj->Nights ?: [] as $duration => $nightsObj)
						{
							echo "<div style='color: green; padding-left: 100px;'>{$duration}</div>";
							if ($tours)
							{
								foreach ($nightsObj->PackagesIds ?: [] as $packId)
								{
									if (($nightObjPackage = $packagesById[$packId]))
									{
										echo "<div style='color: green; padding-left: 150px;'>Package {$nightObjPackage->Name}</div>";
									}
									else
									{
										echo "<div style='color: red; padding-left: 150px;'>Package [{$packId}] not found</div>";
									}
								}
							}
						}
					}
				}

				return;
			}

			// save hotels and tours
			$this->saveCachedData_saveHotelsAndTours($hotelsToBeProcessed, $toursToBeProcessed);


			if ($doDebug)
			{
				qvardump('after saveCachedData_saveHotelsAndTours', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB"
				);
				$t1 = microtime(true);
			}

			$hotelsByInTopId = [];
			if ($toLoadHotels)
			{
				$dbHotels = QQuery('Hotels.{Id, Name, InTourOperatorId WHERE TourOperator.Handle=? AND InTourOperatorId IN (?)}', 
					[$this->TourOperatorRecord->Handle, array_keys($toLoadHotels)])->Hotels;
				foreach ($dbHotels ?: [] as $hotel)
					$hotelsByInTopId[$hotel->InTourOperatorId] = $hotel;
			}
			
			$toursByInTopId = [];
			if ($toLoadTours)
			{
				$dbTours = QQuery('Tours.{Id, Name, InTourOperatorId WHERE TourOperator.Handle=? AND InTourOperatorId IN (?)}', 
					[$this->TourOperatorRecord->Handle, array_keys($toLoadTours)])->Tours;
				foreach ($dbTours ?: [] as $tour)
					$toursByInTopId[$tour->InTourOperatorId] = $tour;
			}
			
			if ($doDebug)
			{
				qvardump('after loading hotels and tours', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB"
				);
				$t1 = microtime(true);
			}

			$t1 = microtime(true);
			$batchSize = 20;

			$transportsBatches = [];
			$batchIndx = 0;
			$respTransportsPos = 0;
			foreach ($respTransports ?: [] as $transportIndx => $respTransport)
			{
				if (($respTransportsPos > 0) && ($respTransportsPos % $batchSize === 0))
				{
					$batchIndx++;
				}
				$respTransportsPos++;
				$transportsBatches[$batchIndx][$transportIndx] = $respTransport;
			}

			if ($doDebug)
			{
				qvardump('after prepare batches', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB");
				$t1 = microtime(true);
			}
			
			$toUpdateHotelsStatuses = [];
			$toUpdateToursStatuses = [];
			

			$requestsUUIDS_All = [];
			$processedTransports = [];
			foreach ($transportsBatches ?: [] as $respTransports_part)
			{
				$respTransportsInTopIds = array_keys($respTransports_part);

				$f_respTransport = reset($respTransports_part);
				$f_respTransport_transportType = $f_respTransport ? $f_respTransport->TransportType : null;

				if (!$f_respTransport_transportType)
				{
					echo "<div style='color: red;'>No transport type on batch items [" . implode(',', $respTransportsInTopIds) . "]</div>";
					continue;
				}

				$syncTravelItems = static::$Config[$this->TourOperatorRecord->Handle]["sync_travel_items_on"][$transportsType][$f_respTransport_transportType];
				$withTravelItems = $syncTravelItems ? true : false;

				$existingTransports = $respTransportsInTopIds ? $this->getExistingTransportsByTopsIds($respTransportsInTopIds, $transportsType, $withTravelItems) : [];

				$requests = [];
				$toProcessTransports = [];
				foreach ($respTransports_part ?: [] as $respTransportInTopId => $respTransport)
				{
					$saveTransport = false;

					if (!($transportType = $respTransport->TransportType))
					{
						echo "<div style='color: red;'>Missing transport type on  transport [{$respTransportInTopId}]</div>";
						continue;
					}

					if (!$respTransport->Dates)
					{
						echo "<div style='color: red;'>Missing dates on transport [{$respTransportInTopId}]</div>";
						continue;
					}

					/*
					$destinationId = $respTransport->To ? 
						($respTransport->To->City ? $respTransport->To->City->Id : 
							($respTransport->To->County ? $respTransport->To->County->Id : 
								($respTransport->To->Country ? $respTransport->To->Country->Id : 
									($respTransport->To->Destination ? $respTransport->To->Destination->Id : null)
								)
							)
						) : null;
					 * 
					 */

					$destinationId = $respTransport->ToOriginalID;

					$_dest = null;
					if ((!$destinationId) || (!($_dest = $cacheDestinations[$destinationId])))
					{
						echo "<div style='color: red;'>Destination not found for id [{$destinationId}] on transport: [{$respTransportInTopId}]</div>";
						continue;
					}

					/*
					$departureId = $respTransport->From ? 
						($respTransport->From->City ? $respTransport->From->City->Id : 
							($respTransport->From->County ? $respTransport->From->County->Id : 
								($respTransport->From->Country ? $respTransport->From->Country->Id : 
									($respTransport->From->Destination ? $respTransport->From->Destination->Id : null)
								)
							)
						) : null;
					 */
					
					$departureId = $respTransport->FromOriginalID;

					$_depart = null;
					if ((!$departureId) || (!($_depart = $cacheDestinations[$departureId])))
					{
						echo "<div style='color: red;'>Departure not found for id [{$departureId}] on transport: [{$respTransportInTopId}]</div>";
						continue;
					}

					if (!($toProcessTransport = $existingTransports[$respTransportInTopId]))
					{
						$toProcessTransport = new $transportCacheCls();
						$this->setupTransport($toProcessTransport, $transportType, $_depart, $_dest, $respTransportInTopId);
					}

					$destCountryId = null;
					if ($toProcessTransport->To->City || $toProcessTransport->To->County || $toProcessTransport->To->Country)
					{
						if ($toProcessTransport->To->City && $toProcessTransport->To->City->Country)
							$destCountryId = $toProcessTransport->To->City->Country->getId();
						else if ($toProcessTransport->To->County && $toProcessTransport->To->County->Country)
							$destCountryId = $toProcessTransport->To->County->Country->getId();
						else if ($toProcessTransport->To->Country)
							$destCountryId = $toProcessTransport->To->Country->getId();
					}
					$useCurrency = $destCountryId ? $this->getConfigCurrency(["CountryCode" => $destCountryId]) : static::$DefaultCurrency;

					$processedDates = [];
					foreach ($respTransport->Dates ?: [] as $retDateObj)
					{
						if (!$retDateObj->Date)
						{
							echo "<div style='color: red;'>Date is missing on transport [{$respTransportInTopId}]</div>";
							continue;
						}

						$checkin = date("Y-m-d", strtotime($retDateObj->Date));
						//echo "<div style='color: green; padding-left: 50px;'>Checkin [{$checkin}]</div>";

						// setup transport date
						list($dateObj) = $this->setupTransportDate($toProcessTransport, $checkin, $transportType);

						$processedNights = [];
						foreach ($retDateObj->Nights ?: [] as $retNightsObj)
						{
							if (!$retNightsObj->Nights)
							{
								echo "<div style='color: red;'>Duration is missing on date [{$checkin}] on transport [{$respTransportInTopId}]</div>";
								continue;
							}
							list($nightsObj, $saveNights) = $this->setupTransportDateDuration($toProcessTransport, $dateObj, $retNightsObj->Nights);
							if ($saveNights)
								$saveTransport = true;
							$processedNights[$nightsObj->Nights] = $nightsObj->Nights;

							if ($syncTravelItems)
							{
								$hasChangesOnPeriodTravelItms = $this->cacheTransports__SyncTravelItems($nightsObj, $retNightsObj->Hotels, $retNightsObj->Tours, 
									$hotelsByInTopId, $toursByInTopId, $transportsType, $transportType, false);
								if ($hasChangesOnPeriodTravelItms)
									$saveTransport = true;
								
								foreach ($nightsObj->Hotels ?: [] as $hotel)
									$toUpdateHotelsStatuses[$transportType][$hotel->getId() ?: $hotel->getTemporaryId()] = $hotel;
								foreach ($nightsObj->Tours ?: [] as $tour)
									$toUpdateToursStatuses[$transportType][$tour->getId() ?: $tour->getTemporaryId()] = $tour;
							}

							$noRequest = static::$Config[$this->TourOperatorRecord->Handle]['no_requests_on'][$transportsType][$transportType];

							if (!$noRequest)
							{
								$reqDestinationId = $destinationId;
								$reqDestinationType = $_dest->getType();

								if (isset(static::$Config[$this->TourOperatorRecord->Handle]['force_requests_on_zone']) 
									&& isset($toProcessTransport->To->City->County->InTourOperatorId) 
									&& isset(static::$Config[$this->TourOperatorRecord->Handle]['force_reqs_on_county'][$toProcessTransport->To->City->County->InTourOperatorId]))
								{
									$reqDestinationId = $toProcessTransport->To->City->County->InTourOperatorId;
									$reqDestinationType = $toProcessTransport->To->City->County->getType();
									#echo "<div style='color: red;'>FORCE ON REQ:</div>";
								}

								#echo $_dest->getId() . " | " . $reqDestinationId . " | " . " | " . $reqDestinationType . " | " . $_dest->getName() . " | " . $_dest->getType() . "<br/>";
								#qvardump(isset($toProcessTransport->To->City->County) ? $toProcessTransport->To->City->County->Name  : "NO County", 
								#	$toProcessTransport->To->City->County);

								$params = [
									"Destination" => $reqDestinationId, 
									"DestinationType" => $reqDestinationType, 
									"IsTour" => $tours,
									"IsFlight" => ($transportType === 'plane'),
									"IsBus" => ($transportType === 'bus'),
									"DepartureDate" => $checkin,
									"Departure" => $departureId,
									"Duration" => $nightsObj->Nights,
									"Rooms" => ["Room" => ["Adults" => 2, "ChildAges" => []]],
									"MinStars" => 0, 
									"ShowBlackedOut" => true,
									"Currency" => $useCurrency
								];

								#echo "\$reqDestinationId: " . $reqDestinationId . "<br/>";

								$request = new \Omi\TF\Request();
								$request->setAddedDate(date("Y-m-d H:i:s"));
								$request->setClass(get_called_class());
								$request->setMethod($requestMethod);
								$request->setType(($tours ? \Omi\TF\Request::Tour : \Omi\TF\Request::Charter));
								$request->setParams(json_encode([$this->TourOperatorRecord->Handle, $params, $nightsObj->Nights]));
								$request->setTransportType($transportType);
								$request->setDestinationIndex($destinationId);
								$request->setDepartureIndex($departureId);
								$request->setDepartureDate($checkin);
								$request->setDuration($nightsObj->Nights);
								$request->setTourOperator($this->TourOperatorRecord);
								$request->setupUniqid();

								$reqUUID = $request->UniqId;
								if (!($useReq = $requests[$reqUUID]))
									$requests[$reqUUID] = $useReq = $request;
								if (!$useReq->TransportsNights_Raw)
									$useReq->TransportsNights_Raw = new \QModelArray();
								$useReq->TransportsNights_Raw[] = $nightsObj;

								/*
								$req_indx = $request->TourOperator->Handle . "_"
									. $request->TransportType . "_"
									. $request->DepartureIndex . "_"
									. $request->DestinationIndex . "_"
									. ($request->DepartureDate ? date("Y-m-d", strtotime($request->DepartureDate)) : "") . "_"
									. $request->Duration;

								echo "REQ_INDX for nights [" . $nightsObj->getId() . "] : " . $req_indx . '<br/>';
								*/
							}
							else if (!$nightsObj->Active)
							{
								#qvardump('activate the night!', $nightsObj->getId());
								$nightsObj->setActive(true);
								$nightsObj->__reactivated = true;
								$nightsObj->setFromTopAddedDate(date("Y-m-d H:i:s"));
								$nightsObj->setFromTopRemoved(false);
								$nightsObj->setFromTopRemovedAt(null);
								$saveNights = true;
								$saveTransport = true;
								if (!$dateObj->Active)
									$dateObj->setActive(true);
							}
						}

						if (count($processedNights) > 0)
						{
							$processedDates[$checkin] = $checkin;
							$added = $this->addDateToTransport($toProcessTransport, $dateObj);
							if ($added || $dateObj->__saved)
								$saveTransport = true;
						}
						else
						{
							echo "<div style='color: red;'>No processed nights of date [{$date}] for [{$retTransport->Id}|{$retTransport->Identifier}]</div>";
						}
						foreach ($dateObj->_nights ?: [] as $_nObj)
						{
							if (!isset($processedNights[$_nObj->Nights]))
							{
								$_nObj->markForCleanup();
								$saveTransport = true;
							}
						}
					}
					foreach ($toProcessTransport->_dates ?: [] as $_dindx => $dateObj)
					{
						if (!isset($processedDates[$_dindx]))
						{
							$dateObj->markForCleanup();
							$saveTransport = true;
						}
					}
					if (count($processedDates) > 0)
					{
						$processedTransports[$respTransportInTopId] = $respTransportInTopId;
						if ($saveTransport)
							$toProcessTransports[$respTransportInTopId] = $toProcessTransport;
					}
					else
					{
						echo "<div style='color: red;'>No processed dates for [{$retTransport->Id}|{$retTransport->Identifier}]</div>";
					}
				}

				if ($toProcessTransports)
				{
					// filter transports
					$toSaveTransports = $this->saveCachedData_filterTransports($toProcessTransports);
					
					if (count($toSaveTransports))
					{
						$this->saveInBatch($toSaveTransports, $transportCacheProp, true, 5);
					}

					$transportsIds = [];
					foreach ($toProcessTransports ?: [] as $transport)
						$transportsIds[$transport->getId()] = $transport->getId();
					if (count($transportsIds))
					{
						$tours ? 
							\Omi\TF\TransportDateNights::QSyncForTours(["IdIN" => [$transportsIds]]) : 
							\Omi\TF\TransportDateNights::QSyncForCharters(["IdIN" => [$transportsIds]]);
					}

				}

				if (count($requests))
				{
					// save requests after transports are saved
					$requestsUUIDS = $this->saveCachedData_saveRequests($requests);
					foreach ($requestsUUIDS ?: [] as $reqUUID)
						$requestsUUIDS_All[] = $reqUUID;
				}

			}

			$this->saveCachedData_doRequestsCleanup($requestsUUIDS_All, ["VacationType" => $tours ? "tour" : "charter"]);

			if (count($toUpdateHotelsStatuses))
			{
				foreach ($toUpdateHotelsStatuses ?: [] as $transportType => $tuph)
				{
					\Omi\TFuse\Api\TravelFuse::UpdateHotelsChartersActiveFlags($tuph, ($transportType == 'bus'));
					$this->flagHotelsTravelItems($tuph);
				}
			}

			if (count($toUpdateToursStatuses))
			{
				foreach ($toUpdateToursStatuses ?: [] as $transportType => $tupt)
				{
					\Omi\TFuse\Api\TravelFuse::UpdateToursActiveFlags($tupt, ($transportType == 'bus'));
					$this->flagToursTravelItems($tupt);
				}
			}

			if ($doDebug)
			{
				qvardump('after processing batches', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB");
				$t1 = microtime(true);
			}

			// do cleanup for rest of transports
			$this->cleanupTransports($processedTransports, $transportCacheProp);

			if ($doDebug)
			{
				qvardump('after cleanupTransports', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB"
				);
				$t1 = microtime(true);
			}

			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $initialTime));

			if ($doDebug)
			{
				qvardump('after setupSoapResponseAndProcessingStatus', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB"
				);
				$t1 = microtime(true);
			}

			$tours ? \Omi\TF\TransportDateNights::LinkRequests_ForTours($this->TourOperatorRecord->Handle) : 
				\Omi\TF\TransportDateNights::LinkRequests_ForCharters($this->TourOperatorRecord->Handle);

			if ($doDebug)
			{
				qvardump('after LinkRequests', "Took: " . (microtime(true) - $t1) . ' seconds',
					"Entire process till now took: " . (microtime(true) - $t_init) . ' seconds',
					"Memory usage: " . (round(memory_get_usage()/1048576, 2)) . " MB", 
					"Memory peak usage: " . (round(memory_get_peak_usage()/1048576, 2)) . " MB"
				);
				$t1 = microtime(true);
			}
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		
		static::$_CacheData = [];
		static::$_LoadedCacheData = [];
	}

	public function saveCachedData_markForCleanup($appData, $transportCacheProp, $existingTransports, $processedTransports, $transportsProcessedDates)
	{
		foreach ($existingTransports ?: [] as $eindx => $etrans)
		{
			$__destination = $etrans->getTransportDestination();
			$__departure = $etrans->getTransportFrom();

			if ((!$__departure) || (!$__destination))
			{
				qvardump("\$etrans", $etrans);
				throw new \Exception(!$__destination ? "NO DESTINATION??" : "NO DEPARTURE??");
			}

			if (!isset($processedTransports[$eindx]))
			{
				//qvardump("\$processedTransports", $processedTransports);
				//echo "<div style='color: red;'>RM TRANSPORT: [{$eindx}]</div>";
				$etrans->markForCleanup(true);
				if (!isset($appData->{$transportCacheProp}[$eindx]))
					$appData->{$transportCacheProp}[$eindx] = $etrans;
				// we deactivate all dates for the transport
				continue;
			}
			$transpProcDates = $transportsProcessedDates[$eindx] ?: [];
			foreach ($etrans->_dates ?: [] as $date => $dateObj)
			{
				if (!isset($transpProcDates[$date]))
				{
					//qvardump("\$etrans->_dates", $etrans->_dates);
					//echo "<div style='color: red;'>RM TRANSPORT AND DATE: [{$eindx}] [{$date}]</div>";
					// we deactivate all nights for the date
					if (!isset($appData->{$transportCacheProp}[$eindx]))
						$appData->{$transportCacheProp}[$eindx] = $etrans;
					$dateObj->markForCleanup();
					continue;
				}

				$transpProcDatesNights = $transpProcDates[$date] ?: [];
				foreach ($dateObj->_nights ?: [] as $nights => $nightsObj)
				{
					if (!isset($transpProcDatesNights[$nights]))
					{
						if (!isset($appData->{$transportCacheProp}[$eindx]))
							$appData->{$transportCacheProp}[$eindx] = $etrans;
						//qvardump("\$dateObj->_nights", $dateObj->_nights);
						//echo "<div style='color: red;'>RM TRANSPORT NIGHTS FROM DATE: [{$eindx}] [{$date}] [{$nights}]</div>";
						$nightsObj->markForCleanup();
					}
				}
			}
		}
	}

	public function saveCachedData_saveHotelsAndTours($hotelsToBeProcessed, $toursToBeProcessed)
	{
		$existingHotels = count($hotelsToBeProcessed) ? QQuery("Hotels.{Name, Stars, Code, InTourOperatorId, "
			. "Address.{City, Destination, County, Country, Latitude, Longitude} "
			. "Content.{*, ImageGallery.{*, Items.{*, TourOperator.*}}} "
			. " WHERE 1 "
				. "??TourOperator?<AND[TourOperator.Id=?]"
				. "??InTourOperatorIdIN?<AND[InTourOperatorId IN (?)]"
			. "}", ["TourOperator" => $this->TourOperatorRecord->getId(), "InTourOperatorIdIN" => [array_keys($hotelsToBeProcessed)]])->Hotels : null;
		$existingHotelsByTopIds = [];
		foreach ($existingHotels ?: [] as $ehotel)
			$existingHotelsByTopIds[$ehotel->InTourOperatorId] = $ehotel;

		$hotelsAppData = \QApp::NewData();
		$hotelsAppData->setHotels(new \QModelArray());
		foreach ($hotelsToBeProcessed ?: [] as $hotelID => $hotelPackages)
		{
			if (!($dbHotel = $existingHotelsByTopIds[$hotelID]))
			{
				echo "<div style='color: red;'>Hotel cannot be processed because is not in db [{$hotelID}]</div>";
				continue;
			}

			$hotelDestinations = [];
			foreach ($hotelPackages ?: [] as $packageData)
			{
				list($package, $destination, $destIsCity, $destIsCounty, $destIsCountry) = $packageData;
				$destinationIndx = get_class($destination) . "-" . $destination->getId();
				$hotelDestinations[$destinationIndx] = $destinationIndx;
			}

			if (count($hotelDestinations) > 1)
			{
				echo "<div style='color: red;'>Multiple destinations on hotel [{$hotelID}] - hotel will be linked to last destination</div>";
			}

			// populate hotel address here in case we don't already have it
			if (!$dbHotel->Address)
				$dbHotel->Address = new \Omi\Address();

			// the address is empty
			if ((!$dbHotel->Address->City) && (!$dbHotel->Address->County) && (!$dbHotel->Address->Country) && (!$dbHotel->Address->Destination))
			{
				if ($destIsCity)
				{
					$dbHotel->Address->setDestination(null);
					$dbHotel->Address->setCity($destination);
					$dbHotel->Address->setCounty(null);
					$dbHotel->Address->setCountry(null);
				}
				else if ($destIsCounty)
				{
					$dbHotel->Address->setDestination(null);
					$dbHotel->Address->setCity(null);
					$dbHotel->Address->setCounty($destination);
					$dbHotel->Address->setCountry(null);
				}
				else if ($destIsCountry)
				{
					$dbHotel->Address->setCity(null);
					$dbHotel->Address->setCounty(null);
					$dbHotel->Address->setDestination(null);
					$dbHotel->Address->setCountry($destination);
				}
				else
				{
					$dbHotel->Address->setCity(null);
					$dbHotel->Address->setCounty(null);
					$dbHotel->Address->setCountry(null);
					$dbHotel->Address->setDestination($destination);
				}
				$hotelsAppData->Hotels[] = $dbHotel;
			}
		}

		// save hotels
		if (count($hotelsAppData->Hotels) > 0)
			$hotelsAppData->save("Hotels.Address.*");
		
		$toursAppData = \QApp::NewData();
		$toursAppData->setTours(new \QModelArray());
		
		$useEntity = "Title, TopTitle, Code, "
				. "Location, "
				. "Period, "
				. "HasSpecialOffer, "
				. "Recommended, "
				. "HasBestDeal, "

				. "LiveVisitors, "
				. "LastReservationDate,	"

				. "HasActiveOffers, "
				. "HasPlaneActiveOffers, "
				. "HasBusActiveOffers, "
				. "BlockContentUpdate, "
				. "Hotels,"
				. "Location.{"
					. "City, "
					. "County, "
					. "Country, "
					. "Destination "
				. "},"
				. "Content.{"
					. "ShortDescription, "
					. "Content, "
					. "ImageGallery.Items.{Updated, Path, ExternalUrl, RemoteUrl, Base64Data, TourOperator.{Handle, Caption, Abbr}, InTourOperatorId, Alt},"
					. "VideoGallery.{Items.{Updated, Path, Poster}}"
				. "}, "
				. "TopContent.{"
					. "ShortDescription, "
					. "Content, "
					. "ImageGallery.Items.{Updated, Path, ExternalUrl, RemoteUrl, Base64Data, TourOperator.{Handle, Caption, Abbr}, InTourOperatorId, Alt},"
					. "VideoGallery.{Items.{Updated, Path, Poster}}"
				. "}, "
				. "InTourOperatorId";

		// sanitize selector
		$useEntity = q_SanitizeSelector("Tours", $useEntity);

		//$tour_id = $hotelObj->InTourOperatorId;
		$dbTours = count($toursToBeProcessed) ? 
			QQuery("Tours.{" . $useEntity . " WHERE TourOperator.Id=? AND InTourOperatorId IN (?)}", 
				[$this->TourOperatorRecord->getId(), array_keys($toursToBeProcessed)])->Tours : null;

		$dbToursByID = [];
		foreach ($dbTours ?: [] as $dbTour)
			$dbToursByID[$dbTour->InTourOperatorId] = $dbTour;

		foreach ($toursToBeProcessed ?: [] as $tourID => $tourPackages)
		{
			/*
			if (count($tourPackages) > 1)
			{
				echo "Multiple packages for tour [{$tourID}]<br/>";
				continue;
			}
			*/
			$dbTour = $dbToursByID[$tourID];
			foreach ($tourPackages ?: [] as $package)
			{
				if (!($dbHotel = $existingHotelsByTopIds[$package->Hotel]))
				{
					$tour_id = $package->Id ?: $package->PackageId;
					$tour_title = $package->Name;
					echo "<div style='color: red;'>Tour [{$tour_id}|{$tour_title}] does not have hotel [{$tourID}]</div>";
					continue;
				}
				list($tour, $saveTour) = $this->GetTour($package, $dbTour, $dbHotel);
				if ($saveTour && $tour)
				{
					if ($this->TrackReport && (!$tour->getId()))
					{
						echo "<div style='color: green;'>Add new tour [{$tour->InTourOperatorId}|{$tour->Title}]</div>";
						if (!$this->TrackReport->NewTours)
							$this->TrackReport->NewTours = 0;
						$this->TrackReport->NewTours++;
					}
					$toursAppData->Tours[$tour->InTourOperatorId] = $tour;
				}
			}
		}

		// save tours
		if (count($toursAppData->Tours) > 0)
			$toursAppData->save(true);
	}

	public static function FixGeoLinks($tourOperator)
	{
		$mysqli = \QApp::GetStorage()->connection;

		$app = \QApp::NewData();
		$app->Cities = new \QModelArray();
		$app->Counties = new \QModelArray();

		$res = $mysqli->query("SELECT "
			. "`Cities`.`\$id` AS `cityId`, "
			. "`Cities`.`\$County` AS `cityCountyId`, "
			. "`Cities`.`\$Country` AS `cityCountryId`, "
			. "`Counties`.`\$id` AS `countyId`, "
			. "`ApiStorages_Identifiers`.`\$Country` AS `countryId` "
		. " FROM `Cities` "
			. "LEFT JOIN `Counties` ON (`Cities`.`InTourOperatorId`=`Counties`.`InTourOperatorId` AND `Cities`.`\$TourOperator`=`Counties`.`\$TourOperator`) "
			. "LEFT JOIN `ApiStorages_Identifiers` ON (`Cities`.`InTourOperatorId`=`ApiStorages_Identifiers`.`Identifier` "
				. "AND `Cities`.`\$TourOperator`=`ApiStorages_Identifiers`.`\$TourOperator`) "
		. " WHERE "
			. "`Cities`.`\$TourOperator`='" . $tourOperator->getId() . "'");

		while ($r = $res->fetch_assoc())
		{
			if ($r["countyId"] && ($r["countyId"] != $r["cityCountyId"]))
			{
				$city = new \Omi\City();
				$city->setId($r["cityId"]);

				$county = new \Omi\County();
				$county->setId($r["countyId"]);

				$city->setCounty($county);
				$app->Cities[] = $city;
			}

			if ($r["countryId"] && ($r["countryId"] != $r["cityCountryId"]))
			{
				echo "<div style='color: red;'>Country mismatch on cities - Manual action necessary</div>";
				/*
				$city = new \Omi\City();
				$city->setId($r["cityId"]);

				$country = new \Omi\Country();
				$country->setId($r["countryId"]);

				$city->setCountry($country);
				$app->Cities[] = $city;
				*/
			}
		}

		$res = $mysqli->query("SELECT "
			. "`Counties`.`\$id` AS `countyId`, "
			. "`Counties`.`\$Country` AS `countyCountryId`, "
			. "`ApiStorages_Identifiers`.`\$Country` AS `countryId` "
		. " FROM `Counties` "
			. "LEFT JOIN `ApiStorages_Identifiers` ON (`Counties`.`InTourOperatorId`=`ApiStorages_Identifiers`.`Identifier` "
				. "AND `Counties`.`\$TourOperator`=`ApiStorages_Identifiers`.`\$TourOperator`) "
		. " WHERE "
			. "`Counties`.`\$TourOperator`='" . $tourOperator->getId() . "'");
		
		while ($r = $res->fetch_assoc())
		{
			if ($r["countryId"] && ($r["countryId"] != $r["countyCountryId"]))
			{
				echo "<div style='color: red;'>Country mismatch on counties - Manual action necessary</div>";
				/*
				$county = new \Omi\County();
				$county->setId($r["countyId"]);

				$country = new \Omi\Country();
				$country->setId($r["countryId"]);

				$county->setCountry($country);
				$app->Counties[] = $county;
				*/
			}
		}

		$app->save("Cities.*, Counties.*");
	}

	protected static function PullCacheData_ProcessHotelsAndTours($storage, $packages, $tours = false, $addSourceToHotelId = true)
	{
		$hotelsIds = [];
		$toursIds = [];
		foreach ($packages as $p)
		{
			if ($p->HotelInfo->Source && $addSourceToHotelId)
			{
				$hidparts = (strrpos($p->HotelInfo->HotelId, '|') !== false) ? explode('|', $p->HotelInfo->HotelId) : [];
				$hasSource = ((count($hidparts) > 1) && (end($hidparts) == $p->HotelInfo->Source));
				if (!$hasSource)
					$p->HotelInfo->HotelId .= "|" . $p->HotelInfo->Source;
				#echo $p->HotelInfo->HotelId . ' | ' . ($hasSource ? 'has source' : 'no source') . '<br/>';
			}

			$hotelsIds[$p->HotelInfo->HotelId] = $p->HotelInfo->HotelId;
			if ($tours)
			{
				$tourId = $p->Id ?: $p->PackageId;
				$toursIds[$tourId] = $tourId;
			}
		}
		$existingHotelsRes = count($hotelsIds) ? QQuery("Hotels.{"
			. "Stars,"
			. "Master.{"
				. "HasCharterOffers, "
				. "HasCharterActiveOffers, "
				. "HasBusChartersActiveOffers, "
				. "HasPlaneChartersActiveOffers, "	
				. "Updated"
			. "},"
			. "HasCharterOffers, "
			. "HasCharterActiveOffers, "
			. "HasBusChartersActiveOffers, "
			. "HasPlaneChartersActiveOffers, "	
			. "TourOperator.Handle,"
			. "InTourOperatorId,"
			. "Rooms.{InTourOperatorId, Title, TourOperator.*}, "
			. "Address.{"
				. "City.{"
					. "InTourOperatorId,"
					. "Code, "
					. "Name, "
					. "County.{"
						. "InTourOperatorId,"
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
					. "InTourOperatorId,"
					. "Country.{"
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "Destination.{"
					. "Name,"
					. "InTourOperatorId"
				. "},"
				. "Country.{"
					. "InTourOperatorsIds.{TourOperator, Identifier}, "
					. "Code, "
					. "Name"
				. "}, "
				. "Latitude, "
				. "Longitude"
			. "}"
		. " WHERE InTourOperatorId IN (?) AND TourOperator.Handle=?}", [$hotelsIds, $storage->TourOperatorRecord->Handle])->Hotels : [];

		$ehotelsByTopIds = [];
		foreach ($existingHotelsRes ?: [] as $eh)
			$ehotelsByTopIds[$eh->InTourOperatorId] = $eh;

		if ($tours)
		{
			$existingToursRes = count($toursIds) ? QQuery("Tours.{"
				. "HasActiveOffers, "
				. "HasBusActiveOffers, "
				. "HasPlaneActiveOffers, "
				. "TourOperator.Handle,"
				. "InTourOperatorId,"
				. "Location.{"
								. "Updated,"
					. "City.{"
						. "InTourOperatorId,"
						. "Code, "
						. "Name, "
						. "County.{"
							. "InTourOperatorId,"
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
						. "InTourOperatorId,"
						. "Country.{"
							. "Code, "
							. "Name"
						. "}"
					. "}, "
					. "Destination.{"
						. "Name,"
						. "InTourOperatorId"
					. "},"
					. "Country.{"
						. "InTourOperatorsIds.{TourOperator, Identifier}, "
						. "Code, "
						. "Name"
					. "}, "
					. "Latitude, "
					. "Longitude"
				. "}"
			. " WHERE InTourOperatorId IN (?) AND TourOperator.Handle=?}", [$toursIds, $storage->TourOperatorRecord->Handle])->Tours : [];
		}
		
		$etoursByTopIds = [];
		foreach ($existingToursRes ?: [] as $et)
			$etoursByTopIds[$et->InTourOperatorId] = $et;

		return [$ehotelsByTopIds, $etoursByTopIds];
	}

	public function setupTransportsCacheData_topCust(array &$toCacheData, \Omi\TF\TransportDateNights $retNightsObj, \stdClass $p, bool $isTour = false,
		\Omi\Travel\Merch\Hotel $hotel = null, \Omi\Travel\Merch\Tour $tour = null, array $params = null, \Omi\TF\Request $request = null, bool $showDiagnosis = false)
	{
		$isCharter = (!$isTour);
		$hasAirportTaxesIncluded = (($isCharter || $isTour) && static::$Config[$this->TourOperatorRecord->Handle][($isCharter ? "charter" : "tour")]["airport_tax_included"]);
		$hasTransferIncluded = $p->TransferInfo ? true : false;
		$hasMedicalInsurenceIncluded = false;
		$status = ($p->IsBookable ? ($p->IsAvailable ? "yes" : "ask") : "no");

		if (!($currencyCode = $params["CurrencyCode"]))
			throw new \Exception("Offer currency not provided!");

		// setup
		if (!($currency = static::GetCurrencyByCode($currencyCode)))
		{
			throw new \Exception("Undefined currency [{$currencyCode}]!");
		}

		$rooms_list = [];
		if ($p->HotelInfo && $p->HotelInfo->Rooms)
			$rooms_list = $this->getRoomsList($p->HotelInfo->Rooms);
		$room = $rooms_list[0];

		// loop through each meal plan
		if ($p->HotelInfo && $p->HotelInfo->MealPlans)
		{
			$minPricedMealPlan = null;

			$mealPlans = ($p->HotelInfo->MealPlans);
			array_reverse($mealPlans);

			foreach ($mealPlans as $mealPlan)
			{
				$mealPlanPrice = $mealPlan->Price ? $mealPlan->Price->Gross : 0;
				if (($minPricedMealPlan === null) || ((!$minPricedMealPlan->Price) || ($minPricedMealPlan->Price->Gross > $mealPlanPrice)))
				{
					$minPricedMealPlan = $mealPlan;
				}			
			}
		}

		$meal = $minPricedMealPlan ? $minPricedMealPlan->Label : null;

		$price = (float)(((isset($p->HotelInfo->Price->Gross) && $p->HotelInfo->Price->Gross) ? 
			$p->HotelInfo->Price->Gross : (($minPricedMealPlan && $minPricedMealPlan->Price) ? $minPricedMealPlan->Price->Gross : 0)) + $p->Price->Gross);

		if ($p->Price && $p->Price->Tax)
			$price += $p->Price->Tax;

		$initialPrice = $price;
		
		$useDiscount = (($p && $p->TotalDiscount) ? $p->TotalDiscount : $p->HotelInfo->TotalDiscount);
		$initialPrice += $useDiscount ?: 0;

		$hasMedicalInsurenceIncluded = false;
		if ($p->ExtraComponents)
		{
			foreach ($p->ExtraComponents as $ec)
			{
				if ($ec->IsOptional)
					continue;

				$isHOF_MedicalEnsurence = false;
				if (
					#(strpos($ec->Label, "RO Cocktail Travel Protection") !== false) || 
					($isHOF_MedicalEnsurence = (strpos($ec->Label, "RO Pachet Protectie de Calatorie") !== false)))
				{
					$hasMedicalInsurenceIncluded = true;
					break;
				}
			}
		}

		$r_label = "";
		$__room = null;
		if ($hotel && $hotel->Rooms && $p->HotelInfo->CategoryId)
		{
			if ($p->HotelInfo->CategorySourceId)
			{
				$hcs_idparts = (strrpos($p->HotelInfo->CategoryId, '|') !== false) ? explode('|', $p->HotelInfo->CategoryId) : [];
				$hasSource = ((count($hcs_idparts) > 1) && (end($hcs_idparts) == $p->HotelInfo->CategorySourceId));
				if (!$hasSource)
					$p->HotelInfo->CategoryId .= "|" . $p->HotelInfo->CategorySourceId;
			}
			foreach ($hotel->Rooms as $r)
			{
				if ($r->InTourOperatorId == $p->HotelInfo->CategoryId)
				{
					$r_label = (($pos = strpos($r->Title, '/')) !== false) ? trim(substr($r->Title, $pos + 1)) : trim($r->Title);
					$__room = $r;
					break;
				}
			}
		}

		$room_label = $room ? $room->Label : null;
		$pos = strpos($room->Label, '/');
		if ($pos !== false)
			$room_label = trim(substr($room->Label, $pos + 1));
		else 
			$room_label = trim($room->Label);

		if (empty($r_label))
			$r_label = $room_label;
		$r_label = trim($r_label . " " . "");

		$isEarlyBooking = (($p->HotelInfo->FareType[0] == "early_booking") || ($p->HotelInfo->FareType[0] == "special_offer") || 
			($p->HotelInfo->FareType[0] == "last_minute") || ($p && 
			(($p->FareType[0] == "special_offer") || ($p->FareType[0] == "last_minute") || ($p->FareType[0] == "early_booking"))));

		$isSpecialOffer = false;
		$discount = null;

		$this->setupTransportsCacheData($toCacheData, $retNightsObj, ((!$isTour) ? $hotel : null), ($isTour ? $tour : null), $params, $price, $initialPrice, $discount, $currency, $status, 
			$r_label, $meal, $hasAirportTaxesIncluded, $hasTransferIncluded, $hasMedicalInsurenceIncluded, $isEarlyBooking, $isSpecialOffer, $request, $showDiagnosis);
	}

	/**
	 * @param type $request
	 * @param type $handle
	 * @param type $params
	 * @param type $_nights
	 * @param type $tours
	 * @param type $do_cleanup
	 * @param type $skip_cache
	 * @return type
	 * @throws \Exception
	 */
	protected static function PullCacheData($request, $handle, $params, $_nights, $tours = false, $do_cleanup = true, $skip_cache = false)
	{
		$reportType = $tours ? "tour-request" : "charter-request";
		
		ob_start();
		$storage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
		if ((!$storage) || (!$storage->TourOperatorRecord))
		{
			$exception = new \Exception("No storage found or not connected [{$storage->TourOperatorRecord->Handle}]");
			if ($storage)
			{
				$storage->startTrackReport($reportType, $request);
				$report = ob_get_clean();
				echo $report;
				$storage->closeTrackReport($report, $exception);
			}
			throw new \Exception($exception);
		}

		$storage->startTrackReport($reportType, $request);

		if (!$params["Rooms"]["Room"]["ChildAges"])
			$params["Rooms"]["Room"]["ChildAges"] = [];

		unset($params["UP_ONLY_NIGHTS"]);
		unset($params["CheckIn"]);
		unset($params["DepCityCode"]);

		$_dest_type = $params["DestinationType"];
		unset($params["DestinationType"]);

		if (!($__checkin = $params["DepartureDate"]))
			throw new \Exception("Checkin not provided!");

		$params["Destination"] = (int)$params["Destination"];
		$params["Departure"] = (int)$params["Departure"];
		
		$storage->getToCallPeriod_FromTranslated($params);

		$force = true;
		$callMethod = "PackageSearch";
		$callKeyIdf = $tours ? "tours" : "charters";
		$callParams = $params;
		// we need to make sure that we have all data in order (countries and continents)
		$refThis = $storage;

		$departCity = QQuery("Cities.{*, Country.{Code, Name, InTourOperatorsIds.{Identifier, TourOperator.Handle WHERE TourOperator.Handle=?}} " 
			. "WHERE TourOperator.Handle=? AND InTourOperatorId=?}", [$storage->TourOperatorRecord->Handle, $storage->TourOperatorRecord->Handle, $params["Departure"]])->Cities[0];

		$departureCountryIdf = ($departCity && $departCity->Country) ? $departCity->Country->getTourOperatorIdentifier($storage->TourOperatorRecord) : null;

		$destIndx = null;
		$countryCode = null;
		if ($_dest_type === "city")
		{
			$destIndx = "CityCode";
			$destinationCity = QQuery("Cities.{*, County.{Name, InTourOperatorId}, " 
				. "Country.{Code, Name, InTourOperatorsIds.{Identifier, TourOperator.Handle WHERE TourOperator.Handle=?}} " 
				. "WHERE TourOperator.Handle=? AND InTourOperatorId=?}", [$storage->TourOperatorRecord->Handle, $storage->TourOperatorRecord->Handle, $params["Destination"]])->Cities[0];
			$countryCode = ($destinationCity && $destinationCity->Country) ? $destinationCity->Country->getTourOperatorIdentifier($storage->TourOperatorRecord) : null;

			if (isset($destinationCity->County->InTourOperatorId) && 
					isset($storage::$Config[$storage->TourOperatorRecord->Handle]['force_reqs_on_county'][$destinationCity->County->InTourOperatorId]))
			{
				$destIndx = "Zone";
				$destinationCity->County->Country = $destinationCity->Country;
				$destinationCounty = $destinationCity->County;
				$destinationCity = null;
				$destIndx = "Zone";
				$callParams["Destination"] = $params["Destination"] = (int)$destinationCounty->InTourOperatorId;
				$_dest_type = "county";
			}
		}
		else if ($_dest_type === "county")
		{
			$destIndx = "Zone";
			$destinationCounty = QQuery("Counties.{*, Country.{Code, Name, InTourOperatorsIds.{Identifier, TourOperator.Handle WHERE TourOperator.Handle=?}} " 
				. "WHERE TourOperator.Handle=? AND InTourOperatorId=?}", [$storage->TourOperatorRecord->Handle, $storage->TourOperatorRecord->Handle, $params["Destination"]])->Counties[0];
			$countryCode = ($destinationCounty && $destinationCounty->Country) ? $destinationCounty->Country->getTourOperatorIdentifier($storage->TourOperatorRecord) : null;
		}
		else if ($_dest_type === "country")
		{
			// should not get here
		}
		else if ($_dest_type === "destination")
		{
			$destIndx = "Destination";
		}

		$indx_params = [
			"VacationType" => $tours ? "tour" : "charter",
			"Transport" => $params["IsFlight"] ? "plane" : ($params["IsBus"] ? "bus" : "individual"),
			"Rooms" => [
				[
					"Room" => [
						"Code" => "DB",
						"NoAdults" => ((int)$params["Rooms"]["Room"]["Adults"] ?: 0),
						"NoChildren" => ((int)$params["Rooms"]["Room"]["Children"] ?: 0),
					],	
				],
			],
			"PeriodOfStay" => [
				["CheckIn" => $params["DepartureDate"]],
				["CheckOut" => date("Y-m-d", strtotime("+ {$params["Duration"]} days", strtotime($params["DepartureDate"])))]
			],
			"DepCityCode" => $params["Departure"] . "",
			"DepCountryCode" => $departureCountryIdf,
			"{$destIndx}" => $params["Destination"] . "",
			
		];

		if (!$tours)
			$indx_params["Days"] = 0;

		if ($countryCode)
			$indx_params["CountryCode"] = $countryCode;

		ksort($indx_params);

		$tf_req_id = md5($storage->TourOperatorRecord->Handle . "|" . json_encode($indx_params));
		$indx_params["CurrencyCode"] = $callParams["Currency"];
		$callParams["__request_data__"]["ID"] = $tf_req_id;

		\Omi\TFuse\Api\TravelFuse::$_CacheData["AllTopsReqsById"][$tf_req_id] = $indx_params;
		\Omi\TFuse\Api\TravelFuse::$_CacheData["AllTopsReqsById_Top"][$tf_req_id] = $storage->TourOperatorRecord->Handle;

		if (!($skip_cache))
		{
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = static::ExecCacheable(["PackageSearch" => "PackageSearch"], 
				function ($refThis, $callMethod, $callParams, $callKeyIdf, $force, $storage, $request, $soapContext, $soapContextPhase) {
				$execPhase = ($soapContextPhase === \Omi\Util\SoapClientContext::PhaseRequest);
				return $storage->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
					$reqEX = null;
					try
					{
						if (($apiCode = ($refThis->TourOperatorRecord->ApiCode__ ?: $refThis->TourOperatorRecord->ApiCode)))
							$params["AgentCode"] = $apiCode;
						$return = $refThis->SOAPInstance->request($method, $params);
					}
					catch (\Exception $ex)
					{
						$reqEX = $ex;
					}
					return [$return, $refThis->SOAPInstance->client->__getLastRequest(), $refThis->SOAPInstance->client->__getLastResponse(), $reqEX];
				}, $callMethod, $callParams, $callKeyIdf, $force, ($request ? $request->UniqId : null), $execPhase);
			}, [$refThis, $callMethod, $callParams, $callKeyIdf, $force, $storage, $request]);
		}
		else
		{
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $storage->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
				$reqEX = null;
				try
				{
					if (($apiCode = ($refThis->TourOperatorRecord->ApiCode__ ?: $refThis->TourOperatorRecord->ApiCode)))
						$params["AgentCode"] = $apiCode;
					$return = $refThis->SOAPInstance->request($method, $params);
				}
				catch (\Exception $ex)
				{
					$reqEX = $ex;
				}
				return [$return, $refThis->SOAPInstance->client->__getLastRequest(), $refThis->SOAPInstance->client->__getLastResponse(), $reqEX];
			}, $callMethod, $callParams, $callKeyIdf, $force, ($request ? $request->UniqId : null));
		}

		if ($alreadyProcessed && (!$force))
		{
			if ($storage->TrackReport)
				$storage->TrackReport->_responseNotChanged = true;
			echo "Nothing changed from last request!";
			$report = ob_get_clean();
			echo $report;
			$storage->closeTrackReport("Nothing changed from last request!");
			return $request;
		}

		$packages = $return;

		$showDiagnosis = true;

		$tProcessPackages = microtime(true);
		$__ex = null;
		try
		{
			$__toSyncTransports = null;
			$__toSyncTransportsDates = null;

			$transportCacheProp = $tours ? "ToursTransports" : "ChartersTransports";
			$trvItmsProp = $tours ? "Tours" : "Hotels";

			static::FixGeoLinks($storage->TourOperatorRecord);
			
			$_qsp = [
				"TourOperator" => $storage->TourOperatorRecord->getId(), 
				"From" => $params["Departure"], 
				ucfirst($_dest_type) => (($_dest_type == "country") ? [$params["Destination"], $storage->TourOperatorRecord->getId()] :  $params["Destination"]),
				"TransportType" => $params["IsBus"] ? "bus" : ($params["IsFlight"] ? "plane" : "individual"),
				"Date" => $__checkin,
				"Nights" => $_nights
			];

			// get req file handle for lite date dump
			$reqFileH = $storage->getRequestDataFileHandle($request);

			$cachedTransports = $storage->getCachedTransportsForRequest($_qsp, $tours, $reqFileH);

			$reqResults = [];
			$reqAllResults = [];

			$updateHotels = [];
			$updateTours = [];

			$appData = \QApp::NewData();
			$appData->{$transportCacheProp} = new \QModelArray();
			$appData->NoResultsRequests = new \QModelArray();
			$appData->NoActiveResultsRequests = new \QModelArray();

			$__hasBookable = false;
			if ($packages && is_array($packages))
			{
				list($ehotelsByTopIds, $eToursByTopIds) = static::PullCacheData_ProcessHotelsAndTours($storage, $packages, $tours);

				$triggerStaticDataImport = false;
				foreach ($packages as $p)
				{
					$hotel = $ehotelsByTopIds[($hotelId = $p->HotelInfo->HotelId)];
					$tour = $tours ? $eToursByTopIds[($tourId = ($p->Id ?: $p->PackageId))] : null;

					// if we don't have the hotel then just skip the package
					if (!$hotel)
					{
						// @TODO trigger static data
						#echo "<div style='color: red;'>no hotel in db [{$hotelId}]</div>";
						#continue;
						#qvardump("Package", $p);
						#throw new \Exception("Hotel not found in db [{$hotelId}] - we must trigger or wait for static data to be re-imported!");
						$triggerStaticDataImport = true;
					}

					if ($tours && (!$tour))
					{
						#echo "<div style='color: red;'>no tour in db [{$tourId}]</div>";
						#continue;
						#throw new \Exception("Tour not found in db [{$tourId}] - we must trigger or wait for static data to be re-imported!");
						$triggerStaticDataImport = true;
					}
				}

				if ($triggerStaticDataImport)
				{
					$config = ["force" => $force];
					if (method_exists($storage, "cacheTopCountries"))
						$storage->cacheTopCountries($config);
					if (method_exists($storage, "cacheTOPCounties"))
						$storage->cacheTOPCounties($config);
					if (method_exists($storage, "cacheTOPCities"))
						$storage->cacheTOPCities($config);
					if (method_exists($storage, "cacheTopHotels"))
						$storage->cacheTopHotels($config);
					if (method_exists($storage, "cacheTopTours"))
						$storage->cacheTopTours($config);
					list($ehotelsByTopIds, $eToursByTopIds) = static::PullCacheData_ProcessHotelsAndTours($storage, $packages, $tours, false);
				}

				$noHotelInDbShownErr = [];
				$noTourInDbShownErr = [];

				$toSaveMeals = [];
				foreach ($packages as $p)
				{
					$tourId = ($p->Id ?: $p->PackageId);
					$hotelId = $p->HotelInfo->HotelId;
					$itmIsBookable = false;
					if ($p->IsBookable)
					{
						$__hasBookable = true;
						$itmIsBookable = true;
						$tours ? ($reqResults[$tourId] = $tourId) : ($reqResults[$hotelId] = $hotelId);
					}

					$tours ? ($reqAllResults[$tourId] = $tourId) : ($reqAllResults[$hotelId] = $hotelId);

					$hotel = $ehotelsByTopIds[$hotelId];
					$tour = $tours ? $eToursByTopIds[$tourId] : null;

					// if we don't have the hotel then just skip the package
					if (!$hotel)
					{
						// @TODO trigger static data
						if (!isset($noHotelInDbShownErr[$hotelId]))
						{
							echo "<div style='color: red;'>no hotel in db [{$hotelId}]</div>";
							$noHotelInDbShownErr[$hotelId] = $hotelId;
						}
						continue;

					}

					if ($tours && (!$tour))
					{
						if (!isset($noTourInDbShownErr[$hotelId]))
						{
							echo "<div style='color: red;'>no tour in db [{$tourId}]</div>";
							$noTourInDbShownErr[$tourId] = $tourId;
						}
						continue;
						#throw new \Exception("Tour not found in db [{$tourId}] - we must trigger or wait for static data to be re-imported!");
					}

					if (isset($p->HotelInfo->MealPlans))
					{
						foreach ($p->HotelInfo->MealPlans as $plan)
						{
							$meal_orig_label = $plan->Label ?: "Fara masa";
							$m = null;
							preg_match("/\/(.*?)$/", $meal_orig_label, $m);
							$meal_actual_label = ($m && $m[1]) ? trim($m[1]) : $meal_orig_label;
							$toSaveMeals[$meal_actual_label] = $meal_actual_label;
						}
					}

					$travel_ITM = $tours ? $tour : $hotel;

					$dest = $hotel->Address->Destination ?: ($hotel->Address->City ?: ($hotel->Address->County ?: $hotel->Address->Country));

					if (!($dest))
					{
						throw new \Exception("Destination not found for hotel [{$hotelId}]");
					}

					$updateHotels[$hotel->InTourOperatorId] = $hotel;
					if ($tours)
						$updateTours[$tour->InTourOperatorId] = $tour;

					// setup duration data
					$retNightsObj = $storage->setupDurationData($cachedTransports, $dest, $travel_ITM, $trvItmsProp, $request, $showDiagnosis, $reqFileH, $itmIsBookable);

					// try to do it on county
					if ((!$retNightsObj) && $dest->County)
						$retNightsObj = $storage->setupDurationData($cachedTransports, $dest->County, $travel_ITM, $trvItmsProp, $request, $showDiagnosis, $reqFileH, $itmIsBookable);
				}
			}

			if (count($toSaveMeals))
				\Omi\TFuse\Api\TravelFuse::SetupResults_SetupMealsAliases_FromList($toSaveMeals, true);

			if (count($updateHotels))
			{
				\Omi\TFuse\Api\TravelFuse::UpdateHotelsChartersActiveFlags($updateHotels, $params["IsBus"]);
				$storage->flagHotelsTravelItems($updateHotels);
			}

			if (count($updateTours))
			{
				\Omi\TFuse\Api\TravelFuse::UpdateToursActiveFlags($updateTours, $params["IsBus"]);
				$storage->flagToursTravelItems($updateTours);
			}

			if ($request)
			{
				$request->setResults(count($reqResults));
				$request->setAllResults(count($reqAllResults));
			}

			$unexReqsBinds = ($request && $request->getId()) ? [
				"NOT" => $request->getId(),
				"TourOperator" => $storage->TourOperatorRecord->getId(),
				"TransportType" => $params["IsBus"] ? "bus" : ($params["IsFlight"] ? "plane" : "individual"),
				"DestinationIndex" => $params["Destination"],
				"DepartureIndex" => $params["Departure"],
				"DepartureDate" => $__checkin
			] : null;

			list($__toSyncTransportsDates, $__toSyncTransports) = $storage->setupCachedTransportsOnRequest($cachedTransports, $appData, $unexReqsBinds, 
				$__hasBookable, $tours, null, false);			

			if ($reqFileH)
				$storage->writeTransportsLinksToDataFile($reqFileH, $appData->{$transportCacheProp});

			// close the handle if opened
			if ($reqFileH)
				fclose($reqFileH);

			if ($storage->TourOperatorRecord)
				$storage->TourOperatorRecord->restoreCredentials();

			// save data
			$appData->save($transportCacheProp.".{"
					. "Dates.{"
						. "Date, "
						. "Nights.{"
							. "Request,"
							. "ReqResults,"
							. "ReqAllResults,"
							. "DeactivationReason, "
							. "Nights,"
							. "Active, "
							. "ReqExecLastDate, "
							. "{$trvItmsProp}"
						. "}"
					. "}"
				. "},"
				. "NoResultsRequests.{"
					. "TourOperator.Id, "
					. "FromCode, "
					. "ToCode, "
					. "Checkin, "
					. "Duration, "
					. "Type, "
					. "TransportType, "
					. "DestinationCity.Id, "
					. "DestinationCounty.Id, "
					. "DestinationCountry.Id, "
					. "DestinationCustom.Id,"
					. "DepartureCity.Id, "
					. "DepartureCounty.Id, "
					. "DepartureCountry.Id, "
					. "DepartureCustom.Id"
				. "}, " 
				. "NoActiveResultsRequests.{"
					. "TourOperator.Id, "
					. "FromCode, "
					. "ToCode, "
					. "Checkin, "
					. "Duration, "
					. "Type, "
					. "TransportType, "
					. "DestinationCity.Id, "
					. "DestinationCounty.Id, "
					. "DestinationCountry.Id, "
					. "DestinationCustom.Id,"
					. "DepartureCity.Id, "
					. "DepartureCounty.Id, "
					. "DepartureCountry.Id, "
					. "DepartureCustom.Id"
				. "}");

			if ($tf_req_id)
			{
				\Omi\TFuse\Api\Travelfuse::RefreshTravelItemsCacheData([
					"TravelfuseReqID" => $tf_req_id
				], true);
			}
			
			$chartersTransportsIds = [];
			foreach ($appData->$transportCacheProp ?: [] as $transport)
			{
				if ($transport instanceof \Omi\TF\CharterTransport)
				{
					$chartersTransportsIds[$transport->getId()] = $transport->getId();
				}
			}

			if (count($chartersTransportsIds))
			{
				\Omi\TF\CharterTransport::SyncHotelsFromNights($chartersTransportsIds);
			}

			// setup soap response and processing
			$storage->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, 
				$callTopRequestTiming, (microtime(true) - $tProcessPackages), ($request ? $request->UniqId : null));
		}
		catch (\Exception $ex)
		{
			try
			{
				\Omi\App::ThrowExWithTypePrefix($ex, \Omi\App::Q_ERR_USER);
			}
			catch (\Exception $nex)
			{
				$__ex = $nex;
				throw $nex;
			}
		}
		finally 
		{
			$report = ob_get_clean();
			$storage->closeTrackReport($report, $__ex);
			echo $report;
		}

		return $request;
	}
}