<?php

namespace Omi\TF;

trait EuroSitePackages
{
	/*=================================PACKAGES AND HOTELS=============================================*/
	/**
	 * Returns a list of packages as offers
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getPackages($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}
		$data = self::GetResponseData($this->doRequest("getPackageNVRoutesRequest", $params), "getPackageNVRoutesResponse");

		$countries = $data["Country"] ? $data["Country"] : null;

		if (!$countries || (count($countries) === 0))
			return [];

		if (!$objs)
			$objs = [];

		$ret = [];
		foreach ($countries as $country)
		{
			$countryObj = null;
			if ($country["CountryCode"])
			{
				$countryObj = $this->getCacheCountry($country["CountryCode"]);
				if (!$countryObj)
				{
					$countryObj = $objs[$country["CountryCode"]]["\Omi\Country"] ?: ($objs[$country["CountryCode"]]["\Omi\Country"] = new \Omi\Country());
					$countryObj->Code = $country["CountryCode"];
					$countryObj->Name = $country["CountryName"];
				}
			}

			$destinations = ($country['Destinations'] && $country['Destinations']['Destination']) ? $country['Destinations']['Destination'] : null;
			if (!$destinations || count($destinations) === 0)
				continue;

			if ($destinations["CityCode"])
				$destinations = array($destinations);

			foreach ($destinations as $destination)
			{
				$destinationCounty = null;
				if ($destination["ZoneCode"])
				{
					$destinationCounty = $this->getCacheCounty($destination["ZoneCode"]);
					if (!$destinationCounty)
					{
						$destinationCounty = $objs[$destination["ZoneCode"]]["\Omi\County"] ?: ($objs[$destination["ZoneCode"]]["\Omi\County"] = new \Omi\County());
						$destinationCounty->Code = $destination["ZoneCode"];
						$destinationCounty->Name = $destination["ZoneName"];
						$destinationCounty->Country = $countryObj;	
					}
				}

				$destinationCity = null;
				if ($destination["CityCode"])
				{
					$destinationCity = $this->getCacheCity($destination["CityCode"]);
					if (!$destinationCity)
					{
						$destinationCity = $objs[$destination["CityCode"]]["\Omi\City"] ?: ($objs[$destination["CityCode"]]["\Omi\City"] = new \Omi\City());
						$destinationCity->Code = $destination["CityCode"];
						$destinationCity->Name = $destination["CityName"];
						$destinationCity->Country = $countryObj;
						$destinationCity->County = $destinationCounty;
					}
				}

				$departures = ($destination["Departures"] && $destination["Departures"]["Departure"]) ? $destination["Departures"]["Departure"] : null;

				if (!$departures)
					continue;

				if (isset($departures["CountryCode"]))
					$departures = array($departures);

				foreach ($departures as $departure)
				{
					$departureCountry = null;
					if ($departure["CountryCode"])
					{
						$departureCountry = $this->getCacheCountry($departure["CountryCode"]);
						if (!$departureCountry)
						{
							$departureCountry = $objs[$departure["CountryCode"]]["\Omi\Country"] ?: ($objs[$departure["CountryCode"]]["\Omi\Country"] = new \Omi\Country());
							$departureCountry->Code = $departure["CountryCode"];
							$departureCountry->Name = $departure["CountryName"];
						}
					}

					$departureCounty = null;
					if ($departure["ZoneCode"])
					{
						$departureCounty = $this->getCacheCounty($departure["ZoneCode"]);
						if (!$departureCounty)
						{
							$departureCounty = $objs[$departure["ZoneCode"]]["\Omi\County"] ?: ($objs[$departure["ZoneCode"]]["\Omi\County"] = new \Omi\County());
							$departureCounty->Code = $departure["ZoneCode"];
							$departureCounty->Name = $departure["ZoneName"];
							$departureCounty->Country = $departureCountry;	
						}
					}

					$departureCity = null;
					if ($departure["CityCode"])
					{
						$departureCity = $this->getCacheCity($departure["CityCode"]);
						if (!$departureCity)
						{
							$departureCity = $objs[$departure["CityCode"]]["\Omi\City"] ?: ($objs[$departure["CityCode"]]["\Omi\City"] = new \Omi\City());
							$departureCity->Code = $departure["CityCode"];
							$departureCity->Name = $departure["CityName"];
							$departureCity->County = $departureCounty;
							$departureCity->Country = $departureCountry;
						}
					}

					$offerIndx = ($departureCountry ? $departureCountry->Code : ""). "~" .
						($departureCity ? $departureCity->Code : "")."~".
						($countryObj ? $countryObj->Code : "")."~".
						($destinationCity ? $destinationCity->Code : "");

					$offer = $objs[$offerIndx]["\Omi\Comm\Offer\Offer"] ?: ($objs[$offerIndx]["\Omi\Comm\Offer\Offer"] = new \Omi\Comm\Offer\Offer());

					$ret[] = $offer;

					if (!$offer->Item)
					{
						$offer->Item = new \Omi\Travel\Offer\Transport();
						$offer->Item->Merch = new \Omi\Travel\Merch\Transport();
					}

					if ($departureCity)
					{
						$offer->Item->Merch->From = $objs[$departureCity->Code]["\Omi\Address"] ?: 
							($objs[$departureCity->Code]["\Omi\Address"] = new \Omi\Address());
						$offer->Item->Merch->From->City = $departureCity;
					}

					if ($destinationCity)
					{
						$offer->Item->Merch->To = $objs[$destinationCity->Code]["\Omi\Address"] ?: 
							($objs[$destinationCity->Code]["\Omi\Address"] = new \Omi\Address());
						$offer->Item->Merch->To->City = $destinationCity;
					}

					$dates = ($departure["Dates"] && $departure["Dates"]["Date"]) ? $departure["Dates"]["Date"] : null;
					
					if (!$dates)
						continue;

					if (is_string($dates))
						$dates = array($dates);

					$offer->Item->Merch->Dates = new \QModelArray();
					foreach ($dates as $date)
					{						
						$dateObj = new \Omi\Travel\Merch\TransportDate();
						$dateObj->Date = $date;
						$offer->Item->Merch->Dates[] = $dateObj;
					}
				}
			}
		}
		return $ret;
	}
	/**
	 * Returns hotels with offers
	 * 
	 * @param array $params
	 * @param type $objs
	 * @return array
	 */
	private function getPackagesOffers($params = null, &$objs = null, $byHotel = false)
	{
		$tinit = microtime(true);
		if (!$objs)
			$objs = [];

		if (!$params)
			$params = [];

		if ($this->TourOperatorRecord->ApiContext)
			$params["TourOpCode"] = $this->TourOperatorRecord->ApiContext;

		$initial_params = $params;
		
		unset($params['getFeesAndInstallments']);
		unset($params['getFeesAndInstallmentsFor']);
		unset($params['getFeesAndInstallmentsForOffer']);
		unset($params["SellCurrency"]);
		unset($params["RequestCurrency"]);
		unset($params["ProductName"]);
		unset($params["ProductType"]);
		unset($params["VacationType"]);
		unset($params["ID"]);

		$callParams = $params;
		#if ((!$params["ProductCode"]) && isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
		if (isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
			unset($callParams['TourOpCode']);

		$data_reqq = $this->doRequest("getPackageNVPriceRequest", $callParams, true);
		$data = self::GetResponseData($data_reqq, "getPackageNVPriceResponse");
		
		$params = $initial_params;

		if ((!$data) || (!$data["Hotel"]))
		{
			#qvardump("getPackagesOffers without data took: " . (microtime(true) - $tinit) . " seconds", (microtime(true) - \Omi\App::$AppStartTime) . " seconds till app started!");
			return null;
		}

		if ($data["Hotel"]["Product"])
			$data["Hotel"] = [$data["Hotel"]];

		// we need to filter results for only one hotel - if this comes from cache
		if ($params["ProductCode"])
		{
			$hotelsOffers = [];
			foreach ($data["Hotel"] as $hotelData)
			{
				// make sure that we only return the requested hotel - otherwise we can have problems due to caching
				if (!$hotelData["Product"] || (!$hotelData["Product"]["ProductCode"]) || ($hotelData["Product"]["ProductCode"] != $params["ProductCode"]))
					continue;
				$hotelsOffers[] = $hotelData;
			}
		}
		else
			$hotelsOffers = $data["Hotel"];

		$t1 = microtime(true);
		$hotelsWithOffers = $this->getHotelsOffers($hotelsOffers, $objs, $byHotel, true, $params, static::$RequestOriginalParams);
		#qvardump("getHotelsOffers took: " . (microtime(true) - $t1) . " seconds", (microtime(true) - \Omi\App::$AppStartTime) . " seconds till app started!");

		if ($hotelsWithOffers)
			$this->flagHotelsTravelItems($hotelsWithOffers);
		
		/*
		if (($tf_request_id = $params["__request_data__"]["ID"]))
		{
			#\QApp::AddCallbackAfterResponseLast(function ($tf_request_id) {
				// refresh travel items cache data
				\Omi\TFuse\Api\Travelfuse::RefreshTravelItemsCacheData([
					"TravelfuseReqID" => $tf_request_id
				], false);
			#}, [$tf_request_id]);	
		}
		*/

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

		#qvardump("getPackagesOffers with data took: " . (microtime(true) - $tinit) . " seconds", (microtime(true) - \Omi\App::$AppStartTime) . " seconds till app started!");
		return $hotelsWithOffers;
	}

	/************************************************************************************
	 ************************************************************************************
	 * 
	 * Rest of the methods for packages are in fact the methods for hotels from individual
	 * Methods like: getProductInfoUpdateRequest, getProductInfoRequest, getHotelServiceTypesRequest, getHotelServicePriceRequest, getItemFeesRequest
	 * 
	 ************************************************************************************
	 ************************************************************************************/

	/*===================================END PACKAGES AND HOTELS=============================================*/
	
}