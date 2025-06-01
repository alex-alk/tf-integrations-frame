<?php

namespace Omi\TF;

trait EuroSiteIndividual
{
	/*===================================INDIVIDUAL=============================================*/

	/**
	 * Returns hotels with offers
	 * 
	 * @param array $params
	 * @param type $objs
	 * @return array
	 */
	private function getIndividualOffers($params = null, &$objs = null, $byHotel = false)
	{
		$t1 = microtime(true);
		if (!$params)
			$params = array();

		if ($this->TourOperatorRecord->ApiContext)
			$params["TourOpCode"] = $this->TourOperatorRecord->ApiContext;

		$initial_params = $params;

		// unset get fees and installments flags
		unset($params['getFeesAndInstallments']);
		unset($params['getFeesAndInstallmentsFor']);
		unset($params['getFeesAndInstallmentsForOffer']);
		unset($params["VacationType"]);
		unset($params["SellCurrency"]);
		unset($params["RequestCurrency"]);
		unset($params["ProductName"]);
		unset($params["ProductType"]);
		unset($params["ID"]);
		unset($params['ParamsFile']);
		unset($params['_req_off_']);

		$callParams = $params;
		# if ((!$params["ProductCode"]) && isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
		if (isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']) && (!isset($callParams['ProductCode'])))
			unset($callParams['TourOpCode']);

		$data = static::GetResponseData($this->doRequest("getHotelPriceRequest", $callParams, true), "getHotelPriceResponse");

//		if (static::$Exec)
//			qvardump("Results", $data, $this->TourOperatorRecord->Handle, $params);

		if (!$data || (!count($data)))
		{
			#qvardump("getIndividualOffers took: " . (microtime(true) - $t1) . " seconds");
			return [];
		}

		$params = $initial_params;
		if (static::$RequestOriginalParams && static::$RequestOriginalParams["_req_off_"])
			$params["_req_off_"] = static::$RequestOriginalParams["_req_off_"];

		if (!$data || !$data["Hotel"])
		{
			#qvardump("getIndividualOffers took: " . (microtime(true) - $t1) . " seconds");
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
				if (!$hotelData["Product"] || !$hotelData["Product"]["ProductCode"] || ($hotelData["Product"]["ProductCode"] != $params["ProductCode"]))
					continue;

				$hotelsOffers[] = $hotelData;
			}
		}
		else
			$hotelsOffers = $data["Hotel"];
		
		//if ($hotelsOffers)
		//	qvardump($this->TourOperatorRecord, $hotelsOffers);

		$hotels = $this->getHotelsOffers($hotelsOffers, $objs, $byHotel, false, $params, static::$RequestOriginalParams);
	
		if ($hotels)
			$this->flagHotelsTravelItems($hotels);

		#qvardump("getIndividualOffers took: " . (microtime(true) - $t1) . " seconds");
		return $hotels;
	}
	/**
	 * Returns true if the hotel needs data needs to be updated, false otherwise
	 * 
	 * @param array $params
	 * @return boolean
	 */
	private function getHotelUpdateInfo($params)
	{
		$params["ProductType"] = "hotel";
		$reqParams = array("ProductList" => array("Product" => $params));
		$data = static::GetResponseData($this->doRequest("getProductInfoUpdateRequest", $reqParams), "getProductInfoUpdateResponse");
		return ($data["UpdateList"] && $data["UpdateList"]["IsUpdatable"] && ($data["UpdateList"]["IsUpdatable"] === "Y"));
	}
	/**
	 * 
	 * @param type $params
	 * @param type $objs
	 * @return \Omi\Travel\Merch\Hotel
	 */
	private function getHotelInfo($params = null, &$objs = null, &$hotels_data = [])
	{
		if (!$params)
			$params = array();

		$params["ProductType"] = "hotel";

		$reqParams = $params;
		unset($reqParams["Force"]);
		unset($reqParams["SkiqQ"]);

		if (!$objs)
			$objs = array();

		try
		{
			// we must skip cache because we don't have the product parameter and it will cache at first and after will give results from cache for each hotel
			$cache_use = \Omi\App::$_ApiQueryOriginalParams["_cache_use"];
			\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = false;
			$data = static::GetResponseData($this->doRequest("getProductInfoRequest", $reqParams), "getProductInfoResponse");
			$hotels_data[$reqParams["TourOpCode"]][$reqParams["ProductCode"]] = $data;
			\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = $cache_use;
		}
		catch (\Exception $ex)
		{
			//qvardump($params);
			throw $ex;
		}
		$ret = $data["Product"] ? $this->getHotel($data["Product"], $objs, $params["Force"], $params["SkiqQ"]) : [null, false];
		list($hotel, $saveHotel) = $ret;
		return $hotel;
	}
	
	/**
	 * Get hotel info V2 (used version)
	 * 
	 * @param type $filter
	 * @param type $useCache
	 * @param type $changeGeo
	 * 
	 * @return \Omi\Travel\Merch\Hotel
	 * 
	 * @throws \Exception
	 */
	private function getHotelInfo_NEW($filter, $useCache = false, $changeGeo = false)
	{
		$toSaveHotelsProcesses = [];
		$alreadyProcessed_all = true;

		$reqParams = [
			"TourOpCode" => $filter['ApiContext'],
			"CityCode" => $filter["cityId"],
			"CountryCode" => $filter["countryId"],
			"ProductType" => "hotel",
			"ProductCode" => $filter["travelItemId"],
			"ProductName" => $filter["travelItemName"],
		];

		$force = $filter["force"] ?: false;

		$hotelObj = null;
		$err = false;
		$objs = [];
		$t1 = microtime(true);

		try
		{
			// we must skip cache because we don't have the product parameter and it will cache at first and after will give results from cache for each hotel
			$cache_use = \Omi\App::$_ApiQueryOriginalParams["_cache_use"];
			\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = false;

			// get response data
			$callMethod = "getProductInfoRequest";
			$callKeyIdf = md5(json_encode($reqParams));
			$callParams = $reqParams;
			
			// we need to make sure that we have all data in order (countries and continents)
			$refThis = $this;
			list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis, $useCache) 
			{
				$reqEX = null;
				try
				{
					$return = $refThis->doRequest($method, $params, true, $useCache);
				}
				catch (\Exception $ex)
				{
					$reqEX = $ex;
				}

				//file_put_contents('hotel_resp_' . $params['ProductCode'] . '.req.xml', $return['rawReq']);
				//file_put_contents('hotel_resp_' . $params['ProductCode'] . '.resp.xml', $return['rawResp']);

				if (($rawResp = $return["rawResp"]))
					$rawResp = preg_replace('#(<ResponseTime>(.*?)</ResponseTime>)|(<ResponseId>(.*?)</ResponseId>)|(<RequestId>(.*?)</RequestId>)#', '', $rawResp);
				if (($rawReq = $return["rawReq"]))
					$rawReq = preg_replace('#(<RequestTime>(.*?)</RequestTime>)#', '', $rawReq);
				return [$return, $rawReq, $rawResp, $reqEX];
			}, $callMethod, $callParams, $callKeyIdf, $force);
			
			if (!$alreadyProcessed)
				$alreadyProcessed_all = false;

			if ((!$return) || ($alreadyProcessed && (!$force)))
			{
				/*
				if (!$return)
					$this->TrackReport->_noResponse = true;
				if ($alreadyProcessed)
					$this->TrackReport->_responseNotChanged = true;
				echo $alreadyProcessed ? "Nothing changed from last request!" : "No return";
				$storage->closeTrackReport(ob_get_clean());
				return;
				*/
			}

			$respData = static::GetResponseData($return, "getProductInfoResponse");

			try
			{
				$hotelObj = null;
				$saveHotel = false;
				if ($respData["Product"])
				{
					list($hotelObj, $saveHotel) = $this->getHotel($respData["Product"], $objs, ($filter["Force"] || $force), 
							$filter["SkiqQ"], $hotelObj, $changeGeo);
				}
			}
			catch (\Exception $ex) 
			{
				throw $ex;
			}

			\Omi\App::$_ApiQueryOriginalParams["_cache_use"] = $cache_use;

			$toSaveHotelsProcesses = [[$callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1)]];
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}

		return [$hotelObj, $err, $alreadyProcessed_all, $toSaveHotelsProcesses, $saveHotel];
	}
	
	public function updateHotelDetails($hotelDetails, $dbHotel, $force = false)
	{
		$saveHotel = true;
		$toSaveProcesses_On_Hotel = [];
		return [$hotelDetails, $saveHotel, $toSaveProcesses_On_Hotel];
	}
	
	/**
	 * Returns an array with extra services
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getHotelSupliments($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = array();
		}
		$data = static::GetResponseData($this->doRequest("getHotelServiceTypesRequest", $params), "getHotelServiceTypesResponse");
		$services = ($data["Services"] && $data["Services"]["Service"]) ? $data["Services"]["Service"] : null;
		return $this->getServices($services);
	}
	/**
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return \Omi\Comm\Offer\OfferItem
	 */
	private function getHotelSupliment($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = array();
		}
		$data = static::GetResponseData($this->doRequest("getHotelServicePriceRequest", $params), "getHotelServicePriceResponse");
		$service = ($data["Services"] && $data["Services"]["Service"]) ? $data["Services"]["Service"] : null;
		return $service ? $this->getSerivce($service) : null;
	}

	/**
	 * Returns hotel earnests
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getHotelInstallments($params = null, &$objs = null)
	{

		$_p = $params;
		$params = array(
			"CurrencyCode" => $_p["CurrencyCode"],
			"BookingItems" => array(
				"BookingItem" => array(
					"ProductType" => "hotel",
					["TourOpCode" => $_p["TourOpCode"]],
				)
			)
		);
		
		$price = $_p["Price"];
		$currencyCode = $_p["CurrencyCode"];
		$sellCurrency = $_p["SellCurrency"];
		
		// setup 
		if (!($suppliedCurrency = static::GetCurrencyByCode($currencyCode)))
		{
			throw new \Exception("Undefined currency [{$currencyCode}]!");
		}
		
		if (!($sellCurrencyObj = static::GetCurrencyByCode($sellCurrency)))
		{
			throw new \Exception("Undefined currency [{$sellCurrency}]!");
		}

		// load currency settings
		$currencySettings = $this->getCurrencySettings($suppliedCurrency->Code, $sellCurrencyObj->Code);

		$__type__ = $_p["__type__"];

		unset($_p["CurrencyCode"]);
		unset($_p["SellCurrency"]);
		unset($_p["RequestCurrency"]);
		unset($_p["__type__"]);
		unset($_p["__cs__"]);
		unset($_p["CheckIn"]);
		unset($_p["Price"]);
		unset($_p["TourOpCode"]);
		unset($_p["Country"]);
		unset($_p["County"]);
		unset($_p["City"]);
		unset($_p["Rooms"]);

		$params["BookingItems"]["BookingItem"][$isCharter ? "CharterItem" : "HotelItem"] = $_p;

		$currencyCode = $params['CurrencyCode'];
		unset($params['CurrencyCode']);

		$data = static::GetResponseData($this->doRequest(["getItemPaymentDLSRequest", "CurrencyCode" => $currencyCode], $params, true), "getItemPaymentDLSResponse");

		$fees = ($data["ItemPaymentDLS"] && $data["ItemPaymentDLS"]["ItemPaymentDL"] && $data["ItemPaymentDLS"]["ItemPaymentDL"]["Fees"] && 
			$data["ItemPaymentDLS"]["ItemPaymentDL"]["Fees"]["Fee"]) ? $data["ItemPaymentDLS"]["ItemPaymentDL"]["Fees"]["Fee"] : null;
		
		if (!$fees || (count($fees) === 0))
			return array();

		if (!$objs)
			$objs = array();

		if (isset($fees["Value"]))
			$fees = array($fees);

		$sum_fees = 0;
		foreach ($fees ?: [] as $fee)
		{
			list($value, $val_attrs) = is_array($fee["Value"]) ? [$fee["Value"][0], $fee["Value"]["@attributes"]] : [$fee["Value"], null];
			$sum_fees += $value;
		}

		$force_percent = (($price != 100) && ($sum_fees == 100));

		$payments = new \QModelArray();
		$pos = 0;
		foreach ($fees as $fee)
		{
			list($value, $val_attrs) = is_array($fee["Value"]) ? [$fee["Value"][0], $fee["Value"]["@attributes"]] : [$fee["Value"], null];

			if ($value == 0)
				continue;

			$is_percent = ($force_percent || ($val_attrs && $val_attrs["Procent"]));

			$payment = new \Omi\Comm\Payment();
			$payment->PayAfter = $fee["FromDate"];
			$payment->PayUntil = $fee["ToDate"];
			$payment->setSuppliedCurrency($suppliedCurrency);

			$payment->MinAmount = $value;
			$payment->MinAmountIsPercent = $is_percent;
			$payment->setupAmount($price);
			$payment->SuppliedPrice = $payment->Amount;
			$payment->Currency = $sellCurrencyObj;

			// if the payment is not percent then apply comission to it
			if (!$is_percent)
			{
				$payment->setAmount($this->getPrice($payment->Amount, $__type__));
				if (($payment->Currency->Code != $payment->SuppliedCurrency->Code))
					$payment->Amount = \Omi\Comm\Payment::GetWithCommisionPrice($payment->SuppliedCurrency->Code, $payment->Currency->Code, $payment->Amount);
			}

			// if we have currency settings and the convert comission - set it up
			if ($currencySettings && $currencySettings->ConvertCommission)
				$payment->Amount += (($payment->Amount * $currencySettings->ConvertCommission)/100);
			
			$payment->Amount = ceil($payment->Amount);

			$payments[] = $payment;
			$pos++;
		}

		return $payments;
	}
	/**
	 * Returns fees as offers for cancelling an offer
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return \Omi\Comm\Offer\Offer
	 */
	private function getHotelFees($params = null, &$objs = null, $isCharter = false)
	{
		$sellCurrencyCode = $params['SellCurrency'];
		unset($params['SellCurrency']);

		$_p = $params;

		$params = array(
			"CurrencyCode" => $_p["CurrencyCode"],
			"BookingItems" => array(
				"BookingItem" => array(
					"ProductType" => $isCharter ? "charter" : "hotel",
					["TourOpCode" => $_p["TourOpCode"]]
				)
			)
		);

		$price = $_p["Price"];
		$__type__ = $_p["__type__"];

		unset($_p["CurrencyCode"]);
		unset($_p["__type__"]);
		unset($_p["__cs__"]);
		unset($_p["CheckIn"]);
		unset($_p["Price"]);
		unset($_p["TourOpCode"]);
		unset($_p["Country"]);
		unset($_p["County"]);
		unset($_p["City"]);
		unset($_p["Rooms"]);

		$params["BookingItems"]["BookingItem"][$isCharter ? "CharterItem" : "HotelItem"] = $_p;

		$currencyCode = $params['CurrencyCode'];
		unset($params['CurrencyCode']);

		$getWithAttrs = true;

		try
		{
			$data = static::GetResponseData($this->doRequest(["getItemFeesRequest", "CurrencyCode" => $currencyCode], $params, $getWithAttrs), "getItemFeesResponse");
		}
		catch (\Exception $ex)
		{
			throw $ex;
			// return null;
		}

		$fees = ($data["ItemFees"] && $data["ItemFees"]["ItemFee"] && $data["ItemFees"]["ItemFee"]["Fees"] && 
			$data["ItemFees"]["ItemFee"]["Fees"]["Fee"]) ? $data["ItemFees"]["ItemFee"]["Fees"]["Fee"] : null;

		if (!$fees || (count($fees) === 0))
			return [];

		if (!$objs)
			$objs = [];

		if (isset($fees["Value"]))
			$fees = [$fees];

		$ret = new \QModelArray();

		$cf = count($fees);

		$last_fee_hundred = false;
		$all_fees_under_hundred = true;

		if (defined("FEES_DAYS_ADV") && (int)FEES_DAYS_ADV)
		{
			$nowDate = date("Y-m-d");
			$nowDateTime = strtotime($nowDate);
			$newFees = [];
			foreach ($fees ?: [] as $fee)
			{
				$dateStart = date("Y-m-d", strtotime("- " . FEES_DAYS_ADV . " days", strtotime($fee["FromDate"])));
				$dateEnd = date("Y-m-d", strtotime("- " . FEES_DAYS_ADV . " days", strtotime($fee["ToDate"])));

				if (strtotime($dateEnd) < $nowDateTime)
					continue;

				if (strtotime($dateStart) < $nowDateTime)
					$dateStart = $nowDate;

				$fee["FromDate"] = $dateStart;
				$fee["ToDate"] = $dateEnd;

				$newFees[] = $fee;
			}
			$fees = $newFees;
		}

		$fees_pos = 0;
		foreach ($fees ?: [] as $fee)
		{
			list($value, $val_attrs) = is_array($fee["Value"]) ? [$fee["Value"][0], $fee["Value"]["@attributes"]] : [$fee["Value"], null];

			if ($value == 0)
				continue;

			#$is_percent = ($force_percent || ($val_attrs && $val_attrs["Procent"]));

			if ($value > 100)
				$all_fees_under_hundred = false;

			$fees_pos++;
			if (($fees_pos === $cf) && ($value == 100))
				$last_fee_hundred = true;
		}

		// force percent if they are all under 100 and 
		$force_percent = (static::$Config[$this->TourOperatorRecord->Handle]["FORCE_FEE_PERCENT"] ||
			static::$Config[$this->TourOperatorRecord->Handle][$isCharter ? "charter" : "individual"]["FORCE_FEE_PERCENT"] || 
			($all_fees_under_hundred && ($last_fee_hundred || (($__type__ === "charter") && ($price > 100)))));

		$pos = 0;
		foreach ($fees as $fee)
		{
			list($value, $val_attrs) = is_array($fee["Value"]) ? [$fee["Value"][0], $fee["Value"]["@attributes"]] : [$fee["Value"], null];

			// skip first if free
			if (!$value && ($pos === 0))
			{
				$pos++;
				continue;
			}

			$fee["Type"] = ($fee["@attributes"] && $fee["@attributes"]["Type"]) ? $fee["@attributes"]["Type"] : null;
			$is_percent = ($force_percent || ($val_attrs && $val_attrs["Procent"]));
			$force_percent_on_itm = ($force_percent || $is_percent);
			$ret[] = $this->getFee($fee, $price, $__type__, $currencyCode, $sellCurrencyCode, $force_percent_on_itm, $objs);
			$pos++;
		}

		//qvardump($ret);
		return $ret;
	}

	private function saveStaysDiscounts(&$objs = null)
	{
		// get stay discounts
		$stayDiscounts = \QApi::Call("\\Omi\\Comm\\Offer\\OfferDiscount::GetDiscounts", "Stays");

		// index existing discounts
		$existingDiscounts = [];
		foreach ($stayDiscounts as $discount)
		{
			// discount code should be: tag, value, valueType, start date, end date
			$discountIndx = $discount->Tag."~".$discount->StartDate."~".$discount->EndDate."~".$discount->Value."~".$discount->Type;
			//var_dump($discountIndx);

			// discunt should be unique
			if ($existingDiscounts[$discountIndx])
				throw new \Exception("Discount must be unique!");

			$existingDiscounts[$discountIndx] = $discount;

			// index discounts offers
			if (!$discount->_offers)
				$discount->_offers = [];

			if ($discount->Offers)
			{
				foreach ($discount->Offers as $key => $offer)
				{
					$roomItem = $offer->getRoomItem();
					if (!$roomItem || !$roomItem->Merch || !$roomItem->Merch->Hotel)
						continue;

					$offerIndx = $roomItem->Merch->Hotel->Code."~".$roomItem->CheckinAfter."~".$roomItem->CheckinBefore;
					//var_dump($offerIndx);
					//var_dump(array_keys($discount->_offers));
					if ($discount->_offers[$offerIndx])
					{
						throw new \Exception("Duplicate offer for discount!");
					}
					$discount->_offers[$offerIndx] = [$key, $offer];
				}
			}
		}

		if (!$objs)
			$objs = array();

		$data = static::GetResponseData($this->doRequest("getUnitDiscountsRequest", [], true), "getUnitDiscountsResponse");

		// get the hotels list
		$hotels = ($data && $data["Hotels"] && $data["Hotels"]["Hotel"]) ? $data["Hotels"]["Hotel"] : null;

		// store the processed discounts to be later compared to the existing one - used for deletion
		$processedDiscounts = [];
		$appData = \QApp::NewData();

		// if we have hotels discounts returned from eurosite go through them
		if ($hotels && (count($hotels) > 0))
		{
			// prepare extra data to be saved!
			$appData->Countries = new \QModelArray();
			$appData->Cities = new \QModelArray();
			$appData->Hotels = new \QModelArray();
			$appData->OffersDiscounts = new \QModelArray();

			foreach ($hotels as $hotel)
			{
				$grids = ($hotel["Grids"] && $hotel["Grids"]["Grid"]) ? $hotel["Grids"]["Grid"] : null;

				if (!$grids || !$hotel["CityCode"])
					continue;

				if ($grids["GridCode"])
					$grids = [$grids];

				// get hotel by code if any
				$hotelObj = $appData->Hotels[$hotel["HotelCode"]] ? $appData->Hotels[$hotel["HotelCode"]] : 
					\QApi::QueryById("Hotels", ["Code" => $hotel["HotelCode"]]);

				// if we don't have the hotel then save it
				if (!$hotelObj)
				{
					$params = [];
					$params["CityCode"] = $hotel["CityCode"];
					$params["CountryCode"] = $hotel["CountryCode"];
					$params["ProductCode"] = $hotel["HotelCode"];
					$hotelObj = $this->getHotelInfo($params);

					if (!$hotelObj)
						continue;

					$appData->Hotels[$hotelObj->Code] = $hotelObj;
				}
				
				if (!$hotelObj)
				{
					continue;
					//qvardump($hotel, $params);
					//throw new \Exception("block here!");
				}

				$hotelObj->setMTime(date("Y-m-d H:i:s"));

				// get the country from cache - if we don't have it then save it
				$country = $appData->Countries[$hotel["CountryCode"]] ? $appData->Countries[$hotel["CountryCode"]] : 
					$this->getCacheCountry($hotel["CountryCode"]);
				if (!$country)
				{
					$country = $objs[$hotel["CountryCode"]]["\Omi\Country"] ?: ($objs[$hotel["CountryCode"]]["\Omi\Country"] = new \Omi\Country());
					$country->Code = $hotel["CountryCode"];
					$country->Name = $hotel["CountryName"];
					$appData->Countries[$country->Code] = $country;
				}
				
				$country->setMTime(date("Y-m-d H:i:s"));

				// get the city from cache - if we don't have it then save it
				$city = $appData->Cities[$hotel["CityCode"]] ? $appData->Cities[$hotel["CityCode"]] : 
					$this->getCacheCity($hotel["CityCode"]);
				if (!$city)
				{
					$city = $objs[$hotel["CityCode"]]["\Omi\City"] ?: ($objs[$hotel["CityCode"]]["\Omi\City"] = new \Omi\City());
					$city->Code = $hotel["CityCode"];
					$city->Name = $hotel["CityName"];
					$city->Country = $country;
					$appData->Cities[$city->Code] = $city;
				}

				$city->setMTime(date("Y-m-d H:i:s"));

				// setup country on city
				if ($country)
					$city->Country = $country;

				// setup country and city on hotel address
				if (!$hotelObj->Address)
					$hotelObj->Address = new \Omi\Address();
				$hotelObj->Address->City = $city;
				$hotelObj->Address->Country = $country;

				// go through eurosite data
				foreach ($grids as $grid)
				{
					$gridDiscounts = ($grid["Discounts"] && $grid["Discounts"]["Discount"]) ? $grid["Discounts"]["Discount"] : null;
					if (!$gridDiscounts)
						continue;

					if ($gridDiscounts["Type"])
						$gridDiscounts = [$gridDiscounts];

					// go through discounts
					foreach ($gridDiscounts as $discountData)
					{
						// determine the discount index
						$discountIndx = $grid["GridName"]."~".$discountData["IssueDateStart"]."~".$discountData["IssueDateStop"]."~"
							. $discountData["Value"]."~". strtolower($discountData["ValueType"]);

						// check if the discount already exists
						$discount = $existingDiscounts[$discountIndx] ? $existingDiscounts[$discountIndx] : ($objs[$discountIndx]["\Omi\Travel\Offer\StayDiscount"] ?: 
							($objs[$discountIndx]["\Omi\Travel\Offer\StayDiscount"] = new \Omi\Travel\Offer\StayDiscount()));

						// setup discount data
						$discount->Tag = $grid["GridName"];
						$discount->StartDate = $discountData["IssueDateStart"];
						$discount->EndDate = $discountData["IssueDateStop"];
						$discount->Value = $discountData["Value"];
						$discount->Type = strtolower($discountData["ValueType"]);
						//$discount->setMTime(date("Y-m-d H:i:s"));

						// store the discount on stay discounts collection
						$appData->OffersDiscounts[$discountIndx] = $discount;

						// determine offer index
						$offerIndx = $hotelObj->Code."~".$discountData["SejourDateStart"]."~".$discountData["SejourDateStop"];

						//var_dump($discountIndx."  ||  ".$offerIndx);

						if (!$processedDiscounts[$discountIndx])
							$processedDiscounts[$discountIndx] = [];
	
						// get the offer and the key - if the offer exists
						list($_offKey, $offer) = ($discount->_offers && $discount->_offers[$offerIndx]) ? $discount->_offers[$offerIndx] : 
							[0, $processedDiscounts[$discountIndx][$offerIndx] ? $processedDiscounts[$discountIndx][$offerIndx] : new \Omi\Travel\Offer\Stay()];

						if (!$offer->Items)
							$offer->Items = new \QModelArray();

						// setup offer data
						$roomOfferItem = $offer->getRoomItem();
						if (!$roomOfferItem)
							$roomOfferItem = new \Omi\Travel\Offer\Room();

						if (!$roomOfferItem->Merch)
							$roomOfferItem->Merch = new \Omi\Travel\Merch\Room();

						$roomOfferItem->Merch->Hotel = $hotelObj;
						$roomOfferItem->CheckinAfter = $discountData["SejourDateStart"];
						$roomOfferItem->CheckinBefore = $discountData["SejourDateStop"];

						$offer->Item = $roomOfferItem;
						if (!$roomOfferItem->getId())
							$offer->Items[] = $roomOfferItem;

						// setup here transport items
						$departureItem = $offer->getDepartureTransportItem();
						if (!$departureItem)
							$departureItem = new \Omi\Travel\Offer\Transport();

						if (!$departureItem->Merch)
							$departureItem->Merch = new \Omi\Travel\Merch\Transport();

						if (!$departureItem->Return)
							$departureItem->Return = new \Omi\Travel\Offer\Transport();

						if (!$departureItem->Return->Merch)
							$departureItem->Return->Merch = new \Omi\Travel\Merch\Transport();

						if (!$departureItem->Merch->To)
							$departureItem->Merch->To = new \Omi\Address();

						if (!$departureItem->Return->Merch->From)
							$departureItem->Return->Merch->From = new \Omi\Address();

						$departureItem->Merch->To->City = $departureItem->Return->Merch->From->City = $city;
						$departureItem->Merch->To->County = $departureItem->Return->Merch->From->County = $city ? $city->County : null;
						$departureItem->Merch->To->Country = $departureItem->Return->Merch->From->Country = $city ? $city->Country : null;
						$departureItem->DepartureDate = $discountData["SejourDateStart"];
						$departureItem->Return->DepartureDate = $discountData["SejourDateStop"];

						if (!$departureItem->getId())
							$offer->Items[] = $departureItem;

						if (!$departureItem->Return->getId())
							$offer->Items[] = $departureItem->Return;

						// save the offer on discount
						if (!$discount->Offers)
							$discount->Offers = new \QModelArray();

						if (!$offer->getId() && !$processedDiscounts[$discountIndx][$offerIndx])
							$discount->Offers[] = $offer;
						$processedDiscounts[$discountIndx][$offerIndx] = $offer;
					}
				}
			}

			//qvardump($appData);
			//q_die("stopped!");

			// save first cities and countries
			$appData->save(
				"Cities.{"
					. "MTime, "
					. "Code, "
					. "Name, "
					. "Country.{"
						. "MTime, "
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "Countries.{"
					. "MTime, "
					. "Code, "
					. "Name"
				. "} "
			);

			// reset already saved data
			$appData->Cities = new \QModelArray();
			$appData->Countries = new \QModelArray();

			// save hotels
			$appData->save("Hotels.{"
				. "MTime, "
				. "Code, "
				. "Name, "
				. "Stars, "
				. "Content.{"
					. "Order, "
					. "Active, "
					. "ShortDescription, "
					. "Content, "
					. "ImageGallery.{Items.{Updated, Path, ExternalUrl, Type, RemoteUrl, Base64Data, TourOperator.{Handle, Caption, Abbr}, InTourOperatorId, Alt}}"				
				. "}, "	
				. "Address.{"
					. "City.{Code, Name}, "
					. "County.{Code, Name}, "
					. "Country.{Code, Name}, "
					. "Latitude, "
					. "Longitude"
				. "}"
			. "}");

			// reset already saved data
			$appData->Hotels = new \QModelArray();	

			// save stay discounts
			$appData->save("OffersDiscounts.{"
				. "MTime, "
				. "Tag, "
				. "StartDate, "
				. "EndDate, "
				. "Value, "
				. "Type, "
				. "Offers.{"
					. "Item.{"
						. "CheckinAfter, "
						. "CheckinBefore, "
						. "Merch.{"
							. "Hotel.Id"
						. "}"
					. "}, "
					. "Items.{"
						. "DepartureDate, "
						. "Return.{"
							. "DepartureDate, "
							. "Merch.{"
								. "From.{"
									. "City.Id, "
									. "Country.Id, "
									. "County.Id"
								. "}"
							. "}"
						. "}, "
						. "Merch.{"
							. "To.{"
								. "City.Id, "
								. "Country.Id, "
								. "County.Id"
							. "}"
						. "}"
					. "}"
				. "}"
			);
		}

		// remove stay discounts or offers for it
		if ($existingDiscounts && (count($existingDiscounts) > 0))
		{
			foreach ($existingDiscounts as $key => $discount)
			{
				$discountCls = get_class($discount);
				$dclone = new $discountCls();
				$dclone->setId($discount->getId());
				$dclone->Offers = new \QModelArray();
				$dclone->Offers->_rowi = $discount->Offers->_rowi;

				$processedOffers = $processedDiscounts[$key];

				$rmOffers = false;
				if ($discount->_offers && (count($discount->_offers) > 0))
				{
					foreach ($discount->_offers as $offkey => $offerData)
					{
						if ($processedOffers && $processedOffers[$offkey])
							continue;

						$rmOffers = true;
						list($offKey, $off) = $offerData;
						$off->setTransformState(\QIModel::TransformDelete);
						$dclone->Offers[$offKey] = $off;
						$dclone->Offers->setTransformState(\QIModel::TransformDelete, $offKey);
					}
				}

				if ($rmOffers)
				{
					//qvardump($dclone);
					$dclone->save("Offers.Id");
				}

				// if has processed offers then don't remove it
				if ($processedOffers)
					continue;

				// remove discount here
				$dclone->delete("Id");
			}
		}
	}

	public function resyncHotels($binds = [])
	{
		$this->saveHotels(array_merge($binds, ["__fromBoxes" => true]));
	}

	public function resyncIndividual($binds = [])
	{
		$individualTransports = QQuery("IndividualTransports.{To.City.{Name, InTourOperatorId} WHERE TourOperator.Id=?}", 
			$this->TourOperatorRecord->getId())->IndividualTransports;

		if (!$individualTransports || (count($individualTransports) === 0))
			return;

		$cities = [];
		foreach ($individualTransports as $it)
		{
			if (!$it->To || !$it->To->City)
				continue;
			$cities[$it->To->City->getId()] = [$it->To->City->getId()];
		}

		$binds = array_merge($binds, ["Cities" => [$cities]]);
		$this->saveHotels($binds);
	}

	public function saveHotels_ExecRequests($params, &$_max_hotels_count, $reqFile = null, $forceGettingHotels = false, &$hotelsInfo = [])
	{
		$today = date("Y-m-d");
		$objs = [];
		try 
		{
			$callParams = $params;
			#if ((!$params["ProductCode"]) && isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
			if (isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
				unset($callParams['TourOpCode']);
			// get response data
			$data = self::GetResponseData($this->doRequest("getHotelPriceRequest", $callParams, true), "getHotelPriceResponse");

			if ($reqFile)
			{
				ob_start();
				qvardump("RESULT", $data);
				$str = ob_get_clean();
				file_put_contents($reqFile, $str, FILE_APPEND);
			}

			$hotels = $data["Hotel"] ? $data["Hotel"] : null;
			$requestHaveHotels = false;
			$appData = \QApp::NewData();

			$useNewFacilitiesFunctionality = (defined('USE_NEW_FACILITIES') && USE_NEW_FACILITIES);
			
			if ($useNewFacilitiesFunctionality)
			{
				if (!isset(static::$_CacheData['facilities_loaded'][$this->TourOperatorRecord->ApiContext]))
				{
					$facilities = static::$_CacheData['facilities'][$this->TourOperatorRecord->ApiContext] = 
						$this->getHotelsFacilities(["TourOpCode" => $this->TourOperatorRecord->ApiContext]);	
					static::$_CacheData['facilities_loaded'][$this->TourOperatorRecord->ApiContext] = true;
				}
				else
				{
					$facilities = static::$_CacheData['facilities'][$this->TourOperatorRecord->ApiContext];
				}
			}

			$mealsTypes = [];
			if ($hotels && (($__hcnt = count($hotels)) > 0))
			{
				if ($__hcnt > $_max_hotels_count)
					$_max_hotels_count = $__hcnt;

				if ($hotels["Product"])
					$hotels = [$hotels];

				$appData->Hotels = new \QModelArray();
				foreach ($hotels as $hotelData)
				{
					if (!$hotelData["Product"] || !$hotelData["Product"]["TourOpCode"] || 
						($hotelData["Product"]["TourOpCode"] != $this->TourOperatorRecord->ApiContext) || !$hotelData["Product"]["ProductCode"])
						continue;

					$offs = $hotelData["Offers"]["Offer"];
					if ($offs && isset($offs["OfferType"]))
						$offs = [$offs];
					foreach ($offs ?: [] as $off)
					{
						$meals = $off["Meals"]["Meal"];
						if ($meals && isset($meals["@attributes"]))
							$meals = [$meals];
						foreach ($meals ?: [] as $meal)
						{
							unset($meal["@attributes"]);
							$mealType = trim(reset($meal));
							if ($mealType)
								$mealsTypes[$mealType] = $mealType;
						}
					}
					// we may have hotels updated in this very day because they are available for charters alse
					// we must skip them
					$dbHotel = QQuery("Hotels.{Code, Name, MTime, Address WHERE InTourOperatorId=? AND TourOperator.Id=?}", [
						$hotelData["Product"]["ProductCode"], $this->TourOperatorRecord->getId()])->Hotels[0];
					$savedToday = ($dbHotel && $dbHotel->MTime && (date("Y-m-d", strtotime($dbHotel->MTime)) == $today));
					if ((!($savedToday)) || $forceGettingHotels)
					{
						$hparams = [
							"TourOpCode" => $hotelData["Product"]["TourOpCode"],
							"CountryCode" => $hotelData["Product"]["CountryCode"],
							"CityCode" => $hotelData["Product"]["CityCode"],
							"ProductCode" => $hotelData["Product"]["ProductCode"],
							"ProductName" => $hotelData["Product"]["ProductName"],
							"Force" => true
						];
						$hotel = null;
						try
						{
							$hotel = $appData->Hotels[$hotelData["Product"]["ProductCode"]] ? 
								$appData->Hotels[$hotelData["Product"]["ProductCode"]] : $this->getHotelInfo($hparams, $objs, $hotelsInfo);
						}
						catch (\Exception $ex)
						{
							// leave as it is
							throw $ex;
						}
					}
					else
						$hotel = $dbHotel;
					if (!$hotel)
						continue;
					$requestHaveHotels = true;
					$hotel->setMTime(date("Y-m-d H:i:s"));
					$hotel->setTourOperator($this->TourOperatorRecord);
					$hotel->setHasIndividualOffers(true);
					$hotel->setHasIndividualActiveOffers(true);
					$hotel->setResellerCode($hotelData["Product"]["TourOpCode"]);
					if (!$hotel->Address)
						$hotel->Address = new \Omi\Address();
					if (!empty($hotelData["Product"]["Longitude"]))
						$hotel->Address->setLongitude($hotelData["Product"]["Longitude"]);
					if (!empty($hotelData["Product"]["Latitude"]))
						$hotel->Address->setLatitude($hotelData["Product"]["Latitude"]);
					$hotel->IsCached = true;
					
					if ($useNewFacilitiesFunctionality)
					{
						if ($facilities && $facilities[$hotel->Code])
						{
							if (!$hotel->Facilities)
								$hotel->Facilities = new \QModelArray();

							$ef = [];
							foreach ($hotel->Facilities as $hf)
							{
								if (trim($hf->Name))
									$ef[static::GetFacilityIdf($hf->Name)] = $hf;
							}

							foreach ($facilities[$hotel->Code] ?: [] as $facility)
							{
								if (isset($ef[static::GetFacilityIdf($facility->Name)]))
									continue;
								$hotel->Facilities[] = $facility;
							}
						}
					}

					if (!$hotel->getId())
					{
						$hotelIsActive = true;
						if (function_exists("q_getTopHotelActiveStatus"))
							$hotelIsActive = q_getTopHotelActiveStatus($hotel);
						$hotel->setActive($hotelIsActive);
					}

					$appData->Hotels[$hotel->InTourOperatorId] = $hotel;
				}
			}
			// meals types
			if (count($mealsTypes))
			{
				//qvardump("\$mealsTypes", $mealsTypes);
				\Omi\TFuse\Api\TravelFuse::SetupResults_SetupMealsAliases_FromList($mealsTypes, true);
			}
			if ($requestHaveHotels)
			{
				$this->saveInBatchHotels($appData->Hotels, 
					"FromTopAddedDate, "
					. "MTime, "
					. "ResellerCode, "
					. "Code, "
					. "Name,"
					. "Stars,"
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
						. "TourOperator.{Handle, Caption, Abbr}, "
						. "InTourOperatorId, "
						. "Alt"
					. "}, "
					. "Active, "
					. "ShortContent, "
					. "HasIndividualOffers,"
					. "HasIndividualActiveOffers,"
					. "HasCharterOffers,"
					. "TourOperator,"
					. "InTourOperatorId,"
					. "Content.{"
						. "Order, "
						. "Active, "
						. "ShortDescription, "
						. "Content, "
						. "ImageGallery.{"
							. "Items.{"
								. "Updated, "
								. "FromTopAddedDate, "
								. "Path, "
								. "Type, "
								. "ExternalUrl, "
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
							. "Code, "
							. "Name, "
							. "County.{"
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
			if ($reqFile)
			{
				ob_start();
				qvardump("EXCEPTION", $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString());
				$str = ob_get_clean();
				file_put_contents($reqFile, $str, FILE_APPEND);
			}
			else
			{
				qvardump("EXCEPTION", $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString());
				throw $ex;
			}
		}
		return $requestHaveHotels;
	}

	public function saveHotels($binds = [])
	{
		if (!file_exists(($fLock = \Omi\App::GetLogsDir('locks') . $this->TourOperatorRecord->Handle . "_individual_reqs.txt")))
			file_put_contents($fLock, "Synchronization lock");

		if (!($lock = \QFileLock::lock($fLock, 1)))
			throw new \Exception("Unable to get lock for synchronization on: " . $fLock);

		$reqsDir = \Omi\App::GetLogsDir('requests/eurosite') . $this->TourOperatorRecord->Handle . "/";
		if (!is_dir($reqsDir))
			qmkdir($reqsDir);
		$reqsPrefix = "eurosite_individual_req__";
		$past3DaysTime = strtotime("-3 days");

		// cleanup files
		$files = scandir($reqsDir);
		foreach ($files ?: [] as $file)
		{
			if ((substr($file, 0, strlen($reqsPrefix)) == $reqsPrefix) && ($ftime = filemtime($reqsDir . $file)) && ($past3DaysTime > $ftime))
			{
				unlink($reqsDir . $file);
			}
		}

		try 
		{
			$_qp = ["TourOperator" => $this->TourOperatorRecord->getId()];
			$_qp = array_merge($binds, $_qp);

			$ids = [];
			if ($binds["__fromBoxes"])
			{
				$boxes = \QQuery("IndividualBoxes.{City WHERE City.TourOperator.Id=?}", $this->TourOperatorRecord->getId())->IndividualBoxes;
				foreach ($boxes ?: [] as $box)
				{
					if (!$box->City)
						continue;
					$ids[$box->City->getId()] = $box->City->getId();
				}
				if (count($ids) === 0)
					$ids[] = 0;
			}

			$existingTransports = [];
			$cachedTransports = [];
			if (($_MERGE_IND_TRNSP = $binds['MERGE_TRANSP']))
			{
				$_etransp = QQuery("IndividualTransports.{"
					. "To.City.{"
						. "Name, "
						. "InTourOperatorId, "
						. "IsMaster, "
						. "Master.{"
							. "IsMaster, "
							. "County.IsMaster"
						. "}, "
						. "County.{"
							. "IsMaster, "
							. "Master.IsMaster"
						. "}"
					. "},"
				. "Content, Edited WHERE TourOperator.Id=?}", 
					[$this->TourOperatorRecord->getId()])->IndividualTransports;
				foreach ($_etransp ?: [] as $_trnsp)
				{
					if (!$_trnsp->To || !$_trnsp->To->City)
						continue;
					$existingTransports[$_trnsp->To->City->getId()] = $cachedTransports[$_trnsp->To->City->getId()] = $_trnsp;
				}
			}

			if ($ids && (count($ids) > 0))
				$_qp["Cities"] = [$ids];

			$cities = \QQuery("Cities.{"
				. "InTourOperatorId, "
				. "Name, "
				. "County, "
				. "Country.{"
					. "Name, "
					. "InTourOperatorsIds.{"
						. "Identifier, "
						. "TourOperator "
						. "WHERE 1 "
						. "??TourOperator?<AND[TourOperator.Id=?]"
					. "}"
				. "} "
				. "WHERE 1 "
					. "??NotExecuted?<AND[ISNULL(HotelsCount)]"
					. "??WithHotels?<AND[HotelsCount>0]"
					. "??NotExecutedOrWithoutHotels?<AND[(ISNULL(HotelsCount) OR HotelsCount=0)]"
					. "??TourOperator?<AND[TourOperator.Id=?]"
					. "??Country?<AND[Country.Id=?]"
					. "??CountryCode?<AND[Country.Code=?]"
					. "??Countries?<AND[Country.Id IN (?)]"
					. "??County?<AND[County.Id=?]"
					. "??Counties?<AND[County.Id IN (?)]"
					. "??City?<AND[Id=?]"
					. "??Cities?<AND[Id IN (?)]"
					. "??CitiesTopsIdsIn?<AND[InTourOperatorId IN (?)]"
					. "??Id?<AND[Id=?]"
			. "}", $_qp)->Cities;

			$month = (int)date("n");
			$year = (int)date("Y");
			$day = (int)date("d");
			$nextYear = date("Y", strtotime("+ 1 year"));
	
			$reqs = [];

			// settings
			if (!$binds['__months'])
			{
				$__months = [];
				$_nm = (int)($binds['__nmonths'] ?: 12);
				$_months_limit = ($month + $_nm);
				for ($i = $month; $i < $_months_limit; $i++)
				{
					$m = ($i > 12) ? ($i % 12) : $i;
					$__months[] = $m;
				}
			}
			else
				$__months = $binds['__months'];

			$__days = $binds['__days'] ?: [(int)date("d", strtotime("+ 1 day"))];
			$__periods = $binds['__periods'] ?: [1];
			$__time = strtotime(date("Y-m-d"));

			foreach ($cities as $city)
			{
				if ((!$city->InTourOperatorId) || (!$city->Country) || (!($countryId = $city->Country->getTourOperatorIdentifier($this->TourOperatorRecord))))
					continue;

				$cityHasHotels = false;
				$_max_hotels_count = 0;
				foreach ($__months as $__month)
				{
					foreach ($__days as $__day)
					{
						$checkin = ((($__month <= $month) && ($day > $__day)) ? $nextYear : $year) . "-" . (($__month < 10) ? "0" : "") . $__month . "-".(($__day < 10) ? "0" : "").$__day;

						if ($__time > strtotime($checkin))
							continue;

						foreach ($__periods as $__period)
						{
							$checkout = date("Y-m-d", strtotime("+ {$__period} days", strtotime($checkin)));
							$params = [
								"TourOpCode" => $this->TourOperatorRecord->ApiContext,
								"CountryCode" => $countryId,
								"CityCode" => $city->InTourOperatorId,
								"PeriodOfStay" => [
									["CheckIn" => $checkin],
									["CheckOut" => $checkout]
								],
								"Rooms" => [
									["Room" => [
											"Code" => "DB",
											"NoAdults" => "2"
										]
									]
								]
							];

							$reqFile = $reqsDir . $reqsPrefix . $this->TourOperatorRecord->Handle . "__" . MD5(json_encode($params)) . ".html";
							if (($reqFExists = file_exists($reqFile)) && (!$binds["forceExec"]))
							{
								echo "<div style='color: red;'>SKIPPED</div>";
								continue;
							}

							ob_start();
							qvardump("REQUEST", $params);
							$str = ob_get_clean();
							file_put_contents($reqFile, $str);

							$reqs[] = $params;

							$requestHaveHotels = $this->saveHotels_ExecRequests($params, $_max_hotels_count, $reqFile);
							if ($requestHaveHotels)
								$cityHasHotels = true;
						}
					}
				}

				$city->setHotelsCount($_max_hotels_count);
				$city->save("HotelsCount");

				if ($_MERGE_IND_TRNSP)
				{
					$transport = $existingTransports[$city->getId()];
					if ($cityHasHotels)
					{
						if (!$transport)
						{
							$transport = new \Omi\TF\IndividualTransport();
							$transport->setContent(new \Omi\Cms\Content());
							$transport->Content->setActive(true);
							$transport->setFromTopAddedDate(date("Y-m-d H:i:s"));
						}

						$transport->setTourOperator($this->TourOperatorRecord);

						if (!$transport->To)
							$transport->setTo(new \Omi\Address());

						if (!$transport->To->City)
							$transport->To->setCity($city);

						if ($city->County)
							$transport->To->setCounty($city->County);
						if ($city->Country)
							$transport->To->setCountry($city->Country);
					}
					// deactivate the transport
					else if ($transport)
					{
						#$transport->Content->setActive(false);
						#$transport->setFromTopRemoved(true);
						#$transport->setFromTopRemovedAt(date("Y-m-d H:i:s"));
					}

					if ($transport)
					{
						$appData = \QApp::NewData();
						$appData->IndividualTransports = new \QModelArray();
						$appData->IndividualTransports[] = $transport;

						// save the transport after city was processed
						$appData->save("IndividualTransports.{"
							. "FromTopAddedDate, "
							. "FromTopRemoved, "
							. "FromTopRemovedAt, "
							. "TourOperator,"
							. "Content.Active,"
							. "To.{"
								. "City,"
								. "County,"
								. "Country"
							. "}"
						. "}");
					}

					
				}
			}
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		finally 
		{
			$lock->unlock();
		}
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers_forIndividualSync(array $filter = null)
	{
		$toSaveHotelsProcesses = [];
		$alreadyProcessed_all = true;
		$err = null;
		$serviceType = $filter["serviceTypes"];
		if ($serviceType && isset($serviceType["hotel"]))
		{
			$t1 = microtime(true);
			$params = [
				"TourOpCode" => $this->TourOperatorRecord->ApiContext,
				"CountryCode" => $filter["countryId"],
				"CityCode" => $filter["cityId"],
				"PeriodOfStay" => [
					["CheckIn" => $filter["checkIn"]],
					["CheckOut" => $filter["checkOut"]]
				],
				"Rooms" => [
					["Room" => [
							"Code" => "DB",
							"NoAdults" => "2"
						]
					]
				]
			];
			$force = $filter["force"];

			// get response data
			$callMethod = "getHotelPriceRequest";
			$callKeyIdf = md5(json_encode($params));
			$callParams = $params;

			#if ((!$params["ProductCode"]) && isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
			if (isset(static::$Config[$this->TourOperatorRecord->Handle]['skip_code_filtering_on_search']))
				unset($callParams['TourOpCode']);
			
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
			
			if (!$alreadyProcessed)
				$alreadyProcessed_all = false;

			if ((!$return) || ($alreadyProcessed && (!$force)))
			{
				/*
				if ($this->TrackReport)
				{
					if (!$return)
						$this->TrackReport->_noResponse = true;
					if ($alreadyProcessed)
						$this->TrackReport->_responseNotChanged = true;
				}
				echo $alreadyProcessed ? "Nothing changed from last request!" : "No return";
				return;
				*/
			}

			$respData = static::GetResponseData($return, "getHotelPriceResponse");

			$ret = null;

			if ($respData && $respData["Hotel"])
			{
				if (isset($respData["Hotel"]["Product"]))
					$respData["Hotel"] = [$respData["Hotel"]];
				$cities = [];
				$counties = [];
				$countries = [];
				foreach ($respData["Hotel"] ?: [] as $hotelData)
				{
					$hotel = $hotelData["Product"];
					$offers = isset($hotelData["Offers"]["Offer"]) ? $hotelData["Offers"]["Offer"] : null;
					if ($offers && isset($offers["OfferType"]))
						$offers = [$offers];
					$retHotel = new \stdClass();
					$retHotel->Id = $hotel["ProductCode"];
					$retHotel->Code = $hotel["ProductCode"];
					$retHotel->ResellerCode = $hotel["TourOpCode"];
					$retHotel->Name = $hotel["ProductName"];
					$retHotel->Stars = $hotel["ProductCategory"];
					$retHotel->Address = new \stdClass();
					$retHotel->Address->City = $cities[$hotel["CityCode"]] ?: ($cities[$hotel["CityCode"]] = new \stdClass());
					$retHotel->Address->City->Id = $hotel["CityCode"];
					$retHotel->Address->City->Name = $hotel["CityName"];
					if ($hotel["ZoneCode"])
					{
						$retHotel->Address->City->County = $counties[$hotel["ZoneCode"]] ?: ($counties[$hotel["ZoneCode"]] = new \stdClass());
						$retHotel->Address->City->County->Id = $hotel["ZoneCode"];
						$retHotel->Address->City->County->Name = $hotel["ZoneName"];
					}
					$retHotel->Address->City->Country = $countries[$hotel["CountryCode"]] ?: ($countries[$hotel["CountryCode"]] = new \stdClass());
					$retHotel->Address->City->Country->Code = $hotel["CountryCode"];
					$retHotel->Address->Latitude = $hotel["Latitude"];
					$retHotel->Address->Longitude = $hotel["Longitude"];
					$retHotel->Offers = [];

					foreach ($offers ?: [] as $off)
					{
						$rooms = $off["BookingRoomTypes"]["Room"];
						if ($rooms && isset($rooms["@attributes"]))
							$rooms = [$rooms];
						
						$roomF_Name = "";
						$r_pos = 0;
						foreach ($rooms ?: [] as $room)
						{
							#$roomAttrs = $room["@attributes"];
							unset($room["@attributes"]);
							$roomName = trim(reset($room));
							$roomF_Name .= (($r_pos > 0) ? ", " : "") . $roomName;
							$r_pos++;
						}

						#$availabilityAttrs = isset($off["Availability"]["@attributes"]) ? $off["Availability"]["@attributes"] : null;
						unset($off["Availability"]["@attributes"]);
						$availability = $off["Availability"] ? trim(reset($off["Availability"])) : null;
						
						$checkIn = isset($off["PeriodOfStay"]["CheckIn"]) ? $off["PeriodOfStay"]["CheckIn"] : null;
						$checkOut = isset($off["PeriodOfStay"]["CheckOut"]) ? $off["PeriodOfStay"]["CheckOut"] : null;

						$meals = $off["Meals"]["Meal"];
						if ($meals && isset($meals["@attributes"]))
							$meals = [$meals];

						$mealF_Name = "";
						$m_pos = 0;
						$hasMeal = false;
						foreach ($meals ?: [] as $meal)
						{
							#$mealAttrs = $meal["@attributes"];
							unset($meal["@attributes"]);
							$mealTitle = trim(reset($meal));
							$mealF_Name .= (($m_pos > 0) ? ", " : "") . $mealTitle;
							$hasMeal = true;
							$m_pos++;
						}

						$offer = new \stdClass();
						$offIndx = $this->getHotelOffer_Code($retHotel, $off);

						$offer = $retHotel->Offers[$offIndx] ?: ($retHotel->Offers[$offIndx] = new \stdClass());

						// room
						$roomType = new \stdClass();
						$roomType->Id = md5(trim($roomF_Name));
						$roomType->Title = $roomF_Name;

						// room
						$roomMerch = new \stdClass();
						$roomMerch->Title = $roomF_Name;
						
						$roomMerch->Type = $roomType;
						$roomMerch->Code = $roomType->Id;
						$roomMerch->Name = $roomF_Name;

						$roomItm = new \stdClass();
						$roomItm->Merch = $roomMerch;
						$roomItm->Id = $roomType->Id;

						//required for indexing
						$roomItm->Code = $roomType->Id;
						$roomItm->CheckinAfter = $checkIn;
						$roomItm->CheckinBefore = $checkOut;
						$roomItm->Currency = $offer->Currency;
						$roomItm->Quantity = 1;

						// set net price
						$roomItm->Net = $offer->Net;

						// Q: there is also a HotelPrice
						$roomItm->InitialPrice = $offer->InitialPrice;

						// for identify purpose
						$offer->Availability = $roomItm->Availability = 
							($availability === "Immediate") ? "yes"  : (($availability === "StopSales") ? "no" : (($availability === "OnRequest") ? "ask" : null));

						if (!$offer->Rooms)
							$offer->Rooms = [];
						$offer->Rooms[] = $roomItm;

						// add items to offer
						$offer->Item = $roomItm;

						if ($hasMeal)
						{
							$_meal_info = null;
							$meal_title_length = defined('MEAL_TITLE_LENGTH') ? MEAL_TITLE_LENGTH : 32;
							if (mb_strlen($mealF_Name) > $meal_title_length)
							{
								$_meal_info = $mealF_Name;
								$mealF_Name = trim(mb_substr($mealF_Name, 0, $meal_title_length));
							}

							// board type
							$boardType = new \stdClass();
							$boardType->Title = $mealF_Name;
							$boardType->Id = md5($mealF_Name);

							// board merch
							$boardMerch			= new \stdClass();
							$boardMerch->Title	= $boardType->Title;
							$boardMerch->Type	= $boardType;
							

							// board item
							$boardItm				= new \stdClass();
							if ($_meal_info && strlen($_meal_info))
								$boardItm->InfoDescription = $_meal_info;
							$boardItm->Merch		= $boardMerch;
							$boardItm->Currency		= $offer->Currency;
							$boardItm->Quantity		= 1;
							$boardItm->UnitPrice	= 0;
							$boardItm->Gross		= 0;
							$boardItm->Net			= 0;
							$boardItm->InitialPrice = 0;

							// for identify purpose
							$boardItm->Id = $boardMerch->Id;
							$offer->MealItem = $boardItm;
						}					
					}

					$ret[] = $retHotel;
				}
			}
			$toSaveHotelsProcesses = [[$callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1)]];
		}

		//$callRequest, $callResponse, $callTopRequestTiming
		return [$ret, $err, $alreadyProcessed_all, $toSaveHotelsProcesses];
	}

	public function api_getHotelDetails_forIndividualSync(array $filter = null)
	{
		return $this->getHotelInfo_NEW($filter);
	}
	
	/**
	 * Returns the hotel populated with data
	 * 
	 * @param array $hotelDescription
	 * @param array $objs
	 * @param boolean $force
	 * @return \Omi\Travel\Merch\Hotel
	 */
	private function getHotel($hotelDescription, &$objs = null, $force = false, $skipQuery = false, $hotelObj = null, $changeGeo = false)
	{
		if (!$objs)
			$objs = array();

		if (empty($hotelDescription["ProductCode"]))
		{
			return null;
			//throw new \Exception("Hotel Code not provided!");
		}

		if (!$skipQuery)
		{
			$useEntity = $this->getEntityForHotel();
			// sanitize selector
			$useEntity = q_SanitizeSelector("Hotels", $useEntity);
			$hotelObj = $this->FindExistingItem("Hotels", $hotelDescription["ProductCode"], $useEntity);
		}

		$hasHotel = $hotelObj;
		$hotelChanged = false;

		$doDebug = false;
		$debugMessageShown = false;

		// repopulate hotel details here
		if (!$hotelObj)
		{
			$hotelObj = $objs[$hotelDescription["ProductCode"]]["\Omi\Travel\Merch\Hotel"] ?: 
				($objs[$hotelDescription["ProductCode"]]["\Omi\Travel\Merch\Hotel"] = new \Omi\Travel\Merch\Hotel());
			
			$hotelObj->Code = $hotelDescription["ProductCode"];
			$hotelObj->setFromTopAddedDate(date("Y-m-d H:i:s"));
			$hotelChanged = true;
			if ($doDebug)
			{
				echo "<div>Hotelul {$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]} este nou</div>";
				$debugMessageShown = true;
			}
		}

		$hotelObj->setResellerCode($hotelDescription["TourOpCode"]);
		$hotelObj->setTourOperator($this->TourOperatorRecord);
		$hotelObj->setInTourOperatorId($hotelDescription["ProductCode"]);

		if (($prodName = trim($hotelDescription["ProductName"])))
		{
			if ($prodName != $hotelObj->Name)
			{
				$iname = $hotelObj->Name;
				$hotelObj->Name = $prodName;
				$hotelChanged = true;
				if ($doDebug && (!$debugMessageShown))
				{
					echo "<div>Numele a fost schimbat din {$iname} in {$prodName} pentru hotelul " 
						. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
					$debugMessageShown = true;
				}
			}
		}

		if ($hotelDescription["ProductCategory"] && is_numeric($hotelDescription["ProductCategory"]))
		{		
			$starsNo = floor($hotelDescription["ProductCategory"]);
			if ($hotelObj->Stars != $starsNo)
			{
				$istars = $hotelObj->Stars;
				$hotelObj->Stars = $starsNo;
				$hotelChanged = true;
				if ($doDebug && (!$debugMessageShown))
				{
					echo "<div>Numarul de stele a fost schimbat din {$istars} in {$starsNo} pentru hotelul " 
						. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
					$debugMessageShown = true;
				}
			}
		}

		if (!$hotelObj->Content)
			$hotelObj->Content = new \Omi\Cms\Content();

		$hotelObj->Content->Title = $hotelObj->Name ? $hotelObj->Name : "Hotel";

		/*
		if (is_scalar($hotelDescription["Description"]))
		{
			//qvardump($hotelDescription["Description"]);
			$hotelObj->Content->ShortDescription = htmlspecialchars_decode($hotelDescription["Description"], ENT_QUOTES);
			$hotelObj->Content->ShortDescription = preg_replace("/\\r\\n/", "<br/>", $hotelObj->Content->ShortDescription);
		}

		if (is_scalar($hotelDescription["DescriptionDet"]))
		{
			$hotelObj->Content->Content = htmlspecialchars_decode($hotelDescription["DescriptionDet"], ENT_QUOTES);
			$hotelObj->Content->Content = preg_replace("/\\r\\n/", "<br/>", $hotelObj->Content->Content);	
		}
		*/

		$descrDet = null;
		if (isset($hotelDescription["DescriptionDet"]) && (!empty($hotelDescription["DescriptionDet"])))
		{
			$descrDet = trim(htmlspecialchars_decode($hotelDescription["DescriptionDet"]));
			if ($descrDet === '<br>')
				$descrDet = null;
		}

		$description = (($descrDet !== null) && is_scalar($descrDet)) ? $hotelDescription["DescriptionDet"] : 
			((!empty($hotelDescription["Description"]) && is_scalar($hotelDescription["Description"])) ? $hotelDescription["Description"] : "");

		$useContent = $description ? preg_replace("/\\r\\n/", "<br/>", htmlspecialchars_decode($description, ENT_QUOTES)) : "";

		if ($hotelObj->Content->Content != $useContent)
		{
			$hotelObj->Content->setContent($useContent);
			$hotelChanged = true;
			if ($doDebug && (!$debugMessageShown))
			{
				echo "<div>Content-ul a fost schimbat pentru hotelul " 
					. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
				$debugMessageShown = true;
			}
		}

		if ($hasHotel && (!$force))
			return [$hotelObj, $hotelChanged];

		$hotelCountry = null;
		$hotelCounty = null;
		$hotelCity = null;

		// load country for the hotel
		if ((!empty($hotelDescription["CountryCode"])) && (!empty($hotelDescription["CountryName"])))
		{
			$hotelCountry = $this->FindExistingItem("Countries", $hotelDescription["CountryCode"], "Code, Alias, Name, InTourOperatorsIds.{TourOperator, Identifier}");
			if (!$hotelCountry)
				throw new \Exception("Country not found for hotel");
		}

		// load county for hotel
		if ((!empty($hotelDescription["ZoneCode"])) && (!empty($hotelDescription["ZoneName"])))
		{
			$hotelCounty = $this->getCountyObj($objs, $hotelDescription["ZoneCode"], $hotelDescription["ZoneName"], $hotelDescription["ZoneCode"]);
			if ($hotelCountry)
				$hotelCounty->setCounty($hotelCountry);

			if (!$hotelCounty->getId())
				$hotelCounty = $this->saveCounty($hotelCounty);
		}

		if ((!empty($hotelDescription["CityCode"])) && (!empty($hotelDescription["CityName"])))
		{
			$hotelCity = $this->getCityObj($objs, $hotelDescription["CityCode"], $hotelDescription["CityName"], $hotelDescription["CityCode"]);

			if ($hotelCountry)
				$hotelCity->setCountry($hotelCountry);

			if ($hotelCounty && $changeGeo)
				$hotelCity->setCounty($hotelCounty);

			if (!$hotelCity->getId())
				$hotelCity = $this->saveCity($hotelCity);
		}

		if (!$hotelObj->Address)
			$hotelObj->setAddress(new \Omi\Address());

		if ($hotelCity)
		{
			if ($changeGeo || (!$hotelObj->Address->City))
			{
				if (
						(($hotelObj->Address->City->Id ?? null) != ($hotelCity->Id ?? null)) || 
						(($hotelObj->Address->Country->Id ?? null) != ($hotelCountry->Id ?? null)) || 
						(($hotelObj->Address->County->Id ?? null) != ($hotelCounty->Id ?? null)) )
				{
					$hotelChanged = true;
				}
				
				$hotelObj->Address->setCity($hotelCity);
				$hotelCity->setCountry($hotelCountry);
				if ($hotelCounty && $changeGeo)
					$hotelCity->setCounty($hotelCounty);
			}
		}
		else
			throw new \Exception("City not provided for hotel {$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}");

		// we don't need 
		/*
		if ($hotelCounty)
		{
			$hotelObj->Address->setCounty($hotelCounty);
			$hotelCounty->setCountry($hotelCountry);
		}
		if ($hotelCountry)
			$hotelObj->Address->setCountry($hotelCountry);
		*/

		if (!empty($hotelDescription["Latitude"]))
		{
			$hotelLatitude = \Omi\Address::GetNormalizedCoordinateValue($hotelDescription["Latitude"]);
			if ($hotelObj->Address->Latitude != $hotelLatitude)
			{
				$ilatitude = $hotelObj->Address->Latitude;
				$hotelObj->Address->setLatitude($hotelLatitude);
				$hotelChanged = true;
				if ($doDebug && (!$debugMessageShown))
				{
					echo "<div>Latitudinea s-a schimbat din {$ilatitude} in {$hotelLatitude} pentru hotelul " 
						. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
					$debugMessageShown = true;
				}
			}
		}

		if (!empty($hotelDescription["Longitude"]))
		{
			$hotelLongitude = \Omi\Address::GetNormalizedCoordinateValue($hotelDescription["Longitude"]);
			if ($hotelObj->Address->Longitude != $hotelLongitude)
			{
				$ilongitude = $hotelObj->Address->Longitude;
				$hotelObj->Address->setLongitude($hotelLongitude);
				$hotelChanged = true;
				if ($doDebug && (!$debugMessageShown))
				{
					echo "<div>Longitudinea s-a schimbat din {$ilongitude} in {$hotelLongitude} pentru hotelul " 
						. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
					$debugMessageShown = true;
				}
			}
		}

		$images = ($hotelDescription["Pictures"] && $hotelDescription["Pictures"]["Picture"]) ? $hotelDescription["Pictures"]["Picture"] : null;

		if (!$hotelObj->Content->ImageGallery)
			$hotelObj->Content->ImageGallery = new \Omi\Cms\Gallery();

		if (!$hotelObj->Content->ImageGallery->Items)
			$hotelObj->Content->ImageGallery->Items = new \QModelArray();

		$existingImages = [];
		foreach ($hotelObj->Content->ImageGallery->Items as $k => $itm)
		{
			if ((!$itm->RemoteUrl) || isset($existingImages[$itm->RemoteUrl]))
			{
				$itm->_toRM = true;
				$hotelObj->Content->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $k);
				$hotelChanged = true;
				if ($doDebug && (!$debugMessageShown))
				{
					echo "<div>Imagine marcata pentru stergere [{$itm->getId()}] in hotelul " 
						. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
					$debugMessageShown = true;
				}
				continue;
			}
			$existingImages[$itm->RemoteUrl] = [$k, $itm];
		}

		$processedImages = [];
		if ($images)
		{
			if (is_string($images) || (is_array($images) && $images["@attributes"]))
				$images = [$images];
			
			foreach ($images as $image)
			{
				$imgAttrs = null;
				if (is_array($image))
				{
					if ($image["@attributes"])
					{
						$imgAttrs = $image["@attributes"];
						unset($image["@attributes"]);
					}

					$image = reset($image);
				}

				// we have the image on our sever
				if (!($image = trim($image)))
					continue;

				if ($existingImages[$image])
				{
					list($_kim, $img) = $existingImages[$image];
				}
				else	
				{
					$img = new \Omi\Cms\GalleryItem();
					$img->setFromTopAddedDate(date("Y-m-d H:i:s"));
					$hotelChanged = true;
					if ($doDebug && (!$debugMessageShown))
					{
						echo "<div>Imagine noua [{$image}] in hotelul " 
							. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
						$debugMessageShown = true;
					}
				}

				// pull tour operator image
				$imagePulled = $img->setupTourOperatorImage($image, $this->TourOperatorRecord, $hotelObj, md5($image), IMAGES_URL, IMAGES_REL_PATH,
					$imgAttrs ? $imgAttrs['Name'] : null);
				
					// if the image is not pulled from server then don't save it
				if (!$imagePulled)
					continue;

				if (!$img->getId())
					$hotelObj->Content->ImageGallery->Items[] = $img;

				$processedImages[$image] = true;
			}
		}

		foreach ($existingImages as $imgUrl => $itmData)
		{
			if (isset($processedImages[$imgUrl]))
				continue;

			list($key, $itm) = $itmData;
			$itm->_toRM = true;
			$hotelObj->Content->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $key);
			$hotelChanged = true;
			if ($doDebug && (!$debugMessageShown))
			{
				echo "<div>Imagine marcata pentru stergere [{$itm->getId()}] in hotelul " 
					. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
				$debugMessageShown = true;
			}
		}

		if (!$hotelObj->getId())
		{
			$hotelIsActive = true;
			if (function_exists("q_getTopHotelActiveStatus"))
				$hotelIsActive = q_getTopHotelActiveStatus($hotelObj);

			if ($hotelObj->Active != $hotelIsActive)
			{
				$iactive = $hotelObj->Active;
				$hotelObj->setActive($hotelIsActive);
				$hotelChanged = true;
				if ($doDebug && (!$debugMessageShown))
				{
					echo "<div>Flag-ul de active s-a schimbat din " . ($iactive ? "true" : "false") . " in " . ($hotelIsActive ? "true" : "false") . " pentru hotelul " 
						. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
					$debugMessageShown = true;
				}
			}

			if ($hotelObj->Content)
			{
				if ($hotelObj->Content->Active != $hotelIsActive)
				{
					$hotelObj->Content->setActive($hotelIsActive);
					$hotelChanged = true;
					if ($doDebug && (!$debugMessageShown))
					{
						echo "<div>Flag-ul de active s-a schimbat din " . ($iactive ? "true" : "false") . " in " . ($hotelIsActive ? "true" : "false") . " pentru hotelul " 
							. "{$hotelDescription["ProductCode"]}|{$hotelDescription["ProductName"]}</div>";
						$debugMessageShown = true;
					}
				}
			}
		}

		return [$hotelObj, $hotelChanged];
	}
	
	private function getEntityForHotel()
	{
		return "Name,"
			. "MTime,"
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
			. "InCategoriesItems.{Category.{Alias, Name, Active, HideTravelItems}, Type}, "
			. "Facilities.Name,"
			. "HasIndividualOffers,"
			. "HasCharterOffers,"
			. "HasCityBreakActiveOffers, "
			. "HasPlaneCityBreakActiveOffers, "
			. "HasBusCityBreakActiveOffers, "

			. "HasChartersActiveOffers, "
			. "HasPlaneChartersActiveOffers, "
			. "HasBusChartersActiveOffers, "

			. "LiveVisitors, "
			. "LastReservationDate,	"

			. "HasIndividualActiveOffers, "
			. "HasHotelsActiveOffers, "
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
						. "Alt,"
						. "Path, "
						. "Type, "
						. "ExternalUrl, "
						. "RemoteUrl, "
						. "Base64Data, "
						. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
						. "InTourOperatorId, "
						. "Order, "
						. "Alt"
					. "}"
				. "}"
			. "},"
			. "Address.{"
				. "City.{"
					. "Code, "
					. "Name, "
					. "County.{"
						. "Code, "
						. "Name, "
						. "Country.{"
							. "Alias,"
							. "Code, "
							. "Name "
						. "}"
					. "}, "
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "County.{"
					. "Code, "
					. "Name, "
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "Country.{"
					. "Alias,"
					. "Code, "
					. "Name"
				. "}, "
				. "Latitude, "
				. "Longitude"
			. "}";
	}
	/**
	 * Returns an array with all offers for hotels
	 * 
	 * @param array $hotels
	 * @param array $objs
	 * @return array
	 * @throws \Exception
	 */
	private function getHotelsOffers($hotels, &$objs = null, $byHotel = false, $charters = false, $params = null, $initialParams = null)
	{
		if (!$hotels || (count($hotels) === 0))
			return array();

		if (!$objs)
			$objs = array();

		//load cache data here
		$this->loadCachedCompanies($objs);

		$fh = reset($hotels);

		if ($fh && $fh["Product"] && ($_topcode = $fh["Product"]["TourOpCode"]))
			$facilities = $this->getHotelsFacilities(["TourOpCode" => $_topcode]);

		$ret = array();
		$packagesIds = array();

		if (isset($hotels["Product"]))
			$hotels = array($hotels);

		$codes = [];
		$countriesCodes = [];
		
		//qvardump($hotels);

		foreach ($hotels as $hotel)
		{
			if (!$hotel["Product"]["ProductCode"])
				continue;
			
			//echo "[TourOperator: {$this->TourOperatorRecord->Handle}]".$hotel["Product"]["ProductName"]."<br/>";

			$codes[] = $hotel["Product"]["ProductCode"];
			if (!$hotel["Product"]["CountryCode"])
				continue;
			$countriesCodes[$hotel["Product"]["CountryCode"]] = $hotel["Product"]["CountryCode"];
		}

		$this->FindExistingItem("Countries", $countriesCodes, "Code, Alias, Name, InTourOperatorsIds.{TourOperator, Identifier}");
		
		$availabilityIndxes = [
			"yes" => 1,
			"ask" => 2,
			"no" => 3
		];
		$useEntity =  "Code, "
			. "Name,"
			. "Master,"
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
			. "HasIndividualOffers, "
			. "ShowOnlyInCategory, "
			. "InCategoriesItems.{Category.{Alias, Name, Active, HideTravelItems}, Type}, "
			. "HasCharterOffers,"
			. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
				
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
						. "ExternalUrl, "
						. "RemoteUrl, "
						. "Base64Data, "
						. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
						. "InTourOperatorId, "
						. "Order, "
						. "Alt"
					. "}"
				. "}"
			. "},"
			. "Address.{"
				. "City.{"
					. "Code, "
					. "InTourOperatorId, "
					. "Name, "
					. "IsMaster,"
					. "Master.{"
						. "Name,"
						. "IsMaster,"
						. "County.{"
							. "IsMaster,"
							. "Name,"
							. "Country.{Code, Alias, Name}"
						. "},"
						. "Country.{Code, Alias, Name}"
					. "},"
					. "County.{"
						. "Code, "
						. "Name, "
						. "InTourOperatorId,"
						. "IsMaster,"
						. "Master.{"
							. "Name,"
							. "IsMaster,"
							. "Country.{Code, Alias, Name}"
						. "},"
						. "Country.{"
							. "Alias,"
							. "Code, "
							. "Name "
						. "}"
					. "}, "
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "County.{"
					. "InTourOperatorId,"
					. "Code, "
					. "Name, "
					. "IsMaster,"
					. "Master.{"
						. "Name,"
						. "IsMaster,"
						. "Country.{Code, Alias, Name}"
					. "},"
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name "
					. "}"
				. "}, "
				. "Country.{"
					. "Alias,"
					. "Code, "
					. "Name"
				. "}, "
				. "Latitude, "
				. "Longitude"
			. "}";

		$useEntity = q_SanitizeSelector("Hotels", $useEntity);
		// load hotels from cache
		$this->FindExistingItem("Hotels", $codes, $useEntity);

		$pos = 0;
		$offersCount = 0;

		$checkin = ($fps = reset($params["PeriodOfStay"])) ? $fps["CheckIn"] : null;
		$checkout = ($lps = end($params["PeriodOfStay"])) ? $lps["CheckOut"] : null;

		$reqParams = static::GetRequestParams($params);

		$toSaveHotelsWithChangedNameOrStars = [];

		
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
			$isHotelSearch = $initialParams['ProductCode'] ? true : false;
			$toPullHotelsFromTF = [];
			// go through hotels
			foreach ($hotels as $hotel)
			{				
				// do extra filtering by context - we may receive data from other tops
				if (!$hotel["Product"] || !$hotel["Product"]["TourOpCode"] || ($hotel["Product"]["TourOpCode"] !== $this->TourOperatorRecord->ApiContext))
					continue;

				// load hotel from database

				$hotelObj = $this->FindExistingItem("Hotels", $hotel["Product"]["ProductCode"], $useEntity);
				if ((!$hotelObj) || (!$hotelObj->getId()))
					$toPullHotelsFromTF[$hotel["Product"]["ProductCode"]] = $hotel["Product"]["ProductCode"];

				# if we have the hotel and it was just light synced then we must do a full sync
				if ($isHotelSearch && $hotelObj && $hotelObj->SyncStatus && $hotelObj->SyncStatus->LiteSynced && (!$hotelObj->SyncStatus->FullySynced))
				{
					$toPullHotelsFromTF[$hotel["Product"]["ProductCode"]] = $hotel["Product"]["ProductCode"];
				}
			}

			# DO THE SYNC
			if ($toPullHotelsFromTF && $this->TourOperatorRecord->Handle)
			{
				list($from_TF_hotels) = \Omi\Travel\Merch\Hotel::HotelSync_SyncFromTF($this->TourOperatorRecord->Handle, $toPullHotelsFromTF, $isHotelSearch);
				foreach ($from_TF_hotels ?: [] as $TF_Hotel)
				{
					if ($TF_Hotel->getId() && $TF_Hotel->InTourOperatorId)
						static::$_CacheData["DB::Hotels::{$this->TourOperatorRecord->getId()}::{$TF_Hotel->InTourOperatorId}"] = $TF_Hotel;
				}
			}
		}

		

		// app data
		$appData = \QApp::NewData();
		$appData->setHotels(new \QModelArray());
		
		$useNewFacilitiesFunctionality = (defined('USE_NEW_FACILITIES') && USE_NEW_FACILITIES);
		
		# get other touroperators code allowed
		$allowed_touroperators_code = static::$Config[$this->TourOperatorRecord->Handle]['allowed_touroperators_code'];
		
		// go through hotels
		foreach ($hotels as $hotel)
		{
			# kip allowed, but not in array
			$top_allowed = false;
			if ($allowed_touroperators_code && in_array($hotel["Product"]["TourOpCode"], $allowed_touroperators_code))
				$top_allowed = true;
			
			# do extra filtering by context - we may receive data from other tops
			if ((!$hotel["Product"] || !$hotel["Product"]["TourOpCode"] || (($hotel["Product"]["TourOpCode"] !== $this->TourOperatorRecord->ApiContext))) && !$top_allowed)
				continue;
			
			// load hotel from database
			$hotelObj = $appData->Hotels[$hotel["Product"]["ProductCode"]] ?: $this->FindExistingItem("Hotels", $hotel["Product"]["ProductCode"], $useEntity);

			// skip not active hotels
			if ($hotelObj && $hotelObj->getId() && (!$hotelObj->Active))
				continue;

			// move it when saving the hotel - the fix
			if ($hotelObj->Content && $hotelObj->Content->Content)
				$hotelObj->Content->Content = utf8_decode($hotelObj->Content->Content);

			$hotelIsNew = false;
			if (!$hotelObj)
			{
				$hotelIsNew = true;
				try {

					// searches in eurosite - 
					$hotelObj = $this->getHotelInfo([
						"CountryCode" => $hotel["Product"]["CountryCode"], 
						"CityCode" => $hotel["Product"]["CityCode"],
						"ProductCode" => $hotel["Product"]["ProductCode"],
						"TourOpCode" => $hotel["Product"]["TourOpCode"],
						"__cs__" => $this->TourOperatorRecord->Handle,
						//"Force" => true
					]);

					if (!$hotelObj)
						continue;

					$appData->Hotels[$hotel["Product"]["ProductCode"]] = $hotelObj;

					if ($hotelObj->Address)
					{
						// let's make sure that we have the county here
						if ((!$hotelObj->Address->County) && 
							$hotel["Product"] && !empty($hotel["Product"]["ZoneName"]) && !empty($hotel["Product"]["ZoneCode"]))
						{
							$countyObj = $this->getCountyObj($objs, $hotel["Product"]["ZoneCode"], $hotel["Product"]["ZoneName"], $hotel["Product"]["ZoneCode"]);
							if ($hotelObj->Address->Country)
								$countyObj->setCountry($hotelObj->Address->Country);
							if ((!$charters) && $hotelObj->Address->City)
								$hotelObj->Address->City->setCounty($countyObj);
							$hotelObj->Address->setCounty($countyObj);
						}

						if (!empty($hotel["Longitude"]))
							$hotelObj->Address->setLongitude($hotel["Longitude"]);
						if (!empty($hotel["Latitude"]))
							$hotelObj->Address->setLatitude($hotel["Latitude"]);
					}
				}
				catch (\Exception $ex) {
					continue;
				}

				if ($charters)
				{
					// here we must pull charter cached transport and link the new hotel to leaving date and charter
					// this needs to be done in order to show the new hotel in step by step mode
				}
			}

			if ($hotel["Product"]["ProductName"] && ($toSetHotelName = trim($hotel["Product"]["ProductName"])) && ($hotelObj->Name != $toSetHotelName))
			{
				$hotelObj->Name = $toSetHotelName;
				if (!$hotelIsNew)
					$toSaveHotelsWithChangedNameOrStars[$hotelObj->getId()] = $hotelObj;
			}

			if ($hotel["Product"]["ProductCategory"] && is_numeric($hotel["Product"]["ProductCategory"]) && ($hotelObj->Stars != $hotel["Product"]["ProductCategory"]))
			{
				$hotelObj->Stars = $hotel["Product"]["ProductCategory"];
				if (!$hotelIsNew)
					$toSaveHotelsWithChangedNameOrStars[$hotelObj->getId()] = $hotelObj;
			}

			$hotelObj->setResellerCode($hotel["Product"]["TourOpCode"]);
			$hotelObj->setTourOperator($this->TourOperatorRecord);

			$hotelObj->setInTourOperatorId($hotel["Product"]["ProductCode"]);

			if ($facilities && $facilities[$hotelObj->Code])
			{
				if (!$hotelObj->Facilities)
					$hotelObj->Facilities = new \QModelArray();

				$ef = [];
				foreach ($hotelObj->Facilities as $hf)
				{
					if (trim($hf->Name))
						$ef[$useNewFacilitiesFunctionality ? static::GetFacilityIdf($hf->Name) : $hf->Name] = $hf;
				}

				foreach ($facilities[$hotelObj->Code] ?: [] as $facility)
				{
					if (isset($ef[($useNewFacilitiesFunctionality ? static::GetFacilityIdf($facility->Name) : $facility->Name)]))
						continue;
					$hotelObj->Facilities[] = $facility;
				}
			}

			$hotelOffers = ($hotel["Offers"] && $hotel["Offers"]["Offer"]) ? $hotel["Offers"]["Offer"] : null;

			$supplier = $objs[$hotel["Product"]["TourOpCode"]]["\Omi\Company"] ?: ($objs[$hotel["Product"]["TourOpCode"]]["\Omi\Company"] = new \Omi\Company());
			$supplier->Code = $hotel["Product"]["TourOpCode"];

			if (!$hotelOffers || (count($hotelOffers) === 0))
				continue;

			if (isset($hotelOffers['@attributes']))
				$hotelOffers = array($hotelOffers);

			$chotels = array();
			if ($hotelOffers)
			{
				$hotelObj->Offers = new \QModelArray();
				$filteredOffers = [];
				foreach ($hotelOffers as $offer)
				{
					if (!$offer['PackageVariantId'])
						continue;
					
					// on individual
					if (!$charters)
					{
						if ((!($offCheckIn = ($offer["PeriodOfStay"] ? $offer["PeriodOfStay"]["CheckIn"] : null))) || 
							(!($offCheckOut = ($offer["PeriodOfStay"] ? $offer["PeriodOfStay"]["CheckOut"] : null))) || 
							(($offCheckIn != $checkin) || ($offCheckOut != $checkout)))
							continue;
					}
					
					if (false && $params["_req_off_"])
					{
						$offer_code = $this->getHotelOffer_Code($hotelObj, $offer);
						if ($offer_code !== $params["_req_off_"])
							continue;
					}

					$identifier = $offer['PackageId'] . "~" . $offer['PackageVariantId'];

					if (isset($packagesIds[$identifier]))						
						throw new \Exception("Offer for identifier [{$identifier}] already created!");

					$packagesIds[$identifier] = $identifier;
					$offerObj = $this->getHotelOffer($offer, $hotelObj, $charters, $objs, $params, $initialParams);
					if (!$offerObj)
						continue;

					$roomItm = $offerObj->getRoomItem();

					// if we don't have room item or the leaving dates are not the same with the search ones (only on individual) - skip offer
					if (!$roomItm || (!$charters && (($offerObj->getLeavingDate() != $checkin) || ($offerObj->getReturnDate() != $checkout))))
						continue;

					if (!isset($filteredOffers[$offerObj->Code]))
						$filteredOffers[$offerObj->Code] = [];

					$availabilityIndx = $availabilityIndxes[$offerObj->Item->Availability];

					if (!isset($filteredOffers[$offerObj->Code][$availabilityIndx]))
						$filteredOffers[$offerObj->Code][$availabilityIndx] = [];

					$filteredOffers[$offerObj->Code][$availabilityIndx][$offerObj->Price] = $offerObj;

					$offerObj->ReqParams = $reqParams;
				}

				foreach ($filteredOffers ?: [] as $offc => $offsByAv)
				{
					ksort($offsByAv);
					$filteredOffers[$offc] = $offsByAv;
					foreach ($offsByAv ?: [] as $availabilityIndx => $offsByPrice)
					{
						ksort($offsByPrice);
						$filteredOffers[$offc][$availabilityIndx] = $offsByPrice;	
					}
				}

				foreach ($filteredOffers ?: [] as $offc => $offsByAv)
				{
					if (!($fav = reset($offsByAv)) || (!($foffbp = reset($fav))))
						continue;

					if ($byHotel)
					{
						if (!$hotelObj->Offers)
							$hotelObj->Offers = new \QModelArray();

						if ($foffbp->isAvailable())
							$hotelObj->_has_available_offs = true;
						else if ($foffbp->isOnRequest())
							$hotelObj->_has_onreq_offs = true;

						$hotelObj->Offers[$foffbp->Code] = $foffbp;
						if (!isset($chotels[$hotelObj->Code]))
						{
							$chotels[$hotelObj->Code] = $hotelObj;
							$ret[] = $hotelObj;
						}
					}
					else	
						$ret[$foffbp->Code] = $foffbp;
				}
			}
			$pos++;
		}

		if ($byHotel && (count($ret) > 0))
		{
			//\Omi\TFuse\Api\TravelFuse::UpdateHotelsStatus($ret, $charters ? "charter" : "individual");
			\QApp::AddCallbackAfterResponseLast(function ($hotels, $type) {
				// sync hotels status
				\Omi\TFuse\Api\TravelFuse::UpdateHotelsStatus($hotels, $type);
			}, [$ret, $charters ? "charter" : "individual"]);
		}

		if (count($toSaveHotelsWithChangedNameOrStars))
			$this->saveInBatchHotels($toSaveHotelsWithChangedNameOrStars, "Name, Stars");

		if (count($appData->Hotels) === 0)
			return $ret;

		foreach ($appData->Hotels ?: [] as $hotel)
		{
			if (!($hotel->getId()))
			{
				$hotelIsActive = true;
				if (function_exists("q_getTopHotelActiveStatus"))
					$hotelIsActive = q_getTopHotelActiveStatus($hotel);
				$hotel->setActive($hotelIsActive);
			}
		}

		$this->saveInBatchHotels($appData->Hotels, "FromTopAddedDate, "
			. "MTime, "
			. "Code, "
			. "Name,"
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
				. "TourOperator.{Handle, Caption, Abbr}, "
				. "InTourOperatorId, "
				. "Alt"
			. "}, "
			. "Active, "
			. "ShortContent, "
			. "Stars,"
			. "HasIndividualOffers,"
			. "HasCharterOffers,"
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
						. "FromTopAddedDate, "
						. "Path, "
						. "Type, "
						. "ExternalUrl, "
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
					. "Code, "
					. "Name, "
					. "County.{"
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

		return $ret;
	}

	private function getHotelOffer_Code($hotelObj, $offer)
	{
		$offerRooms = ($offer["BookingRoomTypes"] && $offer["BookingRoomTypes"]["Room"]) ? $offer["BookingRoomTypes"]["Room"] : null;
		if (($offerRooms["@attributes"]))
			$offerRooms = [$offerRooms];

		$offerCode = $hotelObj->Code . "~";
		foreach ($offerRooms ?: [] as $offerRoom)
		{
			$offerRoomAttrs = ($orisarr = is_array($offerRoom)) ? $offerRoom["@attributes"] : null;
			$offerRoom = trim($orisarr ? $offerRoom[0] : $offerRoom);
			$offerCode .= $offerRoom . "~";
		}

		$offerCode .= (($offer['PeriodOfStay'] && $offer['PeriodOfStay']['CheckIn']) ? $offer['PeriodOfStay']['CheckIn'] : "") . "~" . 
			(($offer['PeriodOfStay'] && $offer['PeriodOfStay']['CheckOut']) ? $offer['PeriodOfStay']['CheckOut'] : "");

		// setup meal
		$meal = ($offer["Meals"] && $offer["Meals"]["Meal"]) ? $offer["Meals"]["Meal"] : null;
		if ($meal && is_array($meal))
		{
			if (isset($meal["@attributes"]))
				$meal = [$meal];
			$_meal = "";
			$__pm = 0;
			foreach ($meal as $_m)
			{
				if (is_scalar($_m))
					$_m = [$_m];
				
				unset($_m["@attributes"]);
				$_meal_caption = reset($_m);

				$_meal .= (($__pm > 0) ? ", " : "") . $_meal_caption;
				$__pm++;
			}
			$offerCode .= "~" . md5($_meal);
		}

		$offerCode .= "~" . md5($offer['OfferDescription']);
		$offerCode .= "~" . $offer['GrilaName'];

		return trim($offerCode);
	}
	
	/**
	 * 
	 * @param array $offer
	 * @param \Omi\Travel\Merch\Hotel $hotelObj
	 * @param array $objs
	 * @return \Omi\Travel\Offer\Offer
	 */
	private function getHotelOffer($offer, $hotelObj, $charters = false, &$objs = null, $params = null, $initialParams = null)
	{
		if (!$objs)
			$objs = array();
		
		//qvardump("OFFER", $this->TourOperatorRecord->Handle, $offer, $hotelObj);

		// load cached data
		//$this->loadCachedCurrencies($objs);
		$this->loadCachedMerchCategories($objs);
		#$this->loadCachedOffersCategories($objs);
		$this->loadCachedCompanies($objs);
		$this->loadCachedAirlines( $objs);
		$this->loadCachedMealTypes($objs);

		// determine checkIn and checkOut - they will be setup on offer code
		$checkIn = ($offer['PeriodOfStay'] && $offer['PeriodOfStay']['CheckIn']) ? $offer['PeriodOfStay']['CheckIn'] : null;
		$checkOut = ($offer['PeriodOfStay'] && $offer['PeriodOfStay']['CheckOut']) ? $offer['PeriodOfStay']['CheckOut'] : null;

		// rooms
		$offerRooms = ($offer["BookingRoomTypes"] && $offer["BookingRoomTypes"]["Room"]) ? $offer["BookingRoomTypes"]["Room"] : null;
		if (($offerRooms["@attributes"]))
			$offerRooms = [$offerRooms];

		$f_off_room = reset($offerRooms);

		// setup other services
		$services = ($offer['PriceDetails'] && $offer['PriceDetails']['Services'] && $offer['PriceDetails']['Services']['Service']) ? 
			$offer['PriceDetails']['Services']['Service'] : null;

		if ($services && isset($services["Code"]))
			$services = [$services];

		$servicesIdf = null;
		$__use_grila = false;
		$__grillaName = $offer["GrilaName"];
		if ($charters && static::$Config[$this->TourOperatorRecord->Handle]["use_services_in_charters_offs_indx"])
		{
			#$servicesCodes = [];
			#foreach ($services ?: [] as $srv)
			#	$servicesCodes[$srv["Type"] . "_" . $srv["Code"]] = $srv["Type"] . "_" . $srv["Code"];
			#$offerObjIndx .= implode("_", $servicesCodes);

			$useServicesInIdf = [];
			foreach ($services ?: [] as $srv)
			{
				if (isset($srv['Seats']))
					unset($srv['Seats']);
				$useServicesInIdf[] = $srv;
			}

			$servicesIdf = md5(json_encode($useServicesInIdf));
		}

		$__use_offer_info = false;
		$offer_info = "";
		$offer_info_indx = null;
		$charters_grilla_services_codes = static::$Config[$this->TourOperatorRecord->Handle]["charters_grilla_services_codes"];
		$charters_grilla_services_categories = static::$Config[$this->TourOperatorRecord->Handle]["charters_grilla_services_categories"];
		if ($charters && ($charters_grilla_services_codes || $charters_grilla_services_categories))
		{
			$__use_offer_info = true;
			$chartersGrilla = "";
			foreach ($services ?: [] as $serv)
			{
				if (($serv["Code"] && $charters_grilla_services_codes && in_array($serv["Code"], $charters_grilla_services_codes)) || 
					($serv["Type"] && $charters_grilla_services_categories && in_array($serv["Type"], $charters_grilla_services_categories)))
				{
					$__use_grila = true;
					$chartersGrilla .= ((strlen($chartersGrilla) > 0) ? " - " : "") . $serv["Name"];
				}
			}
			$offer_info = trim($chartersGrilla);
			$offer_info_indx = md5($offer_info);
		}

		//$offerIndx = $hotelObj->Code."~".$offer['PackageVariantId']."~";
		$offerIndx = $hotelObj->Code . "~";
		$offerIndxParts = ['hotel_code' => $hotelObj->Code];
		$rooms = array();
		$roomCode = null;
		$roomCode_for_INDX = "";
		$__room_price = 0;
		if ($__use_grila === false)
			$__use_grila = ((!$charters) || (isset(static::$Config[$this->TourOperatorRecord->Handle]["use_grila"])));

		foreach ($offerRooms ?: [] as $offerRoom)
		{
			$offerRoomAttrs = ($orisarr = is_array($offerRoom)) ? $offerRoom["@attributes"] : null;
			$offerRoom = trim($orisarr ? $offerRoom[0] : $offerRoom);
			$roomIndx = $hotelObj->Code . "~" . $offerRoom . "~" . ($__use_grila ? $__grillaName . "~" : "") . ($__use_offer_info ? $offer_info_indx : "");
			$roomObj = $objs[$roomIndx]["\Omi\Travel\Merch\Room"] ?: ($objs[$roomIndx]["\Omi\Travel\Merch\Room"] = new \Omi\Travel\Merch\Room());
			$roomObj->Title = $offerRoom;
			if ($offerRoomAttrs)
			{
				//$roomCode = ($offerRoomAttrs["Code"] && ($offerRoomAttrs["Code"] != 0)) ? $offerRoomAttrs["Code"] : $offerRoomAttrs["GCode"];
				$roomCode = $offerRoomAttrs["Code"];
				$roomCode_for_INDX .= $offerRoomAttrs["Code"];
				if ($offerRoomAttrs["Gross"])
					$__room_price = $offerRoomAttrs["Gross"];
			}
			$roomCode_for_INDX .= $offerRoom;
			$roomObj->Hotel = $hotelObj;
			$rooms[] = $roomObj;
			$offerIndx .= $offerRoom . "~";
			$offerIndxParts['offer_rooms'][] = $offerRoom;
		}

		$crooms = count($rooms);
		if ($crooms != 1)
			return null;

		$use_room = array_shift($rooms);

		$offerIndx .= ($checkIn ? $checkIn : "") . "~" . ($checkOut ? $checkOut : "");

		$offerIndxParts['check_in'] = $checkIn;
		$offerIndxParts['check_out'] = $checkOut;

		$offerType = $charters ? "\Omi\Travel\Offer\Charter" : "\Omi\Travel\Offer\Stay";

		// setup meal
		$meal = ($offer["Meals"] && $offer["Meals"]["Meal"]) ? $offer["Meals"]["Meal"] : null;
		//$meal = ($meal && is_array($meal)) ? $meal[0] : $meal;

		$mealItm = null;
		$_meal_props = null;
		if ($meal && is_array($meal))
		{	
			if (isset($meal["@attributes"]))
				$meal = [$meal];

			$_meal = "";
			$__pm = 0;
			$_meal_price = 0;
			foreach ($meal as $_m)
			{
				if (is_scalar($_m))
					$_m = [$_m];

				$_meal_props = $_m["@attributes"];
				
				$_meal_price += (float)$_meal_props["Gross"] ?: (float)$_meal_props["ServicePrice"];
				
				unset($_m["@attributes"]);
				$_meal_caption = reset($_m);
				
				$_meal .= (($__pm > 0) ? ", " : "") . $_meal_caption;
				$__pm++;
			}

			$offerIndx .= "~" . md5($_meal);
			$offerIndxParts['meals'][] = $_meal;

			$_meal_title = $_meal;
			$_meal_info = null;
			$meal_title_length = defined('MEAL_TITLE_LENGTH') ? MEAL_TITLE_LENGTH : 32;
			if (mb_strlen($_meal_title) > $meal_title_length)
			{
				$_meal_info = $_meal_title;
				$_meal_title = trim(mb_substr($_meal_title, 0, $meal_title_length));
			}

			$mealObj = new \Omi\Travel\Merch\Meal();
			$mealObj->Type = $objs[$_meal_title]["\Omi\Travel\Merch\MealType"] ?: ($objs[$_meal_title]["\Omi\Travel\Merch\MealType"] = new \Omi\Travel\Merch\MealType());
			$mealObj->Type->Title = $_meal_title;
			$mealObj->Title = $_meal_title;

			if (!$hotelObj->Meals)
				$hotelObj->Meals = new \QModelArray();
			$hotelObj->Meals[$_meal_title] = $mealObj;

			$mealItm = new \Omi\Comm\Offer\OfferItem();
			if ($_meal_info && strlen($_meal_info))
				$mealItm->InfoDescription = $_meal_info;

			$mealItm->UnitPrice = $_meal_price;
			$mealItm->Merch = $mealObj;
			$mealItm->Quantity = 1;
		}

		$offerIndx .= "~" . md5($offer['OfferDescription']);
		$offerIndxParts['off_descr'] = $offer['OfferDescription'];

		$offerIndx .= "~" . $offer['GrilaName'];
		$offerIndxParts['grilla'] = $offer['GrilaName'];

		// - we need to implement series id - but we need to do it correctly
		unset($offer["SeriesId"]);

		if ($offer["SeriesId"])
		{
			$offerIndx .= "~" . $offer["SeriesId"];
			$offerIndxParts['series'] = $offer['SeriesId'];
		}

		//$offerObj = $objs[$offerIndx][$offerType] ?: ($objs[$offerIndx][$offerType] = new $offerType());
		$offerObjIndx = $hotelObj->Code . "~" . $offer['PackageVariantId'] . "~";

		if ($servicesIdf)
		{
			$offerObjIndx .= $servicesIdf;
			$offerIndx .= $servicesIdf;
			$offerIndxParts['services'] = json_encode($services);
		}

		// offer obj
		$offerObj = $objs[$offerObjIndx][$offerType] ?: ($objs[$offerObjIndx][$offerType] = new $offerType());	


		// set currency
		if (!($currency = ($offer['@attributes'] && $offer['@attributes']['CurrencyCode']) ? $offer['@attributes']['CurrencyCode'] : null))
		{
			throw new \Exception("Offer currency not provided!");
		}

		// setup 
		if (!($offerObj->SuppliedCurrency = static::GetCurrencyByCode($currency)))
		{
			throw new \Exception("Undefined currency [{$currency}]!");
		}

		$offerObj->Code = trim($offerIndx);
		$offerObj->_code_parts = $offerIndxParts;
		$offerObj->Grila = $__grillaName;
		$offerObj->PackageId = $offer["PackageId"];
		$offerObj->PackageVariantId = $offer["PackageVariantId"];

		if ($offer["SeriesId"])
		{
			$offerObj->_series_id = $offer["SeriesId"];
			$offerObj->_series_name = $offer["SeriesName"];
		}

		$offerObj->Content = new \Omi\Cms\Content();
		$offerObj->Content->Content = $offer['OfferDescription'];

		if (!$offerObj->Item)
			$offerObj->Item = new \Omi\Travel\Offer\Room();

		// set the room code
		$offerObj->Item->setCode($roomCode);
		//$offerObj->Item->_INDX = $roomCode . "~" . ($__use_grila ? $offerObj->Grila : "") . ($offer["SeriesId"] ? "~" . $offer["SeriesId"] : "");
		$offerObj->Item->_INDX = $roomCode_for_INDX . "~" . ($__use_grila ? $offerObj->Grila . "~" : "") . ($__use_offer_info ? $offer_info_indx . "~" : "") . ($offer["SeriesId"] ? $offer["SeriesId"] : "");

		$offerObj->TourOperator = $this->TourOperatorRecord;
		$offerObj->ResellerCode = $hotelObj->ResellerCode;

		// setup first room as main item
		$offerObj->Item->Merch = $use_room;
		if ($offerObj->Item->Merch->Title === null)
			$offerObj->Item->Merch->Title = "";
		
		if ($offer['OfferDescription'])
		{
			if ($mealItm)
				$mealItm->InfoDescription = $offer['OfferDescription'];
			else
				$offerObj->Item->MealInfoDescription = $offer['OfferDescription'];
		}

		if ($offerObj->Grila && $__use_grila)
		{
			//$offerObj->Item->_GRILA = $offerObj->Grila;
			$offerObj->Info = "Grila: " . $offerObj->Grila;
			$offerObj->Item->InfoTitle = $offerObj->Info;
			$offerObj->Grila = null;
		}

		if ($offer_info)
		{
			$offerObj->Info .= ((strlen($offerObj->Info)) ? " - " : "") . $offer_info;
			$offerObj->Item->InfoTitle .= ((strlen($offerObj->Item->InfoTitle)) ? " - " : "") . $offer_info;
		}

		if ($offerObj->_series_id && $offerObj->_series_name)
			$offerObj->Item->_SERIES = $offerObj->_series_name;

		// determine the offer price
		$price = 0;
		if (!isset($offer["Gross"]))
		{
			if (!$charters)
			{
				$price = $__room_price;
				foreach ($services ?: [] as $pd)
					$price += $pd["ServicePrice"];
			}
			else
			{
				$price = 0;
				if (($use_comission = static::$Config[$this->TourOperatorRecord->Handle]["charter"]['use_comission']))
				{
					$comissionServ = null;
					foreach ($services ?: [] as $servDetails)
					{
						if (static::$Config[$this->TourOperatorRecord->Handle]["charter"]['comission_code'] && 
							static::$Config[$this->TourOperatorRecord->Handle]["charter"]['comission_type'] && 
							(static::$Config[$this->TourOperatorRecord->Handle]["charter"]['comission_code'] == $servDetails["Code"]) &&
							(static::$Config[$this->TourOperatorRecord->Handle]["charter"]['comission_type'] == $servDetails["Type"]))
						{
							$comissionServ = $servDetails;
							break;
						}
					}

					if ($comissionServ)
					{
						$price = static::$Config[$this->TourOperatorRecord->Handle]["charter"]['comission_is_negative'] ? 
							(-$comissionServ["ServicePrice"]) : $comissionServ["ServicePrice"];
					}
				}

				$price += static::$Config[$this->TourOperatorRecord->Handle]["charter"]["use_noredd_price"] ? $offer["PriceNoRedd"] : $offer["ProductPrice"];
			}
		}
		else
			$price = $offer["Gross"];

		$fullPrice = (float)$offer["PriceNoRedd"];
		$initialPrice = $fullPrice ?: $price;

		$offerObj->Item->Quantity = 1;

		$offerObj->InitialPrice = $initialPrice;
		$offerObj->Price = $price;

		// setup room price
		$offerObj->Item->UnitPrice = (float)$f_off_room["@attributes"]["Gross"];

		//set discount
		if (!$charters)
		{
			$offerObj->_discount = 0;
			$servicesPrice = 0;
			foreach ($services ?: [] as $service)
			{
				if (is_scalar($service["Name"]) && (strtolower($service["Name"]) == "rotunjire"))
					continue;
				$servicesPrice += (float)$service["Gross"];
			}

			$accFullPrice = ($fullPrice - $servicesPrice);

			// acc price = room price + meal price
			$accPrice = ($offerObj->Item->UnitPrice + ($mealItm ? $mealItm->UnitPrice : 0));

			if (ceil($accPrice) < $accFullPrice)
				$offerObj->_discount = (float)number_format((100 - (($accPrice * 100)/$accFullPrice)));

			/*
			qvardump(
				$this->TourOperatorRecord->Handle, 
				$hotelObj->Code."|".$hotelObj->Name, 
				$offerObj->Item->Merch->Title,
				$mealItm ? $mealItm->Merch->Title : "NO MEAL",
				$offer,
				"OFFER SELL PRICE (NO DB DISCOUNT)  : " . $price,
				"FULL PRICE : " . $fullPrice,
				"EXTRA SERVICES PRICE : " . $servicesPrice,
				"ACC FULL PRICE (FULL PRICE - EXTRA SERVICES PRICE) : " . $accFullPrice,
				"ROOM PRICE : " . $offerObj->Item->UnitPrice, 
				"MEAL PRICE : " . $mealItm->UnitPrice,
				"ACC PRICE (ROOM PRICE + MEAL PRICE) : " . $accPrice,
				"DISCOUNT : " . $offerObj->_discount .  "%"
			);
			*/

		}
		else
		{
			$offerObj->_price_discount_only = static::$Config['_price_discount_only'];
		}
		

		$availability = ($offer['Availability'] && is_array($offer['Availability'])) ? $offer['Availability'][0] : null;

		//set availability
		$offerObj->Item->Availability = 'no';
		$lwAv = strtolower($availability);
		if ($lwAv === "immediate")
			$offerObj->Item->Availability = 'yes';
		else if ($lwAv === "onrequest")
			$offerObj->Item->Availability = 'ask';

		// set checkin and checkout
		$offerObj->Item->CheckinAfter = $checkIn;
		$offerObj->Item->CheckinBefore = $checkOut;

		if ($offerObj->Item->CheckinAfter && $offerObj->Item->CheckinBefore)
		{
			$dStart = new \DateTime($offerObj->Item->CheckinAfter);
			$dEnd = new \DateTime($offerObj->Item->CheckinBefore); 
			$diff = $dEnd->diff($dStart);
			$offerObj->Item->MaxDays = $offerObj->Item->MinDays = $diff->format('%d');
		}

		// setup here additional items on the order
		if (!$offerObj->Items)
			$offerObj->Items = new \QModelArray();

		// setup first item into items also
		$offerObj->Items[] = $offerObj->Item;

		// setup meal item here!
		if ($mealItm)
			$offerObj->Items[] = $mealItm;

		if ($crooms > 1)
		{
			foreach ($rooms as $room)
			{
				$itm = new \Omi\Travel\Offer\Room();
				$itm->Merch = $room;
				$offerObj->Items[] = $itm;
			}
		}

		$departureTransportCode = "other-outbound";
		$returnTransportCode = "other-inbound";

		// setup transport services on individual offer - checkin date and checkout date
		if (!$charters)
		{
			//@important fix
			$departureDate = ($offer["PeriodOfStay"] && $offer["PeriodOfStay"]["CheckIn"]) ? $offer["PeriodOfStay"]["CheckIn"] : null;
			$returnDate = ($offer["PeriodOfStay"] && $offer["PeriodOfStay"]["CheckOut"]) ? $offer["PeriodOfStay"]["CheckOut"] : null;			

			$departureItm = new \Omi\Travel\Offer\Transport();
			$departureItm->Merch = new \Omi\Travel\Merch\Transport();
			$departureItm->Merch->Title = "CheckIn: ".($departureDate ? date("d.m.Y", strtotime($departureDate)) : "");
			$departureItm->DepartureDate = $departureDate;
			$departureItm->ArrivalDate = $departureDate;
			$departureItm->Quantity = 1;
			$departureItm->UnitPrice = 0;

			$departureItm->Merch->Category = $objs[$departureTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
				($objs[$departureTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
			$departureItm->Merch->Category->Code = $departureTransportCode;

			$returnItm = new \Omi\Travel\Offer\Transport();
			$returnItm->Merch = new \Omi\Travel\Merch\Transport();
			$returnItm->Merch->Title = "CheckOut: ".($returnDate ? date("d.m.Y", strtotime($returnDate)) : "");
			$returnItm->DepartureDate = $returnDate;
			$returnItm->ArrivalDate = $returnDate;
			$returnItm->Quantity = 1;
			$returnItm->UnitPrice = 0;

			$returnItm->Merch->Category = $objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
				($objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
			$returnItm->Merch->Category->Code = $returnTransportCode;

			// set as return itm
			$departureItm->Return = $returnItm;
			$offerObj->Items[] = $departureItm;
			$offerObj->Items[] = $returnItm;
		}

		# $comission = $charters ? 0 : ($offer['CommissionCed'] ?: ((float)$offer['Gross'] - (float)$offer["ProductPrice"]));
		$comission = ($offer['CommissionCed'] ?: ((float)$offer['Gross'] - (float)$offer["ProductPrice"]));

		$departureTransport = null;
		$arrivalTransport = null;

		foreach ($services ?: [] as $service)
		{
			if (empty($service["Type"]) && empty($service["Name"]) && empty($service["Code"]))
				continue;

			$isTransport = ($service["Type"] == '7');
			$offerItm = $isTransport ? new \Omi\Travel\Offer\Transport() : new \Omi\Comm\Offer\OfferItem();
			$itmType = $isTransport ? "\Omi\Travel\Merch\Transport" : "\Omi\Comm\Merch\Merch";
			$offerItm->Merch = new $itmType();
			$offerItm->Merch->Title = $service["Name"];
			$offerItm->Merch->Code = $service["Code"];

			# this is outdated !!! Geo + Alex
			/*
			$is_comission = (($service['Code'] == 1) && ($service['Type'] == 8));

			if ($is_comission)
				$comission = $service["CommissionCed"] ?: (isset($service["Gross"]) ? ((float)$service["Gross"] - (float)$service['ServicePrice']) : (float)$service['ServicePrice']);
			*/
			
			if (!$isTransport)
			{
				$offerItm->Merch->Category = $objs[$service['Type']]["\Omi\Comm\Merch\MerchCategory"] ?: 
					($objs[$service['Type']]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
				$offerItm->Merch->Category->Code = $service['Type'];
			}

			$offerObj->Items[] = $offerItm;

			$offerItm->Quantity = 1;
			$offerItm->UnitPrice = floatval($service['ServicePrice']);

			$srv_avb = ($service['Availability'] && is_array($service['Availability'])) ? $service['Availability'][0] : null;
			$serviceAvailabilityAttrs = $service['Availability']["@attributes"];

			// by default is ask
			$offerItm->Availability = 'ask';
			if ($srv_avb != null)
			{
				//set availability
				//$offerItm->Availability = 'no';
				$lw_srv_avb = strtolower($srv_avb);
				if ($lw_srv_avb === "immediate")
					$offerItm->Availability = 'yes';
				else if ($lw_srv_avb === "onrequest")
					$offerItm->Availability = 'ask';
				else if ($lw_srv_avb === "stopsales")
					$offerItm->Availability = 'no';
			}
			else if ($serviceAvailabilityAttrs && $serviceAvailabilityAttrs["Code"])
			{
				$lw_srv_avb = strtolower($serviceAvailabilityAttrs["Code"]);
				if ($lw_srv_avb === "im")
					$offerItm->Availability = 'yes';
				else if ($lw_srv_avb === "or")
					$offerItm->Availability = 'ask';
				else if ($lw_srv_avb === "st")
					$offerItm->Availability = 'no';
			}

			if ($service['Provider'])
			{
				$offerItm->SuppliedBy = $objs[$service['Provider']]["\Omi\Company"] ?: ($objs[$service['Provider']]["\Omi\Company"] = new \Omi\Company());
				$offerItm->SuppliedBy->Name = $service['Provider'];
			}

			// only for transport items we will end up here
			if (!$isTransport)
				continue;

			// below we setup data for charter transports

			if (!empty($service["Seats"]) && is_string($service["Seats"]))
				$offerItm->setSeats(explode(",", $service["Seats"]));

			if ($isTransport)
			{
				if (!static::$_CacheData["Airlines"][$service['Company']])
					static::$_CacheData["Airlines"][$service['Company']] = new \Omi\Company();
				$offerItm->Provider = static::$_CacheData["Airlines"][$service['Company']];
			}
			else
				$offerItm->Provider = $objs[$service['Company']]["\Omi\Company"] ?: ($objs[$service['Company']]["\Omi\Company"] = new \Omi\Company());

			$offerItm->Provider->Name = $service["Company"];

			$offerItm->Merch->Type = strtolower($service["Transport"]);

			$offerItm->DepartureDate = ($service["PeriodOfStay"] && $service["PeriodOfStay"]["CheckIn"]) ? $service["PeriodOfStay"]["CheckIn"] : null;
			$offerItm->ArrivalDate = ($service["PeriodOfStay"] && $service["PeriodOfStay"]["CheckOut"]) ? $service["PeriodOfStay"]["CheckOut"] : null;

			if ($service['Departure'] && is_string($service['Departure']))
				$service['Departure'] = trim($service['Departure']);

			if ($service['Arrival'] && is_string($service['Arrival']))
				$service['Arrival'] = trim($service['Arrival']);

			if (is_array($service['Departure']) && $service['Departure']["@attributes"])
				$service['Departure'] = $service['Departure']["@attributes"]["Code"];

			if (is_array($service['Arrival']) && $service['Arrival']["@attributes"])
				$service['Arrival'] = $service['Arrival']["@attributes"]["Code"];

			
		
			if ($service['Departure'])
			{
				$offerItm->Merch->FromAirport = $objs[$service['Departure']]["\Omi\Travel\Airport"] ?: ($objs[$service['Departure']]["\Omi\Travel\Airport"] = new \Omi\Travel\Airport());
				$offerItm->Merch->FromAirport->Code = $service['Departure'];
			}

			if ($service["Arrival"])
			{
				$offerItm->Merch->ToAirport = $objs[$service['Arrival']]["\Omi\Travel\Airport"] ?: ($objs[$service['Arrival']]["\Omi\Travel\Airport"] = new \Omi\Travel\Airport());
				$offerItm->Merch->ToAirport->Code = $service["Arrival"];
			}

			$isDeparture = ($offerObj->Item->CheckinAfter && 
				(
					($offerItm->ArrivalDate && (date("Y-m-d", strtotime($offerItm->ArrivalDate)) === date("Y-m-d", strtotime($offerObj->Item->CheckinAfter)))) || 
					($offerItm->DepartureDate && (date("Y-m-d", strtotime($offerItm->DepartureDate)) === date("Y-m-d", strtotime($offerObj->Item->CheckinAfter))))
				)
			);

			/*
			$depCity = $objs[$params["DepCityCode"]]["\Omi\City"] ?: ($objs[$params["DepCityCode"]]["\Omi\City"] = QQuery("Cities.{* WHERE "
				. "TourOperator.Handle=? AND InTourOperatorId=?"
			. "}", [$this->TourOperatorRecord->Handle, $params["DepCityCode"]])->Cities[0]);
			
			$offerItm->Merch->From = new \Omi\Address();
			$offerItm->Merch->From->City = $isDeparture ? $depCity : $hotelObj->Address->City;

			$offerItm->Merch->To = new \Omi\Address();
			$offerItm->Merch->To->City = $isDeparture ? $hotelObj->Address->City : $depCity;
			*/

			$isArrival = ($offerObj->Item->CheckinBefore && (
				($offerItm->DepartureDate && (date("Y-m-d", strtotime($offerItm->DepartureDate)) === date("Y-m-d", strtotime($offerObj->Item->CheckinBefore)))) || 
				($offerItm->ArrivalDate && (date("Y-m-d", strtotime($offerItm->ArrivalDate)) === date("Y-m-d", strtotime($offerObj->Item->CheckinBefore))))
			));

			// search for a solution for this - for now just leave as it is
			if ((!$isArrival) && (!$arrivalTransport) && $departureTransport && $offerItm->Merch->ToAirport && $offerItm->Merch->FromAirport)
				$isArrival = true;

			if ($isDeparture)
			{
				$departureTransport = $offerItm;
				if ($arrivalTransport)
					$offerItm->Return = $arrivalTransport;

				$departureTransport->Merch->TransportType = $service["Transport"];
				$departureTransport->Merch->DepartureTime = $departureTransport->DepartureDate;
				$departureTransport->Merch->ArrivalTime = $departureTransport->ArrivalDate;
				$departureTransport->Merch->DepartureAirport = $departureTransport->Merch->FromAirport->Code;
				$departureTransport->Merch->ArrivalAirport = $departureTransport->Merch->ToAirport->Code;
				$departureTransport->Merch->ReturnAirport = $departureTransport->Merch->ToAirport->Code;

				$departureTransport->Merch->Category = $objs[$departureTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
					($objs[$departureTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
				$departureTransport->Merch->Category->Code = $departureTransportCode;

				//set offer availability
				$offerObj->AvailableQty = $service["Seats"];
				
				if (($departure = ((static::$RequestData && static::$RequestData["Departure"]) ? static::$RequestData["Departure"] : null)))
				{
					$departureTransport->Merch->From = new \Omi\Address();
					$departureTransport->Merch->From->City = $departure;
				}

				if (($destination = ((static::$RequestData && static::$RequestData["Destination"]) ? static::$RequestData["Destination"] : null)))
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
			else if ($isArrival)
			{
				$arrivalTransport = $offerItm;
				$arrivalTransport->Merch->TransportType = $service["Transport"];
				$arrivalTransport->Merch->DepartureTime = $arrivalTransport->DepartureDate;
				$arrivalTransport->Merch->ArrivalTime = $arrivalTransport->ArrivalDate;
				$arrivalTransport->Merch->DepartureAirport = $departureTransport->Merch->ToAirport->Code;
				$arrivalTransport->Merch->ArrivalAirport = $departureTransport->Merch->FromAirport->Code;
				$arrivalTransport->Merch->ReturnAirport = $departureTransport->Merch->FromAirport->Code;
				$arrivalTransport->Merch->Category = $objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
					($objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
				
				$arrivalTransport->Merch->Category->Code = $returnTransportCode;
					
				if ($departureTransport)
					$departureTransport->Return = $offerItm;
				
				if (($destination = ((static::$RequestData && static::$RequestData["Destination"]) ? static::$RequestData["Destination"] : null)))
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
				
				if (($departure = ((static::$RequestData && static::$RequestData["Departure"]) ? static::$RequestData["Departure"] : null)))
				{
					$arrivalTransport->Merch->To = new \Omi\Address();
					$arrivalTransport->Merch->To->City = $departure;
				}
			}
		}

		$offerObj->Comission = $comission;

		// on eurosite we don't have currency on items - we have it only on offer - so we need to setup the supplied currency on all items
		foreach ($offerObj->Items ?: [] as $itm)
			$itm->SuppliedCurrency = $offerObj->SuppliedCurrency;

		// setup offer currency
		$this->setupOfferPriceByCurrencySettings($params, $params["SellCurrency"], $offerObj, ($charters ? "charter" : "individual"));
		
		$params["Rooms"] = \Omi\Travel\Offer\Stay::SetupRoomsParams($params["Rooms"], [$offerObj]);

		$countryCode = isset($hotelObj->Address->Country->Code) ? $hotelObj->Address->Country->Code : 
			(isset($hotelObj->Address->City->Country->Code) ? $hotelObj->Address->City->Country->Code : null);

		$feesParams = [
			"__type__" => $charters ? "charter" : "individual",
			"__cs__" => $this->TourOperatorRecord->Handle,
			"CheckIn" => $checkIn,
			"Price" => $offerObj->Price,
			"CurrencyCode" => $params["CurrencyCode"],
			"SellCurrency" => $params["SellCurrency"],
			"TourOpCode" => $hotelObj->ResellerCode,
			"Rooms" => $params["Rooms"],
			["CountryCode" => $countryCode],
			["CityCode" => $hotelObj->Address->City->InTourOperatorId],
			["ProductCode" => $hotelObj->InTourOperatorId],
			["PeriodOfStay" => [
					["CheckIn" => $checkIn], 
					["CheckOut" => $checkOut]
				]
			],
			["VariantId" => $offerObj->PackageVariantId],
			["Rooms" => $params["Rooms"]]
		];

		// set in fees params
		if ($hotelObj && $hotelObj->Address && $hotelObj->Address->City)
		{
			if ($hotelObj->Address->City->Country)
				$feesParams["Country"] = $hotelObj->Address->City->Country->getId();
			if ($hotelObj->Address->City->County)
				$feesParams["County"] = $hotelObj->Address->City->County->getId();
			$feesParams["City"] = $hotelObj->Address->City->getId();
		}
		$offerObj->__fees_params = $feesParams;

		$iparams_full = static::$RequestOriginalParams;
		$useAsyncFeesFunctionality = ((defined('USE_ASYNC_FEES') && USE_ASYNC_FEES) && (!$iparams_full["__on_setup__"]) 
			&& (!$iparams_full["__on_add_travel_offer__"]) && (!$iparams_full["__send_to_system__"]));

		if (((!$useAsyncFeesFunctionality) && (($params["getFeesAndInstallments"] && (!$params["getFeesAndInstallmentsFor"])) || 
			(
				$params["getFeesAndInstallmentsFor"] && 
					(
						($params["getFeesAndInstallmentsFor"] == $hotelObj->getId()) || 
						($hotelObj->Master && ($params["getFeesAndInstallmentsFor"] == $hotelObj->Master->getId()))
					) && 
					(!$params["getFeesAndInstallmentsForOffer"] || 
						(
							(is_scalar($params["getFeesAndInstallmentsForOffer"]) && ($params["getFeesAndInstallmentsForOffer"] == $offerObj->Code)) || 
							(is_array($params["getFeesAndInstallmentsForOffer"]) && (isset($params["getFeesAndInstallmentsForOffer"][$offerObj->Code])))
						)
					)
			))
		) || ($initialParams['api_call'] && $initialParams['api_call_method'] && $initialParams['__api_offer_code__'] && 
			($initialParams['api_call_method'] == 'offer-details') && ($initialParams['__api_offer_code__'] == $offerObj->Code)))
		{
			list($offerObj->CancelFees, $offerObj->Installments) = static::ApiQuery($this, "HotelFeesAndInstallments", null, null, [$feesParams, $feesParams]);
		}

		// update offer availability based on transport availability
		if ($charters && ($offerObj->Item->Availability !== "no"))
		{
			if (($departureTransport->Availability == "no") || ($arrivalTransport->Availability == "no"))
				$offerObj->Item->setAvailability("no");
			else if (($departureTransport->Availability == "ask") || ($arrivalTransport->Availability == "ask"))
			{
				$accountSettings = \QApi::Call('Omi\\App::GetAccountSettings');
				$offerObj->Item->setAvailability($accountSettings->SetNotAvailableForOnRequestPlane_Eurosite ? "no" : "ask");
			}
		}

		return $offerObj;
	}

	public static function SyncHotelsContent($binds = [])
	{
		if (!$binds)
			$binds = [];
		$binds["TourOperatorStorageClass"] = 'Omi\\TF\\EuroSite';
		
		$useEntity = "Name,"
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
			. "InCategoriesItems.{Category.{Alias, Name, Active, HideTravelItems}, Type}, "
			. "Facilities.Name,"
			. "HasIndividualOffers,"
			. "HasCharterOffers,"
			. "HasCityBreakActiveOffers, "
			. "HasPlaneCityBreakActiveOffers, "
			. "HasBusCityBreakActiveOffers, "

			. "HasChartersActiveOffers, "
			. "HasPlaneChartersActiveOffers, "
			. "HasBusChartersActiveOffers, "

			. "LiveVisitors, "
			. "LastReservationDate,	"

			. "HasIndividualActiveOffers, "
			. "HasHotelsActiveOffers, "
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
						. "Alt,"
						. "Path, "
						. "Type, "
						. "ExternalUrl, "
						. "RemoteUrl, "
						. "Base64Data, "
						. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
						. "InTourOperatorId, "
						. "Order, "
						. "Alt"
					. "}"
				. "}"
			. "},"
			. "Address.{"
				. "City.{"
					. "Code, "
					. "Name, "
					. "InTourOperatorId, "
					. "County.{"
						. "Code, "
						. "Name, "
						. "InTourOperatorId, "
						. "Country.{"
							. "Alias,"
							. "Code, "
							. "Name "
						. "}"
					. "}, "
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name,"
						. "InTourOperatorsIds.{"
							. "Identifier,"
							. "TourOperator.Handle WHERE 1 "
								. "??TourOperatorHandle?<AND[TourOperator.Handle=?]"
						. "}"
					. "}"
				. "}, "
				. "County.{"
					. "Code, "
					. "Name, "
					. "Country.{"
						. "Alias,"
						. "Code, "
						. "Name"
					. "}"
				. "}, "
				. "Country.{"
					. "Alias,"
					. "Code, "
					. "Name"
				. "}, "
				. "Latitude, "
				. "Longitude"
			. "}";
		
		$useEntity = q_SanitizeSelector("Hotels", $useEntity);
		
		$hotels = QQuery("Hotels.{" . $useEntity
			. " WHERE 1 "
				. "??TourOperatorStorageClass?<AND[TourOperator.StorageClass=?]"
				. "??TourOperatorHandle?<AND[TourOperator.Handle=?]"
				. "??IN?<AND[Id IN (?)]"
				. "??NOT_IN?<AND[Id NOT IN (?)]"
				. "??Id?<AND[Id=?]"
				. "??City?<AND[Address.City.Id=?]"
				. "??CityTopId?<AND[Address.City.InTourOpertorId=?]"
				. "??County?<AND[Address.City.County.Id=?]"
				. "??CountyTopId?<AND[Address.City.County.InTourOperatorId=?]"
				. "??Country?<AND[Address.City.Country.Id=?]"
				. "??CountryCode?<AND[Address.City.Country.Code=?]"
			. " GROUP BY Id"
		. "}", $binds)->Hotels;
		
		$app = \QApp::NewData();
		$app->setHotels(new \QModelArray());

		$toExecInstances = [];
		foreach ($hotels ?: [] as $hotel)
		{
			if (!$hotel->TourOperator || !$hotel->InTourOperatorId || !$hotel->Address || (!($city = $hotel->Address->City)) || !$city->InTourOperatorId || 
					!$city->Country || (!($countryCode = $city->Country->getTourOperatorIdentifier($hotel->TourOperator))))
			{
				continue;
			}

			if (!($toExecInstance = ($toExecInstances[$hotel->TourOperator->Handle] ?: 
				($toExecInstances[$hotel->TourOperator->Handle] = \QApp::GetStorage('travelfuse')->getChildStorage($hotel->TourOperator->Handle)))))
				continue;

			$params = [
				"TourOpCode" => $hotel->ResellerCode,
				"CountryCode" => $countryCode,
				"CityCode" => $city->InTourOperatorId,
				"ProductCode" => $hotel->InTourOperatorId,
				"Force" => true,
				"SkipQ" => true
			];
			
			if (!($newHotel = $toExecInstance->getHotelInfo($params)))
				continue;
			$app->Hotels[] = $newHotel;
		}

		if (count($app->Hotels) > 0)
		{
			$app->save("Hotels.{"
				. "MTime,"
				. "Content.{"
					. "Order, "
					. "Active, "
					. "ShortDescription, "
					. "Content, "
					. "ImageGallery.{"
						. "Items.{"
							. "Updated, "
							. "Alt,"
							. "Path, "
							. "Type, "
							. "ExternalUrl, "
							. "RemoteUrl, "
							. "Base64Data, "
							. "TourOperator.{Handle, Caption, Abbr}, "
							. "InTourOperatorId, "
							. "Order, "
							. "Alt"
						. "}"
					. "}"
				. "}"
			. "}");
		}
	}
}