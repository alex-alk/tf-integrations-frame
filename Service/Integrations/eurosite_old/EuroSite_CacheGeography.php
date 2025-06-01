<?php

namespace Omi\TF;

/**
 * Eurosite cache (save into db) geography
 */
trait EuroSite_CacheGeography
{
	/**
	 * Cache (save into db) touroperator countries
	 * 
	 * @param array $config
	 * @param type $topCountries
	 * 
	 * @return \QModelArray
	 * 
	 * @throws \Exception
	 */
	public function cacheTOPCountries(array $config = [], $topCountries = null)
	{
		# start report
		\Omi\TF\TOInterface::markReportStartpoint($config, 'countries');
		
		# create lock
		if (!file_exists(($lockFile = \Omi\App::GetLogsDir('locks') . 'cacheTOPCountries_on_' . $this->TourOperatorRecord->Handle . '_Lock.txt')))
			file_put_contents($lockFile, 'cacheTOPCountries lock');

		# if locked do not continue execution
		if (!($lock = \QFileLock::lock($lockFile, 1)))
			throw new \Exception('Sincronizarea de tari este inca in procesare - ' . $lockFile);

		try
		{
			$force = $config["force"] ?: false;
			$callMethod = "getCountryRequest";
			$callKeyIdf = null;
			$callParams = [];
			
			// we need to make sure that we have all data in order (countries and continents)
			$refThis = $this;
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
				$reqEX = null;
				try
				{
					$return = $refThis->doRequest($method);
				}
				catch (\Exception $ex)
				{
					$reqEX = $ex;
				}
				if (($rawResp = $return["rawResp"]))
					$rawResp = preg_replace('#(<ResponseTime>(.*?)</ResponseTime>)|(<ResponseId>(.*?)</ResponseId>)|(<RequestId>(.*?)</RequestId>)#', '', $rawResp);
				if (($rawReq = $return["rawReq"]))
					$rawReq = preg_replace('#(<RequestTime>(.*?)</RequestTime>)#', '', $rawReq);
				
				echo "<h5>RAW - {$method}</h5>";
				echo "<pre>".htmlspecialchars(json_encode([$rawReq, $rawResp], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS))."</pre>";

				
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
				return;
			}

			$t1 = microtime(true);
			$data = static::GetResponseData($return, "getCountryResponse");
			
			$countries = $data["Country"] ? $data["Country"] : null;
			$ret = new \QModelArray();
			$topCfg = static::$Config[$this->TourOperatorRecord->Handle]["init"];

			$toAddCountries = $topCfg['ToAddCountries'];
			foreach ($toAddCountries ?: [] as $toAddCountry)
				$countries[] = $toAddCountry;
			$countryByCodes = [];
			foreach ($countries ?: [] as $cd)
			{
				if (($wrongCountryData = (empty($cd["CountryCode"]) || empty($cd["CountryName"]))) || ($topCfg && $topCfg["CountryCodes"] && (!isset($topCfg["CountryCodes"][$cd["CountryCode"]]))))
				{
					echo "<div style='color: red;'>" . ($wrongCountryData ? "Country data not ok: " . json_encode($cd) : 
						"Country [{$cd["CountryCode"]}|{$cd["CountryName"]}] does not meet the config requirements : " . json_encode($topCfg)) . "</div>";
					continue;
				}
				$countryByCodes[$cd["CountryCode"]] = $cd;
			}
			
			if ($countryByCodes)
			{
				// if no countries for code - exit
				if ((!($dbCountries = QQuery("Countries.{Code, Name WHERE Code IN (?)}", [array_keys($countryByCodes)])->Countries)) || (count($dbCountries) === 0))
				{
					echo "<div style='color: red;'>Countries must be in database</div>";
					return;
				}

				// index countries by code
				$dbCountriesByCode = [];
				foreach ($dbCountries ?: [] as $country)
				{
					if (!trim($country->Code))
					{
						echo "<div style='color: red;'>Db country [{$country->getId()}] does not have code</div>";
						continue;
					}
					$dbCountriesByCode[$country->Code] = $country;
				}
				
				$identifiersRes = count($countryByCodes) ? QQuery("ApiStorageIdentifiers.{Identifier, FromTopRemoved, Country.{Code, Name}, TourOperator.Handle "
					. " WHERE TourOperator.Handle=? AND Country.Code IN (?)}", [$this->TourOperatorRecord->Handle, array_keys($countryByCodes)])->ApiStorageIdentifiers : [];

				$countriesIdfs = [];
				foreach ($identifiersRes ?: [] as $idfRow)
				{
					if ((!$idfRow->Country) || (!$idfRow->Country->Code))
					{
						echo "<div style='color: red;'>Idf for country [" . ($idfRow->Country ? $idfRow->Country->getId() : "no_country") . "] does not have code</div>";
						continue;
					}
					$countriesIdfs[$idfRow->Country->Code] = $idfRow;
				}

				// save identifier for country
				$app = \QApp::NewData();
				$app->ApiStorageIdentifiers = new \QModelArray();
				$passedCodes = [];
				$allCountriesIds = [];
				foreach ($countryByCodes ?: [] as $countryCode => $countryData)
				{
					if ((!($dbCountry = $dbCountriesByCode[$countryCode])) || isset($passedCodes[$countryCode]))
					{
						echo "<div style='color: red;'>" . (isset($passedCodes[$countryCode]) ? 
							"Country code [{$countryCode}] is repeated" : "Country [{$countryCode}] not on db") . "</div>";
						continue;
					}
					$allCountriesIds[$countryData["CountryCode"]] = $countryData["CountryCode"];

					$saveIdf = false;
					$apiIdentifier = new \Omi\TF\ApiStorageIdentifier();
					if (($existingIdf = $countriesIdfs[$dbCountry->Code]))
					{
						$apiIdentifier->setId($existingIdf->getId());
						// re-add the item
						if ($existingIdf->FromTopRemoved)
						{
							$saveIdf = true;
							$apiIdentifier->setFromTopAddedDate(date("Y-m-d H:i:s"));
						}
						if ($dbCountry->Name != $countryData["CountryName"])
						{
							echo "<div style='color: orange;'>Country name is different in tour operator for code [{$countryData["CountryCode"]} : " 
								. ($dbCountry->Name . " ~ " . $countryData["CountryName"]) . " - to be revised</div>";
						}
					}
					else
					{
						$saveIdf = true;
						if ($this->TrackReport)
						{
							if (!$this->TrackReport->NewCountries)
								$this->TrackReport->NewCountries = 0;
							$this->TrackReport->NewCountries++;
						}
						$apiIdentifier->__added_to_system__ = true;
						$apiIdentifier->setFromTopAddedDate(date("Y-m-d H:i:s"));
						echo "<div style='color: green;'>Country " . ($countryData["CountryCode"] . "|" . $countryData["CountryName"]) . " identifier added</div>";
					}

					if ($saveIdf)
					{
						$apiIdentifier->setCountry($dbCountry);
						$apiIdentifier->setRemovedFromSource(false);
						$apiIdentifier->setFromTopRemoved(false);
						$apiIdentifier->setFromTopRemovedAt(null);
						$apiIdentifier->setIdentifier($countryData["CountryCode"]);
						$apiIdentifier->setTourOperator($this->TourOperatorRecord);
						$app->ApiStorageIdentifiers[] = $apiIdentifier;
					}
				}

				// save countries indexes
				if (count($app->ApiStorageIdentifiers) > 0)
					$app->save("ApiStorageIdentifiers.{Country, Identifier, TourOperator, FromTopAddedDate, FromTopRemoved, FromTopRemovedAt, RemovedFromSource}");
			}

			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1));
			
			# end report
			\Omi\TF\TOInterface::markReportEndpoint($config, 'countries');
		}
		# catch different exception
		catch (\Exception $ex)
		{
			throw $ex;
		}
		# unlock execution
		finally 
		{
			if ($lock)
				$lock->unlock();
		}

		return $ret;
	}
	
	/**
	 * Cache regions
	 * 
	 * The algorythm
	 *	Load existing top countries
	 *  Load existing top regions
	 *  Get all regions from top - the part with getting them all in one go will be solved on the top implementation
	 *  Go through all regions and setup system regions (setup ids also)
	 *  Flag the regions that we no longer have them in the top api response
	 * 
	 * 
	 * @return type
	 */
	public function cacheTOPRegions($config = [], $allCountiesResp = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($config, 'regions');
		\Omi\TF\TOInterface::markReportData($config, 'tour operator does not have static regions support - they are pulled when getting charters data');
		\Omi\TF\TOInterface::markReportEndpoint($config, 'regions');
	}
	
	protected function getTopCities(array $config = [])
	{
		$force = $config["force"] ?: false;
		$callMethod = "getCityRequest";
		$callKeyIdf = null;
		$callParams = [];

		// we need to make sure that we have all data in order (countries and continents)
		$refThis = $this;
		list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
			$reqEX = null;
			try
			{

				$return = $refThis->doRequest($method, $params, false, false);
				
				echo "<h5>RAW - {$method}</h5>";
				echo "<pre>".htmlspecialchars(json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS))."</pre>";
				echo "<pre>".htmlspecialchars(json_encode($return, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS))."</pre>";
				
			}
			catch (\Exception $ex)
			{
				$reqEX = $ex;
			}
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
			$t1 = microtime(true);
			return [static::GetResponseData($return, "getCityResponse"), $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1];
		}

		$t1 = microtime(true);
		$data = static::GetResponseData($return, "getCityResponse");
		return [$data, $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1];
	}
	
	/**
	 * Returns an array with cities
	 * 
	 * @param array $objs
	 * 
	 * @return type
	 */
	public function cacheTOPCities(array $config = [], $allCitiesResp = null)
	{
		// start report
		\Omi\TF\TOInterface::markReportStartpoint($config, 'cities');
		if (!file_exists(($lockFile = \Omi\App::GetLogsDir('locks') . "cacheTOPCities_on_" . $this->TourOperatorRecord->Handle . "_Lock.txt")))
			file_put_contents($lockFile, "cacheTOPCities lock");

		if (!($lock = \QFileLock::lock($lockFile, 1)))
			throw new \Exception("Sincronizarea de orase este inca in procesare - " . $lockFile);

		try 
		{
			list($data, $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1) = $this->getTopCities($config);

			$topCfg = static::$Config[$this->TourOperatorRecord->Handle]["init"];
			$filterCountries = $topCfg['GetStaticCitiesForCountries'] ?: [];

			$cities = $data["City"] ? $data["City"] : null;
			$ret = new \QModelArray();
			if ($cities && isset($cities["CountryCode"]))
				$cities = [$cities];
			$countriesIds = [];
			$citiesIds = [];
			foreach ($cities ?: [] as $city)
			{
				if (($wrongCityData = (empty($city["CityCode"]) || empty($city["CityName"]) || empty($city["CountryCode"]))) || 
					((!empty($filterCountries)) && (!isset($filterCountries[$city["CountryCode"]]))))
				{
					echo "<div style='color: red;'>" . ($wrongCityData ? "Country data not ok: " . json_encode($city) : 
						"City [{$city["CityCode"]}|{$city["CityName"]}] [{$city["CountryCode"]}|{$city["CountryName"]}] does not meet the config requirements : " . json_encode($filterCountries)) . "</div>";
					continue;
				}

				$countriesIds[$city["CountryCode"]] = $city["CountryCode"];
				$citiesIds[$city["CityCode"]] = $city["CityCode"];
			}

			$countriesByIdf = [];
			$identifiersRes = count($countriesIds) ? QQuery("ApiStorageIdentifiers.{Identifier, FromTopRemoved, Country.{Code, Name}, TourOperator.Handle "
				. " WHERE TourOperator.Handle=? AND Country.Code IN (?)}", [$this->TourOperatorRecord->Handle, array_keys($countriesIds)])->ApiStorageIdentifiers : [];
			foreach ($identifiersRes ?: [] as $idfRes)
			{
				if ($idfRes->Country)
					$countriesByIdf[$idfRes->Identifier] = $idfRes->Country;
			}

			/*
			$dbCountriesRes = count($countriesIds) ? QQuery("Countries.{* WHERE Code IN (?)}", [$countriesIds])->Countries : null;
			foreach ($dbCountriesRes ?: [] as $dbCountry)
				$countriesByCode[$dbCountry->Code] = $dbCountry;
			*/

			$citiesByInTopId = [];
			$dbCitiesRes = count($citiesIds) ? QQuery("Cities.{*, Country.* WHERE InTourOperatorId IN (?) AND TourOperator.Handle=?}", 
				[$citiesIds, $this->TourOperatorRecord->Handle])->Cities : null;
			foreach ($dbCitiesRes ?: [] as $dbCity)
				$citiesByInTopId[$dbCity->InTourOperatorId] = $dbCity;

			$app = \QApp::NewData();
			$app->Cities = new \QModelArray();
			
			foreach ($cities ?: [] as $city)
			{
				if (empty($city["CityCode"]) || empty($city["CityName"]) || empty($city["CountryCode"]))
					continue;
				if ((!$countryObj = $countriesByIdf[$city["CountryCode"]]))
				{
					echo "<div style='color: red;'>skip city [{$city["CityCode"]}] {$city["CityName"]} because country was not found - [{$city["CountryCode"]}]!</div>";
					continue;
				}

				$saveCity = false;
				if (!($cityObj = $citiesByInTopId[$city["CityCode"]]))
				{
					$cityObj = new \Omi\City();			
					$cityObj->setFromTopAddedDate(date("Y-m-d H:i:s"));
					echo "<div style='color: green;'>New City [{$city["CityCode"]}|{$city["CityName"]}] added to country [{$city["CountryCode"]}]</div>";
					if ($this->TrackReport)
					{
						if (!$this->TrackReport->NewCities)
							$this->TrackReport->NewCities = 0;
						$this->TrackReport->NewCities++;
					}
					$saveCity = true;
				}
				
				$cityObj->setTourOperator($this->TourOperatorRecord);
				$cityObj->setInTourOperatorId($city["CityCode"]);
				$cityObj->setCountry($countryObj);
				
				if (($cityObj->Name != $city["CityName"]))
				{
					echo "<div style='color: green;'>Name for city [{$city["CityCode"]}] of country [{$city["CountryCode"]}] " 
						. "changed from `{$cityObj->Name}` to `{$city["CityName"]}`</div>";
					$cityObj->setFromTopModifiedDate(date("Y-m-d H:i:s"));
					$cityObj->setName($city["CityName"]);
					$saveCity = true;
				}
				
				if ($cityObj->Country->Code != $city["CountryCode"])
				{
					echo "<div style='color: red;'>City has issues - It was first linked to [{$cityObj->Country->Code}] and not it is linked with [{$city["CountryCode"]}]</div>";
					continue;
				}

				if ($saveCity)
					$app->Cities[] = $cityObj;
			}

			if (count($app->Cities))
			{
				$app->save("Cities.{Name, TourOperator.Id, InTourOperatorId, Country, FromTopModifiedDate, FromTopAddedDate}");
			}
			
			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1));
		} 
		catch (\Exception $ex)
		{
			throw $ex;
		}
		finally 
		{
			if ($lock)
				$lock->unlock();
		}

		\Omi\TF\TOInterface::markReportEndpoint($config, 'cities');
		return $ret;
	}
	
	public function getTOPCitiesWithIndividualOffers(array $config = [])
	{
		$force = $config["force"] ?: false;
		$callMethod = "getOwnCityRequest";
		$callKeyIdf = null;
		$callParams = [];

		// we need to make sure that we have all data in order (countries and continents)
		$refThis = $this;
		list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
			$reqEX = null;
			try
			{
				# qvar_dump('$method, $params', $method, $params);
				# doRequest($requestType, $params = null, $saveAttrs = false, $useCache = false)
				$return = $refThis->doRequest($method, $params, false, false);
			}
			catch (\Exception $ex)
			{
				$reqEX = $ex;
			}
			
			echo "<h5>RAW - {$method} - a</h5>";
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
			#return;
		}

		$t1 = microtime(true);
		$data = static::GetResponseData($return, "getOwnCityResponse");

		return [(($data && $data['City']) ? $data['City'] : null), $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1];
	}

	/**
	 * Returns an array with cities
	 * @param array $objs
	 * @return type
	 */
	public function cacheTOPCitiesWithIndividualOffers(array $config = [])
	{
		#$setupAsActive = $config['setupAsActive'];
		$setupAsActive = true;
		list($destinations, $callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, $t1) = $this->getTOPCitiesWithIndividualOffers($config);

		$notInTransports = [];
		if ($destinations)
		{
			if (isset($destinations['CityCode']))
				$destinations = [$destinations];
			$indexedDestinations = [];
			foreach ($destinations ?: [] as $destination)
			{
				if (empty($destination['CountryCode']))
					continue;
				
				if ((!is_scalar($destination['CountryCode'])) || (!is_scalar($destination['CityCode'])) || (!is_scalar($destination['CityName'])))
					continue;
				$indexedDestinations[$destination['CountryCode']][$destination['CityCode']] = $destination['CityCode'];
				$notInTransports[$destination['CountryCode'] . '_' . $destination['CityCode']] = $destination['CountryCode'] . '_' . $destination['CityCode'];
			}
		}

		$existingTransports = [];
		$allCitiesById = [];
		foreach ($indexedDestinations ?: [] as $countryCode => $citiesCodes)
		{
			$cities = QQuery("Cities.{Name WHERE TourOperator.Handle=? AND Country.Code=? AND InTourOperatorId IN (?)}", 
				[$this->TourOperatorRecord->Handle, $countryCode, $citiesCodes])->Cities;

			$_etransp = QQuery("IndividualTransports.{"
				. "To.City.{"
					. "Name, "
					//. "HasTravelItems, "
					. "InTourOperatorId, "
					. "IsMaster, "
					. "Master.{"
						. "IsMaster, "
						//. "HasTravelItems, "
						. "County.IsMaster"
						//. "County.{IsMaster, HasTravelItems}"
					. "}, "
					. "County.{"
						. "IsMaster, "
						//. "HasTravelItems, "
						. "Master.IsMaster"
						//. "Master.{IsMaster, HasTravelItems}"
					. "}"
				. "},"
			. "Content.*, Edited WHERE HasSeries=0 AND TourOperator.Handle=? AND To.City.InTourOperatorId IN (?)}", 
			[$this->TourOperatorRecord->Handle, $citiesCodes])->IndividualTransports;

			foreach ($_etransp ?: [] as $_trnsp)
			{
				if ((!$_trnsp->To) || (!$_trnsp->To->City))
					continue;
				$existingTransports[$_trnsp->To->City->getId()] = $_trnsp;
			}

			$appData = \QApp::NewData();
			$appData->IndividualTransports = new \QModelArray();

			foreach ($cities ?: [] as $city)
			{
				$allCitiesById[$city->getId()] = $city->getId();
				if (!isset($existingTransports[$city->getId()]))
				{
					$transport = new \Omi\TF\IndividualTransport();
					$transport->setContent(new \Omi\Cms\Content());
					$transport->Content->setActive($setupAsActive);
					$transport->setTourOperator($this->TourOperatorRecord);
					$transport->setAppAllIndividualTransports(true);
					$transport->setTo(new \Omi\Address());
					$transport->setHasSeries(false);
					$transport->To->setCity($city);
					if ($city->County)
						$transport->To->setCounty($city->County);
					if ($city->Country)
						$transport->To->setCountry($city->Country);
					$appData->IndividualTransports[] = $transport;
				}
			}

			if (count($appData->IndividualTransports))
			{
				$appData->save("IndividualTransports.{"
					. "HasSeries,"
					. "TourOperator,"
					. "AppAllIndividualTransports, "
					. "Content.Active,"
					. "To.{"
						. "City.*,"
						. "County.*,"
						. "Country.*"
					. "}"
				. "}");
			}
		}
		
		$app = \QApp::NewData();
		$app->IndividualTransports = new \QModelArray();
		
		$extraTransportsBinds = [
			"TourOperator" => $this->TourOperatorRecord->getId()
		];
		
		if ($notInTransports) 
		{
			$extraTransportsBinds['NotInIndx'] = [$notInTransports];
		}
		
		$extraTransports = QQuery("IndividualTransports.{Active, "
				. "To.City.{"
					. "Name, "
					. "InTourOperatorId, "
					. "Country.{"
						. "Code, Name"
					. "}"
				. "},"
			. "Content.Active, Edited WHERE HasSeries=0 AND 1 "
				. "??TourOperator?<AND[TourOperator.Id=?]"
				. "??NotInIndx?<AND[CONCAT(To.City.Country.Code, '_', To.City.InTourOperatorId) NOT IN (?)]"
			.  "}", 
			$extraTransportsBinds)->IndividualTransports;
		
		foreach ($extraTransports ?: [] as $existingTransport)
		{
			if ((!$existingTransport->To) || (!$existingTransport->To->City) || 
				(isset($allCitiesById[$existingTransport->To->City->getId()])))
				continue;
			if (!$existingTransport->Content)
				$existingTransport->setContent(new \Omi\Cms\Content());
			if ($existingTransport->Content->Active)
			{
				$existingTransport->Content->setActive(false);
				$app->IndividualTransports[] = $existingTransport;
			}	
		}
		if (count($app->IndividualTransports))
		{
			$app->save('IndividualTransports.Content.Active');
		}

		$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1));
	}
}