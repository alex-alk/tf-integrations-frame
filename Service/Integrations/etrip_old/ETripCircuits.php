<?php

namespace Omi\TF;

trait ETripCircuits
{
	
	public function GetTour_setupContent($tour, $tourDetails, $hotelObj, $contentProp, $doDiag)
	{
		$tour_id = $tourDetails->Id ?: $tourDetails->PackageId;
		$contentChanged = false;
		#$tourDescription = trim(nl2br($tourDetails->Description . ($hotelObj->Content ? "<br/>" . $hotelObj->Content->ShortDescription : "")));
		$tourDescription = trim($tourDetails->Description . ($hotelObj->Content ? "<br/>" . $hotelObj->Content->ShortDescription : ""));
		if ($tour->{$contentProp}->ShortDescription != $tourDescription)
		{
			#$existingShortDescription = $tour->{$contentProp}->ShortDescription;
			$tour->{$contentProp}->ShortDescription = $tourDescription;
			$contentChanged = true;
			if ($doDiag)
			{
				echo "<div style='color: red;'>Save tour: Short description changed for [{$tour_id}]</div>";
			}
		}

		#$tourContent = trim(nl2br($tourDetails->Description
		$tourContent = trim(($tourDetails->Description
				. ($tourDetails->IncludedServices ? "<br/>Servicii incluse: <br/>" . nl2br($tourDetails->IncludedServices) : "")
				. ($tourDetails->NotIncludedServices ? "<br/>Servicii neincluse: <br/>" . nl2br($tourDetails->NotIncludedServices) : "")
				. ($hotelObj->Content ? "<br/>" . $hotelObj->Content->Content : "")));

		if ($tour->{$contentProp}->Content != $tourContent)
		{
			#$existingContent = $tour->{$contentProp}->Content;
			$tour->{$contentProp}->Content = $tourContent;
			$contentChanged = true;
			if ($doDiag)
			{
				echo "<div style='color: red;'>Save tour: Content changed for [{$tour_id}]</div>";
			}
		}

		if (!$tour->{$contentProp}->ImageGallery)
			$tour->{$contentProp}->ImageGallery = new \Omi\Cms\Gallery();

		if (!$tour->{$contentProp}->ImageGallery->Items)
			$tour->{$contentProp}->ImageGallery->Items = new \QModelArray();

		$existingImages = [];
		$existingImagesKeys = [];
		foreach ($tour->{$contentProp}->ImageGallery->Items as $_k => $img)
		{
			$existingImages[$img->RemoteUrl] = $img;
			$existingImagesKeys[$img->RemoteUrl] = $_k;
		}

		$processedImages = [];
		if ($hotelObj && $hotelObj->Content && $hotelObj->Content->ImageGallery && $hotelObj->Content->ImageGallery->Items)
		{
			foreach ($hotelObj->Content->ImageGallery->Items as $img)
			{
				$processedImages[$img->RemoteUrl] = $img;
				if (!isset($existingImages[$img->RemoteUrl]))
				{
					$contentChanged = true;
					$imgClone = $img->getClone('Path, Type, ExternalUrl, RemoteUrl, Base64Data, TourOperator.Id, InTourOperatorId, Alt, FromTopAddedDate');
					$tour->{$contentProp}->ImageGallery->Items[] = $imgClone;
					if ($doDiag)
						echo "<div style='color: red;'>Save tour: New image added for [{$tour_id}]</div>";
				}
			}
		}

		foreach ($existingImages ?: [] as $img)
		{
			if (!isset($processedImages[$img->RemoteUrl]))
			{
				$tour->{$contentProp}->ImageGallery->Items->setTransformState(\QIModel::TransformDelete, $existingImagesKeys[$img->RemoteUrl]);
				$contentChanged = true;
				if ($doDiag)
					echo "<div style='color: red;'>Save tour: Remove image added for [{$tour_id}]</div>";
			}
		}
		return $contentChanged;
	}

	public function GetTour($tourDetails, $tour = null, $hotelObj = null)
	{
		$tour_id = $tourDetails->Id ?: $tourDetails->PackageId;
		$saveTour = false;
		$doDiag = false;
		if (!$tour)
		{
			$tour = new \Omi\Travel\Merch\Tour();
			$tour->InTourOperatorId = $tour_id;
			$tour->setFromTopAddedDate(date("Y-m-d H:i:s"));
			$tour->TourOperator = $this->TourOperatorRecord;
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: New Tour [{$tour_id}]</div>";
		}

		if (!($hotelObj))
			$hotelObj = $this->GetETripHotelFromDB($tourDetails->Hotel);

		if (!$tour->_added_hotels)
			$tour->_added_hotels = [];

		if (!$tour->Hotels)
			$tour->Hotels = new \QModelArray();

		$e_hotels = [];
		foreach ($tour->Hotels ?: [] as $hotel)
			$e_hotels[$hotel->getId()] = $hotel->getId();

		if (!isset($e_hotels[$hotelObj->getId()]) && (!isset($tour->_added_hotels[$hotelObj->getId()])))
		{
			$tour->Hotels[] = $hotelObj;
			$tour->_added_hotels[$hotelObj->getId()] = $hotelObj->getId();
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Add new hotel [{$tour_id}]</div>";
		}

		if ((int)$tourDetails->Duration != (int)$tour->Period)
		{
			$tour->Period = (int)$tourDetails->Duration;
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Period changed [{$tour_id}]</div>";
		}

		if (!$tour->Location)
			$tour->Location = new \Omi\Address();

		if ($hotelObj && $hotelObj->Address)
		{
			$eLocationCity = $tour->Location->City;
			$eLocationCounty = $tour->Location->County;
			$eLocationCountry = $tour->Location->Country;
			$eLocationDestination = $tour->Location->Destination;

			$tour->Location->City = $hotelObj->Address->City;
			$tour->Location->County = $hotelObj->Address->County;
			$tour->Location->Country = $hotelObj->Address->Country;
			$tour->Location->Destination = $hotelObj->Address->Destination;

			if ((($eLocationCity && (!$tour->Location->City)) || ((!$eLocationCity) && $tour->Location->City) || 
				($eLocationCity && $tour->Location->City && ($eLocationCity->getId() != $tour->Location->City->getId()))) || 

				(($eLocationCounty && (!$tour->Location->County)) || ((!$eLocationCounty) && $tour->Location->County) || 
				($eLocationCounty && $tour->Location->County && ($eLocationCounty->getId() != $tour->Location->County->getId()))) || 

				(($eLocationCountry && (!$tour->Location->Country)) || ((!$eLocationCountry) && $tour->Location->Country) || 
				($eLocationCountry && $tour->Location->Country && ($eLocationCountry->getId() != $tour->Location->Country->getId()))) || 

				(($eLocationDestination && (!$tour->Location->Destination)) || ((!$eLocationDestination) && $tour->Location->Destination) || 
				($eLocationDestination && $tour->Location->Destination && ($eLocationDestination->getId() != $tour->Location->Destination->getId())))
			)
			{
				$saveTour = true;
				if ($doDiag)
					echo "<div style='color: red;'>Save tour: Location changed [{$tour_id}]</div>";
			}
		}

		$tourCode = ($tourDetails->Id ? $tourDetails->Id : $tourDetails->PackageId);
		if ($tourCode != $tour->Code)
		{
			$tour->Code = $tourCode;
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Code changed [{$tour_id}]</div>";
		}

		if (!($tour->Content))
		{
			$tour->Content = new \Omi\Cms\Content();
			$tour->Content->setActive(true);
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Content active changed [{$tour_id}]</div>";
		}
		
		if (!$tour->TopContent)
		{
			$tour->TopContent = new \Omi\Cms\Content();
			$tour->TopContent->setActive(true);
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Top Content active changed [{$tour_id}]</div>";
		}

		$topTitle = trim($tourDetails->Name);
		if ($tour->TopTitle != $topTitle)
		{
			$tour->TopTitle = $topTitle;
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Top Title changed [{$tour_id}]</div>";
		}

		$contentChanged = $this->GetTour_setupContent($tour, $tourDetails, $hotelObj, "TopContent", $doDiag);

		if ($contentChanged)
		{
			$saveTour = true;
			if ($doDiag)
				echo "<div style='color: red;'>Save tour: Top Content changed [{$tour_id}]</div>";
		}

		// merge description only if not content blocked
		if (!($tour->BlockContentUpdate))
		{
			//qvardump("UPDATE TOUR CONTENT::", $tour, $tourDetails);
			if ($tour->Title != $topTitle)
			{
				$tour->Title = $topTitle;
				$saveTour = true;
				if ($doDiag)
					echo "<div style='color: red;'>Save tour: Title changed [{$tour_id}]</div>";
			}

			$contentChanged = $this->GetTour_setupContent($tour, $tourDetails, $hotelObj, "Content", $doDiag);
			if ($contentChanged)
			{
				$saveTour = true;
				if ($doDiag)
					echo "<div style='color: red;'>Save tour: Content changed [{$tour_id}]</div>";
			}
		}

		return [$tour, $saveTour];
	}

	public function saveTours__DEPRECATED($force = false, $config = null)
	{
		$this->saveCachedData(true, $force, $config);
	}
	/**
	 * @api.enable
	 * @param type $request
	 * @param type $handle
	 * @param type $params
	 * @param type $_nights
	 */
	public static function PullTours($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false)
	{
		return static::PullCacheData($request, $handle, $params, $_nights, true, $do_cleanup, $skip_cache);
	}
}