<?php

namespace Omi\TF;

/**
 * 
 */
trait EuroSite_CacheStaticData
{
	public $citiesHotelsCacheLimit = 60 * 60 * 24;
	
	/**
	 * Get hotels from city.
	 * 
	 * @param mix $topCity[]
	 * @param array $config[]
	 * 
	 * @return mix $ret[]
	 * 
	 * @throws \Exception
	 */
	public function getCityHotels($topCity = [], array $config = [])
	{
		if (!$topCity['CityCode'])
			throw new \Exception('City code not found!');

		$force = $config["force"] ?: false;
		$callMethod = "getOwnHotelsRequest";
		$callKeyIdf = null;
		$callParams = ["CityCode" => $topCity['CityCode']];

		// we need to make sure that we have all data in order (countries and continents)
		$refThis = $this;
		list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) 
		{
			$reqEX = null;
			
			try
			{
				//$requestType, $params = null, $saveAttrs = false, $useCache = false
				$return = $refThis->doRequest($method, $params, false, false);
				
				echo "<h5>RAW - {$method} - a</h5>";
				echo "<pre>".htmlspecialchars(json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS))."</pre>";
				echo "<pre>".htmlspecialchars(json_encode($return, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS))."</pre>";
			}
			catch (\Exception $ex)
			{
				$reqEX = $ex;
				echo "<h5>ERROR</h5>";
				echo "<pre>".htmlspecialchars($ex->getMessage())."</pre>";
			}

			echo "<pre>".htmlspecialchars(json_encode(['$rawReq' => $return["rawResp"], '$rawResp' => $return["rawReq"]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS))."</pre>";
			
			if (($rawResp = $return["rawResp"]))
				$rawResp = preg_replace('#(<ResponseTime>(.*?)</ResponseTime>)|(<ResponseId>(.*?)</ResponseId>)|(<RequestId>(.*?)</RequestId>)#', '', $rawResp);
			
			if (($rawReq = $return["rawReq"]))
				$rawReq = preg_replace('#(<RequestTime>(.*?)</RequestTime>)#', '', $rawReq);
			
			return [$return, $rawReq, $rawResp, $reqEX];
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
		}

		$t1 = microtime(true);
		
		$data = static::GetResponseData($return, "getOwnHotelsResponse");
		
		$ret = [$data, $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1];
		
		return $ret;
	}
	/**
	 * Cache touroperator hotels.
	 * 
	 * @param type $config
	 * @param type $onCitiesRequests
	 * @param type $binds
	 */
	public function cacheTOPHotels(array $config = [], $onCitiesRequests = true, array $binds = [])
	{
		\Omi\TF\TOInterface::markReportStartpoint($config, 'hotels');
		
		$bag_for_cleanup = [];

		if (!file_exists(($lockFile = \Omi\App::GetLogsDir('locks') . "cacheTOPHotels_on_" . $this->TourOperatorRecord->Handle . "_Lock.txt")))
			file_put_contents($lockFile, "cacheTOPHotels lock");

		if (!($lock = \QFileLock::lock($lockFile, 1)))
			throw new \Exception("Sincronizarea de orase este inca in procesare - " . $lockFile);

		try 
		{
			\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = true;
			
			/*

			list($cities) = $this->getTopCities($config);
			 if ((!is_array($cities)) || (!isset($cities['City'])))
				return false;
			if (isset($cities['City']['CityId']))
				$cities['City'] = [$cities['City']];
			$useCities = $cities['City'];

			*/
			
			$getHotelsContent = $config['get_extra_details'];
			
			list($useCities, /* */, /* */, $the_error) = $this->getTOPCitiesWithIndividualOffers($config);
			
			$topCfg = static::$Config[$this->TourOperatorRecord->Handle]["init"];
			$filterCountries = $topCfg['GetStaticCitiesForCountries'] ?: [];

			$countriesIds = [];
			$citiesCnt = 0;
			$citiesIdfs = [];
			foreach ($useCities ?: [] as $city)
			{
				if (($wrongCityData = (empty($city["CityCode"]) || empty($city["CityName"]) || empty($city["CountryCode"]))) || 
					((!empty($filterCountries)) && (!isset($filterCountries[$city["CountryCode"]]))))
				{
					echo "<div style='color: red;'>" . ($wrongCityData ? "Country data not ok: " . json_encode($city) : 
						"City [{$city["CityCode"]}|{$city["CityName"]}] [{$city["CountryCode"]}|{$city["CountryName"]}] does not meet the config requirements : " . json_encode($filterCountries)) . "</div>";
					continue;
				}

				$citiesCnt++;
				$countriesIds[$city["CountryCode"]] = $city["CountryCode"];
				$citiesIdfs[$city["CountryCode"]][$city["CityCode"]] = $city["CityCode"];
			}

			$countriesByIdf = [];
			$identifiersRes = count($countriesIds) ? QQuery("ApiStorageIdentifiers.{Identifier, FromTopRemoved, Country.{Code, Name}, TourOperator.Handle "
				. " WHERE TourOperator.Handle=? AND Country.Code IN (?)}", [$this->TourOperatorRecord->Handle, array_keys($countriesIds)])->ApiStorageIdentifiers : [];
			foreach ($identifiersRes ?: [] as $idfRes)
			{
				if ($idfRes->Country)
					$countriesByIdf[$idfRes->Identifier] = $idfRes->Country;
			}
			
			$citiesObjs = [];
			foreach ($citiesIdfs ?: [] as $countryCode => $citiesIdfsByCountry) 
			{
				$citiesRes  = QQuery('Cities.{Country.{Name, Code}, InTourOperatorId, Name, LastHotelsSaveDate, 
					LastHotelsDetailsSaveDate WHERE TourOperator.Handle=? AND Country.Code=? AND InTourOperatorId IN (?)}', 
					[$this->TourOperatorRecord->Handle, $countryCode, array_keys($citiesIdfsByCountry)])->Cities;
				foreach ($citiesRes ?: [] as $cityObj) 
				{
					$citiesObjs[$cityObj->Country->Code][$cityObj->InTourOperatorId] = $cityObj;
				}
			}

			$reportFile = "eurosite_" . $this->TourOperatorRecord->Handle . "_cacheTopHotelsReport.txt";

			if (file_exists($reportFile))
				unlink($reportFile);

			file_put_contents($reportFile, $citiesCnt . " orase\n\n");
			
			$propToCheck = $getHotelsContent ? 'LastHotelsDetailsSaveDate' : 'LastHotelsSaveDate';

			$hotelsRequests = 0;
			$citiesHotelsRequests = 0;
			$toSaveProcessesAll = [];
			$appData = \QApp::NewData();
			$cityPos = 0;
			$toProcessHotels = [];
			
			$hotels_index = 0;
			
			echo "<pre>";
			
			# get other touroperators code allowed
			$allowed_touroperators_code = static::$Config[$this->TourOperatorRecord->Handle]['allowed_touroperators_code'];
			
			foreach ($useCities ?: [] as $city)
			{
				if ((!$countryObj = $countriesByIdf[$city["CountryCode"]]))
				{
					echo "<div style='color: red;'>skip city [{$city["CityCode"]}] {$city["CityName"]} because country was not found - [{$city["CountryCode"]}]!</div>";
					continue;
				}

				if (!($cityObj = $citiesObjs[$city["CountryCode"]][$city["CityCode"]])) 
				{
					echo "<div style='color: red;'>skip city [{$city["CityCode"]}] {$city["CityName"]} because city not found in db - [{$city["CountryCode"]}|{$city['CityCode']}]!</div>";
					continue;
				}

				if ((!$config['force']) && ($cityObj->{$propToCheck} && ((strtotime($cityObj->{$propToCheck}) + $this->citiesHotelsCacheLimit) > time())))
				{
					echo "<div style='color: red;'>skip city [{$city["CityCode"]}] {$city["CityName"]} because already processed on " . $cityObj->{$propToCheck} . "</div>";
					continue;
				}

				try
				{
					list($cityHotelsData, $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1) = $this->getCityHotels($city, $config);
					
					foreach ($cityHotelsData['Hotel'] ?: [] as $hotel)
					{
						$hotels_index++;
						echo "incomming hotel ({$hotels_index}) {$city['CityCode']}: ", $hotel['HotelName'], " | ", $hotel['HotelCode'], " | ".json_encode($hotel)." | ".json_encode($city)."\n";
					}
				}
				catch (\Exception $ex)
				{
					echo "<div style='color: red;'>skip city [{$city["CityCode"]}] {$city["CityName"]} because city request for getting hotels responded with error!</div>";
					continue;
				}

				$citiesHotelsRequests++;
				$cityPos++;

				$cityHotelsByCode = [];
				if (($cityHotels = $cityHotelsData['Hotel']))
				{
					if (isset($cityHotels['HotelCode']))
						$cityHotels = [$cityHotels];
					
					$dbHotelsByInTopId = [];
					if (!$getHotelsContent) 
					{
						$hotelsInTopIds = [];
						foreach ($cityHotels ?: [] as $cityHotel)
						{
							if ($cityHotel["HotelCode"]) 
								$hotelsInTopIds[$cityHotel["HotelCode"]] = $cityHotel["HotelCode"];
						}

						$useEntity = $this->getEntityForHotel();
						$dbHotels = $hotelsInTopIds ? QQuery("Hotels.{InTourOperatorId, {$useEntity} WHERE InTourOperatorId IN (?) AND TourOperator.Handle=?}",
							[$hotelsInTopIds, $this->TourOperatorRecord->Handle])->Hotels : [];
						foreach ($dbHotels ?: [] as $hotel) 
							$dbHotelsByInTopId[$hotel->InTourOperatorId] = $hotel;
					}

					file_put_contents($reportFile, "Orasul: {$cityPos}/{$citiesCnt} [{$city["CountryCode"]}] {$city["CityName"]} - " . count($cityHotels) . " hoteluri\n", FILE_APPEND);

					foreach ($cityHotels ?: [] as $cityHotel)
					{
						$hparams = [
							'force' => true,
							"ApiContext" => $this->TourOperatorRecord->ApiContext,
							"cityId" => $cityHotel["CityCode"],
							"countryId" => $cityHotel["CountryCode"],
							"travelItemId" => $cityHotel["HotelCode"],
							"travelItemName" => $cityHotel["HotelName"],
						];
						
						if (in_array($cityHotel['Touropcode'], $allowed_touroperators_code))
							$hparams['ApiContext'] = $cityHotel['Touropcode'];
						
						$toProcessHotels[$cityHotel["HotelCode"]] = $cityHotel["HotelCode"];
						$cityHotelsByCode[$cityHotel["HotelCode"]] = $cityHotel["HotelCode"];

						static::keep_track_for_cleanup($bag_for_cleanup, $city['CityCode'], $cityHotel["HotelCode"]);
						
						try
						{
							if ((!$getHotelsContent) && ($existingHotel = $dbHotelsByInTopId[$cityHotel["HotelCode"]]))
							{
								$hotel = $existingHotel;
								$hotel_err = null;
								$alreadyProcessed = false;
								$toSaveProcesses = [];
								$saveHotel = false;
							}
							else
							{
								list($hotel, $hotel_err, $alreadyProcessed, $toSaveProcesses, $saveHotel) = $this->getHotelInfo_NEW($hparams, false, true);
							}
							
							$saveHotelBecauseIndividualFlagsChanged = $this->flagHotelAsIndividualActive($hotel);
							if ($saveHotelBecauseIndividualFlagsChanged)
							{
								$saveHotel = true;
							}

							$hotelsRequests++;
						}
						catch (\Exception $ex)
						{
							// don't stop the execution if data for an hotel was not pulled
							// echo it to be in the report
							echo "<div style='color: red;'>content for hotel [{$cityHotel["HotelCode"]}|{$cityHotel["HotelName"]}] from city [{$city["CityCode"]}] {$city["CityName"]} cannot be retreived!</div>";
							continue;
							//throw $ex;
						}

						foreach ($toSaveProcesses ?: [] as $toSaveProcess)
							$toSaveProcessesAll[] = $toSaveProcess;

						if ($hotel && $saveHotel)
						{
							$appData->Hotels[$cityHotel["HotelCode"]] = $hotel;
						}
					}

					if ($appData->Hotels)
					{
						# $ret = $storage->saveInBatchHotels($appData->Hotels, 
						$this->saveInBatchHotels($appData->Hotels, 
							"FromTopAddedDate, "
							. "FromTopModifiedDate, "
							. "FromTopRemovedAt, "
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
								. "HasIndividualActiveOffers"
							. "},"
							. "HasIndividualOffers,"
							. "HasIndividualActiveOffers,"
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
							. "Destination,City,County,MasterCity,MasterCounty,Country,"
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
				else
				{
					file_put_contents($reportFile, "Orasul: {$cityPos}/{$citiesCnt} [{$city["CountryCode"]}] {$city["CityName"]} - 0 hoteluri\n", FILE_APPEND);
				}

				$toSaveHotels = [];
				
				// hotels are indexed by code
				if ($cityHotelsByCode)
				{
					$query = ''
						. 'Hotels.{'
							. 'Name, '
							. 'Active, '
							. 'FromTopRemoved, '
							. 'Content.Active, '
							
							. 'WHERE 1'
								. ' AND TourOperator.Id=? '
								. ' AND InTourOperatorId IN (?) '	
						. '}'
					;
					
					$hotelsToFlagAsActive = QQuery($query, [$this->TourOperatorRecord->getId(), array_keys($cityHotelsByCode)])->Hotels;
					
					foreach ($hotelsToFlagAsActive ?: [] as $hotelFlagAsActive)
					{
						// hotel is not active and we have hotels by code
						if (!$hotelFlagAsActive->Active)
						{
							// set active
							$hotelFlagAsActive->setActive(true);

							// not removed from top
							$hotelFlagAsActive->setFromTopRemoved(false);
							$hotelFlagAsActive->setFromTopRemovedAt(null);

							// init content
							if (!$hotelFlagAsActive->Content)
								$hotelFlagAsActive->Content = new \Omi\Cms\Content();

							// activate content
							$hotelFlagAsActive->Content->setActive(true);

							// add to save
							$toSaveHotels[] = $hotelFlagAsActive;
						}
					}
				}
				
				// flag deactivated
				$toFlagAsRemovedAndDeactivateHotels = $cityHotelsByCode ? 
					QQuery('Hotels.{Name, Active, FromTopRemoved, Content.Active, InTourOperatorId WHERE TourOperator.Id=? AND Address.City.Id=? AND InTourOperatorId NOT IN (?)}', 
						[$this->TourOperatorRecord->getId(), $cityObj->getId(), array_keys($cityHotelsByCode)])->Hotels : 
					QQuery('Hotels.{Name, Active, FromTopRemoved, Content.Active WHERE TourOperator.Id=? AND Address.City.Id=?}', 
						[$this->TourOperatorRecord->getId(), $cityObj->getId()])->Hotels;
				
				foreach ($toFlagAsRemovedAndDeactivateHotels ?: [] as $tmpHotel)
				{
					$saveHotel = [];
					if ($tmpHotel->Active)
					{
						$tmpHotel->setActive(false);
						$saveHotel = true;
					}

					if ((!$tmpHotel->Content) || $tmpHotel->Content->Active)
					{
						if (!!$tmpHotel->Content)
							$tmpHotel->Content = new \Omi\Cms\Content();
						$tmpHotel->Content->setActive(false);
						$saveHotel = true;
					}

					if (!$tmpHotel->FromTopRemoved) 
					{
						$tmpHotel->setFromTopRemoved(true);
						$tmpHotel->setFromTopRemovedAt(date("Y-m-d H:i:s"));
						$saveHotel = true;
					} 
					
					// hotel is not active and we have hotels by code
					if (!$tmpHotel->Active && in_array($tmpHotel->InTourOperatorId, $cityHotelsByCode))
					{						
						// set active
						$tmpHotel->setActive(true);
						
						// not removed from top
						$tmpHotel->setFromTopRemoved(false);
						$tmpHotel->setFromTopRemovedAt(null);
						
						// init content
						if (!$tmpHotel->Content)
							$tmpHotel->Content = new \Omi\Cms\Content();
						
						// activate content
						$tmpHotel->Content->setActive(false);
						
						// mark to save
						$saveHotel = true;
					}
					
					// save hotels
					if ($saveHotel)
						$toSaveHotels[] = $tmpHotel;
				}

				if ($toSaveHotels)
					\Omi\App::SaveInBatch($toSaveHotels, "Hotels", "Active, Content.Active, FromTopRemoved, FromTopRemovedAt");

				$cityObj->{"set{$propToCheck}"}(date("Y-m-d H:i:s"));
				$cityObj->save($propToCheck);
			}
			
			echo "</pre>";
			
			# THIS IS NOT SAFE - we need to re-think the import !
			# static::run_cleanup($this, $bag_for_cleanup, $config);
			
			file_put_contents($reportFile, "Au fost executate {$citiesHotelsRequests} request-uri pentru a prelua hotelurile pentru orase si {$hotelsRequests} request-uri pentru detalii hoteluri \n", FILE_APPEND);
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		finally 
		{
			if ($lock)
				$lock->unlock();
			
			# qvar_dump(['$ex' => $ex, '$ex message' => $ex ? $ex->getMessage() : null, 'hotels' => $appData->Hotels]);
		}
		
		\Omi\TF\TOInterface::markReportEndpoint($config, 'hotels');
		
		
		return ['bag_for_cleanup' => $bag_for_cleanup];
	}

	public function cacheTOPTours()
	{
		
	}
	
	protected static function run_cleanup($storage, array $bag_for_cleanup = null, array $config = null)
	{
		# disabled atm
		return;
		
		if (!$bag_for_cleanup)
			return;
				
		echo "<pre>";
		# let it execute every time, we will also fix cities if needed
		$db_hotels = \QQuery('Hotels.{Id,Content.Active,Name,InTourOperatorId,City.InTourOperatorId,Address.City.{Name,InTourOperatorId} WHERE TourOperator.Id=? AND InTourOperatorId IS NOT NULL}', 
					[$storage->TourOperatorRecord->Id])->Hotels;

		$citites_to_fix = [];

		$app_to_save = \QApp::NewData();
		$app_to_save->setHotels(new \QModelArray());

		foreach ($db_hotels as $h)
		{
			# $indexed_hotels[$h->InTourOperatorId] = $h;
			$hotels_indx_by_city = $bag_for_cleanup[$h->InTourOperatorId] ?? null;
			if (!isset($hotels_indx_by_city))
			{
				echo "<b>", $h->Name , "</b>\n";
				echo "<span style='color: red;'>deactivate: ", $h->Name, " | ", $h->InTourOperatorId , " | ", $h->Address->City->InTourOperatorId, "</span>\n";

				$h->setActive(false);
				if (isset($h->Content->Id))
					$h->Content->setActive(false);

				$app_to_save->Hotels[] = $h;
			}
			else
			{
				$to_cities_ids = array_keys($hotels_indx_by_city);
				if ((count($to_cities_ids) != 1) || (reset($to_cities_ids) != $h->Address->City->InTourOperatorId))
				{
					echo $h->Name , "\n";
					if (count($to_cities_ids) == 1)
					{
						echo "<span style='color: red;'>fix city from: {$h->Address->City->InTourOperatorId} to ".reset($to_cities_ids)."</span>\n";
						$citites_to_fix[reset($to_cities_ids)][] = $h;
					}
					else
						echo "<span style='color: red;'>Multiple cities per hotel !!! - no action, needs manual fixing.</span>\n";
				}
			}
		}

		$db_fix_cities = $citites_to_fix ? \QQuery('Cities.{Name,InTourOperatorId WHERE TourOperator.Id=? AND InTourOperatorId IN (?)}', [$storage->TourOperatorRecord->Id, array_keys($citites_to_fix)])->Cities : [];
		foreach ($db_fix_cities ?: [] as $new_city)
		{
			foreach ($citites_to_fix[$new_city->InTourOperatorId] ?: [] as $h)
			{
				$h->setCity($new_city);
				if (isset($h->Address->Id))
					$h->Address->setCity($new_city);

				$app_to_save->Hotels[] = $h;
			}
		}

		$app_to_save->save('Hotels.{Active,Content.Active,City.Id,Address.City.Id}');

		echo "FINISHED CLEANUP\n";
	}
	
	
	protected static function keep_track_for_cleanup(array &$bag, string $city_code, string $hotel_code)
	{
		$bag[$hotel_code][$city_code] = $hotel_code;
	}
	
}