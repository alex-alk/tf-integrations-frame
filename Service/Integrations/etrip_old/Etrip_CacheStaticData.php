<?php

namespace Omi\TF;

/**
 * 
 */
trait Etrip_CacheStaticData
{	
	public function saveHotels_checkRooms($toProcessHotels, $existingHotelsByInTopId, $showDump = false)
	{
		$allRoomsCategories = [];
		$allExistingRoomsCategories = [];
		foreach ($toProcessHotels ?: [] as $hotel)
		{
			if ($showDump)
				echo $hotel->Id . " | " . $hotel->Name . "<br/>";
			$hotelRooms = [];
			foreach ($hotel->RoomCategories ?: [] as $room_cat)
			{
				if ($room_cat->SourceId)
					$room_cat->Id .= "|" . $room_cat->SourceId;
				if (isset($hotelRooms[$room_cat->Id]))
					throw new \Exception("Duplicate room on hotel [{$room_cat->Id}] -> [{$hotel->Id}]");
				$hotelRooms[$room_cat->Id] = $room_cat;
			}
			ksort($hotelRooms);
			foreach ($hotelRooms ?: [] as $room_cat)
			{
				if ($showDump)
					echo "<div style='padding-left: 20px;'>{$room_cat->Id} | {$room_cat->Name}</div>";
				if (isset($allRoomsCategories[$room_cat->Id]))
					throw new \Exception("Duplicate categ in top ret hotel [{$room_cat->Id}] - [{$hotel->Id}]");
				$allRoomsCategories[$room_cat->Id][$hotel->Id] = $hotel->Id;
			}

			if (($existingHotel = $existingHotelsByInTopId[$hotel->Id]))
			{
				if ($showDump)
					echo "Existing hotel: " . $existingHotel->getId() . " | " . $existingHotel->Name . "<br/>";
				$allHotelRooms = new \QModelArray();
				$allHotelRooms->_rowi = [];
				$existingHotelRoomsByInTopID = [];
				foreach ($existingHotel->Rooms ?: [] as $roomk => $room)
				{
					if ((!$room->TourOperator) || (!$room->TourOperator->Handle))				
						continue;
					if (!$room->InTourOperatorId)
						continue;
					if (isset($existingHotelRoomsByInTopID[$room->InTourOperatorId]))
					{
						continue;
						# throw new \Exception("Duplicate existing room in hotel [{$room->InTourOperatorId}] - {$existingHotel->getId()}");
					}
					$existingHotelRoomsByInTopID[$room->InTourOperatorId] = [$roomk, $room];
				}
				ksort($existingHotelRoomsByInTopID);
				foreach ($existingHotelRoomsByInTopID ?: [] as $roomData)
				{
					list($roomk, $room) = $roomData;
					if ($showDump)
						echo "<div style='padding-left: 20px;'>Existing Room: {$room->InTourOperatorId} | {$room->Title}</div>";
					if (isset($allExistingRoomsCategories[$room->InTourOperatorId]))
					{
						if ($showDump)
							echo "<div style='color: red;'>Duplicate categ in db hotel [{$room->InTourOperatorId}] - [{$existingHotel->InTourOperatorId}]</div>";
						//throw new \Exception("Duplicate categ in db hotel [{$room->InTourOperatorId}] - [{$existingHotel->InTourOperatorId}]");
					}
					$allExistingRoomsCategories[$room->InTourOperatorId][$existingHotel->InTourOperatorId] = $existingHotel->InTourOperatorId;
					$allHotelRooms[] = $room;
					$allHotelRooms->_rowi[$roomk] = $existingHotel->Rooms->_rowi[$roomk];

					if (!isset($hotelRooms[$room->InTourOperatorId]))
					{
						if ($showDump)
							echo "<div style='color: red;'>Room [{$room->InTourOperatorId}] not in hotel [{$existingHotel->InTourOperatorId}] anymore</div>";
					}
				}
				$existingHotel->Rooms = $allHotelRooms;
			}
			
			if ($showDump)
				echo "<hr/>";
		}

		foreach ($allRoomsCategories ?: [] as $categId => $hotelsIds)
		{
			if (count($hotelsIds) > 1)
				throw new \Exception("RoomCategory [{$categId}] is applied to multiple hotels. We do not support this yet!");
		}

		foreach ($allExistingRoomsCategories ?: [] as $categId => $hotelsIds)
		{
			if (count($hotelsIds) > 1)
				throw new \Exception("RoomCategory [{$categId}] is applied to multiple hotels in our system. It is an error and it needs to be sorted out!");
		}

		foreach ($allExistingRoomsCategories ?: [] as $ercId => $ercByHotelId)
		{
			if (!($retRoomsCategsHotels = $allRoomsCategories[$ercId]))
			{
				#echo "<div style='color: red;'>Existing room category not in top anymore [{$ercId}]</div>";
				continue;
			}
			$ercHotelId = reset($ercByHotelId);
			$retHotelId = reset($retRoomsCategsHotels);
			if ($ercHotelId != $retHotelId)
			{
				throw new \Exception("RoomCategory [{$ercId}] was moved from hotel {$ercHotelId} to {$ercHotelId}!");
			}
		}

		#qvardump("\$existingHotelsByInTopId", $existingHotelsByInTopId);
	}

	/**
	* Save all the hotels from ETrip to our database
	* Used in crons
	*/
	public function cacheTopHotels(array $config = [], $onCitiesRequests = true, array $binds = [])
	{
		\Omi\TF\TOInterface::markReportStartpoint($config, 'hotels');
		$force = $config["force"] ?: false;
		$callMethod = "GetHotels";
		$callKeyIdf = null;
		$callParams = [];
		// we need to make sure that we have all data in order (countries and continents)
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
			
			echo $alreadyProcessed ? "Nothing changed from last request! <br />" : "No return <br />";
			
			return;
		}

		$hotelsInTopsIds = [];
		foreach ($return ?: [] as $hotel)
		{
			$hotel->Id .= "";
			if ($hotel->Id && $hotel->SourceId && $hotel->Source)
				$hotel->Id .= "|" . $hotel->Source;
			if ((empty($hotel->Id)) || (empty($hotel->Location)) || (empty(trim($hotel->Name))))
			{
				echo "<div style='color: red;'>Skip hotel because data is missing on it: " . json_encode($hotel)  . "</div>";
				continue;
			}
			$hotelsInTopsIds[$hotel->Id] = $hotel->Id;
		}

		$untouchedHotels = $hotelsInTopsIds ? QQuery("Hotels.{* WHERE TourOperator.Id=? AND FromTopRemoved=? AND InTourOperatorId NOT IN (?)}", [
			$this->TourOperatorRecord->getId(),
			false,
			$hotelsInTopsIds
		])->Hotels : [];

		$hotelsToBeRemoved = [];
		foreach ($untouchedHotels ?: [] as $h)
		{
			// reset all the hotel data
			$saveHotel = false;
			$h->FromTopRemoved = filter_var($h->FromTopRemoved, FILTER_VALIDATE_BOOLEAN);
			if ($h->HasIndividualOffers)
			{
				$h->setHasIndividualOffers(false);
				$saveHotel = true;
			}
			if ($h->HasCharterOffers)
			{
				$h->setHasCharterOffers(false);
				$saveHotel = true;
			}
			if ($h->HasHotelsOffers)
			{
				$h->setHasHotelsOffers(false);
				$saveHotel = true;
			}
			if ($h->HasChartersActiveOffers)
			{
				$h->setHasChartersActiveOffers(false);
				$saveHotel = true;
			}
			if ($h->HasPlaneChartersActiveOffers)
			{
				$h->setHasPlaneChartersActiveOffers(false);
				$saveHotel = true;
			}
			if ($h->HasBusChartersActiveOffers)
			{
				$h->setHasBusChartersActiveOffers(false);
				$saveHotel = true;
			}
			if ($h->HasIndividualActiveOffers)
			{
				$h->setHasIndividualActiveOffers(false);
				$saveHotel = true;
			}
			if ($h->HasHotelsActiveOffers)
			{
				$h->setHasHotelsActiveOffers(false);
				$saveHotel = true;
			}

			if (!$h->FromTopRemoved)
			{
				$saveHotel = true;
				$h->setFromTopRemoved(true);
				$h->setFromTopRemovedAt(date("Y-m-d H:i:s"));
			}

			if ($saveHotel)
			{
				echo "<div style='color: red;'>Hotel [{$h->InTourOperatorId}|{$h->Name}] not in tour operator anymore - flagged</div>";
				$hotelsToBeRemoved[] = $h;
			}
		}

		if ($hotelsToBeRemoved)
			$this->saveInBatchHotels($hotelsToBeRemoved, true);

		// check if cleanup was well done
		static::CheckCleanup($this->TourOperatorRecord);

		$dumpHotels = false;
		if ($dumpHotels)
		{
			
		}

		try 
		{
			$t1 = microtime(true);
			// we need to make sure that we have all data in order (countries and continents)
			#$this->resyncCountries($config);

			$pos = 0;
			$toProcessHotels = [];
			foreach ($return ?: [] as $hotel)
			{
				if ((!$hotel->Id) || (!$hotel->Location) || (!trim($hotel->Name)))
				{
					echo "<div style='color :red;'>Bad Format for hotel [{$hotel->Id}]: missing id, location or name</div>";
					continue;
				}
				$toProcessHotels[$hotel->Id] = $hotel;
			}

			$existingHotels = $this->getExistingHotelsByTopsIds(array_keys($toProcessHotels));
			$existingHotelsByInTopId = [];
			foreach ($existingHotels ?: [] as $eh)
				$existingHotelsByInTopId[$eh->InTourOperatorId] = $eh;

			$this->saveHotels_checkRooms($toProcessHotels, $existingHotelsByInTopId);

			$useNewFacilitiesFunctionality = (defined('USE_NEW_FACILITIES') && USE_NEW_FACILITIES);

			$toSetupFacilities = [];
			$facilitiesApp = \QApp::NewData();
			$existingFacilities = [];
			if ($useNewFacilitiesFunctionality)
			{
				foreach ($toProcessHotels ?: [] as $hotel)
				{
					foreach ($hotel->HotelAmenities ?: [] as $fac)
					{
						if (trim($fac))
							$toSetupFacilities[] = $fac;
					}
				}
				$existingFacilities = $this->getExistingHotelsFacilitiesByNames($toSetupFacilities);
			}

			$toSaveHotels = [];
			foreach ($toProcessHotels ?: [] as $hotel)
			{
				echo "<div style='color: darkgreen;'>Process hotel [{$hotel->Id}|{$hotel->Name}]</div>";
				list($hotelObj, $saveHotel) = $this->GetHotel($hotel, $existingHotelsByInTopId, true, $existingFacilities, $facilitiesApp);

				$saveHotelBecauseIndividualFlagsChanged = $this->flagHotelAsIndividualActive($hotelObj);
				if ($saveHotelBecauseIndividualFlagsChanged)
					$saveHotel = true;

				if ($hotelObj)
				{
					if ((!$hotelObj->getId()))
					{
						if ($this->TrackReport)
						{
							if (!$this->TrackReport->NewHotels)
								$this->TrackReport->NewHotels = 0;
							$this->TrackReport->NewHotels++;
						}
						echo "<div style='color: green;'>Add new hotel [{$hotelObj->InTourOperatorId}|{$hotelObj->Name}] to system</div>";
					}

					if ($saveHotel)
					{
						$hotelObj->setFromTopRemoved(false);
						$hotelObj->setFromTopRemovedAt(null);
						if (!$hotelObj->getId())
							$hotelObj->setFromTopAddedDate(date("Y-m-d H:i:s"));
						else
							$hotelObj->setFromTopModifiedDate(date("Y-m-d H:i:s"));
						$toSaveHotels[] = $hotelObj;	
					}
					else
					{
						echo "<div style='color: green;'>Hotel data not changed</div>";
					}
				}
				else
				{
					echo "<div style='color: red;'>Hotel can't be added to database</div>";
				}
				echo "<hr/>";
				$pos++;
			}

			#facilities app
			if ($useNewFacilitiesFunctionality && $facilitiesApp->HotelsFacilities)
				$facilitiesApp->save("HotelsFacilities.{*}");	

			if (count($toSaveHotels))
				$this->saveInBatchHotels($toSaveHotels, true);

			$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1));
		}
		catch (\Exception $ex) {
			throw $ex;
		}
		\Omi\TF\TOInterface::markReportEndpoint($config, 'hotels');
	}
	
	public function cacheTOPTours()
	{
		
	}
	
	public function GetHotel(\stdClass $hotelDescription, array $existingHotels = [], $dumpData = false, $existingFacilities = [], $facilitiesApp = null)
	{		
		if (!($destination = $this->getDestination($hotelDescription->Location)))
		{
			if ($dumpData)
				echo "<div style='color: red;'>Destination not found for hotel [{$hotelDescription->Id}]</div>";
			return [null, false];
		}

		$hotel = $existingHotels[$hotelDescription->Id];
		$saveHotel = false;

		if (!($hotel))
		{
			$hotel = new \Omi\Travel\Merch\Hotel();
			$hotel->InTourOperatorId = $hotelDescription->Id;
			$hotel->TourOperator = $this->TourOperatorRecord;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>New Hotel</div>";
		}
		
		$hotelName = trim(str_replace('&AMP;', '&', htmlspecialchars_decode($hotelDescription->Name)));
		if ($hotelName != trim($hotel->Name))
		{
			$hotel->Name = $hotelName;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Name changed</div>";
		}

		if (trim($hotelDescription->Phone) || trim($hotelDescription->Contact->Fax))
		{
			if (!$hotel->ContactPerson)
				$hotel->ContactPerson = new \Omi\Person();
			if (trim($hotel->ContactPerson->Phone) != trim($hotelDescription->Phone))
			{
				$hotel->ContactPerson->Phone = $hotelDescription->Phone . "";
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Contact person phone changed</div>";
			}
			if (trim($hotel->ContactPerson->Fax) != trim($hotelDescription->Fax))
			{
				$hotel->ContactPerson->Fax = $hotelDescription->Fax . "";
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Contact person fax changed</div>";
			}
		}

		if (!$hotel->Address)
			$hotel->Address = new \Omi\Address();

		#$addrLatitude = $hotelDescription->Latitude ? round($hotelDescription->Latitude, 8) : null;
		#$addrLongitude = $hotelDescription->Longitude ? round($hotelDescription->Longitude, 8) : null;

		$addrLatitude = $hotelDescription->Latitude ? \Omi\Address::GetNormalizedCoordinateValue($hotelDescription->Latitude) : null;
		$addrLongitude = $hotelDescription->Longitude ? \Omi\Address::GetNormalizedCoordinateValue($hotelDescription->Longitude) : null;

		if ($addrLatitude != $hotel->Address->Latitude)
		{
			$prevLatitude = $hotel->Address->Latitude;
			$hotel->Address->setLatitude($addrLatitude);
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Address Latitude changed from [{$prevLatitude}] to [{$addrLatitude}]</div>";
		}

		if ($addrLongitude != $hotel->Address->Longitude)
		{
			$prevLongitude = $hotel->Address->Longitude;
			$hotel->Address->setLongitude($addrLongitude);
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Address Longitude changed from [{$prevLongitude}] to [{$addrLongitude}]</div>";
		}
		// setup destination
		if (($destination instanceof \Omi\City))
		{
			if ((!$hotel->Address->City) || ($hotel->Address->City->getId() != $destination->getId()))
			{
				$hotel->Address->setCity($destination);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>City changed or setup new</div>";
			}
			if ($hotel->Address->Destination)
			{
				$hotel->Address->setDestination(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset destination - we have the city</div>";
			}
			if ($hotel->Address->County)
			{
				$hotel->Address->setCounty(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset county - we have the city</div>";
			}
			if ($hotel->Address->Country)
			{
				$hotel->Address->setCountry(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset country - we have the city</div>";
			}

		}
		else if ($destination instanceof \Omi\County)
		{
			if ((!$hotel->Address->County) || ($hotel->Address->County->getId() != $destination->getId()))
			{
				$hotel->Address->setCounty($destination);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>county changed or setup new</div>";
			}
			if ($hotel->Address->City)
			{
				$hotel->Address->setCity(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset city - we have the county</div>";
			}
			if ($hotel->Address->Country)
			{
				$hotel->Address->setCountry(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset country - we have the county</div>";
			}
			if ($hotel->Address->Destination)
			{
				$hotel->Address->setDestination(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset destination - we have the county</div>";
			}
		}
		else if ($destination instanceof \Omi\Country)
		{
			if ((!$hotel->Address->Country) || ($hotel->Address->Country->getId() != $destination->getId()))
			{
				$hotel->Address->setCountry($destination);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>country changed or setup new</div>";
			}
			if ($hotel->Address->City)
			{
				$hotel->Address->setCity(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset city - we have the country</div>";
			}
			if ($hotel->Address->County)
			{
				$hotel->Address->setCounty(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset county - we have the country</div>";
			}
			if ($hotel->Address->Destination)
			{
				$hotel->Address->setDestination(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset destination - we have the country</div>";
			}
		}
		else if ($destination instanceof \Omi\TF\Destination)
		{
			if ((!$hotel->Address->Destination) || ($hotel->Address->Destination->getId() != $destination->getId()))
			{
				$hotel->Address->setDestination($destination);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>destination changed or setup new</div>";
			}
			if ($hotel->Address->City)
			{
				$hotel->Address->setCity(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset city - we have the destination</div>";
			}
			if ($hotel->Address->County)
			{
				$hotel->Address->setCounty(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset county - we have the destination</div>";
			}
			if ($hotel->Address->Country)
			{
				$hotel->Address->setCountry(null);
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Reset country - we have the destination</div>";
			}
		}

		if (!$hotel->Content)
			$hotel->Content = new \Omi\Cms\Content();

		/*
		$hotelShortDescription = htmlspecialchars_decode(html_entity_decode($hotelDescription->Description));
		$hotelShortDescriptionWT = strip_tags($hotelShortDescription);
		$hasFacilities = ($hotelDescription->HotelAmenities && (count($hotelDescription->HotelAmenities) > 0));
		$useHotelDescription = null;
		$descr = "";
		$textFacilities = "";
		if ($hotelDescription->DetailedDescriptions)
		{
			$detailedDescriptionsByIndex = [];
			foreach ($hotelDescription->DetailedDescriptions as $d)
			{
				$useIndx = $d->Index ?: 0;
				$detailedDescriptionsByIndex[$useIndx][] = $d;
			}
			ksort($detailedDescriptionsByIndex);
			
			$facilitiesTexts = [];
			foreach ($detailedDescriptionsByIndex ?: [] as $detailedDescriptions)
			{
				foreach ($detailedDescriptions ?: [] as $d)
				{
					$d->Text = preg_replace('#\r\n|\r\t|\n|\t#', '', nl2br($d->Text));

					#$dlabel = htmlspecialchars_decode(html_entity_decode($d->Label));
					$dlabel = strip_tags(htmlspecialchars_decode(html_entity_decode($d->Label)));

					#$dTxt = htmlspecialchars_decode(html_entity_decode($d->Text));
					#$dTxtWT = strip_tags($dTxt);

					$dTxt = strip_tags(htmlspecialchars_decode(html_entity_decode($d->Text)));
					$dTxtWT = $dTxt;
					
					$isSameTextAsDescription = ($hotelShortDescriptionWT == $dTxtWT);
					if ($isSameTextAsDescription)
					{
						$useHotelDescription = $dlabel . "<br />"  . $hotelShortDescription . "<br /><br />";
						continue;
					}

					$addToDescription = true;
					if (($isFacilitiesText = (stripos($d->Label, "facilitati") !== false)))
					{
						$facilitiesTexts[] = $d;
						if (!$hasFacilities)
							$addToDescription = false;
					}

					if ($addToDescription)
						$descr .= $dlabel . "<br />"  . $dTxt . "<br /><br />";
				}
			}

			if ($facilitiesTexts)
			{
				if (count($facilitiesTexts) > 1)
				{
					foreach ($facilitiesTexts ?: [] as $ft)
					{
						#$ftlabel = htmlspecialchars_decode(html_entity_decode($ft->Label));
						#$ftTxt = htmlspecialchars_decode(html_entity_decode($ft->Text));
						
						$ftlabel = strip_tags(htmlspecialchars_decode(html_entity_decode($ft->Label)));
						$ftTxt = strip_tags(htmlspecialchars_decode(html_entity_decode($ft->Text)));
						$textFacilities .= $ftlabel . "<br />"  . $ftTxt . "<br /><br />";
					}
				}
				else
				{
					$textFacilities = htmlspecialchars_decode($d->Text);
				}
			}
		}
		if ($useHotelDescription)
			$hotelShortDescription = $useHotelDescription;
		$hotelShortDescription .= $descr;
		*/

		$hotelShortDescription = htmlspecialchars_decode(html_entity_decode(trim($hotelDescription->Description)));
		$hotelContentDDs = "";
		if ($hotelDescription->DetailedDescriptions)
		{
			$detailedDescriptionsByIndex = [];
			foreach ($hotelDescription->DetailedDescriptions as $d)
			{
				$useIndx = $d->Index ?: 0;
				$detailedDescriptionsByIndex[$useIndx][] = $d;
			}
			ksort($detailedDescriptionsByIndex);
			foreach ($detailedDescriptionsByIndex ?: [] as $detailedDescriptions)
			{
				foreach ($detailedDescriptions ?: [] as $d)
				{
					//$d->Text = preg_replace('#\r\n|\r\t|\n|\t#', '', nl2br($d->Text));
					#$dlabel = strip_tags(htmlspecialchars_decode(html_entity_decode(trim($d->Label))));
					#$dTxt = strip_tags(htmlspecialchars_decode(html_entity_decode(trim($d->Text))));

					$dlabel = $this->fixHtml(trim($d->Label));
					$dTxt = $this->fixHtml(trim($d->Text));

					/*
					if ($hotelDescription->Id == 9359)
					{
						echo "\n";
						var_dump('$dTxt', $dTxt, $d->Text);
					}
					*/

					#$hotelContentDDs .= $dlabel . "<br />"  . $dTxt . "<br /><br />";
					$hotelContentDDs .= $dlabel . $dTxt;
				}
			}
		}

		$textFacilities = null;
		$hotelContent = $hotelShortDescription . (strlen($hotelContentDDs) ? "<br /><br />" : "") . $hotelContentDDs;

		if ($hotel->TextFacilities != $textFacilities)
		{
			$hotel->TextFacilities = $textFacilities;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>TextFacilities changed</div>";
		}

		if ($hotel->Content->ShortDescription != $hotelShortDescription)
		{
			$hotel->Content->ShortDescription = $hotelShortDescription;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Short description changed</div>";
		}

		if ($hotel->Content->Content != $hotelContent)
		{
			$hotel->Content->Content = $hotelContent;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Content changed</div>";
		}

		$hotel->MTime = date("Y-m-d");

		if ($hotel->Stars != $hotelDescription->Class)
		{
			$hotel->Stars = $hotelDescription->Class;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Stars changed</div>";
		}

		if ($hotel->Code != $hotelDescription->Code . "-" . $hotelDescription->Id)
		{
			$hotel->Code = $hotelDescription->Code . "-" . $hotelDescription->Id;
			$saveHotel = true;
			if ($dumpData)
				echo "<div style='color: red;'>Code has changed</div>";
		}

		if (!$hotel->Content->ImageGallery)
			$hotel->Content->ImageGallery = new \Omi\Cms\Gallery();

		if (!$hotel->Content->ImageGallery->Items)
			$hotel->Content->ImageGallery->Items = new \QModelArray();

		$existingImages = [];
		foreach ($hotel->Content->ImageGallery->Items as $k => $itm)
		{
			if (!$itm->RemoteUrl)
				continue;

			if (isset($existingImages[$itm->RemoteUrl]))
			{
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Remove duplicate image</div>";
				$itm->_toRM = true;
				$hotel->Content->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $k);
				continue;
			}
			$existingImages[$itm->RemoteUrl] = [$k, $itm];
		}

		if (!static::$_CacheData["_API_BASE_URL_"])
		{
			$_m = null;
			preg_match("/(.*?).ro\//", $this->TourOperatorRecord->ApiUrl, $_m);
			static::$_CacheData["_API_BASE_URL_"] = $_m[0];
		}

		$_IMG_URL = static::$_IMAGES_URLS[$this->TourOperatorRecord->Handle][static::$_CacheData["_API_BASE_URL_"]];

		$processedImages = [];
		foreach ($hotelDescription->Images ?: [] as $img_source)
		{
			$url = ($img_source->Url ?: $img_source->URL);
			if (!$url)
			{
				if (!$_IMG_URL)
				{
					qvardump('$hotelDescription', $hotelDescription);
					throw new \Exception("IMAGE URL NOT DEFINED FOR STORAGE " . $this->TourOperatorRecord->Handle);
				}
			}

			$imgUrl = $url ?: (($img_source->Id > 0) ? ($_IMG_URL ? preg_replace("/__IMG_SOURCE__/", $img_source->Id, $_IMG_URL) : null) : $url);
			if ($imgUrl)
			{
				#$image = $_IMG_URL ? preg_replace("/__IMG_SOURCE__/", $img_source->Id, $_IMG_URL) : null;
				$image  = $imgUrl;

				if (!$image || (!($image = trim($image))))
					continue;

				if ($existingImages[$image])
				{
					list($_kim, $img) = $existingImages[$image];
				}
				else	
				{
					$img = new \Omi\Cms\GalleryItem();
					$img->setFromTopAddedDate(date("Y-m-d H:i:s"));
					$saveHotel = true;
					if ($dumpData)
						echo "<div style='color: red;'>Add new image [{$image}]</div>";
				}

				// pull tour operator image
				$imagePulled = $img->setupTourOperatorImage($image, $this->TourOperatorRecord, $hotel, md5($image), IMAGES_URL, IMAGES_REL_PATH,
					$img_source->Name);

				if (!$imagePulled)
				{
					if ($dumpData)
					{
						if (!$img->getId())
							echo "<div style='color: red;'>Image cannot be pulled so it will not be added to hotel images [{$image}]</div>";
					}
					continue;
				}

				if (!$img->getId())
					$hotel->Content->ImageGallery->Items[] = $img;

				$processedImages[$image] = true;
			}
		}

		// remove extra images
		foreach ($existingImages as $imgUrl => $itmData)
		{
			if (isset($processedImages[$imgUrl]))
				continue;
			if ($dumpData)
				echo "<div style='color: red;'>Remove extra image [{$imgUrl}]</div>";
			list($key, $itm) = $itmData;
			$itm->_toRM = true;
			$saveHotel = true;
			$hotel->Content->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $key);
		}

		if (!$hotel->Rooms)
			$hotel->Rooms = new \QModelArray();

		$existingRooms = [];

		$hotelNewRooms = new \QModelArray();
		$hotelNewRooms->_rowi = [];
		foreach ($hotel->Rooms as $_k => $room)
		{
			#$room->InTourOperatorId = (int)$room->InTourOperatorId;
			
			// ignore duplicates
			if (isset($existingRooms[$room->InTourOperatorId]))
			{
				continue;
			}
			$hotelNewRooms[$_k] = $room;
			$hotelNewRooms->_rowi[$_k] = $hotel->Rooms->_rowi[$_k];
			$existingRooms[$room->InTourOperatorId] = [$room, $_k];
		}
		$hotel->Rooms = $hotelNewRooms;
	
		$useNewFacilitiesFunctionality = (defined('USE_NEW_FACILITIES') && USE_NEW_FACILITIES);

		$processedRooms = [];
		$alreadyAddedRooms = [];
		foreach ($hotelDescription->RoomCategories ?: [] as $room_cat)
		{
			$room_cat->Id = (int)$room_cat->Id;
			if ($room_cat->SourceId)
				$room_cat->Id .= "|" . $room_cat->SourceId;

			if (!trim($room_cat->Name))
			{
				if ($dumpData)
					echo "<div style='color: red;'>Room does not have name - {$room_cat->Id}</div>";
				continue;
			}

			if (isset($alreadyAddedRooms[$room_cat->Id]))
			{
				if ($dumpData)
					echo "<div style='color: red;'>Room is duplicate - {$room_cat->Id}</div>";
				continue;
			}
			$alreadyAddedRooms[$room_cat->Id] = $room_cat->Id;
			
			list($existingRoom) = $existingRooms[$room_cat->Id];

			$room = $existingRoom ?: new \Omi\Travel\Merch\Room();
			$processedRooms[$room_cat->Id] = $room_cat->Id;
			$room->setInTourOperatorId($room_cat->Id);

			if (!$room->getId())
			{
				$room->setFromTopAddedDate(date("Y-m-d H:i:s"));
				$saveHotel = true;
				$hotel->Rooms[] = $room;
				if ($dumpData)
					echo "<div style='color: red;'>Add new room [{$room_cat->Name}-{$room_cat->Id}]</div>";
			}
			
			$roomTitle = trim($room_cat->Name);
			if ($room->Title != $roomTitle)
			{
				$prevTitle = $room->Title;
				$room->Title = $roomTitle;
				$saveHotel = true;
				if ($dumpData && $room->getId())
					echo "<div style='color: red;'>Change room title [{$room_cat->Id}] from [{$prevTitle}] to [{$roomTitle}]</div>";
			}
			$room->setTourOperator($this->TourOperatorRecord);

			

			if (!$useNewFacilitiesFunctionality)
			{
				if (!$room->Facilities)
					$room->Facilities = new \QModelArray();

				$refs = [];
				foreach ($room->Facilities as $fk => $facility)
				{
					if (!$facility->Name)
					{
						$saveHotel = true;
						if ($dumpData)
							echo "<div style='color: red;'>Remove empty facility on room [{$roomTitle}]</div>";
						$room->Facilities->setTransformState(\QIModel::TransformDelete, $fk);
					}
					else
						$refs[$facility->Name] = [$facility, $fk];
				}

				$roomProcessedFacilities = [];
				foreach ($hotelDescription->RoomAmenities ?: [] as $rf)
				{
					$roomProcessedFacilities[$rf] = $rf;
					if ($refs[$rf])
						continue;
					if ($dumpData)
						echo "<div style='color: red;'>Save new facility on room [{$rf}]</div>";
					$saveHotel = true;
					$rFacility = new \Omi\Travel\Merch\RoomFacility();
					$rFacility->Name = $rf;
					$rFacility->Active = true;
					$room->Facilities[] = $rFacility;
				}

				foreach ($refs as $name => $facilityData)
				{
					if (isset($roomProcessedFacilities[$name]))
						continue;
					$saveHotel = true;
					if ($dumpData)
						echo "<div style='color: red;'>Remove extra facility {$name} on room [{$roomTitle}]</div>";
					list($facility, $facilityKey) = $facilityData;
					$room->Facilities->setTransformState(\QModel::TransformDelete, $facilityKey);
				}
			}
		}

		foreach ($existingRooms ?: [] as $roomID => $roomData)
		{
			if (!isset($processedRooms[$roomID]))
			{
				$saveHotel = true;
				list($room, $roomK) = $roomData;
				if ($dumpData)
					echo "<div style='color: red;'>Remove extra room [{$roomID} - {$room->getId()}]</div>";
				$hotel->Rooms->setTransformState(\QIModel::TransformDelete, $roomK);
			}
		}

		if (!$useNewFacilitiesFunctionality)
		{
			// merge hotel facilities
			if (!$hotel->Facilities)
				$hotel->Facilities = new \QModelArray();

			$existingFacilities = [];
			foreach ($hotel->Facilities as $fk => $facility)
			{
				if (!($facility->Name))
				{
					$saveHotel = true;
					if ($dumpData)
						echo "<div style='color: red;'>Remove empty facility</div>";
					$hotel->Facilities->setTransformState(\QIModel::TransformDelete, $fk);
				}
				else
					$existingFacilities[$facility->Name] = [$facility, $fk];
			}

			$processedFacilities = [];
			foreach ($hotelDescription->HotelAmenities ?: [] as $fac)
			{
				$processedFacilities[$fac] = $fac;
				if ($existingFacilities[$fac])
					continue;
				$saveHotel = true;
				if ($dumpData)
					echo "<div style='color: red;'>Add new facility [{$fac}]</div>";
				$hFacility = new \Omi\Travel\Merch\HotelFacility();
				$hFacility->setFromTopAddedDate(date("Y-m-d H:i:s"));
				$hFacility->Name = $fac;
				$hFacility->Active = true;
				$hotel->Facilities[] = $hFacility;
			}

			foreach ($existingFacilities as $name => $facilityData)
			{
				if (isset($processedFacilities[$name]))
					continue;
				if ($dumpData)
					echo "<div style='color: red;'>Remove extra facility [{$name}]</div>";
				$saveHotel = true;
				list($facility, $facilityKey) = $facilityData;
				$facility->setTransformState(\QModel::TransformDelete);
				$hotel->Facilities->setTransformState(\QIModel::TransformDelete, $facilityKey);
			}

		}
		else
		{
			$newFacilities = [];
			foreach ($hotelDescription->HotelAmenities ?: [] as $fac)
			{
				if (trim($fac))
					$newFacilities[] = $fac;
			}

			$this->syncHotelFacilities($hotel, $newFacilities, $existingFacilities, $saveHotel, $facilitiesApp);
		}

		if (!$hotel->getId())
		{
			$hotelIsActive = true;
			if (function_exists("q_getTopHotelActiveStatus"))
				$hotelIsActive = q_getTopHotelActiveStatus($hotel);
			$hotel->setActive($hotelIsActive);
			$saveHotel = true;
		}

		return [$hotel, $saveHotel];
	}
	
	public static function CheckCleanup($tourOperator)
	{
		$mysqli = \QApp::GetStorage()->connection;
		$roomType = getTypeId('Omi\Travel\Merch\Room');
		$r = $mysqli->query("DELETE FROM `Merch_Facilities` USING `Merch_Facilities` JOIN `Merch` ON (`Merch_Facilities`.`\$Merch`=`Merch`.`\$id` " 
			. "AND `Merch`.`\$_type`='" . $roomType . "') WHERE `Merch_Facilities`.`\$Facilities` NOT IN (SELECT `\$id` FROM `RoomsFacilities` WHERE 1)");
		if ($r === false)
		{
			#echo $mysqli->error;
			throw new \Exception("Cleanup not well done!");
		}

		$r = $mysqli->query("UPDATE `Merch` LEFT JOIN `Hotels` ON (`Merch`.`\$Hotel`=`Hotels`.`\$id`) "
			. "SET `Merch`.`\$TourOperator`=NULL, `Merch`.`InTourOperatorId`=NULL WHERE `Merch`.`\$_type`='" . $roomType . "' " 
			. "AND (ISNULL(`Hotels`.`InTourOperatorId`) OR `Hotels`.`FromTopRemoved`=1) AND `Merch`.`\$TourOperator`='" . $tourOperator->getId() . "';");
		if ($r === false)
		{
			#echo $mysqli->error;
			throw new \Exception("Cleanup not well done!");
		}
		
		$r = $mysqli->query("DELETE FROM `TravelCategories_TravelItems` WHERE `\$Category` NOT IN (SELECT `\$id` FROM `TravelCategories` WHERE 1);");
		if ($r === false)
		{
			#echo $mysqli->error;
			throw new \Exception("Cleanup not well done!");
		}

		$r = $mysqli->query("DELETE FROM `Cities_Hotels` WHERE `\$City` NOT IN (SELECT `\$id` FROM `Cities` WHERE 1);");
		if ($r === false)
		{
			#echo $mysqli->error;
			throw new \Exception("Cleanup not well done!");
		}

		$r = $mysqli->query("SELECT `\$id` FROM `Hotels` WHERE `\$City` NOT IN (SELECT `\$id` FROM `Cities` WHERE 1) LIMIT 1;");
		if ($r === false)
		{
			throw new \Exception("Cleanup not well done!");
		}
		if ($r && ($r->num_row > 0))
		{
			$r = $mysqli->query("UPDATE `Hotels` SET `\$City`=NULL WHERE `\$City` NOT IN (SELECT `\$id` FROM `Cities` WHERE 1);");
			if ($r === false)
			{
				#echo $mysqli->error;
				throw new \Exception("Cleanup not well done!");
			}
		}

		$r = $mysqli->query("SELECT `\$id` FROM `Hotels` WHERE `\$County` NOT IN (SELECT `\$id` FROM `Counties` WHERE 1) LIMIT 1;");
		if ($r === false)
		{
			throw new \Exception("Cleanup not well done!");
		}
		if ($r && ($r->num_row > 0))
		{
			$r = $mysqli->query("UPDATE `Hotels` SET `\$County`=NULL WHERE `\$County` NOT IN (SELECT `\$id` FROM `Counties` WHERE 1);");
			if ($r === false)
			{
				#echo $mysqli->error;
				throw new \Exception("Cleanup not well done!");
			}
		}
	}
}