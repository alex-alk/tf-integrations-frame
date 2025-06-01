<?php

namespace Integrations\H2B;

use IntegrationTraits\TourOperatorRecordTrait;
use Omi\TF\TOInterface_Util;

class H2B extends \Omi\TF\TOInterface
{
	// ------added---------
	use TourOperatorRecordTrait;
	// -------------------
	use TOInterface_Util;

	public $debug = true;

	public $cacheTimeLimit = 60 * 60 * 24;

	public $apiTypesByMethod = [
		'geography-countries' => 'geography',
		'geography-cities' => 'geography',
		'properties-properties' => 'properties',
		'search-search' => 'search',
		'reservations-make' => 'reservations',
		'h2b-meta' => 'h2b',
	];

	protected static $DefaultCurrencyCode = 'EUR';

	public static $BaseUrl;
	
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

    public function getCountriesMapping()
	{
		return [];
	}

	/* ----------------------------end plane tickets operators---------------------*/
	
	public function getSoapClientByMethodAndFilter($method, $filter = null)
	{
		return false;
	}

	public function api_testConnection(array $filter = null)
	{
		try
		{
			list($countriesResp) = $this->request('geography-countries');
		}
		catch (\Exception $ex)
		{
			echo "<div style='color: red;'>" . $ex->getMessage() . "</div>";
		}
		return $countriesResp;
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
		list($countriesResp) = $this->request('geography-countries');
		$countries = ($countriesResp && $countriesResp['response']) ? $countriesResp['response'] : null;
		\Omi\TF\TOInterface::markReportData($filter, 'Count countries: %s', [$countries ? count($countries) : 'no_countries']);
		$countriesMapping = $this->getCountriesMapping();
		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		$filterCode = ($filter && $filter['Code']) ? $filter['Code'] : null;
		$ret = [];
		foreach ($countries ?: [] as $c)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process country: %s', [$c['Id'] . ' ' . $c['Name']], 50);
			if (empty($c) || empty($c['Id']) || (!($useCode = trim($countriesMapping[$c['Id']] ?: $c['Code']))))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Skip country %s - incorrect data or country not mapped', [json_encode($c)], 50);
				continue;
			}
			if (($filterId && ($c['Id'] != $filterId)) || ($filterCode && ($useCode != $filterCode)))
				continue;
			$cObj = new \stdClass();
			$cObj->Id = $c['Id'];
			$cObj->Code = $useCode;
			$cObj->Name = trim($c['Name']);
			$ret[$cObj->Code] = $cObj;
		}
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
		return [($filterId || $filterCode) ? reset($ret) : $ret];
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
		\Omi\TF\TOInterface::markReportData($filter, 'tour operator does not have static regions support');
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
		return null;
		
		// when we are going to re-activate this, if ever - just add the reports then
		

		$filterForCountries = ($hasCountryFilter = ($filter['Country']['Id'] || $filter['Country']['Code'])) ? [
			"Id" => $filter['Country']['Id'],
			"Code" => $filter['Country']['Code'],
		] : null;
		list($countries) = $this->api_getCountries($filterForCountries);
		if ($hasCountryFilter && $countries && isset($countries->Id))
			$countries = [$countries];
		$countriesById = [];
		foreach ($countries ?: [] as $country)
			$countriesById[$country->Id] = $country;

		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;

		#if (file_exists('h2b_names.txt'))
			#unlink('h2b_names.txt');

		foreach ($countries ?: [] as $country)
		{
			$ret = [];
			list($countiesResp) = $this->request('geography-cities', [
				"CountryId" => $country->Id
			]);
			$counties = ($countiesResp && $countiesResp['response']) ? $countiesResp['response'] : null;
			foreach ($counties ?: [] as $c)
			{
				if (empty($c) || empty($c['Id']) || empty($c['type']) || ($filterId && ($c['Id'] != $filterId)))
					continue;

				if  ($c['type'] == 'county')
				{
					$cObj = new \stdClass();
					$cObj->Id = $c['Id'];
					$cObj->Name = trim($c['Name']);

					#file_put_contents('h2b_names.txt', iconv("ASCII", "UTF-8", $c['Name']) . "\n", FILE_APPEND);

					$cObj->Country = $country;
					$ret[$cObj->Id] = $cObj;
				}
				else if (($c['type'] == 'city') && ($c['County'] && is_array($c['County'])))
				{
					$cObj = new \stdClass();
					$cObj->Id = $c['County']['Id'];
					$cObj->Name = trim($c['County']['Name']);

					#file_put_contents('h2b_names.txt', iconv("ASCII", "UTF-8", $c['County']['Name']) . "\n", FILE_APPEND);

					$cObj->Country = $country;
					$ret[$cObj->Id] = $cObj;
				}
			}
		}
		return [($filterId) ? reset($ret) : $ret];
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
		$filterForCountries = ($hasCountryFilter = ($filter['Country']['Id'] || $filter['Country']['Code'])) ? [
			"Id" => $filter['Country']['Id'],
			"Code" => $filter['Country']['Code'],
			'skip_report' => true
		] : ['skip_report' => true];
		list($countries) = $this->api_getCountries($filterForCountries);
		if ($hasCountryFilter && $countries && isset($countries->Id))
			$countries = [$countries];
		$countriesById = [];
		foreach ($countries ?: [] as $country)
			$countriesById[$country->Id] = $country;
		$filterId = ($filter && $filter['Id']) ? $filter['Id'] : null;
		foreach ($countries ?: [] as $country)
		{
			$ret = [];
			list($citiesResp) = $this->request('geography-cities', [
				"CountryId" => $country->Id
			]);
			$cities = ($citiesResp && $citiesResp['response']) ? $citiesResp['response'] : null;
			\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s, for country: ', [$cities ? count($cities) : 'no_cities', 
				$country->Id . ' ' . $country->Name]);
			foreach ($cities ?: [] as $c)
			{
				$region = null;
				\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s from country %s, and region %', 
				[
					$c['Id'] . ' ' . $c['Name'],
					$country->ID . ' ' . $country->Name,
					$region ? $region->ID . ' ' . $region->Name : "no region",
				], 50);

				if (empty($c) || empty($c['Id']) || empty($c['type']) || ($c['type'] != 'city'))
				{
					\Omi\TF\TOInterface::markReportError($filter, 'City incorrect data or city is region: %s', [json_encode($c)], 50);
					continue;
				}

				if ($filterId && ($c['Id'] != $filterId))
				{
					continue;
				}

				$cObj = new \stdClass();
				$cObj->Id = $c['Id'];
				$cObj->Name = trim($c['Name']);
				/*
				if ($c['County'] && is_array($c['County']))
				{
					$cObj->County = (object)$c['County'];
					$cObj->County->Country = $country;
				}
				*/
				$cObj->Country = $country;
				$ret[$cObj->Id] = $cObj;
			}
		}
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
		return [($filterId) ? reset($ret) : $ret];
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
		
	}

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');
		list($cities) = $this->api_getCities(['skip_report' => true]);
		$citiesById = [];
		foreach ($cities ?: [] as $c)
			$citiesById[$c->Id] = $c;

		list($hotelsResp) = $this->request('properties-properties');
		$hotels = ($hotelsResp && $hotelsResp['response']) ? $hotelsResp['response'] : null;
		
		list($metaResp) = $this->request('h2b-meta');
		$meta = ($metaResp && $metaResp['response']) ? $metaResp['response'] : null;

		$metaTranslations = $meta && is_array($meta) ? $meta['translations'] : null;
		$RO_tranlations = $metaTranslations && is_array($metaTranslations) ? $metaTranslations['RO'] : [];

		$hotelFacilityPrefix = 'Properties.Property_Facil_';
		$hotelFacilityPrefix_len = strlen($hotelFacilityPrefix);
		$hotelsFacilitiesTranslations = [];
		foreach ($RO_tranlations ?: [] as $k => $v)
		{
			// match the facility prefix
			if (substr($k, 0, $hotelFacilityPrefix_len) == $hotelFacilityPrefix)
			{
				$facilityK = substr($k, $hotelFacilityPrefix_len);
				$facilityK_arr = explode('.', $facilityK);
				$facilityType = array_shift($facilityK_arr);
				$facilityK_use = implode('.', $facilityK_arr);
				$hotelsFacilitiesTranslations[$facilityType][$facilityK_use] = $v;
			}
		}

		$propFacil = 'Property_Facil_';
		$propFacil_len = strlen($propFacil);
		
		$roomPropFacil = 'Property_Room_Facil_';
		$roomPropFacil_len = strlen($roomPropFacil);

		$hotelsTypes = [];
		$ret = [];
		$roomsTypes = [];
		foreach ($hotels ?: [] as $hotel)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', 
				[
					$hotel['Id'] . ' ' . $hotel['Name'],
				], 50);

			if (empty($hotel) || empty($hotel['Id']) || empty($hotel['Address']))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Skip hotel, incomplete data: %s', [json_encode($hotel)], 50);
				continue;
			}

			if (isset($hotel["Address"]["City"]['Name']) && ($cityObj = $citiesById[$hotel["Address"]["City"]['Id']]))
			{
				$hotelObj = new \stdClass();
				$hotelObj->Id = $hotel['Id'];
				$hotelObj->Active = true;

				$hotelObj->HasIndividualOffers = true;
				$hotelObj->HasIndividualActiveOffers = true;

				$hotelObj->Name = trim($hotel['Name']);
				if ($hotel['Stars'] && is_numeric($hotel['Stars']))
					$hotelObj->Stars = trim($hotel['Stars']);
				$hotelObj->Address = new \stdClass();
				$hotelObj->Address->City = $cityObj;
				$countyObj = null;
				if ($hotel["Address"]['County'] && $hotel["Address"]['County']['Id'])
					$countyObj = (object)$hotel["Address"]['County'];

				$countryObj = null;
				#if ($hotel["Address"]['Country'] && $hotel["Address"]['Country']['Id'])
				#	$countryObj = (object)$hotel["Address"]['Country'];

				if ($countyObj)
					$cityObj->County = $countyObj;
				
				if ($countryObj)
					$cityObj->Country = $countryObj;

				if ($hotel['Address']['PostCode'])
					$hotelObj->Address->PostCode = $hotel['Address']['PostCode'];
				if ($hotel['Address']['Longitude'])
					$hotelObj->Address->Longitude = $hotel['Address']['Longitude'];
				if ($hotel['Address']['Latitude'])
					$hotelObj->Address->Latitude = $hotel['Address']['Latitude'];

				$addr_destails = "";
				if (($street = trim($hotel['Address']['Street'])))
				{
					$hotelObj->Address->Street = $street;
					$addr_destails .= $street;
				}

				if (($streetNo = trim($hotel['Address']['StreetNumber'])))
				{
					$hotelObj->Address->StreetNumber = $streetNo;
					$addr_destails .= ((strlen($addr_destails) > 0) ? ", " : "") . $streetNo;
				}

				if ($addr_destails)
					$hotelObj->Address->Details = $addr_destails;

				list($roomsResp) = $this->request('properties-rooms', [
					"PropertyId" => $hotelObj->Id
				]);

				$rooms = ($roomsResp && $roomsResp["response"]) ? $roomsResp["response"] : null;
				$roomsFacilities = [];
				$hotelObj->Rooms = [];

				$roomsImages = [];
				foreach ($rooms ?: [] as $room)
				{
					\Omi\TF\TOInterface::markReportData($filter, 'Process room: %s', 
						[
							$room['Id'] . ' ' . $room['Name'],
						], 100);

					if ((!$room) || (!$room["Id"]))
					{
						\Omi\TF\TOInterface::markReportError($filter, 'Skip room, incomplete data: %s', [json_encode($room)], 100);
						continue;
					}
					$roomObj = new \stdClass();
					$roomObj->Id = $room["Id"];
					$roomObj->Title = $room["Name"];
					if ($room["Standard_Type"])
					{
						if (!($roomType = $roomsTypes[$room["Standard_Type"]]))
						{
							$roomType = new \stdClass();
							$roomType->Title = $room["Standard_Type"];
							$roomType->Id = md5($room["Standard_Type"]);
						}
						$roomObj->Type = $roomType;
					}
					$hotelObj->Rooms[] = $roomObj;
					
					foreach ($room['Content_Images'] ?: [] as $imgData)
					{
						if (is_array($imgData) && $imgData['Path'] && $imgData['_url_'])
						{
							$imgObj = new \stdClass();
							$imgObj->RemoteUrl = rtrim($imgData['_url_'], '\//') . '/' . $imgData['Path'];
							if ($imgData['Order'])
								$imgObj->Order = $imgData['Order'];
							$imgObj->Alt = $imgData['Alt'] ?: $room["Name"];
							$roomsImages[$imgObj->RemoteUrl] = $imgObj;
						}
					}

					$roomObj->Facilities = [];
					foreach ($room ?: [] as $r_prop => $r_propData)
					{
						if (substr($r_prop, 0, $roomPropFacil_len) == $roomPropFacil)
						{
							if ($r_propData)
							{
								foreach ($r_propData ?: [] as $facilityName => $facilityF)
								{
									if (($facilityName = trim($facilityName)) && (($facilityF === 'free') || ($facilityF === true)))
									{
										$roomsFacilities[$facilityName] = $facilityName;
										$roomFacility = new \stdClass();
										$roomFacility->Name = $facilityName;
										$roomFacility->Id = md5($facilityName);
										$roomObj->Facilities[$facilityName] = $roomFacility;
									}
								}	
							}
						}
					}
				}

				if ($hotel["Type"])
				{
					if (!($hotelsType = $hotelsTypes[$hotel["Type"]]))
					{
						$hotelsType = new \stdClass();
						$hotelsType->Title = $hotel["Type"];
						$hotelsTypes[$hotel["Type"]] = $hotelsType;
					}
					$hotelObj->Type = $hotelsType;
				}

				if ($hotel["Content_Description_HTML"])
				{
					if (!$hotelObj->Content)
						$hotelObj->Content = new \stdClass();
					$hotelObj->Content->Content = $hotel["Content_Description_HTML"];
				}

				if ($hotel['Content_Video_Embeds'])
				{
					if (!$hotelObj->Content)
						$hotelObj->Content = new \stdClass();
					$hotelObj->Content->VideoGallery = new \stdClass();
					$hotelObj->Content->VideoGallery->Items = [];
					foreach ($hotel['Content_Video_Embeds'] ?: [] as $embed)
					{
						if (is_array($embed) && $embed['Path'])
						{
							$vid = new \stdClass();
							$vidEmbedPath = $embed['Path'];
							if ((substr($vidEmbedPath, 0, 7) !== 'http://') && (substr($vidEmbedPath, 0, 8) !== 'https://') && $embed['_url_'])
								$vidEmbedPath = rtrim($embed['_url_'], '\//') . '/' . $embed['Path'];
							$vid->Embed = $vidEmbedPath;
							if ($embed['Order'])
								$vid->Order = $embed['Order'];
							$hotelObj->Content->VideoGallery->Items[] = $vid;
						}
					}
				}

				if ($hotel['Content_Image'] && $hotel['Content_Image']['Path'] && $hotel['Content_Image']['_url_'])
				{
					if (!$hotelObj->Content)
						$hotelObj->Content = new \stdClass();
					$hotelObj->Content->ImageGallery = new \stdClass();
					$hotelObj->Content->ImageGallery->Items = [];
					$imgObj = new \stdClass();
					$imgObj->RemoteUrl = rtrim($hotel['Content_Image']['_url_'], '\\/') . '/' . $hotel['Content_Image']['Path'];
					$imgObj->Order = 0;
					if ($hotel['Content_Image']['Alt'])
						$imgObj->Alt = $hotel['Content_Image']['Alt'];
					$hotelObj->Content->ImageGallery->Items[] = $imgObj;
				}

				$hotelsImages = [];
				if ($hotel['Content_Images'])
				{
					if (!$hotelObj->Content)
						$hotelObj->Content = new \stdClass();
					if (!$hotelObj->Content->ImageGallery)
						$hotelObj->Content->ImageGallery = new \stdClass();
					if (!$hotelObj->Content->ImageGallery->Items)
						$hotelObj->Content->ImageGallery->Items = [];

					foreach ($hotel['Content_Images'] ?: [] as $imgData)
					{
						if (is_array($imgData) && $imgData['Path'] && $imgData['_url_'])
						{
							$imgObj = new \stdClass();
							$imgObj->RemoteUrl = rtrim($imgData['_url_'], '\//') . '/' . $imgData['Path'];
							if ($imgData['Order'])
								$imgObj->Order = $imgData['Order'];
							if ($imgData['Alt'])
								$imgObj->Alt = $imgData['Alt'];
							$hotelsImages[$imgObj->RemoteUrl] = $imgObj->RemoteUrl;
							$hotelObj->Content->ImageGallery->Items[] = $imgObj;
						}
					}
				}

				if ($roomsImages)
				{
					foreach ($roomsImages ?: [] as $imgRemoteUrl => $image)
					{
						if (!isset($hotelsImages[$imgRemoteUrl]))
						{
							if (!$hotelObj->Content)
								$hotelObj->Content = new \stdClass();
							if (!$hotelObj->Content->ImageGallery)
								$hotelObj->Content->ImageGallery = new \stdClass();
							if (!$hotelObj->Content->ImageGallery->Items)
								$hotelObj->Content->ImageGallery->Items = [];
							$hotelObj->Content->ImageGallery->Items[] = $image;
						}
					}
				}

				$hotelObj->Facilities = [];
				foreach ($hotel ?: [] as $prop => $propData)
				{
					if (substr($prop, 0, $propFacil_len) == $propFacil)
					{
						if ($propData)
						{
							$facilitiesType = substr($prop, $propFacil_len);
							foreach ($propData ?: [] as $facilityName => $facilityF)
							{
								if (($facilityName = trim($facilityName)) && (($facilityF === 'free') || ($facilityF === true)))
								{
									$facilityAlias = $hotelsFacilitiesTranslations[$facilitiesType][$facilityName];
									$facility = new \stdClass();
									$facility->Name = $facilityAlias ?: $facilityName;
									$facility->Id = md5($facility->Name);
									$hotelObj->Facilities[$facility->Name] = $facility;
								}
							}	
						}
					}
				}

				/*
				foreach ($roomsFacilities ?: [] as $facilityName)
				{
					if (!isset($hotelObj->Facilities[$facilityName]))
					{
						$facility = new \stdClass();
						$facility->Name = $facilityName;
						$facility->Id = md5($facilityName);
						$hotelObj->Facilities[$facility->Name] = $facility;
					}
				}
				*/

				$ret[$hotelObj->Id] = $hotelObj;
			}
			else 
			{
				\Omi\TF\TOInterface::markReportError($filter, 'City not found for hotel: %s', [$hotel['Id']], 50);
			}
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
		return [$ret];
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
		list($ratesResp) = $this->request('properties-rates');
		$rates = ($ratesResp && $ratesResp['response']) ? $ratesResp['response'] : null;
		return [json_decode(json_encode($rates))];
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
	
	public function getSearchDataFromFilters($filter = null)
	{
		#list($countryId, $cityId, $hotelId, $checkIn, $checkOut, $adults, $children, $childrenAges) = $this->getSearchDataFromFilters($filter);
		if ((!$filter['checkOut']) && $filter['checkIn'] && isset($filter['days']))
		{
			$filter['checkOut'] = date("Y-m-d", strtotime(" + " . $filter['days'] . " days", strtotime($filter['checkIn'])));
			#qvardump($filter['checkOut'], $filter['days'], $filter['checkIn']);
		}

		if (!($countryId = $filter['countryId']))
			throw new \Exception('Destination country not found!');
		if (!($cityId = $filter['cityId']))
			throw new \Exception('Destination city not found!');
		$hotelId = $filter['travelItemId'] ?: null;
		if (!($checkIn = $filter['checkIn']))
			throw new \Exception('Check In not found!');
		if (!($checkOut = $filter['checkOut']))
			throw new \Exception('Check Out not found!');
		if (!($room = $filter['rooms'] ? reset($filter['rooms']) : null))
			throw new \Exception('Room data not found!');
		if (!($adults = $room['adults']))
			throw new \Exception('Adults not found!');
		$children = $room['children'];
		$childrenAges = $room['childrenAges'];

		return [$countryId, $cityId, $hotelId, $checkIn, $checkOut, $adults, $children, $childrenAges];
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers(array $filter = null)
	{		
		$dumpData = false;
		$serviceType = $filter['serviceTypes'] ? reset($filter['serviceTypes']) : null;
		if ((!$serviceType) || ($serviceType != 'hotel'))
			return null;

		list(/*$countryId*/, $cityId, $hotelId, $checkIn, $checkOut, $adults, $children, $childrenAges) = $this->getSearchDataFromFilters($filter);
		
		$offsParams = [
			"City_Id" => $cityId,
			"Check_In" => $checkIn,
			"Check_Out" => $checkOut,
			"Adults" => $adults,
		];
		
		if ($filter['travelItemId'])
			$offsParams["Property_Id"] = $filter['travelItemId'];

		if ($children && (!empty($childrenAges)))
			$offsParams["Children_Ages"] = implode(",", $childrenAges);

		//$method, $callParams = [], $filter = null, $useSimpleCache = false, $logData = false
		list($offersResp) = $this->request('search-search', $offsParams, $filter, false, ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"])));

		if (($rawRequest = (isset($filter['rawResponse']) && $filter['rawResponse'])))
		#if (false)
		{
			return [$offersResp];
		}

		$offs = ($offersResp && $offersResp['response'] && $offersResp['response']["results"]) ? $offersResp['response']["results"] : null;

		$paymentPolicies = ($offersResp && $offersResp['response'] && $offersResp['response']["payment_policies"]) ? $offersResp['response']["payment_policies"] : null;
		$cancellationPolicies = ($offersResp && $offersResp['response'] && $offersResp['response']["cancellation_policies"]) ? $offersResp['response']["cancellation_policies"] : null;


		$hotels = [];
		$eoffs = [];
		$currencies = [];
		if ($offs)
		{
			$servicesIds = [];
			foreach ($offs ?: [] as $offerData)
			{
				#list($offDate, $offPrice, $offPropertyId, $offRoomId, $offRateId, $offFacil) = $offerData;			
				list($offDate, $offNights, $offPrice, $offPropertyId, $offRoomId, $offCount, $offRateId, $offFacil, $withExtraBed, $currency, $meal, $extraServices) = $offerData;
				if ($meal && is_array($meal))
					$meal = reset($meal);
				if ($meal)
					$servicesIds[$meal] = $meal;
				foreach ($extraServices ?: [] as $ex)
				{
					list($ex_id, /*$ex_w*/) = $ex;
					$servicesIds[$ex_id] = $ex_id;
				}
			}

			$servicesById = [];
			if (count($servicesIds))
			{
				list($servicesResp) = $this->request('properties-services', ['Id_In_List' => implode(',', $servicesIds)]);
				if ($servicesResp && $servicesResp['response'])
				{
					foreach ($servicesResp['response'] ?: [] as $serv)
						$servicesById[$serv['Id']] = $serv;
				}
			}

			foreach ($offs ?: [] as $offerData)
			{
				#list($offDate, $offPrice, $offPropertyId, $offRoomId, $offRateId, $offFacil) = $offerData;
				list($offDate, $offNights, $offPrice, $offPropertyId, $offRoomId, $offCount, $offRateId, $offFacil, $withExtraBed, $currency, 
					$meal, $extraServices, $paymentPolicy, $cancellationPolicy, $comission) = $offerData;

				$toSendOffData = $offerData;

				$offerPaymentPolicies = null;
				$offerCancellationPolicies = null;

				if ($paymentPolicy && ($offerPaymentPolicies = (($paymentPolicies && is_array($paymentPolicies) && $paymentPolicies[$paymentPolicy]) ? $paymentPolicies[$paymentPolicy] : null)))
				{
					$toSendOffData[12] = $offerPaymentPolicies;
				}

				if ($cancellationPolicy && ($offerCancellationPolicies = (($cancellationPolicies && is_array($cancellationPolicies) && $cancellationPolicies[$cancellationPolicy]) ? $cancellationPolicies[$cancellationPolicy] : null)))
				{
					$toSendOffData[13] = $offerCancellationPolicies;
				}

				$initialData = json_encode($toSendOffData);

				/*
				qvardump('$offDate, $offNights, $offPrice, $offPropertyId, $offRoomId, $offCount, $offRateId, $offFacil, $withExtraBed, $currency, $meal, $extraServices', 
					$offDate, $offNights, $offPrice, $offPropertyId, $offRoomId, $offCount, $offRateId, $offFacil, $withExtraBed, $currency, $meal, $extraServices);
				*/

				if ((!$offPropertyId) || (!$offRoomId) || (!$offPrice))
				{
					if ($dumpData)
						echo '<div style="color: red;">Offer not well formatted [' . $initialData . ']</div>';
					continue;
				}

				if ($meal && is_array($meal))
					$meal = reset($meal);

				$mealServ = null;
				if ($meal && (!($mealServ = $servicesById[$meal])))
				{
					if ($dumpData)
						echo '<div style="color: red;">Meal not found [' . $initialData . ']</div>';
					continue;
				}

				$hotelObj = $hotels[$offPropertyId] ?: ($hotels[$offPropertyId] = new \stdClass());
				$hotelObj->Id = $offPropertyId;

				$mealId = $mealServ ? $mealServ['Id'] : null;
				$offInitialPrice = $offPrice;
				$offAvailability = "yes";

				$offerCodeStr = $offPropertyId . "-" . $offRoomId . "-" . $mealId . "-" . $offRateId . "-" . $checkIn . "-" . $checkOut . "-" . $adults . "-" . $children . "-" . ($childrenAges ? implode("|", $childrenAges) : "");
				//echo $offerCodeStr . "<br/>";
				$offerCode = md5($offerCodeStr);

				$rateObj = null;
				if ($offRateId)
				{
					$rateObj = new \stdClass();
					$rateObj->Id = $offRateId;
				}

				$offer = $eoffs[$hotelObj->Id][$offerCode] ?: ($eoffs[$hotelObj->Id][$offerCode] = new \stdClass());
				$offer->Code = $offerCode;

				$offer->InitialData = $initialData;

				// set checkin and nights
				$offer->CheckIn = $offDate;
				$offer->Nights = $offNights;

				// set offer currency
				$offer->Currency = $currencies[$currency] ?: ($currencies[$currency] = new \stdClass());
				$offer->Currency->Code = $currency;

				// net price
				$offer->Net = (float)$offPrice;

				// offer total price
				$offer->Gross = (float)$offPrice;

				$offer->InitialPrice = (float)($offInitialPrice ?: $offPrice);

				if ($comission)
					$offer->Comission = ($offer->Gross * (float)$comission) / 100;

				// get availability
				$offer->Availability = $offAvailability;

				// number of days needed for booking process
				$offer->Days = $filter['days'];

				$roomID = $offRoomId;

				// room
				/*
				$roomType = new \stdClass();
				$roomType->Id = $roomID;
				$roomType->Title = $room;
				*/

				$roomMerch = new \stdClass();
				$roomMerch->Id = $roomID;
				#$roomMerch->Title = $room;
				#$roomMerch->Type = $roomType;

				$roomItm = new \stdClass();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = $roomID;

				if ($extraServices)
				{
					
					$extraServicesByName = [];
					foreach ($extraServices ?: [] as $esrv)
					{
						list($ex_id, /*$ex_w*/) = $esrv;
						if (($eservData = $servicesById[$ex_id]) && $eservData['Name'])
							$extraServicesByName[$eservData['Name']] = $eservData['Name'];
					}

					if (count($extraServicesByName))
						$roomItm->InfoTitle = implode(', ', $extraServicesByName);
				}

				//required for indexing
				$roomItm->CheckinAfter = $filter['checkIn'];
				$roomItm->CheckinBefore = $checkOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Quantity = 1;
				$roomItm->Availability = $offer->Availability;

				if (!$offer->Rooms)
					$offer->Rooms = [];

				$offer->Rooms[] = $roomItm;

				$boardItm = null;
				if ($mealId)
				{
					// board
					/*
					$boardType = new \stdClass();
					$boardType->Id = $mealCode;
					$boardType->Title = $mealCaption;
					*/

					$boardMerch = new \stdClass();
					#$boardMerch->Id = $mealId;
					$boardMerch->Title = $mealServ['Name'];
					#$boardMerch->Type = $boardType;

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
				}

				// departure transport item
				$departureTransportMerch = new \stdClass();
				$departureTransportMerch->Title = "CheckIn: ".($filter['checkIn'] ? date("d.m.Y", strtotime($filter['checkIn'])) : "");

				$departureTransportItm = new \stdClass();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Quantity = 1;
				$departureTransportItm->Currency = $offer->Currency;
				$departureTransportItm->UnitPrice = 0;
				$departureTransportItm->Gross = 0;
				$departureTransportItm->Net = 0;
				$departureTransportItm->InitialPrice = 0;
				$departureTransportItm->DepartureDate = $filter['checkIn'];
				$departureTransportItm->ArrivalDate = $filter['checkIn'];

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
				$offer->MealItem = $boardItm ?: $this->getEmptyBoardItem($offer);

				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;

				if ($offerPaymentPolicies)
					$this->setupPaymentPolicies($offer, $offerPaymentPolicies);

				if ($offerCancellationPolicies)
					$this->setupCancellationPolcies($offer, $offerCancellationPolicies);

				if ($rateObj)
					$offer->Rate = $rateObj;

				#qvardump('$offer', $offer->Gross, $offer, $offerPaymentPolicies, $offerCancellationPolicies);
				
				if (!$hotelObj->Offers)
					$hotelObj->Offers = [];
				$hotelObj->Offers[$offer->Code] = $offer;
			}
		}
		
		$ret = [];
		foreach ($hotels ?: [] as $hotel)
		{
			if ($hotel->Offers)
				$ret[] = $hotel;
		}

		return $ret;
	}

	public function setupPaymentPolicies($offer, $offerPayementPolicies)
	{

		$todayTime = strtotime(date("Y-m-d"));

		$offPolicies = [];
		foreach ($offerPayementPolicies ?: [] as $paymentPolicy)
		{
			if (!(floatval($paymentPolicy['value'])))
				continue;

			$dateStart = $paymentPolicy['from'];
			if (!$dateStart)
				$dateStart = date("Y-m-d");

			$dateEnd = $paymentPolicy['to'];
			if (!$dateEnd)
				$dateEnd =  $offer->CheckIn;
			
			if ($todayTime > strtotime($dateStart))
				$dateStart = date("Y-m-d");
			if ($todayTime > strtotime($dateEnd))
				$dateEnd = date("Y-m-d");
			$offPolicies[$dateEnd][] = $paymentPolicy;
		}

		ksort($offPolicies);

		$toProcessPolicies = [];
		foreach ($offPolicies ?: [] as $endDate => $policiesByEndDate)
		{
			$fullAmount = 0;
			$minDateStart = null;
			foreach ($policiesByEndDate ?: [] as $paymentPolicy)
			{
				$amount = $paymentPolicy['value'];
				$isPercent = (strpos($amount, "%") !== false);
				$amount = floatval($amount);

				$policyAmount = $isPercent ? ($amount * $offer->Gross)/100 : $amount;
				$fullAmount += $policyAmount;
				$dateStart = $paymentPolicy['from'];
				if ($todayTime > strtotime($dateStart))
					$dateStart = date("Y-m-d");
				
				if (($minDateStart === null) || ($minDateStart > $dateStart))
					$minDateStart = $dateStart;
			}

			$toProcessPolicies[] = [
				'from' => $minDateStart,
				'to' => $endDate,
				'value' => $fullAmount
			];
		}

		$offer->Installments = [];
		foreach ($toProcessPolicies ?: [] as $paymentPolicy)
		{
			$amount = $paymentPolicy['value'];
			$isPercent = (strpos($amount, "%") !== false);
			$amount = floatval($amount);

			if (!$amount)
				continue;

			$dateStart = $paymentPolicy['from'];
			$dateEnd = $paymentPolicy['to'];
			if ($todayTime > strtotime($dateStart))
				$dateStart = date("Y-m-d");
			if ($todayTime > strtotime($dateEnd))
				$dateEnd = date("Y-m-d");

			$pObj = new \stdClass();
			$pObj->PayAfter = $dateStart;
			$pObj->PayUntil = $dateEnd;
			$pObj->Amount = $isPercent ? ($amount * $offer->Gross)/100 : $amount;
			$pObj->Currency = $offer->Currency;
			$offer->Installments[] = $pObj;
		}
		$offer->Payments = $offer->Installments;
	}

	public function setupCancellationPolcies($offer, $offerCancellationPolicies)
	{
		$todayTime = strtotime(date("Y-m-d"));

		// populate to as being next cancellation fee from / for the last cancel fee, it will be the checkin
		$lastCfeeK = null;
		foreach ($offerCancellationPolicies ?: [] as $cfeek => $cancellationPolicy)
		{
			if (($lastCfeeK !== null) && isset($offerCancellationPolicies[$lastCfeeK]['from']) && (!isset($offerCancellationPolicies[$lastCfeeK]['to'])))
			{
				$offerCancellationPolicies[$lastCfeeK]['to'] = date('Y-m-d', strtotime('-1 day', strtotime($cancellationPolicy['from'])));
			}
			$lastCfeeK = $cfeek;
		}
		
		if (($lastCfeeK !== null) && isset($offerCancellationPolicies[$lastCfeeK]['from']) && (!isset($offerCancellationPolicies[$lastCfeeK]['to'])))
		{
			$offerCancellationPolicies[$lastCfeeK]['to'] = $offer->CheckIn;
		}

		#qvardump('$offerCancellationPolicies', $offerCancellationPolicies);

		$offPolicies = [];
		foreach ($offerCancellationPolicies ?: [] as $cancellationPolicy)
		{
			if (!(floatval($cancellationPolicy['value'])))
				continue;

			$dateStart = $cancellationPolicy['from'];
			if (!$dateStart)
				$dateStart = date("Y-m-d");

			$dateEnd = $cancellationPolicy['to'];
			if (!$dateEnd)
				$dateEnd =  $offer->CheckIn;

			if ($todayTime > strtotime($dateStart))
				$dateStart = date("Y-m-d");
			if ($todayTime > strtotime($dateEnd))
				$dateEnd = date("Y-m-d");
			$offPolicies[$dateEnd][] = $cancellationPolicy;
		}

		ksort($offPolicies);

		$toProcessPolicies = [];
		foreach ($offPolicies ?: [] as $endDate => $policiesByEndDate)
		{
			$fullAmount = 0;
			$minDateStart = null;
			foreach ($policiesByEndDate ?: [] as $paymentPolicy)
			{
				$amount = $paymentPolicy['value'];
				$isPercent = (strpos($amount, "%") !== false);
				$amount = floatval($amount);

				$policyAmount = $isPercent ? ($amount * $offer->Gross)/100 : $amount;
				$fullAmount += $policyAmount;
				$dateStart = $paymentPolicy['from'];
				if ($todayTime > strtotime($dateStart))
					$dateStart = date("Y-m-d");
				
				if (($minDateStart === null) || ($minDateStart > $dateStart))
					$minDateStart = $dateStart;
			}

			$toProcessPolicies[] = [
				'from' => $minDateStart,
				'to' => $endDate,
				'value' => $fullAmount
			];
		}
		
		$offer->CancelFees = [];
		$prvCancelFee = null;
		foreach ($toProcessPolicies ?: [] as $offcp)
		{
			$amount = $offcp['value'];
			$isPercent = (strpos($amount, "%") !== false);
			$amount = floatval($amount);

			if (!$amount)
				continue;

			$dateStart = $offcp['from'];
			$dateEnd = ($offcp['to'] ?: null);
			if ($todayTime > strtotime($dateStart))
				$dateStart = date("Y-m-d");
			if ($dateEnd && ($todayTime > strtotime($dateEnd)))
				$dateEnd = date("Y-m-d");

			if ($prvCancelFee && (!$prvCancelFee->DateEnd))
				$prvCancelFee->DateEnd = date("Y-m-d", strtotime("-1 day", strtotime($dateStart)));

			$cpObj = new \stdClass();
			$cpObj->DateStart = $dateStart;
			if ($dateEnd)
				$cpObj->DateEnd = $dateEnd;
			$cpObj->Price = $isPercent ? ($amount * $offer->Gross)/100 : $amount;
			$cpObj->Currency = $offer->Currency;
			$offer->CancelFees[] = $cpObj;
			$prvCancelFee = $cpObj;
		}

		if (!$prvCancelFee->DateEnd)
			$prvCancelFee->DateEnd = $offer->CheckIn;
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
		
	}

	/**
	 * 
	 */
	public function api_getOfferPaymentsPlan(array $filter = null)
	{
		
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
		
		 $hasCompany = (isset($data['BillingTo']['Company']) && 
			$data['BillingTo']['Company']['Name'] && $data['BillingTo']['Company']['TaxIdentificationNo']);

		$bookingParams = [

			'Buyer[Gender]' => $data['BillingTo']['Gender'],
			'Buyer[Firstname]' => $data['BillingTo']['Firstname'],
			'Buyer[IdentityCardNumber]' => $data['BillingTo']['IdentityCardNumber'],
			'Buyer[Name]' => $data['BillingTo']['Lastname'],
			'Buyer[Email]' => $data['BillingTo']['Email'],
			'Buyer[Phone]' => $data['BillingTo']['Phone'],
			'Buyer[Address][Country][Code]' => isset($data['BillingTo']['Address']['Country']['Code']) ? $data['BillingTo']['Address']['Country']['Code'] : null,
			'Buyer[Address][County][Name]' => isset($data['BillingTo']['Address']['County']['Name']) ? $data['BillingTo']['Address']['County']['Name'] : null,
			'Buyer[Address][City][Name]' => isset($data['BillingTo']['Address']['City']['Name']) ? $data['BillingTo']['Address']['City']['Name'] : null,
			'Buyer[Address][PostCode]' => $data['BillingTo']['Address']['ZipCode'],
			'Buyer[Address][Street]' => $data['BillingTo']['Address']['Street'],
			'Buyer[Address][StreetNumber]' => $data['BillingTo']['Address']['StreetNumber'],
			'Buyer[Address][Building]' => null,

			'Buyer_Company[Name]' => $data['AgencyDetails']['Name'],
			'Buyer_Company[Reg_No]' => $data['AgencyDetails']['RegistrationNo'],
			'Buyer_Company[VAT_No]' => $data['AgencyDetails']['TaxIdentificationNo'],
			'Buyer_Company[Address][Country][Code]' => (isset($data['AgencyDetails']['HeadOffice']['Country']['Code'])) ? 
				$data['AgencyDetails']['HeadOffice']['Country']['Code'] : 'RO',
			'Buyer_Company[Address][County][Name]' => (isset($data['AgencyDetails']['HeadOffice']['County']['Name'])) ? 
				$data['AgencyDetails']['HeadOffice']['County']['Name'] : '-',
			'Buyer_Company[Address][City][Name]' => (isset($data['AgencyDetails']['HeadOffice']['City']['Name'])) ? 
				$data['AgencyDetails']['HeadOffice']['City']['Name'] : '-',
			'Buyer_Company[Address][PostCode]' => (isset($data['AgencyDetails']['HeadOffice']['PostCode'])) ? 
				$data['AgencyDetails']['HeadOffice']['PostCode'] : '-',
			'Buyer_Company[Address][Street]' => (isset($data['AgencyDetails']['HeadOffice']['Street'])) ? 
				$data['AgencyDetails']['HeadOffice']['Street'] : '-',
			'Buyer_Company[Address][StreetNumber]' => (isset($data['AgencyDetails']['HeadOffice']['StreetNumber'])) ? 
				$data['AgencyDetails']['HeadOffice']['StreetNumber'] : '-',
			'Buyer_Company[Address][Building]' => (isset($data['AgencyDetails']['HeadOffice']['Building'])) ? 
				$data['AgencyDetails']['HeadOffice']['Building'] : null,
		];

		$extraServices = [];
		$es_pos = 0;
		foreach ($extraServices ?: [] as $es)
		{
			$bookingParams['Extra_Services[' . $es_pos . '][Id]'] = $es['Id'];
			$bookingParams['Extra_Services[' . $es_pos . '][Quantity]'] = $es['Quantity'];
			$bookingParams['Extra_Services[' . $es_pos . '][Info]'] = $es['Info'];
			$es_pos++;
		}

		$nowDate = date_create(date("Y-m-d"));
		$offPos = 0;
		foreach ($data['Items'] ?: [] as $off)
		{
			#$bookingParams['Reservations[' . $offPos . '][Room]'] = $off['Room_Def_Id'];
			$bookingParams['Reservations[' . $offPos . '][Room]'] = $off['Offer_InitialData'];

			$adults = 0;
			$childrenAges = "";
			foreach ($off['Passengers'] ?: [] as $passenger)
			{
				if ($passenger['IsAdult'])
				{
					$adults++;
				}
				else
				{
					$age = date_diff($nowDate, date_create($passenger['BirthDate']))->y;
					$childrenAges .= ((strlen($childrenAges) > 0) ? ', ' : '') . $age;
				}
			}

			$occupancy = ['Adults' => $adults];
			#if (strlen($childrenAges))
			$occupancy['Children_Ages'] = $childrenAges;
			$bookingParams['Reservations[' . $offPos . '][Occupancy]'] = json_encode($occupancy);

			#$bookingParams['Reservations[' . $offPos . '][Room]'] = $off['Offer_InitialData'];
			$passengerPos = 0;
			foreach ($off['Passengers'] ?: [] as $p)
			{
				$bookingParams['Reservations[' . $offPos . '][Occupants][' . $passengerPos . '][Gender]'] = $p['Gender'];	
				$bookingParams['Reservations[' . $offPos . '][Occupants][' . $passengerPos . '][First_Name]'] = $p['Firstname'];
				$bookingParams['Reservations[' . $offPos . '][Occupants][' . $passengerPos . '][Last_Name]'] = $p['Lastname'];
				$bookingParams['Reservations[' . $offPos . '][Occupants][' . $passengerPos . '][Date_Of_Birth]'] = $p['BirthDate'];
				$passengerPos++;
			}

			$offPos++;
		}

		//$method, $callParams = [], $filter = null, $useSimpleCache = false, $logData = false
		list($offersResp, $rawResp) = $this->request('reservations-make', $bookingParams, $data, false, true);

		if ((!$offersResp) || (!$offersResp['response']))
		{
			throw new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
		}			

		$order = new \stdClass();
		$order->Id = $offersResp['response']['order_id'];
		// return order and xml confrm reservation
		return [$order, $rawResp];
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
	
	/**
	 * @param array $filter
	 */
	public function api_cancelBooking(array $filter = null)
	{
		
	}

	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "h2b";
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
	public function request($method, $callParams = [], $filter = null, $useSimpleCache = false, $logData = false)
	{
		if (!$this->TourOperatorRecord->ApiPassword)
			throw new \Exception('Api Password not provided!');
		if ($callParams === null)
			$callParams = [];
		$callParams['auth_key'] = $this->TourOperatorRecord->ApiPassword;

		#if (in_array($method, ["hotel_search_results"]) && $this->useMultiInTop && (!$_GET['_force_exec_call']))
		if (in_array($method, ["search-search"]) && $this->useMultiInTop && (!$_GET['_force_exec_call']))
			$ret = $this->requestOnTop_SetupMulti($method, $callParams, $filter, $logData);
		else
			$ret = $this->requestOnTop($method, $callParams, $filter, $logData);

		$respJson = $ret;
		if ($ret && ($ret !== null))
			$ret = json_decode($ret, true);

		if ($ret && $ret['error'])
		{
			$ex = new \Exception('H2b System responded with error: ' . $ret['error_no'] . " | " . $ret['error']);
			$this->logError([
				"\$method" => $method,
				'\$callParams' => $callParams,
				'\$filter' => $filter,
				"\$logData" => $logData,
				"respJSON" => $respJson,
			], $ex);
			throw $ex;
		}

		return [$ret, $respJson];
	}
	
	public function getApiUrl($method)
	{
		$type = $this->apiTypesByMethod[$method];
		return rtrim(($this->TourOperatorRecord->ApiUrl__ ?: $this->TourOperatorRecord->ApiUrl), "\\/") . "/?call=" . $method . ($type ? '&type=' . $type : '');
	}

	public function requestOnTop($method, $callParams = [], $filter = null, $logData = false)
	{
		$logDataSimple = false;
		if (!$logData)
		{
			$logData = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));
			$logDataSimple = true;
		}

		$url = $this->getApiUrl($method);
		$curlHandle = $this->_curl_handle = q_curl_init_with_log();
		$headers = [];

		q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $url);

		// send xml request to a server
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
		q_curl_setopt_with_log($curlHandle, CURLINFO_HEADER_OUT, true);

		if ($callParams)
		{
			q_curl_setopt_with_log($curlHandle, CURLOPT_POST, 1);
			q_curl_setopt_with_log($curlHandle, CURLOPT_POSTFIELDS, $callParams);
		}

		q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION, 1);
		q_curl_setopt_with_log($curlHandle, CURLOPT_CONNECTTIMEOUT, 10);

		q_curl_setopt_with_log($curlHandle, CURLOPT_VERBOSE, 0);
		q_curl_setopt_with_log($curlHandle, CURLOPT_HTTPHEADER, $headers);

		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);

		$this->_reqHeaders = [];
		$headers_out = &$this->_reqHeaders;
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers_out) {
			$len = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) < 2) // ignore invalid headers
				return $len;

			$name = strtolower(trim($header[0]));
			if (!array_key_exists($name, $headers_out))
				$headers_out[$name] = [trim($header[1])];
			else
				$headers_out[$name][] = trim($header[1]);
			return $len;
		});

		$t1 = microtime(true);
		$ret = q_curl_exec_with_log($curlHandle);
		curl_close($curlHandle);

		#if ($method == 'reservations-make')
		#	echo $ret;

		if ($ret === false)
		{
			$ex = new \Exception("Invalid response from server - " . curl_error($curlHandle));
			$this->logError([
				"\$method" => $method,
				'\$callParams' => $callParams,
				'\$filter' => $filter,
			], $ex);
			throw $ex;
		}

		$httpcode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		if ($httpcode >= 400)
		{
			# we have an error
			$ex = new \Exception($ret);
			$this->logError([
				"\$method" => $method,
				'\$callParams' => $callParams,
				'\$filter' => $filter,
				"respJSON" => $ret,
				"duration" => (microtime(true) - $t1) . " seconds"
			], $ex);
			throw $ex;
		}

		if ($logData)
		{
			// log paradise data
			$logMeth = $logDataSimple ? 'logDataSimple' : 'logData';
			$this->{$logMeth}("request." . ($filter && $filter["cityId"] ? $filter["cityId"] : "_") . "." . $method, [
				"\$method" => $method,
				'\$callParams' => $callParams,
				'\$filter' => $filter,
				"respJSON" => $ret,
				"duration" => (microtime(true) - $t1) . " seconds"
			]);
		}
		return $ret;
	}

	public function requestOnTop_SetupMulti($method, $postParams = [], $filter = null, $logData = false)
	{
		
	}

	public function getRequestMode()
	{
		return static::RequestModeCurl;
	}
}