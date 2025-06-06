<?php

namespace Omi\TF;

/**
 * Functions used for Individual Offers
 */
trait ETripIndividual
{
	public function GetHotels()
	{
		$res = $this->SOAPInstance->GetHotels();
		return $res;
	}

	public function GetHotelAvailability($params, $initialParams = null)
	{
		if (!$initialParams)
			$initialParams = [];
		$initialParams["__REQ_INDX__"] = static::$RequestData["__REQ_INDX__"];

		if (!($res = $this->SOAPInstance->HotelSearch($params)))
			return [];
		
		if ($params["HotelSource"])
			$params["Hotel"] = $params["Hotel"] . "|" . $params["HotelSource"];

		$ret = new \QModelArray();
		$reqParams = static::GetRequestParams($initialParams);

		//if (static::$Exec || $_GET["RESP_PS"])
		//	qvardump("GetHotelAvailability :: ", $res, $this->TourOperatorRecord->Handle);

		// Group offers by Hotel
		$offers_desc = [];
		$hotels_ids = [];

		if ($res && is_array($res))
		{
			$offsIndx = 0;
			foreach ($res as $h_offer)
			{
				$h_offer->_index_offer = $offsIndx++;
				$mealPlansIndx = 0;
				if (isset($h_offer->MealPlans))
				{
					foreach ($h_offer->MealPlans ?: [] as $mp)
						$mp->_index_meal_plan = $mealPlansIndx++;
				}
				
				if (!$h_offer->HotelId)
					continue;

				if ($h_offer->Source)
				{
					$hidparts = (strrpos($h_offer->HotelId, '|') !== false) ? explode('|', $h_offer->HotelId) : [];
					$hasSource = ((count($hidparts) > 1) && (end($hidparts) == $h_offer->Source));
					if (!$hasSource)
						$h_offer->HotelId .= "|" . $h_offer->Source;
				}

				// make sure that we only return the requested hotel - otherwise we can have problems due to caching
				if (($params["Hotel"] && ($params["Hotel"] !== $h_offer->HotelId)))
					continue;

				$offers_desc[$h_offer->HotelId][] = $h_offer;
				$hotels_ids[$h_offer->HotelId] = $h_offer->HotelId;
			}
		}

		$hotels = (count($hotels_ids) > 0) ? $this->GetETripHotelFromDB($hotels_ids) : [];

		$params["DateRange"] = [
			"Start" => ($initialParams["PeriodOfStay"] && ($checkinData = $initialParams["PeriodOfStay"][0])) ? $checkinData["CheckIn"] : null,
			"End" => ($initialParams["PeriodOfStay"] && ($checkoutData = $initialParams["PeriodOfStay"][1])) ? $checkoutData["CheckOut"] : null,
		];

		if ($offers_desc)
		{
			foreach ($offers_desc as $hotel_id => $hDesc)
			{
				//$hotel = $this->GetETripHotelFromDB($hotel_id);	
				$hotel = $hotels[$hotel_id];

				if (!$hotel && !$hotel->Id)
					continue; // skip offers with hotels we do not have cahced in our database

				if (!$hotel->Offers)
					$hotel->Offers = new \QModelArray();

				$getFeesAndInstallments = (
					($params["Hotel"] && ($params["Hotel"] == $hotel_id)) || 
					$initialParams['getFeesAndInstallments']
				);

				// Loop through each offer
				if ($hDesc)
				{	
					foreach ($hDesc as $hD)
					{
						// loop through each meal plan
						if ($hD->MealPlans)
						{
							$hotel->setHasIndividualOffers(true);
							$hotel->setHasIndividualActiveOffers(true);

							foreach ($hD->MealPlans as $plan)
							{
								$offers = $this->buildOffer([$plan, $hDesc, null, $hD], $params, $hD, $hD->Price, false, null, $getFeesAndInstallments, $initialParams, true);
								if ($offers)
								{
									foreach ($offers as $offer)
									{
										if (!$offer)
											continue;

										if ($offer->isAvailable())
											$hotel->_has_available_offs = true;
										else if ($offer->isOnRequest())
											$hotel->_has_onreq_offs = true;

										$hotel->Offers[$offer->Code] = $offer;
										
										$offer->ReqParams = $reqParams;
									}
								}
							}
						}
					}
				}
				$ret[] = $hotel;
			}

			$appData = \QApp::NewData();
			$appData->Hotels = new \QModelArray();
			$appData->Hotels = $ret;
		}

		//\Omi\TFuse\Api\TravelFuse::UpdateHotelsStatus($ret, "individual");
		\QApp::AddCallbackAfterResponseLast(function ($hotels, $type) {
			// sync hotels status
			\Omi\TFuse\Api\TravelFuse::UpdateHotelsStatus($hotels, $type);
		}, [$ret, "individual"]);

		if ($ret)
			$this->flagHotelsTravelItems($ret);
		
		return $ret;
	}

	public function getRoomsList($rooms)
    {
        $list = array();
        if (!is_array($rooms))
		{
			$rooms = array($rooms);
		}

        if ($rooms)
		{
            foreach ($rooms as $k=>$room)
            {
				$list[$k] = $room;
            }
		}
        return $list;
    }

	public function buildOffer($OfferStdClass, $params, $hotelDetails, $priceOptionalInfo = null, 
		$isTour = false, $tourObj = null, $getFeesAndInstallments = false, $initial_params = null, $isIndividual = false)
	{
		// initial params & request data
		#qvardump('$params, $initial_params, static::$RequestData', $params, $initial_params, static::$RequestData);

		$TFREQID = $initial_params["__request_data__"]["ID"];
		$TFLISTREQID = $initial_params["__request_data__"]["LIST_ID"];

		$Package = null;
		if (is_array($OfferStdClass))
		{
			//$other_info = $OfferStdClass[1][0];
			$transportProvider = $OfferStdClass[2];
			$Package = $OfferStdClass[3];
			$OfferStdClass = $OfferStdClass[0];
		}

		// original package is used for retriving payment plan
		// be carreful not to add properties on the package object
		$originalPackage = $Package ? json_decode((json_encode($Package)), true) : null;
		unset($originalPackage['OptionExpiryDate']);
		unset($originalPackage['_index_offer']);
		if (isset($originalPackage['HotelInfo']['MealPlans']))
		{
			foreach ($originalPackage['HotelInfo']['MealPlans'] as $mpIndx => $mp)
			{
				unset($mp['_index_meal_plan']);
				$originalPackage['HotelInfo']['MealPlans'][$mpIndx] = $mp;
			}
		}
		if (isset($originalPackage['MealPlans']))
		{
			foreach ($originalPackage['MealPlans'] as $mpIndx => $mp)
			{
				unset($mp['_index_meal_plan']);
				$originalPackage['MealPlans'][$mpIndx] = $mp;
			}
		}
		static::KSortTree($originalPackage);

		$segment = $isTour ? "tour" : ($isIndividual ? "individual" : "charter");
		$isCharter = ($segment === "charter");

		$reqFullParams = $params;
		if ($isCharter && (!$reqFullParams['Hotel'])) 
		{
			$reqFullParams['Hotel'] = $hotelDetails->HotelId;
		}
		else if ($isTour && (!$reqFullParams['Tour']))
		{
			$reqFullParams['Tour'] = $Package->PackageId;
		}

		$is_early = (($hotelDetails->FareType[0] == "early_booking") || ($hotelDetails->FareType[0] == "special_offer") || 
				($hotelDetails->FareType[0] == "last_minute") || ($Package && 
					(($Package->FareType[0] == "special_offer") || ($Package->FareType[0] == "last_minute") || ($Package->FareType[0] == "early_booking"))));

		$offers = new \QModelArray();
		$rooms_list = array();
		if ($hotelDetails->Rooms)
			$rooms_list = $this->getRoomsList($hotelDetails->Rooms);

		$hotelObj = $this->GetETripHotelFromDB($hotelDetails->HotelId);

		// if the hotel is not active - return
		if ($hotelObj && $hotelObj->getId() && (!$hotelObj->Active))
			return null;

		$hotelObj->setTourOperator($this->TourOperatorRecord);

		$plan = $OfferStdClass;
		if ($plan)
		{
			$originalPlan = json_decode((json_encode($plan)), true);
			static::KsortTree($originalPlan);
			unset($originalPlan['_index_meal_plan']);

			$offer = $isTour ? new \Omi\Travel\Offer\Tour() : ($isCharter ? new \Omi\Travel\Offer\Charter() : new \Omi\Travel\Offer\Stay());
			if (!($currency = $initial_params["CurrencyCode"]))
			{
				throw new \Exception("Offer currency not provided!");
			}

			// setup
			if (!($offer->SuppliedCurrency = static::GetCurrencyByCode($currency)))
			{
				throw new \Exception("Undefined currency [{$currency}]!");
			}

			$offer->Hotel = $hotelObj;

			$offer->IsEarlyBooking = $is_early ? true : false;
			$room = $rooms_list[0];
			if (!$room)
			{
				qvardump($OfferStdClass, $hotelDetails->Rooms, $rooms_list); //->PackageRooms->PackageRoom->PackageRoomCode
				throw new \Exception("No room found for this package!");
			}

			if ($hotelDetails->CategorySourceId)
			{
				$hcs_idparts = (strrpos($hotelDetails->CategoryId, '|') !== false) ? explode('|', $hotelDetails->CategoryId) : [];
				$hasSource = ((count($hcs_idparts) > 1) && (end($hcs_idparts) == $hotelDetails->CategorySourceId));
				if (!$hasSource)
					$hotelDetails->CategoryId .= "|" . $hotelDetails->CategorySourceId;
			}

			$r_label = "";
			$__room = null;
			if ($hotelObj->Rooms)
			{
				foreach ($hotelObj->Rooms as $r)
				{
					if ($r->InTourOperatorId == $hotelDetails->CategoryId)
					{
						$r_label = (($pos = strpos($r->Title, '/')) !== false) ? trim(substr($r->Title, $pos + 1)) : trim($r->Title);
						$__room = $r;
						break;
					}
				}
			}

			$room_label = $room->Label;
			$pos = strpos($room->Label, '/');
			if ($pos !== false)
				$room_label = trim(substr($room->Label, $pos + 1));
			else 
				$room_label = trim($room->Label);

			$r = new \Omi\Travel\Merch\Room();

			// room code is in fact the room id from tour operator
			$r->Code = $__room->InTourOperatorId;
			$r->_INDX = $r->Code;

			$r->Title = trim($r_label . " " . ""); //$room_label;

			if (empty($r->Title))
				return;

			$r->Content = new \Omi\Cms\Content();
			$r->Content->ShortDescription = $room->Label;
			$r->Content->Content = $room->Label;

			$price = (float)(($hotelDetails->Price->Gross ? $hotelDetails->Price->Gross : $priceOptionalInfo->Gross) + $OfferStdClass->Price->Gross);

			if ($priceOptionalInfo->Tax)
				$price += $priceOptionalInfo->Tax;
			$offer->Price = $offer->InitialPrice = $r->Price = $price;

			$useDiscount = (($Package && $Package->TotalDiscount) ? $Package->TotalDiscount : $hotelDetails->TotalDiscount);
			$offer->InitialPrice += $useDiscount ?: 0;
			$offer->setTourOperator($this->TourOperatorRecord);

			if ($priceOptionalInfo->Commission)
				$offer->Comission = $priceOptionalInfo->Commission;
			else if (($_comission = static::$Config[$this->TourOperatorRecord->Handle]['comission'][$segment][$Package->IsHotDeal ? 1 : 0]))
				$offer->Comission = $_comission * $offer->Price / 100;
			else if ($segment === "individual")
			{
				// we don't have comissions - just show what we have in the database
				$offer->Comission = $this->TourOperatorRecord->ComissionIndividual * $offer->Price / 100;
			}

			$_departureDate = $params["DepartureDate"] ? $params["DepartureDate"] : ($params["CheckIn"] ? $params["CheckIn"] : null);

			$topCheckIn = ($Package && $Package->Date) ? date("Y-m-d", strtotime($Package->Date)) : null;
			$topCheckOut = ($topCheckIn && ($Package && $Package->Duration)) ? date("Y-m-d", strtotime("+ " . $Package->Duration . " days", strtotime($topCheckIn))) : null; 

			$checkIN = $topCheckIn ?: ($initial_params ? $initial_params["PeriodOfStay"][0]["CheckIn"] : null);
			$checkOUT = $topCheckOut ?: ($initial_params ? $initial_params["PeriodOfStay"][1]["CheckOut"] : null);

			// offer index
			$offer->ETripIndexOffer = $Package->_index_offer;
			// meal index
			$offer->ETripIndexMeal = $plan->_index_meal_plan;

			//$r->Currency = "EUR";
			$r->RoomCode = "0";

			// set currency
			//$offer->Currency = static::$_CacheData["Currencies"][$r->Currency] ?: 
			//	(static::$_CacheData["Currencies"][$r->Currency] = QQuery("Currencies.{Code WHERE Code=?}", $r->Currency)->Currencies[0]);

			// the offer code will be used for indexing the offers
			// it will be in the folowing format: hotel.Id + romm.code + checkin + checkout + meal
			$offer->Code = 
					trim($hotelDetails->HotelId."~".
						$r->Code . " ~ " . 
					($params["DateRange"]["Start"] ?: "")."~".
					($params["DateRange"]["End"] ?: "").
					$OfferStdClass->Label . "~" . $Package->PackageId);

			/*
			$offer->Code = 
					trim($hotelDetails->HotelId."~".
						$r->Code . " ~ " . 
					($params["DateRange"]["Start"] ?: "")."~".
					($params["DateRange"]["End"] ?: "").
					$OfferStdClass->Label);
			*/

			// main item is either tour or room
			$_mainItm = $tourObj ? new \Omi\Comm\Offer\OfferItem() : new \Omi\Travel\Offer\Room();
			$_room_Itm = $tourObj ? new \Omi\Travel\Offer\Room() : $_mainItm;
			if ($tourObj)
				$_mainItm->Merch = $tourObj;

			$_room_Itm->CheckinAfter = $checkIN;
			$_room_Itm->CheckinBefore = $checkOUT;

			$_room_Itm->Merch = $r;
			$r->Hotel = $hotelObj;
			$offer->Item = $_mainItm;

			#if ($offer->IsEarlyBooking)
			#	$_room_Itm->InfoTitle = 'Early booking';

			$offer->Items = new \QModelArray();
			$offer->Items[] = $_mainItm;
			if ($tourObj)
				$offer->Items[] = $_room_Itm;

			$offer->Item->Code = $r->Code;
			$offer->PackageVariantId = $offer->PackageId = $offer->Code;

			$meal_orig_label = $OfferStdClass->Label ?: "Fara masa";
			/*
			$m = null;
			preg_match("/\/(.*?)$/", $meal_orig_label, $m);
			//qvardump($m, $meal_orig_label);
			$meal_actual_label = ($m && $m[1]) ? trim($m[1]) : $meal_orig_label;
			*/

			$meal_actual_label = $meal_orig_label;
			
			$meal = new \Omi\Comm\Offer\OfferItem();
			$meal->Title = $meal_actual_label;
			$meal->Content = new \Omi\Cms\Content();
			$meal->Content->Content = $meal_actual_label;
			$meal->Price = 0;
			$meal->Code = $OfferStdClass->Label;
			$meal->Merch = new \Omi\Travel\Merch\Meal();
			$meal->Merch->Title = $meal_actual_label;

			if (!static::$_CacheData["MealTypesSet"])
			{
				static::$_CacheData["MealTypesSet"] = true;
				static::$_CacheData["MealTypes"] = [];
				$_meal_types = \QApi::Query("MealTypes", "Title");
				if ($_meal_types && count($_meal_types))
				{
					foreach ($_meal_types as $_mt)
						static::$_CacheData["MealTypes"][$_mt->Title] = $_mt;
				}
			}

			if (!static::$_CacheData["MealTypes"])
				static::$_CacheData["MealTypes"] = [];

			if (!static::$_CacheData["MealTypes"][$meal->Title])
			{
				$type = new \Omi\Travel\Merch\MealType();
				$type->setTitle($meal->Title);
				static::$_CacheData["MealTypes"][$meal->Title] = $type;
			}
			else
				$type = static::$_CacheData["MealTypes"][$meal->Title];

			$meal->Merch->Type = $type;
			$offer->Items[] = $meal;

			/*
			$transport = new \Omi\Travel\Offer\Transport();
			$transport->Merch = new \Omi\Travel\Merch\Transport();
			$transport->DepartureDate = $params["DateRange"]["Start"];
			$transport->ArrivalDate = $params["DateRange"]["Start"];
			$transport->Merch->Title = "Checkin: " . $params["DateRange"]["Start"];
			$transport->Return = new \Omi\Travel\Offer\Transport();
			$transport->Return->Merch = new \Omi\Travel\Merch\Transport();
			$transport->Return->Merch->Title = "Checkout: " . $params["DateRange"]["End"];
			$transport->Return->DepartureDate = $params["DateRange"]["End"];
			$transport->Return->ArrivalDate = $params["DateRange"]["End"];
			$offer->Items[] = $transport;
			*/

			// set here the availability
			$offer->Item->Availability = $Package ? 
				($Package->IsBookable ? ($Package->IsAvailable ? "yes" : "ask") : "no") :
				($hotelDetails->IsBookable ? ($hotelDetails->IsAvailable ? "yes" : "ask") : "no");

			$offer->Content = new \Omi\Cms\Content();
			$offer->Content->ShortDescription = $room->Label;
			$offer->Content->Content = $room->Label;

			if ($isTour)
				$offer->UniqueId = $offer->Code;

			$air_line = $transportProvider->Provider && $transportProvider->Provider->Name ? $transportProvider->Provider->Name : "";			

			// if it has flight info then setup flight info details
			if ($Package->FlightInfo)
			{
				$__outbound_transport = null;
				$__inbound_transport = null;
				if ($Package->FlightInfo->Outbound)
				{
					foreach ($Package->FlightInfo->Outbound as $ob)
					{
						$__outbound_transport = new \Omi\Travel\Offer\Transport();
						$__outbound_transport->Quantity = 1;
						$__outbound_transport->UnitPrice = 0;
						$__outbound_transport->Availability = "yes";
						$__outbound_transport->Merch = new \Omi\Travel\Merch\Transport();
						$__outbound_transport->Merch->TransportType = "plane";

						$legs = $ob->Legs[0];

						$__deptime = strtotime($legs->Departure);
						$__arrtime = strtotime($legs->Arrival);

						$__outbound_transport->Merch->Title = "Dus: " . $air_line . " " . $legs->From . " " 
							. $legs->To . " " . date("d.m.Y H:s", $__deptime) . " - " . date("d.m.Y H:s", $__arrtime);

						$__outbound_transport->Merch->Code = uniqid();
						$__outbound_transport->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
						$__outbound_transport->Merch->Category->Code = "other-outbound";

						$__outbound_transport->Merch->DepartureTime = date("Y-m-d H:i:s", $__deptime);
						$__outbound_transport->Merch->ArrivalTime = date("Y-m-d H:i:s", $__arrtime);

						$__outbound_transport->DepartureDate = date("Y-m-d", $__deptime);
						$__outbound_transport->ArrivalDate = date("Y-m-d", $__arrtime);

						$__outbound_transport->Merch->DepartureAirport = $legs->From;
						$__outbound_transport->Merch->ReturnAirport = $legs->From;
						$__outbound_transport->Merch->ArrivalAirport = $legs->To;
						//$__outbound_transport->Return = $__outbound_transport;

						$offer->Items[] = $__outbound_transport;
					}
				}

				if ($Package->FlightInfo->Inbound)
				{
					foreach ($Package->FlightInfo->Inbound as $ob)
					{
						$__inbound_transport = new \Omi\Travel\Offer\Transport();
						$__inbound_transport->Quantity = 1;
						$__inbound_transport->UnitPrice = 0;
						$__inbound_transport->Availability = "yes";
						$__inbound_transport->Merch = new \Omi\Travel\Merch\Transport();
						$__inbound_transport->Merch->TransportType = "plane";

						$legs = $ob->Legs[0];

						$__deptime = strtotime($legs->Departure);
						$__arrtime = strtotime($legs->Arrival);

						$__inbound_transport->Merch->Title = "Retur: " . $air_line . " " . $legs->From . " " . $legs->To . " " 
							. date("d.m.Y H:s", $__deptime) . " - " . date("d.m.Y H:s", $__arrtime);

						$__inbound_transport->Merch->Code = uniqid();
						$__inbound_transport->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
						$__inbound_transport->Merch->Category->Code = "other-inbound";

						$__inbound_transport->DepartureDate = date("Y-m-d", $__deptime);
						$__inbound_transport->ArrivalDate = date("Y-m-d", $__arrtime);

						$__inbound_transport->Merch->DepartureTime = date("Y-m-d H:i:s", $__deptime);
						$__inbound_transport->Merch->ArrivalTime = date("Y-m-d H:i:s", $__arrtime);
						$__inbound_transport->Merch->DepartureAirport = $legs->From;
						$__inbound_transport->Merch->ArrivalAirport = $legs->To;
						$__inbound_transport->Merch->ReturnAirport = $legs->To;

						if ($__outbound_transport)
							$__outbound_transport->Return = $__inbound_transport;

						$offer->Items[] = $__inbound_transport;
					}
				}

				if (($departure = ((static::$RequestData && static::$RequestData["Departure"]) ? static::$RequestData["Departure"] : null)))
				{
					if ($__outbound_transport)
					{
						$__outbound_transport->Merch->From = new \Omi\Address();
						$__outbound_transport->Merch->From->City = $departure;
					}
					
					if ($__inbound_transport)
					{
						$__inbound_transport->Merch->To = new \Omi\Address();
						$__inbound_transport->Merch->To->City = $departure;	
					}
				}

				if (($destination = ((static::$RequestData && static::$RequestData["Destination"]) ? static::$RequestData["Destination"] : null)))
				{
					if ($__outbound_transport)
						$__outbound_transport->Merch->To = new \Omi\Address();
					if ($__inbound_transport)
						$__inbound_transport->Merch->From = new \Omi\Address();

					if ($destination instanceof \Omi\City)
					{
						if ($__outbound_transport)
							$__outbound_transport->Merch->To->City = $destination;
						if ($__inbound_transport)
							$__inbound_transport->Merch->From->City = $destination;
					}
					else if ($destination instanceof \Omi\County)
					{
						if ($__outbound_transport)
							$__outbound_transport->Merch->To->County = $destination;
						if ($__inbound_transport)
							$__inbound_transport->Merch->From->County = $destination;
					}
					else if ($destination instanceof \Omi\Country)
					{
						if ($__outbound_transport)
							$__outbound_transport->Merch->To->Country = $destination;
						if ($__inbound_transport)
							$__inbound_transport->Merch->From->Country = $destination;
					}
					else if ($destination instanceof \Omi\TF\Destination)
					{
						if ($__outbound_transport)
							$__outbound_transport->Merch->To->Destination = $destination;
						if ($__inbound_transport)
							$__inbound_transport->Merch->From->Destination = $destination;
					}
				}

				// if the package is with plane - check for this
				if ($Package->TransferInfo)
				{
					$new_item = new \Omi\Comm\Offer\OfferItem();
					$new_item->Quantity = 1;
					$new_item->UnitPrice = $Package->TransferInfo->Price;
					$new_item->Availability = "yes";
					$new_item->Merch = new \Omi\Comm\Merch\Merch();
					$new_item->Merch->Title = $Package->TransferInfo->Label;
					$new_item->Merch->Code = uniqid();
					$new_item->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
					$new_item->Merch->Category->Code = \Omi\Comm\Offer\Offer::TransferItmIndx;
					$offer->Items[] = $new_item;
				}

				if (($isCharter || $isTour) && static::$Config[$this->TourOperatorRecord->Handle][($isCharter ? "charter" : "tour")]["airport_tax_included"])
				{
					$new_item = new \Omi\Comm\Offer\OfferItem();
					$new_item->Quantity = 1;
					$new_item->UnitPrice = 0;
					$new_item->Availability = "yes";
					$new_item->Merch = new \Omi\Comm\Merch\Merch();
					$new_item->Merch->Title = "Taxe aeroport";
					$new_item->Merch->Code = uniqid();
					$new_item->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
					$new_item->Merch->Category->Code = \Omi\Comm\Offer\Offer::AirportTaxItmIndx;
					$offer->Items[] = $new_item;
				}
			}

			if ($Package->ExtraComponents)
			{
				foreach ($Package->ExtraComponents as $ec)
				{
					if ($ec->IsOptional)
						continue;

					$forCocktail = false;
					if (
						#($forCocktail = (strpos($ec->Label, "RO Cocktail Travel Protection") !== false)) || 
						(($forHolidayOffice = (strpos($ec->Label, "RO Pachet Protectie de Calatorie") !== false))) || 
						(($forCHR = (strpos($ec->Label, "Asigurare COVID (storno + medicala)") !== false))))
					{
						$medialInsurenceFileName = null;
						if (($this->TourOperatorRecord->Handle == 'cocktail_holidays') && $forCocktail)
						{
							#$medialInsurenceFileName = 'Conditii Asigurare Travel Protection.pdf';
							$medialInsurenceFileName = 'Pliant_Cocktail_Travel_Protection.pdf';
						}
						else if (($this->TourOperatorRecord->Handle == 'holiday_office') && $forHolidayOffice)
							$medialInsurenceFileName = 'Asigurare pachet travel Holiday Office.pdf';
						else if (($this->TourOperatorRecord->Handle == 'christian_tour') && $forCHR)
							$medialInsurenceFileName = 'CGA RO RO 01 07 2020 Protectia Covid.pdf';

						if ($medialInsurenceFileName !== null)
						{
							$medicalEnsurenceFile = "uploads/util/" . $medialInsurenceFileName;
							if (!file_exists($medicalEnsurenceFile))
								$medicalEnsurenceFile = rtrim(TRAVELFUSE_WEB_PATH, "\\/") . "/res/docs/" . $medialInsurenceFileName;

							$new_item = new \Omi\Comm\Offer\OfferItem();
							$new_item->Code = \Omi\Comm\Offer\Offer::MedicalInsurenceItmIndx;
							$new_item->Quantity = 1;
							$new_item->Document = $medicalEnsurenceFile;
							$new_item->UnitPrice = 0;
							$new_item->Merch = new \Omi\Comm\Merch\Merch();
							$new_item->Merch->Title = $ec->Label;
							$new_item->Merch->Code = \Omi\Comm\Offer\Offer::MedicalInsurenceItmIndx;
							$new_item->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
							$new_item->Merch->Category->Code = "MI";
							$new_item->Merch->Category->Name = "Medical Insurence";
							$offer->Items[] = $new_item;
							break;
						}
					}
				}
			}

			if ($Package->BusInfo)
			{
				$_depart = new \Omi\Travel\Offer\Transport();
				$_depart->Quantity = 1;
				$_depart->UnitPrice = 0;
				$_depart->Availability = "yes";
				$_depart->Merch = new \Omi\Travel\Merch\Transport();				
				$_depart->Merch->TransportType = "bus";
				$_depart->Merch->Title = "Dus - " . $Package->BusInfo->Label . ($Package->BusInfo->BusType ? "(".$Package->BusInfo->BusType.")" : "");
				$_depart->Merch->Code = uniqid();
				$_depart->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
				$_depart->Merch->Category->Code = "other-outbound";

				$__deptime = strtotime($Package->BusInfo->OutboundDate);
				$__arrtime = strtotime($Package->BusInfo->OutboundArrivalDate);

				$_depart->DepartureDate = date("Y-m-d", $__deptime);
				$_depart->Merch->DepartureTime = date("Y-m-d H:i:s", $__deptime);
				$_depart->Merch->ArrivalTime = date("Y-m-d H:i:s", $__arrtime);

				$offer->Items[] = $_depart;

				$_return = new \Omi\Travel\Offer\Transport();
				$_return->Quantity = 1;
				$_return->UnitPrice = 0;
				$_return->Availability = "yes";
				$_return->Merch = new \Omi\Travel\Merch\Transport();
				$_return->Merch->TransportType = "bus";
				$_return->Merch->Title = "Retur - " . $Package->BusInfo->Label . ($Package->BusInfo->BusType ? "(".$Package->BusInfo->BusType.")" : "");
				$_return->Merch->Code = uniqid();

				$__ret_deptime = strtotime($Package->BusInfo->InboundDate);
				$__ret_arrtime = strtotime($Package->BusInfo->InboundArrivalDate);
				
				$_return->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
				$_return->Merch->Category->Code = "other-inbound";

				$_return->DepartureDate = date("Y-m-d", $__deptime);
				$_return->Merch->DepartureTime = date("Y-m-d H:i:s", $__ret_deptime);
				$_return->Merch->ArrivalTime = date("Y-m-d H:i:s", $__ret_arrtime);
				
				$_depart->Return = $_return;
				$offer->Items[] = $_return;

				if (($departure = ((static::$RequestData && static::$RequestData["Departure"]) ? static::$RequestData["Departure"] : null)))
				{
					$_depart->Merch->From = new \Omi\Address();
					$_depart->Merch->From->City = $departure;
					
					$_return->Merch->To = new \Omi\Address();
					$_return->Merch->To->City = $departure;	
				}

				if (($destination = ((static::$RequestData && static::$RequestData["Destination"]) ? static::$RequestData["Destination"] : null)))
				{
					$_depart->Merch->To = new \Omi\Address();
					$_return->Merch->From = new \Omi\Address();

					if ($destination instanceof \Omi\City)
					{
						$_depart->Merch->To->City = $destination;
						$_return->Merch->From->City = $destination;
					}
					else if ($destination instanceof \Omi\County)
					{
						$_depart->Merch->To->County = $destination;
						$_return->Merch->From->County = $destination;
					}
					else if ($destination instanceof \Omi\Country)
					{
						$_depart->Merch->To->Country = $destination;
						$_return->Merch->From->Country = $destination;
					}
					else if ($destination instanceof \Omi\TF\Destination)
					{
						$_depart->Merch->To->Destination = $destination;
						$_return->Merch->From->Destination = $destination;
					}
				}
			}

			// if on individual setup departure and return transports for checkin and checkout date
			// needs to be similar with charters/tours (we have common functionality)
			if (!$Package->FlightInfo && (!$Package->BusInfo) && $isIndividual)
			{
				//qvardump($OfferStdClass, $params, $hotelDetails, $priceOptionalInfo, $isTour, $tourObj, $getFeesAndInstallments, $initial_params, $isIndividual);
				//q_die("ook");
				
				$checkin = ($initial_params && ($fps = reset($initial_params["PeriodOfStay"]))) ? $fps["CheckIn"] : null;
				$checkout = ($initial_params && ($lps = end($initial_params["PeriodOfStay"]))) ? $lps["CheckOut"] : null;

				$_depart = new \Omi\Travel\Offer\Transport();
				$_depart->Quantity = 1;
				$_depart->UnitPrice = 0;
				$_depart->Availability = "yes";
				$_depart->DepartureDate = $checkin;
				$_depart->ArrivalDate = $checkin;

				$_depart->Merch = new \Omi\Travel\Merch\Transport();				
				$_depart->DepartureDate = $checkin;
				$_depart->Merch->Code = uniqid();
				$_depart->Merch->Title = "CheckIn: " . ($checkin ? date("d.m.Y", strtotime($checkin)) : "");
				
				$_depart->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
				$_depart->Merch->Category->Code = "other-outbound";
				$offer->Items[] = $_depart;

				$_return = new \Omi\Travel\Offer\Transport();
				$_return->Quantity = 1;
				$_return->UnitPrice = 0;
				$_return->Availability = "yes";
				$_return->DepartureDate = $checkout;
				$_return->ArrivalDate = $checkout;

				$_return->Merch = new \Omi\Travel\Merch\Transport();
				$_return->Merch->Code = uniqid();
				$_return->Merch->Title = "CheckOut: ".($checkout ? date("d.m.Y", strtotime($checkout)) : "");
			
			 
				$_return->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
				$_return->Merch->Category->Code = "other-inbound";

				$_return->DepartureDate = $checkout;
				$_depart->Return = $_return;
				$offer->Items[] = $_return;
			}

			if ($Package->DiscountInfo)
			{
				$new_item = new \Omi\Comm\Offer\OfferItem();
				$new_item->Quantity = 1;
				$new_item->UnitPrice = 0;
				$new_item->Availability = "yes";
				$new_item->Merch = new \Omi\Comm\Merch\Merch();
				$new_item->Merch->Title = $Package->DiscountInfo->Label;
				$new_item->Merch->Category = new \Omi\Comm\Merch\MerchCategory();
				$new_item->Merch->Category->Code = "other";
				$new_item->Merch->Code = uniqid();
				$offer->Items[] = $new_item;
			}

			// on eurosite we don't have currency on items - we have it only on offer - so we need to setup the supplied currency on all items
			foreach ($offer->Items ?: [] as $itm)
				$itm->SuppliedCurrency = $offer->SuppliedCurrency;

			$this->setupOfferPriceByCurrencySettings($initial_params, $initial_params["SellCurrency"], $offer, $segment, true);

			$iparams_full = static::$RequestOriginalParams;
			$useAsyncFeesFunctionality = ((defined('USE_ASYNC_FEES') && USE_ASYNC_FEES) && (!$iparams_full["__on_setup__"]) 
				&& (!$iparams_full["__on_add_travel_offer__"]) && (!$iparams_full["__send_to_system__"]));

			#if ($isTour)
			#	$useAsyncFeesFunctionality = false;

			$feesParams = [
				"CheckIn" => $_departureDate,
				"__type__" => $segment,
				"Price" => $offer->Price, 
				"IsEarlyBooking" => $is_early,
				"__cs__" => $this->TourOperatorRecord->Handle,
				"CurrencyCode" => $initial_params["CurrencyCode"],
				"SellCurrency" => $initial_params["SellCurrency"],
				"Rooms" => \Omi\Travel\Offer\Stay::SetupRoomsParams($params["Rooms"]),
				"ETripIndexOffer" => $offer->ETripIndexOffer,
				"ETripIndexMeal" => $offer->ETripIndexMeal,
				"TFREQID" => $TFREQID,
				"TFLISTREQID" => $TFLISTREQID,
				"reqFullParams" => $reqFullParams
			];

			$feesParams['packageJSON'] = json_encode($originalPackage);
			$feesParams['planJSON'] = json_encode($originalPlan);

			$feesParams['packageHash'] = md5($feesParams['packageJSON']);
			$feesParams['planHash'] = md5($feesParams['planJSON']);

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

			// cancel fees
			if (((!$useAsyncFeesFunctionality) && ($getFeesAndInstallments || 
				(
					$initial_params && 
					$initial_params["getFeesAndInstallmentsFor"] && 
					(
						($initial_params["getFeesAndInstallmentsFor"] == $hotelObj->getId()) || 
						($hotelObj->Master && ($initial_params["getFeesAndInstallmentsFor"] == $hotelObj->Master->getId()))
					) && 
					(!$initial_params["getFeesAndInstallmentsForOffer"] || 
						(
							(is_scalar($initial_params["getFeesAndInstallmentsForOffer"]) && ($initial_params["getFeesAndInstallmentsForOffer"] == $offer->Code)) || 
							(is_array($initial_params["getFeesAndInstallmentsForOffer"]) && (isset($initial_params["getFeesAndInstallmentsForOffer"][$offer->Code])))
						)
					)
				))) || ($initial_params['api_call'] && $initial_params['api_call_method'] && $initial_params['__api_offer_code__'] && 
			($initial_params['api_call_method'] == 'offer-details') && ($initial_params['__api_offer_code__'] == $offer->Code)))
			{
				
				list($offer->CancelFees, $offer->Installments) = static::ApiQuery($this, ($isTour ? "TourFeesAndInstallments" : "HotelFeesAndInstallments"), null, null, 
					[$feesParams, $feesParams]);
			}

			$offers[] = $offer;
		}

		return $offers;
	}
	
	public function formatETripCode($str)
	{
		$str = str_replace(" ", "_", $str);
		return preg_replace("/[^[:alnum:]]/u", '', $str);
	}
	
	public function GetIndividualTransport($type, $c_item)
	{
		return $this->GetCachedTransport($type, $c_item, "IndividualTransports");
	}
}