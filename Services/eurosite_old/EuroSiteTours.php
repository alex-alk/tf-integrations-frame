<?php

namespace Omi\TF;

trait EuroSiteTours
{
	/*===================================TOURS DATA=============================================*/
	/**
	 * Returns an array with countries
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	public function getCountriesWithTours(&$objs = null)
	{
		$data = static::GetResponseData($this->doRequest("CircuitSearchCityRequest"), "CircuitSearchCityResponse");
		return $this->getCountriesWithServices($data ? $data["Country"] : null, $objs);
	}

	/**
	 * Returns a list of offers populated with tours
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getTours($params = null, &$objs = null)
	{
		#$showDiag = ($_SERVER["REMOTE_ADDR"] == "86.125.118.86");
		$showDiag = \QAutoload::In_Debug_Mode();

		try
		{
			if ($showDiag)
				ob_start();
			
			if (!$params)
				$params = [];
			$transport = $params["Transport"];
			if ($this->TourOperatorRecord->ApiContext)
				$params["TourOpCode"] = $this->TourOperatorRecord->ApiContext;
			$initialParams = $params;
			unset($params["Transport"]);
			unset($params["VacationType"]);
			unset($params["PeriodOfStay"]);
			unset($params["__CACHED_TRANSPORTS__"]);
			unset($params["__CACHED_TRANSPORTS_DESTINATIONS__"]);
			unset($params["__CACHED_TRANSPORTS_NIGHTS__"]);
			unset($params["_cache_use"]);
			unset($params["_cache_create"]);
			unset($params["getFeesAndInstallments"]);
			unset($params["SellCurrency"]);
			unset($params["RequestCurrency"]);
			unset($params["DepCountryCode"]);
			unset($params["DepCityCode"]);
			unset($params["ProductName"]);
			unset($params["ProductType"]);
			unset($params["ID"]);

			$callParams = $params;
			#if ((!$params["ProductCode"]) && isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
			if (isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
				unset($callParams['TourOpCode']);

			$to_response = $this->doRequest("CircuitSearchRequest", $callParams, true);
			$data = static::GetResponseData($to_response, "CircuitSearchResponse");

			$params = $initialParams;
			$tours = $data["Circuit"] ? $data["Circuit"] : null;
			if (!$tours || (count($tours) === 0))
				return [];
			if (isset($tours["TourOpCode"]))
				$tours = array($tours);

			$appData = \QApp::NewData();
			$appData->Tours = new \QModelArray();
			$countryCodes = [];
			$toursCodes = [];
			$_ret_tours = [];
			$reqParams = static::GetRequestParams($params);

			foreach ($tours as $tour)
			{
				// make sure that we only return the requested tour - otherwise we can have problems due to caching
				// make sure that all tours are for relevant tour operator
				if ((!$tour["TourOpCode"] || $tour["TourOpCode"] != $this->TourOperatorRecord->ApiContext) || 
					(!$tour["CircuitId"] || ($params["ProductCode"] && ($params["ProductCode"] != $tour["CircuitId"]))))
				{
					if ($showDiag)
						echo "<div style='color: red;'>SKIP TOUR [{$tour["CircuitId"]}|{$tour["TourOpCode"]}] because not in api context or filtered by product code [{$params["ProductCode"]}]</div>";
					continue;
				}
				$_ret_tours[] = $tour;
				$toursCodes[$tour["CircuitId"]] = $tour["CircuitId"];

				/*
				if (!$tour["CountryCode"])
				{
					if ($showDiag)
					{
						echo "<div style='color: red;'>SKIP TOUR [{$tour["CircuitId"]}|{$tour["TourOpCode"]}] bacause it does not have a country code</div>";
					}
					continue;
				}
				*/
				if ($tour["CountryCode"])
					$countryCodes[$tour["CountryCode"]] = $tour["CountryCode"];
			}

			$this->FindExistingItem("Countries", $countryCodes, "Code, Name, InTourOperatorsIds.{TourOperator, Identifier}");	
			// sanitize selector
			$useEntity = q_SanitizeSelector("Tours", $this->getToursEntity());

			$this->FindExistingItem("Tours", $toursCodes, $useEntity);

			$ret = [];
			if ($objs === null)
				$objs = [];

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
				foreach ($_ret_tours as $tour)
				{
					$tourObj = $appData->Tours[$tour["CircuitId"]] ?: $this->FindExistingItem("Tours", $tour["CircuitId"], $useEntity);
					if (!($tourObj))
					{
						$toPullToursFromTF[$tour["CircuitId"]] = $tour["CircuitId"];
					}

					if ($isTourSearch && $tourObj && $tourObj->SyncStatus && $tourObj->SyncStatus->LiteSynced && (!$tourObj->SyncStatus->FullySynced))
					{
						$toPullToursFromTF[$tour["CircuitId"]] = $tour["CircuitId"];
					}
				}

				if ($toPullToursFromTF && $this->TourOperatorRecord->Handle)
				{
					list($from_TF_Tours) = \Omi\Travel\Merch\Tour::TourSync_SyncFromTF($this->TourOperatorRecord->Handle, $toPullToursFromTF, $isTourSearch);
					foreach ($from_TF_Tours ?: [] as $TF_Tour)
					{
						if ($TF_Tour->getId() && $TF_Tour->InTourOperatorId)
						{
							static::$_CacheData["DB::Tours::{$this->TourOperatorRecord->getId()}::{$TF_Tour->InTourOperatorId}"] = $TF_Tour;
						}
					}
				}
			}


			foreach ($_ret_tours as $tour)
			{
				$tourObj = $appData->Tours[$tour["CircuitId"]] ?: $this->FindExistingItem("Tours", $tour["CircuitId"], $useEntity);

				if (!$tourObj)
				{
					try {
						list($tourObj) = $this->getTour($tour, $tourObj, $objs, true);

						if (!$tourObj)
							continue;
						$appData->Tours[$tour["CircuitId"]] = $tourObj;

					} catch (Exception $ex) {
						if ($showDiag)
							echo "<div style='color: red;'>SKIP TOUR [{$tour["CircuitId"]}|{$tour["TourOpCode"]}] not found on tur operator</div>";
						// do log here
						continue;
					}
				}

				$departureIsAccepted = false;
				$departure = null;
				foreach ($tourObj->Departures ?: [] as $departureTmp)
				{
					if ($departureTmp->DepartureGroup && $departureTmp->DepartureGroup->DepartureCity && 
						($departureTmp->DepartureGroup->DepartureCity->InTourOperatorId == $params["DepCityCode"]) && 
						($departureTmp->DepartureGroup->TransportType == $transport)
					)
					{
						$departure = $departureTmp;
						$departureIsAccepted = true;
						break;
					}
				}

				if (!$departureIsAccepted)
				{
					if ($showDiag)
						echo "<div style='color: red;'>SKIP TOUR [{$tour["CircuitId"]}|{$tour["TourOpCode"]}] because it does not have the departure setup</div>";
					continue;
				}

				$tourObj->setTourOperator($this->TourOperatorRecord);
				$tourObj->setInTourOperatorId($tourObj->Code);

				if (!$tourObj->Content)
					$tourObj->Content = new \Omi\Cms\Content();

				if (!$tourObj->TopContent)
					$tourObj->TopContent = new \Omi\Cms\Content();

				if ($tour["Description"] && is_scalar($tour["Description"]))
				{
					$tourObj->TopContent->Content = htmlspecialchars_decode($tour["Description"], ENT_QUOTES);
					$tourObj->TopContent->Content = preg_replace("/\\r\\n/", "<br/>", $tourObj->TopContent->Content);
				}

				// setup details on tours
				if ($tour["Description"] && is_scalar($tour["Description"]) && (!$tourObj->BlockContentUpdate))
				{
					$tourObj->Content->Content = htmlspecialchars_decode($tour["Description"], ENT_QUOTES);
					$tourObj->Content->Content = preg_replace("/\\r\\n/", "<br/>", $tourObj->Content->Content);
				}

				//if ($tour["Period"])
				//	$tourObj->setPeriod((int)$tour["Period"] - 1);

				if ($tour["Period"])
					$tourObj->setPeriod((int)$tour["Period"]);

				$stages = ($tour["DayDescriptions"] && $tour["DayDescriptions"]["DayDescription"]) ? $tour["DayDescriptions"]["DayDescription"] : null;
				$this->getTourInfo_setupStages($tourObj, $stages, "TopStages");
				// stages to be setup only if not update content blocked
				if (!$tourObj->BlockContentUpdate)
				{
					$this->getTourInfo_setupStages($tourObj, $stages, "Stages");
				}

				// deal with variants here - ask eurosite
				$variants = ($tour["Variants"] && $tour["Variants"]["Variant"]) ? $tour["Variants"]["Variant"] : null;

				if (!($variants) || (count($variants) === 0))
				{
					if ($showDiag)
						echo "<div style='color: red;'>No offers on tour [{$tour["CircuitId"]}|{$tour["TourOpCode"]}]</div>";
					continue;
				}

				if (isset($variants["UniqueId"]))
					$variants = array($variants);

				$tourObj->Offers = new \QModelArray();

				// check if the transport is accepted (bus|plane)
				$transportAccepted = false;

				// foreach variants
				foreach ($variants ?: [] as $variant)
				{
					$offer = $this->getTourOffer($variant, $tourObj, $tour, $objs, $params, static::$RequestOriginalParams, $departure);

					if (!$offer)
					{
						if ($showDiag)
							echo "<div style='color: red;'>Offer cannot be setup on tour {$variant["UniqueId"]} - [{$tour["CircuitId"]}|{$tour["TourOpCode"]}]</div>";
						continue;
					}

					if (!$offer->Content)
						$offer->Content = new \Omi\Cms\Content();

					$offer->Content->Title = $tourObj->Title;

					if ($tourObj->Content)
						$offer->Content->Content = $tourObj->Content->Content;
					if ($offer->Item)
						$offer->Item->Merch = $tourObj;
					$tourObj->Offers[$offer->Code] = $offer;

					if ($offer->isAvailable())
						$tourObj->_has_available_offs = true;
					else if ($offer->isOnRequest())
						$tourObj->_has_onreq_offs = true;

					if (($tt = $offer->getTransportType()) && ($tt == $transport))
						$transportAccepted = true;

					$offer->ReqParams = $reqParams;
				}

				if (!$transportAccepted)
				{
					if ($showDiag)
						echo "<div style='color: red;'>Transport is not accepted for tour {$transport} - [{$tour["CircuitId"]}|{$tour["TourOpCode"]}]</div>";
					continue;
				}
				$ret[$tourObj->InTourOperatorId] = $tourObj;
			}

			if (count($ret) > 0)
			{
				//\Omi\TFuse\Api\TravelFuse::UpdateToursStatus($ret);
				\QApp::AddCallbackAfterResponseLast(function ($tours) {
					// sync tours status
					\Omi\TFuse\Api\TravelFuse::UpdateToursStatus($tours);
				}, [$ret]);
			}

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

			if (count($appData->Tours) === 0)
			{
				if ($ret)
					$this->flagToursTravelItems($ret);
				return $ret;
			}

			$appData->save("Tours.{Title, TopTitle, MTime, Code, Period, Price, "
				. "InTourOperatorId,"
				. "TourOperator, "
				. "Content.{ShortDescription, Content, Active, Order, "
					. "ImageGallery.{Tag, "
						. "Items.{Updated, Type, Path, ExternalUrl, RemoteUrl, Base64Data, TourOperator.{Handle, Caption, Abbr}, InTourOperatorId, Alt}"
					. "}, "
					. "VideoGallery.{Tag, "
						. "Items.{Updated, Type, Path, RemoteUrl}"
					. "}"
				. "}, "
				. "TopContent.{ShortDescription, Content, Active, Order, "
					. "ImageGallery.{Tag, "
						. "Items.{Updated, Type, Path, ExternalUrl, RemoteUrl, Base64Data, TourOperator.{Handle, Caption, Abbr}, InTourOperatorId, Alt}"
					. "}, "
					. "VideoGallery.{Tag, "
						. "Items.{Updated, Type, Path, RemoteUrl}"
					. "}"
				. "}, "
				. "Location.{Country.{Code, Name}, County.{Code, Name}, City.{Code, Name}, Latitude, Longitude}, "
				. "Stages.{"
					. "Date, "
					. "Content.{Title, ShortDescription, Content, Active, Order}"
				. "},"
				. "TopStages.{"
					. "Date, "
					. "Content.{Title, ShortDescription, Content, Active, Order}"
				. "}"
			. "}");

			if ($ret)
				$this->flagToursTravelItems($ret);

			return $ret;
		}
		finally
		{
			$out_data = null;
			if ($showDiag)
			{
				$out_data = ob_get_clean();
			}
			if ($to_response)
			{
				q_remote_log_sub_entry([
					[
						'Timestamp_ms' => (string)microtime(true),
						'Tags' => ['tag' => 'Eurosite - getTours'],
						'Data' => [
							'$out_data' => $out_data,
							'$appData' => $appData,
							'return' => $ret],
					]
				]);
			}
		}
	}
	/**
	 * Returns an offer populated with a tour
	 * 
	 * @param type $circuit
	 * @param type $objs
	 * @param boolean $force
	 * @return type
	 */
	private function getTour($circuit, $tourObj = null, &$objs = null, $force = false, $config = null)
	{
		$tStartProcess = microtime(true);

		$params = [
			"ProductCode" => $circuit["CircuitId"], 
			"TourOpCode" => $circuit["TourOpCode"],
			"ProductType" => "circuit"
		];
		
		$useCachedReqs = ($config && $config["useCachedReqs"]);

		// we must skip cache because we don't have the product parameter and it will cache at first and after will give results from cache for each tour
		$cache_use = \Omi\App::$_ApiQueryOriginalParams["_cache_use"];
		\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = false;

		$callMethod = "getProductInfoRequest";
		$callKeyIdf = md5(json_encode($params));
		$callParams = $params;
		
		
		$return = null;
		$alreadyProcessed = false;

		$useLastTracking = false;
		if ($useCachedReqs)
		{
			$lastTrackingResp = \Omi\TF\SyncTopTrack::GetLastTrackingByMethodAndKeyIdf($this->TourOperatorRecord, $callMethod, $callKeyIdf);
			if ($lastTrackingResp)
			{
				$lastTrackingResp = preg_replace('#(<ResponseTime>(.*?)</ResponseTime>)|(<ResponseId>(.*?)</ResponseId>)|(<RequestId>(.*?)</RequestId>)#', '', $lastTrackingResp);
				$return = ["success" => true, "data" => $this->SOAPInstance->decodeResponse($lastTrackingResp, true, true, $callMethod), "rawResp" => $lastTrackingResp];
				$useLastTracking = true;
			}
		}

		if (!$useLastTracking)
		{
			// we need to make sure that we have all data in order (countries and continents)
			$refThis = $this;
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
				$reqEX = null;
				try
				{
					$return = $refThis->doRequest($method, $params, true);
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
		}

		if ((!$return) || ($alreadyProcessed && (!$force)))
		{
			return [null, $alreadyProcessed];
		}

		$data = static::GetResponseData($return, "getProductInfoResponse");
		
		\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = $cache_use;

		if (!($tour = ($data["Product"]) ? $data["Product"] : null))
		{
			if (!$useLastTracking)
				$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $tStartProcess));
			return [null, false];
		}

		$tourCitiesCodes = [];
		$tourCitiesCodes[($tour["CountryCode"] ?: 0)][($tour["CityCode"] ?: 0)] = ["Code" => $tour["CityCode"], "Name" => $tour["CityName"]];
		$tourDestinations = ($tour["Destinations"] && $tour["Destinations"]["CircuitDestination"]) ? $tour["Destinations"]["CircuitDestination"] : null;
		
		if (!$tourDestinations)
			$tourDestinations = ($tour["Destinations"] && $tour["Destinations"]["Destination"]) ? $tour["Destinations"]["Destination"] : null;
		
		if ($tourDestinations && isset($tourDestinations["CityCode"]))
			$tourDestinations = [$tourDestinations];
		
		$topCfg = static::$Config[$this->TourOperatorRecord->Handle]["skip_cities_on_tours"];
		
		foreach ($tourDestinations ?: [] as $tourDestination)
		{
			if ($topCfg &&array_key_exists($tourDestination['CityCode'], $topCfg))
			{
				echo '<div style="color: red; font-weight: bold;">City is skipped on tours :: ' . $topCfg[$td['CityCode']] . '</div>';
				
				continue;
			}
			
			$tourCitiesCodes[($tourDestination["CountryCode"] ?: 0)][($tourDestination["CityCode"] ?: 0)] = 
				["Code" => $tourDestination["CityCode"], "Name" => $tourDestination["CityName"]];
		}

		$firstCountry = null;
		$firstCity = null;
		$cityCountryCode = null;
		$cityCodeWithCountry = null;

		$citiesData = [];
		foreach ($tourCitiesCodes ?: [] as $tmpCountryCode => $countryCities)
		{
			foreach ($countryCities ?: [] as $tmpCityCode => $cityData)
			{
				$citiesData[$tmpCityCode] = $cityData;
				if ($tmpCityCode !== 0)
				{
					if ($tmpCountryCode !== 0)
					{
						$cityCodeWithCountry = $tmpCityCode;
						$cityCountryCode = $tmpCountryCode;
						break;
					}
					else if ($firstCity !== null)
						$firstCity = $tmpCityCode;
				}
			}
			if (($tmpCountryCode !== 0) && ($firstCountry !== null))
				$firstCountry = $tmpCountryCode;
		}

		$countryCode = ($cityCountryCode ?: $firstCountry);
		$cityCode = ($cityCodeWithCountry ?: $firstCity);

		$countryObj = null;
		$cityObj = null;
		if ($countryCode)
		{
			if (!($countryObj = $this->FindExistingItem("Countries", $countryCode)))
			{
				qvardump("Country not found for tour: [{$countryCode}|" . strlen($countryCode) . "]", $tour, $countryCode);
				#throw new \Exception("Country not found!");
			}
		}
		if ($cityCode && $countryObj)
		{
			$cityData = $citiesData[$cityCode];
			$cityObj = $this->getCityObj($objs, $cityCode, $cityData["CityName"], $cityData["CityCode"]);
			if ($cityObj && $countryObj)
				$cityObj->setCountry($countryObj);
		}

		if ((!$cityObj) && (!$countryObj))
		{
			if (!$useLastTracking)
				$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $tStartProcess));
			
			return [null, false];
		}

		$tour["CircuitId"] = $tour["ProductCode"];
		$tour["Name"] = $tour["ProductName"];

		if ($tourObj === null)
			$tourObj = $this->FindExistingItem("Tours", $tour["ProductCode"], q_SanitizeSelector("Tours", $this->getToursEntity()));

		$saveTour = false;
		$dumpData = true;

		if (!$tourObj)
		{
			$tourObj = $objs[$tour["CircuitId"]]["\Omi\Travel\Merch\Tour"] ?: ($objs[$tour["CircuitId"]]["\Omi\Travel\Merch\Tour"] = new \Omi\Travel\Merch\Tour());
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>New Tour [{$tour["ProductName"]}|{$tour["CircuitId"]}]</div>";
				
		}

		// return straight from cache
		$tourObj->setTourOperator($this->TourOperatorRecord);
		if (!$tourObj->getId())
			$tourObj->setFromTopAddedDate(date("Y-m-d H:i:s"));

		if (!$tourObj->Location)
		{
			#$tourObj->Location = $objs[$cityObj->Code]["\Omi\Address"] ?: ($objs[$cityObj->Code]["\Omi\Address"] = new \Omi\Address());
			$tourObj->Location = new \Omi\Address();
		}

		if ($cityObj)
		{
			if ((!isset($tourObj->Location->City)) || ($tourObj->Location->City->getId() != $cityObj->getId()))
			{
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new/changed city</div>";
			}
			$tourObj->Location->setCity($cityObj);
			$tourObj->Location->setCounty(null);
			$tourObj->Location->setCountry(null);
		}
		else
		{
			if ((!isset($tourObj->Location->Country)) || ($tourObj->Location->Country->getId() != $countryObj->getId()))
			{
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new/changed country</div>";
			}
			$tourObj->Location->setCity(null);
			$tourObj->Location->setCounty(null);
			$tourObj->Location->setCountry($countryObj);
		}

		if ($tourObj->Code != $tour["CircuitId"])
		{
			$tourObj->Code = $tour["CircuitId"];
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - code</div>";
		}

		if ($tourObj->Period != $circuit["Period"])
		{
			$tourObj->setPeriod((int)$circuit["Period"]);
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - period</div>";
		}

		$tourTitle = $tour["Name"] ? $tour["Name"] : (($tourObj->Location && $tourObj->Location->City && $tourObj->Location->City->Country) ? 
			"Circuit " . $tourObj->Location->City->Country->Name : "Circuit");

		if ($tourObj->Title != $tourTitle)
		{
			$tourObj->Title = $tourTitle;
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - title</div>";
		}

		if ($tourObj->TopTitle != $tour["Name"])
		{
			$tourObj->TopTitle = $tour["Name"];
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - top title</div>";
		}

		if (!$tourObj->Content)
		{
			$tourObj->Content = new \Omi\Cms\Content();
			$tourObj->Content->setActive(true);
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new content</div>";
		}

		if (!$tourObj->TopContent)
		{
			$tourObj->TopContent = new \Omi\Cms\Content();
			$tourObj->TopContent->setActive(true);
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new top content</div>";
		}

		if ($tourObj->InTourOperatorId != $tourObj->Code)
		{
			$tourObj->setInTourOperatorId($tourObj->Code);
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new tour operator id</div>";
		}

		// get stages
		$stages = ($tour["DayDescriptions"] && $tour["DayDescriptions"]["DayDescription"]) ? $tour["DayDescriptions"]["DayDescription"] : null;
		$topStagesChanged = $this->getTourInfo_setupStages($tourObj, $stages, "TopStages", $dumpData);
		if ($topStagesChanged)
		{
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - top stages changed</div>";
		}
		$topContentChanged = $this->getTourInfo_setupContent($tourObj, $tour, "TopContent", $dumpData);
		if ($topContentChanged)
		{
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - top content changed</div>";
		}

		if (!$tourObj->BlockContentUpdate)
		{
			if (!empty($tourObj->Title))
				$tourObj->Content->setTitle($tourObj->Title);
			$stagesChanged = $this->getTourInfo_setupStages($tourObj, $stages, "Stages", $dumpData);
			if ($stagesChanged)
			{
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - stages changed</div>";
			}
			$contentChanged = $this->getTourInfo_setupContent($tourObj, $tour, "Content", $dumpData);
			if ($contentChanged)
			{
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - content changed</div>";
			}
		}
	
		// get tour destinations from circuits
		$tourDestinations = ($circuit["Destinations"] && $circuit["Destinations"]["CircuitDestination"]) ? $circuit["Destinations"]["CircuitDestination"] : null;
		if ($tourDestinations && $tourDestinations["CityCode"])
			$tourDestinations = [$tourDestinations];

		$variants = ($circuit["Variants"] && $circuit["Variants"]["Variant"]) ? $circuit["Variants"]["Variant"] : null;
		if ($variants && $variants["UniqueId"])
			$variants = [$variants];

		// setup discount labels on tour
		$labels = $circuit["Label"] ? explode(",", $circuit["Label"]) : [];

		/*
		if (!$tourObj->DiscountLabels)
			$tourObj->setDiscountLabels(new \QModelArray());

		$existingLabels = [];
		foreach ($tourObj->DiscountLabels as $lbl)
			$existingLabels[$lbl] = $lbl;

		foreach ($labels ?: [] as $label)
		{
			$label = trim($label);
			if (!isset($existingLabels[$label]))
			{
				$tourObj->setDiscountLabels_Item_($label);
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new discount label</div>";
			}
		}

		foreach ($tourObj->DiscountLabels as $_lk => $label)
		{
			if (!in_array($label, $labels))
			{
				$tourObj->DiscountLabels->setTransformState(\QIModel::TransformDelete, $_lk);
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - remove discount label</div>";
			}
		}
		*/

		if (!$tourObj->Destinations)
			$tourObj->Destinations = new \QModelArray();

		$e_destinations = [];
		foreach ($tourObj->Destinations ?: [] as $k => $dCity)
			$e_destinations[$dCity->InTourOperatorId] = [$k, $dCity];

		$processedDestinations = [];
		$allTourDestinations = [];
		$cachedCities = [];
		foreach ($tourDestinations ?: [] as $td)
		{
			if ((!$td["CityCode"]) || (!$td["CityName"]) || isset($allTourDestinations[$td["CityCode"]]))
			{
				continue;
			}

			$allTourDestinations[$td["CityCode"]] = $td["CityCode"];
			try {
				$cityObj = $cachedCities[$td["CityCode"]] ?: ($cachedCities[$td["CityCode"]] = $this->getCityObj($objs, $td["CityCode"], $cityData["CityName"], $cityData["CityCode"]));
			}
			catch (\Exception $ex) 
			{
				if ($topCfg && array_key_exists($td['CityCode'], $topCfg))
				{
					echo '<div style="color: red; font-weight: bold;">City is skipped on tours :: ' . $topCfg[$td['CityCode']] . '</div>';
					
					continue;
				}
				
				throw $ex;
			}

			if (!$cityObj)
				continue;
			
			$processedDestinations[$cityObj->InTourOperatorId] = $cityObj;
			if (isset($e_destinations[$cityObj->InTourOperatorId]))
				continue;
			$saveTour = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new destination</div>";
			$tourObj->Destinations[] = $cityObj;
		}

		foreach ($e_destinations as $eDestData)
		{
			list($k, $eDestCity) = $eDestData;
			if (!$processedDestinations[$eDestCity->InTourOperatorId])
			{
				$tourObj->Destinations->setTransformState(\QIModel::TransformDelete, $k);
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - remove destination</div>";
			}
		}

		if (!$tourObj->_existing_transport_types)
			$tourObj->_existing_transport_types = [];

		foreach ($tourObj->TransportTypes ?: [] as $tk => $tt)
			$tourObj->_existing_transport_types[$tt] = $tk;

		$transportTypes = [];
		// go through offers
		foreach ($variants ?: [] as $variant)
		{
			$variantTopCode = $variant["UniqueId"] ? end(explode("|", $variant["UniqueId"])) : null;
			// filter variant by top code
			if ((!$variantTopCode) || ($variantTopCode != $this->TourOperatorRecord->ApiContext))
				continue;
			$transportType = null;
			$services = $variant["Services"]["Service"];
			if ($services)
			{
				if ($services["Type"])
					$services = [$services];
				foreach ($services as $serv)
				{
					if ($serv["Type"] == "7")
					{
						$transportType = strtolower($serv["Transport"]);
						break;
					}
				}
			}
			if (!$transportType)
				throw new \Exception("Transport type cannot be determined!");
			$transportTypes[$transportType] = $transportType;
			if (!$tourObj->HasActiveOffers)
			{
				$tourObj->setHasActiveOffers(true);
				$saveTour = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - flag acvive offers</div>";
			}
			if ($transportType === "plane")
			{
				if (!$tourObj->HasPlaneActiveOffers)
				{
					$tourObj->setHasPlaneActiveOffers(true);
					$saveTour = true;
					if ($dumpData)
						echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - flag plane acvive offers</div>";
				}
			}
			else if ($transportType === "bus")
			{
				if (!$tourObj->HasBusActiveOffers)
				{
					$tourObj->setHasBusActiveOffers(true);
					$saveTour = true;
					if ($dumpData)
						echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - flag bus acvive offers</div>";
				}
			}
		}

		// setup tour transport typs
		if (!$tourObj->TransportTypes)
			$tourObj->TransportTypes = new \QModelArray();

		foreach ($transportTypes as $tt)
		{
			if (!$tourObj->_existing_transport_types || !isset($tourObj->_existing_transport_types[$tt]))
			{
				$tourObj->TransportTypes[] = $tt;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - new transport type</div>";
				$saveTour = true;
			}
		}

		foreach ($tourObj->_existing_transport_types ?: [] as $ett => $etk)
		{
			if (!isset($transportTypes[$ett]))
			{
				$tourObj->TransportTypes->setTransformState(\QIModel::TransformDelete, $etk);
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tour["CircuitId"]}] - data changed - remove transport type</div>";
				$saveTour = true;
			}
		}

		if (!$useLastTracking)
		{
			// add tracking
			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $tStartProcess));
		}

		return [$tourObj, false, $saveTour];
	}
	/**
	 * Returns supliments for tours
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getTourSupliments($params, &$objs = null)
	{
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}

		$data = static::GetResponseData($this->doRequest("CircuitSearchServiceRequest", $params), "CircuitSearchServiceResponse");
		$services = ($data["Services"] && $data["Services"]["Service"]) ? $data["Services"]["Service"] : null;
		return $this->getServices($services, $objs);
	}
	/**
	 * Returns supliments for tours
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getTourSupliment($params, &$objs = null)
	{
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}

		$data = static::GetResponseData($this->doRequest("CircuitSearchServicePriceRequest", $params), "CircuitSearchServicePriceResponse");
		$service = ($data["Services"] && $data["Services"]["Service"]) ? $data["Services"]["Service"] : null;
		return $service ? $this->getSerivce($service, $objs) : null;
	}
	/**
	 * Returns an array 
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getTourFees($params = null, &$objs = null)
	{
		$price = $params["Price"];
		unset($params["Price"]);
		unset($params["CheckIn"]);
		//CircuitFeesRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}
		
		$currencyCode = $params["CurrencyCode"];
		$sellCurrencyCode = $params["SellCurrency"];
		unset($params["SellCurrency"]);
		unset($params["RequestCurrency"]);

		$data = static::GetResponseData($this->doRequest("CircuitFeesRequest", $params), "CircuitFeesResponse");

		$fees = $data["Service"] ? $data["Service"] : null;

		if (!$fees || (count($fees) === 0))
			return [];

		if (!$objs)
			$objs = [];

		if (isset($fees["CurrencyCode"]))
			$fees = array($fees);

		$cf = count($fees);

		$last_fee_hundred = false;
		$all_fees_under_hundred = true;

		$fees_pos = 0;
		foreach ($fees ?: [] as $fee)
		{
			if ($fee["Value"] > 100)
				$all_fees_under_hundred = false;

			$fees_pos++;
			if (($fees_pos === $cf) && ($fee["Value"] == 100))
			{
				$last_fee_hundred = true;
			}
		}

		// force percent if they are all under 100 and 
		$force_percent = (($price > 100) && ($all_fees_under_hundred && $last_fee_hundred));

		$ret = new \QModelArray();
		$pos = 0;
		foreach ($fees as $fee)
		{
			// skip first fee if is free (to be shown as gratuit in interface)
			if (($pos === 0) && ($fee["Amount"] == 0))
			{
				$pos++;
				continue;
			}

			$fee["FromDate"] = $fee["DStart"];
			$fee["ToDate"] = $fee["DStop"];
			$fee["Type"] = "cancellation";
			$fee["Value"] = $fee["Amount"];
			$ret[] = $this->getFee($fee, $price, "tour", $currencyCode, $sellCurrencyCode, $force_percent, $objs);
			$pos++;
		}
		return $ret;
	}

	/**
	 * Returns offer for the tour
	 * 
	 * @param array $variant
	 * @param array $tour
	 * @param array $objs
	 * @return \Omi\Travel\Offer\Tour
	 */
	private function getTourOffer($variant, $tourObj, $tour, &$objs = null, $params = [], $initialParams = [], $departure = null)
	{
		if ($objs === null)
			$objs = [];

		// load cached companies
		$this->loadCachedCompanies($objs);
		$this->loadCachedMerchCategories($objs);
		//$this->loadCachedCurrencies($objs);

		$offerCode = $variant["DepartureCharter"] . "~" . $tour["TourOpCode"] . "~" . $tour["CircuitId"];
		//$offerCode = $variant["DepartureCharter"];
		$offerRooms = ($variant["Rooms"] && $variant["Rooms"]["Room"]) ? $variant["Rooms"]["Room"] : null;

		$roomAttrs = null;
		if ($offerRooms)
		{
			$roomAttrs = $offerRooms["@attributes"];
			$roomData = $offerRooms[0];
			if (is_string($roomData))
				$roomData = [$roomData];

			foreach ($roomData as $offerRoom)
				$offerCode .= "~".trim($offerRoom);
		}

		$charterDepId = ($variant["InfoCharter"] && $variant["InfoCharter"]["DepId"]) ? $variant["InfoCharter"]["DepId"] : null;
		$charterDepDate = ($variant["InfoCharter"] && $variant["InfoCharter"]["DepDate"]) ? $variant["InfoCharter"]["DepDate"] : null;
		$charterDepArrDate = ($variant["InfoCharter"] && $variant["InfoCharter"]["DepArrDate"]) ? $variant["InfoCharter"]["DepArrDate"] : null;
		$charterRetDate = ($variant["InfoCharter"] && $variant["InfoCharter"]["RetDate"]) ? $variant["InfoCharter"]["RetDate"] : null;
		$charterRetArrDate = ($variant["InfoCharter"] && $variant["InfoCharter"]["RetArrDate"]) ? $variant["InfoCharter"]["RetArrDate"] : null;

		$offerCode .= "~".($charterDepId ? $charterDepId : "")."~".($charterDepDate ? $charterDepDate : "")."~".($charterDepArrDate ? $charterDepArrDate : "")
			."~".($charterRetDate ? $charterRetDate : "")."~".($charterRetArrDate ? $charterRetArrDate : "");

		$tourOffer = $objs[$offerCode]["\Omi\Travel\Offer\Tour"] ?: ($objs[$offerCode]["\Omi\Travel\Offer\Tour"] = new \Omi\Travel\Offer\Tour());

		// set currency
		if (!($currency = ($variant['CurrencyCode'] ? $variant['CurrencyCode'] : null)))
		{
			throw new \Exception("Offer currency not provided!");
		}

		// setup 
		if (!($tourOffer->SuppliedCurrency = static::GetCurrencyByCode($currency)))
		{
			throw new \Exception("Undefined currency [{$currency}]!");
		}

		$tourOffer->Code = $offerCode;
		$tourOffer->setSearchId($tour["SearchId"]);
		$tourOffer->setTourOperator($this->TourOperatorRecord);

		$tourOffer->UniqueId = $variant["UniqueId"];
		$tourOffer->DepartureCharter = $variant["DepartureCharter"];

		if ($tour["TourOpCode"])
		{
			$tourOffer->SuppliedBy = $objs[$tour["TourOpCode"]]["\Omi\Company"] ?: ($objs[$tour["TourOpCode"]]["\Omi\Company"] = new \Omi\Company());
			$tourOffer->SuppliedBy->Code = $tour["TourOpCode"];
		}

		if (!$tourOffer->Item)
			$tourOffer->Item = new \Omi\Comm\Offer\OfferItem();
		$tourOffer->Item->Merch = $tourObj;

		$availability = $variant['Availability'] ? $variant['Availability'][0] : null;

		$tourOffer->Item->Availability = 'no';
		$lwAv = $availability ? strtolower($availability) : null;
		if ($lwAv === "immediate")
			$tourOffer->Item->Availability = 'yes';
		else if ($lwAv === "onrequest")
			$tourOffer->Item->Availability = 'ask';

		$offerInitialPrice = $variant["PriceNoRedd"] ?: $variant["Gross"];
		$offerPrice = $variant["Gross"] ?: $variant["PriceNoRedd"];

		$tourOffer->setInitialPrice($offerInitialPrice);
		$tourOffer->setPrice($offerPrice);
		$tourOffer->Comission = floatval($variant["CommissionCed"]);

		// it is the same price as for room
		$tourOffer->Item->UnitPrice = 0;
		$tourOffer->Item->Quantity = 1;

		$services = ($variant["Services"] && $variant["Services"]["Service"]) ? $variant["Services"]["Service"] : null;

		if (!$tourOffer->Items)
			$tourOffer->Items = new \QModelArray();

		$tourOffer->Items[] = $tourOffer->Item;

		if (!$services || (count($services) === 0))
			return $tourOffer;

		if (isset($services["Code"]))
			$services = array($services);

		$departureTransportCode = "other-outbound";
		$returnTransportCode = "other-inbound";

		#qvardump('$departure, $initialParams', $departure, $initialParams);

		$departureCity = $initialParams['DepCity'];
		if (($departureCity === null) && ($departure))
			$departureCity = isset($departure->DepartureGroup->DepartureCity) ? $departure->DepartureGroup->DepartureCity : null;
		
		$destination = $initialParams['Destination_Full'];
		if (($destination === null) && ($pcountry = $initialParams['Country']))
			$destination = $pcountry;

		#qvardump('$departureCity, $destination', $departureCity, $destination);

		$departureTransport = null;
		$arrivalTransport = null;
		foreach ($services as $service)
		{
			$isRoom = ($service["Type"] === "11");
			$isTransport = ($service["Type"] === "7");
			$itmType = $isRoom ? "\Omi\Travel\Merch\Room" : ($isTransport ? "\Omi\Travel\Merch\Transport" : "\Omi\Comm\Merch\Merch");

			$offerItmType = $isRoom ? "\Omi\Travel\Offer\Room" : ($isTransport ? "\Omi\Travel\Offer\Transport" : "\Omi\Comm\Offer\OfferItem");

			//$itmObj = $objs[$service["Code"]][$itmType] ?: ($objs[$service["Code"]][$itmType] = new $itmType());
			$itmObj = new $itmType();
			$itmObj->Code = $service["Code"];
			if ($service["Name"])
				$itmObj->Title = $service["Name"];

			$serviceType = $service['Type'];

			if (!$isTransport)
			{
				$itmObj->Category = $objs[$serviceType]["\Omi\Comm\Merch\MerchCategory"] ?: 
					($objs[$serviceType]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
				$itmObj->Category->Code = $serviceType;
			}

			$offerItm = new $offerItmType();
			$offerItm->Merch = $itmObj;

			$tourOffer->Items[] = $offerItm;

			$offerItm->Availability = 'no';
			$lwAv = $availability ? strtolower($availability) : null;
			if ($lwAv === "immediate")
				$offerItm->Availability = 'yes';
			else if ($lwAv === "onrequest")
				$offerItm->Availability = 'ask';

			$offerItm->UnitPrice = floatval($service["ServicePrice"]);
			$offerItm->Quantity = 1;

			if ($service["Provider"])
			{
				$offerItm->SuppliedBy = $objs[$service["Provider"]]["\Omi\Company"] ?: ($objs[$service["Provider"]]["\Omi\Company"] = new \Omi\Company());
				$offerItm->SuppliedBy->Code = $service["Provider"];
			}

			if ((!($itmObj instanceof \Omi\Travel\Merch\Room)) && (!($itmObj instanceof \Omi\Travel\Merch\Transport)))
				continue;

			$checkIn = ($service["PeriodOfStay"] && $service["PeriodOfStay"]["CheckIn"]) ? $service["PeriodOfStay"]["CheckIn"] : null;
			$checkOut = ($service["PeriodOfStay"] && $service["PeriodOfStay"]["CheckOut"]) ? $service["PeriodOfStay"]["CheckOut"] : null;

			if ((!$checkIn) && (!$checkOut))
				continue;

			if ($isRoom)
			{
				$offerItm->CheckinAfter = $checkIn;
				$offerItm->CheckinBefore = $checkIn;

				if ($roomAttrs && $roomAttrs["Code"])
					$offerItm->setCode($roomAttrs["Code"]);

				$days = null;
				if ($checkIn && $checkOut)
				{
					$interval = date_diff(date_create($checkOut), date_create($checkIn));
					$days = $interval->format("%d");
				}
				$offerItm->MinDays = $days;
				$offerItm->MaxDays = $days;
			}
			else
			{
				$offerItm->setSeats(explode(",", $service["Seats"]));
				$itmObj->Type = strtolower($service["Transport"]);

				if (!$variant["InfoCharter"])
					continue;

				$date = $checkIn ? $checkIn : $checkOut;
				$isDeparture = $charterDepDate ? (date("Y-m-d", strtotime($date)) === date("Y-m-d", strtotime($charterDepDate))) : false;
				$isArrival = $charterRetDate ? (date("Y-m-d", strtotime($date)) === date("Y-m-d", strtotime($charterRetDate))) : false;

				if (!$isDeparture && !$isArrival)
					continue;

				if ($service["Transport"])
					$offerItm->Merch->TransportType = strtolower($service["Transport"]);

				if ($isDeparture)
				{
					$departureTransport = $offerItm;	

					$departureTransport->Merch->Category = $objs[$departureTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
						($objs[$departureTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
					$departureTransport->Merch->Category->Code = $departureTransportCode;

					$departureTransport->Merch->DepartureTime = $offerItm->DepartureDate = $charterDepDate;
					$departureTransport->Merch->ArrivalTime = $offerItm->ArrivalDate = $charterDepArrDate;
					if ($arrivalTransport)
						$offerItm->Return = $arrivalTransport;
					
					if ($departureCity)
					{
						$departureTransport->Merch->From = new \Omi\Address();
						$departureTransport->Merch->From->City = $departureCity;
					}
					
					if ($destination)
					{
						$departureTransport->Merch->To = new \Omi\Address();
						if ($destination instanceof \Omi\City)
							$departureTransport->Merch->To->City = $destination;
						else if ($destination instanceof \Omi\County)
							$departureTransport->Merch->To->County = $destination;
						else if ($destination instanceof \Omi\Country)
							$departureTransport->Merch->To->Country = $destination;
						else if ($destination instanceof \Omi\TF\Destination)
							$departureTransport->Merch->To->Destination = $destination;
					}
				}
				else 
				{
					$arrivalTransport = $offerItm;
					$arrivalTransport->Merch->DepartureTime = $offerItm->DepartureDate = $charterRetDate;
					$arrivalTransport->Merch->ArrivalTime = $offerItm->ArrivalDate = $charterRetArrDate;

					$arrivalTransport->Merch->Category = $objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
						($objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
					$arrivalTransport->Merch->Category->Code = $returnTransportCode;

					if ($departureTransport)
						$departureTransport->Return = $offerItm;
					
					if ($departureCity)
					{
						$arrivalTransport->Merch->To = new \Omi\Address();
						$arrivalTransport->Merch->To->City = $departureCity;
					}
					
					if ($destination)
					{
						$arrivalTransport->Merch->From = new \Omi\Address();
						if ($destination instanceof \Omi\City)
							$arrivalTransport->Merch->From->City = $destination;
						else if ($destination instanceof \Omi\County)
							$arrivalTransport->Merch->From->County = $destination;
						else if ($destination instanceof \Omi\Country)
							$arrivalTransport->Merch->From->Country = $destination;
						else if ($destination instanceof \Omi\TF\Destination)
							$arrivalTransport->Merch->From->Destination = $destination;
					}
				}
			}
		}

		// on eurosite we don't have currency on items - we have it only on offer - so we need to setup the supplied currency on all items
		foreach ($tourOffer->Items ?: [] as $itm)
			$itm->SuppliedCurrency = $tourOffer->SuppliedCurrency;

		// setup offer currency
		$this->setupOfferPriceByCurrencySettings($params, $params["SellCurrency"], $tourOffer, "tour");
		
		$params["Rooms"] = \Omi\Travel\Offer\Stay::SetupRoomsParams($params["Rooms"]);

		$roomItm = $tourOffer->getRoomItem();
		$feesCheckIn = $roomItm ? $roomItm->CheckinBefore : null;
		$feesParams = [
			"__type__" => "tour",
			"__cs__" => $this->TourOperatorRecord->Handle,
			"UniqueId" => $tourOffer->UniqueId,
			"CheckIn" => $feesCheckIn,
			"Price" => $tourOffer->Price,
			"CurrencyCode" => $params["CurrencyCode"],
			"SellCurrency" => $params["SellCurrency"],
			"Rooms" => $params["Rooms"]
		];
		
		// set in fees params
		if ($tourObj && $tourObj->Location && $tourObj->Location->City)
		{
			if ($tourObj->Location->City->Country)
				$feesParams["Country"] = $tourObj->Location->City->Country->getId();
			if ($tourObj->Location->City->County)
				$feesParams["County"] = $tourObj->Location->City->County->getId();
			$feesParams["City"] = $tourObj->Location->City->getId();
		}
		
		$tourOffer->__fees_params = $feesParams;
		
		$iparams_full = static::$RequestOriginalParams;
		$useAsyncFeesFunctionality = ((defined('USE_ASYNC_FEES') && USE_ASYNC_FEES) && (!$iparams_full["__on_setup__"]) 
			&& (!$iparams_full["__on_add_travel_offer__"]) && (!$iparams_full["__send_to_system__"]));
		#$useAsyncFeesFunctionality = false;

		if ((!$useAsyncFeesFunctionality) && (($params["getFeesAndInstallments"] && (!$params["getFeesAndInstallmentsFor"])) || ($params["getFeesAndInstallmentsFor"] && ($params["getFeesAndInstallmentsFor"] == $tourObj->getId()))))
		{
			list($tourOffer->CancelFees, $tourOffer->Installments) = static::ApiQuery($this, "TourFeesAndInstallments", null, null, [$feesParams, $feesParams]);
		}
		return $tourOffer;
	}

	private function getTourInfo_setupStages($tourObj, $stages, $stagesProp, $dumpData = false)
	{
		$changesChanged = false;
		if (!$tourObj->{$stagesProp})
			$tourObj->{$stagesProp} = new \QModelArray();
		$cstages = count($tourObj->{$stagesProp});
		$pos = 0;
		if ($stages && (count($stages) > 0))
		{
			foreach ($stages as $stage)
			{
				if (is_array($stage) && empty($stage))
					$stage = "";
				$stageObj = $tourObj->{$stagesProp}[$pos] ?: ($tourObj->{$stagesProp}[$pos] = new \Omi\Travel\Merch\TourStage());
				if (!$stageObj->getId())
				{
					$changesChanged = true;
					if ($dumpData)
						echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$stagesProp}] new stage</div>";
				}
				if (!$stageObj->Content)
					$stageObj->Content = new \Omi\Cms\Content();
				$shortDescription = trim(preg_replace("/\\r\\n/", "<br/>", htmlspecialchars_decode($stage, ENT_QUOTES)));
				if ($stageObj->Content->ShortDescription != trim($shortDescription))
				{
					$stageObj->Content->ShortDescription = trim($shortDescription);
					$changesChanged = true;
					if ($dumpData)
						echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$stagesProp}] change short description</div>";
				}
				$pos++;
			}
		}
		while($pos < $cstages)
		{
			if ($tourObj->{$stagesProp}[$pos])
			{
				$tourObj->{$stagesProp}[$pos]->setTransformState(\QModel::TransformDelete);
				$changesChanged = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$stagesProp}] delete stage</div>";
			}
			$pos++;
		}
		return $changesChanged;
	}

	private function getTourInfo_setupContent($tourObj, $tour, $contentProp, $dumpData = false)
	{
		// set content
		$tourContent = trim($tour["Description"] ? preg_replace("/\\r\\n/", "<br/>", htmlspecialchars_decode($tour["Description"], ENT_QUOTES)) : "");
		$contentChanged = false;
		if ($tourContent != $tourObj->{$contentProp}->Content)
		{
			$tourObj->{$contentProp}->Content = $tourContent;
			$contentChanged = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$contentProp}] content changed</div>";
		}

		if (!$tourObj->{$contentProp}->ImageGallery)
			$tourObj->{$contentProp}->ImageGallery = new \Omi\Cms\Gallery();

		if (!$tourObj->{$contentProp}->ImageGallery->Items)
			$tourObj->{$contentProp}->ImageGallery->Items = new \QModelArray();

		$existingImages = [];
		foreach ($tourObj->{$contentProp}->ImageGallery->Items as $k => $itm)
			$existingImages[$itm->RemoteUrl] = [$k, $itm];

		$pictures = ($tour["Pictures"] && $tour["Pictures"]["Picture"]) ? $tour["Pictures"]["Picture"] : null;

		$processedImages = [];
		// we need to see here how galleries comes
		if ($pictures && (count($pictures) > 0))
		{
			if (is_string($pictures))
				$pictures = array($pictures);
			foreach ($pictures as $image)
			{
				if (!($image = trim($image)))
					continue;
				$processedImages[$image] = true;
				if (isset($existingImages[$image]))
					continue;
				$img = new \Omi\Cms\GalleryItem();
				$img->setFromTopAddedDate(date("Y-m-d H:i:s"));
				$contentChanged = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$contentProp}] new image</div>";
				// pull tour operator image
				$imagePulled = $img->setupTourOperatorImage($image, $this->TourOperatorRecord, $tourObj, md5($image), IMAGES_URL, IMAGES_REL_PATH);
				if ($imagePulled)
					$tourObj->{$contentProp}->ImageGallery->Items[] = $img;
			}
		}

		foreach ($existingImages as $imUrl => $imData)
		{
			if (isset($processedImages[$imUrl]))
				continue;
			list($imK, $img) = $imData;
			$img->_toRM = true;
			$contentChanged = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$contentProp}] remove image</div>";
			$tourObj->{$contentProp}->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $imK);
		}

		if ($tour["MovieLink"])
		{
			if (!$tourObj->{$contentProp}->VideoGallery)
				$tourObj->{$contentProp}->VideoGallery = new \Omi\Cms\Gallery();

			if (!$tourObj->{$contentProp}->VideoGallery->Items)
				$tourObj->{$contentProp}->VideoGallery->Items = new \QModelArray();

			$itm = reset($tourObj->{$contentProp}->VideoGallery->Items);
			if (!$itm)
			{
				$itm = new \Omi\Cms\GalleryItem();
				$tourObj->{$contentProp}->VideoGallery->Items[] = $itm;
				$contentChanged = true;
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$contentProp}] new video</div>";
			}
			$itm->Type = \Omi\Cms\GalleryItem::TypeVideo;
			if ($itm->RemoteUrl != $tour["MovieLink"])
			{
				$contentChanged = true;
				$itm->RemoteUrl = $tour["MovieLink"];
				if ($dumpData)
					echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$contentProp}] different movie link</div>";
			}
		}
		else if (isset($tourObj->{$contentProp}->VideoGallery->Items))
		{
			foreach ($tourObj->{$contentProp}->VideoGallery->Items ?: [] as $itm)
				$itm->setTransformState(\QIModel::TransformDelete);

			$contentChanged = true;
			if ($dumpData)
				echo "<div style='color: red;'>Tour [{$tourObj->InTourOperatorId}] - data changed - [{$contentProp}] remove movie</div>";
		}

		return $contentChanged;
	}

	/*===================================END TOURS DATA=============================================*/	

	protected function getToursDepartureCities()
	{
		if ($this->_toursDepartCities && $this->_toursDepartCities[$this->TourOperatorRecord->Handle])
			return $this->_toursDepartCities[$this->TourOperatorRecord->Handle];

		$departureGroups = QQuery("DepartureDestinationGroups.{"
				. "DepartureCity.{Name, Alias, InTourOperatorId}, "
				. "TourOperator.Handle, "
				. "TransportType "
			. "WHERE TourOperator.Handle=?}", 
			$this->TourOperatorRecord->Handle)->DepartureDestinationGroups;

		$cities = [];
		foreach ($departureGroups ?: [] as $dpg)
		{
			if (!$dpg->DepartureCity || !$dpg->TransportType)
				continue;

			if (!isset($cities[$dpg->DepartureCity->getId()]))
				$cities[$dpg->DepartureCity->getId()] = ["City" => $dpg->DepartureCity, "TransportTypes" => []];
			$cities[$dpg->DepartureCity->getId()]["TransportTypes"][$dpg->TransportType] = $dpg->TransportType;
		}
		return ($this->_toursDepartCities[$this->TourOperatorRecord->Handle] = $cities);
	}

	public function canSetupDestination($config, $country, $city, $adults = 2, $currency = null)
	{
		$force = $config["force"];
		if ($currency === null)
			$currency = static::$DefaultCurrency;

		$params = [
			"CountryCode" => $country["Code"],
			"CityCode" => $city["Code"],
			"CurrencyCode" => $currency,
			"Year" => date("y"),
			"Month" => "13",
			"Rooms" => [
				[
				"Room" => [
						"Code" => "DB",
						"NoAdults" => $adults
					]
				]
			]
		];

		$useCachedReqs = ($config && $config["useCachedReqs"]);

		$tStartProcess = microtime(true);
		$callMethod = "CircuitSearchRequest";
		$callKeyIdf = md5(json_encode($params));
		$callParams = $params;

		$return = null;
		$alreadyProcessed = false;

		$useLastTracking = false;
		if ($useCachedReqs)
		{
			$lastTrackingResp = \Omi\TF\SyncTopTrack::GetLastTrackingByMethodAndKeyIdf($this->TourOperatorRecord, $callMethod, $callKeyIdf);
			if ($lastTrackingResp)
			{
				$lastTrackingResp = preg_replace('#(<ResponseTime>(.*?)</ResponseTime>)|(<ResponseId>(.*?)</ResponseId>)|(<RequestId>(.*?)</RequestId>)#', '', $lastTrackingResp);
				$return = ["success" => true, "data" => $this->SOAPInstance->decodeResponse($lastTrackingResp, true, true, $callMethod), "rawResp" => $lastTrackingResp];
				$useLastTracking = true;
			}
		}

		if (!$useLastTracking)
		{
			// we need to make sure that we have all data in order (countries and continents)
			$refThis = $this;
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
				$reqEX = null;
				try
				{
					$return = $refThis->doRequest($method, $params, true);
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
			}, $callMethod, $callParams, $callKeyIdf, true);
		}

		/*
		if ((!$return) || ($alreadyProcessed && (!$force)))
		{
			return [null, $alreadyProcessed];
		}
		*/

		$toursData = static::GetResponseData($return, "CircuitSearchResponse");

		if ($_GET['show_requests_dump'] || $_GET['verbose'])
		{
			qvardump("\CircuitSearchResponse", $params, $toursData);
		}

		$circuits = ($toursData && $toursData["Circuit"]) ? $toursData["Circuit"] : null;
		if ($circuits && $circuits["CircuitId"])
			$circuits = [$circuits];
		$hasTours = false;
		$tours = [];
		$transportTypes = [];
		foreach ($circuits ?: [] as $circuit)
		{
			if ((!$circuit["TourOpCode"]) || ($circuit["TourOpCode"] != $this->TourOperatorRecord->ApiContext))
				continue;
			$tourDestinations = ($circuit["Destinations"] && $circuit["Destinations"]["CircuitDestination"]) ? $circuit["Destinations"]["CircuitDestination"] : null;
			if ($tourDestinations && $tourDestinations["CityCode"])
				$tourDestinations = [$tourDestinations];
			$isDestinationTour = false;
			foreach ($tourDestinations ?: [] as $dest)
			{
				if ($dest['CityCode'] == $city['Code'])
				{
					$hasTours = true;
					$isDestinationTour = true;
					$tours[$circuit["CircuitId"]] = $circuit;
				}
			}
			if (!$isDestinationTour)
				continue;

			$variants = ($circuit["Variants"] && $circuit["Variants"]["Variant"]) ? $circuit["Variants"]["Variant"] : null;
			if ($variants && $variants["UniqueId"])
				$variants = [$variants];
			foreach ($variants ?: [] as $variant)
			{
				$variantTopCode = $variant["UniqueId"] ? end(explode("|", $variant["UniqueId"])) : null;
				// filter variant by top code
				if (!$variantTopCode || ($variantTopCode != $this->TourOperatorRecord->ApiContext))
					continue;
				$transportType = null;
				$services = $variant["Services"]["Service"];
				if ($services)
				{
					if ($services["Type"])
						$services = [$services];
					foreach ($services as $serv)
					{
						if ($serv["Type"] == "7")
						{
							$transportType = strtolower($serv["Transport"]);
							break;
						}
					}
				}
				if (!$transportType)
					throw new \Exception("Transport type cannot be determined!");
				$transportTypes[$transportType] = $transportType;
			}
		}

		if (!$useLastTracking)
			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $tStartProcess));

		return [$hasTours, $transportTypes, $tours];
	}

	public function refreshToursDepartures_resyncToursConfig()
	{
		// get existing tours configs
		$toursConfigs = \QQuery("ToursConfig.{"
			. "TransportTypes,"
			. "TourOperator.Handle, "
			. "Tours, "
			. "DestinationCity.{"
				. "InTourOperatorId,"
				. "Name"
			. "}, "
			. "Departures.{"
				. "TransportType, "
				. "DepartureCity.InTourOperatorId"
			. "} "
			. "WHERE "
			. "TourOperator.Handle=?}", $this->TourOperatorRecord->Handle)->ToursConfig;

		$etcfgs = [];
		$allTCFGS = [];
		foreach ($toursConfigs ?: [] as $tc)
		{
			// the tour config it must have destination city
			if (!$tc->DestinationCity || (!$tc->DestinationCity->InTourOperatorId))
			{
				// maybe we should remove the tour config here
				continue;
			}

			// the index is the city tour operator id
			$tcfgindx = $tc->DestinationCity->InTourOperatorId;

			// index transports
			foreach ($tc->TransportTypes ?: [] as $tt)
				$tcfgindx .= "~" . $tt;

			// if we don't have departures check for duplicates
			if (!$tc->Departures || (count($tc->Departures) === 0))
			{
				if (isset($allTCFGS[$tcfgindx]))
				{
					// don't allow removal yet
					throw new \Exception("Duplicate!");
					#$tc->delete("Id");
					#continue;
				}
				$allTCFGS[$tcfgindx] = $tcfgindx;
			}

			// index tours configs
			$etcfgs[$tc->DestinationCity->InTourOperatorId] = [
				"Id" => $tc->getId(), 
				"TourConfig" => $tc, 
				"Departures" => [], 
				"TransportTypes" => []
			];

			$rm = false;
			// check to se if we have transport types duplicated
			foreach ($tc->TransportTypes ?: [] as $k => $tt)
			{
				if (isset($etcfgs[$tc->DestinationCity->InTourOperatorId]["TransportTypes"][$tt]))
				{
					$tc->TransportTypes->setTransformState(\QIModel::TransformDelete, $k);
					$rm = true;
					continue;
				}
				$etcfgs[$tc->DestinationCity->InTourOperatorId]["TransportTypes"][$tt] = $tt;
			}

			// remove duplicates from transport types
			if ($rm)
				$tc->save("TransportTypes");

			// index tours from config
			$cleanupTours = false;
			foreach ($tc->Tours ?: [] as $k => $tour)
			{
				if (isset($etcfgs[$tc->DestinationCity->InTourOperatorId]["Tours"][$tour]))
				{
					$tc->Tours->setTransformState(\QIModel::TransformDelete, $k);
					$cleanupTours = true;
					continue;
				}
				$etcfgs[$tc->DestinationCity->InTourOperatorId]["Tours"][$tour] = $tour;
			}

			if ($cleanupTours)
				$tc->save("Tours");

			// index departures
			foreach ($tc->Departures ?: [] as $departure)
			{
				if (!$departure->TransportType || !$departure->DepartureCity || !$departure->DepartureCity->InTourOperatorId)
					continue;
				$etcfgs[$tc->DestinationCity->InTourOperatorId]["Departures"][$departure->TransportType . "~" . $departure->DepartureCity->InTourOperatorId] = $departure;
			}
		}
		return [$toursConfigs, $etcfgs, $allTCFGS];
	}

	/**
	 * Refresh config
	 * 
	 * @return type
	 * @throws \Exception
	 */
	public function refreshToursDepartures($config = [], $force = false)
	{
		$t1 = microtime(true);

		if (!($this->TourOperatorRecord))
			throw new \Exception("Storage not found!");		

		$useCachedReqs = ($config && $config["useCachedReqs"]);

		// first we must resync countries
		// making sure that we have all countries from eurosite
		$this->resyncCountries(true);

		$callMethod = "CircuitSearchCityRequest";
		$callKeyIdf = null;
		$callParams = [];
		// we need to make sure that we have all data in order (countries and continents)
		$refThis = $this;

		$return = null;
		$alreadyProcessed = false;

		$useLastTracking = false;
		if ($useCachedReqs)
		{
			$lastTrackingResp = \Omi\TF\SyncTopTrack::GetLastTrackingByMethodAndKeyIdf($this->TourOperatorRecord, $callMethod, $callKeyIdf);
			if ($lastTrackingResp)
			{
				$lastTrackingResp = preg_replace('#(<ResponseTime>(.*?)</ResponseTime>)|(<ResponseId>(.*?)</ResponseId>)|(<RequestId>(.*?)</RequestId>)#', '', $lastTrackingResp);
				$return = ["success" => true, "data" => $this->SOAPInstance->decodeResponse($lastTrackingResp, true, true, $callMethod), "rawResp" => $lastTrackingResp];
				$useLastTracking = true;
			}
		}
		
		if (!$useLastTracking)
		{
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
				$reqEX = null;
				try
				{
					$return = $refThis->doRequest($method, $params);
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
		}

		if ((!$return) || ($alreadyProcessed && (!$force)))
			return [null, $alreadyProcessed];

		$tStartProcess = microtime(true);

		$respData = static::GetResponseData($return, "CircuitSearchCityResponse");

		if ($_GET['verbose'])
		{
			qvardump("CircuitSearchCityResponse", "No call params", $respData);
		}

		$countries = ($respData && $respData["Country"]) ? $respData["Country"] : null;

		if ($countries && isset($countries["CountryCode"]))
			$countries = [$countries];

		$countriesCodes = [];
		$countiesByInTopId = [];
		$citiesByInTopId = [];
		
		// go thorugh countries and setup needed data
		foreach ($countries ?: [] as $country)
		{
			if (empty($country["CountryCode"]))
				continue;
			$countriesCodes[$country["CountryCode"]] = $country["CountryCode"];
			$cities = ($country["Cities"] && $country["Cities"]["City"]) ? $country["Cities"]["City"] : null;
			if ($cities && isset($cities["CityCode"]))
				$cities = [$cities];
			foreach ($cities ?: [] as $city)
				$citiesByInTopId[$city["CityCode"]] = $city["CityCode"];
		}
		$dbCountriesByCode = [];
		$dbCountriesRes = count($countriesCodes) ? QQuery("Countries.{*, InTourOperatorsIds.{Identifier, TourOperator.Handle WHERE TourOperator.Handle=?} "
			. " WHERE Code IN (?)}", [$this->TourOperatorRecord->Handle, array_keys($countriesCodes)])->Countries : null;
		foreach ($dbCountriesRes ?: [] as $dbCountry)
			$dbCountriesByCode[$dbCountry->Code] = $dbCountry;
		$dbCountiesByInTopIds = [];
		$dbCountiesRes = count($countiesByInTopId) ? QQuery("Counties.{*, Country.* WHERE TourOperator.Handle=? AND InTourOperatorId IN (?)}", 
			[$this->TourOperatorRecord->Handle, array_keys($countiesByInTopId)])->Counties : null;
		foreach ($dbCountiesRes ?: [] as $dbCounty)
			$dbCountiesByInTopIds[$dbCounty->InTourOperatorId] = $dbCounty;
		$dbCitiesByInTopIds = [];
		$dbCitiesRes = count($citiesByInTopId) ? QQuery("Cities.{*, County.*, Country.* WHERE TourOperator.Handle=? AND InTourOperatorId IN (?)}", 
			[$this->TourOperatorRecord->Handle, array_keys($citiesByInTopId)])->Cities : null;
		foreach ($dbCitiesRes ?: [] as $dbCity)
			$dbCitiesByInTopIds[$dbCity->InTourOperatorId] = $dbCity;

		$useCountries = [];
		// go thorugh countries and setup needed data
		foreach ($countries ?: [] as $country)
		{
			if ($country["CountryCode"])
			{
				if (!isset($useCountries[$country["CountryCode"]]))
				{
					$useCountries[$country["CountryCode"]] = ["Country" => [
						"Code" => $country["CountryCode"],
						"Name" => $country["CountryName"]
					], "Cities" => []];
				}
				$cities = ($country["Cities"] && $country["Cities"]["City"]) ? $country["Cities"]["City"] : null;
				if ($cities && isset($cities["CityCode"]))
					$cities = [$cities];
				foreach ($cities ?: [] as $city)
				{
					if (!isset($useCountries[$country["CountryCode"]]["Cities"][$city["CityCode"]]))
						$useCountries[$country["CountryCode"]]["Cities"][$city["CityCode"]] = ["Code" => $city["CityCode"], "Name" => $city["CityName"]];
				}
			}
		}

		$toSaveCountries = new \QModelArray();
		$toSaveCities = new \QModelArray();

		$countriesByCode = [];
		$citiesByInTopIds = [];
		// go thorugh countries and setup needed data
		foreach ($useCountries ?: [] as $countryData)
		{
			$country = $countryData["Country"];
			try
			{
				$countryObj = $this->saveCharters_setupCountry($country["Code"], $dbCountriesByCode, $toSaveCountries);
			}
			catch (\Exception $ex)
			{
				echo "<div style='color: red;'>{$ex->getMessage()}</div>";
				continue;
			}
			$countriesByCode[$countryObj->Code] = $countryObj;
			//echo "Process country : " . $countryObj->Name . "<br/>";
			if (($cities = ($countryData["Cities"])))
			{
				// go through cities and check data
				foreach ($cities ?: [] as $cityData)
				{
					if (empty($cityData["Code"]) || empty($cityData["Name"]))
					{
						continue;
					}
					$cityData["Code"] = trim($cityData["Code"]);
					try
					{
						$city = $this->saveCharters_setupCity(["CityCode" => $cityData["Code"], "CityName" => $cityData["Name"]], 
							$countryObj, $dbCitiesByInTopIds, $toSaveCities);
					}
					catch (\Exception $ex)
					{
						echo "<div style='color: red;'>{$ex->getMessage()}</div>";
						continue;
					}
					$citiesByInTopIds[$city->InTourOperatorId] = $city;
				}
			}
		}

		if (count($toSaveCountries))
		{
			$newApp = \QApp::NewData();
			$newApp->setCountries(new \QModelArray($toSaveCountries));
			$newApp->save(true);
		}

		if (count($toSaveCities))
		{
			$newApp = \QApp::NewData();
			$newApp->setCities(new \QModelArray($toSaveCities));
			$newApp->save(true);
		}

		// cache existing data
		$this->cacheExistingData("Countries", $countriesByCode);
		$this->cacheExistingData("Cities", $citiesByInTopIds);

		list(, $etcfgs, ) = $this->refreshToursDepartures_resyncToursConfig();

		// save countries/cities/toursconfigs/tours
		$toursConfigs = [];
		$toSaveTours = [];

		$ret = [];
		try
		{
			$_tobjs = [];
			// index all cities
			$allTours = [];
			$processdCities = [];
			$toursDestinationsWithoutTours = [];

			$dbToursByInTopIds = [];
			$toursEntity = $this->getToursEntity();
			$tours = QQuery("Tours.{" . $toursEntity . " WHERE TourOperator.Handle=?}", [$this->TourOperatorRecord->Handle])->Tours;
			foreach ($tours ?: [] as $t)
				$dbToursByInTopIds[$t->InTourOperatorId] = $t;
			$this->cacheExistingData("Tours", $dbToursByInTopIds);

			// go thorugh countries and setup needed data
			foreach ($useCountries ?: [] as $countryData)
			{
				if ((!($country = $countryData["Country"])) || (!($countryObj = $countriesByCode[$country["Code"]])))
				{
					echo "<div style='color: red;'>Country not found [{$country["Code"]}]</div>";
					continue;
				}
				// save data to return
				if (!isset($ret[$country["Code"]]))
					$ret[$country["Code"]] = ["Country" => $countryObj, "Cities" => []];

				//echo "Process country : " . $countryObj->Name . "<br/>";
				if (($cities = ($countryData["Cities"])))
				{
					// go through cities and check data
					foreach ($cities ?: [] as $cityData)
					{
						if (empty($cityData["Code"]) || empty($cityData["Name"]))
							continue;

						$cityData["Code"] = trim($cityData["Code"]);					
						if (!($city = $citiesByInTopIds[$cityData["Code"]]))
						{
							echo "<div style='color: red;'>City not found for code [{$cityData["Code"]}]</div>";
							continue;
						}

						// if we already processed the city - don't process it again
						if (isset($processdCities[$city->InTourOperatorId]))
							continue;

						// if sync only destinations - do query to see if destination has tours
						list($hasTours, $transportTypes, $tours) = $this->canSetupDestination($config, $country, $cityData);

						// get tour city config
						$dcfg = $etcfgs[$city->InTourOperatorId];

						// if we don't have tours mark for clearing the data
						if (!$hasTours)
						{
							if ($dcfg && $dcfg["TourConfig"] && $dcfg["TourConfig"]->DestinationCity)
								$toursDestinationsWithoutTours[$dcfg["TourConfig"]->DestinationCity->InTourOperatorId][$dcfg["Id"]] = $dcfg;
							continue;
						}
						else
						{
							// we have tours
						}

						// save data for later usage
						if (!isset($ret[$country["Code"]]["Cities"][$cityData["Code"]]))
							$ret[$country["Code"]]["Cities"][$cityData["Code"]] = ["City" => $city, "Tours" => []];

						$processdCities[$cityData["Code"]] = $cityData["Code"];

						// get destinations tours
						foreach ($tours ?: [] as $tour)
						{
							$allTours[$tour["CircuitId"]] = $tour["CircuitId"];
							$ret[$country["Code"]]["Cities"][$cityData["Code"]]["Tours"][$tour["CircuitId"]] = $tour["CircuitId"];
							list($fullTour, $alreadyProcessed, $saveTour) = $this->getTour($tour, $dbToursByInTopIds[$tour["CircuitId"]], $_tobjs, $force, $config);
							if ($fullTour && $saveTour)
								$toSaveTours[$fullTour->InTourOperatorId] = $fullTour;
						}

						// if we don't have config - add it
						if (!$dcfg)
						{
							// save it
							$departGrp = new \Omi\TF\DepartureDestinationGroup();
							$departGrp->setFromTopAddedDate(date("Y-m-d H:i:s"));
							$departGrp->setDestinationCity($city);
							$departGrp->setActive(true);
							$departGrp->setTourOperator($this->TourOperatorRecord);
							$departGrp->TransportTypes = new \QModelArray($transportTypes);
							$departGrp->Tours = new \QModelArray();
							foreach ($tours ?: [] as $tour)
								$departGrp->Tours[] = $tour["CircuitId"] . " " . $tour["Name"];
							$toursConfigs[$city->InTourOperatorId] = $departGrp;
						}
						// update the destination config - because it was already added
						else
						{
							$departGrp = new \Omi\TF\DepartureDestinationGroup();
							$departGrp->setActive(true);
							// set existing id
							$departGrp->setId($dcfg["Id"]);
							// index transport types
							$departGrp->TransportTypes = new \QModelArray();
							$ttpos = 0;
							foreach ($transportTypes ?: [] as $tt)
							{
								if (!isset($dcfg["TransportTypes"][$tt]))
									$departGrp->TransportTypes[$tt] = $tt;
								$ttpos++;
							}

							// update tours - remove old tours
							$countTours = count($dcfg["Tours"]);

							if (!$departGrp->Tours)
								$departGrp->Tours = new \QModelArray();

							$tpos = 0;
							foreach ($tours ?: [] as $tour)
							{
								 $departGrp->Tours[] = $tour["CircuitId"] . " " . $tour["Name"];
								 $tpos++;
							}
							for ($i = $tpos; $i < $countTours; $i++)
							{
								$departGrp->Tours[$i] = $dcfg["Tours"][$i];
								$departGrp->Tours->setTransformState(\QModel::TransformDelete, $i);
							}
							$toursConfigs[$city->InTourOperatorId] = $departGrp;
						}
						$ret[$country["Code"]]["Cities"][$cityData["Code"]]["DestinationConfig"] = $departGrp;
						$ret[$country["Code"]]["Cities"][$cityData["Code"]]["ExistingDestinationCfg"] = $dcfg;
					}
				}
			}

			// deactivate destinations that are no longer used
			foreach ($toursDestinationsWithoutTours ?: [] as $cityCode => $topsData)
			{
				if (isset($processdCities[$cityCode]))
					continue;
				foreach ($topsData ?: [] as $td)
				{
					echo "<div style='color: red;'>DEACTIVATE CONFIG FOR {$cityCode}</div>";
					$td["TourConfig"]->setActive(false);
					$td["TourConfig"]->update("UPDATE Active=?", false);
				}
			}

			$notProcessedToursBinds = ["TourOperatorHandle" => $this->TourOperatorRecord->Handle, "HasActiveOffers" => true];
			if (count($allTours))
				$notProcessedToursBinds["InTourOperatorIdNOTIN"] = array_keys($allTours);

			// deactivate tours that are not longer in use - maybe we should remove them
			$appTours = \QQuery("Tours.{Title, InTourOperatorId, TourOperator.Handle WHERE 1 "
				. " ??TourOperatorHandle?<AND[TourOperator.Handle=?]"
				. " ??HasActiveOffers?<AND[HasActiveOffers=?]"
				. " ??InTourOperatorIdNOTIN?<AND[InTourOperatorId NOT IN (?)]"
			. "}", $notProcessedToursBinds)->Tours;

			// reset active offers flag
			foreach ($appTours ?: [] as $tour)
			{
				if (!isset($allTours[$tour->InTourOperatorId]))
				{
					if (isset($toSaveTours[$tour->InTourOperatorId]))
						throw new \Exception("Tour already processed but not marked?");
					$tour->setFromTopRemoved(true);
					$tour->setFromTopRemovedAt(date("Y-m-d H:i:s"));
					$tour->setHasActiveOffers(false);
					$tour->setHasPlaneActiveOffers(false);
					$tour->setHasBusActiveOffers(false);
					echo "<div style='color: red;'>DEACTIVATE TOUR {$tour->Title} [{$tour->InTourOperatorId}]</div>";
					$toSaveTours[$tour->InTourOperatorId] = $tour;
				}
			}

			if (count($toursConfigs) > 0)
			{
				$newApp = \QApp::NewData();
				$newApp->setToursConfig(new \QModelArray($toursConfigs));
				$newApp->save(true);
			}

			// save tours
			if (count($toSaveTours) > 0)
			{				
				$newApp = \QApp::NewData();
				$newApp->setTours($toSaveTours);
				$newApp->save(true);
			}

			if (!$useLastTracking)
				$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $tStartProcess));
		}
		catch(\Exception $ex)
		{
			throw $ex;
		}

		static::$_CacheData = [];
		static::$_LoadedCacheData = [];
		
		return [$ret, $alreadyProcessed];
	}

	/**
	 * Save tours transports
	 * Cache details for tours
	 * @param array $config
	 * 
	 * @return null
	 */
	public function saveTours($config = [], $force = false)
	{
		if (!$this->TourOperatorRecord)
			throw new \Exception("Storage not found!");

		$reportsFile = \Omi\App::GetLogsDir("reports") . "tours_resync.html";
		if (file_exists($reportsFile))
			@unlink($reportsFile);

		if (!is_dir("reports"))
			qmkdir("reports");

		ob_start();

		// refresh tours departures
		list($processedData, $alreadyProcessed) = $this->refreshToursDepartures($config, $force);
		if ($alreadyProcessed && (!$force))
		{
			$str = ob_get_clean();
			return;
		}

		$filteredProceedData = [];
		foreach ($processedData ?: [] as $countryCode => $countryData)
		{
			if (!$countryData["Cities"] || (count($countryData["Cities"]) == 0))
				continue;
			$filteredProceedData[$countryCode] = $countryData;
		}

		if (empty($processedData))
		{
			echo "<div style='color: red;'>Nu sunt circuite de importat - Va rugam verificati b2b-ul!</div>";
			return;
			//throw new \Exception("Nu sunt circuite de importat - Va rugam verificati b2b-ul!");
		}

		$SKIP_TO_PROCESS = (!$force) ? $this->getEmptyRequests(["Type" => "tours"]) : [];

		$toursTransports = \QApi::Call("\Omi\TF\TourTransport::GetTransports", [
			"Content" => true,
			"To" => true,
			"From" => true,
			"TourOperator" => $this->TourOperatorRecord->getId()
		]);

		// get the existing transports
		$existingTransports = $this->setupExistingTransports($toursTransports);
		
		$toSaveTransports = [];
		$processedTransports = [];
		$requests = [];
		$toExecRequestsParams = [];
		$cachedTransports = [];
		$reqsOut = "";
		
		$tours_departures_shown = [];
		$transports_departures_shown = [];

		$transportsIds = [];

		try
		{
			foreach ($filteredProceedData ?: [] as $countryCode => $countryData)
			{
				$countryObj = $countryData["Country"];
				$cities = $countryData["Cities"];

				// here we have only cities that have tours
				foreach ($cities ?: [] as $cityCode => $cityData)
				{
					$city = $cityData["City"];
					if (!($eCityCfg = $cityData["ExistingDestinationCfg"]))
					{
						echo "<div style='color: red;'>"
							. "New tour destination was added on tour operator [{$this->TourOperatorRecord->Handle}].<br/>"
							. "The destination is: {$countryObj->Name} -> {$city->Name}.<br/>"
							. "Departures needs to be setup on the destination in order to be added to the system!"
						. "</div>";
						// we don't have the config - this means that we have a new config
						continue;
					}
					else if (count($eCityCfg["Departures"]) === 0)
					{
						echo "<div style='color: red;'>"
							. "Missing departures on tour destination : {$countryObj->Name} -> {$city->Name} from [{$this->TourOperatorRecord->Handle}]"
						. "</div>";
					}
					else
					{
						$departuresSetTransports = [];
						$departuresByID = [];
						foreach ($eCityCfg["Departures"] as $departure)
						{
							$departuresSetTransports[$departure->TransportType] = $departure->TransportType;
							$departuresByID[$departure->getId()] = $departure->getId();
						}
	
						foreach ($cityData["Tours"] ?: [] as $cd_tour)
						{
							foreach ($cd_tour->TransportTypes ?: [] as $cd_tt)
							{
								if ((!isset($departuresSetTransports[$cd_tt])) && (!isset($transports_departures_shown[$cd_tt . "|" . $city->getId()])))
								{
									echo "<div style='color: red;'>"
										. "Missing departures on tour destination for transport [{$cd_tt}] : {$countryObj->Name} -> {$city->Name} from [{$this->TourOperatorRecord->Handle}]"
									. "</div>";
									$transports_departures_shown[$cd_tt . "|" . $city->getId()] = $cd_tt . "|" . $city->getId();
								}	
							}
							foreach ($cd_tour->Departures ?: [] as $cd_dd)
							{
								if ($cd_dd->DepartureGroup && (!isset($departuresByID[$cd_dd->DepartureGroup->getId()])) && 
									(!isset($tours_departures_shown[$cd_dd->DepartureGroup->getId() . "-" . $cd_tour->getId()])))
								{
									$departureObj = ($cd_dd->DepartureGroup->DepartureCity ?: ($cd_dd->DepartureGroup->DepartureCounty ?: 
										($cd_dd->DepartureGroup->DepartureCountry ?: $cd_dd->DepartureGroup->DepartureDestination)));
									echo "<div style='color: red;'>"
										. "Departure [" . $cd_dd->DepartureGroup->getId() . "|" . ($departureObj ? $departureObj->getType() . "|" . $departureObj->getModelCaption() : "") . "] " 
											. "set on tour [{$cd_tour->InTourOperatorId}|{$cd_tour->Title}] but not on destination : " 
											. "{$countryObj->Name} -> {$city->Name} from [{$this->TourOperatorRecord->Handle}]"
									. "</div>";
									$tours_departures_shown[$cd_dd->DepartureGroup->getId() . "-" . $cd_tour->getId()] = $cd_dd->DepartureGroup->getId() . "-" . $cd_tour->getId();
								}
							}
						}

						foreach ($eCityCfg["Departures"] as $departure)
						{
							$transportType = $departure->TransportType;
							$departCity = $departure->DepartureCity;

							$ct_indx = $transportType . "~" . get_class($departCity) . "|" . 
								$departCity->InTourOperatorId . "~" . get_class($city) . "|" . $city->InTourOperatorId;

							// get the transport from cache if any, from existing or new
							$transport = $cachedTransports[$ct_indx] ?: ($cachedTransports[$ct_indx] = 
								($existingTransports[$ct_indx] ?: new \Omi\TF\TourTransport()));

							// setup transport
							$this->setupTransport($transport, $transportType, $departCity, $city);

							$transport_dmp_data = $this->getTransportDumpData($transport);

							$processedTransports[$ct_indx] = $cityData;
							$toSaveTransports[$ct_indx] = $transport;

							// if the transport with leaving details was flagged with no results then skip it permanently - use force flag to process anyway
							if ($SKIP_TO_PROCESS[$city->InTourOperatorId])
							{
								$reqsOut .= "<div style='color: darkgray;'>"
									. "REQUEST SKIPPED :: " . $transport_dmp_data
								. "</div>";
								continue;
							}

							$params = [
								"CountryCode" => $countryCode,
								"CityCode" => $cityCode,
								"CurrencyCode" => static::$DefaultCurrency,
								"Year" => date("Y"),
								"Month" => "13",
								"Rooms" => [
									[
									"Room" => [
											"Code" => "DB",
											"NoAdults" => 2,
											"NoChildren" => 0
										]
									]
								]
							];

							if ($this->TourOperatorRecord->ApiContext)
								$params["TourOpCode"] = $this->TourOperatorRecord->ApiContext;

							$params_indx = md5(json_encode($params));
							$toExecRequestsParams[$params_indx] = $params;
							$reqsOut .= "<div style='color: darkorange;'>"
								. "REQUEST SAVED :: " . $transport_dmp_data
							. "</div>";

							$request = new \Omi\TF\Request();
							$request->setAddedDate(date("Y-m-d H:i:s"));
							$request->setClass(get_called_class());
							$request->setMethod("PullTours");
							$request->setTourOperator($this->TourOperatorRecord);
							$request->setParams(json_encode([$this->TourOperatorRecord->Handle, $params]));
							$request->setType(\Omi\TF\Request::Tour);
							$request->setDestinationIndex($city->InTourOperatorId);
							$request->setupUniqid();
							$requests[$request->UniqId] = $request;

							/*
							$reqUUID = $request->UniqId;
							if (!($useReq = $requests[$reqUUID]))
								$requests[$reqUUID] = $useReq = $request;
							if (!$useReq->TransportsNights_Raw)
								$useReq->TransportsNights_Raw = new \QModelArray();
							$useReq->TransportsNights_Raw[] = $nightsObj;
							*/
						}
					}
				}
			}

			$toRmTransportsIds = [];
			foreach ($existingTransports ?: [] as $tindx => $transport)
			{
				// if it does not come from tour operator - remove it
				if (!isset($processedTransports[$tindx]))
				{
					$toRmTransportsIds[$transport->getId()] = $tindx;
					//$transport->markForCleanup(true);
				}
				//$toSaveTransports[$tindx] = $transport;
			}
			
			if (count($toRmTransportsIds))
			{
				$toRmTransports = QQuery("ToursTransports.{*, Dates.{*, Nights.*} WHERE Id IN (?)}", 
					[array_keys($toRmTransportsIds)])->ToursTransports;
				foreach ($toRmTransports ?: [] as $toRmTransport)
				{
					$tindx = $toRmTransportsIds[$toRmTransport->getId()];
					$toRmTransport->markForCleanup(true);
					$toSaveTransports[$tindx] = $toRmTransport;
				}
			}

			// filter transports
			#$toSaveTransports = $this->saveCachedData_filterTransports($toSaveTransports);
			if (count($toSaveTransports))
			{
				$transportsAppData = \QApp::NewData();
				$transportsAppData->ToursTransports = new \QModelArray($toSaveTransports);
				// save transports
				$transportsAppData->save(true);
				
				foreach ($toSaveTransports ?: [] as $transport)
					$transportsIds[$transport->getId()] = $transport->getId();
			}

			foreach ($existingTransports ?: [] as $et)
				$transportsIds[$et->getId()] = $et->getId();

			// save requests after transports are saved
			$this->saveCachedData_saveRequests($requests, true, ["VacationType" => "tour"]);
			
		}
		catch(\Exception $ex)
		{
			throw $ex;
		}
		
		if (count($transportsIds))
			\Omi\TF\TransportDateNights::QSyncForTours(["IdIN" => [$transportsIds]]);

		static::$_CacheData = [];
		static::$_LoadedCacheData = [];

		foreach ($toExecRequestsParams ?: [] as $params)
			static::PullTours(null, $this->TourOperatorRecord->Handle, $params, null, true, true);

		\Omi\TF\TransportDateNights::LinkRequests_ForTours($this->TourOperatorRecord->Handle);

		$reportData = ob_get_clean();
		echo $reportData;
		file_put_contents($reportsFile, $reportData);
	}

	public function setupTransportsCacheData_Tours_topCust(array &$toCacheData, \Omi\TF\TransportDateNights $retNightsObj, array $tourData = [], 
		\Omi\Travel\Merch\Tour $tour = null, array $params = null, \Omi\TF\Request $request = null, bool $showDiagnosis = false)
	{
		$hasAirportTaxesIncluded = false;
		$hasTransferIncluded = false;
		$hasMedicalInsurenceIncluded = false;

		$room = null;
		$meal = null;
		#$transportType = null;

		$services = $tourData["Services"]["Service"];
		if ($services && $services["Type"])
			$services = [$services];

		foreach ($services ?: [] as $service)
		{
			if ($service["Type"] == "11")
			{
				$room = $service["Name"];
				break;
			}

			/*
			if ($service["Type"] == "7")
				$transportType = strtolower($service["Transport"]);
			*/

		}

		if (!($currencyCode = $tourData["CurrencyCode"]))
			throw new \Exception("Offer currency not provided!");

		// setup
		if (!($currency = static::GetCurrencyByCode($currencyCode)))
		{
			throw new \Exception("Undefined currency [{$currencyCode}]!");
		}

		$availability = "no";
		$lwAv = strtolower(($tourData['Availability'] && is_array($tourData['Availability'])) ? $tourData['Availability'][0] : "no");
		if ($lwAv === "immediate")
			$availability = 'yes';
		else if ($lwAv === "onrequest")
			$availability = 'ask';

		$status = $availability;
		$initialPrice = $tourData["PriceNoRedd"] ?: $tourData["Gross"];
		$price = $tourData["Gross"] ?: $tourData["PriceNoRedd"];

		$isEarlyBooking = false;
		$isSpecialOffer = false;
		$discount = null;

		$this->setupTransportsCacheData($toCacheData, $retNightsObj, null, $tour, $params, $price, $initialPrice, $discount, $currency, $status, 
			$room, $meal, $hasAirportTaxesIncluded, $hasTransferIncluded, $hasMedicalInsurenceIncluded, $isEarlyBooking, $isSpecialOffer, $request, $showDiagnosis);
	}

	/**
	 * @api.enable
	 * 
	 * @param type $params
	 */
	public static function PullTours($request, $handle, $params, $_nights = null, $do_cleanup = true, $skip_cache = false)
	{
		try
		{
			$storage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
			if (!$storage)
				throw new \Exception("Storage [{$handle}] cannot be identified!");

			$country = $storage->FindExistingItem("Countries", $params["CountryCode"], "Code, Name");
			if (!$country)
				throw new \Exception("Country not found for '{$params["CountryCode"]}'");

			$destinationCity = $storage->getCacheCity($params["CityCode"]);
			if (!$destinationCity)
				throw new \Exception("destination city not for code {$params["CityCode"]} found!");

			$_binds = [
				"TourOperator" => $storage->TourOperatorRecord->getId(),
				"ToCity" => $destinationCity->getId()
			];

			if ($request && $request->__nightsObj)
			{
				$nightsObjDateTime = strtotime($request->__nightsObj->Date);
				$params["CheckIn"] = date("Y-m-d", $nightsObjDateTime);
				$_nights = $request->__nightsObj->Nights;
				if ($request->__nightsObj->FromCity)
					$params["DepCityCode"] = $request->__nightsObj->FromCity->InTourOperatorId;
				$params["Year"] = date("Y", $nightsObjDateTime);
				$params["Month"] = date("m", $nightsObjDateTime);
			}

			if ($_nights)
				$_binds["Nights"] = $_nights;

			if (($_checkIn = $params["CheckIn"]))
			{
				$_binds["Date"] = $params["CheckIn"];
				unset($params["CheckIn"]);
			}

			if ($params["DepCityCode"])
			{
				$departureCity = $storage->getCacheCity($params["DepCityCode"]);
				if (!$departureCity)
					throw new \Exception("destination city not for code {$params["DepCityCode"]} found!");
				$_binds["FromCity"] = $departureCity->getId();
				unset($params["DepCityCode"]);
			}

			$onSave = false;
			if ($params["OnSave"])
			{
				$onSave = true;
				unset($params["OnSave"]);
			}

			$upOnlyNights = false;
			if (isset($params['UP_ONLY_NIGHTS']))
			{
				$upOnlyNights = true;
				unset($params['UP_ONLY_NIGHTS']);
			}

			// we can only have a max of 2 cached transports for city - one with plane and other with bus
			$existingTransports = \QQuery("ToursTransports.{*, "
				. "Content.{Active}, "
				. "TransportType, "
				. "Edited,"
				. "TransportType, "
				. "From.City.{"
					. "Name, "
					. "InTourOperatorId"
				. "}, "
				. "To.City.{"
					. "Name, "
					. "InTourOperatorId"
				. "}, "
				. "Tours.{"
					. "Title, "
					. "InTourOperatorId"
				. "}, "
				. "Dates.{"
					. "FromTopRemoved, "
					. "Active,"
					. "Edited,"
					. "Date, "
					. "TransportType, "
					. "Tours.{Title, InTourOperatorId}, "
					. "Nights.{"
						. "FromTopRemoved, "
						. "Active,"
						. "Edited,"
						. "ReqExecLastDate, "
						. "Nights,"
						. "Tours.{Title, InTourOperatorId} "
						. "WHERE 1 "
							. "??Nights?<AND[Nights=?]"
					. "} "
					. "WHERE 1 "
						. " ??Date?<AND[Date=?]"
				. "} "
				. "WHERE 1 "

					. "??TourOperator?<AND[TourOperator.Id=?] "
					. "??FromCity?<AND[From.City.Id=?] "
					. "??ToCity?<AND[To.City.Id=?] "

				. " GROUP BY Id}", $_binds)->ToursTransports;

			if (!$existingTransports)
			{
				echo "<div style='color: red;'>Missing cached transports for {$destinationCity->Name}, {$country->Name}</div>";
				return $request;
			}

			$showDiagnosis = true;
			$todayTime = strtotime(date("Y-m-d"));

			$departures = [];
			foreach ($existingTransports as $cachedTransport)
			{
				$from = $cachedTransport->getTransportFrom();
				//$to = $cachedTransport->getTransportDestination();

				if (!$departures[$from->getId()])
					$departures[$from->getId()] = ["From" => $from, "TransportTypes" => []];
				$departures[$from->getId()]["TransportTypes"][$cachedTransport->TransportType] = $cachedTransport->TransportType;

				if (!$cachedTransport->Tours)
					$cachedTransport->Tours = new \QModelArray();

				//$ct_indx = $cachedTransport->TransportType."~".get_class($from)."|".$from->InTourOperatorId."~".get_class($to)."|".$to->InTourOperatorId;
				$ct_indx = $cachedTransport->TransportType."~".get_class($from)."|".$from->InTourOperatorId;
				$cachedTransports[$ct_indx] = $cachedTransport;

				$cachedTransport->_tours = [];
				foreach ($cachedTransport->Tours ?: [] as $k => $tour)
					$cachedTransport->_tours[$tour->InTourOperatorId] = [$k, $tour];

				if (!$cachedTransport->Dates)
					$cachedTransport->Dates = new \QModelArray();

				$cachedTransport->_dates = [];
				foreach ($cachedTransport->Dates as $k => $dateObj)
				{
					$dateObj->Date = date("Y-m-d", strtotime($dateObj->Date));
					$cachedTransport->_dates[$dateObj->Date] = $dateObj;

					$dateObj->_tours = new \QModelArray();
					foreach ($dateObj->Tours ?: [] as $k => $v)
						$dateObj->_tours[$v->InTourOperatorId] = [$k, $v];

					if (!$dateObj->Nights)
						$dateObj->Nights = new \QModelArray();

					$dateObj->_nights = [];
					foreach ($dateObj->Nights as $nightsObj)
					{
						$dateObj->_nights[$nightsObj->Nights] = $nightsObj;

						$nightsObj->_tours = [];
						foreach ($nightsObj->Tours ?: [] as $k => $v)
							$nightsObj->_tours[$v->InTourOperatorId] = [$k, $v];
					}
				}
			}

			$indx_params = $params;
			if ($departureCity && $departureCity->InTourOperatorId)
				$indx_params["DepCityCode"] = $departureCity->InTourOperatorId;

			$indx_params["VacationType"] = "tour";
			if ($request && $request->__nightsObj)
				$indx_params["Transport"] = $request->__nightsObj->TransportType;

			if ($indx_params["Rooms"][0]["Room"]["NoAdults"])
				$indx_params["Rooms"][0]["Room"]["NoAdults"] = (int)$indx_params["Rooms"][0]["Room"]["NoAdults"];

			if (!isset($indx_params["Rooms"][0]["Room"]["NoChildren"]))
				$indx_params["Rooms"][0]["Room"]["NoChildren"] = 0;

			if ($indx_params["Rooms"][0]["Room"]["NoChildren"])
				$indx_params["Rooms"][0]["Room"]["NoChildren"] = (int)$indx_params["Rooms"][0]["Room"]["NoChildren"];

			unset($indx_params["CurrencyCode"]);
			ksort($indx_params);

			$tf_req_id = md5($storage->TourOperatorRecord->Handle . "|" . json_encode($indx_params));

			#$indx_params["CurrencyCode"] = $params["Currency"];
			$params["__request_data__"]["ID"] = $tf_req_id;
			\Omi\TFuse\Api\TravelFuse::$_CacheData["AllTopsReqsById"][$tf_req_id] = $indx_params;
			\Omi\TFuse\Api\TravelFuse::$_CacheData["AllTopsReqsById_Top"][$tf_req_id] = $storage->TourOperatorRecord->Handle;

			if (!$skip_cache)
			{
				$toursData = static::ExecCacheable(["CircuitSearchRequest" => "CircuitSearchRequest"], function ($params) use ($storage) {
					return static::GetResponseData($storage->doRequest("CircuitSearchRequest", $params, true), "CircuitSearchResponse");
				}, [$params]);
			}
			else
			{
				$toursData = static::GetResponseData($storage->doRequest("CircuitSearchRequest", $params, true), "CircuitSearchResponse");
			}

			$circuits = ($toursData && $toursData["Circuit"]) ? $toursData["Circuit"] : null;

			if ($circuits && $circuits["CircuitId"])
				$circuits = [$circuits];

			$price = 0;
			$processedTransports = [];
			$processedTransportsTours = [];
			$processedTransportsDates = [];
			$processedTransportsDatesTours = [];
			$processedTransportsDatesNights = [];
			$processedTransportsDatesNightsTours = [];

			$appData = \QApp::NewData();
			$appData->Tours = new \QModelArray();
			$appData->ToursTransports = new \QModelArray();
			$appData->NoResultsRequests = new \QModelArray();

			$hasTours = false;

			if (file_exists("eurosite_active_tours.php"))
				require_once("eurosite_active_tours.php");

			if (!($EU_ACTIVE_TOURS))
				$EU_ACTIVE_TOURS = [];

			if (!static::$_CacheData["EU_ACTIVE_TOURS"])
				static::$_CacheData["EU_ACTIVE_TOURS"] = [];

			foreach ($EU_ACTIVE_TOURS AS $k => $v)
			{
				if (!isset(static::$_CacheData["EU_ACTIVE_TOURS"][$k]))
					static::$_CacheData["EU_ACTIVE_TOURS"][$k] = $v;
			}

			$toursInTopsIds = [];
			foreach ($circuits ?: [] as $circuit)
			{
				if (!$circuit["TourOpCode"] || ($circuit["TourOpCode"] != $storage->TourOperatorRecord->ApiContext))
					continue;
				$toursInTopsIds[$circuit["CircuitId"]] = $circuit["CircuitId"];
			}

			$dbToursByInTopIds = [];
			$toursEntity = $storage->getToursEntity();
			$tours = count($toursInTopsIds) ? QQuery("Tours.{" . $toursEntity 
				. " WHERE TourOperator.Handle=? AND InTourOperatorId IN (?)}", [$storage->TourOperatorRecord->Handle, array_keys($toursInTopsIds)])->Tours : [];
			foreach ($tours ?: [] as $t)
				$dbToursByInTopIds[$t->InTourOperatorId] = $t;
			$storage->cacheExistingData("Tours", $dbToursByInTopIds);

			$_tobjs = [];
			$errShownForTours = [];
			$saveToursHasTravelItmFlags = [];
			$toCacheData = [];
			
			$reqResults = [];
			$reqAllResults = [];
			foreach ($circuits ?: [] as $circuit)
			{
				if (!$circuit["TourOpCode"] || $circuit["TourOpCode"] != $storage->TourOperatorRecord->ApiContext)
					continue;

				if (!isset(static::$_CacheData["EU_ACTIVE_TOURS"][$circuit["CircuitId"] . "~" . $storage->TourOperatorRecord->Handle]))
				{
					static::$_CacheData["EU_ACTIVE_TOURS"][$circuit["CircuitId"] . "~" . $storage->TourOperatorRecord->Handle] = 
						$circuit["CircuitId"] . "~" . $storage->TourOperatorRecord->Handle;
				}

				list($tour) = $storage->getTour($circuit, $dbToursByInTopIds[$circuit["CircuitId"]], $_tobjs, true);
				$saveToursHasTravelItmFlags[] = $tour;
				$tourDestinations = ($circuit["Destinations"] && $circuit["Destinations"]["CircuitDestination"]) ? $circuit["Destinations"]["CircuitDestination"] : null;
				if ($tourDestinations && $tourDestinations["CityCode"])
					$tourDestinations = [$tourDestinations];

				$variants = ($circuit["Variants"] && $circuit["Variants"]["Variant"]) ? $circuit["Variants"]["Variant"] : null;
				if ($variants && $variants["UniqueId"])
					$variants = [$variants];

				// check if we accept the destination - 
				// this is due to the bug that eurosite can send all circuits on some destinations that does not have circuits
				$destinationAccepted = false;
				foreach ($tourDestinations ?: [] as $td)
				{
					if (empty($td["CityCode"]) || ($td["CityCode"] != $destinationCity->InTourOperatorId))
						continue;
					$destinationAccepted = true;
					break;
				}

				if (!$destinationAccepted)
					continue;

				// check nights too
				$nights = (int)$circuit["Period"] ? ((int)$circuit["Period"] - 1) : 1;

				// we need to have periods also to setup them on the cached transports
				if (!$nights)
					continue;

				// has tours
				$hasTours = true;
				
				$reqAllResults[$circuit["CircuitId"]] = $circuit["CircuitId"];

				// go through offers
				foreach ($variants ?: [] as $variant)
				{
					$variantTopCode = $variant["UniqueId"] ? end(explode("|", $variant["UniqueId"])) : null;

					// filter variant by top code
					if (!$variantTopCode || ($variantTopCode != $storage->TourOperatorRecord->ApiContext))
						continue;

					$variantPrice = floatval($variant["Gross"]);
					if (($price === 0) || ($price > $variantPrice))
						$price = $variantPrice;

					$date = ($variant && $variant["InfoCharter"] && $variant["InfoCharter"]["DepDate"]) ? 
						date("Y-m-d", strtotime($variant["InfoCharter"]["DepDate"])) : null;

					if ((!$date || (strtotime($date) < $todayTime)) || ($_checkIn && ($_checkIn != $date)))
						continue;
					
					$availability = $variant['Availability'] ? $variant['Availability'][0] : null;
					$lwAv = $availability ? strtolower($availability) : null;
					
					if (($lwAv === "immediate") || ($lwAv === "onrequest"))
					{
						$reqResults[$circuit["CircuitId"]] = $circuit["CircuitId"];
					}

					$transportType = null;
					$services = $variant["Services"]["Service"];

					if ($services)
					{
						if ($services["Type"])
							$services = [$services];

						foreach ($services as $serv)
						{
							if ($serv["Type"] == "7")
							{
								$transportType = strtolower($serv["Transport"]);
								break;
							}
						}
					}

					if (!$transportType)
						throw new \Exception("Transport type cannot be determined!");

					$tour->setHasActiveOffers(true);
					if ($transportType === "plane")
						$tour->setHasPlaneActiveOffers(true);
					else if ($transportType === "bus")
						$tour->setHasBusActiveOffers(true);

					$tourDepartures = [];
					foreach ($tour->Departures ?: [] as $tourDeparture)
					{
						if ((!$tourDeparture->DepartureGroup || (!($depCity = $tourDeparture->DepartureGroup->DepartureCity))) || 
							!$tourDeparture->DepartureGroup->TransportType)
							continue;

						if (!$tourDepartures[$depCity->getId()])
							$tourDepartures[$depCity->getId()] = ["From" => $depCity, "TransportTypes" => []];
						$tourDepartures[$depCity->getId()]["TransportTypes"][$tourDeparture->DepartureGroup->TransportType] = $tourDeparture->DepartureGroup->TransportType;
					}

					// we will use only tour departures
					$useDepartures = $tourDepartures;

					if (count($useDepartures) > 0)
					{
						foreach ($useDepartures ?: [] as $tourDepartureData)
						{
							$depCity = $tourDepartureData["From"];

							foreach ($tourDepartureData["TransportTypes"] ?: [] as $transportType)
							{
								$indx = $transportType . "~" . get_class($depCity) . "|" . $depCity->InTourOperatorId;

								// cached transport
								$cachedTransport = $cachedTransports[$indx];

								if (!$cachedTransport)
								{
									continue;

									//qvardump("Cached Transport cannot be identified!", $indx, $tour, $cachedTransports, $useDepartures, $tourDepartures, $departures);
									//throw new \Exception("Cached Transport cannot be identified!");
								}

								// save it to app
								$appData->ToursTransports[$cachedTransport->getId()] = $cachedTransport;

								// save processed tours
								$processedTransports[$indx] = $cachedTransport;
								$processedTransportsTours[$indx . "~" . $tour->InTourOperatorId] = $tour;
								$processedTransportsDates[$indx . "~" . $date] = $date;
								$processedTransportsDatesTours[$indx . "~" . $date . "~" . $tour->InTourOperatorId] = $tour;
								$processedTransportsDatesNights[$indx . "~" . $date . "~" . $nights] = $nights;
								$processedTransportsDatesNightsTours[$indx . "~" . $date . "~" . $nights . "~" . $tour->InTourOperatorId] = $tour;

								if (!$cachedTransport->_added_tours)
									$cachedTransport->_added_tours = [];

								// if tour not set on cached transport - save it
								if (!$cachedTransport->_tours[$tour->InTourOperatorId] && !$cachedTransport->_added_tours[$tour->InTourOperatorId])
								{
									$cachedTransport->Tours[$tour->InTourOperatorId] = $tour;
									$cachedTransport->_added_tours[$tour->InTourOperatorId] = $tour;
								}

								// setup transport date and add it to transport
								list($dateObj) = $storage->setupTransportDate($cachedTransport, $date, $transportType, true);

								if (!$dateObj->Tours)
									$dateObj->Tours = new \QModelArray();

								if (!$dateObj->_added_tours)
									$dateObj->_added_tours = [];

								// if tour not set on cached transport - save it
								if (!$dateObj->_tours[$tour->InTourOperatorId] && (!$dateObj->_added_tours[$tour->InTourOperatorId]))
								{
									$dateObj->Tours[$tour->InTourOperatorId] = $tour;
									$dateObj->_added_tours[$tour->InTourOperatorId] = $tour;
								}

								list($nightsObj) = $storage->setupTransportDateDuration($cachedTransport, $dateObj, $nights);

								if (!$nightsObj->Tours)
									$nightsObj->Tours = new \QModelArray();

								if (!$nightsObj->_added_tours)
									$nightsObj->_added_tours = [];

								// if tour not set on cached transport - save it
								if (!$nightsObj->_tours[$tour->InTourOperatorId] && (!$nightsObj->_added_tours[$tour->InTourOperatorId]))
								{
									$nightsObj->Tours[$tour->InTourOperatorId] = $tour;
									$nightsObj->_added_tours[$tour->InTourOperatorId] = $tour;
								}

								$nightsObjActive = $nightsObj->Active;

								// if we get here and we have tour
								if (!$nightsObj->Edited)
									$nightsObj->setActive(true);

								if ($nightsObjActive !== $nightsObj->Active)
									$nightsObj->setReqExecLastDate(date("Y-m-d H:i:s"));

								if (!$dateObj->Edited)
									$dateObj->setActive(true);

								if (!$cachedTransport->Edited)
								{
									if (!$cachedTransport->Content)
										$cachedTransport->Content = new \Omi\Cms\Content();
									$cachedTransport->Content->setActive(true);
								}

								try
								{
									$storage->setupTransportsCacheData_Tours_topCust($toCacheData, $nightsObj, $variant, $tour, $params, $request, $showDiagnosis);
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
					}
					else
					{
						if (!isset($errShownForTours[$tour->InTourOperatorId]))
						{
							echo "<div style='color: red;'>Missing departure cities on tour [{$tour->InTourOperatorId}] {$tour->Title}</div>";
							$errShownForTours[$tour->InTourOperatorId] = $tour->InTourOperatorId;
						}

					}
				}

				// save the tour
				$appData->Tours[$tour->InTourOperatorId] = $tour;
			}

			if ($request)
			{
				$request->setResults(count($reqResults));
				$request->setAllResults(count($reqAllResults));
			}

			if (count($saveToursHasTravelItmFlags))
				$storage->flagToursTravelItems($saveToursHasTravelItmFlags);

			// save active tours
			qArrayToCodeFile(static::$_CacheData["EU_ACTIVE_TOURS"], "EU_ACTIVE_TOURS", "eurosite_active_tours.php");

			foreach ($cachedTransports ?: [] as $indx => $transport)
			{
				if (!$upOnlyNights)
				{
					// deactivate the transport
					if (!$processedTransports[$indx])
						$transport->deactivate();

					$appData->ToursTransports[$transport->getId()] = $transport;

					foreach ($transport->_tours ?: [] as $_tourTopId => $__tdata)
					{
						list($tk, $_tour) = $__tdata;

						if (!isset($processedTransportsTours[$indx . "~" . $_tourTopId]))
						{
							$transport->Tours->setTransformState(\QModel::TransformDelete, $tk);
						}
					}
				}

				// dates
				foreach ($transport->_dates ?: [] as $date => $dateObj)
				{
					if (!$upOnlyNights)
					{
						// deactivate the date
						if (!isset($processedTransportsDates[$indx . "~". $date]))						
							$dateObj->deactivate();

						foreach ($dateObj->_tours ?: [] as $_tourTopId => $__tdata)
						{
							list($tk, $_tour) = $__tdata;

							if (!isset($processedTransportsDatesTours[$indx . "~" . $date . "~" . $_tourTopId]))	
								$dateObj->Tours->setTransformState(\QModel::TransformDelete, $tk);
						}
					}

					// nights
					foreach ($dateObj->_nights ?: [] as $nightObj)
					{
						// remove if no nights
						if (!$nightObj->Nights)
						{
							$nightObj->markForCleanup();
							continue;
						}

						// deactivate nights
						if (!isset($processedTransportsDatesNights[$indx . "~" . $date . "~" . $nightObj->Nights]))
							$nightObj->deactivate();							

						foreach ($nightObj->_tours ?: [] as $_tourTopId => $__tdata)
						{
							list($tk, $_tour) = $__tdata;
							if (!isset($processedTransportsDatesNightsTours[$indx . "~" . $date . "~" . $nightObj->Nights . "~" . $_tourTopId]))
								$nightObj->Tours->setTransformState(\QModel::TransformDelete, $tk);
						}
					}
				}
			}

			if ($storage->TourOperatorRecord)
				$storage->TourOperatorRecord->restoreCredentials();

			$appData->save(true);

			if ($tf_req_id)
			{
				/*
				\Omi\TFuse\Api\Travelfuse::RefreshTravelItemsCacheData([
					"TravelfuseReqID" => $tf_req_id
				], true);
				*/
			}

			// save transports cache data
			#$storage->saveTransportsCacheData($toCacheData, true);
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
	}
}