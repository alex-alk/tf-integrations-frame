<?php

namespace Omi\TF;

trait EuroSiteCharters
{
	
	public static $RoomsDefault = [
		[
			"Room" => [
				"Code" => "DB",
				"NoAdults" => 2,
				"NoChildren" => 0
			]
		]
	];
	
	/*===========================================CHARTERS================================================*/
	/**
	 * Returns an array with locations for departure or arrival
	 * The array contains country objects and countries contains cities
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function GetChartersLocations($params = null, $objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
			$params = [];

		$data = static::GetResponseData($this->Request("getCharterCitiesRequest", $params), "getCharterCitiesResponse");
		return $this->getCountriesWithServices($data ? $data["Country"] : null, $objs);
	}
	/**
	 * Returns charter offers
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getCharters($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}

		$data = static::GetResponseData($this->doRequest("getCharterPriceRequest", $params), "getCharterPriceResponse");

		$charters = $data["Charter"] ? $data["Charter"] : null;

		if (!$charters || (count($charters) === 0))
			return [];

		if (isset($charters["@attributes"]))
			$charters = array($charters);
		
		if (!$objs)
			$objs = [];
		
		$app = \QApp::NewData();
		
		$ret = [];
		foreach ($charters as $charter)
		{
			$offers = ($charter["Offers"] && $charter["Offers"]["Offer"]) ? $charter["Offers"]["Offer"] : null;
			if (!$offers || (count($offers) === 0))
				continue;

			//$type = ($charter["@attributes"] && $charter["@attributes"]["Type"]) ? $charter["@attributes"]["Type"] : null;
			
			if (isset($offers["TourOpCode"]))
				$offers = array($offers);

			foreach ($offers as $offer)
			{
				$offer["Description"] = $offer["CharterNr"];
				$offer["DepartureDate"] = $offer["Departure"];
				$offer["Departure"] = $charter["Departure"];
				$offer["Destination"] = $charter["Destination"];
				$charter = $this->getCharterOffer($offer, $objs);
				$ret[] = $charter;
				
				$app->Charters[] = $charter;
			}
		}

		$app->save("Charters.{"
			. "Code, Group, AddedByPartner, Price, DateStart, DateEnd, "
			. "Content.{Content, Order, Active}, "
			. "Item.{Merch.Id , Persons.Id, Availability}, "
			. "Items.{Merch.{Category.Code, Title, Provider.Id, From.City.Id, To.City.Id} , Persons.Id, DepartureDate, ArrivalDate, "
				. "Return.{DepartureDate, ArrivalDate, Merch.{Title, Provider.Id, From.City.Id, To.City.Id}}"
			. "}"
		. "}, "
		. "ChartersTransports.{Price, To.City.Id, Provider.Id, From.City.Id, "
			. "Content.{Active, Order}, ForPartner, Dates.{Date, ForPartner, Active}"
		. "}");
		
		return $ret;
	}
	/**
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return \Omi\Travel\Offer\Charter
	 */
	private function getCharter($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}

		$params["ProductType"] = "charter";
		$data = static::GetResponseData($this->doRequest("getProductInfoRequest", $params), "getProductInfoResponse");

		return $this->getCharterOffer($data ? $data["Product"] : null, $objs);
	}
	/**
	 * Returns charter supliments (suplimentary services)
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return array
	 */
	private function getCharterSupliments($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}

		$data = static::GetResponseData($this->doRequest("getCharterServiceRequest", $params), "getCharterServiceResponse");
		$services = ($data["Services"] && $data["Services"]["Service"]) ? $data["Services"]["Service"] : null;
		return $this->getServices($services, $objs);
	}
	/**
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return \Omi\Comm\Offer\OfferItem
	 */
	private function getCharterSupliment($params = null, &$objs = null)
	{
		//CircuitSearchCityRequest
		if (!$params)
		{
			// populate here with some defaultss
			$params = [];
		}
		$data = static::GetResponseData($this->doRequest("getCharterServicePriceRequest", $params), "getCharterServicePriceResponse");
		$service = ($data["Services"] && $data["Services"]["Service"]) ? $data["Services"]["Service"] : null;
		return $service ? $this->getSerivce($service, $objs) : null;
	}
	/**
	 * Alias for get hotel fees - only parameters are different
	 * 
	 * @param array $params
	 * @param array $objs
	 * @return \Omi\Comm\Offer\OfferItem
	 */
	private function getCharterFees($params = null, &$objs = null)
	{
		return $this->getHotelFees($params, $objs, true);
	}
	/**
	 * Returns an charter offer
	 * 
	 * @param array $offer
	 * @param array $objs
	 * @return \Omi\Travel\Offer\Charter
	 */
	private function getCharterOffer($offer, &$objs = null)
	{
		// load cached data
		$this->loadCachedCurrencies($objs);
		$this->loadCachedCompanies($objs);

		$offerObj = $objs[$offer["DepartureId"]]["\Omi\Travel\Offer\Charter"] ?: ($objs[$offer["DepartureId"]]["\Omi\Travel\Offer\Charter"] = new \Omi\Travel\Offer\Charter());

		$offerObj->Code = $offer["CharterId"];
		$offerObj->Title = $offer["Description"];
		$offerObj->Description = $offer["Description"];

		if (!$offerObj->Item)
			$offerObj->Item = new \Omi\Travel\Offer\Transport();

		if ($offer["Provider"])
		{
			$offerObj->Item->SuppliedBy = $objs[$offer["Provider"]]["\Omi\Company"] ?: ($objs[$offer["Provider"]]["\Omi\Company"] = new \Omi\Company());
			$offerObj->Item->SuppliedBy->Name = $offer["Provider"];
		}

		$offerObj->Item->Quantity = 1;
		$offerObj->Item->UnitPrice = $offer["Gross"];

		//set availability
		$offerObj->Item->Availability = 'no';
		$lwAv = strtolower($offer['Availability']);
		if ($lwAv === "immediate")
			$offerObj->Item->Availability = 'yes';
		else if ($lwAv === "onrequest")
			$offerObj->Item->Availability = 'ask';

		// set currency
		$currency = ($offer['@attributes'] && $offer['@attributes']['CurrencyCode']) ? $offer['@attributes']['CurrencyCode'] : null;
		$currencyObj = $currency ? $objs[$currency]["\Omi\Comm\Currency"] ?: ($objs[$currency]["\Omi\Comm\Currency"] = new \Omi\Comm\Currency()) : null;
		if ($currencyObj)
			$currencyObj->Code = $currency;
		$offerObj->Item->Currency = $currencyObj;

		if (!$offerObj->Item->Merch)
			$offerObj->Item->Merch = new \Omi\Travel\Merch\Transport();

		$offerObj->Item->Merch->Type = $offer["Transport"];
		$offerObj->Item->Merch->Code = $offer["DepartureId"];
		$offerObj->Item->FlightNumber = $offer["FlightNumber"];
		
		$offerObj->Item->Provider = $objs[$offer["Company"]]["\Omi\Company"] ?: ($objs[$offer["Company"]]["\Omi\Company"] = new \Omi\Company());
		$offerObj->Item->Provider->Name = $offer["Company"];

		$offerObj->Item->DepartureDate = $offer["DepartureDate"];
		$offerObj->Item->ArrivalDate = $offer["Arrival"];

		// setup departure and destination
		if ($offer["Departure"])
			$this->setOnCharterTransport($offerObj->Item->Merch, "From", $offer["Departure"], $objs);

		if ($offer["Destination"])
			$this->setOnCharterTransport($offerObj->Item->Merch, "To", $offer["Destination"], $objs);

		// setup dates here on transport and on offer
		$transportDates = new \Omi\Travel\Merch\TransportDate();
		$transportDates->Date = $offer["DepartureDate"];

		if (!$offerObj->Item->Merch->Dates)
			$offerObj->Item->Merch->Dates = new \QModelArray();
		$offerObj->Item->Merch->Dates[] = $transportDates;
		$offerObj->DepartureDate = $transportDates;

		// setup provider here
		$offerObj->Item->Merch->Provider = $objs[$offer["Company"]]["\Omi\Company"] ?: ($objs[$offer["Company"]]["\Omi\Company"] = new \Omi\Company());
		$offerObj->Item->Merch->Provider->Name = $offer["Company"];
		return $offerObj;
	}

	/**
	 * Set from and to on charter transport
	 * 
	 * @param type $obj
	 * @param type $prop
	 * @param type $arr
	 * @param type $objs
	 */
	private function setOnCharterTransport($obj, $prop, $arr, &$objs = null)
	{
		$country = null;
		if ($arr["CountryCode"])
		{
			$country = $this->GetCacheCountry($arr["CountryCode"]);
			if (!$country)
			{
				$country = $objs[$arr["CountryCode"]]["\Omi\Country"] ?: ($objs[$arr["CountryCode"]]["\Omi\Country"] = new \Omi\Country());
				$country->Code = $arr["CountryCode"];
				$country->Name = $arr["CountryName"];
			}
		}

		$city = null;
		if ($arr["CityCode"])
		{
			$city = $this->getCacheCity($arr["CityCode"]);
			if (!$city)
			{
				$city = $objs[$arr["CityCode"]]["\Omi\City"] ?: ($objs[$arr["CityCode"]]["\Omi\City"] = new \Omi\City());
				$city->Code = $arr["CityCode"];
				$city->Name = $arr["CityName"];
				if ($country)
					$city->Country = $country;
			}
		}

		$addrIndx = $arr["CountryCode"]."~".$arr["CityCode"]."~".$arr["AirportCode"];
		$obj->{$prop} = $objs[$addrIndx]["\Omi\Address"] ?: ($objs[$addrIndx]["\Omi\Address"] = new \Omi\Address());
		$obj->{$prop}->Country = $country;
		$obj->{$prop}->City = $city;

		if ($arr["AirportCode"])
			$obj->{$prop}->Code = $arr["AirportCode"];
		if ($arr["AirportName"])
			$obj->{$prop}->Alias = $arr["AirportName"];
	}

	private function saveChartersDiscounts(&$objs = null)
	{
		// do the request to get the stay discounts
		$charterDiscounts = \QApi::Call("\\Omi\\Comm\\Offer\\OfferDiscount::GetDiscounts", "Charters");
		
		// index existing discounts
		$existingDiscounts = [];
		foreach ($charterDiscounts as $discount)
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
			$objs = [];
		// needs to set up TourOpCode
		$data = static::GetResponseData($this->doRequest("getPackageChartersDiscountsRequest", [], true), "getPackageChartersDiscountsResponse");
		$appData = \QApp::NewData();

		$charters = ($data && $data["Charters"] && $data["Charters"]["Charter"]) ? $data["Charters"]["Charter"] : null;

		// store the processed discounts to be later compared to the existing one - used for deletion
		$processedDiscounts = [];

		// if we have hotels discounts returned from eurosite go through them
		if ($charters && (count($charters) > 0))
		{
			if ($charters["CharterName"])
				$charters = [$charters];

			// prepare extra data to be saved!
			$appData->Countries = new \QModelArray();
			$appData->Cities = new \QModelArray();
			$appData->Hotels = new \QModelArray();
			$appData->OffersDiscounts = new \QModelArray();

			foreach ($charters as $charter)
			{
				$discounts = ($charter["Discounts"] && $charter["Discounts"]["Discount"]) ? $charter["Discounts"]["Discount"] : null;

				$charterTag = trim($charter["CharterName"]);

				if (!$discounts)
					continue;

				if ($discounts["Nights"])
					$discounts = [$discounts];

				// get the country from cache - if we don't have it then save it
				$country = $appData->Countries[$charter["ArrivalCountryCode"]] ? $appData->Countries[$charter["ArrivalCountryCode"]] : 
					$this->getCacheCountry($charter["ArrivalCountryCode"]);

				if (!$country)
				{
					$country = $objs[$charter["ArrivalCountryCode"]]["\Omi\Country"] ?: ($objs[$charter["ArrivalCountryCode"]]["\Omi\Country"] = new \Omi\Country());
					$country->Code = $charter["ArrivalCountryCode"];
					$country->Name = $charter["ArrivalCountryName"];
					$appData->Countries[$country->Code] = $country;
				}

				$country->setMTime(date("Y-m-d H:i:s"));

				// get the city from cache - if we don't have it then save it
				$city = $appData->Cities[$charter["ArrivalCityCode"]] ? $appData->Cities[$charter["ArrivalCityCode"]] : 
					$this->getCacheCity($charter["ArrivalCityCode"]);

				if (!$city)
				{
					$city = $objs[$charter["ArrivalCityCode"]]["\Omi\City"] ?: ($objs[$charter["ArrivalCityCode"]]["\Omi\City"] = new \Omi\City());
					$city->Code = $charter["ArrivalCityCode"];
					$city->Name = $charter["ArrivalCityName"];
					$city->Country = $country;
					$appData->Cities[$city->Code] = $city;
				}

				$city->setMTime(date("Y-m-d H:i:s"));

				// this should be given by Eurosite
				$charter["DepartCountryCode"] = "RO";
				$charter["DepartCityCode"] = "ROBCH1";
				if (strpos(strtolower($charterTag), "bacau") !== false)
					$charter["DepartCityCode"] = "ROBC";
				else if (strpos(strtolower($charterTag), "chisinau") !== false)
				{
					$charter["DepartCityCode"] = "MDCHS";
					$charter["DepartCountryCode"] = "MD";
				}
				else if (strpos(strtolower($charterTag), "craiova") !== false)
					$charter["DepartCityCode"] = "ROCRV";
				else if (strpos(strtolower($charterTag), "cluj") !== false)
					$charter["DepartCityCode"] = "ROCLJNPC";
				else if (strpos(strtolower($charterTag), "iasi") !== false)
						$charter["DepartCityCode"] = "ROIS";
				else if (strpos(strtolower($charterTag), "timisoara") !== false)
						$charter["DepartCityCode"] = "ROTMS";

				// get the city from cache - if we don't have it then save it
				$depCountry = $appData->Countries[$charter["DepartCountryCode"]] ? $appData->Countries[$charter["DepartCountryCode"]] : 
					$this->getCacheCountry($charter["DepartCountryCode"]);
				
				if (!$depCountry)
				{
					$depCountry = $objs[$charter["DepartCountryCode"]]["\Omi\City"] ?: ($objs[$charter["DepartCountryCode"]]["\Omi\Country"] = new \Omi\Country());
					$depCountry->Code = $charter["DepartCountryCode"];
					$depCountry->Name = $charter["DepartCountryName"];
					$appData->Countries[$depCountry->Code] = $depCountry;
				}
				
				$depCountry->setMTime(date("Y-m-d H:i:s"));

				// get the city from cache - if we don't have it then save it
				$depCity = $appData->Cities[$charter["DepartCityCode"]] ? $appData->Cities[$charter["DepartCityCode"]] : 
					$this->getCacheCity($charter["DepartCityCode"]);
				if (!$depCity)
				{
					$depCity = $objs[$charter["DepartCityCode"]]["\Omi\City"] ?: ($objs[$charter["DepartCityName"]]["\Omi\City"] = new \Omi\City());
					$depCity->Code = $charter["DepartCityCode"];
					$depCity->Name = $charter["DepartCityName"];
					$depCity->Country = $depCountry;
					$appData->Cities[$depCity->Code] = $depCity;
				}

				$depCity->setMTime(date("Y-m-d H:i:s"));

				// setup country on city
				if ($country)
					$city->Country = $country;

				foreach ($discounts as $discountData)
				{
					$hotels = ($discountData["Hotels"] && $discountData["Hotels"]["Hotel"]) ? $discountData["Hotels"]["Hotel"] : null;
					if (!$hotels)
						continue;

					// determine the discount index
					$discountIndx = $charter["CharterName"]."~".$discountData["IssueDateStart"]."~".$discountData["IssueDateStop"]."~"
						. $discountData["Value"]."~". strtolower($discountData["ValueType"]);

					// check if the discount already exists
					$discount = $existingDiscounts[$discountIndx] ? $existingDiscounts[$discountIndx] : ($objs[$discountIndx]["\Omi\Travel\Offer\CharterDiscount"] ?: 
						($objs[$discountIndx]["\Omi\Travel\Offer\CharterDiscount"] = new \Omi\Travel\Offer\CharterDiscount()));

					// setup discount data
					$discount->Tag = $charter["CharterName"];
					$discount->StartDate = $discountData["IssueDateStart"];
					$discount->EndDate = $discountData["IssueDateStop"];
					$discount->Value = $discountData["Value"];
					$discount->Type = strtolower($discountData["ValueType"]);
					//$discount->setMTime(date("Y-m-d H:i:s"));
					
					// store the discount on stay discounts collection
					$appData->OffersDiscounts[$discountIndx] = $discount;
					
					$charterDepartureDate = $discountData["DepartureDateStart"];
					$charterArrivalDate = date("Y-m-d", strtotime("+ ".$discountData["Nights"]." days", strtotime($discountData["DepartureDateStart"])));

					if ($hotels["HotelCode"])
						$hotels = [$hotels];

					foreach ($hotels as $hotel)
					{
						// get hotel by code if any
						$hotelObj = $appData->Hotels[$hotel["HotelCode"]] ? $appData->Hotels[$hotel["HotelCode"]] : \QApi::QueryById("Hotels", ["Code" => $hotel["HotelCode"]]);

						if ($hotelObj && $hotelObj->Address && $hotelObj->Address->City)
							$city = $hotelObj->Address->City;

						// if we don't have the hotel then save it
						if (!$hotelObj)
						{
							$params = [];
							$params["CityCode"] = $city->Code;
							$params["CountryCode"] = $country->Code;
							$params["ProductCode"] = $hotel["HotelCode"];
							$hotelObj = $this->getHotelInfo($params);
							if (!$hotelObj)
								continue;
							$appData->Hotels[$hotelObj->Code] = $hotelObj;
						}

						$hotelObj->setMTime(date("Y-m-d H:i:s"));

						// setup country and city on hotel address
						if (!$hotelObj->Address)
							$hotelObj->Address = new \Omi\Address();
						$hotelObj->Address->City = $city;
						$hotelObj->Address->Country = $country;

						// determine offer index
						$offerIndx = $hotelObj->Code."~".$charterDepartureDate."~".$charterArrivalDate;

						if (!$processedDiscounts[$discountIndx])
							$processedDiscounts[$discountIndx] = [];

						// get the offer and the key - if the offer exists
						list($_offKey, $offer) = ($discount->_offers && $discount->_offers[$offerIndx]) ? $discount->_offers[$offerIndx] : 
							[0, $processedDiscounts[$discountIndx][$offerIndx] ? $processedDiscounts[$discountIndx][$offerIndx] : new \Omi\Travel\Offer\Charter()];

						if (!$offer->Content)
							$offer->Content = new \Omi\Cms\Content();
						$offer->Content->Title = $charterTag;

						if (!$offer->Items)
							$offer->Items = new \QModelArray();

						// setup offer data
						$roomOfferItem = $offer->getRoomItem();
						if (!$roomOfferItem)
							$roomOfferItem = new \Omi\Travel\Offer\Room();

						if (!$roomOfferItem->Merch)
							$roomOfferItem->Merch = new \Omi\Travel\Merch\Room();

						$roomOfferItem->Merch->Hotel = $hotelObj;
						$roomOfferItem->CheckinAfter = $charterDepartureDate;
						$roomOfferItem->CheckinBefore = $charterArrivalDate;

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

						$departureItem->DepartureDate = $charterDepartureDate;
						$departureItem->Return->DepartureDate = $charterArrivalDate;

						if (!$departureItem->Merch->To)
							$departureItem->Merch->To = new \Omi\Address();

						if (!$departureItem->Merch->From)
							$departureItem->Merch->From = new \Omi\Address();

						if (!$departureItem->Return->Merch->From)
							$departureItem->Return->Merch->From = new \Omi\Address();

						if (!$departureItem->Return->Merch->To)
							$departureItem->Return->Merch->To = new \Omi\Address();

						$departureItem->Merch->To->City = $departureItem->Return->Merch->From->City = $city;
						$departureItem->Merch->To->County = $departureItem->Return->Merch->From->County = $city ? $city->County : null;
						$departureItem->Merch->To->Country = $departureItem->Return->Merch->From->Country = $city ? $city->Country : null;

						$departureItem->Merch->From->City = $departureItem->Return->Merch->To->City = $depCity;
						$departureItem->Merch->From->County = $departureItem->Return->Merch->To->County = $depCity ? $depCity->County : null;
						$departureItem->Merch->From->Country = $departureItem->Return->Merch->To->Country = $depCity ? $depCity->Country : null;

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
			//q_die();

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
					. "Content.Title, "
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
								. "To.{"
									. "City.Id, "
									. "Country.Id, "
									. "County.Id"
								. "}, "
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
							. "}, "
							. "From.{"
								. "City.Id, "
								. "Country.Id, "
								. "County.Id"
							. "}"
						. "}"
					. "}"
				. "}"
			);
		}

		if (!$existingDiscounts || (count($existingDiscounts) === 0))
			return;

		// remove stay discounts or offers for it
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
	/*===================================END CHARTERS=============================================*/	
	
	public function saveCharters_ByTransportType($transportType, $force = false, $config = null)
	{
		$requestsUIDS = [];
		$callMethod = "getPackageNVRoutesRequest";
		$callKeyIdf = $transportType;
		$callParams = ["Transport" => $transportType];
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

		$tStartProcess = microtime(true);

		$respData = static::GetResponseData($return, "getPackageNVRoutesResponse");
		$forceToCity = (isset(static::$Config[$this->TourOperatorRecord->Handle]["reqs_on_city"]) || ($config && $config["_FORCE_TO_CITY_"]));

		$existingChartersTransports = \Omi\TF\CharterTransport::GetTransports([
			"TransportType" => $transportType,
			"Content" => true,
			"From" => true,
			"To" => true,
			"DatesWithNights" => true,
			"TourOperator" => $this->TourOperatorRecord->getId()
		], true);

		// setup existing charters transports
		$existingTransports = $this->setupExistingTransports($existingChartersTransports);

		$processedTransports = [];
		$cachedTransports = [];

		$countries = [];
		$counties = [];
		$cities = [];
		$chartersTransports = [];
		$requests = [];

		$__todayTime = strtotime(date("Y-m-d"));

		$reqs_out = "";
		try
		{
			$charterCountries = ($respData && $respData["Country"]) ? $respData["Country"] : null;
			if ($charterCountries && isset($charterCountries["CountryCode"]))
				$charterCountries = [$charterCountries];

			$countriesCodes = [];
			$countiesByInTopId = [];
			$citiesByInTopId = [];

			// go through countries
			$toProcessCountries = [];
			
			#$datesCountByZone = [];
			
			foreach ($charterCountries ?: [] as $countryK => $country)
			{
				if (!$country["CountryCode"])
					continue;
				$countriesCodes[$country["CountryCode"]] = $country["CountryCode"];
				$destinations = ($country['Destinations'] && $country['Destinations']['Destination']) ? $country['Destinations']['Destination'] : null;
				if ($destinations && isset($destinations["CityCode"]))
					$destinations = [$destinations];
				$topDestinations = [];
				foreach ($destinations ?: [] as $destK => $destination)
				{
					if ($destination["ZoneCode"])
					{
						$countiesByInTopId[$destination["ZoneCode"]] = [
							"Name" => $destination["ZoneName"],
							"InTourOperatorId" => $destination["ZoneCode"],
							"CountryCode" => $country["CountryCode"],
						];
					}
					if ($destination["CityCode"])
					{
						$citiesByInTopId[$destination["CityCode"]] = [
							"Name" => $destination["CityName"],
							"InTourOperatorId" => $destination["CityCode"],
							"ZoneCode" => $destination["ZoneCode"],
							"CountryCode" => $country["CountryCode"],
						];
					}
					$departures = ($destination["Departures"] && $destination["Departures"]["Departure"]) ? $destination["Departures"]["Departure"] : null;
					if ($departures && isset($departures["CountryCode"]))
						$departures = [$departures];
					$topDepartures = [];
					foreach ($departures ?: [] as $depK => $departure)
					{
						if (!$departure["CountryCode"])
							continue;
						$countriesCodes[$departure["CountryCode"]] = $departure["CountryCode"];
						if ($departure["ZoneCode"])
						{
							$countiesByInTopId[$departure["ZoneCode"]] = [
								"Name" => $departure["ZoneName"],
								"InTourOperatorId" => $departure["ZoneCode"],
								"CountryCode" => $country["CountryCode"],
							];
						}
						if ($departure["CityCode"])
						{
							$citiesByInTopId[$departure["CityCode"]] = [
								"Name" => $departure["CityName"],
								"InTourOperatorId" => $departure["CityCode"],
								"ZoneCode" => $departure["ZoneCode"],
								"CountryCode" => $country["CountryCode"],
							];
						}

						$dates = ($departure["Dates"] && $departure["Dates"]["Date"]) ? $departure["Dates"]["Date"] : null;
						if (is_string($dates) || (is_array($dates) && isset($dates["@attributes"])))
							$dates = [$dates];
						
						$countDates = count($dates);
						$destIndx = $destination["ZoneCode"] ?: 0;
						$departIndx = $departure["CityCode"] ?: 0;
						#$datesCountByZone[$destIndx][$departIndx][$countDates][] = [$departure, $destination];
						
						$_dpos = 0;
						$topDates = [];
						foreach ($dates ?: [] as $dateK => $dateDescr)
						{
							$isArr = is_array($dateDescr);
							$date = $isArr ? $dateDescr[0] : $dateDescr;
							$topContext = $isArr ? (($dateDescr["@attributes"] && $dateDescr["@attributes"]["TourOpCode"]) ? $dateDescr["@attributes"]["TourOpCode"] : null) : null;
							// make sure that the date is for the current tour operator
							if (($topContext && (!$this->TourOperatorRecord || !$this->TourOperatorRecord->ApiContext || 
								($this->TourOperatorRecord->ApiContext != $topContext))) || (strtotime($date) < $__todayTime))
							{
								echo "<div style='color: red;'>Date [{$date}] - passed</div>";
								continue;
							}
							$topDates[$dateK] = $dateDescr;
						}
						if (count($topDates) > 0)
						{
							$departure["Dates"]["Date"] = $topDates;
							$topDepartures[$depK] = $departure;
						}
					}
					if (count($topDepartures) > 0)
					{
						$destination["Departures"]["Departure"] = $topDepartures;
						$topDestinations[$destK] = $destination;
					}
				}
				if (count($topDestinations) > 0)
				{
					$country['Destinations']['Destination'] = $topDestinations;
					$toProcessCountries[$countryK] = $country;
				}
			}
			
			/*
			foreach ($datesCountByZone ?: [] as $zoneCode => $departuresByIndex)
			{
				foreach ($departuresByIndex ?: [] as $departIndex => $datesByCount)
				{
					if (count($datesByCount) > 1)
					{
						foreach ($datesByCount ?: [] as $dateCount => $dateData)
						{
							list($dateDataDepart, $dateDataDestination) = reset($dateData);
							qvardump('$datesByCount - multiple counts: ' . $dateCount, $zoneCode, $dateDataDestination['ZoneName'], $departIndex, $dateDataDepart['CityName'], $dateDataDestination['CityCode'], $dateDataDestination['CityName']);
						}
						
					}
				}
			}
			*/

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
			

			// go through countries
			foreach ($toProcessCountries ?: [] as $country)
			{
				if (!$country["CountryCode"])
				{
					echo "<div style='color: red;'>Country not ok: " . json_encode($country) . "</div>";
					continue;
				}
				try
				{
					$countryObj = $this->saveCharters_setupCountry($country["CountryCode"], $dbCountriesByCode, $countries);
				}
				catch (\Exception $ex)
				{
					echo "<div style='color: red;'>Country not on EX: {$ex->getMessage()}</div>";
					continue;
				}
				$destinations = ($country['Destinations'] && $country['Destinations']['Destination']) ? $country['Destinations']['Destination'] : null;
				if ((!$destinations) || (count($destinations) === 0))
				{
					echo "<div style='color: red;'>No destinations for country [{$country["CountryCode"]}]<br/>";
					continue;
				}

				if ($destinations["CityCode"])
					$destinations = [$destinations];

				foreach ($destinations as $destination)
				{
					$destinationCounty = null;
					if ($destination["ZoneCode"])
					{
						try
						{
							$destinationCounty = $this->saveCharters_setupCounty($destination, $countryObj, $dbCountiesByInTopIds, $counties, true);
						}
						catch (\Exception $ex)
						{
							echo "<div style='color: red;'>County saving ex: {$ex->getMessage()}</div>";
							continue;
						}
					}

					$destinationCity = null;
					if ($destination["CityCode"])
					{
						
						try
						{
							$destinationCity = $this->saveCharters_setupCity($destination, $countryObj, $dbCitiesByInTopIds, $cities, $destinationCounty);
						}
						catch (\Exception $ex)
						{
							echo "<div style='color: red;'>{$ex->getMessage()}</div>";
							continue;
						}
					}

					$departures = ($destination["Departures"] && $destination["Departures"]["Departure"]) ? $destination["Departures"]["Departure"] : null;
					if (!($departures))
					{
						echo "<div style='color: red;'>No departures for destination [{$destination["CityCode"]}] [{$country["CountryCode"]}]<br/>";
						continue;
					}

					if (isset($departures["CountryCode"]))
						$departures = [$departures];

					foreach ($departures ?: [] as $departure)
					{
						if (!$departure["CountryCode"])
						{
							echo "<div style='color: red;'>No country for destination [{$departure["CityCode"]}] [{$departure["ZoneCode"]}]<br/>";
							continue;
						}
						try
						{
							$departureCountryObj = $this->saveCharters_setupCountry($departure["CountryCode"], $dbCountriesByCode, $countries);
						}
						catch (\Exception $ex)
						{
							echo "<div style='color: red;'>{$ex->getMessage()}</div>";
							continue;
						}

						$departureCounty = null;
						if ($departure["ZoneCode"])
						{
							try
							{
								$departureCounty = $this->saveCharters_setupCounty($departure, $departureCountryObj, $dbCountiesByInTopIds, $counties);
							}
							catch (\Exception $ex)
							{
								echo "<div style='color: red;'>{$ex->getMessage()}</div>";
								continue;
							}
						}

						$departureCity = null;
						if ($departure["CityCode"])
						{
							try
							{
								$departureCity = $this->saveCharters_setupCity($departure, $departureCountryObj, $dbCitiesByInTopIds, $cities, $departureCounty);
							}
							catch (\Exception $ex)
							{
								echo "<div style='color: red;'>{$ex->getMessage()}</div>";
								continue;
							}
						}

						if ((!$destinationCity) || (!$destinationCity->InTourOperatorId) || (!$departureCity) || (!$departureCity->InTourOperatorId))
						{
							echo "<div style='color: red;'>Destination/Departure not found!</div>";
							continue;
						}

						$ct_indx = $transportType . "~" . get_class($departureCity) . "|" . $departureCity->InTourOperatorId
							. "~" . get_class($destinationCity) . "|" . $destinationCity->InTourOperatorId;

						// get the transport from cache if any, from existing or new
						$transport = $cachedTransports[$ct_indx] ?: ($cachedTransports[$ct_indx] = 
							($existingTransports[$ct_indx] ?: new \Omi\TF\CharterTransport()));

						//echo "<div style='color: blue;'>" . $ct_indx . "</div>";

						// setup transport
						$this->setupTransport($transport, $transportType, $departureCity, $destinationCity);

						// transport dump data used for requests
						$transport_dmp_data = $this->getTransportDumpData($transport);

						$dates = ($departure["Dates"] && $departure["Dates"]["Date"]) ? $departure["Dates"]["Date"] : null;

						$requestsDestination = ($destinationCounty && (!$forceToCity)) ? $destinationCounty : $destinationCity;

						if ((!$requestsDestination) || (!$requestsDestination->InTourOperatorId))
							continue;

						$processedDates = [];							
						if (is_string($dates) || (is_array($dates) && isset($dates["@attributes"])))
							$dates = array($dates);

						$_dpos = 0;
						foreach ($dates ?: [] as $dateDescr)
						{
							$isArr = is_array($dateDescr);
							$date = $isArr ? $dateDescr[0] : $dateDescr;

							$topContext = $isArr ? (($dateDescr["@attributes"] && $dateDescr["@attributes"]["TourOpCode"]) ? $dateDescr["@attributes"]["TourOpCode"] : null) : null;

							// make sure that the date is for the current tour operator
							if ($topContext && (!$this->TourOperatorRecord || !$this->TourOperatorRecord->ApiContext || 
								($this->TourOperatorRecord->ApiContext != $topContext)))
							{
								continue;
							}
							
							$checkin = date("Y-m-d", strtotime($date));

							// skip passed dates
							if (strtotime($date) < $__todayTime)
							{
								if (($dateObj = $transport->_dates[$checkin]))
								{
									$dateObj->markForCleanup();
								}
								continue;
							}

							// 7 nights by default
							$nights = $isArr ? (($dateDescr["@attributes"] && $dateDescr["@attributes"]["Nights"]) ? 
								explode(",", $dateDescr["@attributes"]["Nights"]) : [7]) : [7];

							if (is_scalar($nights))
								$nights = [$nights];

							// setup transport date
							list($dateObj) = $this->setupTransportDate($transport, $checkin, $transportType);

							$processedNights = [];
							foreach ($nights ?: [] as $_nights)
							{	
								$_nights = (int)$_nights;
								list($_nightsObj) = $this->setupTransportDateDuration($transport, $dateObj, $_nights);
								$processedNights[$_nights] = $_nightsObj;
							}

							if (count($processedNights) > 0)
							{
								$processedDates[$checkin] = $checkin;
								$this->addDateToTransport($transport, $dateObj);

								foreach ($processedNights ?: [] as $_nights => $_nObj)
								{
									$params = [
										"VacationType" => "charter",
										"Transport" => $transportType,
										"CurrencyCode" => static::$DefaultCurrency,
										"CountryCode" => $destinationCity->Country->Code,
										"DepCountryCode" => $departureCity->Country->Code,
										"DepCityCode" => $departureCity->InTourOperatorId,
										"PeriodOfStay" => [
											["CheckIn" => $checkin],
											["CheckOut" => date("Y-m-d", strtotime($date . " + " . $_nights . "days"))]
										],
										"Days" => 0,
										"Rooms" => static::$RoomsDefault
									];

									if ($this->TourOperatorRecord->ApiContext)
										$params["TourOpCode"] = $this->TourOperatorRecord->ApiContext;

									$params[($requestsDestination instanceof \Omi\County) ? "Zone" : "CityCode"] = $requestsDestination->InTourOperatorId;

									$reqs_out .= "<div style='color: darkorange;'>"
										. "REQUEST SAVED :: " . $transport_dmp_data . "[{$checkin}] -> [{$_nights}]"
									. "</div>";

									
									$request = new \Omi\TF\Request();
									$request->setClass(get_called_class());
									$request->setAddedDate(date("Y-m-d H:i:s"));
									$request->setMethod("PullChartersHotels");
									$request->setParams(json_encode([$this->TourOperatorRecord->Handle, $params, $_nights]));
									$request->setType(\Omi\TF\Request::Charter);
									$request->setTransportType($transportType);
									$request->setDestinationIndex($requestsDestination->InTourOperatorId);
									$request->setDepartureIndex($departureCity->InTourOperatorId);
									$request->setDepartureDate($checkin);
									$request->setDuration($_nights);
									$request->setTourOperator($this->TourOperatorRecord);
									$request->setupUniqid();

									$reqUUID = $request->UniqId;
									if (!($useReq = $requests[$reqUUID]))
										$requests[$reqUUID] = $useReq = $request;
									if (!$useReq->TransportsNights_Raw)
										$useReq->TransportsNights_Raw = new \QModelArray();
									$useReq->TransportsNights_Raw[] = $_nObj;

									/*
									$req_indx = $request->TourOperator->Handle . "_"
										. $request->TransportType . "_"
										. $request->DepartureIndex . "_"
										. $request->DestinationIndex . "_"
										. ($request->DepartureDate ? date("Y-m-d", strtotime($request->DepartureDate)) : "") . "_"
										. $request->Duration;

									echo "REQ_INDX for nights [" . $_nObj->getId() . "] : " . $req_indx . '<br/>';
									*/

								}
								$_dpos++;
							}

							// remove nights that are not longer available
							foreach ($dateObj->_nights ?: [] as $_nObj)
							{
								if (!isset($processedNights[$_nObj->Nights]))
									$_nObj->markForCleanup();
							}
						}

						foreach ($transport->_dates ?: [] as $_dindx => $dateObj)
						{
							if (!isset($processedDates[$_dindx]))
								$dateObj->markForCleanup();
						}

						// if we have processed dates - add the transport
						if (count($processedDates) > 0)
							$processedTransports[$ct_indx] = $ct_indx;

						if ((count($transport->Dates) > 0) || (count($transport->_dates) > 0))
						{
							// setup transport on data
							$chartersTransports[$ct_indx] = $transport;
						}
					}
				}
			}

			// deactivate unprocessed transports
			foreach ($existingTransports as $tindx => $transport)
			{
				#echo '<div style="color: green;">Check transport: ' . $tindx . '</div>';
				if (!isset($processedTransports[$tindx]))
				{
					#echo '<div style="color: red;">Mark for remove the transport: ' . $tindx . ' | ' . $transport->getId() . '</div>';
					$transport->markForCleanup(true);
				}
				$chartersTransports[$tindx] = $transport;
			}

			// filter transports
			$chartersTransports = $this->saveCachedData_filterTransports($chartersTransports);

			if (count($countries))
			{
				$newApp = \QApp::NewData();
				$newApp->setCountries(new \QModelArray($countries));
				$newApp->save(true);
			}

			if (count($counties))
			{
				$newApp = \QApp::NewData();
				$newApp->setCounties(new \QModelArray($counties));
				$newApp->save(true);
			}

			if (count($cities))
			{
				$newApp = \QApp::NewData();
				$newApp->setCities(new \QModelArray($cities));
				$newApp->save(true);
			}

			if (count($chartersTransports))
			{
				$newApp = \QApp::NewData();
				$newApp->setChartersTransports(new \QModelArray($chartersTransports));
				$newApp->save(true);
				
				$transportsIds = [];
				foreach ($chartersTransports ?: [] as $transport)
					$transportsIds[$transport->getId()] = $transport->getId();
				if (count($transportsIds))
					\Omi\TF\TransportDateNights::QSyncForCharters(["IdIN" => [$transportsIds]]);
			}

			// save requests after transports are saved
			$requestsUIDS = $this->saveCachedData_saveRequests($requests);

			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $tStartProcess));

			\Omi\TF\TransportDateNights::LinkRequests_ForCharters($this->TourOperatorRecord->Handle);
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		
		return $requestsUIDS;
	}
	
	/**
	 * Save charter transports
	 * From city, To City, County, prices, flight company, price and so on
	 * 
	 * @return null
	 */
	public function saveCharters_Plane($config = null, $force = false)
	{
		return $this->saveCharters_ByTransportType("plane", $force, $config);
	}
	
	/**
	 * Save charter transports
	 * From city, To City, County, prices, flight company, price and so on
	 * 
	 * @return null
	 */
	public function saveCharters_Bus($config = null, $force = false)
	{
		return $this->saveCharters_ByTransportType("bus", $force, $config);
	}
	/**
	 * Save charter transports
	 * From city, To City, County, prices, flight company, price and so on
	 * 
	 * @return null
	 */
	public function saveCharters($config = [], $force = false)
	{
		if (!$this->TourOperatorRecord)
			throw new \Exception("Tour OP not found!");

		// first we must resync countries
		$this->resyncCountries(true);

		$planeRequestsUuids = $this->saveCharters_Plane($config, $force);
		$busRequestsUuids = $this->saveCharters_Bus($config, $force);
		
		$allRequestsUUIDS = ($planeRequestsUuids ?: []) + ($busRequestsUuids ?: []);

		$this->saveCachedData_doRequestsCleanup($allRequestsUUIDS, ["VacationType" => "charter"]);

		static::$_CacheData = [];
		static::$_LoadedCacheData = [];
	}
	
	public function setupTransportsCacheData_Hotels_topCust(array &$toCacheData, \Omi\TF\TransportDateNights $retNightsObj, array $hotelData = [], 
		\Omi\Travel\Merch\Hotel $hotel = null, array $params = null, \Omi\TF\Request $request = null, bool $showDiagnosis = false)
	{
		$hasAirportTaxesIncluded = false;
		$hasTransferIncluded = false;
		$hasMedicalInsurenceIncluded = false;

		$offers = isset($hotelData["Offers"]["Offer"]) ? $hotelData["Offers"]["Offer"] : null;
		if ($offers && is_array($offers) && isset($offers["@attributes"]))
			$offers = [$offers];

		$minOffer = null;
		$minAvailableOffer = null;
		foreach ($offers ?: [] as $offer)
		{
			// determine the offer price
			$price = 0;
			// setup other services
			$services = ($offer['PriceDetails'] && $offer['PriceDetails']['Services'] && $offer['PriceDetails']['Services']['Service']) ? 
				$offer['PriceDetails']['Services']['Service'] : null;

			if ($services && isset($services["Code"]))
				$services = [$services];
			
			$lwAv = strtolower(($offer['Availability'] && is_array($offer['Availability'])) ? $offer['Availability'][0] : "no");
			if ($lwAv === "immediate")
				$availability = 'yes';
			else if ($lwAv === "onrequest")
				$availability = 'ask';
			else if ($lwAv === "stopsales")
				$availability = "no";
			if (!isset($offer["Gross"]))
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
			else
				$price = $offer["Gross"];

			$offer["__det_price__"] = $price;
			$offer["__det_availability__"] = $availability;
			if (($minOffer === null) || ($minOffer["__det_price__"] > $price))
				$minOffer = $offer;

			if (($availability != "no") && (($minAvailableOffer === null) || ($minAvailableOffer["__det_price__"] > $price)))
				$minAvailableOffer = $offer;
		}

		if (!($useOffer = $minAvailableOffer ?: $minOffer))
		{
			if ($showDiagnosis)
			{
				echo "<div style='color: red;'>No offers on hotel</div>";
			}
			return;
		}

		if (!($currencyCode = $useOffer["@attributes"]["CurrencyCode"]))
			throw new \Exception("Offer currency not provided!");

		// setup
		if (!($currency = static::GetCurrencyByCode($currencyCode)))
		{
			throw new \Exception("Undefined currency [{$currencyCode}]!");
		}
		
		$price = (float)$useOffer["__det_price__"];
		$fullPrice = (float)$useOffer["PriceNoRedd"];
		$initialPrice = $fullPrice ?: $price;
		$status = $useOffer["__det_availability__"];

		$rooms = $useOffer["BookingRoomTypes"]["Room"];
		if ($rooms && isset($rooms["@attributes"]))
			$rooms = [$rooms];

		$room = "";
		$r_pos = 0;
		foreach ($rooms ?: [] as $r_tmp)
		{
			#$roomAttrs = $room["@attributes"];
			unset($r_tmp["@attributes"]);
			$roomName = trim(reset($r_tmp));
			$room .= (($r_pos > 0) ? ", " : "") . $roomName;
			$r_pos++;
		}

		if (empty(trim($room)))
			$room = null;

		$mealsTypes = [];
		$meals = $useOffer["Meals"]["Meal"];
		if ($meals && isset($meals["@attributes"]))
			$meals = [$meals];
		foreach ($meals ?: [] as $meal)
		{
			unset($meal["@attributes"]);
			$mealType = trim(reset($meal));
			if ($mealType)
				$mealsTypes[$mealType] = $mealType;
		}

		$meal = (!empty($mealsTypes)) ? implode(", ", $mealsTypes) : null;

		$isEarlyBooking = false;
		$isSpecialOffer = false;
		$discount = null;

		$this->setupTransportsCacheData($toCacheData, $retNightsObj, $hotel, null, $params, $price, $initialPrice, $discount, $currency, $status, 
			$room, $meal, $hasAirportTaxesIncluded, $hasTransferIncluded, $hasMedicalInsurenceIncluded, $isEarlyBooking, $isSpecialOffer, $request, $showDiagnosis);
	}

	/**
	 * @api.enable
	 * @param type $params
	 */
	public static function PullChartersHotels($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false, $force = false)
	{
		$reportType = "charter-request";
		if ((!$force) && ($request->Force))
			$force = $request->Force;

		ob_start();
		// determine storage
		$storage = \QApp::GetStorage('travelfuse')->getChildStorage($handle);
		if ((!$storage) || (!$storage->TourOperatorRecord))
		{
			$exception = new \Exception(\Omi\App::Q_ERR_USER . ": No storage found or not connected [{$storage->TourOperatorRecord->Handle}]");
			if ($storage)
			{
				$storage->startTrackReport($reportType, $request);
				$storage->closeTrackReport(ob_get_clean(), $exception);
			}
			throw new \Exception($exception);
		}

		if (!$params["CurrencyCode"])
			$params["CurrencyCode"] = static::$DefaultCurrency;

		if (!$params['Rooms'])
			$params['Rooms'] = static::$RoomsDefault;

		if (!$params["VacationType"])
			$params["VacationType"] = "charter";

		if (isset($params["Rooms"][0]["Room"]) && (!isset($params["Rooms"][0]["Room"]["NoChildren"])))
			$params["Rooms"][0]["Room"]["NoChildren"] = 0;

		if (isset($params["Rooms"][0]["Room"]["NoAdults"]))
			$params["Rooms"][0]["Room"]["NoAdults"] = (int)$params["Rooms"][0]["Room"]["NoAdults"];

		if (isset($params["Rooms"][0]["Room"]["NoChildren"]))
			$params["Rooms"][0]["Room"]["NoChildren"] = (int)$params["Rooms"][0]["Room"]["NoChildren"];

		if (isset($params["Days"]))
			$params["Days"] = (int)$params["Days"];

		$storage->startTrackReport($reportType, $request);
		$showDiagnosis = true;

		$t1 = microtime(true);
		
		$refThis = $storage;

		$toSaveProcessesAll = [];

		$__ex = null;
		try
		{
			$objs = [];

			// determine checkin
			if (!($checkin = (isset($params["PeriodOfStay"]) ? reset($params["PeriodOfStay"])["CheckIn"] : $params['DepartureDate'])))
				throw new \Exception("Checkin not provided!");

			$checkin = date("Y-m-d", strtotime($checkin));
			
			if ($params['TransportType'])
			{
				if (!$params['Transport'])
					$params['Transport'] = $params['TransportType'];
				unset($params['TransportType']);
			}

			if (!($transport = $params['Transport']))
				throw new \Exception('Transport type not provided!');

			if (!($destination = ($params["Zone"] ?: ($params["CityCode"] ?: $params['Destination']))))
				throw new \Exception('Destination not provided!');

			$destinationType = $params['DestinationType'];

			if ((!$destinationType) && ($params['Zone'] || $params['CityCode']))
				$destinationType = $params['Zone'] ? 'county' : 'city';

			if ((!isset($params['Zone'])) && (!isset($params['CityCode'])))
			{
				if (!$destinationType)
					throw new \Exception('Destination type not provided!');

				if ($destinationType == 'county')
					$params['Zone'] = $destination;
				else if ($destinationType == 'city')
					$params['CityCode'] = $destination;

				if ((!isset($params['Zone'])) && (!isset($params['CityCode'])))	
					throw new \Exception('Destination not ok in params!');
			}

			if (!($departure = ($params["DepCityCode"] ?: $params["Departure"])))
				throw new \Exception('Departure not provided!');

			$departureType = $params["DepartureType"];
			if ((!$departureType) && $params['DepCityCode'])
				$departureType = 'city';

			if (!isset($params["DepCityCode"]))
			{
				if (!$departureType)
					throw new \Exception('Departure type not provided!');

				if ($departureType == 'city')
					$params['DepCityCode'] = $departure;

				if (!isset($params["DepCityCode"]))
					throw new \Exception('Departure not ok in params!');
			}

			if ((!$_nights) && $params['Duration'])
				$_nights = $params['Duration'];

			$__qp = [
				//"FromInTOPId" => $params["DepCityCode"],
				"From" => $departure,
				"TransportType" => $transport,
				"TourOperator" => $storage->TourOperatorRecord->getId(),
				"Date" => $checkin,
				"Nights" => $_nights
			];

			$__qp[($destinationType == 'county') ? "County" : "City"] = $destination;

			if (!$params['PeriodOfStay'])
			{
				$params['PeriodOfStay'] = [
					["CheckIn" => $checkin],
					["CheckOut" => date("Y-m-d", strtotime($checkin . " + " . $_nights . "days"))]
				];
			}

			// arrange params by key
			ksort($params);

			// get req file handle for lite date dump
			$reqFileH = $storage->getRequestDataFileHandle($request);

			//get cached transports from params
			$cachedTransports = $storage->getCachedTransportsForRequest($__qp, false, $reqFileH);
			
			$objs = [];

			if (!isset($params['Days']))
				$params['Days'] = 0;

			if (!isset($params['TourOpCode']))
				$params['TourOpCode'] = $storage->TourOperatorRecord->ApiContext;

			if (!$params['DepCountryCode'])
			{
				if (!$departureType)
					throw new \Exception('Departure type must be provided in order to determine the country!');

				$departureObj = 
					($departureType == 'city') ? 
					QQuery('Cities.{*, Country.{
							Code, Name, 
								InTourOperatorsIds.{
									TourOperator, Identifier 
									WHERE 1 
										??TourOperatorHandle?<AND[TourOperator.Handle=?]
								}
							} 
						WHERE 1 
							??InTourOperatorId?<AND[InTourOperatorId=?]
							??TourOperatorHandle?<AND[TourOperator.Handle=?]							
					}', ['InTourOperatorId' => $departure, 'TourOperatorHandle' => $storage->TourOperatorRecord->Handle])->Cities[0] : 
					(($departureType == 'county') ? QQuery('Counties.{*, Country.{
							Code, Name, 
								InTourOperatorsIds.{
									TourOperator, Identifier 
									WHERE 1 
										??TourOperatorHandle?<AND[TourOperator.Handle=?]
								} 
							} 
						WHERE 1 
							??InTourOperatorId?<AND[InTourOperatorId=?]
							??TourOperatorHandle?<AND[TourOperator.Handle=?]
					}', ['InTourOperatorId' => $departure, 'TourOperatorHandle' => $storage->TourOperatorRecord->Handle])->Counties[0] : null);

				$depCountryObj = $departureObj ? $departureObj->Country : null;
				
				$params['DepCountryCode'] = $depCountryObj ? $depCountryObj->getTourOperatorIdentifier($storage->TourOperatorRecord) : null;

				if (!$params['DepCountryCode'])
					throw new \Exception('DepCountryCode not provided!');
			}


			if (!$params['CountryCode'])
			{
				if (!$destinationType)
					throw new \Exception('Destination type must be provided in order to determine the country!');

				$destinationObj = 
					($destinationType == 'city') ? 
					QQuery('Cities.{*, Country.{
							Code, Name, 
								InTourOperatorsIds.{
									TourOperator, Identifier 
									WHERE 1 
										??TourOperatorHandle?<AND[TourOperator.Handle=?]
								}
							} 
						WHERE 1 
							??InTourOperatorId?<AND[InTourOperatorId=?]
							??TourOperatorHandle?<AND[TourOperator.Handle=?]							
					}', ['InTourOperatorId' => $destination, 'TourOperatorHandle' => $storage->TourOperatorRecord->Handle])->Cities[0] : 
					(($destinationType == 'county') ? QQuery('Counties.{*, Country.{
							Code, Name, 
								InTourOperatorsIds.{
									TourOperator, Identifier 
									WHERE 1 
										??TourOperatorHandle?<AND[TourOperator.Handle=?]
								} 
							} 
						WHERE 1 
							??InTourOperatorId?<AND[InTourOperatorId=?]
							??TourOperatorHandle?<AND[TourOperator.Handle=?]
					}', ['InTourOperatorId' => $destination, 'TourOperatorHandle' => $storage->TourOperatorRecord->Handle])->Counties[0] : null);

				$destinationCountry = $destinationObj ? $destinationObj->Country : null;
				
				$params['CountryCode'] = $destinationCountry ? $destinationCountry->getTourOperatorIdentifier($storage->TourOperatorRecord) : null;

				if (!$params['CountryCode'])
					throw new \Exception('CountryCode not provided!');
			}

			$indx_params = [
				'VacationType' => 'charter',
				'Transport' => $transport,
				'Rooms' => $params['Rooms'],
				'PeriodOfStay' => $params['PeriodOfStay'],
				'DepCityCode' => $departure,
				'DepCountryCode' => $params['DepCountryCode'],
				'Days' => $params['Days'] ? (int)$params['Days'] : 0,
			];

			if ($destinationType == 'county')
				$indx_params['Zone'] = $destination;
			else if ($destinationType == 'city')
				$indx_params['CityCode'] = $destination;

			$indx_params['CountryCode'] = $params['CountryCode'];

			ksort($indx_params);

			$tf_req_id = md5($storage->TourOperatorRecord->Handle . "|" . json_encode($indx_params));

			$callParams = $params;
			$callKeyIdf = md5(json_encode($callParams));
			$callMethod = "getPackageNVPriceRequest";

			unset($callParams['Departure']);
			unset($callParams['DepartureDate']);
			unset($callParams['DepartureType']);
			unset($callParams['Destination']);
			unset($callParams['DestinationType']);
			unset($callParams['Duration']);
			unset($callParams['TransportIndx']);
			unset($callParams['TourOperator']);

			ksort($callParams);
			$callParams["__request_data__"]["ID"] = $tf_req_id;
			\Omi\TFuse\Api\TravelFuse::$_CacheData["AllTopsReqsById"][$tf_req_id] = $callParams;
			\Omi\TFuse\Api\TravelFuse::$_CacheData["AllTopsReqsById_Top"][$tf_req_id] = $storage->TourOperatorRecord->Handle;

			// get packages
			if (!$skip_cache)
			{
				list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = 
					static::ExecCacheable([$callMethod => $callMethod], function ($refThis, $callMethod, $callParams, $callKeyIdf, $force, $storage, $request) {
					return $storage->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
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
						}, $callMethod, $callParams, $callKeyIdf, $force, ($request ? $request->UniqId : null));
				}, [$refThis, $callMethod, $callParams, $callKeyIdf, $force, $storage, $request]);
			}
			else
			{
				list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = 
						$storage->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
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
				}, $callMethod, $callParams, $callKeyIdf, $force, ($request ? $request->UniqId : null));
			}

			if ((!$return) || ($alreadyProcessed && (!$force)))
			{
				if (!$return)
					$storage->TrackReport->_noResponse = true;
				if ($alreadyProcessed)
					$storage->TrackReport->_responseNotChanged = true;
				#echo $alreadyProcessed ? "Nothing changed from last request!" : "No return";
				#$storage->closeTrackReport(ob_get_clean());
				$storage->closeTrackReport($alreadyProcessed ? "Nothing changed from last request!" : "No return");
				return $request;
			}

			$packages = static::GetResponseData($return, "getPackageNVPriceResponse");
			
			$hotels = ($packages && $packages["Hotel"]) ? $packages["Hotel"] : null;
			if ($hotels && $hotels["Product"])
				$hotels = [$hotels];

			$appData = \QApp::NewData();
			$appData->Hotels = new \QModelArray();
			$appData->ChartersTransports = new \QModelArray();
			$appData->NoResultsRequests = new \QModelArray();

			$codes = [];
			$countriesCodes = [];
			foreach ($hotels ?: [] as $hotelData)
			{
				if (!$hotelData["Product"] || !$hotelData["Product"]["TourOpCode"] || 
					($hotelData["Product"]["TourOpCode"] != $storage->TourOperatorRecord->ApiContext) || !$hotelData["Product"]["ProductCode"])
				continue;

				$codes[$hotelData["Product"]["ProductCode"]] = $hotelData["Product"]["ProductCode"];

				if (!$hotelData["Product"]["CountryCode"])
					continue;
				$countriesCodes[$hotelData["Product"]["CountryCode"]] = $hotelData["Product"]["CountryCode"];
			}

			if (count($countriesCodes) > 0)
				$storage->FindExistingItem("Countries", $countriesCodes, "Code, Name, InTourOperatorsIds.{TourOperator, Identifier}");

			// load hotels from cache
			if (count($codes) > 0)
			{
				$storage->FindExistingItem("Hotels", $codes, "Code, "
					. "MTime,"
					. "Name,"
					. "Stars,"
					. "Master,"
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
							. "InTourOperatorId,"
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
								. "Name,"
								. "InTourOperatorsIds.{"
									. "TourOperator.Handle,"
									. "Identifier"
								. "}"
							. "}"
						. "}, "
						. "County.{"
							. "InTourOperatorId,"
							. "Code, "
							. "Name, "
							. "Country.{"
								. "Code, "
								. "Name"
							. "}"
						. "}, "
						. "Country.{"
							. "Code, "
							. "Name,"
							. "InTourOperatorsIds.{"
								. "TourOperator.Handle,"
								. "Identifier"
							. "}"
						. "}, "
						. "Latitude, "
						. "Longitude"
					. "}");
			}

			$__ttime = strtotime(date("Y-m-d"));

			$mealsTypes = [];
			$__hasBookable = false;

			$toSetupHasTravelItemFlagOnHotel = [];

			$reqResults = [];
			$reqAllResults = [];

			foreach ($hotels ?: [] as $hotelData)
			{
				if (!$hotelData["Product"] || !$hotelData["Product"]["TourOpCode"] || 
					($hotelData["Product"]["TourOpCode"] != $storage->TourOperatorRecord->ApiContext) || !$hotelData["Product"]["ProductCode"])
				{
					echo '<div style="color: red; font-weight: bold;">Hotel [' . $hotelData["Product"]['ProductName'] . '] skipped because touroperator code [' . $hotelData["Product"]["TourOpCode"] . '] is different from turoperator record code [' . $storage->TourOperatorRecord->ApiContext . ']</div>';					
					continue;
				}

				$hotel = $storage->FindExistingItem("Hotels", $hotelData["Product"]["ProductCode"], "Code, "
					. "MTime,"
					. "Name,"
					. "Stars,"
					. "Master,"
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
							. "InTourOperatorId,"
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
								. "Name,"
								. "InTourOperatorsIds.{"
									. "TourOperator.Handle,"
									. "Identifier"
								. "}"
							. "}"
						. "}, "
						. "County.{"
							. "InTourOperatorId,"
							. "Code, "
							. "Name, "
							. "Country.{"
								. "Code, "
								. "Name"
							. "}"
						. "}, "
						. "Country.{"
							. "Code, "
							. "Name,"
							. "InTourOperatorsIds.{"
								. "TourOperator.Handle,"
								. "Identifier"
							. "}"
						. "}, "
						. "Latitude, "
						. "Longitude"
					. "}");

				//qvardump("DB HOTEL :: ", $hotel, strtotime(date("Y-m-d", strtotime($hotel->MTime))), $__ttime);
				$dbHotelCityCode = ($hotel && $hotel->Address && $hotel->Address->City) ? $hotel->Address->City->InTourOperatorId : null;
				$dbHotelCountyCode = ($hotel && $hotel->Address && $hotel->Address->City && $hotel->Address->City->County) ? 
						$hotel->Address->City->County->InTourOperatorId : null;
				$dbHotelCountryCode = ($hotel && $hotel->Address && $hotel->Address->City && $hotel->Address->City->Country) ? 
					$hotel->Address->City->Country->getTourOperatorInstance($storage->TourOperatorRecord) : null;
				if ($dbHotelCountryCode instanceof \Omi\Country)
					$dbHotelCountryCode = $dbHotelCountryCode->Code;

				$retHotelCityCode = $hotelData["Product"]["CityCode"];
				$retHotelCountryCode = $hotelData["Product"]["CountryCode"];
				$retHotelCountyCode = $hotelData["Product"]["ZoneCode"];
				
				$geoChanged = false;
				if ($dbHotelCityCode && $retHotelCityCode)
					$geoChanged = ($dbHotelCityCode != $retHotelCityCode);
				if ((!$geoChanged) && $dbHotelCountyCode && $retHotelCountyCode)
					$geoChanged = ($dbHotelCountyCode != $retHotelCountyCode);
				if ((!$geoChanged) && $dbHotelCountryCode && $retHotelCountryCode)
					$geoChanged = ($dbHotelCountryCode != $retHotelCountryCode);
				
				// do request on hotel only if it was not saved today
				# this is not sustainable when you have 1000+ Hotels !!!!
				# if ((!$hotel) || $geoChanged || ((!$hotel->MTime || (strtotime(date("Y-m-d", strtotime($hotel->MTime))) != $__ttime)) && (!$request->SaveOnlyNewHotels)))
				if ((!$hotel) || $geoChanged)
				{
					try
					{
						if (!($hotel = $appData->Hotels[$hotelData["Product"]["ProductCode"]]))
						{
							$hparams = [
								'force' => $force,
								"ApiContext" => $hotelData["Product"]["TourOpCode"],
								"cityId" => $hotelData["Product"]["CityCode"],
								"countryId" => $hotelData["Product"]["CountryCode"],
								"travelItemId" => $hotelData["Product"]["ProductCode"],
								"travelItemName" => $hotelData["Product"]["ProductName"],
							];

							list($hotel, $hotel_err, $alreadyProcessed, $toSaveProcesses) = $storage->getHotelInfo_NEW($hparams, false, true);

							foreach ($toSaveProcesses ?: [] as $toSaveProcess)
								$toSaveProcessesAll[] = $toSaveProcess;
							if ($hotel)
								$appData->Hotels[$hotelData["Product"]["ProductCode"]] = $hotel;
						}
					}
					catch (\Exception $ex)
					{
						echo "<div style='color: red;'>Error when getting hotel info [{$ex->getMessage()}]</div>";
						continue;
					}
				}

				if (!$hotel)
					continue;

				if ((!$hotel->getId()))
				{
					$hotel->setFromTopAddedDate(date("Y-m-d H:i:s"));
					if ($storage->TrackReport)
					{
						if (!$storage->TrackReport->NewHotels)
							$storage->TrackReport->NewHotels = 0;
						$storage->TrackReport->NewHotels++;
					}
					$hotel->__added_to_system__ = true;
					echo "<div style='color: green;'>Add new hotel [{$hotel->InTourOperatorId}|{$hotel->Name}] to system</div>";
				}

				$hotelOffers = ($hotelData["Offers"] && $hotelData["Offers"]["Offer"]) ? $hotelData["Offers"]["Offer"] : null;

				if (isset($hotelOffers['@attributes']))
					$hotelOffers = [$hotelOffers];

				$itmIsBookable = false;
				$transportIsBookable = true;

				// add it to all results
				$reqAllResults[$hotelData["Product"]["ProductCode"]] = $hotelData["Product"]["ProductCode"];

				foreach ($hotelOffers as $offer)
				{
					$availability = ($offer['Availability'] && is_array($offer['Availability'])) ? strtolower($offer['Availability'][0]) : null;

					if (($availability === "immediate") || ($availability === "onrequest"))
					{
						$itmIsBookable = true;
						// setup other services
						$services = ($offer['PriceDetails'] && $offer['PriceDetails']['Services'] && $offer['PriceDetails']['Services']['Service']) ? 
							$offer['PriceDetails']['Services']['Service'] : null;

						$offIsAvailable = true;
						foreach ($services ?: [] as $serv)
						{
							if ($serv["Type"] != '7')
								continue;
							
							$srv_avb = ($serv['Availability'] && is_array($serv['Availability'])) ? $serv['Availability'][0] : null;
							$serviceAvailabilityAttrs = $serv['Availability']["@attributes"];

							if ($srv_avb != null)
							{
								//set availability
								//$offerItm->Availability = 'no';
								$lw_srv_avb = strtolower($srv_avb);
								if ($lw_srv_avb === "stopsales")
								{
									$offIsAvailable = false;
									$transportIsBookable = false;
									break;
								}
							}
							else if ($serviceAvailabilityAttrs && $serviceAvailabilityAttrs["Code"])
							{
								$lw_srv_avb = strtolower($serviceAvailabilityAttrs["Code"]);
								if ($lw_srv_avb === "st")
								{
									$offIsAvailable = false;
									$transportIsBookable = false;
									break;
								}
							}
						}

						if ($offIsAvailable)
						{
							#$itmIsBookable = true;
							$reqResults[$hotelData["Product"]["ProductCode"]] = $hotelData["Product"]["ProductCode"];

							$__hasBookable = true;
							$meals = $offer["Meals"]["Meal"];
							if ($meals && isset($meals["@attributes"]))
								$meals = [$meals];
							foreach ($meals ?: [] as $meal)
							{
								unset($meal["@attributes"]);
								$mealType = trim(reset($meal));
								if ($mealType)
								{
									$_meal_info = null;
									$meal_title_length = defined('MEAL_TITLE_LENGTH') ? MEAL_TITLE_LENGTH : 32;
									if (mb_strlen($mealType) > $meal_title_length)
									{
										$_meal_info = $mealType;
										$mealType = trim(mb_substr($mealType, 0, $meal_title_length));
									}
									$mealsTypes[$mealType] = $mealType;
								}
							}
						}
					}
				}

				if (!$hotel->Address)
					$hotel->Address = new \Omi\Address();

				// let's make sure that we have the county here
				if ($hotelData["Product"] && (!empty($hotelData["Product"]["ZoneName"])) && (!empty($hotelData["Product"]["ZoneCode"])))
				{
					$countyObj = $storage->getCountyObj($objs, $hotelData["Product"]["ZoneCode"], $hotelData["Product"]["ZoneName"], $hotelData["Product"]["ZoneCode"]);
					if (!$countyObj->Country && isset($hotel->Address->City->Country))
					{
						$countyObj->setCountry($hotel->Address->City->Country);
					}
					if ($hotel->Address->City)
					{
						if ($countyObj && $countyObj->Country && $hotel->Address->City->Country && 
								($hotel->Address->City->Country->getId() !== $countyObj->Country->getId()))
						{
							echo "<div style='color: red;'>City for hotel [{$hotel->InTourOperatorId}|{$hotel->Name}] " 
								. "is not in the same country as the county" 
								. " - city [{$hotel->Address->City->InTourOperatorId}|{$hotel->Address->City->Name}]" 
								. " - county [{$countyObj->InTourOperatorId}|{$countyObj->Name}]" 
								. "</div>";
							continue;
						}
						$hotel->Address->City->setCounty($countyObj);
					}

					$hotel->Address->setCounty($countyObj);
				}

				// setup longitude and latitude
				if (!empty($hotelData["Product"]["Longitude"]))
					$hotel->Address->setLongitude($hotelData["Product"]["Longitude"]);
				if (!empty($hotelData["Product"]["Latitude"]))
					$hotel->Address->setLatitude($hotelData["Product"]["Latitude"]);
				$appData->Hotels[$hotel->Code] = $hotel;

				$hotelDestination = $hotel->Address ? $hotel->Address->City : null;

				if (!$hotelDestination)
				{
					echo "<div style='color: red;'>Hotel [{$hotel->InTourOperatorId}|{$hotel->Name}] not linked to destination</div>";
					continue;
				}

				// setup duration data
				$nightsObj = $storage->setupDurationData($cachedTransports, $hotelDestination, $hotel, "Hotels", $request, $showDiagnosis, $reqFileH, $itmIsBookable, $transportIsBookable);

				$toSetupHasTravelItemFlagOnHotel[] = $hotel;

				if (!$nightsObj)
				{
					echo "<div style='color: red;'>Nights object not found. Hotel [{$hotel->InTourOperatorId}|{$hotel->Name}] cannot be linked to nights</div>";
					continue;
				}

				$hotel->setMTime(date("Y-m-d H:i:s"));
				$hotel->setHasCharterOffers(true);
				$hotel->setHasChartersActiveOffers(true);
				($transport === "plane") ? $hotel->setHasPlaneChartersActiveOffers(true) : $hotel->setHasBusChartersActiveOffers(true);

				if ($hotel->Master)
				{
					$hotel->Master->setHasCharterOffers(true);
					$hotel->Master->setHasChartersActiveOffers(true);
					($transport === "plane") ? $hotel->Master->setHasPlaneChartersActiveOffers(true) : $hotel->Master->setHasBusChartersActiveOffers(true);
				}
			}

			if (count($toSetupHasTravelItemFlagOnHotel))
				$storage->flagHotelsTravelItems($toSetupHasTravelItemFlagOnHotel);

			// meals types
			if (count($mealsTypes))
			{
				//qvardump("\$mealsTypes", $mealsTypes);
				\Omi\TFuse\Api\TravelFuse::SetupResults_SetupMealsAliases_FromList($mealsTypes, true);
			}

			$unexReqsBinds = ($request && $request->getId()) ? [
				"NOT" => $request->getId(),
				"TransportType" => $transport,
				"TourOperator" => $storage->TourOperatorRecord->getId(),
				"DestinationIndex" => $destination,
				"DepartureIndex" => $departure,
				"DepartureDate" => $checkin
			] : null;

			#list($__toSyncTransportsDates, $__toSyncTransports) = 
				$storage->setupCachedTransportsOnRequest($cachedTransports, $appData, $unexReqsBinds, $__hasBookable, false, $request, false);

			// write transports links to data file
			if ($reqFileH)
				$storage->writeTransportsLinksToDataFile($reqFileH, $appData->ChartersTransports);

			// close the handle if opened
			if ($reqFileH)
				fclose($reqFileH);

			# request
			if ($request)
			{
				$request->setResults(count($reqResults));
				$request->setAllResults(count($reqAllResults));
			}
			
			if ($storage->TourOperatorRecord)
				$storage->TourOperatorRecord->restoreCredentials();

			#$ret = $storage->saveInBatchHotels($appData->Hotels, 
			$storage->saveInBatchHotels($appData->Hotels, 
				"FromTopAddedDate, "
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
				. "},"
				. "HasIndividualOffers,"
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

			try
			{
				$appData->save("ChartersTransports.{"
						. "FromTopRemoved, "
						. "Dates.{"
							. "Date, "
							. "FromTopRemoved, "
							. "Active, "
							. "Hotels, "
							. "Nights.{"
								. "FromTopRemoved, "
								. "Nights,"
								. "Request,"
								. "ReqResults,"
								. "ReqAllResults,"
								. "DeactivationReason, "
								. "ReqExecLastDate, "
								. "Active, "
								. "Hotels"
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
				
					$chartersTransportsIds = [];
					foreach ($appData->ChartersTransports ?: [] as $transport)
					{
						$chartersTransportsIds[$transport->getId()] = $transport->getId();
					}

					if (count($chartersTransportsIds))
					{
						\Omi\TF\CharterTransport::SyncHotelsFromNights($chartersTransportsIds);
					}
			}
			catch (\Exception $ex)
			{
				\Omi\TFuse\Api\TravelFuse::DoDataLoggingError($storage->TourOperatorRecord->Handle, ['$appData' => $appData], $ex);
				throw $ex;
			}

			if ($tf_req_id)
			{
				\Omi\TFuse\Api\Travelfuse::RefreshTravelItemsCacheData([
					"TravelfuseReqID" => $tf_req_id
				]);
			}
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
			$storage->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, 
				(microtime(true) - $t1), ($request ? $request->UniqId : null));

			foreach ($toSaveProcessesAll ?: [] as $toSaveProcess)
			{
				list($toSaveProcess_callMethod, $toSaveProcess_callRequest, $toSaveProcess_callResponse, $toSaveProcess_callKeyIdf, 
					$toSaveProcess_callTopRequestTiming, $toSaveProcess_processTiming) = $toSaveProcess;
				$storage->setupSoapResponseAndProcessingStatus($toSaveProcess_callMethod, $toSaveProcess_callRequest, $toSaveProcess_callResponse, 
					$toSaveProcess_callKeyIdf, $toSaveProcess_callTopRequestTiming, $toSaveProcess_processTiming, ($request ? $request->UniqId : null));
			}

			$storage->closeTrackReport(ob_get_clean(), $__ex);
		}

		return $request;
	}
}