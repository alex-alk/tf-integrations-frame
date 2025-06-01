<?php

namespace Integrations\GoGlobal_old;

use IntegrationTraits\TourOperatorRecordTrait;
use Omi\TF\TOInterface_Util;

class GoGlobal_old extends \Omi\TF\TOInterface
{
	// ------added--------
	use TourOperatorRecordTrait;
	// -------------------

	use TOInterface_Util;
	
	protected $curl;
	
	public static $UrlCsvCountries = 'https://static-data.tourismcloudservice.com/propsdata/Destinations/compress/false';
	
	public static $UrlCsvHotels = 'https://static-data.tourismcloudservice.com/propsdata/HotelsExtendedCustom/compress/false';
	
	public static $UrlCsvSearchCodes = 'https://static-data.tourismcloudservice.com/propsdata/InfoSearchCodes/compress/false';
	
	public static $ExtractedHotelsFolder = 'unrar/';
	
	public static $CsvHotelsFileName = 'csv_hotels.csv';
	
	public static $RarHotelsFileName = 'csv_hotels.rar';
	
	public static $FileRequestUsername = 'TRAVELFUSEXMLTEST';
	
	public static $FileRequestPassword = 'YGH32LPH4974';
	
	public $mapDataCacheLimit = 60 * 60 * 24 * 7;
	
	public $cityFilesCacheLimit = 60 * 60 * 24 * 3;
	
	protected $destinations;
	
	protected $hotels;
	
	protected $hotelsSearchCodes;
	
	protected $cacheTimeLimit = 60 * 60 * 24;
	
	public static $Microtime;
	
	public $useAdditionalHeaders = false;
	
	public $useAdditionalHeaders_new = true;

	public function getApiCredentialsHeaders()
	{
		return '<Agency>' . ($this->ApiContext__ ?: $this->ApiContext) . '</Agency>'
			. ($this->useAdditionalHeaders ? '<API-AgencyID>' . ($this->ApiContext__ ?: $this->ApiContext) . '</API-AgencyID>' : '')
			. '<User>' . ($this->ApiUsername__ ?: $this->ApiUsername) . '</User>
			<Password>' . ($this->ApiPassword__ ?: $this->ApiPassword) . '</Password>';
	}
	
	public function getOperationHeaders($operation)
	{
		return '<Operation>' . $operation . '</Operation>'
			. ($this->useAdditionalHeaders ? '<API-Operation>' . $operation . '</API-Operation>' : '');
	}
	
	/**
	 * Test connection
	 * 
	 * @param array $filter
	 */
	public function api_testConnection(array $filter = null)
	{
		# does not have method to test connection
		# not ok to search offers
		//return true;
		
		// missing cache folder
		if (!is_dir(($cacheFolder = $this->getResourcesDir())))
			throw new \Exception('Missing cache folder!');

		// get cache file
		$firstCityFile = $cacheFolder . 'first_city.json';
		
		// calculate cache time limit
		$map_cache_time_limit = (time() - $this->mapDataCacheLimit);

		if (file_exists($firstCityFile) || (filemtime($firstCityFile) > $map_cache_time_limit))
			unlink($firstCityFile);
		
		if ((!file_exists($firstCityFile)))
		{
			$countriesCsvFile = $this->getCountriesCsvFile();
			if ($countriesCsvFile && file_exists($countriesCsvFile))
			{
				$header = null;
				if (($handle = fopen($countriesCsvFile, 'r')) !== false)
				{
					while (($row = fgetcsv($handle, null, '|')) !== false)
					{
						// did not get the header
						if ($header === null)
						{	
							// header is the first row of the csv
							foreach ($row ?: [] as $rk => $rv)
							{
								$row[$rk] = str_replace("\"", "", preg_replace('/\x{FEFF}/u', '', $rv));
							}
							$header = $row;
							// skip to the first row with values
							continue;
						}

						$rec_mix = array_combine($header, $row);
						
						if ($rec_mix["CityId"])
						{
							$firstCity = $rec_mix;
							file_put_contents($firstCityFile, json_encode($firstCity));
							break;
						}
					}
				}
			}
			else
			{
				throw new \Exception('Countries file not found');
			}
		}
		else
		{
			$firstCity = json_decode(file_get_contents($firstCityFile), true);
		}

		if ($firstCity && ($cityId = ($firstCity["CityId"] ?: $firstCity["cityID"])))
		{
			$testRequest = '<requestType>11</requestType>
				<xmlRequest><![CDATA[
					<Root>
						<Header>
							' . $this->getApiCredentialsHeaders() . '
							' . $this->getOperationHeaders('HOTEL_SEARCH_REQUEST') . '
							<OperationType>Request</OperationType>
						</Header>
						<Main Version="2.2" ResponseFormat="JSON" IncludeGeo="false" Currency="EUR">
							<FilterPriceMin>0</FilterPriceMin>
							<FilterPriceMax>1000000</FilterPriceMax>
							<Nationality>RO</Nationality>
							<MaximumWaitTime>60</MaximumWaitTime>
							<MaxResponses>10</MaxResponses>
							<CityCode>' . $cityId . '</CityCode>
							<ArrivalDate>' . date("Y-m-d", strtotime("+10 days")) . '</ArrivalDate>
							<Nights>7</Nights>
							<Rooms>
								<Room Adults="1" ChildCount="0" RoomCount="1" />
							</Rooms>
						</Main>
					</Root>
				]]></xmlRequest>';

			// get hotel details response
			$ret = $this->request('MakeRequest', 'HOTEL_SEARCH_REQUEST', $testRequest, false, true);
			if ($ret && is_object($ret) && $ret->MakeRequestResult)
			{
				$retData = null;
				if (($retDataFromJson = json_decode($ret->MakeRequestResult, true)))
				{
					$retData = $retDataFromJson;
				}
				else
				{
					try
					{
						$retData = $this->simpleXML2Array(simplexml_load_string($ret->MakeRequestResult));
					}
					catch (\Exception $ex)
					{

					}
				}
				$explicitErr = ($retData && $retData["Main"] && $retData["Main"]["Error"] && is_array($retData["Main"]["Error"]) &&
					($retData["Main"]["Error"]["Code"] != 1)) ? $retData["Main"]["Error"] : null;
				if ($explicitErr)
				{
					echo "<div style='color: red;'>" . $explicitErr["Message"] . "</div>";
					if (isset($retData["Main"]["DebugError"]["Message"]))
						echo "<div style='color: red;'>" . $retData["Main"]["DebugError"]["Message"] . "</div>";
				}

				return (($retData && $retData["Main"]) && (!$explicitErr));
			}
		}
		else
			throw new \Exception('First city not found!');

		return false;
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
		// get all destinations
		$destinations = $this->getPreparedDestinations($filter);

		// exit if no destinations
		if (!$destinations)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'destinations couldn\'t be pulled/prepared');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
			return [false];
		}
		
		$countries = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count all destinations: %s', [$destinations ? q_count($destinations) : 'no_destinations']);

		foreach ($destinations as $destination)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process destination: %s', [$destination['CountryId'] . ' ' . $destination['Country']], 50);
			if (!$countries[$destination['CountryId']])
			{
				$country = new \stdClass();
				$country->Id = $destination['CountryId'];
				$country->Name = $destination['Country'];
				$country->Code = $destination['IsoCode'];
				
				$countries[$country->Id] = $country;
			}
			else 
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Destination does not have country data: %s', [json_encode($destination)], 50);
			}
		}
		
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
		\Omi\TF\TOInterface::markReportData($filter, 'tour operator does not have regions support');
		\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');
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
		// get all destinations
		$destinations = $this->getPreparedDestinations($filter);

		// get countries
		list($countries) = $this->api_getCountries(array_merge($filter ?: [], ['skip_report' => true]));

		// exit if no destinations
		if (!$destinations) 
		{
			\Omi\TF\TOInterface::markReportData($filter, 'destinations couldn\'t be pulled/prepared');
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'cities');
			return [false];
		}

		// init array
		$cities = [];
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count all destinations: %s', [$destinations ? q_count($destinations) : 'no_destinations']);
		
		// go though each destination
		foreach ($destinations as $destination)
		{
			if ($filter['countryId'] && ($destination['CountryId'] != $filter['countryId']))
				continue;
			
			\Omi\TF\TOInterface::markReportData($filter, 'Process destination: %s', [$destination['CityId'] . ' ' . $destination['City']], 50);
			
			$city = null;
			if ($destination['CityId'] && $destination['City'] && $destination['CountryId'])
			{
				$country = $countries[$destination['CountryId']];
				if ($country)
				{
					\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', [$destination['CityId'] . ' ' . $destination['City']], 50);
					$city = new \stdClass();
					$city->Id = $destination['CityId'];
					$city->Name = $destination['City'];
					$city->Country = $country;	
				}
				else
				{
					\Omi\TF\TOInterface::markReportError($filter, 'CountryId provided on destination but no actual country found: %s', 
						[json_encode($destination)], 50);
				}
			}
			else 
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Destination does not have city data: %s', [json_encode($destination)], 50);
			}

			if ($city)
			{
				// index cities by id
				$cities[$city->Id] = $city;
			}
		}
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
	 * Get hotels from api.
	 * 
	 * $filter: CountryId, CountryCode, ...city
	 * 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	// ------------------------- disabled -------------------
	/*
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');

		static::$Microtime = microtime(true);

		if (!isset($filter['CityId']))
		{
			\Omi\TF\TOInterface::markReportData($filter, "City ID not provided for pulling the hotels");
			\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
			throw new \Exception('CityId is mandatory');
		}

		// get prepared hotels from csv
		$preparedHotelsForCity = $this->getPreparedHotels_ForCity($filter['CityId'], ['skip_report' => true]);
		
		// init hotels
		$countries = [];
		$cities = [];
		$hotels = [];

		$cacheFolder = $this->getResourcesDir();
		$cache_hotels_search_codes_file = $cacheFolder . "hotels_search_codes.json";

		$cache_hotels_search_codes = [];
		if (file_exists($cache_hotels_search_codes_file))
		{
			$cache_hotels_search_codes = json_decode(file_get_contents($cache_hotels_search_codes_file), true);
		}

		if (is_scalar($cache_hotels_search_codes))
			$cache_hotels_search_codes = [];
		
		$saveHotelsCodes = false;
		
		\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s for city: %s', 
			[$preparedHotelsForCity ? q_count($preparedHotelsForCity) : 'no_hotels', $filter['CityId']]);

		// go throughg each prepared hotel
		foreach ($preparedHotelsForCity ?: [] as $hotelData)
		{
			\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', [$hotelData['HotelId'] . ' ' . $hotelData['Name']], 50);
			if ((!$hotelData['CountryId']) || (!$hotelData['Country']) || (!$hotelData['IsoCode']) || 
				(!$hotelData['CityId']) || (!$hotelData['HotelId']) || (!$hotelData['Name']))
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Hotel has incomplete data: %s', 
					[json_encode($hotelData)], 50);
				continue;
			}

			$hotel = new \stdClass();
			$hotel->Id = $hotelData['HotelId'];
			$hotel->Name = $hotelData['Name'];
			$hotel->Address = new \stdClass();

			$countryObj = $countries[$hotelData['CountryId']] ?: ($countries[$hotelData['CountryId']] = new \stdClass());
			$countryObj->Id = $hotelData['CountryId'];
			$countryObj->Name = $hotelData['Country'];
			$countryObj->Code = $hotelData['IsoCode'];

			$cityObj = $cities[$hotelData['CityId']] ?: ($cities[$hotelData['CityId']] = new \stdClass());
			$cityObj->Id = $hotelData['CityId'];
			$cityObj->Name = $hotelData['City'];
			$cityObj->Country = $countryObj;
			$hotel->Address->City = $cityObj;

			if ($hotelData['Address'])
				$hotel->Address->Details = $hotelData['Address'];
			if ($hotelData['Stars'])
				$hotel->Stars = round($hotelData['Stars']);
			if ($hotelData['Longitude'])
				$hotel->Address->Longitude = $hotelData['Longitude'];
			if ($hotelData['Latitude'])
				$hotel->Address->Latitude = $hotelData['Latitude'];
			if ($hotelData['Phone'] || $hotelData['Fax'])
			{
				$hotel->ContactPerson = new \stdClass();
				$hotel->ContactPerson->Phone = $hotelData['Phone'];
				$hotel->ContactPerson->Fax = $hotelData['Fax'];
			}

			if ($hotelData['HotelId'] && $hotelData['Infosearchcode'])
			{
				if (!isset($cache_hotels_search_codes[$hotelData['HotelId']]) || ($cache_hotels_search_codes[$hotelData['HotelId']] != $hotelData['Infosearchcode']))
				{
					$saveHotelsCodes = true;
					$cache_hotels_search_codes[$hotelData['HotelId']] = $hotelData['Infosearchcode'];
				}
			}

			// get hotel details
			if ($filter['get_extra_details'])
			{
				// get json response
				$hotelDetails = $this->getHotelDetails($hotelData['Infosearchcode']);
				$this->setupHotelContent($hotel, $hotelDetails);
			}

			$hotels[$hotel->Id] = $hotel;
		}

		if ($saveHotelsCodes)
		{
			file_put_contents($cache_hotels_search_codes_file, json_encode($cache_hotels_search_codes));
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
		// return hotels
		return $hotels;
	}*/

	// --------------- added -------------------------
	private function unZip($srcName, $dstName): void
    {
		$zip = new ZipArchive();
		$res = $zip->open($srcName);
        
		if ($res === TRUE) {
			$filename = $zip->getNameIndex(0);
			
			$zip->extractTo($dstName);
     		rename($dstName."/$filename", $dstName . '/hotels-list.csv');
			$zip->close();
		}
    }

	private function getDownloadedHotelsCsvPath(): string
	{
		$cacheFolder = $this->getResourcesDir();

		// missing cache folder
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		// get cache file
		$zip = $cacheFolder . 'hotels-list.zip';
		$unzipFolder = $cacheFolder . 'hotels-list-unzipped';


		$csv = $unzipFolder . '/hotels-list.csv';
		$filePointer = fopen($zip, 'w+');

		$url = 'https://static-data.tourismcloudservice.com/agency/hotels/' . ($this->ApiContext__ ?: $this->ApiContext);
		$this->downloadFileNew($url, $filePointer);

		$this->unZip($zip, $unzipFolder);

		return $csv;
	}

	// de schimbat: lista de hoteluri trebuie apelata o singura data!
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');

		static::$Microtime = microtime(true);

		$hotelsCsvPath = $this->getDownloadedHotelsCsvPath();

		$countries = [];
		$cities = [];
		$hotelsArr = [];
		$hotels = [];
		if (($handle = fopen($hotelsCsvPath, "r")) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, "|")) !== FALSE) {
				if (empty($fields)) {
                    $fields = $row;
                    $fields[0] = str_replace('"', '', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $fields[0]));
                    continue;
                }

                foreach ($row as $k => $value) {
                    $hotelsArr[$row[5]][$fields[$k]] = $value;
                }
				$hotelData = $hotelsArr[$row[5]];

				
				\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', [$hotelData['HotelID'] . ' ' . $hotelData['Name']], 50);
				if ((!$hotelData['CountryId']) || (!$hotelData['Country']) || (!$hotelData['IsoCode']) || 
					(!$hotelData['CityId']) || (!$hotelData['HotelID']) || (!$hotelData['Name']))
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Hotel has incomplete data: %s', 
						[json_encode($hotelData)], 50);
					continue;
				}

				$hotel = new \stdClass();
				$hotel->Id = $hotelData['HotelID'];
				$hotel->Name = $hotelData['Name'];
				$hotel->Address = new \stdClass();
	
				$countryObj = $countries[$hotelData['CountryId']] ?: ($countries[$hotelData['CountryId']] = new \stdClass());
				$countryObj->Id = $hotelData['CountryId'];
				$countryObj->Name = $hotelData['Country'];
				$countryObj->Code = $hotelData['IsoCode'];
	
				$cityObj = $cities[$hotelData['CityId']] ?: ($cities[$hotelData['CityId']] = new \stdClass());
				$cityObj->Id = $hotelData['CityId'];
				$cityObj->Name = $hotelData['City'];
				$cityObj->Country = $countryObj;
				$hotel->Address->City = $cityObj;
	
				if ($hotelData['Address'])
					$hotel->Address->Details = $hotelData['Address'];
				if ($hotelData['Stars'])
					$hotel->Stars = round($hotelData['Stars']);
				if ($hotelData['Longitude'])
					$hotel->Address->Longitude = $hotelData['Longitude'];
				if ($hotelData['Latitude'])
					$hotel->Address->Latitude = $hotelData['Latitude'];
				if ($hotelData['Phone'] || $hotelData['Fax'])
				{
					$hotel->ContactPerson = new \stdClass();
					$hotel->ContactPerson->Phone = $hotelData['Phone'];
					$hotel->ContactPerson->Fax = $hotelData['Fax'];
				}

				$hotels[$hotel->Id] = $hotel;
			}
			fclose($handle);
		}

		\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s', 
			[$hotelsArr ? q_count($hotelsArr) : 'no_hotels']);

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
		return $hotels;
	}
	// ------------------------------------------------------------------------------------
	
	public function setupHotelContent($hotel, $hotelDetails)
	{
		if ((!$hotel->Name) && $hotelDetails['Name'])
			$hotel->Name = $hotelDetails['Name'];

		if ($hotelDetails && ($hotelDetails['Description'] || $hotelDetails['Images']))
		{
			$hotel->Content = new \stdClass();
			if ($hotelDetails['Description'])
				$hotel->Content->Content = $hotelDetails['Description'];

			if ($hotelDetails['Images'])
			{
				$hotel->Content->ImageGallery = new \stdClass();
				foreach ($hotelDetails['Images'] as $image)
				{
					$photo_obj = new \stdClass();
					$photo_obj->RemoteUrl = $image;
					$hotel->Content->ImageGallery->Items[] = $photo_obj;
				}
			}
		}

		if ($hotelDetails['Facilities'])
		{
			$expHotelFacilities = explode(',', $hotelDetails['Facilities']);
			#$hotelDetails['Facilities'] = $hotelFacilities;
			foreach ($expHotelFacilities as $expHotelFacility)
			{
				$trimmedExpHotelFacility = trim($expHotelFacility);

				$facility = new \stdClass();
				$facility->Id = md5($trimmedExpHotelFacility);
				$facility->Name = $trimmedExpHotelFacility;

				$hotel->Facilities[$facility->Id] = $facility;
			}
		}
	}
	
	/**
	 * $filter: CountryId, CountryCode, ...city
	 * 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	// ---------------------------- disabled -----------------------------
	/*
	public function getHotelDetails($searchCode)
	{	
		$requestString = '
			<requestType>61</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('HOTEL_INFO_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main>
						<HotelSearchCode>' . $searchCode . '</HotelSearchCode>
					</Main>
				</Root>
			]]></xmlRequest>';

		// get hotel details response
		$hotelDetailsResp = $this->request('MakeRequest', 'HOTEL_SEARCH_REQUEST', $requestString, true, true);

		if (!$hotelDetailsResp->MakeRequestResult)
			return null;

		$hotelDetailsXml = simplexml_load_string($hotelDetailsResp->MakeRequestResult);

		if (!($hotelDetailsData = ($hotelDetailsXml ? $hotelDetailsXml->Main : null)))
			return null;

		$hotelDetails = [
			"Name" => $hotelDetailsData->HotelName . '',
		];

		// get hotel city code
		// $hotelPhone = $hotelDetailsData->Phone . '';
		// $hotelFax = $hotelDetailsData->Fax . '';
		$hotelDescription = $hotelDetailsData->Description . '';

		// get hotel pictures
		$hotelPictures = $hotelDetailsData->Pictures;

		// $hotelHotelFacilities = $hotelDetailsData->HotelFacilities;
		// $hotelHotelRoomFacilities = $hotelDetailsData->RoomFacilities;
		// $hotelHotelRoomCount = $hotelDetailsData->RoomCount;

		/*
		$hotelGeoCodes = $hotelDetailsData->GeoCodes;
		if ($hotelGeoCodes)
		{
			foreach ($hotelGeoCodes as $hotelGeoCode)
			{
				$hotelLatitude = $hotelGeoCode->Latitude . '';
				$hotelLongitude = $hotelGeoCode->Longitude . '';
			}
		}
		* 
		*/
		/*
		if ($hotelPictures)
		{
			$pictures = [];
			foreach ($hotelPictures->Picture as $hotelPicture)
			{
				if ($hotelPicture)
					$pictures[] = $hotelPicture . '';
			}
		}

		$hotelDetails['Description'] = '';
		if ($hotelDescription)
			$hotelDetails['Description'] = $hotelDescription;

		$roomFacilities = (string)$hotelDetailsXml->Main->RoomFacilities;
		if ($roomFacilities)
			$hotelDetails['Description'] .= '<br /><h4>Room Facilities</h4><div>' . $roomFacilities . '</div>';

		$hotelFacilities = (string)$hotelDetailsXml->Main->HotelFacilities;
		if ($hotelFacilities)
			$hotelDetails['Facilities'] = $hotelFacilities;

		if ($pictures)
		{
			$hotelDetails['Images'] = [];
			foreach ($pictures as $picture)
				$hotelDetails['Images'][] = (string)$picture;
		}

		// return hotel details
		return $hotelDetails;
	}
	
	/**
	 * $filter: CountryId, CountryCode, ...city
	 * 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	/*
	public function api_getHotelDetails(array $filter = null)
	{
		if (!$filter['search_code'])
		{
			$hotelId = $filter['HotelId'];
			if ($hotelId)
			{
				$cacheFolder = $this->getResourcesDir();
				$cache_hotels_search_codes_file = $cacheFolder . "hotels_search_codes.json";
				$cache_hotels_search_codes = [];
				if (file_exists($cache_hotels_search_codes_file))
				{
					$cache_hotels_search_codes = json_decode(file_get_contents($cache_hotels_search_codes_file), true);
				}
			}
			if ($cache_hotels_search_codes[$hotelId])
				$filter['search_code'] = $cache_hotels_search_codes[$hotelId];
		}

		// error if no hotel code
		if (!$filter['search_code'])
		{
			return null;
		}
			#throw new \Exception('Missing hotel code!');
		
		/*
		if (!$filter["HotelId"])
			throw new \Exception("Hotel Id must be specified!");
		
		// get hotel search codes prepared
		$hotelsSearchCodesPrepared = $this->getHotelsSearchCodes();
		
		if (!$hotelsSearchCodesPrepared[$filter["HotelId"]])
			return false;
		
		// get indexed hotel search code by hotel id
		$hotelSearchCodes = $hotelsSearchCodesPrepared[$filter["HotelId"]];
		
		// explode by /
		$hotelSearchCodesArray = explode('/', $hotelSearchCodes);
		
		$hasHotelDetails = false;
		
		$count = 0;
		*/
		/*
		$hotel = new \stdClass();
		// get json response
		$hotelDetails = $this->getHotelDetails($filter['search_code']);
		$this->setupHotelContent($hotel, $hotelDetails);
		
		return $hotel;

	}*/
	// ------------------------------------------------------------------------

	// --------------------------- added --------------------------------------
	public function api_getHotelDetails(array $filter = null)
	{
		$hotel = new \stdClass();
		// get json response
		$hotelDetails = $this->getHotelDetails($filter['HotelId']);
		$this->setupHotelContent($hotel, $hotelDetails);
		
		return $hotel;
	}

	public function getHotelDetails($hotelId)
	{	
		$requestString = '
			<requestType>61</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('HOTEL_INFO_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main>
						<InfoHotelId>' . $hotelId . '</InfoHotelId>
					</Main>
				</Root>
			]]></xmlRequest>';

		// get hotel details response
		$hotelDetailsResp = $this->request('MakeRequest', 'HOTEL_SEARCH_REQUEST', $requestString, true, true);

		if (!$hotelDetailsResp->MakeRequestResult)
			return null;

		$hotelDetailsXml = simplexml_load_string($hotelDetailsResp->MakeRequestResult);

		if (!($hotelDetailsData = ($hotelDetailsXml ? $hotelDetailsXml->Main : null)))
			return null;

		$hotelDetails = [
			"Name" => $hotelDetailsData->HotelName . '',
		];

		// get hotel city code
		// $hotelPhone = $hotelDetailsData->Phone . '';
		// $hotelFax = $hotelDetailsData->Fax . '';
		$hotelDescription = $hotelDetailsData->Description . '';

		// get hotel pictures
		$hotelPictures = $hotelDetailsData->Pictures;

		// $hotelHotelFacilities = $hotelDetailsData->HotelFacilities;
		// $hotelHotelRoomFacilities = $hotelDetailsData->RoomFacilities;
		// $hotelHotelRoomCount = $hotelDetailsData->RoomCount;

		/*
		$hotelGeoCodes = $hotelDetailsData->GeoCodes;
		if ($hotelGeoCodes)
		{
			foreach ($hotelGeoCodes as $hotelGeoCode)
			{
				$hotelLatitude = $hotelGeoCode->Latitude . '';
				$hotelLongitude = $hotelGeoCode->Longitude . '';
			}
		}
		* 
		*/
		
		if ($hotelPictures)
		{
			$pictures = [];
			foreach ($hotelPictures->Picture as $hotelPicture)
			{
				if ($hotelPicture)
					$pictures[] = $hotelPicture . '';
			}
		}

		$hotelDetails['Description'] = '';
		if ($hotelDescription)
			$hotelDetails['Description'] = $hotelDescription;

		$roomFacilities = (string)$hotelDetailsXml->Main->RoomFacilities;
		if ($roomFacilities)
			$hotelDetails['Description'] .= '<br /><h4>Room Facilities</h4><div>' . $roomFacilities . '</div>';

		$hotelFacilities = (string)$hotelDetailsXml->Main->HotelFacilities;
		if ($hotelFacilities)
			$hotelDetails['Facilities'] = $hotelFacilities;

		if ($pictures)
		{
			$hotelDetails['Images'] = [];
			foreach ($pictures as $picture)
				$hotelDetails['Images'][] = (string)$picture;
		}

		// return hotel details
		return $hotelDetails;
	}
	// -----------------------------------------------------



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
	public function api_getOffers(array $filter = null)
	{
		// check filter and get service type
		$serviceType = $this->checkFilters($filter);
		
		switch ($serviceType)
		{
			case 'hotel':
			case 'individual':
			{
				// get offers index by hotels
				$hotels = $this->getIndividualOffers($filter);

				// return hotels
				return [$hotels];
			}
		}
		return [null];
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferAvailability(array $filter = null)
	{
		
	}
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOfferDetails(array $filter = null)
	{
		
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
	 * @param array $filter
	 */
	public function api_prepareBooking(array $filter = null)
	{
		
	}

	/**
	 * Do Booking.
	 * 
	 * @param array $filter
	 */
	public function api_doBooking(array $filter = null)
	{
		// get offer
		$offer = q_reset($filter['Items']);		
		if (!$offer || !$offer['Offer_HotelSearchCode'] || !$offer['Offer_Days'] || !$offer['Room_CheckinBefore'] 
			|| !$offer['Room_CheckinAfter'] || !$offer['Offer_Days'])
			throw new \Exception('Missing offer data!');
		
		// get passengers
		$passengers = $filter['Passengers'];		
		if (!$passengers || (q_count($passengers) == 0))
			throw new \Exception('Missing Passengers');
		
		$bookingValuationString = '
			<requestType>9</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('BOOKING_VALUATION_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main Version="2.0">
						<HotelSearchCode>' . $offer['Offer_HotelSearchCode'] . '</HotelSearchCode>
						<ArrivalDate>' . $offer['Room_CheckinAfter'] . '</ArrivalDate>
					</Main>
				</Root>
			]]></xmlRequest>';
		
		// get booking valuaition response
		$bookingValuationResult = $this->request('MakeRequest', 'BOOKING_VALUATION_REQUEST', $bookingValuationString, false, true);
		
		if (!$bookingValuationResult->MakeRequestResult)
			return false;
		
		$bookingValuationXml = simplexml_load_string($bookingValuationResult->MakeRequestResult);
		
		$adults = 0;
		$children = 0;
		
		foreach ($passengers as $passenger)
		{
			if ($passenger['Type'] == 'adult')
				$adults++;
			else if ($passenger['Type'] == 'child')
				$children++;
		}
		
		$diffDate = date_create($offer['Room_CheckinBefore']);
		
		$requestRoomString = '<Rooms>'
			. '<RoomType Adults="' . $adults . '" ChildCount="' . $children . '">'
				. '<Room RoomID="1">';
		foreach ($passengers as $i => $passenger)
		{
			$index = $i + 1;
			
			if ($passenger['Type'] == 'adult')
			{
				if ($passenger['Gender'] == 'male')
					$passengerTitle = 'MR.';
				else
					$passengerTitle = 'MS.';
					
				$requestRoomString .= '<PersonName PersonID="' . $index . '" Title="' . $passengerTitle . '" FirstName="' . $passenger['Firstname'] . '" LastName="' . $passenger['Lastname'] .'"/>';
			}
			else if ($passenger['Type'] == 'child')
			{
				if ($passenger['Gender'] == 'male')
					$passengerTitle = 'MR.';
				else
					$passengerTitle = 'MISS';
				
				$age = date_diff($diffDate, date_create($passenger['BirthDate']))->y;
				
				$requestRoomString .= '<ExtraBed PersonID="' . $index . '" FirstName="' . $passenger['Firstname'] . '" LastName="' . $passenger['Lastname'] .'" ChildAge="' . $age . '" />';
			}
		}

		$requestRoomString .= '</Room>'
				. '</RoomType>'
			. '</Rooms>';
		
		$bookingInsertString = '
			<requestType>2</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('BOOKING_INSERT_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main Version="2.2">
						<AgentReference>' . $this->ApiUsername . '</AgentReference>
						<Remark></Remark>
						<TotalPrice>' . $offer['Offer_OfferTotalPrice'] . '</TotalPrice>
						<Currency>' . $offer['Offer_OfferCurrency'] . '</Currency>
						<HotelSearchCode>' . $offer['Offer_HotelSearchCode'] . '</HotelSearchCode>
						<ArrivalDate>' . $offer['Room_CheckinAfter'] . '</ArrivalDate>
						<Nights>' . $offer['Offer_Days'] . '</Nights>
						<NoAlternativeHotel>1</NoAlternativeHotel>
						<Leader LeaderPersonID="1"/>
						' . $requestRoomString . '
						' . $remarksString . '
					</Main>
				</Root>
			]]></xmlRequest>';
		
		// get booking insert response
		$bookingInsertResult = $this->request('MakeRequest', 'BOOKING_INSERT_REQUEST', $bookingInsertString, false, true);
		
		if (!$bookingInsertResult->MakeRequestResult)
			return false;
		
		$bookingInsertXml = simplexml_load_string($bookingInsertResult->MakeRequestResult);
		
		if (!$bookingInsertXml->Main || !$bookingInsertXml->Main->GoBookingCode || !$bookingInsertXml->Main->GoReference)
			return false;
		
		$bookingStatus = $bookingInsertXml->Main->BookingStatus;

		if ($bookingStatus == 'X')
			throw new \Exception('Booking is canceled!');

		$order = new \stdClass();
		$order->Id = $bookingInsertXml->Main->GoBookingCode;
		$order->BookingReference = $bookingInsertXml->Main->GoReference;
		
		// encode booking response
		$bookingJson = json_encode((array)$bookingInsertXml);		
		
		// return order and xml confrm reservation
		return [$order, $bookingJson];
	}

	/**
	 * Get bookings from api.
	 * 
	 * @param array $filter
	 */
	public function api_getBookings(array $filter = null)
	{
		$bookingsSearchString = '
			<requestType>10</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('ADV_BOOKING_SEARCH_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main>
						<CreatedDate>2019-10-30</CreatedDate>
					</Main>
				</Root>
			]]></xmlRequest>';
		
		// get hotel details response
		$bookingsResp = $this->request('MakeRequest', 'ADV_BOOKING_SEARCH_REQUEST', $bookingsSearchString, false, true);
		
		#qvar_dump($bookingsResp); q_die();
	}

	/**
	 * Cancel booking.
	 * 
	 * @param array $filter
	 */
	public function api_cancelBooking(array $filter = null)
	{
		if (!$filter['BookingId'])
			return false;	
		
		$requestString = '
			<requestType>3</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('BOOKING_CANCEL_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main>
						<GoBookingCode>' . $filter['BookingId'] . '</GoBookingCode>
					</Main>
				</Root>
			]]></xmlRequest>';
		
		// get hotel details response
		$cancelBookingResp = $this->request('MakeRequest', 'BOOKING_CANCEL_REQUEST', $requestString, false, true);
		
		#qvar_dump($cancelBookingResp); q_die();
	}
	
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
		
	public function api_getChartersRequests()
	{
	
	}
	
	public function getBookingStatus(array $filter = null)
	{
		if (!$filter['BookingCode'])
			throw new \Exception('Missing booking code!');
		
		$requestString = '
			<requestType>5</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('BOOKING_STATUS_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main>
						<GoBookingCode>' . $filter['BookingCode'] . '</GoBookingCode>
					</Main>
				</Root>
			]]></xmlRequest>';
		
		// get hotel details response
		$statusBookingResp = $this->request('MakeRequest', 'BOOKING_STATUS_REQUEST', $requestString, false, true);
		
		#qvar_dump($statusBookingResp); q_die();
	}
	
	public function getBoardItem($offer, $offerData)
	{
		if (!$offerData->RoomBasis)
		{
			// board
			$boardType = new \stdClass();
			$boardType->Id = "no_meal";
			$boardType->Title = "Fara masa";

			$boardMerch = new \stdClass();
			$boardMerch->Title = "Fara masa";
			$boardMerch->Type = $boardType;
		}
		else
		{
			// board
			$boardType = new \stdClass();
			$boardType->Id = md5($offerData->RoomBasis);
			$boardType->Title = $offerData->RoomBasis;

			$boardMerch = new \stdClass();
			$boardMerch->Title = $offerData->RoomBasis;
			$boardMerch->Type = $boardType;
		}

		$boardItm = new \stdClass();
		$boardItm->Merch = $boardMerch;
		$boardItm->Currency = $offer->Currency;
		$boardItm->Quantity = 1;
		$boardItm->UnitPrice = 0;
		$boardItm->Gross = 0;
		$boardItm->Net = 0;
		$boardItm->InitialPrice = 0;

		// for identify purpose
		// $boardItm->Id = $boardMerch->Id;

		return $boardItm;
	}
	
	/**
	 * Get individual offers based on a filter from the ajuniper api
	 * 
	 * @param array $filter
	 * @throws \Exception
	 */
	public function getIndividualOffers(array $filter = null)
	{		
		if (!$filter['cityId'])
			throw new \Exception('Missing city id!');

		// get checkin
		$checkIn = $filter["checkIn"];
		
		// calculate checkout date
		$checkOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));
		
		// get only first room
		$room = q_reset($filter['rooms']);
		
		$adults = $room['adults'];
		$children = $room['children'];
		
		$requestChildrentString = '';
		if ($children)
		{
			$childrenAges = $room['childrenAges'];
			
			for ($i = 0; $i < $children; $i++)
			{
				$requestChildrentString .= '<ChildAge>' . $childrenAges[$i] .'</ChildAge>';
			}
			
			$requestRoomString = '<Room Adults="' . $adults . '" ChildCount="' . $children . '" RoomCount="1">' . $requestChildrentString . '</Room>';
		}
		else
			$requestRoomString = '<Room Adults="' . $adults . '" ChildCount="0" RoomCount="1" />';
		
		if ($filter['travelItemId'])
			$requestHotelIdString = '<Hotels><HotelId>' . $filter['travelItemId'] . '</HotelId></Hotels>';
		
		$requestString = '
			<requestType>11</requestType>
			<xmlRequest><![CDATA[
				<Root>
					<Header>
						' . $this->getApiCredentialsHeaders() . '
						' . $this->getOperationHeaders('HOTEL_SEARCH_REQUEST') . '
						<OperationType>Request</OperationType>
					</Header>
					<Main Version="2.2" ResponseFormat="JSON" IncludeGeo="false" Currency="EUR">
						<FilterPriceMin>0</FilterPriceMin>
						<FilterPriceMax>1000000</FilterPriceMax>
						<Nationality>RO</Nationality>
						' . $requestHotelIdString . '
						<MaximumWaitTime>120</MaximumWaitTime>
						<MaxResponses>100000</MaxResponses>
						<CityCode>' . $filter['cityId'] . '</CityCode>
						<ArrivalDate>' . $filter['checkIn'] . '</ArrivalDate>
						<Nights>' . $filter['days'] . '</Nights>
						<Rooms>
							' . $requestRoomString . '
						</Rooms>
					</Main>
				</Root>
			]]></xmlRequest>';

		// get hotel details response
		$offersResp = $this->request('MakeRequest', 'HOTEL_SEARCH_REQUEST', $requestString, false, ($filter && ($filter['__booking_search__'] || $filter["__on_setup_search__"])));

		// if we have raw request then return the result raw
		if (($rawRequest = (isset($filter['rawResponse']) && $filter['rawResponse'])))
		{
			$resp = new \stdClass();
			$makeRequestResult_Decoded = $offersResp->MakeRequestResult ? json_decode($offersResp->MakeRequestResult) : null;
			if ($makeRequestResult_Decoded && $makeRequestResult_Decoded->Hotels)
			{
				$hotels = [];
				foreach ($makeRequestResult_Decoded->Hotels ?: [] as $h)
					$hotels[$h->HotelCode . "|" . $h->HotelName] = $h;
				$resp->MakeRequestResult_Hotels_Decoded = $hotels;
			}
			$resp->MakeRequestResult_Decoded = $makeRequestResult_Decoded;
			foreach ($offersResp ?: [] as $k => $respv)
				$resp->{$k} = $respv;
			return $resp;
		}

		if (!$offersResp->MakeRequestResult)
			return false;
		
		// decode resp
		$offersDecoded = json_decode($offersResp->MakeRequestResult);
		
		if (!$offersDecoded->Hotels)
			return false;
		
		$hotels = [];
		$eoffs = [];
		
		foreach ($offersDecoded->Hotels as $hotelData)
		{
			if (!$hotelData->Offers || !$hotelData->HotelCode || !$hotelData->HotelName)
				continue;
			
			$hotel = new \stdClass();
			$hotel->Id = $hotelData->HotelCode;
			$hotel->Name = $hotelData->HotelName;
			
			foreach ($hotelData->Offers as $offerData)
			{				
				if (!$hotel->Stars)
					$hotel->Stars = round($offerData->Category);
				
				// generate offer code
				$offerCode = $hotel->Id . '~' . $checkIn . '~' . $checkOut .'~' . md5(q_reset($offerData->Rooms)) . '~' . $offerData->RoomBasis . '~' . $offerData->TotalPrice . '~' . $offerData->CxlDeadLine;
				
				$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
				$offer->Code = $offerCode;
				
				$offer->HotelSearchCode = $offerData->HotelSearchCode;
				$offer->OfferTotalPrice = (float)$offerData->TotalPrice;
				$offer->OfferCurrency = $offerData->Currency;
				
				// set offer currency
				$offer->Currency = new \stdClass();
				$offer->Currency->Code = $offerData->Currency;
				
				// net price
				$offer->Net = (float)$offerData->TotalPrice;
				
				$offer->Gross = $offer->Net;
				
				// get availability :: Ok, Wait, Stop
				$offer->Availability = ($offerData->Availability == '1') ? 'yes' : (($offerData->Availability == '0') ? 'no' : 'ask' );
				
				// number of days needed for booking process
				$offer->Days = $filter['days'];
				
				$roomName = q_reset($offerData->Rooms);
				
				// room
				$roomType = new \stdClass();
				$roomType->Id = md5($roomName);
				$roomType->Title = $roomName;

				$roomMerch = new \stdClass();
				$roomMerch->Title = $roomName;
				$roomMerch->Type = $roomType;

				$roomItm = new \stdClass();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = $roomType->Id;

				//required for indexing
				$roomItm->Code = $roomType->Id;
				$roomItm->CheckinAfter = $filter['checkIn'];
				$roomItm->CheckinBefore = $checkOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Quantity = 1;
				$roomItm->Availability = $offer->Availability;
				
				if ($offerData->SpecialOffer)
					$roomItm->InfoDescription = $offerData->SpecialOffer;
				
				if ($offerData->Remark)
				{
					#$roomItm->InfoTitle = $offerData->Remark;

					if ($roomItm->InfoDescription)
						$roomItm->InfoDescription .= '<br />' . $offerData->Remark;
					else
						$roomItm->InfoDescription = $offerData->Remark;
				}

				if (!$offer->Rooms)
					$offer->Rooms = [];

				$offer->Rooms[] = $roomItm;

				$offer->MealItem = $this->getBoardItem($offer, $offerData);
				
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
				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;

				if ($offerData->CxlDeadLine)
				{
					$cxlDeadLine = str_replace('/', '-', $offerData->CxlDeadLine);
					$offer->CancelFees = [];
					$offer->FixCancelFeesPrices = true;
					$cancelFee = new \stdClass();
					$cancelFee->DateStart = date('Y-m-d', strtotime($cxlDeadLine));
					$cancelFee->DateEnd = $checkIn;
					$cancelFee->Price = $offer->Net;
					$cancelFee->Currency = $offer->Currency;
					$offer->CancelFees[] = $cancelFee;
				}

				// save offer on hotel
				$hotel->Offers[$offer->Code] = $offer;
			}

			$hotels[$hotel->Id] = $hotel;
		}

		return $hotels;
	}
	
	/**
	 * Check filters for get offers function.
	 * 
	 * @param type $filter
	 * @return type
	 * @throws \Exception
	 */
	public function checkFilters($filter)
	{
		$serviceType = ($filter && $filter['serviceTypes']) ? q_reset($filter['serviceTypes']) : null;

		if (!$serviceType)
			throw new \Exception("Service type is mandatory!");
		
		// check in is mandatory
		if (!$filter["checkIn"])
			throw new \Exception("CheckIn date is mandatory!");
		
		// check in is mandatory
		if (!($destination = ($filter["cityId"] ?: $filter["countryId"])))
			throw new \Exception("City is mandatory!");

		// number of days / nights are mandatory
		if (empty($filter["days"]))
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
	
		// return service type
		return $serviceType;
	}

	/**
	 * Get hotels from a csv file and prepare them
	 * @return type
	 * @throws \Exception
	 */
	public function getPreparedHotels_ForCity($cityID, $filter)
	{
		// get cache folder
		$cacheFolder = $this->getResourcesDir();
		$cacheFolder_fc = $cacheFolder . "prepared_hotels/";
		if (!is_dir($cacheFolder_fc))
			qmkdir($cacheFolder_fc);
		if (!isset($cityID))
			throw new \Exception('CityId is mandatory');
		$city_file_cache_limit = (time() - $this->cityFilesCacheLimit);
		$cityFile = $cacheFolder_fc . "city_" . $cityID . ".json";
		
		#echo $cityFile;
		if ((!file_exists($cityFile)) || (filemtime($cityFile) < $city_file_cache_limit))
		{
			// get cities
			list($cities) = $this->api_getCities($filter);
			$allCitiesIds = [];
			foreach ($cities ?: [] as $retCity)
				$allCitiesIds[$retCity->Id] = $retCity->Id;

			$hotelsCsvFile = $this->getHotelsCsvFiles();
			
			// get cache file
			$hotelsCsvFile = $cacheFolder . 'csv_hotels.csv';
			// get rar file
			// $hotelsRarFile = $cacheFolder . static::$RarHotelsFileName;

			/*
			// calculate cache time limit
			$map_cache_time_limit = (time() - $this->mapDataCacheLimit);
			if ((!file_exists($hotelsCsvFile)) || (filemtime($hotelsCsvFile) < $map_cache_time_limit))
			{
				$ch = q_curl_init_with_log(static::$UrlCsvHotels);
				q_curl_setopt_array_with_log($ch, [
					CURLOPT_FOLLOWLOCATION => 1,
					CURLOPT_RETURNTRANSFER => 1
				]);
				// get content of file from url
				$rarArchiveHotels = q_curl_exec_with_log($ch);
				// put contents into file 
				file_put_contents($hotelsRarFile, $rarArchiveHotels);
				$command = '7z x ' . $hotelsRarFile . ' -o"' . $cacheFolder . static::$ExtractedHotelsFolder . '" -aoa';
				// exec in command line
				exec($command);
				$files = scandir($cacheFolder . static::$ExtractedHotelsFolder);
				foreach ($files as $key => $file)
				{
					if(($file == '.') || ($file == '..'))
						unset($files[$key]);
				}
				if (!$files || (q_count($files) < 1))
					throw new \Exception('No files extracted');
				if (q_count($files) > 1)
					throw new \Exception('Extracted more files!');
				$file = q_reset($files);
				rename($cacheFolder . static::$ExtractedHotelsFolder . $file, $hotelsCsvFile);
				if (!file_exists($hotelsCsvFile))
					throw new \Exception('Hotels csv file could not be found!');
			}
			*/
			if (!file_exists($hotelsCsvFile))
				throw new \Exception("Hotels csv file not created!");
			$hotelsByCity = [];
			$header = null;
			if (($handle = fopen($hotelsCsvFile, 'r')) !== false)
			{
				while (($row = fgetcsv($handle, null, '|')) !== false)
				{
					// did not get the header
					if (!$header)
					{
						// header is the first row of the csv
						foreach ($row ?: [] as $rk => $rv)
						{
							$row[$rk] = str_replace("\"", "", preg_replace('/\x{FEFF}/u', '', $rv));
						}
						$header = $row;
						// skip to the first row with values
						continue;
					}
					$record = array_combine($header, $row);
					// combine array of name and value to array
					$hotelsByCity[$record["CityId"]][] = $record;
				}
			}
			foreach ($hotelsByCity ?: [] as $cityId => $cityHotels)
			{
				$tmpCityFile = $cacheFolder_fc . "city_" . $cityId . ".json";
				file_put_contents($tmpCityFile, json_encode($cityHotels));
			}
			foreach ($allCitiesIds ?: [] as $cityId)
			{
				if (!isset($hotelsByCity[$cityId]))
					file_put_contents($cacheFolder_fc . "city_" . $cityId . ".json", json_encode([]));
			}
		}
		
		return file_exists($cityFile) ? json_decode(file_get_contents($cityFile), true) : [];
		/*
		if (!$this->hotels)
		{
			// missing cache folder
			if (!is_dir($cacheFolder))
				throw new \Exception('Missing cache folder!');
			// get cache file
			$hotelsCsvFile = $cacheFolder . 'csv_hotels.csv';
			// calculate cache time limit
			$map_cache_time_limit = (time() - $this->mapDataCacheLimit);
			if (!file_exists($hotelsCsvFile) || (filemtime($hotelsCsvFile) < $map_cache_time_limit))
			{
				// TODO
			}
			$header = null;
			$this->hotels = [];
			if (($handle = fopen($hotelsCsvFile, 'r')) !== false)
			{
				while (($row = fgetcsv($handle, null, '|')) !== false)
				{				
					// did not get the header
					if (!$header)
					{	
						// header is the first row of the csv
						$header = $row;

						// skip to the first row with values
						continue;
					}
					$record = array_combine($header, $row);
					if (isset($filter['CityId']) && ($filter['CityId'] != $record['cityid']))
						continue;
					// combine array of name and value to array
					$this->hotels[] = $record;
				}
			}
			// return hotels
			return $this->hotels;
		}
		return $this->hotels;
		*/
	}

	/**
	 * Get prepared hotels search codes.
	 * 
	 * @return type
	 * @throws \Exception
	 */
	public function getHotelsSearchCodes()
	{
		if (!$this->hotelsSearchCodes)
		{
			// get cache folder
			$cacheFolder = $this->getResourcesDir();

			// missing cache folder
			if (!is_dir($cacheFolder))
				throw new \Exception('Missing cache folder!');

			// get cache file
			$hotelsSearchCodesCsvFile = $cacheFolder . 'csv_hotels_search_codes.csv';

			// calculate cache time limit
			$map_cache_time_limit = (time() - $this->mapDataCacheLimit);

			if (!file_exists($hotelsSearchCodesCsvFile) || (filemtime($hotelsSearchCodesCsvFile) < $map_cache_time_limit))
			{
				// Open file handler.
				$filePointer = fopen($hotelsSearchCodesCsvFile, 'w+');

				// doanload file to location
				$this->downloadFile(static::$UrlCsvSearchCodes, $filePointer);
			}

			$header = null;
			$this->hotelsSearchCodes = [];
			if (($handle = fopen($hotelsSearchCodesCsvFile, 'r')) !== false)
			{
				while (($row = fgetcsv($handle, null, '|')) !== false)
				{				
					// did not get the header
					if (!$header)
					{
						// header is the first row of the csv
						foreach ($row ?: [] as $rk => $rv)
						{
							$row[$rk] = str_replace("\"", "", preg_replace('/\x{FEFF}/u', '', $rv));
						}
						$header = $row;

						// skip to the first row with values
						continue;
					}

					$arrayCombine = array_combine($header, $row);

					if (!$this->hotelsSearchCodes[$arrayCombine['HotelId']])
						$this->hotelsSearchCodes[$arrayCombine['HotelId']] = $arrayCombine['Infosearchcode'];
					else
						$this->hotelsSearchCodes[$arrayCombine['HotelId']] .= '/' . $arrayCombine['Infosearchcode'];
				}
			}

			// return hotels
			return $this->hotelsSearchCodes;
		}
		else
			return $this->hotelsSearchCodes;
	}
	
	public function getCountriesCsvFile($force = false)
	{
		if (!static::$UrlCsvCountries)
			return false;

		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		// missing cache folder
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		// get cache file
		$countriesCsvFile = $cacheFolder . 'csv_countries.csv';

		// calculate cache time limit
		$map_cache_time_limit = (time() - $this->mapDataCacheLimit);

		if ($force || (!file_exists($countriesCsvFile)) || (filemtime($countriesCsvFile) < $map_cache_time_limit))
		{
			if ($force && file_exists($countriesCsvFile))
				unlink($countriesCsvFile);

			// Open file handler.
			$filePointer = fopen($countriesCsvFile, 'w+');
			
			// doanload file to location
			$this->downloadFile(static::$UrlCsvCountries, $filePointer);

		}
		return $countriesCsvFile;
	}
	
	public function getHotelsCsvFiles()
	{
		if (!static::$UrlCsvHotels)
			return false;

		// get cache folder
		$cacheFolder = $this->getResourcesDir();

		// missing cache folder
		if (!is_dir($cacheFolder))
			throw new \Exception('Missing cache folder!');

		// get cache file
		$hotelsCsvFile = $cacheFolder . 'csv_hotels.csv';

		// calculate cache time limit
		$map_cache_time_limit = (time() - $this->mapDataCacheLimit);

		if (!file_exists($hotelsCsvFile) || (filemtime($hotelsCsvFile) < $map_cache_time_limit))
		{
			// Open file handler.
			$filePointer = fopen($hotelsCsvFile, 'w+');
			
			// doanload file to location
			$this->downloadFile(static::$UrlCsvHotels, $filePointer);
		}
		return $hotelsCsvFile;
	}

	public function getPreparedDestinations(array $filter = null)
	{
		if (!$this->destinations)
		{
			if (!static::$UrlCsvCountries)
				return false;

			$countriesCsvFile = $this->getCountriesCsvFile();
			
			if (!$countriesCsvFile)
				return false;

			$header = null;
			$this->destinations = [];
			if (($handle = fopen($countriesCsvFile, 'r')) !== false)
			{
				while (($row = fgetcsv($handle, null, '|')) !== false)
				{
					// did not get the header
					if (!$header)
					{	
						// header is the first row of the csv
						foreach ($row ?: [] as $rk => $rv)
						{
							$row[$rk] = str_replace("\"", "", preg_replace('/\x{FEFF}/u', '', $rv));
						}
						// header is the first row of the csv
						$header = $row;

						// skip to the first row with values
						continue;
					}
					
					$rec_mix = array_combine($header, $row);
					
					if (isset($filter['CityId']) && ($filter['CityId'] != $rec_mix['CityId']))
						continue;

					// combine array of name and value to array
					$this->destinations[] = $rec_mix;
				}
			}
			
			return $this->destinations;
		}
		else
			return $this->destinations;
	}
	
	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "goglobal";
	}
	
	/**
	 * Download file from url pwith basic authentication cUrl
	 * 
	 * @param type $filePointer
	 * @throws Exception
	 */
	public function downloadFile($url, $filePointer)
	{
		// init curl
		$ch = q_curl_init_with_log();
		//$fp = fopen('goglobal_curl_errorlog.txt', 'w');
		// set options
		q_curl_setopt_with_log($ch, CURLOPT_URL, $url);
		q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, 1);
		q_curl_setopt_with_log($ch, CURLOPT_USERAGENT, 'curl/' . curl_version()['version']);
		q_curl_setopt_with_log($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		q_curl_setopt_with_log($ch, CURLOPT_USERPWD, static::$FileRequestUsername . ':' . static::$FileRequestPassword);

		//q_curl_setopt_with_log($ch, CURLOPT_VERBOSE, true);
		//q_curl_setopt_with_log($ch, CURLOPT_STDERR, $fp);

		if (
				defined("CURL_VERSION_HTTP2") &&
				(curl_version()["features"] & CURL_VERSION_HTTP2) !== 0
			)
		{
			q_curl_setopt_with_log($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		}
		q_curl_setopt_with_log($ch, CURLOPT_FILE, $filePointer);

		// exec curl
		$resp = q_curl_exec_with_log($ch);

		// check error
		if (curl_errno($ch))
			throw new Exception(curl_error($ch));

		$curlInfo = curl_getinfo($ch);

		// close curl
		curl_close($ch);

		// close file pointer
		fclose($filePointer);

		if ($curlInfo['http_code'] >= 400)
			throw new \Exception('Cannot download file from url: ' . $url);
	}

	// ------------------------- added --------------------------------------
	public function downloadFileNew($url, $filePointer)
	{
		// init curl
		$ch = q_curl_init_with_log();
		//$fp = fopen('goglobal_curl_errorlog.txt', 'w');
		// set options
		q_curl_setopt_with_log($ch, CURLOPT_URL, $url);
		q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, 1);
		q_curl_setopt_with_log($ch, CURLOPT_USERAGENT, 'curl/' . curl_version()['version']);
		q_curl_setopt_with_log($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		q_curl_setopt_with_log($ch, CURLOPT_USERPWD, $this->ApiUsername . ':' . $this->ApiPassword);

		//q_curl_setopt_with_log($ch, CURLOPT_VERBOSE, true);
		//q_curl_setopt_with_log($ch, CURLOPT_STDERR, $fp);

		if (
				defined("CURL_VERSION_HTTP2") &&
				(curl_version()["features"] & CURL_VERSION_HTTP2) !== 0
			)
		{
			q_curl_setopt_with_log($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		}
		q_curl_setopt_with_log($ch, CURLOPT_FILE, $filePointer);

		// exec curl
		$resp = q_curl_exec_with_log($ch);
		// check error
		if (curl_errno($ch))
			throw new \Exception(curl_error($ch));

		$curlInfo = curl_getinfo($ch);

		// close curl
		curl_close($ch);

		// close file pointer
		fclose($filePointer);

		if ($curlInfo['http_code'] >= 400)
			throw new \Exception('Cannot download file from url: ' . $url);
	}
	// --------------------------------------------------------------------------------

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
	public function request($method, $operation, $requestString = null, $useCache = false, $logData = false)
	{
		if (($doLogging = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]))))
			$logData = true;

		// exit if no api url
		if (!$this->ApiUrl)
			throw new \Exception('Api Url is missing!');

		// add params
		$params = [
			'trace' => 1,
		];
		
		$response = null;
		
		// if we are using cache check if the cache file exists
		if ($useCache)
		{
			// get cache file path
			$cache_file = $this->getSimpleCacheFile([$method, $requestString], ($this->ApiUrl__ ?: $this->ApiUrl), 'json');

			$cf_last_modified = ($f_exists = file_exists($cache_file)) ? filemtime($cache_file) : null;
			$cache_time_limit = time() - $this->cacheTimeLimit;

			// if exists - last modified
			if (($f_exists) && ($cf_last_modified >= $cache_time_limit))
				$response = json_decode(file_get_contents($cache_file));
		}
		
		$soapClient = null;
		if (!$response)
		{
			try {
				$streamContextParams = [
					'ssl' => [
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					]
				];

				if ($this->useAdditionalHeaders_new)
				{
					/*
					$streamContextParams['header'] = "Content-type: application/soap+xml; charset=utf-8\r\n"
						. "API-Operation: " . $operation . "\r\n"
						. "API-AgencyID: " . $this->ApiContext . "\r\n";
					*/
				}

				#qvardump('$streamContextParams', $streamContextParams);
				#q_die();

				$streamContext = stream_context_create($streamContextParams);
				$params['stream_context'] = $streamContext;

				// init soap client
				$soapClient = new GoGlobalSoap(($this->ApiUrl__ ?: $this->ApiUrl), $params);

				if ($this->useAdditionalHeaders_new)
				{
					/*
					$soapClient->sendAdditionalHeaders = true;
					$soapClient->apiOperation = $operation;
					$soapClient->agencyID = $this->ApiContext;
					*/

					$goglobalUseNamespace = 'http://www.goglobal.travel/';

					$headers = [];

					$contentTypeHeader = new \SoapHeader($goglobalUseNamespace, 'Content-Type', 
						new \SoapVar('application/soap+xml; charset=utf-8', XSD_STRING, null, null, null, $goglobalUseNamespace));
					$headers[] = $contentTypeHeader;

					// setup soap security header
					$apiOperationHeader = new \SoapHeader($goglobalUseNamespace, 'API-Operation', 
						new \SoapVar($operation, XSD_STRING, null, null, null, $goglobalUseNamespace));
					$headers[] = $apiOperationHeader;

					// setup soap security header
					$agencyIdHeader = new \SoapHeader($goglobalUseNamespace, 'API-AgencyID', 
						new \SoapVar(($this->ApiContext__ ?: $this->ApiContext), XSD_STRING, null, null, null, $goglobalUseNamespace));				
					$headers[] = $agencyIdHeader;

					// set security header on soap client
					$soapClient->__setSoapHeaders($headers);
				}

				// add request string
				$soapClient->currentRequestXML = $requestString;

				// setup needed params
				$soapClient->ReqApiContext = ($this->ApiContext__ ?: $this->ApiContext);
				$soapClient->ReqApiPassword = ($this->ApiPassword__ ?: $this->ApiPassword);
				$soapClient->ReqApiUsername = ($this->ApiUsername__ ?: $this->ApiUsername);
				$soapClient->ReqMethod = $method;
				$soapClient->ReqOperation = $operation;

				// get responses
				$response = $soapClient->{$method}();

				if ($logData)
					$this->logData($method, ["reqXML" => $requestString, "respXML" => $soapClient->_lastResp]);
				dump($soapClient->_lastReq);
				dump($soapClient->_lastResp);
				
				if ($useCache)
					file_put_contents($cache_file, json_encode($response));
			}
			catch(\Exception $ex)
			{
				$errData = [
					"url" => ($this->ApiUrl__ ?: $this->ApiUrl),
					"params" => $params
				];

				if ($soapClient)
				{
					$errData["reqXML"] = $requestString;
					$errData["respXML"] = $soapClient->_lastResp;
				}

				$this->logError($errData, $ex);
				if ($ex->getMessage() != 'SOAP-ERROR: Parsing WSDL: Couldn\'t load from \'http://xml.qa.goglobal.travel/XMLWebService.asmx?WSDL\' : failed to load external entity "http://xml.qa.goglobal.travel/XMLWebService.asmx?WSDL"')
					return null;
				else
					throw new \Exception($ex);
			}

			// reset request string
			$soapClient->currentRequestXML = null;
		}
		
		// return response
		return $response;
	}
	
	/**
	 * Get request Mode
	 * 
	 * @return type
	 */
	public function getRequestMode()
	{
		// return request mode soap
		return static::RequestModeSoap;
	}
}