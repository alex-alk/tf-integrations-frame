<?php

namespace Omi\TF;

class Sejour extends \Omi\TF\TOInterface
{
	use TOInterface_Util;
	
	public $debug = true;
	
	public $cacheTimeLimit = 60 * 60 * 24;
	
	public static $BaseUrl;
	
	protected static $Databases = [
		"magellan" => "MAGELLAN",
		"ireles_travel" => "IRELS2022",
	];
	
	/* ----------------------------plane tickets operators---------------------*/

	public function api_getCarriers(array $filter = null)
	{
		
	}

	public function api_getAirports(array $filter = null)
	{
		
	}

	public function api_getRoutes(array $filter = null)
	{
		
	}

	/* ----------------------------end plane tickets operators---------------------*/
	
	public function getSoapClientByMethodAndFilter($method, $filter = null)
	{
		return false;
	}
	
	public function api_testConnection(array $filter = null)
	{
		#return $this->login();
		$loginParams = [
			"databaseName" => $this->getDatabaseName(),
			"userName" => ($this->ApiUsername__ ?: $this->ApiUsername), 
			"password" => ($this->ApiPassword__ ?: $this->ApiPassword)
		];

		// get login response
		list($loginResp, $soap) = $this->__request("Authentication", "Login", $loginParams);

		// lagin successfuly, get auth key (token)
		if ($loginResp->LoginResult->Authenticated)
		{
			$this->authKey = $loginResp->LoginResult->AuthKey;
			return true;
		}
		else
		{
			if (isset($loginResp->LoginResult))
			{
				echo '<div style="color: red;">' . ($loginResp->LoginResult->Description ?: ($soap ? $soap->__getLastResponse() : 'unknown issue')) . '</div>';
			}
			else
			{
				echo '<div style="color: red;">' . ($soap ? $soap->__getLastResponse() : 'unknown issue') . '</div>';
			}

			return false;
		}
	}

	/**
	 * Gets the countries.
	 * Response format: 
	 *		array of: Id,Name,Code
	 * 
	 * @param array $filter Apply a filter like: [Id => , Name => , Code => ]
	 *						For more complex: [Name => ['like' => '...']]
	 * 
	 */
	public function api_getCountries(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'countries');
		// init countries array
		$countries = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count countries - added static: %s', [2]);
		
		// we have only one country: Bulgaria
		$country		= new \stdClass();
		$country->Id	= 'BG';
		$country->Name	= 'Bulgaria';
		$country->Code	= 'BG';
		
		\Omi\TF\TOInterface::markReportData($filter, 'Manul added country: %s', [$country->Id . ' ' . $country->Name], 50);

		// add country to array of countries
		$countries[$country->Id] = $country;
		
		// we have only one country: Bulgaria
		$country		= new \stdClass();
		$country->Id	= 'TR';
		$country->Name	= 'Turcia';
		$country->Code	= 'TR';
		
		\Omi\TF\TOInterface::markReportData($filter, 'Manul added country: %s', [$country->Id . ' ' . $country->Name], 50);

		// add country to array of countries
		$countries[$country->Id] = $country;

		// return country by country code
		if ($filter['CountryCode'])
			return [$countries[$filter['CountryCode']]];
		
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
		// return countries
		return [$countries];
	}

	/**
	 * Gets the regions.
	 * Response format: 
	 *		array of: Id,Name,Code,CountryId,CountryCode
	 * 
	 * @param array $filter See $filter in general, CountryCode, CountryId
	 */
	public function api_getRegions(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'regions');
		$allRegions = [];
		$date = date("Y-m-d");
		$count = 0;

		while ($count < 12)
		{
			// setup params
			$params = [
				"cinDate" => $date . 'T00:00:00', // date('Y-m-d\TH:i:s'), 
				"isUrun" => false, 
				"InsertEmptyRecord" => false
			];

			// request common regions
			$regionsResp = $this->request("Common", "GetRegionsDS", $params);

			// get regions in xml format
			$regionsXML = $regionsResp->GetRegionsDSResult->any;

			// decode regions
			$regionsData = json_decode(json_encode(simplexml_load_string($regionsXML)));

			// increment counter
			$count++;

			// increment date
			$date = date("Y-m-d", strtotime("+ 1 month", strtotime($date)));

			// exit if no regions after request
			if (!$regionsData->NewDataSet->Region)
				continue;

			// put regions into an array
			$regionList = is_array($regionsData->NewDataSet->Region) ? $regionsData->NewDataSet->Region : [$regionsData->NewDataSet->Region];
			foreach ($regionList ?: [] as $regionData)
				$allRegions[$regionData->Region] = $regionData;
		}

		// get all countries
		list($allCountries) = $this->api_getCountries(['skip_report' => true]);

		// init regions array
		$regions = [];
		
		$regionsCountriesMapping = $this->getCountriesRegionsMapping();
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count regions: %s', [$allRegions ? q_count($allRegions) : 'no_regions']);

		// go though each region and create object
		foreach ($allRegions ?: [] as $regionData)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', [$regionData->Region . ' ' . $regionData->Description], 50);
			$region				= new \stdClass();
			$region->Id			= $regionData->Region;
			$region->Name		= $regionData->Description;
			$region->Code		= $regionData->Region;
			if (!($countryCode = $regionsCountriesMapping[$this->TourOperatorRecord->Handle][$regionData->Region]))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Country not mapped for region %s', [json_encode($regionData)], 50);
				continue;
			}
			
			if (!$country = $allCountries[$countryCode])
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Country not found for region %s', [json_encode($regionData)], 50);
				continue;
			}

			$region->Country	= $country;
			// add region to regions array
			$regions[$region->Id] = $region;
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
		// return regions
		return [$regions];
	}

	public function getCountriesRegionsMapping()
	{
		return [
			'ireles_travel' => [
				"BLK" => "TR",
				"CAN" => "TR",
				"SID" => "TR",
				"ALY" => "TR",
				"BOD" => "TR",
				"DID" => "TR",
				"FET" => "TR",
				"KMR" => "TR",
				"KUS" => "TR",
				"LAR" => "TR",
				"MAR" => "TR",
				"OZD" => "TR",
				"CSM" => "TR",
				"IZM" => "TR",
				"SAR" => "TR",
				"AKC" => "TR",
				"AYV" => "TR",
			],
			"magellan" => [
				"ALB" => "BG",
				"BAL" => "BG",
				"BAN" => "BG",
				"BOR" => "BG",
				"GS" => "BG",
				"KAV" => "BG",
				"NES" => "BG",
				"PAM" => "BG",
				"SB" => "BG",
				"SKE" => "BG",
				"SUN" => "BG",
				"AHL" => "BG",
				"RAZ" => "BG",
				"DUN" => "BG",
				"ELE" => "BG",
				"KRA" => "BG",
				"OBZ" => "BG",
				"POM" => "BG",
				"PRI" => "BG",
				"RAV" => "BG",
				"SHK" => "BG",
				"SOZ" => "BG",
				"SVL" => "BG",
				"KIT" => "BG",
				"CAI" => "BG"
			]
		];
	}

	/**
	 * Gets the regions.
	 * Response format: 
	 *		array of: Id,Name,Code,IsResort,ParentCity.Id,ParentCity.Code,Region.Code,Region.Id,Country.Code,Country.Id
	 * 
	 * @param array $filter
	 */
	public function api_getCities(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'cities');
		// get regions
		list($regions) = $this->api_getRegions(['skip_report' => true]);
		
		// init cities array
		$cities = [];

		\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s', [$regions ? q_count($regions) : 'no_cities']);

		// go through each region
		foreach ($regions as $region)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$region->Id . ' ' . $region->Name], 50);

			// create new city object
			$city			= new \stdClass();
			$city->Id		= $region->Id;
			$city->Name		= $region->Name;
			$city->Code		= $region->Code;
			$city->County	= $region;
			$city->Country	= $region->Country;
			
			// add city to cities array
			$cities[$city->Id] = $city;
		}
		
		// return city by code
		if ($filter['CityCode'])
			return $cities[$filter['CityCode']];
		
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
		// return cities
		return [$cities];
	}
	
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getBoardTypes(array $filter = null)
	{
		
	}
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getRoomTypes(array $filter = null)
	{
		
	}
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getRoomsFacilities(array $filter = null)
	{
		
	}
	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelDetails(array $filter = null)
	{
		if (!$filter["HotelId"])
			throw new \Exception("Hotel Id must be specified!");

		return $this->getHotelDetails($filter["HotelId"], $filter);
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');

		// setup params
		$params = [
			"web" => false,
			"mail" => false
		];

		// request common regions
		$hotelsResp = $this->request("Common", "GetHotelListDS", $params);

		// get regions in xml format
		$hotelsXML = $hotelsResp->GetHotelListDSResult->any;

		// decode regions
		$hotelsData = json_decode(json_encode(simplexml_load_string($hotelsXML)));

		// exit if no regions after request
		if (!$hotelsData->NewDataSet->Table)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s', [0]);
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
			return false;
		}

		// put regions into an array
		$hotelList = is_array($hotelsData->NewDataSet->Table) ? $hotelsData->NewDataSet->Table : [$hotelsData->NewDataSet->Table];
		
		// init hotels array
		$hotels = [];

		// get all regions
		list($regions) = $this->api_getCities(['skip_report' => true]);

		\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s', [$hotelList ? q_count($hotelList) : 'no_hotels']);
		
		// go through each hotel
		$hotelsPos = 0;
		foreach ($hotelList ?: [] as $hotelData)
		{
			// new hotel object
			$hotel = new \stdClass();
			
			\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', 
				[
					$hotelData->HotelCode . ' ' . $hotelData->HotelName,
				], 50);
			
			// if has no code go to the next (skip current)
			if (!($hotel->Id = $hotelData->HotelCode)) 
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Skip hotel, no code provided: %s', [json_encode($hotelData)], 50);
				continue;
			}
			
			// also if the hotel does not have a name we will skip it
			if (!($hotel->Name = $hotelData->HotelName))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Skip hotel, no name provided: %s', [json_encode($hotelData)], 50);
				continue;
			}
			
			// get hotel stars
			$hotel->Stars = $hotelData->Category ? str_replace('*', '', $hotelData->Category) : '';
			if (!is_numeric($hotel->Stars))
				unset($hotel->Stars);
			
			// get hotel web address
			$hotel->WebAddress = $hotelData->Web ?: '';
			
			// new address object - region, city, county, country, street, etc
			$hotel->Address = new \stdClass();			

			// must have region code
			if ($hotelData->RegionCode)
			{
				if ((!($hotelRegion = $regions[$hotelData->RegionCode])) || (!($country = $hotelRegion->Country)))
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Skip hotel, city not found: %s', [json_encode($hotelData)], 50);
					continue;
				}

				// set country on hotel address
				$hotel->Address->Country = $country;

				// set city on hotel address
				$hotel->Address->County = $regions[$hotelData->RegionCode];

				$hotel->Address->County->Country = $country;

				if ($hotel->Address->County)
					$hotel->Address->City = clone $hotel->Address->County;
			}

			try
			{
				// get hotel extra detials if filter is set
				if ($filter['get_extra_details'] && ($hotelExtraDetails = $this->getHotelDetails($hotel->Id, null, true)))
				{
					$hotel->Content = $hotelExtraDetails->Content;
					$hotel->Rooms = $hotelExtraDetails->Rooms;
				}
			}
			catch (\Exception $ex)
			{
				if ($filter['get_extra_details'])
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Content cannot be pulled for hotel : %s. Exception: %s', 
						[$hotelData->HotelCode . ' ' . $hotelData->HotelName, $ex->getMessage()], 50);
				}
			}

			// contact person will container info as hotel emai, phone fax - it's not really a person
			$hotel->ContactPerson = new \stdClass();

			// set contact person email
			$hotel->ContactPerson->Email = $hotelData->Email;

			// add hotel to array
			$hotels[$hotel->Id] = $hotel;
			
			$hotelsPos++;
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');

		// return regions
		return $hotels;
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsCategories(array $filter = null)
	{
		
	}

	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsFacilities(array $filter = null)
	{
		
	}

	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsRooms(array $filter = null)
	{
		
	}
	
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getRates(array $filter = null)	
	{
		
	}
	
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelsBoards(array $filter = null)
	{
		
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getTours(array $filter = null)
	{
		
	}

	/**
	 * Array of: charter, tours, hotel
	 */
	public function api_getServiceTypes()
	{
		
	}
	/**
	 * $filter: Array of: charter, tours, hotel
	 * 
	 * Returns Array of: bus, plane, individual
	 */
	public function api_getTransportTypes(array $filter = null)
	{
		
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferAvailability(array $filter = null)
	{
		//qvardump("api_getOfferAvailability", $filter);
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers(array $filter = null)
	{
		$hotelsWithOffers = $this->getHotelsWithOffers($filter);
		return [$hotelsWithOffers];
	}
	
	protected function getHotelsSposMappings(array $hotelsWithSpos = []) 
	{
		if (empty($hotelsWithSpos)) {
			return [];
		}
		// setup params
		$params = [
			"Params" => ["HotelCodes" => array_keys($hotelsWithSpos), 'LastExportDate' => date('Y-m-d', strtotime('-200 day'))],
		];
		// request common regions
		$sejourContractExportView = $this->request("Export", "GetSejourContractExportView", $params, false, null, true);
		$exportData = $sejourContractExportView->GetSejourContractExportViewResult->Data->Export ?? null;
		if (!$exportData) {
			return [];
		}
		if (isset($exportData->Hotel)) {
			$exportData = [$exportData];
		}
		$spoCombinations = [];
		foreach ($exportData as $hotelData) {
			if ((!isset($hotelData->Hotel->General->Hotel_Code)) || (!($hotelId = $hotelData->Hotel->General->Hotel_Code)) || 
				(!isset($hotelData->Hotel->Special_Offers->Early_Bookings))) {
				continue;
			}
			$earlyBookings = $hotelData->Hotel->Special_Offers->Early_Bookings;
			if (is_object($earlyBookings) && isset($earlyBookings->SpoCode)) {
				$earlyBookings = [$earlyBookings];
			}
			foreach ($earlyBookings as $earlyBooking) {
				if ((!empty($earlyBooking->SpoNo_Apply)) && (!empty($earlyBooking->Spo_No))) {
					$spoCombinations[$hotelId][$earlyBooking->SpoNo_Apply][$earlyBooking->Spo_No] = $earlyBooking->Spo_No;
				}
			}
		}
		return $spoCombinations;
	}
	
	public function getHotelsWithOffers(array $filter = null)
	{
		// check in is mandatory
		if (!$filter["checkIn"])
			throw new \Exception("CheckIn date is mandatory!");

		// number of days / nights are mandatory
		if (!$filter["days"] || !is_numeric($filter["days"]))
			throw new \Exception("Duration is mandatory!");

		// rooms are mandatory
		if (!$filter["rooms"])
			throw new \Exception("Rooms are mandatory");

		// adults are mandatory
		if (isset($filter["rooms"]["adults"]))
			$filter["rooms"] = [$filter["rooms"]];

		// number of adults are mandatory
		foreach ($filter["rooms"] ?: [] as $room)
		{
			if (!isset($room["adults"]))
				throw new \Exception("Adults count is mandatory!");
		}

		// checkin
		$checkIn = $filter["checkIn"];
		$filter["checkIn"] = date('Y-m-d\TH:i:s', strtotime($filter["checkIn"]));

		// calculate checkout date
		$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

		// setup params
		$params = [
			"searchRequest" => [
				"CheckIn" => $filter['checkIn'],
				"Night" => $filter['days'],
				"HoneyMoon" => false,
				"RegionCode" => $filter['cityId'] ?? $filter['regionId'],
				"Currency" => $filter["RequestCurrency"],
				"OnlyAvailable" => false,
				"CalculateHandlingFee" => false,
				"CalculateTransfer" => false,
				"ShowPaymentPlanInfo" => true,
				"ShowHotelRemarksinPriceSearch" => false,
				"MakeControlWebParamForPaximum" => false,
				"RoomCriterias" => [],
			]
		];

		if ($filter["travelItemId"])
			$params["searchRequest"]["HotelCode"] = $filter["travelItemId"];

		// setup room params for search
		foreach ($filter['rooms'] ?: [] as $room)
		{
			if (!is_array($params['searchRequest']['RoomCriterias']))
				$params['searchRequest']['RoomCriterias'] = [];

			$params['searchRequest']['RoomCriterias']['RoomCriteria'] = [
				"Adult" => $room['adults'],
				"Child" => $room['children'] ?: 0
			];

			if ($room['childrenAges'] && (q_count($room['childrenAges'] == $room['children'])))
			{
				foreach ($room['childrenAges'] as $childAge)
				{
					$params['searchRequest']['RoomCriterias']['RoomCriteria']['ChildAges'][] = $childAge;
				}
			}
			else
				$params['searchRequest']['RoomCriterias']['RoomCriteria']['ChildAges'] = false;
		}
		$params['searchRequest']["RequestDatetime"] = date('Y-m-d\TH:i:s');

		// request common regions
		$offersResp = $this->request("Reservation", "PriceSearch", $params, false, $filter);

		// if we have raw request then return the result raw
		if (false && ($rawRequest = (isset($filter['rawResponse']) && $filter['rawResponse'])))
		{
			$spos = [];
			$allOffers = $offersResp->PriceSearchResult->RoomOffers->SejourRoomOffer ?? null;
			if ($allOffers && !is_array($allOffers)) 
			{
				$allOffers = [$allOffers];
			}

			$hotelsWithSpos = [];
			foreach ($allOffers ?: [] as $offerDetails)
			{
				if (($roomOfferSpos = $offerDetails->CalculatedSPOs))
				{
					$sposToProcess = is_array($roomOfferSpos->CalculatedSPO) ? $roomOfferSpos->CalculatedSPO : [$roomOfferSpos->CalculatedSPO];

					#$moreSpos = (q_count($sposToProcess) > 1);
					foreach ($sposToProcess as $roomOfferSpo)
					{
						// create special offer code
						$spoCode = $offerDetails->Hotel . '~' . $roomOfferSpo->No;

						$isEB = ($roomOfferSpo->Type == "EB");
						if ($isEB)
						{
							//echo "IS EARLY BOOKING<br/>";
							$offer->IsEarlyBooking = true;
						}

						$roomInfo .= ((strlen($roomInfo) > 0) ? " + " : "") . $roomOfferSpo->Description;

						$spoRequestParams = [
							'hotelCode' => $offerDetails->Hotel, 
							'spoNo' => $roomOfferSpo->No
						];
						// index special offer by code
						if ((!isset($spos[$spoCode])))
						{
							$spo = $spos[$spoCode] = $this->getDetailSpecialOffer($spoRequestParams);
							if ($spo) {
								$hotelsWithSpos[$offerDetails->Hotel] = $offerDetails->Hotel;
							}
						}
						else
							$spo = $spos[$spoCode];
						$roomOfferSpo->_spo_request_params = $spoRequestParams;
						$roomOfferSpo->_spo_response = $spo;
					}
				}
			}
			return $offersResp;
		}
		
		$nowTime = time();

		// init hotels (what will be returned)
		$hotels = [];
		
		#qvardump('$offersResp', $offersResp);
		#q_die();

		// we have response
		if ($offersResp->PriceSearchResult->Successful)
		{
			// get regions in xml format
			$roomOffers = $offersResp->PriceSearchResult->RoomOffers;

			// init indexed hotels
			$indexedHotels = [];
			$eoffs  = [];
			
			// init hotels (what will be returned)
			$hotels = [];
			
			// special offers
			$spos = [];
			
			$useRoomOffers = $roomOffers->SejourRoomOffer;
			if (is_object($roomOffers->SejourRoomOffer) && $roomOffers->SejourRoomOffer->OfferID)
				$useRoomOffers = [$useRoomOffers];

			// load spo's before and 
			$hotelsWithSpos = [];
			$filteredRoomOffers = [];
			foreach ($useRoomOffers ?: [] as $roomOffer)
			{
				// ignore offers with no hotel code
				if (((!($hotelCode = trim($roomOffer->Hotel))) || ($filter["travelItemId"] && ($filter["travelItemId"] !== $hotelCode))) || 
						(!trim($roomOffer->HotelName)) || (!($roomOffer->RoomType && $roomOffer->Room)))
					continue;
				$filteredRoomOffers[] = $roomOffer;
				if (($roomOfferSpos = $roomOffer->CalculatedSPOs))
				{
					$sposToProcess = is_array($roomOfferSpos->CalculatedSPO) ? $roomOfferSpos->CalculatedSPO : [$roomOfferSpos->CalculatedSPO];
					#$moreSpos = (q_count($sposToProcess) > 1);
					foreach ($sposToProcess as $roomOfferSpo)
					{
						// create special offer code
						$spoCode = $roomOffer->Hotel . '~' . $roomOfferSpo->No;
						// index special offer by code
						if ((!isset($spos[$spoCode])))
						{
							$spo = $this->getDetailSpecialOffer([
								'hotelCode' => $roomOffer->Hotel, 
								'spoNo' => $roomOfferSpo->No
							]);
							if ($spo) {
								$hotelsWithSpos[$roomOffer->Hotel] = $roomOffer->Hotel;
							}
							$spos[$spoCode] = $spo ?: false;
						}
						else
							$spo = $spos[$spoCode];
					}
				}
			}

			$sposMapping = $this->getHotelsSposMappings($hotelsWithSpos);

			foreach ($filteredRoomOffers ?: [] as $roomOffer)
			{
				// ignore offers with no hotel code
				if ((!($hotelCode = trim($roomOffer->Hotel))) || ($filter["travelItemId"] && ($filter["travelItemId"] !== $hotelCode)))
					continue;
				
				// init hotel object
				$hotel = $indexedHotels[$hotelCode] ?: ($indexedHotels[$hotelCode] = new \stdClass());
				
				// set hotel id as the code from sejour
				$hotel->Id = $hotelCode;
				
				// ignore offers with no hotel name
				if (!($hotel->Name = trim($roomOffer->HotelName)))
					continue;
				
				// ignore offers with no room type
				if (!($roomOffer->RoomType && $roomOffer->Room))
					continue;
				
				//echo "<div style='color: green;'>" . $roomOffer->HotelName . "</div>";
				
				// init offers if not already done this
				if (!$hotel->Offers)
					$hotel->Offers = [];
				
				// setup offer code
				$offerCode = $hotel->Id . '~' . $roomOffer->Room . '~' . $roomOffer->RoomType . '~' . $roomOffer->Board . '~' . $filter['checkIn'] . '~' . $checkOut;
				
				// init new offer
				$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
				$offer->Code = $offerCode;
				
				// set offer currency
				$offer->Currency = new \stdClass();
				$offer->Currency->Code = $roomOffer->Currency;

				// net price
				$offer->Net = (float)(($roomOffer->HotelNetPrice ?: 0) + $roomOffer->ExtraPrice);

				// comission
				$offer->Comission = $roomOffer->HotelPrice - $roomOffer->HotelNetPrice;

				// offer total price
				$offer->Gross = $roomOffer->TotalPrice;

				// get initial price
				$offer->InitialPrice = $offer->Gross;

				$offer->Spo_Details = "";

				$offer_spo_nos = [];

				$offer->HotelCode = $roomOffer->Hotel;

				$offer->IsSpecialOffer = false;
				// offer has special offers
				$roomInfo = "";

				if (($roomOfferSpos = $roomOffer->CalculatedSPOs))
				{
					$sposToProcess = is_array($roomOfferSpos->CalculatedSPO) ? $roomOfferSpos->CalculatedSPO : [$roomOfferSpos->CalculatedSPO];

					$sposForPrices = [];
					#$moreSpos = (q_count($sposToProcess) > 1);
					foreach ($sposToProcess as $roomOfferSpo)
					{
						// create special offer code
						$spoCode = $roomOffer->Hotel . '~' . $roomOfferSpo->No;
						
						$spoPaymentPlan = $roomOfferSpo->PaymentPlans->SPOPaymentPlan;
						
						if ($spoPaymentPlan) {
							if ((strtotime($spoPaymentPlan->BeginDate) <= $nowTime) && 
								(strtotime($spoPaymentPlan->EndDate) >= $nowTime)) {	
							}
						}

						$isEB = ($roomOfferSpo->Type == "EB");
						if ($isEB)
						{
							//echo "IS EARLY BOOKING<br/>";
							$offer->IsEarlyBooking = true;
						}

						$roomInfo .= ((strlen($roomInfo) > 0) ? " + " : "") . $roomOfferSpo->Description;

						// index special offer by code
						if ((!isset($spos[$spoCode])))
						{
							#qvardump('do spo top request!');
							$spo = $spos[$spoCode] = $this->getDetailSpecialOffer([
								'hotelCode' => $roomOffer->Hotel, 
								'spoNo' => $roomOfferSpo->No
							]);
						}
						else
							$spo = $spos[$spoCode];

						//qvardump('$spoCode, $spo', $spoCode, $spo);
						if (in_array($roomOfferSpo->Type, ['EBR', 'EB']))
							$offer->IsSpecialOffer = true;

						$offer_spo_nos[$roomOfferSpo->No] = $roomOfferSpo->No;

						$sposForPrices[$roomOfferSpo->No] = [$spo, $roomOfferSpo];

						$offer->Spo_Details .= ((strlen($offer->Spo_Details) > 0) ? " / " : "") . $roomOfferSpo->Type . " " . $roomOfferSpo->Description;
					}
				}

				// just add the value for the eb percentage and leave the functionality as it is
				// so the max discount will be taken in consideration
				// working only for eb type
				// case 'EBR':
				// case 'EB':
				foreach ($sposForPrices as $spoNumber => $sopDetails)
				{
					if (isset($sposMapping[$roomOffer->Hotel][$spoNumber])) {
						list ($spo, $roomOfferSpo) = $sopDetails;
						if (($roomOfferSpo->Type === 'EBR') || ($roomOfferSpo->Type === 'EB')) {
							foreach ($sposMapping[$roomOffer->Hotel][$spoNumber] as $spoToApply) {
								if (isset($sposForPrices[$spoToApply])) {
									list($toAddSpo, $toAddOfferSpo) = $sposForPrices[$spoToApply];
									if (($toAddOfferSpo->Type === 'EBR') || ($toAddOfferSpo->Type === 'EB')) {
										$toAddSpo->EBpercentage += $spo->EBpercentage;
									}
								}
							}
						}
					}
				}

				foreach ($sposForPrices as $spoDetails)
				{
					list($spo, $roomOfferSpo) = $spoDetails;
					// calcultate initial price
					$iprice = $this->calculateInitialPrice($offer->InitialPrice, $roomOfferSpo->Type, $spo, $filter['days']);
					if ((!$offer->InitialPrice) || ($offer->InitialPrice < $iprice))
						$offer->InitialPrice = $iprice;
				}

				if ($offer_spo_nos)
					$offer->Spos = json_encode($offer_spo_nos);

				// allotment type needed for booking process
				$offer->AllotmentType = $roomOffer->AllotmentType;

				// number of days needed for booking process
				$offer->Days = $roomOffer->Night;

				// room
				$roomType = new \stdClass();
				$roomType->Id = $roomOffer->RoomType;
				$roomType->Title = $roomOffer->RoomTypeName;

				$roomMerch = new \stdClass();
				//$roomMerch->Id = $roomOffer->RoomType;
				$roomMerch->Title = $roomOffer->RoomTypeName;
				$roomMerch->Type = $roomType;
				$roomMerch->Code = $roomOffer->Room;
				$roomMerch->Name = $roomOffer->RoomName;

				$roomItm = new \stdClass();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = $roomOffer->Room;
				
				#if ($offer->IsEarlyBooking)
				#	$roomItm->InfoTitle = 'Early booking';
				
				//required for indexing
				$roomItm->Code = $roomOffer->RoomType;
				$roomItm->CheckinAfter = $filter['checkIn'];
				$roomItm->CheckinBefore = $checkOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Quantity = 1;

				// set net price
				$roomItm->Net = $roomOffer->HotelNetPrice;
				
				// Q: there is also a HotelPrice
				$roomItm->InitialPrice = $offer->InitialPrice;
				
				// set the info
				if ((strlen($roomInfo) > 0))
					$roomItm->InfoTitle = $roomInfo;

				// for identify purpose
				$offer->Availability = $roomItm->Availability = 
					($roomOffer->AvailabilityStatusDesc && ($roomOffer->AvailabilityStatusDesc == 'StopSale')) ? 'no' : 
					(($roomOffer->AvailabilityStatus == 'Ok') ? 'yes' : (($roomOffer->AvailabilityStatus == 'Request') ? 'ask' : 'no'));

				if (!$offer->Rooms)
					$offer->Rooms = [];
				$offer->Rooms[] = $roomItm;

				// board
				$boardType = new \stdClass();
				$boardType->Id = $roomOffer->Board;
				$boardType->Title = $roomOffer->BoardName;

				$boardMerch = new \stdClass();
				//$boardMerch->Id = $roomOffer->Board;
				$boardMerch->Title = $boardType->Title;
				$boardMerch->Type = $boardType;

				$boardItm = new \stdClass();
				$boardItm->Merch = $boardMerch;
				$boardItm->Currency = $offer->Currency;
				$boardItm->Quantity = 1;
				$boardItm->UnitPrice = 0;
				$boardItm->Gross = 0;
				$boardItm->Net = 0;
				$boardItm->InitialPrice = 0;
				
				// for identify purpose
				$boardItm->Id = $boardMerch->Id;

				// departure transport item
				$departureTransportMerch = new \stdClass();
				$departureTransportMerch->Title = "CheckIn: ".($checkIn ? date("d.m.Y", strtotime($checkIn)) : "");

				$departureTransportItm = new \stdClass();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Quantity = 1;
				$departureTransportItm->Currency = $offer->Currency;
				$departureTransportItm->UnitPrice = 0;
				$departureTransportItm->Gross = 0;
				$departureTransportItm->Net = 0;
				$departureTransportItm->InitialPrice = 0;
				$departureTransportItm->DepartureDate = $checkIn;
				$departureTransportItm->ArrivalDate = $checkIn;

				// for identify purpose
				$departureTransportItm->Id = $departureTransportMerch->Id;

				// return transport item
				$returnTransportMerch = new \stdClass();
				$returnTransportMerch->Title = "CheckOut: ".($checkOut ? date("d.m.Y", strtotime($checkOut)) : "");

				$returnTransportItm = new \stdClass();
				$returnTransportItm->Merch = $returnTransportMerch;
				$returnTransportItm->Quantity = 1;
				$returnTransportItm->Currency = $offer->Currency;
				$returnTransportItm->UnitPrice = 0;
				$returnTransportItm->Gross = 0;
				$returnTransportItm->Net = 0;
				$returnTransportItm->InitialPrice = 0;
				$returnTransportItm->DepartureDate = $checkOut;
				$returnTransportItm->ArrivalDate = $checkOut;
				// for identify purpose
				$returnTransportItm->Id = $returnTransportMerch->Id;
				$departureTransportItm->Return = $returnTransportItm;
				
				
				// add items to offer
				$offer->Item = $roomItm;
				$offer->MealItem = $boardItm;
				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;
				
				// save offer on hotel
				$hotel->Offers[] = $offer;

				// add hotel to returning hotels
				$hotels[$hotel->Id] = $hotel;
			}			
		}

		return $hotels;
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferDetails(array $filter = null)
	{
		return null;
	}

	/**
	 * 
	 */
	public function api_getOfferCancelFees(array $filter = null)
	{
		return null;

		/*
		if ((!$filter['Hotel']) || (!($hotelCode = $filter['Hotel']['InTourOperatorId'])) || (!$filter['CheckIn']) || (!$filter['Duration']))
			return null;

		$cancelRules = $this->request("Hotel", "GetHotelCancellationRules", [
			"hotelCode" => $hotelCode,
			"checkIn" => $filter['CheckIn'],
			"night" => $filter['Duration'],
			"allotType" => "N"
		], false);
		
		$cancelRulesData = json_decode(json_encode(simplexml_load_string($cancelRules->GetHotelCancellationRulesResult->any)));
		*/

		#qvardump('$cancelRules', $cancelRules, $cancelRulesData, $hotelCode, $filter['CheckIn'], $filter['Duration']);
		#throw new \Exception('bb');

		$offPrice = $filter["SuppliedPrice"];
		$checkIn = $filter['CheckIn'];

		$cancelFees = [];

		$totalPrice = 0;
		$lastDate = null;
		foreach ($cancelFees ?: [] as $fee)
		{
			$lastDate = $fee->EndDate;
			$totalPrice += $fee->Amount;
		}

		if ($totalPrice < $offPrice)
		{
			#if ($lastDate <)
		}

		return $cancelFees;
	}

	/**
	 * 
	 */
	public function api_getOfferPaymentsPlan(array $filter = null)
	{
		return null;

		/*
		if ((!($origOff = $filter["Offer"])) || (!($hotelCode = $origOff['HotelCode'])) || (!($sposStr = $origOff['Spos'])) || (!($spos = json_decode($sposStr, true))))
		{
			return null;
		}

		foreach ($spos ?: [] as $spoNo)
		{
			// request common regions
			$spoResp = $this->request("Hotel", "GetSPOPaymentPlanDetailList", ['pRequest' => [
				"HotelCode" => $hotelCode,
				"SPONo" => $spoNo
			]], false, null, true);
			$spoRespData = json_decode(json_encode(simplexml_load_string($spoResp->GetSPOPaymentPlanDetailListResult)));
			#qvardump('$spoResp--', $hotelCode, $spoNo, $spoResp, $spoRespData, '--');
		}

		#qvardump('api_getOfferPaymentsPlan - $filter', $hotelCode, $spos);
		throw new \Exception('blocked in test');
		*/
	}

	/**
	 * 
	 */
	public function api_getOfferCancelFeesPaymentsAvailabilityAndPrice(array $filter = null)
	{

	}

	/**
	 * 
	 */
	public function api_getOfferExtraServices(array $filter = null)
	{
		
	}

	/**
	 * 
	 */
	public function api_getAvailabilityDates(array $filter = null)
	{
		
	}

	/**
	 * @param array $data
	 */
	public function api_doBooking(array $data = null)
	{
		// get offer
		$offer = q_reset($data['Items']);
		if (!$offer || !$offer['Offer_AllotmentType'] || !$offer['Board_Type_InTourOperatorId'] || !$offer['Room_Def_Code'] || !$offer['Room_Type_InTourOperatorId'] 
				|| !$offer['Offer_Days'] ||!$offer['Room_CheckinBefore'] || !$offer['Room_CheckinAfter'] || !$offer['Hotel'] || !$offer['Hotel']['InTourOperatorId'])
			throw new \Exception('Missing offer data!');
		
		// get passengers
		$passengers = $data['Passengers'];
		if (!$passengers || (q_count($passengers) == 0))
			throw new \Exception('Missing Passengers');

		// init passengers types
		$adults = 0;
		$children = 0;
		$infants = 0;
		$childAges = [];
		$passengersData = [];
		$customerOpr = [];
		$i = 0;
		foreach ($passengers as $passenger)
		{
			$i++;
			
			$customerOnRoom = [];
			// init passenger data
			$passengerData = [];
			
			// check gender to establish the title
			if ($passenger['Gender'] == 'male')
				$passengerData['Title'] = 'Mr';
			else
				$passengerData['Title'] = 'Mrs';
			
			// set passenger name
			$passengerData['Name'] = $passenger['Firstname'] . ' ' . $passenger['Lastname'];
			
			// set passenger birthdate
			$passengerData['BirthDate'] = $passenger['BirthDate'] . 'T00:00:00';
			
			$birthDate = explode("-", $passenger['BirthDate']);
			
			//get age from date or birthdate
			$age = (date("md", date("U", mktime(0, 0, 0, $birthDate[1], $birthDate[2], $birthDate[0]))) > date("md")
			  ? ((date("Y") - $birthDate[0]) - 1)
			  : (date("Y") - $birthDate[0]));
			
			$passengerData['Age'] = $age;
			
			// default values
			$passengerData['Nationalty'] = 'RO';
			$passengerData['ApplyVisa'] = false;
			$passengerData['checkApplyVisaFromNationality'] = false;
			$passengerData['ID'] = $i;
			
			if ($passenger['Type'] == 'adult')
			{
				$adults++;
			}
			else if ($passenger['Type'] == 'child')
			{
				if ($age <= 2)
				{
					$passengerData['Title'] = 'Inf';
					$infants++;
				}
				else
				{
					$passengerData['Title'] = 'Chd';
					$children++;
				}
			}
			$passengersData[] = $passengerData;
			$customerOnRoom['CustNum'] = $passengerData['ID'];
			$customerOnRoom['ResOrderNum'] = 1;
			$customerOnRoom['CinDate'] = $offer['Room_CheckinAfter'];	
			$customerOpr[] = $customerOnRoom;
		}

		$params = [
			"ri" => [
				"OnlyCalculate" => false,
				"source" => 0,
				"OnlyHotel" => true,
				"OwnProvider" => true,
				"DontWaitForCalculation" => false,
				"customer" => $passengersData,
				"resHotel" => [
					"ResHotel" => [
						"OrdNum" => 1,
						"Cindate" => $offer['Room_CheckinAfter'],
						"CoutDate" => $offer['Room_CheckinBefore'] . "T00:00:00",
						"Day" => $offer['Offer_Days'],
						"HotelCode" => $offer['Hotel']['InTourOperatorId'],
						"HotelNote" => ($offer['Offer_Spo_Details'] ?: ""),
						"RoomTypeCode" => $offer['Room_Type_InTourOperatorId'],
						"RoomCode" => $offer['Room_Def_Code'],
						"BoardCode" => $offer['Board_Type_InTourOperatorId'],
						"Adult" => $adults,
						"Child" => $children,
						"Infant" => $infants,
						"RoomCount" => 1,
						"ResStatus" => "*",
						"ArrTrf" => false,
						"OnlyTransfer" => false,
						"OnlyService" => false,
						"DepTrf" => false,
						"SatFTip" => 0,
						"AllotmentType" => $offer['Offer_AllotmentType'],
						"HoneyMooners" => false,
						"SellDate" => date('Y-m-d') . 'T00:00:00+02:00',
						"CustomerOpr" => $customerOpr
					]
				]
			],
			"isB2B" => false
		];

		// do booking
		$bookingResp = $this->request("Reservation", "MultiMakeReservation", $params, false, $data);
		if ((!(isset($bookingResp->MultiMakeReservationResult->ReservationOK))) || (!isset($bookingResp->MultiMakeReservationResult->VoucherNo)))
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError(["\$bookingResp" => $bookingResp, "\$params" => $params, "\$data" => $data], $ex);
			throw $ex;
		}

		$bookingData = $bookingResp->MultiMakeReservationResult;
		$order = new \stdClass();			
		$order->BookingRawParams = $params;			
		foreach ($bookingData ?: [] as $k => $v)
			$order->{$k} = $v;	
		$order->Id = $bookingData->VoucherNo;
		return [$order, $bookingResp];
	}

	/**
	 * @param array $filter
	 */
	public function api_prepareBooking(array $filter = null)
	{
		
	}

	/**
	 * @param array $filter
	 */
	public function api_getBookings(array $filter = null)
	{
		
	}
	
	public function getNationality()
	{
		// request common regions
		$nationalityResp = $this->request("Common", "GetNationalityDS", $params);
		
		// get regions in xml format
		$nationalityXML = $nationalityResp->GetNationalityDSResult->any;
		
		// decode regions
		$nationalityData = json_decode(json_encode(simplexml_load_string($nationalityXML)));
		
		// init array
		$nationalities = [];
		
		if (($_nationalities = $nationalityData->NewDataSet->Nationalities))
		{
			foreach ($_nationalities as $_nationality)
			{
				$nationality = new \stdClass();
				$nationality->Code = $_nationality->Nationality;
				$nationality->Title = $_nationality->Description;
				
				// add nationality to array
				$nationalities[$_nationality->Nationality] = $nationality;
			}
		}
		
		// return nationalities
		return $nationalities;
	}

	/**
	 * @param array $filter
	 */
	public function api_cancelBooking(array $filter = null)
	{
		
	}

	/**
	 * Get Hotel Details.
	 * 
	 * @param type $hotelCode
	 * @param type $filter
	 * @param type $hotel
	 */
	public function getHotelDetails($hotelCode, $filter = null, $useSimpleCache = false, $forceImgPull = false)
	{	
		$hotel = new \stdClass();

		// request common regions
		$hotelResp = $this->request("Hotel", "GetHotelAllInformations", ["hotelCode" => $hotelCode], $useSimpleCache);
		$hotelData = json_decode(json_encode(simplexml_load_string($hotelResp->GetHotelAllInformationsResult->any)));

		if (!$hotelData)
		{
			qvardump("getHotelDetails - no hotel data", $hotelResp, $hotelCode);
			return null;
		}

		$hotel->Name = $hotelData->NewDataSet->HotelDescription ? $hotelData->NewDataSet->HotelDescription->Adi : null;
		$hotel->Stars = $hotelData->NewDataSet->HotelDescription ? trim($hotelData->NewDataSet->HotelDescription->Kategori, "*") : null;
		if (!is_numeric($hotel->Stars))
			unset($hotel->Stars);

		$hotel->Content = new \stdClass();

		// exit if no regions after request
		if (($hotelPresentation = $hotelData->NewDataSet->HotelPresentaion))
			$hotel->Content->Content = $hotelPresentation->PresText;

		// we have images
		if (($hotelImages = $hotelData->NewDataSet->HotelPicture))
		{
			// new image gallery
			$hotel->Content->ImageGallery = new \stdClass();

			$travelResDir = $this->getTravelImagesDir();
			$travelResUrl = $this->getTravelImagesUrl();
			
			if (is_object($hotelImages) && $hotelImages->RecId)
				$hotelImages = [$hotelImages];

			// go through each 
			foreach ($hotelImages as $hotelImage)
			{
				$fileName = $this->TourOperatorRecord->Handle . "_" . $hotelCode . "_" . $hotelImage->RecId . ".jpeg";
				$imgFilePath = rtrim($travelResDir, "\\/") . "/" . $fileName;
				$imgUrl = rtrim($travelResUrl, "\\/") . "/" . $fileName;

				$saveImg = false;
				if (!file_exists($imgFilePath) || $forceImgPull)
				{
					//echo "<div style='color: red;'>NO IMG [{$imgUrl}]</div>";
					$base64Data = $this->getImageById($hotelImage->RecId, $useSimpleCache);
					if (strlen($base64Data) > 0)
					{
						$this->base64ToJpeg("data:image/png;base64, " . $base64Data, $imgFilePath);
						if (file_exists($imgFilePath) && (filesize($imgFilePath) > 100))
						{
							$saveImg = true;
						}
					}
				}
				else
					$saveImg = true;

				if ($imgUrl)
				{
					$image = new \stdClass();
					$image->RemoteUrl = $imgUrl;
					$hotel->Content->ImageGallery->Items[] = $image;
				}
			}
		}

		$roomsList = $hotelData->NewDataSet->Table;
		if (is_object($roomsList) && $roomsList->RoomType)
			$roomsList = [$roomsList];

		$hotel->Rooms = [];
		foreach ($roomsList ?: [] as $room)
		{
			$roomObj = new \stdClass();
			$roomObj->Name = $room->RoomType;
			$roomObj->Description = $room->Description;
			$hotel->Rooms[] = $roomObj;
		}

		return $hotel;
	}
	
	public function base64ToJpeg($base64_string, $output_file) 
	{
		// open the output file for writing
		$ifp = fopen( $output_file, 'wb'); 
		// split the string on commas
		// $data[ 0 ] == "data:image/png;base64"
		// $data[ 1 ] == <actual base64 string>
		$data = explode( ',', $base64_string);

		// we could add validation here with ensuring q_count( $data ) > 1
		fwrite($ifp, base64_decode($data[1]));

		// clean up the file resource
		fclose($ifp); 

		return $output_file; 
	}
	
	/**
	 * 
	 * @param array $filter
	 * @return int
	 * @throws Exception
	 */
	public function getDetailSpecialOffer(array $filter = null)
	{		
		// setup params
		$params = [
			"hotelCode" => $filter['hotelCode'],
			"SPONumber" => $filter['spoNo'],
		];
		
		// request common regions
		$SPOResp = $this->request("Hotel", "GetDetailSpo", $params);
		
		// get regions in xml format
		$SPOXML = $SPOResp->GetDetailSpoResult->any;
		
		// decode regions
		$SPOData = json_decode(json_encode(simplexml_load_string($SPOXML)));
		
		// return empty array if not data found
		if (!($specialOfferDetails = $SPOData->NewDataSet->Table1))
			return [];
		
		// returnj special offer details
		return $specialOfferDetails;
	}
	
	/**
	 * Calculate Initial Price based on discounted price
	 * 
	 * @param type $price
	 * @param type $spoType
	 * @param type $spo
	 * @param type $nights
	 * @return type
	 */
	public function calculateInitialPrice($price, $spoType, $spo, $nights)
	{		
		// check spo type
		switch ($spoType)
		{
			// early booking
			case 'EBR':
			case 'EB':
			{
				if ($spo->EBpercentage)
				{
					// calculate original price
					$originalPrice = round($price + (($price * $spo->EBpercentage) / (100 - $spo->EBpercentage)));
				}
				
				break;
			}
			// free nights
			case 'GP':
			{				
				// go through each discount until you find the right nights
				foreach ($spo as $spoFreeNights)
				{
					if ($spoFreeNights->XStay == $nights)
					{
						// calculate initial price
						$originalPrice = round($price + (($price / $spoFreeNights->YPay) * ($spoFreeNights->XStay - $spoFreeNights->YPay)));
					}
				}
				break;
			}
			/*
			// long stay
			case 'LONG':
			{
				if ($spo->Percentage)
				{
					// calculate original price
					$originalPrice = round($price + (($price * (100 - $spo->Percentage)) / $spo->Percentage));
					
					qvar_dump($originalPrice);
				}
				
				break;
			}
			*/
			/*
			case 'NOR':
			{
				break;
			}
			*/
			default:
			{
				// qvar_dump($spoType, $spo);
				// q_die();
				break;
			}
		}
		
		// return original price
		return $originalPrice ?: $price;
	}
	
	/**
	 * Get image by id api.
	 * 
	 * @param type $imageId
	 * @return type
	 */
	public function getImageById($imageId, $useSimpleCache = false)
	{
		// return null if no image id
		if (!$imageId)
			return null;
		
		// setup params
		$params = [
			"pictureId" => $imageId,
		];
		
		// request common regions
		$imageResp = $this->request("Hotel", "GetHotelImageByID", $params, $useSimpleCache);
		
		// get regions in xml format
		$imageXML = trim($imageResp->GetHotelImageByIDResult->any);
		
		// match picture
		$m = null;
		preg_match_all("'<Picture>(.*?)</Picture>'si", $imageXML, $m);

		// we have foudn the image
		if ($m && $m[1])
		{	
			foreach ($m[1] as $imageData)
			{
				// return base64 data
				return $imageData;
			}
		}
		
		$pfpos = (($ptmp = strpos($imageXML, "<Picture>")) !== false) ? $ptmp + strlen("<Picture>") : null;
		$plpos = (($ptmp = strrpos($imageXML, "</Picture>")) !== false) ? $ptmp : null;
		
		return (($pfpos !== null) && ($plpos !== null)) ? substr($imageXML, $pfpos, - (strlen($imageXML) - $plpos)) : null;
	}

	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "sejour";
	}

	public function getDatabaseName()
	{
		if (($this->TourOperatorRecord->Handle == 'magellan') && (defined("MAGELLAN_DB") && MAGELLAN_DB))
			return MAGELLAN_DB;
		$useDb = isset(static::$Databases[$this->TourOperatorRecord->Handle]) ? static::$Databases[$this->TourOperatorRecord->Handle] : null;
		if (!$useDb)
			throw new \Exception('empty database name!');
		return $useDb;
	}

	public function login()
	{
		// prepare params for login
		$loginParams = [
			"databaseName" => $this->getDatabaseName(),
			"userName" => $this->ApiUsername,  
			"password" => $this->ApiPassword
		];

		// get login response
		list($loginResp) = $this->__request("Authentication", "Login", $loginParams);

		// lagin successfuly, get auth key (token)
		if ($loginResp->LoginResult->Authenticated)
		{
			$this->authKey = $loginResp->LoginResult->AuthKey;
			return true;
		}
		// could not login
		else
			return false;
	}

	public function getSimpleCacheFile($params = [], $url = null, $format = "json")
	{
		$tmp_dir = $this->getResourcesDir() . "/cache/";
		if (!is_dir($tmp_dir))
			qmkdir($tmp_dir);

		$urlHash = md5(implode("|", $params));
		$cache_file = $tmp_dir . "cache_" . $urlHash . "." . $format;

		return $cache_file;
	}

	/**
	 * First will try to login and then prepare params for the SOAP request.
	 * 
	 * @param type $module
	 * @param type $method
	 * @param type $params
	 * 
	 * @return type
	 * 
	 * @throws \Exception
	 */
	public function request($module, $method, $params = [], $useSimpleCache = false, $filter = null, $doLogging = false)
	{
		// login if no auth key found
		if (!$this->authKey)
		{
			$loggedIn = $this->login();
			if (!$loggedIn)
				throw new \Exception("can't login!");				
		}
		// init params array
		if (!$params)
			$params = [];
		// add token to params at first position
		$params = ["token" => $this->authKey] + $params;
		$loadedFromCache = false;
		// if we are using cache check if the cache file exists
		if ($useSimpleCache)
		{
			list($respJson, $cacheFile, $loadedFromCache) = $this->getDataFromSimpleCache($params);
			if ($respJson)
				$ret = json_decode($respJson);
		}
		if (!($loadedFromCache))
		{
			try
			{
				list($ret) = $this->__request($module, $method, $params, $filter, $doLogging);
			}
			catch (\Exception $ex)
			{
				throw $ex;
			}
			if ($useSimpleCache && $cacheFile)
				file_put_contents($cacheFile, json_encode($ret));
		}

		return $ret;
	}

	public function initSoap($url = null, $options = [], $skipCache = false)
	{
		if ($url === null)
			$url = ($this->ApiUrl__ ?: $this->ApiUrl);
		$soapOptions = array_merge($options ?: [], ['trace' => 1]);
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			$soapOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$soapOptions["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$soapOptions["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$soapOptions["proxy_password"] = $proxyPassword;
		if (!$this->soapClients[$url][$skipCache ? 1 : 0])
			$this->soapClients[$url][$skipCache ? 1 : 0] = new \Omi\Util\SoapClient_Wrap($url, $soapOptions);
		return $this->soapClients[$url][$skipCache ? 1 : 0];
	}
	
	/**
	 * Executes the actual SOAP request.
	 * 
	 * @param type $module
	 * @param type $method
	 * @param type $params
	 * 
	 * @return type
	 */
	public function __request($module, $method, $params = [], $filter = null, $doLogging = false)
	{
		// no module found on soaps then setup soap client
		$url = ($this->ApiUrl__ ?: $this->ApiUrl) . "{$module}.asmx?WSDL";
		
		//params
		$params = array_merge($this->getSoapOptions(), $params ?: []);
		
		// init soap
		$soap = $this->initSoap($url, $params);
		if (!$soap->_request_headers)
			$soap->_request_headers = ["Content-Type: text/xml; charset=utf-8"];
		
		$isReservation = (($module === "Reservation") && ($method === "MultiMakeReservation"));
		
		try
		{
			$ret = $soap->{$method}((object)$params);
		}
		catch (\Exception $ex)
		{
			$this->logError([
				"\$url" => $url,
				"\$module" => $module, 
				"\$method" => $method, 
				"\$filter" => $filter, 
				"\$params" => $params, 
				"reqXML" => $soap->__getLastRequest(),
				"respXML" => $soap->__getLastResponse(),
				"reqHeaders" => $soap->__getLastRequestHeaders(),
				"respHeaders" => $soap->__getLastResponseHeaders()], $ex);
			if ($isReservation)
			{
				throw new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			}
			else
				throw $ex;
		}

		// log data
		if ($doLogging || ($isReservation || (($method === "PriceSearch") && ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])))))
		{
			
			$this->logData($module . "_" . $method, [
				"\$url" => $url,
				"\$module" => $module, 
				"\$method" => $method, 
				"\$filter" => $filter, 
				"\$params" => $params, 
				"reqXML" => $soap->__getLastRequest(),
				"respXML" => $soap->__getLastResponse(),
				"reqHeaders" => $soap->__getLastRequestHeaders(),
				"respHeaders" => $soap->__getLastResponseHeaders(),
			]);
		}
		
		return [$ret, $soap];
	}

	/**
	 * Get SOAP Options: keep alive, and trace if in debug mode
	 * 
	 * @return array | null
	 */
	public function getSoapOptions()
	{
		// init options array
		$options = [];
		
		// on debug mode activate traces
		if ($this->debug)
			$options['trace'] = 1;
		
		// set keep alive
		$options['keep_alive'] = 1;
		
		// return options
		return $options ?: null;
	}
	
	public function getRequestMode()
	{
		return static::RequestModeSoap;
	}
}