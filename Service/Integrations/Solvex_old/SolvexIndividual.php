<?php

namespace Omi\TF;

/**
 * Functions used for Individual Offers
 */
trait SolvexIndividual
{
	public function GetHotels()
	{
		$res = $this->SOAPInstance->GetHotels();
		return $res;
	}

	public function GetHotelAvailability($params, $initalParams)
	{
		//qvardump($params);
		if ($params['VacationType'] !== "individual")
			return new \QModelArray();

		$hotelIds = [];

		$hotelIdsParams = [
			"Zone" => $params['Zone'],
			"City" => $params['CityCode'],
			"TourOperator" => $this->TourOperatorRecord->getId()
		];

		if ($params["ProductCode"])
			$hotelIdsParams["Hotel"] = $params["ProductCode"];

		$all_hotels_in_city = $params["Zone"] ? 
			QQuery("Hotels.{InTourOperatorId WHERE 1 "
				. " ??TourOperator?<AND[TourOperator.Id=?]"
				. " ??Zone?<AND[Address.City.County.InTourOperatorId=?]"
				. " ??Hotel?<AND[InTourOperatorId=?]"
			. "}", $hotelIdsParams) : 
			QQuery("Hotels.{InTourOperatorId WHERE 1 "
				. " ??TourOperator?<AND[TourOperator.Id=?]"
				. " ??City?<AND[Address.City.InTourOperatorId=?]"
				. " ??Hotel?<AND[InTourOperatorId=?]"
			. "}", $hotelIdsParams);

		if ((!$all_hotels_in_city) || (!$all_hotels_in_city->Hotels))
			return new \QModelArray();

		//$ret = new \QModelArray();
		foreach ($all_hotels_in_city->Hotels as $h)
		{
			if ($h->InTourOperatorId)
				$hotelIds[] = (int)$h->InTourOperatorId;
		}

		$room_info = $params['Rooms'][0]['Room'];

		$children_ages = [];
		foreach ($room_info['Children'] ?: [] as $child_inf)
		{
			if ($child_inf['Age'])
				$children_ages[] = (int)$child_inf['Age'];
		}
		
		//$t1 = microtime(true);
		$meals = $this->SOAPInstance->getPensions();
		//qvar_dump((microtime(true) - $t1) . " seconds took pulling pensions/meals", $meals);
		//echo "<br/>";

		//$t1 = microtime(true);
		$this->SOAPInstance->login();
		
		$availabilities = $this->GetHotelAvailability_DoReq($params, $hotelIds, $room_info, $children_ages);

		//qvardump("\$availabilities", $availabilities);
		//q_die();

		//var_dump($this->SOAPInstance->client->__getLastRequestHeaders(), $this->SOAPInstance->client->__getLastRequest(), 
		//	$this->SOAPInstance->client->__getLastResponseHeaders(), $this->SOAPInstance->client->__getLastResponse());

		// qvar_dump($this->SOAPInstance->client->__getLastRequestHeaders());
		//qvar_dump('availability', $availability);

		$ret = [];
		foreach ($availabilities ?: [] as $availability)
		{
			# extract availability
			$res = $this->extractAvailability($availability);
			
			# get cancellation policy
			# $cancellation_policies = $this->getCancellationPolicy($params, $res);

			try
			{
				# build offers
				$ret_val = $this->buildOffers(is_array($res) ? $res : [$res], $params, $meals, $initalParams, $cancellation_policies);
				
				foreach ($ret_val ?: [] as $k => $v)
					$ret[$k] = $v;
			}
			catch (\Exception $ex)
			{
				throw $ex;
			}
		}

		if ($ret)
			$this->flagHotelsTravelItems($ret);

		return $ret;
	}

	public function GetHotelAvailability_DoReq($params, $hotelIds, $room_info, $children_ages)
	{
		#$hotelIdsChinks = array_chunk($hotelIds, 10);
		$hotelIdsChinks = [$hotelIds];
		$availabilities = [];

		foreach ($hotelIdsChinks ?: [] as $hids)
		{
			$request = [
				'PageSize' => 1000,
				'RowIndexFrom' => 0,
				'PartnerKey' => (int)($this->SOAPInstance->ApiContext__ ?: $this->SOAPInstance->ApiContext),
				'DateFrom' => $params['PeriodOfStay'][0]['CheckIn'].'T00:00:00',
				'DateTo' => $params['PeriodOfStay'][1]['CheckOut'].'T00:00:00',
				// 'RegionKeys' => [(int)$countyId],
				// 'RegionKeys' => [-1],
				//'CityKeys' => [(int)$params['CityCode']],
				'HotelKeys' => $hids,
				"ResultView" => 0,
				"Mode" => 0,
				"IsAddHotsWithCosts" => true,
				// 'RoomDescriptionsKeys' => [-1],
				"ValidateQuota" => true,
				'Pax' => $room_info['NoAdults'] + q_count($children_ages),
				//"__request_data__" => $params["__request_data__"]
			];

			if ($params["Zone"])
				$request["RegionKeys"] = [(int)$params["Zone"]];
			else if ($params["CityCode"])
				$request["CityKeys"] = [(int)$params["CityCode"]];
			else
				throw new \Exception("No destination for request");

			if ($children_ages)
				$request['Ages'] = $children_ages;

			// $res = $this->SOAPInstance->HotelSearch($params);
			$connected = ($this->SOAPInstance->connect_result->ConnectResult != -1);

			try
			{
				$avParams = [
					'guid' => $this->SOAPInstance->connect_result->ConnectResult,
					'request' => $request
				];
				if ($params["__request_data__"])
					$avParams["__request_data__"] = $params["__request_data__"];
				$availability = $connected ? $this->SOAPInstance->client->SearchHotelServices($avParams) : [];
			}
			catch (\Exception $ex)
			{
				$doLogging = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));
				if ($doLogging)
				{
					$keep = 1;
					$data = [
						"reqXML" => $this->SOAPInstance->client ? $this->SOAPInstance->client->__getLastRequest() : null,
						"respXML" => $this->SOAPInstance->client ? $this->SOAPInstance->client->__getLastResponse() : null,
						"reqHeaders" => $this->SOAPInstance->client ? $this->SOAPInstance->client->__getLastRequestHeaders() : null,
						"respHeaders" => $this->SOAPInstance->client ? $this->SOAPInstance->client->__getLastResponseHeaders() : null,
						"\$connected" => $connected,
						"\$request" => $request
					];
					\Omi\TFuse\Api\TravelFuse::DoDataLoggingError($this->TourOperatorRecord->Handle, $data, $ex, null, $keep);
				}
				/*
				var_dump($this->SOAPInstance->client->__getLastRequestHeaders(), $this->SOAPInstance->client->__getLastRequest(), 
					$this->SOAPInstance->client->__getLastResponseHeaders(), $this->SOAPInstance->client->__getLastResponse());
				*/

				//qvar_dump("SOLVEX => \$request", [$connected, $this->SOAPInstance->connect_result->ConnectResult, $request, $availability]);
				//throw $ex;
				// log the error here
			}

			if (!empty($availability))
				$availabilities[] = $availability;
		}

		return $availabilities;
	}
	
	/**
	 * Get cancellationpolicy
	 * 
	 * @param type $params
	 * @param type $availability
	 */
	public function getCancellationPolicy($params, $availability)
	{
		if (!is_array($availability))
			throw new \Exception('Availability extracted not array!');
		
		# check connected
		$connected = ($this->SOAPInstance->connect_result->ConnectResult != -1);
		
		$room_info = $params['Rooms'][0]['Room'];
		
		if ($connected)
		{
			$cancellation_policies = [];
			
			foreach ($availability as $hotel)
			{				
				$avParams = [
					'guid' => $this->SOAPInstance->connect_result->ConnectResult,
					'dateFrom' => $params['PeriodOfStay'][0]['CheckIn'].'T00:00:00',
					'dateTo' => $params['PeriodOfStay'][1]['CheckOut'].'T00:00:00',
					'HotelKey' => $hotel->HotelKey,
					'Pax' => $room_info['NoAdults'],
				];
				
				$ret = $this->SOAPInstance->client->GetCancellationPolicyInfoWithPenalty($avParams);
				
				if ($ret->GetCancellationPolicyInfoWithPenaltyResult && $ret->GetCancellationPolicyInfoWithPenaltyResult->Data 
					&& $ret->GetCancellationPolicyInfoWithPenaltyResult->Data->CancellationPolicyInfoWithPenalty)
				{
					if (is_array($ret->GetCancellationPolicyInfoWithPenaltyResult->Data->CancellationPolicyInfoWithPenalty))
					{
						foreach ($ret->GetCancellationPolicyInfoWithPenaltyResult->Data->CancellationPolicyInfoWithPenalty as $policy)
						{
							if ($policy->PolicyData && $policy->PolicyData->CancellationPolicyWithPenaltyValue)
							{	
								$offer_code = $policy->HotelKey . '~' . $policy->AccomodatioKey . '~' . $policy->RoomTypeKey . '~' . $policy->RoomCategoryKey . '~' . $policy->PansionKey;
								$cancellation_policies[$offer_code] = [];
								
								if (is_array($policy->PolicyData->CancellationPolicyWithPenaltyValue))
								{									
									$date_start = null;
									
									foreach ($policy->PolicyData->CancellationPolicyWithPenaltyValue as $policy_value)
									{
										if ($policy_value->PolicyKey == '-1')
											continue;
										
										if ($date_start === null)
											$date_start = $policy->CancellationDate;
										else
											$date_start = $policy_value->DateFrom;
																				
										$cancellation_policy = new \stdClass();
										$cancellation_policy->DateStart = date('Y-m-d', strtotime($date_start));
										
										if ($policy_value->DateTo)
											$cancellation_policy->DateEnd = date('Y-m-d', strtotime($policy_value->DateTo));
										else
											$cancellation_policy->DateEnd = $params['PeriodOfStay'][0]['CheckIn'];
										
										/*
										if ($policy_value->IsPercent)
										{
											qvar_dump($availability, $policy_value); die;
											
											$cancellation_policy->Price = ($availability->Cost);
										}
										else
										*/
										$cancellation_policy->Price = $policy_value->PenaltyTotal;
										
										$cancellation_policy->Currency = new \stdClass();
										if ($policy_value->Currency == 'EU')
											$cancellation_policy->Currency->Code = 'EUR';
										else
											throw new \Exception('Currency not found :: cancellation policy');
										
										$cancellation_policies[$offer_code][] = $cancellation_policy;
									}
								}
								else
								{
									$policy_value = $policy->PolicyData->CancellationPolicyWithPenaltyValue;
									
									$cancellation_policy = new \stdClass();
									
									$cancellation_policy->DateStart = date('Y-m-d', strtotime($policy->CancellationDate));
									
									if ($policy_value->DateTo)
										$cancellation_policy->DateEnd = date('Y-m-d', strtotime($policy_value->DateTo));
									else
										$cancellation_policy->DateEnd = $params['PeriodOfStay'][0]['CheckIn'];
									
									/*
									if ($policy_value->IsPercent)
									{
										qvar_dump($availability, $policy_value); die;

										$cancellation_policy->Price = ($availability->Cost);
									}
									else
									*/
									$cancellation_policy->Price = $policy_value->PenaltyTotal;
									
									$cancellation_policy->Currency = new \stdClass();
									if ($policy_value->Currency == 'EU')
										$cancellation_policy->Currency->Code = 'EUR';
									else
										throw new \Exception('Currency not found :: cancellation policy');
									
									$cancellation_policies[$offer_code][] = $cancellation_policy;
								}
							}
						}
					}
					else
						throw new \Exception('Cancellation policies not array!');
				}
			}
		}
		
		# qvar_dump($cancellation_policies); die;
	}

	public function buildOffers($api_offers, $parameters, $meals, $initialParams, $cancellation_policies = null)
	{
		if (!$api_offers)
			return [];
		
		if ($parameters["ProductCode"])
		{
			$new_api_offers = [];
			foreach ($api_offers ?: [] as $api_offer)
			{
				if (($hotelId = $api_offer->HotelKey) && ($hotelId == $parameters["ProductCode"]))
					$new_api_offers[] = $api_offer;
			}
			$api_offers = $new_api_offers;
		}

		$objs = [];
		$ret = [];
		$reqParams = static::GetRequestParams($parameters);
		$offerType = "\Omi\Travel\Offer\Stay";	
		$toLoadHotels = [];
		foreach ($api_offers ?: [] as $api_offer)
		{
			$hotelId = $api_offer->HotelKey;
			$toLoadHotels[$hotelId] = $hotelId;
		}

		$useEntity = "ContactPerson.{Email, Phone, Fax},"
			. "Facilities.{Name, Type, Active, Icon, IconHover, ListingOrder},"
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
				. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
				. "InTourOperatorId, "
				. "Alt"
			. "}, "
			. "Active, "
			. "ShortContent, "
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
						. "TourOperator.{Handle, Caption, Abbr}, "
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
		// first load all hotels
		$this->FindExistingItem("Hotels", $toLoadHotels, $useEntity);
		
		// setup 
		if (!($suppliedCurrency = static::GetCurrencyByCode($parameters["CurrencyCode"])))
		{
			throw new \Exception("Undefined currency [{$parameters["CurrencyCode"]}]!");
		}	
		if (!($sellCurrency = static::GetCurrencyByCode($parameters["SellCurrency"])))
		{
			throw new \Exception("Undefined currency [{$parameters["SellCurrency"]}]!");
		}

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

		$passedHotels = [];	
		if ($DO_TF_SYNC)
		{
			$toPullHotelsFromTF = [];
			$isHotelSearch = $initialParams['ProductCode'] ? true : false;
			foreach ($api_offers ?: [] as $api_offer)
			{
				if (!($hotelObj = $passedHotels[$api_offer->HotelKey]))
				{
					$hotelObj = $this->GetHotel($api_offer, $objs);
					if ((!$hotelObj) || (!$hotelObj->getId()))
					{
						$toPullHotelsFromTF[$api_offer->HotelKey] = $api_offer->HotelKey;
						continue;
					}

					# if we have the hotel and it was just light synced then we must do a full sync
					if ($isHotelSearch && $hotelObj && $hotelObj->SyncStatus && $hotelObj->SyncStatus->LiteSynced && (!$hotelObj->SyncStatus->FullySynced))
					{
						$toPullHotelsFromTF[$api_offer->HotelKey] = $api_offer->HotelKey;
					}
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

		$passedHotels = [];	
		/**
		 * 
		 */
		foreach ($api_offers ?: [] as $api_offer)
		{
			if (!($hotelObj = $passedHotels[$api_offer->HotelKey]))
			{
				$hotelObj = $this->GetHotel($api_offer, $objs);
				if (!$hotelObj)
					continue;
				$hotelObj->Offers = new \QModelArray();
				$passedHotels[$api_offer->HotelKey] = $hotelObj;
			}

			if ($hotelObj && $hotelObj->getId() && (!$hotelObj->Active))
				continue;

			$ret[$api_offer->HotelKey] = $hotelObj;
			$hotelObj->setHasIndividualOffers(true);
			$offer = new $offerType();
			$offer->ReqParams = $reqParams;
			$offer->SuppliedCurrency = $suppliedCurrency;
			$hotelObj->Offers[] = $offer;
			$offer->PackageId = uniqid(); # @TODO
			$offer->PackageVariantId = $offer->PackageId;
			$offer->setTourOperator($this->TourOperatorRecord);
			$offer->Content = new \Omi\Cms\Content();
			$offer->Content->Content = "";
			// $offer->DateStart = date("Y-m-d H:i:s", strtotime($api_offer->SaleBegDate));
			// $offer->DateEnd = date("Y-m-d H:i:s", strtotime($api_offer->SaleEndDate));
			$offer->BookData = $api_offer;
			// $offer->ApiOffer = $api_offer;
			$offer->Items = new \QModelArray();
			$offer->Items[] = $offer->Item = $room_item = new \Omi\Travel\Offer\Room();
			$room_item->Merch = new \Omi\Travel\Merch\Room();
			$room_item->Merch->Code = $api_offer->RtKey;
			$room_item->Merch->Title = $api_offer->RdName;
			$room_item->Code = $api_offer->RtKey;
			$room_item->_INDX = $api_offer->RtKey . "~" . $api_offer->RcKey . "~" . $api_offer->RdKey;
			$room_item->Merch->Hotel = $hotelObj;
			#if ($offer->IsEarlyBooking)
				#$room_item->InfoTitle = 'Early booking';
			// $peb = $api_offer->SPWithEBBySaleCur;
			$extra_costs = 0;
			if (($extra_costs = (int)$api_offer->AddHotsCost))
			{
				/*
				[array(7)]:
				Name[string]: "Bed and breakfast"
				ID[int]: 23
				Description[string]: ""
				NameLat[string]: ""
				Code[string]: "BB"
				CodeLat[string]: ""
				Unicode[string]: ""
				 */
				// Omi\Travel\Merch\Meal
				$extra_cost_obj = new \Omi\Comm\Merch\Merch();
				$extra_cost_obj->Title = 'Extra Costs';
				$extra_cost_obj->Code = 'EXTRA_COSTS';

				$extra_cost_itm = new \Omi\Comm\Offer\OfferItem();
				$extra_cost_itm->Merch = $extra_cost_obj;
				$extra_cost_itm->Quantity = 1;
				$offer->Items[] = $extra_cost_itm;
			}
			
			$extra_hots_costs = 0;
			if (($extra_hots_costs = (int)$api_offer->AddHotsWithCosts))
			{
				/*
				[array(7)]:
				Name[string]: "Bed and breakfast"
				ID[int]: 23
				Description[string]: ""
				NameLat[string]: ""
				Code[string]: "BB"
				CodeLat[string]: ""
				Unicode[string]: ""
				 */
				// Omi\Travel\Merch\Meal
				$extra_cost_obj = new \Omi\Comm\Merch\Merch();
				$extra_cost_obj->Title = 'Extra Costs S';
				$extra_cost_obj->Code = 'EXTRA_COSTS_2';

				$extra_cost_itm = new \Omi\Comm\Offer\OfferItem();
				$extra_cost_itm->Merch = $extra_cost_obj;
				$extra_cost_itm->Quantity = 1;
				$offer->Items[] = $extra_cost_itm;
			}

			$price = (float)$extra_costs + (float)$extra_hots_costs + (float)$api_offer->Cost;
			// if (($offer->IsEarlyBooking = ($peb && ((float)$peb > 0))))
			//	$price = (float)$peb;
			// if we don't have old sale price - just set the price as the initial price and let the system handle it
			$offer->setInitialPrice($price);
			$offer->setPrice($price);
			//$offer->setComission($this->getComission((float)$price, $price ? "charter" : "individual"));
			// set the comission for the aggregator
			$offer->Comission = $this->TourOperatorRecord->ComissionIndividual * $offer->Price / 100;
			//$offer->setComission(0);
			// $offer->_discount = ceil($api_offer->PasEBPer);
			$offer->Item->Quantity = 1;
			$offer->Item->UnitPrice = $price;
			$_qt = (isset($api_offer->QuoteType) && is_scalar($api_offer->QuoteType)) ? (int)$api_offer->QuoteType : static::Availability_No;
			$offer->Item->Availability = ($_qt === static::Availability_Yes) ? "yes" : (($_qt === static::Availability_Ask) ? "ask" : "no");
			if ($offer->isAvailable())
				$hotelObj->_has_available_offs = true;
			else if ($offer->isOnRequest())
				$hotelObj->_has_onreq_offs = true;
			$offer->Item->CheckinAfter = $parameters['PeriodOfStay'][0]['CheckIn'];
			$offer->Item->CheckinBefore = $parameters['PeriodOfStay'][1]['CheckOut'];
			$departureTransportCode = "other-outbound";
			$returnTransportCode = "other-inbound";
			//@important fix
			$departureDate = date('Y-m-d', strtotime($offer->Item->CheckinAfter));
			$returnDate = date('Y-m-d', strtotime($offer->Item->CheckinBefore));
			$departureTime = $departureDate;
			$returnTime = $returnDate;
			$departureItm = new \Omi\Travel\Offer\Transport();
			$departureItm->Merch = new \Omi\Travel\Merch\Transport();
			$departureItm->Merch->Title = "CheckIn: ".($departureDate ? date("d.m.Y", strtotime($departureDate)) : "");
			$departureItm->DepartureDate = $departureDate;
			$departureItm->Merch->DepartureTime = $departureTime;
			$departureItm->Merch->ArrivalTime = $departureTime;
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
			$returnItm->Merch->DepartureTime = $returnTime;
			$returnItm->Merch->ArrivalTime = $returnTime;
			$returnItm->Quantity = 1;
			$returnItm->UnitPrice = 0;
			$departureItm->Merch->TransportType = $returnItm->Merch->TransportType = "individual";
			$returnItm->Merch->Category = $objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] ?: 
				($objs[$returnTransportCode]["\Omi\Comm\Merch\MerchCategory"] = new \Omi\Comm\Merch\MerchCategory());
			$returnItm->Merch->Category->Code = $returnTransportCode;
			// set as return itm
			$departureItm->Return = $returnItm;
			$offer->Items[] = $departureItm;
			$offer->Items[] = $returnItm;
			if (($meal_assoc = $meals[(int)$api_offer->PnKey]))
			{
				/*
				[array(7)]:
				Name[string]: "Bed and breakfast"
				ID[int]: 23
				Description[string]: ""
				NameLat[string]: ""
				Code[string]: "BB"
				CodeLat[string]: ""
				Unicode[string]: ""
				 */
				// Omi\Travel\Merch\Meal
				$mealObj = new \Omi\Travel\Merch\Meal();
				$mealObj->Type = $objs[$meal_assoc['ID']]["\Omi\Travel\Merch\MealType"] ?: 
					($objs[$meal_assoc['ID']]["\Omi\Travel\Merch\MealType"] = new \Omi\Travel\Merch\MealType());
				$mealObj->Type->Title = $meal_assoc['Name'];
				$mealObj->Title = $meal_assoc['Name'];
				if (!$hotelObj->Meals)
					$hotelObj->Meals = new \QModelArray();
				$hotelObj->Meals[$meal_assoc['ID']] = $mealObj;

				$mealItm = new \Omi\Comm\Offer\OfferItem();
				$mealItm->Merch = $mealObj;
				$mealItm->Quantity = 1;
				$offer->Items[] = $mealItm;
				// qvar_dump($mealObj);
			}
			// the code for the offer
			/*
				HotelName[string]: "Flamingo GRAND (Albena) 5*"
				HotelKey[string]: "1456"
				RtCode[string]: "APT"
				RtKey[string]: "58"
				RcName[string]: "1 bedroom Corner_SUCORN"
				RcKey[string]: "285"
				RdName[string]: "APT 1 bedroom Corner_SUCORN"
				RdKey[string]: "2221"
				AcName[string]: "2Ad"
				AcKey[string]: "2220"
				PnCode[string]: "HB"
				PnKey[string]: "24"
				Cost[string]: "1912.0000"
				AddHotsCost[string]: "38.0000"
				DetailBrutto[string]: "(278.40[17_ordinary_APARTMENTS]*5 + 260.00[17_ordinary_APARTMENTS]*2) * 1 room
						= 1912.00"
				QuoteType[string]: "0"
				CountryKey[string]: "4"
				CityKey[string]: "14"
				CityName[string]: "Albena"
				HotelWebSite[array(0)]:

				TariffId[string]: "0"
				TariffName[string]: "Ordinary"
				TariffDescription[array(0)]:

				AddHots[string]: "-171,-172,-173"
				ContractPrKey[string]: "3455"
		 */
			$room_info = $parameters['Rooms'][0]['Room'];
			$room_info_children = [];
			foreach ($room_info['Children'] ?: [] as $room_info_chld)
					$room_info_children[] = (int)$room_info_chld['Age'];
			$offer->Code = $hotelObj->InTourOperatorId . "~" . /*$api_offer->Board*/ 0 . "~" . $room_item->Code . "~" . $departureDate . "~" . $returnDate. "~". 
							implode("~", [
								$api_offer->HotelKey,
								$api_offer->RtKey,
								$api_offer->RcKey,
								$api_offer->RdKey,
								$api_offer->AcKey,
								$api_offer->PnKey,
								$api_offer->Cost,
								$api_offer->QuoteType,
								$api_offer->CityKey,
							])."~".$room_info['NoAdults']."~".implode('~', $room_info_children);
			// on solvex we don't have currency on items - we have it only on offer - so we need to setup the supplied currency on all items
			foreach ($offer->Items ?: [] as $itm)
				$itm->SuppliedCurrency = $offer->SuppliedCurrency;

			// setup offer currency
			$this->setupOfferPriceByCurrencySettings($parameters, $parameters["SellCurrency"], $offer, "individual");
			
			$iparams_full = static::$RequestOriginalParams;
			$useAsyncFeesFunctionality = ((defined('USE_ASYNC_FEES') && USE_ASYNC_FEES) && (!$iparams_full["__on_setup__"]) 
				&& (!$iparams_full["__on_add_travel_offer__"]) && (!$iparams_full["__send_to_system__"]));
			
			$checkIn = $parameters["CheckIn"] ?: (isset($parameters["PeriodOfStay"]) ? q_reset($parameters["PeriodOfStay"])["CheckIn"] : null);

			$feesParams = [
				"CheckIn" => $checkIn,
				"__type__" => $parameters["VacationType"],
				"CurrencyCode" => $parameters["CurrencyCode"],
				"SellCurrency" => $parameters["SellCurrency"],
				"Price" => $offer->Price, 
				"IsEarlyBooking" => $offer->IsEarlyBooking,
				"Rooms" => \Omi\Travel\Offer\Stay::SetupRoomsParams($parameters["Rooms"]),
				"__cs__" => $this->TourOperatorRecord->Handle
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
			$offer->__fees_params = $feesParams;

			if (((!$useAsyncFeesFunctionality) && ($checkIn  && ($parameters["ProductCode"] || 
				$parameters["getFeesAndInstallments"] || 
					(
					$parameters["getFeesAndInstallmentsFor"] && 
					(
						($parameters["getFeesAndInstallmentsFor"] == $hotelObj->getId()) || 
						($hotelObj->Master && ($parameters["getFeesAndInstallmentsFor"] == $hotelObj->Master->getId()))
					)
					&& 
					(!$parameters["getFeesAndInstallmentsForOffer"] || 
						(
							(is_scalar($parameters["getFeesAndInstallmentsForOffer"]) && ($parameters["getFeesAndInstallmentsForOffer"] == $offer->Code)) || 
							(is_array($parameters["getFeesAndInstallmentsForOffer"]) && (isset($parameters["getFeesAndInstallmentsForOffer"][$offer->Code])))
						)
					)
				)))) || 
				($initialParams['api_call'] && $initialParams['api_call_method'] && $initialParams['__api_offer_code__'] && 
					($initialParams['api_call_method'] == 'offer-details') && ($initialParams['__api_offer_code__'] == $offer->Code)))
			{
				
				list($offer->CancelFees, $offer->Installments) = static::ApiQuery($this, "HotelFeesAndInstallments", null, null, [$feesParams, $feesParams]);
				
			}
		}
		if ($ret)
		{
			$app = \QApp::Data();
			$app->Hotels = new \QModelArray();
			foreach ($ret as $hotel)
			{
				if (!$hotel->getId())
					$app->Hotels[] = $hotel;
			}
			if (q_count($app->Hotels) > 0)
			{
				$app->save("Hotels.{"
					. "ContactPerson.{Email, Phone, Fax},"
					. "Facilities.{Name, Type, Active, Icon, IconHover, ListingOrder},"
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
						. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
						. "InTourOperatorId, "
						. "Order, "
						. "Alt"
					. "}, "
					. "Active, "
					. "ShortContent, "
					. "Stars,"
					. "Master,"
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
						. "City, "
						. "County, "
						. "Country, "
						. "PostCode,"
						. "Latitude, "
						. "Longitude"
					. "}"
				. "}");
			}
		}
		return $ret;
	}

	public function formatSolvexCode($str)
	{
		$str = str_replace(" ", "_", $str);
		return preg_replace("/[^[:alnum:]]/u", '', $str);
	}
	
	public function GetIndividualTransport($type, $c_item)
	{
		return $this->GetCachedTransport($type, $c_item, "IndividualTransports");
	}

	public function extractAvailability($availability)
	{
		$xml = $availability->SearchHotelServicesResult->Data->DataRequestResult->ResultTable->any;
		
		$beg_pos = strpos($xml, '<DocumentElement');
		$end_pos = strrpos($xml, '</DocumentElement>');

		if (($beg_pos === false) || ($end_pos === false))
			return null;

		$xml_clean = '<?xml version="1.0"?>' . substr($xml, $beg_pos, $end_pos - $beg_pos + strlen('</DocumentElement>'));

		//  xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:int"

		// $xml_valid = substr($xml, 0, $beg_pos);
		// echo $xml_clean;

		// diffgr:id="HotelServices1" msdata:rowOrder="0" diffgr:hasChanges="inserted"

		$xml_clean = preg_replace('/('.

						'(xmlns\\:xs\\=\\"http\\:\\/\\/www\\.w3\\.org\\/2001\\/XMLSchema\\")|'.
						'(xsi\\:type\\=\\"xs\\:[\w]+\\")|'.
						'(diffgr\\:id\\=\\"\\w+\\"\\s+msdata\\:rowOrder\\=\\"\\w+\\"\\s+diffgr\\:hasChanges\\=\\"\\w+\\")|'.
						'(xmlns\\=\\"\\w*\\")'.
						')/us', "", $xml_clean);

		$xml_obj = simplexml_load_string($xml_clean);

		$assoc = json_decode(json_encode($xml_obj));
		//qvar_dump($assoc ? $assoc->HotelServices : null);
		return $assoc ? $assoc->HotelServices : null;
	}
	
	
	public function resyncHotels($binds = [])
	{
		$this->saveHotels(array_merge($binds, ["__fromBoxes" => true]));
	}

	public function resyncIndividual($binds = [])
	{
		$individualTransports = QQuery("IndividualTransports.{To.City.{Name, InTourOperatorId} WHERE TourOperator.Id=?}", 
			$this->TourOperatorRecord->getId())->IndividualTransports;

		if (!$individualTransports || (q_count($individualTransports) === 0))
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

	public function saveHotels($binds = [])
	{
		try 
		{
			$this->SOAPInstance->login();
			if (!($connected = ($this->SOAPInstance->connect_result->ConnectResult != -1)))
				throw new \Exception("Cannot connect to Solvex API!");
			$meals = $this->SOAPInstance->getPensions();
			
			$reqsDir = \Omi\App::GetLogsDir('requests/solvex') . $this->TourOperatorRecord->Handle . "/";
			if (!is_dir($reqsDir))
				qmkdir($reqsDir);
			$reqsPrefix = "solvex_individual_req__";
			$_qp = ["TourOperator" => $this->TourOperatorRecord->getId()];
			$_qp = array_merge($binds, $_qp);
			$ids = [];
			if ($binds["__fromBoxes"])
			{
				$boxes = \QQuery("IndividualBoxes.{City WHERE City.TourOperator.Id=?}", $this->TourOperatorRecord->getId())->HotelsBoxes;
				foreach ($boxes ?: [] as $box)
				{
					if (!$box->City)
						continue;
					$ids[$box->City->getId()] = $box->City->getId();
				}

				if (q_count($ids) === 0)
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
			if ($ids && (q_count($ids) > 0))
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
					. "??IN?<AND[Id IN (?)]"
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
			$appData = \QApp::Data();
			$month = (int)date("n");
			$year = (int)date("Y");
			$day = (int)date("d");
			$nextYear = date("Y", strtotime("+ 1 year"));
			$today = date("Y-m-d");
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
			// go through cities
			foreach ($cities ?: [] as $city)
			{
				if (!$city->InTourOperatorId || (!$city->Country) || (!($countryId = $city->Country->getTourOperatorIdentifier($this->TourOperatorRecord))))
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
							echo "REQ :: " . $city->Name . " (" . $city->InTourOperatorId . ") ~ " . $checkin . " | " . $__period . "<br/>";
							$checkout = date("Y-m-d", strtotime("+ {$__period} days", strtotime($checkin)));
							$request = [
								'PageSize' => 10000,
								'RowIndexFrom' => 0,
								'PartnerKey' => (int)($this->SOAPInstance->ApiContext__ ?: $this->SOAPInstance->ApiContext),
								'DateFrom' => $checkin . 'T00:00:00',
								'DateTo' => $checkout . 'T00:00:00',
								'CityKeys' => [$city->InTourOperatorId],
								'Pax' => 2,
								"ValidateQuota" => true,
								"ResultView" => 0,
								"Mode" => 0,
								"IsAddHotsWithCosts" => true
							];
							$reqFile = $reqsDir . $reqsPrefix . MD5(json_encode($request)) . ".html";
							if (($reqFExists = file_exists($reqFile)) && !$binds["forceExec"])
							{
								echo "<div style='color: red;'>SKIPPED</div>";
								continue;
							}
							ob_start();
							qvardump("REQUEST", $request);
							$str = ob_get_clean();
							file_put_contents($reqFile, $str);
							try 
							{
								if (!($connected = ($this->SOAPInstance->connect_result->ConnectResult != -1)))
									throw new \Exception("Cannot connect to Solvex API!");
								$availability = $this->SOAPInstance->client->SearchHotelServices([
									'guid' => $this->SOAPInstance->connect_result->ConnectResult,
									'request' => $request
								]);
								$res = $this->extractAvailability($availability);
								if ($res && !is_array($res))
									$res = [$res];
								ob_start();
								qvardump("RESULT", $res);
								$str = ob_get_clean();
								file_put_contents($reqFile, $str, FILE_APPEND);
								$hotels = [];
								$mealsTypes = [];
								foreach ($res ?: [] as $off)
								{
									if (($meal_assoc = $meals[(int)$off->PnKey]) && ($meal_assoc_type = trim($meal_assoc['Name'])))
										$mealsTypes[$meal_assoc_type] = $meal_assoc_type;

									if (!isset($hotels[$off->HotelKey]))
										$hotels[$off->HotelKey] = [];

									$hotels[$off->HotelKey]["Hotel"] = [
										"InTourOperatorId" => $off->HotelKey
									];
									
									if (!isset($hotels[$off->HotelKey]["Offers"]))
										$hotels[$off->HotelKey]["Offers"] = [];
									$hotels[$off->HotelKey]["Offers"][] = $off;
								}
								// meals types
								if (q_count($mealsTypes))
									\Omi\TFuse\Api\TravelFuse::SetupResults_SetupMealsAliases_FromList($mealsTypes, true);
								$requestHaveHotels = false;
								if ($hotels && (($__hcnt = q_count($hotels)) > 0))
								{
									if ($__hcnt > $_max_hotels_count)
										$_max_hotels_count = $__hcnt;
									
									$dbHotelsIds = [];
									foreach ($hotels as $hotelID => $hotelData)
										$dbHotelsIds[$hotelID] = $hotelID;
									$dbHotels = [];
									if (q_count($dbHotelsIds) > 0)
									{
										$dbHotelsRes = QQuery("Hotels.{MTime, InTourOperatorId WHERE TourOperator.Id=? AND InTourOperatorId IN (?)}", 
											[$this->TourOperatorRecord->getId(), array_keys($dbHotelsIds)])->Hotels;
										foreach ($dbHotelsRes ?: [] as $dbH)
										{
											if (!$dbH->InTourOperatorId)
												continue;
											$dbHotels[$dbH->InTourOperatorId] = $dbH;
										}
									}
									$appData->Hotels = new \QModelArray();
									foreach ($hotels as $hotelID => $hotelData)
									{
										// we may have hotels updated in this very day because they are available for charters alse
										// we must skip them
										$savedToday = (($dbHotel = $dbHotels[$hotelID]) && $dbHotel->MTime && (date("Y-m-d", strtotime($dbHotel->MTime)) == $today));
										if (!$savedToday)
										{
											$hotel = $dbHotel;
										}
										else
											$hotel = $dbHotel;
										if (!$hotel)
											continue;
										$cityHasHotels = true;
										$requestHaveHotels = true;
										$hotel->setMTime(date("Y-m-d H:i:s"));
										$hotel->setTourOperator($this->TourOperatorRecord);
										$hotel->setHasIndividualOffers(true);
										$hotel->setHasIndividualActiveOffers(true);
										$hotel->setResellerCode($hotelData["Product"]["TourOpCode"]);
										$hotel->IsCached = true;
										$hotel->setupDataForQuickList();
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
								if ($requestHaveHotels)
								{
									$appData->save("Hotels.{"
										. "MTime, "
										. "ResellerCode, "
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
											. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
											. "InTourOperatorId, "
											. "Alt"
										. "}, "
										. "Active, "
										. "ShortContent, "
										. "Stars,"
										. "HasIndividualOffers,"
										. "HasIndividualActiveOffers,"
										. "HasCharterOffers,"
										. "TourOperator.{StorageClass, Handle, Caption, Abbr, UseMealAliasesOnInterface}, "
										. "InTourOperatorId,"
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
										. "}"
									. "}");

									if ($appData->Hotels)
										$this->flagHotelsTravelItems($appData->Hotels);
								}
							}
							catch (\Exception $ex)
							{
								var_dump($this->SOAPInstance->client->__getLastRequestHeaders(), $this->SOAPInstance->client->__getLastRequest(), 
									$this->SOAPInstance->client->__getLastResponseHeaders(), $this->SOAPInstance->client->__getLastResponse());
								ob_start();
								qvardump("EXCEPTION", $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString());
								$str = ob_get_clean();
								file_put_contents($reqFile, $str, FILE_APPEND);
							}
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
							$transport->setFromTopAddedDate(date("Y-m-d H:i:s"));
						}
						if (!$transport->Content)
							$transport->setContent(new \Omi\Cms\Content());
						$transport->Content->setActive(true);
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
			// execute callbacks
			//\QApp::ExecuteCallbacks();
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
	}

	public function importHotelsContent($hotelsWithDescription)
	{
		if (!($hotelsWithDescription))
			return;

		$allHotels = QQuery("Hotels.{*, Address.*, Content.{*, Seo.*, ImageGallery.Items.{*, TourOperator.{Handle, Caption, Abbr}}, VideoGallery.Items.*}, Facilities.* WHERE TourOperator.Id=?}", 
			$this->TourOperatorRecord->getId())->Hotels;

		$hotels = [];
		foreach ($allHotels ?: [] as $hotel)
			$hotels[$hotel->InTourOperatorId] = $hotel;

		$app = \QApp::NewData();
		$app->setHotels(new \QModelArray());
		foreach ($hotelsWithDescription ?: [] as $hotel)
		{
			if ((!$hotel["id"]) || !($dbHotel = $hotels[$hotel["id"]]))
				continue;

			if (!$dbHotel->Address)
				$dbHotel->Address = new \Omi\Address();

			if ($hotel["Code"])
				$dbHotel->setCode($hotel["Code"]);

			if ($hotel["lat"] && $hotel["lon"])
			{
				$dbHotel->Address->setLatitude($hotel["lat"]);
				$dbHotel->Address->setLongitude($hotel["lon"]);
			}


			if (!$dbHotel->Content)
				$dbHotel->Content = new \Omi\Cms\Content();


			if (!$dbHotel->Content->Seo)
				$dbHotel->Content->Seo = new \Omi\Cms\Seo();
			$dbHotel->Content->Seo->setBrowserTitle($hotel["meta_title"]);
			$dbHotel->Content->Seo->setKeywords($hotel["meta_keywords"]);
			$dbHotel->Content->Seo->setDescription($hotel["meta_description"]);

			$dbHotel->Content->setShortDescription($hotel["description"]);
			$dbHotel->Content->setContent($hotel["description"]);
			
			foreach ($hotel["notes"] ?: [] as $note)
				$dbHotel->Content->Content .= "<h2>{$note['title']}</h2>" . $note['descripion'] . "<br/>";

			$images = $hotel["images"] ?: [];

			if ($hotel["image"])
				array_unshift($images, $hotel["image"]);

			$this->importHotelsContent_SetupHotelsImages($dbHotel, $images);

			$videos = $hotel["videos"] ?: [];
			if ($hotel["video"])
				array_unshift($videos, $hotel["video"]);

			$this->importHotelsContent_SetupHotelsVideos($dbHotel, $videos);
			$app->Hotels[] = $dbHotel;
		}

		// save hotels
		$app->save("Hotels.{Code, "
			. "Address.{"
				. "Longitude,"
				. "Latitude"
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
				. "TourOperator.{Handle, Caption, Abbr}, "
				. "InTourOperatorId, "
				. "Alt"
			. "}, "
			. "Active, "
			. "ShortContent, "
			. "Content.{"
				. "ShortDescription, "
				. "Content, "
				. "Seo.{"
					. "BrowserTitle,"
					. "Description,"
					. "Keywords"
				. "},"
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
				. "},"
				. "VideoGallery.{"
					. "TourOperator, "
					. "RemoteUrl"
				. "}"
			. "}"
		. "}");
	}

	public function importHotelsContent_SetupHotelsImages($dbHotel, $images)
	{
		if (!$dbHotel->Content->ImageGallery)
			$dbHotel->Content->ImageGallery = new \Omi\Cms\Gallery();

		if (!$dbHotel->Content->ImageGallery->Items)
			$dbHotel->Content->ImageGallery->Items = new \QModelArray();

		$existingImages = [];
		foreach ($dbHotel->Content->ImageGallery->Items as $k => $itm)
		{
			if (!$itm->RemoteUrl || isset($existingImages[$itm->RemoteUrl]))
			{
				$itm->_toRM = true;
				$dbHotel->Content->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $k);
				continue;
			}
			$existingImages[$itm->RemoteUrl] = [$k, $itm];
		}

		$processedImages = [];
		foreach ($images ?: [] as $imageData)
		{
			if (!($image = $imageData["url"]))
				continue;

			if ($existingImages[$image])
			{
				list($_kim, $img) = $existingImages[$image];
			}
			else	
				$img = new \Omi\Cms\GalleryItem();

			// pull tour operator image
			$imagePulled = $img->setupTourOperatorImage($image, $this->TourOperatorRecord, $dbHotel, md5($image), IMAGES_URL, IMAGES_REL_PATH);

			// if the image is not pulled from server then don't save it
			if (!$imagePulled)
				continue;

			if (!$img->getId())
				$dbHotel->Content->ImageGallery->Items[] = $img;
			$processedImages[$image] = true;
		}

		foreach ($existingImages as $imgUrl => $itmData)
		{
			if (isset($processedImages[$imgUrl]))
				continue;

			list($key, $itm) = $itmData;
			$itm->_toRM = true;
			$dbHotel->Content->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $key);
		}
	}

	public function importHotelsContent_SetupHotelsVideos($dbHotel, $videos)
	{
		if (!$dbHotel->Content->VideoGallery)
			$dbHotel->Content->VideoGallery = new \Omi\Cms\Gallery();

		if (!$dbHotel->Content->VideoGallery->Items)
			$dbHotel->Content->VideoGallery->Items = new \QModelArray();

		$existingVideos = [];
		foreach ($dbHotel->Content->VideoGallery->Items as $k => $itm)
		{
			if (!$itm->RemoteUrl || isset($existingVideos[$itm->RemoteUrl]))
			{
				$itm->_toRM = true;
				$dbHotel->Content->VideoGallery->Items->setTransformState(\QIModel::TransformDelete, $k);
				continue;
			}
			$existingVideos[$itm->RemoteUrl] = [$k, $itm];
		}

		$processedImages = [];
		foreach ($videos ?: [] as $videoData)
		{
			if (!($videoUrl = $videoData["url"]))
				continue;

			if ($existingVideos[$videoUrl])
			{
				list($_kim, $video) = $existingVideos[$videoUrl];
			}
			else	
			{
				$video = new \Omi\Cms\GalleryItem();
				$video->Type = \Omi\Cms\GalleryItem::TypeVideo;
			}

			$video->TourOperator = $this->TourOperatorRecord;
			$video->RemoteUrl = $videoUrl;

			if (!$video->getId())
				$dbHotel->Content->VideoGallery->Items[] = $video;
			$processedImages[$videoUrl] = true;
		}

		foreach ($existingVideos as $videoUrl => $itmData)
		{
			if (isset($processedImages[$videoUrl]))
				continue;

			list($key, $itm) = $itmData;
			$itm->_toRM = true;
			$dbHotel->Content->VideoGallery->Items->setTransformState(\QIModel::TransformDelete, $key);
		}
	}
}