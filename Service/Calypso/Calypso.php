<?php

namespace Integrations\Calypso;

use Integrations\Samo\CryptAes;
use IntegrationTraits\TourOperatorRecordTrait;
use Omi\TF\API_Wrap;
use Omi\TF\TOInterface_Util;
use SoapClient;

// require aes
//if (!class_exists('Crypt_AES'))
    //require_once(Omi_Travel_Fuse_Path . 'res/php/phpseclib/Crypt/AES.php');

/**
 * Calypso API
 */
class Calypso extends \Omi\TF\TOInterface
{
	use TOInterface_Util;
    use TourOperatorRecordTrait;

	protected $curl;

	public $passengersTypesTranslations = [
		"adult" => "ADL",
		"child" => "CHD",
		"infant" => "INF"
	];

	public $infantAgeBelow = 2;
	
	public $tourTypes = [];

	public $currencies = [];

	protected static $DatabaseName = "CALYPSO";
	
	protected static $IndividualDepartureCityID = 1;
	
	// param for individual search
	protected static $OnlyHotelPacket = 2;
	
	protected static $CurrencyName = "EURO";
	
	protected static $Currency_Code = [
		'prestige' => 'eur',
		'join_up' => 'euro'
	];
	
	protected static $IndividualHotelsTypeName = "Only Hotel";
	
	protected $cacheTimeLimit = 60 * 60 * 24;
	/**
	 * Departure country for integration
	 * 
	 * @var int[]
	 * 
	 * join_up
	 *		82 - Romania
	 */
	protected static $Departure_Country = [
		'join_up' => 82
	];
	
	/**
	 * Skip tours that have in name different strings
	 * 
	 * @var string[]
	 * 
	 * join up has same departures for residents of ukraine and moldova as the residents of romania.
	 */
	protected static $Skip_Tours_Having_In_Name = [
		'join_up' => [
			'ukraine', 
			'moldova'
		]
	];
	
	protected static $Offer_Availability_Codes = [
		'join_up' => [
			'YYYY'	=> 'yes',
			'Y'		=> 'yes',
			'F'		=> 'yes',
			'FFFF'	=> 'yes',
			'R'		=> 'ask',
			'RRRR'	=> 'ask',
			'RYYY'	=> 'ask',
			'RRYY'	=> 'ask',
			'RRRY'	=> 'ask',
			'RYRY'	=> 'ask',
			'RYRR'	=> 'ask',
			'RYYR'	=> 'ask',
			'YRRR'	=> 'ask',
			'YYRR'	=> 'ask',
			'YYYR'	=> 'ask',
			'YRYR'	=> 'ask',
			'YRRY'	=> 'ask',
			'YRYY'	=> 'ask',
		]
	];
	
	protected static $Skip_Towns_In_Search = [
		'join_up' => true
	];
	
	protected static $Get_Freights_Method = [
		'join_up' => 'FreightMonitor_FREIGHTSBYPACKET'
	];
	
	protected static $Hotel_State_From = [
		'prestige' => 'romania'
	];
	/**
	 * Nationality id used in booking process
	 * 
	 * @var type
	 */
	protected static $Nationality_Id = [
		'join_up' => 82,
		'prestige' => 80
	];
	
	/**
	 * Exotic Countries ids array
	 *
	 * @var int[]
	 * 
	 * 133 - Cuba
	 * 136 - Singapore
	 * 134 - Tanzania
	 */
	protected static $ExoticCountries = [133, 136, 134];

	public function api_testConnection(array $filter = null)
	{
		list($individualStateFromResp) = $this->request('SearchHotel_TOWNFROMS', [], [], true, true);
		
		$individualStateFromXML = json_decode(json_encode(simplexml_load_string($individualStateFromResp)));
		
		if ($individualStateFromXML && $individualStateFromXML->items && $individualStateFromXML->items->item)
			return true;
		
		list($holidayTownsFromResp) = $this->request('SearchTour_TOWNFROMS', [], [], true, true);
		$holidayTownsFromXML = json_decode(json_encode(simplexml_load_string($holidayTownsFromResp)));

		if ($holidayTownsFromXML && $holidayTownsFromXML->items && $holidayTownsFromXML->items->item)
			return true;

		$individualErr = null;
		if ($individualStateFromXML && $individualStateFromXML->Error)
		{
			echo $individualStateFromXML->Error . "<br/>";
			$individualErr = $individualStateFromXML->Error;
		}
		
		if ($holidayTownsFromXML && $holidayTownsFromXML->Error && ((!$individualErr) || ($individualErr != $holidayTownsFromXML->Error)))
		{
			echo $holidayTownsFromXML->Error;
		}
		
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
		
		// get departure state for individual
		$individualStatesFrom = $this->getIndividualStatesFrom();

		// get departure cities for holidays/charters
		$holidayTownsFrom = $this->getHolidayTownsFrom();
		
		$exoticTownFrom = $this->getExoticTownsFrom();
		
		$fromCountries = [];
		foreach ($holidayTownsFrom ?: [] as $townFrom)
			$fromCountries[$townFrom->Country->Id] = $townFrom->Country;

		// get individual destination countries
		$individualCountries = $this->getIndividualCountries($individualStatesFrom, $filter);

		// get holiday destination countries
		$holidayCountries = $this->getHolidayCountries($holidayTownsFrom, $filter);
		
		// get exotic countries
		$exoticCountries = $this->getExoticCountries($exoticTownFrom, $filter);

		// merge individual countries with holiday countries
		$countries = ($holidayCountries ?: []) + ($individualCountries ?: []) + ($fromCountries ?: []) + ($exoticCountries ?: []);

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'countries');
		// return array of countries
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

		// get departure state for individual
		$individualStatesFrom = $this->getIndividualStatesFrom();

		// get individual destination countries
		$individualCountries = $this->getIndividualCountries($individualStatesFrom, ['skip_report' => true]);

		// get departure cities for holidays/charters
		$holidayTownsFrom = $this->getHolidayTownsFrom();
		
		// get departure cities for holidays/charters
		$exoticTownsFrom = $this->getExoticTownsFrom();

		// get holiday destination countries
		$holidayCountries = $this->getHolidayCountries($holidayTownsFrom, ['skip_report' => true]);
		
		// get holiday destination countries
		$exoticCountries = $this->getExoticCountries($exoticTownsFrom, ['skip_report' => true]);

		// init regions
		$regions = [];

		// go through each individual country destination
		foreach ($individualCountries as $individualCountry)
		{
			// prepare params
			$params = [
				'STATEINC' => $individualCountry->Id, 
				'TOWNFROMINC' => static::$IndividualDepartureCityID
			];

			// request for individual cities
			list($individualCitiesResp) = $this->request('SearchHotel_TOWNS', $params, $filter);

			// decode xml
			$individualCitiesXML = json_decode(json_encode(simplexml_load_string($individualCitiesResp)));

			// go to next if no results
			if (!$individualCitiesXML->items->item)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Regions not provied by top for individual country: %s', [json_encode($individualCountry)]);
				continue;
			}

			// get cities array
			$individualCitiesData = is_array($individualCitiesXML->items->item) ? $individualCitiesXML->items->item : [$individualCitiesXML->items->item];

			\Omi\TF\TOInterface::markReportData($filter, 'Count regions: %s for individual country: %s', [q_count($individualCitiesData), json_encode($individualCountry)]);

			// go through each individuals cities
			foreach ($individualCitiesData as $individualCityData)
			{
				// region not set
				if (!$regions[$individualCityData->regionKey])
				{
					\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', 
						[$individualCityData->regionKey . ' ' . $individualCityData->region], 50);
					
					// new region object
					$region = new \stdClass();
					$region->Id = $individualCityData->regionKey;
					$region->Name = $individualCityData->region;
					$region->Code = $individualCityData->regionKey;
					$region->Country = $individualCountry;

					// add regions to array
					$regions[$region->Id] = $region;
				}
			}
		}

		// go through each holiday city from
		foreach ($holidayTownsFrom as $holidayTownFrom)
		{			
			foreach ($holidayCountries as $holidayCountry)
			{
				// prepare params
				$params = [
					'STATEINC' => $holidayCountry->Id, 
					'TOWNFROMINC' => $holidayTownFrom->Id
				];

				// request for individual cities
				list($holidayCitiesResp) = $this->request('SearchTour_TOWNS', $params, $filter);

				// decode xml
				$holidayCitiesXML = json_decode(json_encode(simplexml_load_string($holidayCitiesResp)));

				// go to next if no results
				if (!$holidayCitiesXML->items->item)
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Regions not provied by top for holiday country: %s', [json_encode($holidayCountry)]);
					continue;
				}

				// get cities array
				$holidayCitiesData = is_array($holidayCitiesXML->items->item) ? $holidayCitiesXML->items->item : [$holidayCitiesXML->items->item];

				\Omi\TF\TOInterface::markReportData($filter, 'Count regions: %s for holiday country: %s', [q_count($holidayCitiesData), json_encode($holidayCountry)]);

				// go through each individuals cities
				foreach ($holidayCitiesData as $holidayCityData)
				{
					if (!$regions[$holidayCityData->regionKey])
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', 
							[$holidayCityData->regionKey . ' ' . $holidayCityData->region], 50);

						// new region object
						$region = new \stdClass();
						$region->Id = $holidayCityData->regionKey;
						$region->Name = $holidayCityData->region;
						$region->Code = $holidayCityData->regionKey;
						$region->Country = $holidayCountry;

						// add regions to array
						$regions[$region->Id] = $region;
					}
				}
			}
		}

		// go through each holiday city from
		foreach ($exoticTownsFrom as $exoticTownFrom)
		{
			foreach ($exoticCountries as $exoticCountry)
			{
				// prepare params
				$params = [
					'STATEINC' => $exoticCountry->Id, 
					'TOWNFROMINC' => $exoticTownFrom->Id
				];
				
				// request for individual cities
				list($exoticCitiesResp) = $this->request('SearchExcursion_TOWNS', $params, $filter);
				
				// decode xml
				$exoticCitiesXML = json_decode(json_encode(simplexml_load_string($exoticCitiesResp)));
				
				// go to next if no results
				if (!$exoticCitiesXML->items->item)
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Regions not provied by top for exotic country: %s', [json_encode($exoticCountry)]);
					continue;
				}

				// get cities array
				$exoticCitiesData = is_array($exoticCitiesXML->items->item) ? $exoticCitiesXML->items->item : [$exoticCitiesXML->items->item];

				\Omi\TF\TOInterface::markReportData($filter, 'Count regions: %s for exotic country: %s', [q_count($exoticCitiesData), json_encode($exoticCountry)]);

				// go through each individuals cities
				foreach ($exoticCitiesData as $exoticCityData)
				{
					if (!$regions[$exoticCityData->regionKey])
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process region: %s', 
							[$exoticCityData->regionKey . ' ' . $exoticCityData->region], 50);

						// new region object
						$region = new \stdClass();
						$region->Id = $exoticCityData->regionKey;
						$region->Name = $exoticCityData->region;
						$region->Code = $exoticCityData->regionKey;
						$region->Country = $exoticCountry;

						// add regions to array
						$regions[$region->Id] = $region;
					}
				}
			}
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'regions');

		// return cities
		return [$regions];
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

		// get departure state for individual
		$individualStatesFrom = $this->getIndividualStatesFrom();

		// get individual destination countries
		$individualCountries = $this->getIndividualCountries($individualStatesFrom, ['skip_report' => true]);


		// get departure cities for holidays/charters
		$holidayTownsFrom = $this->getHolidayTownsFrom();

		// get holiday destination countries
		$holidayCountries = $this->getHolidayCountries($holidayTownsFrom, ['skip_report' => true]);

		// get departure cities for holidays/charters
		$exoticTownsFrom = $this->getExoticTownsFrom();

		// get holiday destination countries
		$exoticCountries = $this->getExoticCountries($exoticTownsFrom, ['skip_report' => true]);

		// init individual cities
		$individualCities = [];

		// init holidays cities
		$holidayCities = [];

		// init exotic cities
		$exoticCities = [];

		// init regions
		$regions = [];

		// go through each individual country destination
		foreach ($individualCountries as $individualCountry)
		{
			// prepare params
			$params = [
				'STATEINC' => $individualCountry->Id, 
				'TOWNFROMINC' => static::$IndividualDepartureCityID
			];

			// request for individual cities
			list($individualCitiesResp) = $this->request('SearchHotel_TOWNS', $params, $filter);

			// decode xml
			$individualCitiesXML = json_decode(json_encode(simplexml_load_string($individualCitiesResp)));

			// go to next if no results
			if (!$individualCitiesXML->items->item)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Cities not provied by top for individual country: %s', [json_encode($individualCountry)]);
				continue;
			}

			// get cities array
			$individualCitiesData = is_array($individualCitiesXML->items->item) ? $individualCitiesXML->items->item : [$individualCitiesXML->items->item];

			
			\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s for individual country: %s', [q_count($individualCitiesData), json_encode($individualCountry)]);

			// go through each individuals cities
			foreach ($individualCitiesData as $individualCityData)
			{	
				// region not set
				if (!$regions[$individualCityData->regionKey])
				{
					// new region object
					$region = new \stdClass();
					$region->Id = $individualCityData->regionKey;
					$region->Name = $individualCityData->region;
					$region->Code = $individualCityData->regionKey;
					$region->Country = $individualCountry;

					// add regions to array
					$regions[$region->Id] = $region;
				}

				\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', 
					[$individualCityData->id . ' ' . $individualCityData->name], 50);
				
				// new city object
				$individualCity = new \stdClass();
				$individualCity->Id = $individualCityData->id;
				$individualCity->Name = $individualCityData->name;
				$individualCity->Code = $individualCityData->id;
				$individualCity->County = $regions[$individualCityData->regionKey];
				$individualCity->Country = $individualCountry;
				
				// add city to array
				$individualCities[$individualCity->Id] = $individualCity;
			}
		}

		// go through each holiday city from
		foreach ($holidayTownsFrom as $holidayTownFrom)
		{
			foreach ($holidayCountries as $holidayCountry)
			{
				// prepare params
				$params = [
					'STATEINC' => $holidayCountry->Id, 
					'TOWNFROMINC' => $holidayTownFrom->Id
				];
				
				// request for individual cities
				list($holidayCitiesResp) = $this->request('SearchTour_TOWNS', $params, $filter);
				
				// decode xml
				$holidayCitiesXML = json_decode(json_encode(simplexml_load_string($holidayCitiesResp)));
				
				// go to next if no results
				if (!$holidayCitiesXML->items->item)
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Cities not provied by top for holiday country: %s', [json_encode($holidayCountry)]);
					continue;
				}

				// get cities array
				$holidayCitiesData = is_array($holidayCitiesXML->items->item) ? $holidayCitiesXML->items->item : [$holidayCitiesXML->items->item];

				\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s for holiday country: %s', [q_count($holidayCitiesData), json_encode($holidayCountry)]);
				
				// go through each individuals cities
				foreach ($holidayCitiesData as $holidayCityData)
				{
					if (!$regions[$holidayCityData->regionKey])
					{
						// new region object
						$region = new \stdClass();
						$region->Id = $holidayCityData->regionKey;
						$region->Name = $holidayCityData->region;
						$region->Code = $holidayCityData->regionKey;
						$region->Country = $holidayCountry;

						// add regions to array
						$regions[$region->Id] = $region;
					}

					\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', 
						[$holidayCityData->id . ' ' . $holidayCityData->name], 50);

					// new city object
					$holidayCity = new \stdClass();
					$holidayCity->Id = $holidayCityData->id;
					$holidayCity->Name = $holidayCityData->name;
					$holidayCity->Code = $holidayCityData->id;
					$holidayCity->County = $regions[$holidayCityData->regionKey];
					$holidayCity->Country = $holidayCountry;

					// add city to array
					$holidayCities[$holidayCity->Id] = $holidayCity;
				}
			}

			// add city to array
			$holidayCities[$holidayTownFrom->Id] = $holidayTownFrom;
		}
		
		// go through each holiday city from
		foreach ($exoticTownsFrom as $exoticTownFrom)
		{
			foreach ($exoticCountries as $exoticCountry)
			{
				// prepare params
				$params = [
					'STATEINC' => $exoticCountry->Id, 
					'TOWNFROMINC' => $exoticTownFrom->Id
				];
				
				// request for individual cities
				list($exoticCitiesResp) = $this->request('SearchExcursion_TOWNS', $params, $filter);
				
				// decode xml
				$exoticCitiesXML = json_decode(json_encode(simplexml_load_string($exoticCitiesResp)));
				
				// go to next if no results
				if (!$exoticCitiesXML->items->item)
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Cities not provied by top for exotic country: %s', [json_encode($exoticCountry)]);
					continue;
				}

				// get cities array
				$exoticCitiesData = is_array($exoticCitiesXML->items->item) ? $exoticCitiesXML->items->item : [$exoticCitiesXML->items->item];
				
				\Omi\TF\TOInterface::markReportData($filter, 'Count cities: %s for exotic country: %s', [q_count($exoticCitiesData), json_encode($exoticCountry)]);

				// go through each individuals cities
				foreach ($exoticCitiesData as $exoticCityData)
				{
					if (!$regions[$exoticCityData->regionKey])
					{
						// new region object
						$region = new \stdClass();
						$region->Id = $exoticCityData->regionKey;
						$region->Name = $exoticCityData->region;
						$region->Code = $exoticCityData->regionKey;
						$region->Country = $exoticCountry;

						// add regions to array
						$regions[$region->Id] = $region;
					}
					
					\Omi\TF\TOInterface::markReportData($filter, 'Process city: %s', 
						[$exoticCityData->id . ' ' . $exoticCityData->name], 50);

					// new city object
					$exoticCity = new \stdClass();
					$exoticCity->Id = $exoticCityData->id;
					$exoticCity->Name = $exoticCityData->name;
					$exoticCity->Code = $exoticCityData->id;
					$exoticCity->County = $regions[$exoticCityData->regionKey];
					$exoticCity->Country = $exoticCountry;
					
					// add city to array
					$exoticCities[$exoticCity->Id] = $exoticCity;
				}
			}

			// add city to array
			$exoticCities[$exoticTownFrom->Id] = $exoticTownFrom;
		}

		// merge cities
		$cities = $individualCities + $holidayCities + $exoticCities;

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
	public function api_getHotels(array $filter = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($filter, 'hotels');

		$forceCall = $filter["force"];

		// get individual destination countries
		$individualCountries = $this->getIndividualCountries($this->getIndividualStatesFrom(), ['skip_report' => true]);

		// get holiday destination countries
		$holidayCountries = $this->getHolidayCountries($this->getHolidayTownsFrom(), ['skip_report' => true]);

		// get holiday destination countries
		$exoticCountries = $this->getExoticCountries($this->getExoticTownsFrom(), ['skip_report' => true]);

		// init hotels array
		$hotels = [];
		
		$hotelsPos = 0;
		$individualHotelsPos = 0;
		$holidayHotelsPos = 0;
		$exoticHotelsPos = 0;
		
		$allIndividualHotels = [];
		$allHolidayHotels = [];
		$allExoticHotels = [];

		// get cities
		list($cities) = $this->api_getCities(['skip_report' => true]);

		#if (false)
		{
			foreach ($individualCountries ?: [] as $individualCountry)
			{
				foreach ($individualCountry->_statesFrom ?: [] as $stateFromId)
				{
					$params = [
						'STATEINC' => $individualCountry->Id, 
						'TOWNFROMINC' => $stateFromId
					];

					// request hotels
					list($hotelsResp) = $this->request('SearchHotel_HOTELS', $params, $filter, $forceCall);

					// decode xml
					$hotelsXML = json_decode(json_encode(simplexml_load_string($hotelsResp)));

					// go to next if no results
					if (!$hotelsXML->items->item)
					{
						\Omi\TF\TOInterface::markReportError($filter, 'Hotels not provied by top for individual country: %s and state id: %s', 
							[json_encode($individualCountry), $stateFromId]);
						continue;
					}

					// get hotels data array
					$hotelsData = is_array($hotelsXML->items->item) ? $hotelsXML->items->item : [$hotelsXML->items->item];

					\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s for individual country: %s and state id: %s', [
						q_count($hotelsData), json_encode($individualCountry), $stateFromId]);

					foreach ($hotelsData ?: [] as $hotelData)
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', 
							[$hotelData->id . ' ' . $hotelData->name], 50);
						
						if (!$hotelData->id)
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have id: %s', [json_encode($hotelData)]);
							#echo "<div style='color: red;'>Hotel data has no id</div>";
							continue;
						}

						if (isset($hotels[$hotelData->id]))
						{
							#echo "<div style='color: red;'>Hotel [{$hotelData->id}] already processed</div>";
							continue;
						}

						$allIndividualHotels[$hotelData->id] = $hotelData;

						#echo "<div style='color: blue;'>" . (++$hotelsPos) . ". [" . (++$individualHotelsPos) . "] {$hotelData->name}</div>";

						// new hotel object
						$hotel = new \stdClass();
						
						// if has no code go to the next (skip current)
						if (!($hotel->Id = $hotelData->id))
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have id: %s', [json_encode($hotelData)]);
							continue;
						}

						// also if the hotel does not have a name we will skip it
						if (!($hotel->Name = $hotelData->name))
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have name: %s', [json_encode($hotelData)]);
							continue;
						}

						$hotelStars = str_replace(['*', '+'], '', $hotelData->star);
						if (in_array((string)$hotelStars, ['1', '2', '3', '4', '5']))
							$hotel->Stars = $hotelStars;
						else
							$hotel->Stars = 0;

						// new address object - region, city, county, country, street, etc
						$hotel->Address = new \stdClass();

						// set country on hotel address
						$hotel->Address->Country = $individualCountry;

						// set region on hotel address
						$hotel->Address->County = $cities[$hotelData->townKey]->County;

						// set city on hotel address
						$hotel->Address->City = $cities[$hotelData->townKey];

						// $hotel->Details = $this->api_getHotelDetails(['HotelId' => $hotel->Id]);

						// add hotel to array
						$hotels[$hotel->Id] = $hotel;
					}
				}
			}
		}

		#if (false)
		{
			foreach ($holidayCountries ?: [] as $holidayCountry)
			{
				foreach ($holidayCountry->_townsFromInc ?: [] as $townFromIncId)
				{
					// set params
					$params = [
						'STATEINC' => $holidayCountry->Id, 
						'TOWNFROMINC' => $townFromIncId
					];

					// request hotels
					list($hotelsResp) = $this->request('SearchTour_HOTELS', $params, $filter, $forceCall);

					// decode xml
					$hotelsXML = json_decode(json_encode(simplexml_load_string($hotelsResp)));

					// go to next if no results
					if (!$hotelsXML->items->item)
					{
						\Omi\TF\TOInterface::markReportError($filter, 'Hotels not provied by top for holiday country: %s and state id: %s', 
							[json_encode($holidayCountry), $townFromIncId]);
						continue;
					}

					// get hotels data array
					$hotelsData = is_array($hotelsXML->items->item) ? $hotelsXML->items->item : [$hotelsXML->items->item];

					\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s for holiday country: %s and state id: %s', [
						q_count($hotelsData), json_encode($holidayCountry), $stateFromId]);

					foreach ($hotelsData ?: [] as $hotelData)
					{
						\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', 
							[$hotelData->id . ' ' . $hotelData->name], 50);

						if (!$hotelData->id)
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have id: %s', [json_encode($hotelData)]);
							#echo "<div style='color: red;'>Hotel data has no id</div>";
							continue;
						}

						$allHolidayHotels[$hotelData->id] = $hotelData;
						if (isset($hotels[$hotelData->id]))
						{
							#echo "<div style='color: red;'>Hotel [{$hotelData->id}] already processed</div>";
							continue;
						}

						#echo "<div style='color: blue;'>" . (++$hotelsPos) . ". [" . (++$holidayHotelsPos) . "] {$hotelData->name}</div>";

						// new hotel object
						$hotel = new \stdClass();

						// if has no code go to the next (skip current)
						if (!($hotel->Id = $hotelData->id))
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have id: %s', [json_encode($hotelData)]);
							continue;
						}

						// also if the hotel does not have a name we will skip it
						if (!($hotel->Name = $hotelData->name))
						{
							\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have name: %s', [json_encode($hotelData)]);
							continue;
						}

						$hotelStars = str_replace(['*', '+'], '', $hotelData->star);
						if (in_array((string)$hotelStars, ['1', '2', '3', '4', '5']))
							$hotel->Stars = $hotelStars;
						else
							$hotel->Stars = 0;

						// new address object - region, city, county, country, street, etc
						$hotel->Address = new \stdClass();

						// set country on hotel address
						$hotel->Address->Country = $holidayCountry;

						// set region on hotel address
						$hotel->Address->County = $cities[$hotelData->townKey]->County;

						// set city on hotel address
						$hotel->Address->City = $cities[$hotelData->townKey];

						// add hotel to array
						$hotels[$hotel->Id] = $hotel;
					}
				}
			}
		}

		#qvardump("\$allHolidayHotels", $allHolidayHotels);
		#echo "<hr/>";

		foreach ($exoticCountries ?: [] as $exoticCountry)
		{
			foreach ($exoticCountry->_townsFromInc ?: [] as $townFromIncId)
			{
				// set params
				$params = [
					'STATEINC' => $exoticCountry->Id, 
					'TOWNFROMINC' => $townFromIncId
				];

				// request hotels
				list($hotelsResp) = $this->request('SearchExcursion_HOTELS', $params, $filter, $forceCall);

				// decode xml
				$hotelsXML = json_decode(json_encode(simplexml_load_string($hotelsResp)));

				// go to next if no results
				if (!$hotelsXML->items->item)
				{
					\Omi\TF\TOInterface::markReportError($filter, 'Hotels not provied by top for exotic country: %s and state id: %s', 
						[json_encode($exoticCountry), $townFromIncId]);
					continue;
				}

				// get hotels data array
				$hotelsData = is_array($hotelsXML->items->item) ? $hotelsXML->items->item : [$hotelsXML->items->item];
				
				\Omi\TF\TOInterface::markReportData($filter, 'Count hotels: %s for exotic country: %s and state id: %s', [
						q_count($hotelsData), json_encode($hotelsData), $stateFromId]);

				foreach ($hotelsData ?: [] as $hotelData)
				{
					\Omi\TF\TOInterface::markReportData($filter, 'Process hotel: %s', 
						[$hotelData->id . ' ' . $hotelData->name], 50);

					if (!$hotelData->id)
					{
						\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have id: %s', [json_encode($hotelData)]);
						#echo "<div style='color: red;'>Hotel data has no id</div>";
						continue;
					}

					$allExoticHotels[$hotelData->id] = $hotelData;

					if (isset($hotels[$hotelData->id]))
					{
						
						#echo "<div style='color: red;'>Hotel [{$hotelData->id}] already processed</div>";
						continue;
					}

					#echo "<div style='color: blue;'>" . (++$hotelsPos) . ". [" . (++$exoticHotelsPos) . "] {$hotelData->name}</div>";

					// new hotel object
					$hotel = new \stdClass();

					// if has no code go to the next (skip current)
					if (!($hotel->Id = $hotelData->id))
					{
						\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have id: %s', [json_encode($hotelData)]);
						continue;
					}

					// also if the hotel does not have a name we will skip it
					if (!($hotel->Name = $hotelData->name))
					{
						\Omi\TF\TOInterface::markReportError($filter, 'Hotel data does not have name: %s', [json_encode($hotelData)]);
						continue;
					}

					$hotelStars = str_replace(['*', '+'], '', $hotelData->star);
					if (in_array((string)$hotelStars, ['1', '2', '3', '4', '5']))
						$hotel->Stars = $hotelStars;
					else
						$hotel->Stars = 0;

					// new address object - region, city, county, country, street, etc
					$hotel->Address = new \stdClass();

					// set country on hotel address
					$hotel->Address->Country = $holidayCountry;

					// set region on hotel address
					$hotel->Address->County = $cities[$hotelData->townKey]->County;

					// set city on hotel address
					$hotel->Address->City = $cities[$hotelData->townKey];

					// add hotel to array
					$hotels[$hotel->Id] = $hotel;
				}
			}
		}

		\Omi\TF\TOInterface::markReportEndpoint($filter, 'hotels');
		
		// return hotels
		return $hotels;
	}
	
	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public function api_getHotelDetails(array $filter = null)
	{
		if (!($hotelId = $filter["HotelId"] ?: $filter["hotelId"]))
			throw new \Exception("Hotel Id must be specified!");

		$forceCall = $filter["force"];

		// set params
		$params = ['HOTEL' => $hotelId];

		// request hotels
		list($hotelResp) = $this->request('SearchTour_CONTENT', $params, $filter, $forceCall);

		// decode xml
		$hotelXML = json_decode(json_encode(simplexml_load_string($hotelResp)));

		if ((!$hotelXML->samo_id) || (!is_scalar($hotelXML->samo_id)))
		{
			#echo "<div style='color: red;'>Hotel [{$filter["HotelId"]}] has no samo_id property</div>";
			return false;
		}

		$hasContent = false;
		$hotelDetails = new \stdClass();
		$hotelDetails->Id = (string)$hotelXML->samo_id;

		$hotelDetails->Content = new \stdClass();
		$hotelDetails->Content->Content = '';
		
		if (is_scalar($hotelXML->description))
			$hotelDetails->Content->ShortDescription = strip_tags($hotelXML->description);

		if ($hotelXML->about && $hotelXML->about->about && (is_scalar($hotelXML->about->about->text)))
		{
			$aboutDescr = trim(strip_tags($hotelXML->about->about->text));
			if ($aboutDescr)
			{
				$hotelDetails->Content->Content .= '<p>' . $aboutDescr . '</p>';
				$hasContent = true;
			}
		}
		
		if ($hotelXML->beach && $hotelXML->beach->beach && (is_scalar($hotelXML->beach->beach->info)))
		{
			$beachDescr = trim(strip_tags($hotelXML->beach->beach->info));
			if ($beachDescr)
			{
				$hotelDetails->Content->Content .= '<h2>Plaja</h2>';
				$hotelDetails->Content->Content .= '<p>' . $beachDescr . '</p>';
				$hasContent = true;
			}
			
			if ($hotelXML->beach->beach->item && $hotelXML->beach->beach->item->item && ($hotelXML->beach->beach->item->item != new \stdClass()))
			{
				if (is_array($hotelXML->beach->beach->item->item))
				{
					$beachUl = '<ul>';
					foreach ($hotelXML->beach->beach->item->item as $beachItem)
						$beachUl .= '<li>' . $beachItem->name . '</li>';	
					$beachUl .= '</ul>';
					$hotelDetails->Content->Content .= '<div>' . $beachUl . '</div>';
					$hasContent = true;
				}
			}
		}
		
		if ($hotelXML->food && $hotelXML->food->food && ($hotelXML->food->food->info != new \stdClass()))
		{
			$foodDescr = trim(strip_tags($hotelXML->food->food->info));
			
			if ($foodDescr)
			{
				$hotelDetails->Content->Content .= '<h2>Mancare</h2>';
				$hotelDetails->Content->Content .= '<p>' . $foodDescr . '</p>';
				$hasContent = true;
			}

			if ($hotelXML->food->food->item && $hotelXML->food->food->item->item && ($hotelXML->food->food->item->item != new \stdClass()))
			{
				if (is_array($hotelXML->food->food->item->item))
				{
					$foodUl = '<ul>';
					foreach ($hotelXML->food->food->item->item as $foodItem)
					{
						if (is_scalar($foodItem->name))
							$foodUl .= '<li>' . $foodItem->name . '</li>';
					}
					
					$foodUl .= '</ul>';
					$hotelDetails->Content->Content .= '<div>' . $foodUl . '</div>';
					$hasContent = true;
				}
			}
		}
		
		if ($hotelXML->gallery && $hotelXML->gallery->gallery)
		{
			$prestigeUrl = 'https://prestige.ro';
			$hotelDetails->Content->ImageGallery = new \stdClass();
			
			foreach ($hotelXML->gallery->gallery as $galleryItem)
			{
				$photo_obj = new \stdClass();
				$photo_obj->RemoteUrl = $prestigeUrl . (string)$galleryItem;
				$hotelDetails->Content->ImageGallery->Items[] = $photo_obj;
				$hasContent = true;
			}
		}
		
		if ($hotelXML->map && $hotelXML->map->map && ($hotelXML->map->map->longitude || $hotelXML->map->map->latitude))
		{
			$hotelDetails->Address = new \stdClass();
			$hotelDetails->Address->Longitude = (string)$hotelXML->map->map->longitude;
			$hotelDetails->Address->Latitude = (string)$hotelXML->map->map->latitude;
			
			if ($hotelXML->map->map->address != new \stdClass())
				$hotelDetails->Address->Details = (string)$hotelXML->map->map->address;
			$hasContent = true;
		}
		
		if ($hotelXML->service && $hotelXML->service->service && ($hotelXML->service->service != new \stdClass()))
		{
			if (is_array($hotelXML->service->service))
			{
				$hotelDetails->Facilities = [];
				foreach ($hotelXML->service->service as $service)
				{
					if ($service->item && $service->item->item && ($service->item->item != new \stdClass()))
					{
						if (is_array($service->item->item))
						{
							foreach ($service->item->item as $serviceItem)
							{
								$facility = new \stdClass();
								$facility->Name = $serviceItem->name;
								
								$hotelDetails->Facilities[$facility->Name] = $facility->Name;
							}
						}
					}
				}
				$hasContent = true;
			}
		}
		
		if ($hotelXML->site && $hotelXML->site->site && $hotelXML->site->site->site)
		{
			$hotelDetails->WebAddress = (string)$hotelXML->site->site->site;
			$hasContent = true;
		}

		// hotel
		#echo "<div style='color: " . ($hasContent ? "blue" : "red") . ";'>Hotel [{$filter["HotelId"]}] " . ($hasContent ? " has content" : "does not have content") . "</div>";

		// return hotel details
		return $hotelDetails;
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
		
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers(array $filter = null)
	{
		$serviceType = $this->checkApiOffersFilter($filter);
		$ret = null;
		$rawRequests = [];
		
		switch($serviceType)
		{
			case 'charter' : 
			{
				list($ret, $ex, $rawRequests) =  $this->getCharterOffers($filter);
				break;
			}
			case 'hotel' :
			case 'individual' :
			{
				$ret = $this->getIndividualOffers($filter);
				break;
			}
		}
				
		return [$ret, $ex, false, $rawRequests];
	}

	private function checkApiOffersFilter(&$filter)
	{
		$serviceType = ($filter && $filter['serviceTypes']) ? q_reset($filter['serviceTypes']) : null;
		if (!$serviceType)
			throw new \Exception("Service type is mandatory!");

		// check in is mandatory
		if (!$filter["checkIn"])
			throw new \Exception("CheckIn date is mandatory!");

		// number of days / nights are mandatory
		if (!$filter["days"] || (!is_numeric($filter["days"])))
			throw new \Exception("Duration is mandatory!");

		// rooms are mandatory
		if (!$filter["rooms"])
			throw new \Exception("Rooms are mandatory");

		// adults are mandatory
		if (isset($filter["rooms"]["adults"]))
			$filter["rooms"] = [$filter["rooms"]];

		// number of adults are mandatory
		// maximum number of children allowed is 3
		foreach ($filter["rooms"] ?: [] as $room)
		{
			if (!isset($room["adults"]))
				throw new \Exception("Adults count is mandatory!");
			if ($room["children"] && ($room["children"] > 3))
				throw new \Exception("Child count must be under 4!");
		}

		return $serviceType;
	}
	
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
	
	public function api_getOfferCancelFeesPaymentsAvailabilityAndPrice(array $filter = null)
	{

	}

	/**
	 * 
	 */
	public function api_getOfferExtraServices(array $filter = null)
	{
		
	}

	public function getChartersAvailabilityDates(array $filter = null)
	{
		$transports = [];

		// get departure cities for holidays/charters
		$exoticTownsFrom = $this->getExoticTownsFrom();

		foreach ($exoticTownsFrom as $exoticTownFrom)
		{
			// prepare params
			$params = ['TOWNFROMINC' => $exoticTownFrom->Id];

			// get destination countries for holiday
			list($exoticCountriesResp) = $this->request('SearchExcursion_STATES', $params, $filter, true);

			// decode xml
			$exoticCountriesXML = json_decode(json_encode(simplexml_load_string($exoticCountriesResp)));

			// return false if no results found
			if (!$exoticCountriesXML->items->item)
				continue;

			// get countries array
			$exoticCountriesData = is_array($exoticCountriesXML->items->item) ? $exoticCountriesXML->items->item : [$exoticCountriesXML->items->item];
			
			// go through each destination country
			foreach ($exoticCountriesData as $exoticCountry)
			{
				// new country object
				$country = new \stdClass();
				$country->Id = $exoticCountry->id;
				$country->Name = $exoticCountry->name;

				$tourParams = [
					'STATEINC' => $exoticCountry->id, 
					'TOWNFROMINC' => $exoticTownFrom->Id
				];
				
				// request for individual cities
				list($toursResp) = $this->request('SearchExcursion_Tours', $tourParams, $filter, true);
				
				// decode xml
				$toursXML = json_decode(json_encode(simplexml_load_string($toursResp)));

				// continu if no tours
				if (!$toursXML->items->item)
					continue;
				
				// get tours array
				$toursData = is_array($toursXML->items->item) ? $toursXML->items->item : [$toursXML->items->item];

				foreach ($toursData as $tour)
				{
					// prepare params
					$params2 = [
						'STATEINC' => $exoticCountry->id,
						'TOWNFROMINC' => $exoticTownFrom->Id,
						'TOURINC' => $tour->id
					];

					// request for individual cities
					list($exoticCitiesResp) = $this->request('SearchExcursion_TOWNS', $params2, $filter, true);

					// decode xml
					$exoticCitiesXML = json_decode(json_encode(simplexml_load_string($exoticCitiesResp)));

					// return false if no results found
					if (!$exoticCitiesXML->items->item)
						continue;

					// get countries array
					$exoticCitiesData = is_array($exoticCitiesXML->items->item) ? $exoticCitiesXML->items->item : [$exoticCitiesXML->items->item];

					$params3 = [
						'STATEINC' => $exoticCountry->id, 
						'TOWNFROMINC' => $exoticTownFrom->Id,
						'TOURINC' => $tour->id
					];

					// request for individual cities
					list($exoticCheckinsResp) = $this->request('SearchExcursion_CHECKIN', $params3, $filter, true);

					// decode xml
					$exoticCheckinsXML = json_decode(json_encode(simplexml_load_string($exoticCheckinsResp)));

					// return false if no results found
					if (!$exoticCheckinsXML->items->item)
						continue;

					// get countries array
					$exoticCheckinsData = is_array($exoticCheckinsXML->items->item) ? $exoticCheckinsXML->items->item : [$exoticCheckinsXML->items->item];


					$dates = [];
					foreach ($exoticCheckinsData as $exoticCheckin)
					{
						$params4 = [
							'STATEINC' => $exoticCountry->id, 
							'TOWNFROMINC' => $exoticTownFrom->Id,
							'CHECKIN_BEG' => $exoticCheckin->checkin,
							'TOURINC' => $tour->id
						];

						// request for individual cities
						list($exoticNightsResp) = $this->request('SearchExcursion_NIGHTS', $params4, $filter, true);

						// decode xml
						$exoticNightsXML = json_decode(json_encode(simplexml_load_string($exoticNightsResp)));

						// get countries array
						$exoticNightsData = is_array($exoticNightsXML->nights->night) ? $exoticNightsXML->nights->night : [$exoticNightsXML->nights->night];

						// set dates
						$dates[] = [
							"checkin" => $exoticCheckin,
							"nights" => $exoticNightsData
						];						
					}

					foreach ($exoticCitiesData as $exoticCity)
					{
						$city = new \stdClass();
						$city->Id = $exoticCity->id;
						$city->Name = $exoticCity->name;
						$city->CountryId = $exoticCountry->id;

						$transportType = "plane";

						$exoticCity->Id = $city->Id;
						$transport = new \stdClass();
						$transport->TransportType = $transportType;
						$transport->From = new \stdClass();
						$transport->From->City = $exoticTownFrom;
						$transport->To = new \stdClass();
						$transport->To->City = $city;
						$transport->Dates = [];

						foreach ($dates ?: [] as $checkinData)
						{
							$checkin = $checkinData["checkin"]->checkin;

							$dateObj = new \stdClass();
							$dateObj->Date = date('Y-m-d', strtotime($checkin));
							$dateObj->Nights = [];

							foreach ($checkinData["nights"] ?: [] as $nightsData)
							{
								$nightsObj = new \stdClass();
								$nightsObj->Nights = $nightsData;

								$dateObj->Nights[] = $nightsObj;
							}

							$transport->Dates[] = $dateObj;

							// set transport object id
							$transport->Id = $transportType . "~city|" . $exoticTownFrom->Id . "~city|" . $exoticCity->Id;

							// index transports by id
							$transports[$transport->Id] = $transport;
						}

					}
				}
			}
		}
		
		// get departure cities for holidays/charters
		$holidayTownsFrom = $this->getHolidayTownsFrom();

		// go through each departure city
		foreach ($holidayTownsFrom as $townFrom)
		{
			// prepare params
			$params = ['TOWNFROMINC' => $townFrom->Id];

			// get destination countries for holiday
			list($holidayCountriesResp) = $this->request('SearchTour_STATES', $params, $filter, true);

			// decode xml
			$holidayCountriesXML = json_decode(json_encode(simplexml_load_string($holidayCountriesResp)));

			// return false if no results found
			if (!$holidayCountriesXML->items->item)
				continue;

			// get countries array
			$holidayCountriesData = is_array($holidayCountriesXML->items->item) ? $holidayCountriesXML->items->item : [$holidayCountriesXML->items->item];

			// go through each destination country
			foreach ($holidayCountriesData as $holidayCountry)
			{
				// new country object
				$country = new \stdClass();
				$country->Id = $holidayCountry->id;
				$country->Name = $holidayCountry->name;

				$tourParams = [
					'STATEINC' => $holidayCountry->id, 
					'TOWNFROMINC' => $townFrom->Id
				];

				// request for individual cities
				list($toursResp) = $this->request('SearchTour_Tours', $tourParams, $filter, true);

				// decode xml
				$toursXML = json_decode(json_encode(simplexml_load_string($toursResp)));

				// continu if no tours
				if (!$toursXML->items->item)
					continue;

				// get tours array
				$toursData = is_array($toursXML->items->item) ? $toursXML->items->item : [$toursXML->items->item];

				foreach ($toursData as $tour)
				{
					// prepare params
					$params2 = [
						'STATEINC' => $holidayCountry->id,
						'TOWNFROMINC' => $townFrom->Id,
						'TOURINC' => $tour->id
					];

					// request for individual cities
					list($holidayCitiesResp) = $this->request('SearchTour_TOWNS', $params2, $filter, true);

					// decode xml
					$holidayCitiesXML = json_decode(json_encode(simplexml_load_string($holidayCitiesResp)));

					// return false if no results found
					if (!$holidayCitiesXML->items->item)
						continue;

					// get countries array
					$holidayCitiesData = is_array($holidayCitiesXML->items->item) ? $holidayCitiesXML->items->item : [$holidayCitiesXML->items->item];

					$params3 = [
						'STATEINC' => $holidayCountry->id, 
						'TOWNFROMINC' => $townFrom->Id,
						'TOURINC' => $tour->id
					];

					// request for individual cities
					list($holidayCheckinsResp) = $this->request('SearchTour_CHECKIN', $params3, $filter, true);

					// decode xml
					$holidayCheckinsXML = json_decode(json_encode(simplexml_load_string($holidayCheckinsResp)));

					// return false if no results found
					if (!$holidayCheckinsXML->items->item)
						continue;

					// get countries array
					$holidayCheckinsData = is_array($holidayCheckinsXML->items->item) ? $holidayCheckinsXML->items->item : [$holidayCheckinsXML->items->item];


					$dates = [];
					foreach ($holidayCheckinsData as $holidayCheckin)
					{
						$params4 = [
							'STATEINC' => $holidayCountry->id, 
							'TOWNFROMINC' => $townFrom->Id,
							'CHECKIN_BEG' => $holidayCheckin->checkin,
							'CHECKIN_END' => $holidayCheckin->checkin,
							'TOURINC' => $tour->id
						];

						// request for individual cities
						list($holidayNightsResp) = $this->request('SearchTour_NIGHTS', $params4, $filter, true);

						// decode xml
						$holidayNightsXML = json_decode(json_encode(simplexml_load_string($holidayNightsResp)));

						// get countries array
						$holidayNightsData = is_array($holidayNightsXML->nights->night) ? $holidayNightsXML->nights->night : [$holidayNightsXML->nights->night];

						// set dates
						$dates[] = [
							"checkin" => $holidayCheckin,
							"nights" => $holidayNightsData
						];						
					}

					foreach ($holidayCitiesData as $holidayCity)
					{
						$city = new \stdClass();
						$city->Id = $holidayCity->id;
						$city->Name = $holidayCity->name;
						$holidayCity->Id = $city->Id;

						$transportType = "plane";

						$id = $transportType . "~city|" . $townFrom->Id . "~city|" . $holidayCity->Id;

						$transport = null;

						if (!isset($trana2swqsports[$id])) {
							// create new

							$transport = new \stdClass();
							$transport->Id = $id;
							$transport->TransportType = $transportType;
							$transport->From = new \stdClass();
							$transport->From->City = $townFrom;
							$transport->To = new \stdClass();
							$transport->To->City = $city;
	
							foreach ($dates ?: [] as $checkinData)
							{
								$checkin = $checkinData["checkin"]->checkin;
	
								$dateObj = new \stdClass();
								$dateObj->Date = date('Y-m-d', strtotime($checkin));
								$dateObj->Nights = [];
	
								foreach ($checkinData["nights"] ?: [] as $nightsData)
								{
									$nightsObj = new \stdClass();
									$nightsObj->Nights = $nightsData;
	
									$dateObj->Nights[$nightsObj->Nights] = $nightsObj;
								}
	
								$transport->Dates[$dateObj->Date] = $dateObj;
							}
						} else {
							// if transport id already exists add dates and nights
							$transport = $transports[$id];

							foreach ($dates ?: [] as $checkinData)
							{
								$checkin = $checkinData["checkin"]->checkin;

								// if date does not exist, create new
								if (!isset($transport->Dates[$checkin])) {
									$dateObj = new \stdClass();
									$dateObj->Date = date('Y-m-d', strtotime($checkin));
									
								} else {
									// else get date
									$dateObj = $transport->Dates[$checkin];
								}

								// add nights to new date or existing date
								foreach ($checkinData["nights"] ?: [] as $nightsData)
								{
									$nightsObj = new \stdClass();
									$nightsObj->Nights = $nightsData;
									$dateObj->Nights[$nightsObj->Nights] = $nightsObj;
								}
								$transport->Dates[$dateObj->Date] = $dateObj;
							}
						}
						$transports[$transport->Id] = $transport;
					}
				}
			}
		}
		
		return $transports;
	}

	/**
	 * 
	 */
	public function api_getAvailabilityDates(array $filter = null)
	{		
		$ret = null;
		if ($filter['type'] == 'charter')
		{
			$ret = $this->getChartersAvailabilityDates($filter);
		}
		
		return [$ret];
	}
	
	public function api_prepareBooking(array $filter = null)
	{
		
	}

	/**
	 * @param array $data
	 */
	public function api_doBooking(array $data = null)
	{		
		// get offer
		$offer = q_reset($data['Items']);

		// get passengers
		$passengers = $data['Passengers'];
		if (!$passengers || (q_count($passengers) == 0))
			throw new \Exception('Missing Passengers');
		
		if (!$offer['Offer_Claim'])
			throw new \Exception('Claim is missing from the offer!');
		
		// get packet for agent :: prepare for booking
		$xmlPrepareForBooking = $this->prepareForBooking($offer['Offer_Claim'], $data);

		if (!($this->xmlIsValid($xmlPrepareForBooking)))
			throw new \Exception("Eroare tur operator: comanda nu poate fi procesata");

		$xmlPrepareForBooking_Decoded = $this->simpleXML2Array(simplexml_load_string($xmlPrepareForBooking));
		if ((!$xmlPrepareForBooking_Decoded) || (!isset($xmlPrepareForBooking_Decoded["claim"]["claimDocument"]["@attrs"]["currency"])))
		{
			$err = (isset($xmlPrepareForBooking_Decoded["faultstring"]) && is_scalar($xmlPrepareForBooking_Decoded["faultstring"])) ? 
				$xmlPrepareForBooking_Decoded["faultstring"] : "comanda nu poate fi procesata";
			throw new \Exception("Eroare tur operator: " . $err);
		}

		// get bron price for agent :: recalculate price
		// $xmlRecalculatePrice = $this->recalculatePrice($xmlPrepareForBooking, $passengers);

		# get book reservation xml
		# $xmlBookReservation = $this->bookReservation($xmlPrepareForBooking, $passengers);
		
		# get xml confirm reservation
		$xmlConfirmReservation = $this->confirmReservation($xmlPrepareForBooking, $passengers, $data);
		
		if (!($this->xmlIsValid($xmlConfirmReservation)))
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!"
				. "\nRaspuns tur operator: " . $xmlConfirmReservation);
			$this->logError(["confirmReservationError" => true, "\$xmlConfirmReservation" => $xmlConfirmReservation], $ex);
			throw $ex;
		}

		$xmlConfirmReservation_Decoded = $this->simpleXML2Array(simplexml_load_string($xmlConfirmReservation));
		if ((!$xmlConfirmReservation_Decoded) || (!isset($xmlConfirmReservation_Decoded["claim"]["claimDocument"]["@attrs"]["providerNumber"])))
		{
			$err = (isset($xmlConfirmReservation_Decoded["faultstring"]) && is_scalar($xmlConfirmReservation_Decoded["faultstring"])) ? 
				"Eroare tur operator: " . $xmlConfirmReservation_Decoded["faultstring"] : "Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!"
				. "\nRaspuns tur operator: " . $xmlConfirmReservation;
			throw new \Exception($err);
		}
		$order = new \stdClass();
		$order->Id = $xmlConfirmReservation_Decoded["claim"]["claimDocument"]["@attrs"]["providerNumber"];
		return [$order, $xmlConfirmReservation];
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
	
	public function api_getCarriers(array $filter = null)
	{
		
	}

	public function api_getAirports(array $filter = null)
	{
		
	}

	public function api_getRoutes(array $filter = null)
	{
		
	}

	/**
	 * Get departure countries for individual offers.
	 * 
	 * @return boolean|\stdClass
	 */
	public function getIndividualStatesFrom()
	{
		// request town/states from for individual
		list($individualStateFromResp) = $this->request('SearchHotel_TOWNFROMS');
		
		// decode xml
		$individualStateFromXML = json_decode(json_encode(simplexml_load_string($individualStateFromResp)));
		
		if (!$individualStateFromXML->items->item)
			return false;
		
		// make result array
		$individualStateFromData = is_array($individualStateFromXML->items->item) ? $individualStateFromXML->items->item : [$individualStateFromXML->items->item];
		
		// init countries
		$states = [];
		
		// go through each departure destination
		foreach ($individualStateFromData as $individualState)
		{
			// new state object
			$state = new \stdClass();
			$state->Id = $individualState->stateFromKey;
			$state->Name = $individualState->stateFromName;
			
			// add state to array
			$states[$state->Id] = $state;
		}
		
		// return states
		return $states;
	}
	
	/**
	 * Get departure cities.
	 * 
	 * @return boolean|\stdClass
	 */
	public function getHolidayTownsFrom()
	{
		// get departure cities for holidays/charters
		list($holidayTownsFromResp) = $this->request('SearchTour_TOWNFROMS');
		
		// decode xml
		$holidayTownsFromXML = json_decode(json_encode(simplexml_load_string($holidayTownsFromResp)));
		
		// no results found
		if (!$holidayTownsFromXML->items->item)
			return false;
		
		// get array results
		$holidayTownsFromData = is_array($holidayTownsFromXML->items->item) ? $holidayTownsFromXML->items->item : [$holidayTownsFromXML->items->item];
		
		// init towns
		$towns = [];
		
		// init countries array
		$countries = [];
		
		// get mapped countries
		$countriesMapping = $this->getCountriesMapping();
		
		$only_from_country = null;
		if (isset(static::$Departure_Country[$this->TourOperatorRecord->Handle]))
			$only_from_country = static::$Departure_Country[$this->TourOperatorRecord->Handle];
		
		// got through each town
		foreach ($holidayTownsFromData as $holidayTownFrom)
		{
			// skip departures other then romania
			if (isset($only_from_country) && ($holidayTownFrom->stateFromKey != $only_from_country))
				continue;
			
			// new town object
			$town = new \stdClass();
			$town->Id = $holidayTownFrom->id;
			$town->Name = $holidayTownFrom->name;
			
			$town->Country = $countries[$holidayTownFrom->stateFromKey] ?: ($countries[$holidayTownFrom->stateFromKey] = new \stdClass());
			$town->Country->Id = $holidayTownFrom->stateFromKey;
			$town->Country->Name = $holidayTownFrom->stateFromName;
			$town->Country->Code = $countriesMapping[strtolower($holidayTownFrom->stateFromName)];
			
			// add towns to array
			$towns[$town->Id] = $town;
		}
		
		// return towns
		return $towns;
	}
	
	/**
	 * Get departure cities.
	 * 
	 * @return boolean|\stdClass
	 */
	public function getExoticTownsFrom()
	{
		// get departure cities for holidays/charters
		list($holidayTownsFromResp) = $this->request('SearchExcursion_TOWNFROMS');
		
		// decode xml
		$holidayTownsFromXML = json_decode(json_encode(simplexml_load_string($holidayTownsFromResp)));
		
		// no results found
		if (!$holidayTownsFromXML->items->item)
			return false;
		
		// get array results
		$holidayTownsFromData = is_array($holidayTownsFromXML->items->item) ? $holidayTownsFromXML->items->item : [$holidayTownsFromXML->items->item];
		
		// init towns
		$towns = [];
		
		// init countries array
		$countries = [];
		
		// get mapped countries
		$countriesMapping = $this->getCountriesMapping();
		
		// got through each town
		foreach ($holidayTownsFromData as $holidayTownFrom)
		{
			// new town object
			$town = new \stdClass();
			$town->Id = $holidayTownFrom->id;
			$town->Name = $holidayTownFrom->name;
			
			$town->Country = $countries[$holidayTownFrom->stateFromKey] ?: ($countries[$holidayTownFrom->stateFromKey] = new \stdClass());
			$town->Country->Id = $holidayTownFrom->stateFromKey;
			$town->Country->Name = $holidayTownFrom->stateFromName;
			$town->Country->Code = $countriesMapping[strtolower($holidayTownFrom->stateFromName)];
			
			// add towns to array
			$towns[$town->Id] = $town;
		}
		
		// return towns
		return $towns;
	}
	
	/**
	 * Get destination countries for individual.
	 * 
	 * @return boolean|\stdClass
	 */
	public function getIndividualCountries($statesFrom, $filter = [])
	{
		// departure country is mandatory
		if (!$statesFrom)
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Individual countries not provided by tour operator');
			return false;
		}
		
		// init countries
		$countries = [];
		
		// get mapped countries
		$countriesMapping = $this->getCountriesMapping();
		
		$allCountries = [];
		
		// go through each departure country
		foreach ($statesFrom as $stateFrom)
		{
			// prepare params
			$params = ['STATEFROM' => $stateFrom->Id];

			// get destination countries for individual
			list($individualCountriesResp) = $this->request('SearchHotel_STATES', $params);

			// decode xml
			$individualCountriesXML = json_decode(json_encode(simplexml_load_string($individualCountriesResp)));

			// return false if no results found
			if (!$individualCountriesXML->items->item)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Individual countries not provided by tour operator for state: %s', [json_encode($stateFrom)]);
				continue;
			}

			// get countries array
			$individualCountriesData = is_array($individualCountriesXML->items->item) ? $individualCountriesXML->items->item : [$individualCountriesXML->items->item];

			\Omi\TF\TOInterface::markReportError($filter, 'Count individual countries: %s', [q_count($individualCountriesData)]);

			// go through each country
			foreach ($individualCountriesData as $individualCountry)
			{
				$countryCode = isset($countriesMapping[strtolower($individualCountry->name)]) ? $countriesMapping[strtolower($individualCountry->name)] : $individualCountry->stateISO;
				\Omi\TF\TOInterface::markReportData($filter, 'Process country from individual: %s', [$individualCountry->id . ' ' . $individualCountry->name], 50);
				$country = $allCountries[$individualCountry->id] ?: ($allCountries[$individualCountry->id] = new \stdClass());
				$country->Id = $individualCountry->id;
				$country->Name = $individualCountry->name;
				$country->Code = $countryCode;
				if (!$country->_statesFrom)
					$country->_statesFrom = [];
				$country->_statesFrom[$stateFrom->Id] = $stateFrom->Id;
				// add country to array
				$countries[$country->Id] = $country;
			}
		}
		// return countries for individual
		return $countries;
	}
	
	/**
	 * Get destination countries for holidays / charters.
	 * 
	 * @param type $townsFrom
	 * @return boolean|array
	 */
	public function getHolidayCountries($townsFrom, $filter = [])
	{
		// departure towns are mandatory
		if (!$townsFrom)
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Holiday countries not provided by tour operator');
			return false;
		}
		
		// init countries
		$countries = [];
		
		// get mapped countries
		$countriesMapping = $this->getCountriesMapping();
		
		$allCountries = [];
		// go through each departure city
		foreach ($townsFrom as $townFrom)
		{
			// prepare params
			$params = ['TOWNFROMINC' => $townFrom->Id];
			
			// get destination countries for holiday
			list($holidayCountriesResp) = $this->request('SearchTour_STATES', $params);
			
			// decode xml
			$holidayCountriesXML = json_decode(json_encode(simplexml_load_string($holidayCountriesResp)));

			// return false if no results found
			if (!$holidayCountriesXML->items->item)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Holiday countries not provided by tour operator for state: %s', [json_encode($townFrom)]);
				continue;
			}

			// get countries array
			$holidayCountriesData = is_array($holidayCountriesXML->items->item) ? $holidayCountriesXML->items->item : [$holidayCountriesXML->items->item];
			
			\Omi\TF\TOInterface::markReportError($filter, 'Count holiday countries: %s', [q_count($holidayCountriesData)]);

			// go through each destination country
			foreach ($holidayCountriesData as $holidayCountry)
			{
				$countryCode = isset($countriesMapping[strtolower($holidayCountry->name)]) ? $countriesMapping[strtolower($holidayCountry->name)] : $holidayCountry->stateISO;
				\Omi\TF\TOInterface::markReportData($filter, 'Process country from holidays: %s', [$holidayCountry->id . ' ' . $holidayCountry->name], 50);
				// new country object
				$country = $allCountries[$holidayCountry->id] ?: ($allCountries[$holidayCountry->id] = new \stdClass());
				$country->Id = $holidayCountry->id;
				$country->Name = $holidayCountry->name;
				$country->Code = $countryCode;
				if (!$country->_townsFromInc)
					$country->_townsFromInc = [];
				$country->_townsFromInc[$townFrom->Id] = $townFrom->Id;
				// add country to array
				$countries[$country->Id] = $country;
			}
		}
		
		// return countries
		return $countries;
	}
	
	public function getExoticCountries($townsFrom, $filter = [])
	{
		// departure towns are mandatory
		if (!$townsFrom)
		{
			\Omi\TF\TOInterface::markReportError($filter, 'Exotic countries not provided by tour operator');
			return false;
		}
		
		// init countries
		$countries = [];
		
		// get mapped countries
		$countriesMapping = $this->getCountriesMapping();
		$allCountries = [];
		// go through each departure city
		foreach ($townsFrom as $townFrom)
		{
			// prepare params
			$params = ['TOWNFROMINC' => $townFrom->Id];
			
			// get destination countries for holiday
			list($holidayCountriesResp) = $this->request('SearchExcursion_STATES', $params);
			
			// decode xml
			$holidayCountriesXML = json_decode(json_encode(simplexml_load_string($holidayCountriesResp)));

			// return false if no results found
			if (!$holidayCountriesXML->items->item)
			{
				\Omi\TF\TOInterface::markReportError($filter, 'Exotic countries not provided by tour operator for state: %s', [json_encode($townFrom)]);
				continue;
			}

			// get countries array
			$holidayCountriesData = is_array($holidayCountriesXML->items->item) ? $holidayCountriesXML->items->item : [$holidayCountriesXML->items->item];

			\Omi\TF\TOInterface::markReportError($filter, 'Count exotic countries: %s', [q_count($holidayCountriesData)]);

			// go through each destination country
			foreach ($holidayCountriesData as $holidayCountry)
			{				
				$countryCode = isset($countriesMapping[strtolower($holidayCountry->name)]) ? $countriesMapping[strtolower($holidayCountry->name)] : $holidayCountry->stateISO;
				\Omi\TF\TOInterface::markReportData($filter, 'Process country from exotic: %s', [$holidayCountry->id . ' ' . $holidayCountry->name], 50);
				// new country object
				$country = $allCountries[$holidayCountry->id] ?: ($allCountries[$holidayCountry->id] = new \stdClass());
				$country->Id = $holidayCountry->id;
				$country->Name = $holidayCountry->name;
				$country->Code = $countryCode;
				if (!$country->_townsFromInc)
					$country->_townsFromInc = [];
				$country->_townsFromInc[$townFrom->Id] = $townFrom->Id;
				// add country to array
				$countries[$country->Id] = $country;
			}
		}

		// return countries
		return $countries;
	}

	public function getTourTypes($params)
	{
		$paramsIdf = md5(json_encode($params));
		if ($this->tourTypes[$paramsIdf])
			return $this->tourTypes[$paramsIdf];
		// get destination countries for holiday
		list($tourTypesResp) = $this->request('SearchHotel_TOUR_TYPES', $params, [], true);
		// decode xml
		$tourTypesXML = json_decode(json_encode(simplexml_load_string($tourTypesResp)));
		return ($this->tourTypes[$paramsIdf] = $tourTypesXML);
	}

	public function getCurrencies($filter)
	{
		if (!$filter['countryId'])
			throw new \Exception('cannot get currencies because country not provided!');
		$currenciesParams = ['STATEINC' => $filter['countryId']];
		if ($filter["departureCity"])
			$currenciesParams["TOWNFROMINC"] = $filter["departureCity"];

		$serviceType = $filter["serviceTypes"] ? q_reset($filter["serviceTypes"]) : null;
		$useMethod = ($serviceType == "charter") ? 
			(in_array($filter['countryId'], static::$ExoticCountries) ? "SearchExcursion_CURRENCIES" : "SearchTour_CURRENCIES") : "SearchHotel_CURRENCIES";
		
		if (static::$Hotel_State_From[$this->TourOperatorRecord->Handle])
		{
			list($states_from_xml) = $this->request('SearchHotel_STATEFROM', [], [], true);
			
			$states_from = simplexml_load_string($states_from_xml);
			
			if (isset($states_from))
			{
				$states_from_items = q_reset($states_from->items);
				
				if (is_array($states_from_items))
					$state_itms = $states_from_items;
				else
					$state_itms = [$states_from_items];

				foreach ($state_itms ?: [] as $c)
					$statesByName[strtolower($c->name)] = $c;
				
				$state_from = $statesByName[static::$Hotel_State_From[$this->TourOperatorRecord->Handle]];

				$currenciesParams['STATEFROM'] = (string)$state_from->id;
			}
		}

		$currenciesIndx = md5(json_encode($currenciesParams) . $useMethod);
		if (isset($this->currencies[$currenciesIndx]))
			return $this->currencies[$currenciesIndx];

		// get destination countries for holiday
		list($currenciesResp) = $this->request($useMethod, $currenciesParams, [], true);

		// decode xml
		return ($this->currencies[$currenciesIndx] = json_decode(json_encode(simplexml_load_string($currenciesResp))));
	}

	public function getRequestCurrencyId($filter)
	{
		$currencies = $this->getCurrencies($filter);
		$currenciesByName = [];
		if (isset($currencies->items->item))
		{
			$itms = null;
			if (is_array($currencies->items->item))
				$itms = $currencies->items->item;
			else if (isset($currencies->items->item->id))
				$itms = [$currencies->items->item];
			
			foreach ($itms ?: [] as $c)
				$currenciesByName[strtolower($c->name)] = $c;
		}
		
		$currency_name = static::$Currency_Code[$this->TourOperatorRecord->Handle];

		// here we should return needed curency or default one!
		return (isset($filter["RequestCurrency"]) && isset($currenciesByName[strtolower($filter["RequestCurrency"])])) ? $currenciesByName[strtolower($filter["RequestCurrency"])]->id :
			(isset($currenciesByName[$currency_name]) ? $currenciesByName[$currency_name]->id : null);
	}

	public function getIndividualTourId($filter)
	{
		if (!$filter['countryId'])
			throw new \Exception('cannot get individual tour type because country not provided!');
		$tourTypesParams = ['STATEINC' => $filter['countryId']];
		if ($filter["departureCity"])
			$tourTypesParams["TOWNFROMINC"] = $filter["departureCity"];
		$tourTypes = $this->getTourTypes($tourTypesParams);
		$tourTypesByName = [];
		if (isset($tourTypes->items->item))
		{
			$itms = null;
			if (is_array($tourTypes->items->item))
				$itms = $tourTypes->items->item;
			else if (isset($tourTypes->items->item->id))
				$itms = [$tourTypes->items->item];
			foreach ($itms ?: [] as $tt)
				$tourTypesByName[$tt->name] = $tt;
		}
		return isset($tourTypesByName[static::$IndividualHotelsTypeName]) ? $tourTypesByName[static::$IndividualHotelsTypeName]->id : null;
	}

	/**
	 * Get individual offer params.
	 * 
	 * @param type $filter
	 * @return type
	 */
	public function getIndividualOffers_params($filter)
	{
		// checkin
		$checkIn = date('Ymd', strtotime($filter["checkIn"]));

		// calculate checkout date
		$checkOut = date("Ymd", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));		

		// get only first room
		$roomFilter = q_reset($filter['rooms']);
		
		// set the individual departure city
		$filter["departureCity"] = static::$IndividualDepartureCityID;

		/*
		if ($this->TourOperatorRecord->Handle != 'join_up')
		{
			$individualTourId = $this->getIndividualTourId($filter);

			if (!$individualTourId)
				throw new \Exception("Individual tour not available!");
		}
		*/
		
		$reqCurrencyId = $this->getRequestCurrencyId($filter);
		
		if (!$reqCurrencyId)
		{
			if (\QAutoload::GetDevelopmentMode())
				qvar_dump(['$filter' => $filter, 'static::$Currency_Code' => static::$Currency_Code]);
			throw new \Exception("Currency not available!");
		}

		$params = [
			'STATEINC' => $filter['countryId'],
			'TOWNS' => is_array($filter['cityId']) ? implode (',', $filter['cityId']) : $filter['cityId'],
			'TOWNFROMINC' => $filter["departureCity"],
			'CHECKIN_BEG' => $checkIn,
			'CHECKIN_END' => $checkIn,
			'NIGHTS_FROM' => $filter["days"],
			'NIGHTS_TILL' => $filter["days"],
			'ADULT' => $roomFilter['adults'],
			'CURRENCY' => $reqCurrencyId,
			'PACKET' => static::$OnlyHotelPacket
		];
		
		# if ($this->TourOperatorRecord->Handle != 'join_up')
		# 	$params['TOURTYPE'] = $individualTourId;

		if ($filter["travelItemId"])
			$params["HOTELS"] = $filter["travelItemId"];

		if ($filter["page"])
			$params["PRICEPAGE"] = $filter["page"];

		if ($roomFilter['children'])
			$params['CHILD'] = $roomFilter['children'];

		if ($roomFilter['childrenAges'])
		{
			$params['AGES'] = '';
			foreach ($roomFilter['childrenAges'] as $childrenAge)
			{
				$params['AGES'] = $params['AGES'] . $childrenAge . ',';
			}
			
			$params['AGES'] = rtrim($params['AGES'], ",");
		}

		return [$checkIn, $checkOut, $params];
	}

	public function getIndividualOffers(array $filter = null)
	{
		try
		{
			list($checkIn, $checkOut, $params) = $this->getIndividualOffers_params($filter);
		}
		catch (\Exception $ex)
		{
			if ($filter['rawResponse'])
			{
				return ['Eroare la verificare parametri: Tur operatorul nu a raspuns la un call pentru a prelua parametrii aditionali de search' => $ex->getMessage()];
			}
			throw $ex;
		}

		$toUseCheckIn = date('Y-m-d', strtotime($filter["checkIn"]));

		// calculate checkout date
		$toUseCheckOut = date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));
		
		if (static::$Hotel_State_From[$this->TourOperatorRecord->Handle])
		{
			list($states_from_xml) = $this->request('SearchHotel_STATEFROM', [], [], true);
			
			$states_from = simplexml_load_string($states_from_xml);
			
			if (isset($states_from))
			{
				$states_from_items = q_reset($states_from->items);
				
				if (is_array($states_from_items))
					$state_itms = $states_from_items;
				else
					$state_itms = [$states_from_items];

				foreach ($state_itms ?: [] as $c)
					$statesByName[strtolower($c->name)] = $c;
				
				$state_from = $statesByName[static::$Hotel_State_From[$this->TourOperatorRecord->Handle]];

				$params['STATEFROM'] = (string)$state_from->id;
			}
		}

		list($tourPricesXML) = $this->request('SearchHotel_PRICES', $params, $filter, true);

		// get first set of results
		$tourPricesResp = json_decode(json_encode(simplexml_load_string($tourPricesXML)));
	
		$allPages = ($tourPricesResp && $tourPricesResp->pager && $tourPricesResp->pager->total) ? $tourPricesResp->pager->total : 0;
		$currentPage = ($tourPricesResp && $tourPricesResp->pager && $tourPricesResp->pager->current) ? $tourPricesResp->pager->current : 1;
		$nextPage = $currentPage + 1;
		$prices[] = $tourPricesResp->prices;
		$wp = 0;
		
		while ($nextPage <= $allPages)
		{
			$params["PRICEPAGE"] = $nextPage;
			list($tourPricesXMLTMP) = $this->request('SearchHotel_PRICES', $params, $filter, true);
			// decode xml
			$tourPricesRespTMP = json_decode(json_encode(simplexml_load_string($tourPricesXMLTMP)));
			$prices[] = $tourPricesRespTMP->prices;
			$nextPage++;
			$wp++;
			if ($wp > 350)
				break;
		}

		// if we have raw request then return the result raw
		if (($rawRequest = (isset($filter['rawResponse']) && $filter['rawResponse'])))
			return $prices;

		// init indexed hotels
		$indexedHotels = [];
		$eoffs  = [];

		// init hotels
		$hotels = [];

		// go though each page
		foreach ($prices as $offersPrices)
		{
			// reset to offers
			$offersPrices = $offersPrices->price;
			
			$offsByCodeAndPrice = [];
			// go through each offer
			foreach ($offersPrices ?: [] as $roomOffer)
			{
				if ((!($hotelCode = trim($roomOffer->hotelKey))) || ($filter["travelItemId"] && ($filter["travelItemId"] !== $hotelCode)))
				{
					//echo "<div style='color: green;'>" . $hotelCode . "|" . $filter["travelItemId"] . "</div>";
					continue;
				}

				$offerCode = $hotelCode . '~' . $roomOffer->roomKey . '~' . $roomOffer->htPlaceKey . '~' . $roomOffer->mealKey . '~' . $toUseCheckIn . '~' . $toUseCheckOut;
				$offsByCodeAndPrice[$offerCode][$roomOffer->price][] = $roomOffer;
			}
			
			$newOffersPrices = [];
			foreach ($offsByCodeAndPrice ?: [] as $byPriceOffs)
			{
				ksort($byPriceOffs);
				$minPricedOffs = q_reset($byPriceOffs);
				$minPricedOff = q_reset($minPricedOffs);
				$newOffersPrices[] = $minPricedOff;
			}

			// go through each offer
			foreach ($newOffersPrices ?: [] as $roomOffer)
			{				
				// ignore offers with no hotel code
				if ((!($hotelCode = trim($roomOffer->hotelKey))) || ($filter["travelItemId"] && ($filter["travelItemId"] !== $hotelCode)))
					continue;
				
				// init hotel object
				$hotel = $indexedHotels[$hotelCode] ?: ($indexedHotels[$hotelCode] = new \stdClass());
				
				// set hotel id as the code from sejour
				$hotel->Id = $hotelCode;
				
				// ignore offers with no hotel name
				if (!($hotel->Name = trim($roomOffer->hotel)))
					continue;
				
				// ignore offers with no room type
				if (!($roomOffer->roomKey && $roomOffer->htPlaceKey))
					continue;
				
				// init offers if not already done this
				if (!$hotel->Offers)
					$hotel->Offers = [];

				// setup offer code
				$offerCode = $hotel->Id . '~' . $roomOffer->roomKey . '~' . $roomOffer->htPlaceKey . '~' . $roomOffer->mealKey . '~' . $filter['checkIn'] . '~' . $toUseCheckOut;

				// init new offer
				$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
				$offer->Code = $offerCode;
				$offer->Claim = $roomOffer->id;
				
				// set offer currency
				$offer->Currency = new \stdClass();
				$offer->Currency->Code = $roomOffer->currency;
				
				// net price
				$offer->Net = (float)$roomOffer->price;
				
				// offer total price
				$offer->Gross = (float)$roomOffer->price;
				
				// get initial price
				$offer->InitialPrice = $roomOffer->priceOld ?: $roomOffer->price;
				
				// number of days needed for booking process
				$offer->Days = $roomOffer->nights;
				
				// room
				$roomType = new \stdClass();
				$roomType->Id = $roomOffer->roomKey;
				$roomType->Title = $roomOffer->room;
				
				$roomMerch = new \stdClass();
				//$roomMerch->Id = $roomOffer->roomKey;
				$roomMerch->Title = $roomOffer->room;
				$roomMerch->Type = $roomType;
				$roomMerch->Code = $roomOffer->htPlaceKey;
				$roomMerch->Name = $roomOffer->htPlace;
				
				$roomItm = new \stdClass();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = $roomOffer->roomKey;
				
				if ($roomOffer->programType || $roomOffer->programTypeAlt)
					$roomItm->InfoTitle = $roomOffer->programType ?: $roomOffer->programTypeAlt;

				//required for indexing
				$roomItm->Code = $roomOffer->roomKey;
				$roomItm->CheckinAfter = $filter['checkIn'];
				$roomItm->CheckinBefore = $toUseCheckOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Quantity = 1;

				// set ne price on room
				$roomItm->Net = $roomOffer->price;

				// Q: set initial price :: priceOld
				$roomItm->InitialPrice = $roomOffer->priceOld ?: $roomOffer->price;
				
				if (isset(static::$Offer_Availability_Codes[$this->TourOperatorRecord->Handle]))
				{
					$offer_availability = static::$Offer_Availability_Codes[$this->TourOperatorRecord->Handle][$roomOffer->hotelAvailability];

					$offer->Availability = $roomItm->Availability = $offer_availability ?: 'no';
				}
				else
				{
					// for identify purpose
					// hotelAvailability can be also N or NNNN
					$offer->Availability = $roomItm->Availability = ($roomOffer->hotelAvailability == 'Y') ? 'yes' : ((($roomOffer->hotelAvailability == 'R') || $roomOffer->hotelAvailability == 'RRRR') ? 'ask' : 'no');
				}

				if (!$offer->Rooms)
					$offer->Rooms = [];
				$offer->Rooms[] = $roomItm;

				// board
				$boardType = new \stdClass();
				$boardType->Id = $roomOffer->mealKey;
				$boardType->Title = $roomOffer->meal;

				$boardMerch = new \stdClass();
				//$boardMerch->Id = $roomOffer->mealKey;
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
				$departureTransportMerch->Title = "CheckIn: ".($toUseCheckIn ? date("d.m.Y", strtotime($toUseCheckIn)) : "");

				$departureTransportItm = new \stdClass();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Quantity = 1;
				$departureTransportItm->Currency = $offer->Currency;
				$departureTransportItm->UnitPrice = 0;
				$departureTransportItm->Gross = 0;
				$departureTransportItm->Net = 0;
				$departureTransportItm->InitialPrice = 0;
				$departureTransportItm->DepartureDate = $toUseCheckIn;
				$departureTransportItm->ArrivalDate = $toUseCheckIn;

				// for identify purpose
				$departureTransportItm->Id = $departureTransportMerch->Id;

				// return transport item
				$returnTransportMerch = new \stdClass();
				$returnTransportMerch->Title = "CheckOut: ".($toUseCheckOut ? date("d.m.Y", strtotime($toUseCheckOut)) : "");

				$returnTransportItm = new \stdClass();
				$returnTransportItm->Merch = $returnTransportMerch;
				$returnTransportItm->Quantity = 1;
				$returnTransportItm->Currency = $offer->Currency;
				$returnTransportItm->UnitPrice = 0;
				$returnTransportItm->Gross = 0;
				$returnTransportItm->Net = 0;
				$returnTransportItm->InitialPrice = 0;
				$returnTransportItm->DepartureDate = $toUseCheckOut;
				$returnTransportItm->ArrivalDate = $toUseCheckOut;

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

				// individual hotels pages
				/*
				if (!isset($_topData["individualHotelsPages"][$hotel->Id]))
					$_topData["individualHotelsPages"][$hotel->Id] = [];
				$_topData["individualHotelsPages"][$hotel->Id][$tourPricesXML->pager->current] = $tourPricesXML->pager->current;
				*/

				// add hotel to array
				$hotels[$hotel->Id] = $hotel;
			}
		}
		
		// write to file
		//filePutContentsIfChanged($dataFile, qArrayToCode($_topData, "TDATA"), true);

		// return hotels
		return $hotels;
	}

	public function getCharterOffers_params($filter)
	{
		if (!($transportType = q_reset($filter["transportTypes"])))
			throw new \Exception("Transport Type not provided!");
		
		// checkin
		$checkIn = date('Ymd', strtotime($filter["checkIn"]));

		// calculate checkout date
		$checkOut = date("Ymd", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

		// get only first room
		$roomFilter = q_reset($filter['rooms']);

		if ((!$filter['countryId']) && $filter['cityId'])
		{
			list($apiCities) = $this->api_getCities();

			foreach ($apiCities ?: [] as $c)
			{
				if ($filter['cityId'] == $c->Id)
				{
					if ($c->Country && $c->Country->Id)
						$filter['countryId'] = $c->Country->Id;
					break;
				}
			}
		}

		$reqCurrencyId = $this->getRequestCurrencyId($filter);
		if (!$reqCurrencyId)
			throw new \Exception("Currency not available!");

		$params = [
			'STATEINC' => $filter['countryId'], // mandatory
			'TOWNS' => is_array($filter['cityId']) ? implode (',', $filter['cityId']) : $filter['cityId'],
			'TOWNFROMINC' => $filter['departureCity'],
			'CHECKIN_BEG' => $checkIn,
			'CHECKIN_END' => $checkIn,
			'NIGHTS_FROM' => $filter["days"],
			'NIGHTS_TILL' => $filter["days"],
			'ADULT' => $roomFilter['adults'],
			'CURRENCY' => $reqCurrencyId,
			'FREIGHT' => 1,
		];
		
		if (isset(static::$Skip_Towns_In_Search[$this->TourOperatorRecord->Handle]) && static::$Skip_Towns_In_Search[$this->TourOperatorRecord->Handle])
			unset($params['TOWNS']);

		if ($filter["travelItemId"])
			$params["HOTELS"] = $filter["travelItemId"];
		else if ($filter["page"])
			$params["PRICEPAGE"] = $filter["page"];

		if ($roomFilter['children'])
			$params['CHILD'] = $roomFilter['children'];

		if ($roomFilter['childrenAges'])
		{
			$params['AGES'] = '';
			foreach ($roomFilter['childrenAges'] as $childrenAge)
			{
				$params['AGES'] = $params['AGES'] . $childrenAge . ',';
			}
			$params['AGES'] = rtrim($params['AGES'], ",");
		}
		

		return [$transportType, $checkIn, $checkOut, $params];
	}

	public function getCharterOffers(array $filter = null)
	{
		$chartersEx = null;
		$rawReqs = [];
		
		try
		{
			list($transportType, $checkIn, $checkOut, $params) = $this->getCharterOffers_params($filter);

			$toUseCheckIn =  date('Y-m-d', strtotime($filter["checkIn"]));
			$toUseCheckOut =  date("Y-m-d", strtotime("+ {$filter["days"]} days", strtotime($filter["checkIn"])));

			$searchMethod = 'SearchTour';
			if (in_array($filter['countryId'], static::$ExoticCountries))
				$searchMethod = 'SearchExcursion';

			$ta_0 = microtime(true);
			// get first set of results
			list($tourPricesXML, $toSaveRequest) = $this->request($searchMethod . '_PRICES', $params, $filter, true);
			if ($toSaveRequest && $filter['_in_resync_request'])
				$rawReqs[] = $toSaveRequest;
						
			$tourPricesResp = json_decode(json_encode(simplexml_load_string($tourPricesXML)));
			
			$allPages = ($tourPricesResp && $tourPricesResp->pager && $tourPricesResp->pager->total) ? $tourPricesResp->pager->total : 0;
			$currentPage = ($tourPricesResp && $tourPricesResp->pager && $tourPricesResp->pager->current) ? $tourPricesResp->pager->current : 1;

			#error
			if ($tourPricesResp && $tourPricesResp->error)
				return [];

			$nextPage = $currentPage + 1;
			$prices[] = $tourPricesResp->prices;
			$wp = 0;
			while ($nextPage <= $allPages)
			{
				$params["PRICEPAGE"] = $nextPage;
				list($tourPricesXMLTMP, $toSaveRequest) = $this->request($searchMethod . '_PRICES', $params, $filter, true);
				if ($toSaveRequest && $filter['_in_resync_request'])
					$rawReqs[] = $toSaveRequest;
				// decode xml
				$tourPricesRespTMP = json_decode(json_encode(simplexml_load_string($tourPricesXMLTMP)));
				$nextPage++;
				$wp++;
				if ($wp > 350)
					break;
				if ($tourPricesResp && $tourPricesResp->prices && (!$tourPricesResp->error))
					$prices[] = $tourPricesRespTMP->prices;
			}
			
			$ta_1 = microtime(true);
			
			// if we have raw request then return the result raw
			if (($rawRequest = (isset($filter['rawResponse']) && $filter['rawResponse'])))
				return [$prices];

			// init indexed hotels
			$indexedHotels = [];
			$eoffs  = [];

			// init hotels
			$hotels = [];
			$freights = [];

			$debug = false;

			$eoffPricesByCode = [];
			
			$skip_tours_having_in_name = null;
			if (isset(static::$Skip_Tours_Having_In_Name[$this->TourOperatorRecord->Handle]))
				$skip_tours_having_in_name = static::$Skip_Tours_Having_In_Name[$this->TourOperatorRecord->Handle];
			
			// go though each page
			foreach ($prices ?: [] as $offersPrices)
			{				
				// reset to offers
				$offersPrices = $offersPrices->price;

				if ($offersPrices && (gettype($offersPrices) === "object") && $offersPrices->id)
					$offersPrices = [$offersPrices];
				
				$offsByCodeAndPrice = [];
				// go through each offer
				foreach ($offersPrices ?: [] as $roomOffer)
				{					
					if ((!($hotelCode = trim($roomOffer->hotelKey))) || ($filter["travelItemId"] && ($filter["travelItemId"] != $hotelCode)))
					{
						//echo "<div style='color: green;'>" . $hotelCode . "|" . $filter["travelItemId"] . "</div>";
						if ($debug)
							qvardump("skip hotel because no code or not in filter", $roomOffer, $filter);
						continue;
					}
					
					if (isset($skip_tours_having_in_name))
					{						
						$to_skip = false;
						foreach ($skip_tours_having_in_name as $string_to_skip)
						{							
							if (strpos(strtolower($roomOffer->tourAlt), $string_to_skip) !== false)
							{
								$to_skip = true;
								
								break;
							}
						}
						
						if ($to_skip)
						{
							if ($debug)
								qvardump("skip hotel because string to skip found in his name", $skip_tours_having_in_name, $roomOffer);
							
							continue;
						}
					}
					
					/*
					if (($this->TourOperatorRecord->Handle == 'join_up') && ($filter['cityId'] != $roomOffer->townKey))
					{
						if ($debug)
							qvardump("skip offer because not search city", $filter['cityId'], $roomOffer->townKey);
							
						continue;
					}
					*/

					$offerCode = $hotelCode . '~' . $roomOffer->roomKey . '~' . $roomOffer->htPlaceKey . '~' . $roomOffer->mealKey . '~' . $toUseCheckIn . '~' . $toUseCheckOut . '~' . $roomOffer->tourKey;
					$offsByCodeAndPrice[$offerCode][$roomOffer->price][] = $roomOffer;
				}

				$newOffersPrices = [];
				foreach ($offsByCodeAndPrice ?: [] as $byPriceOffs)
				{
					ksort($byPriceOffs);
					$minPricedOffs = q_reset($byPriceOffs);
					$minPricedOff = q_reset($minPricedOffs);
					$newOffersPrices[] = $minPricedOff;
				}

				// go through each offer
				foreach ($newOffersPrices ?: [] as $roomOffer)
				{
					if (!$freights[$roomOffer->tourKey])
					{
						$toursParams = [
							'STATEINC' => $filter['countryId'],
							'TOWNFROMINC' => $filter['departureCity']
						];

						$toursParams_indx = json_encode($toursParams);
						
						$toursResp_arr = $this->requestsData['loadedTours'][$toursParams_indx];
						if (! $toursResp_arr)
						{
							list($toursResp, $toSaveRequest) = 
							$this->requestsData['loadedTours'][$toursParams_indx] = $this->request($searchMethod . '_TOURS', $toursParams, $filter, true);
							
							if ($toSaveRequest && $filter['_in_resync_request'])
								$rawReqs[] = $toSaveRequest;
						}
						else
						{
							list($toursResp, $toSaveRequest) = $toursResp_arr;
						}

						// decode xml
						$toursXML = json_decode(json_encode(simplexml_load_string($toursResp)));

						// return false if no results found
						if (!$toursXML->items->item)
						{
							if ($debug)
								qvardump("no tour found", $toursParams, $roomOffer->hotelKey, $toursXML, $toursResp, $toSaveRequest);
							
							continue;
						}

						// make result array
						$toursData = is_array($toursXML->items->item) ? $toursXML->items->item : [$toursXML->items->item];
						
						if (isset($skip_tours_having_in_name))
						{
							foreach ($toursData as $key => $tour_data)
							{
								$to_skip = false;
								foreach ($skip_tours_having_in_name as $string_to_skip)
								{							
									if (strpos(strtolower($tour_data->nameAlt), $string_to_skip) !== false)
									{
										$to_skip = true;

										break;
									}
								}

								if ($to_skip)
								{
									if ($debug)
										qvardump("skip hotel because string to skip found in his name", $skip_tours_having_in_name, $roomOffer);

									unset($toursData[$key]);
								}
							}
						}

						$offerTour = null;
						foreach ($toursData as $tour)
						{
							if ($roomOffer->tourKey == $tour->id)
								$offerTour = $tour;
						}

						if (!$offerTour)
						{
							if ($debug)
								qvardump("no offer found on tour found", $toursData);
							
							continue;
						}
						
						if (isset(static::$Get_Freights_Method[$this->TourOperatorRecord->Handle]))
						{							
							$freightsParams = [
								'CATCLAIM' => $roomOffer->id
							];
							
							$freightsParams_indx = json_encode($freightsParams);

							$frightResp_arr = $this->requestsData['loadedFreights'][$freightsParams_indx];
							if (! $frightResp_arr)
							{
								list($frightResp, $toSaveRequest) = 
								$this->requestsData['loadedFreights'][$freightsParams_indx] = $this->request(static::$Get_Freights_Method[$this->TourOperatorRecord->Handle], $freightsParams, $filter, true);
								
								if ($toSaveRequest && $filter['_in_resync_request'])
									$rawReqs[] = $toSaveRequest;
							}
							else
							{
								list($frightResp, $toSaveRequest) = $frightResp_arr;
							}
														
							// decode xml
							$freightXml = json_decode(json_encode(simplexml_load_string($frightResp)));
							
							if (!$freightXml->routes->route || !is_array($freightXml->routes->route))
							{								
								if ($debug)
									qvardump("frights issue", $freightsParams);
								
								continue;
							}
							
							$freights[$roomOffer->tourKey] = $freightXml;
							
							$first_route = q_reset($freights[$roomOffer->tourKey]->routes->route);
							
							$last_route = end($freights[$roomOffer->tourKey]->routes->route);
							
							// get departure flight info
							$departureFlightInfo = $first_route->freights->freight;
							$departure_flight_date = $first_route->info->date;

							// get return flight info
							$returnFlightInfo = $last_route->freights->freight;
							$return_flight_date = $last_route->info->date;
						}
						else
						{
							$freightsParams = [
								'SOURCE' => $filter['departureCity'],
								'TARGET' => $offerTour->townToKey,
								'CHECKIN' => $checkIn, 
								'CHECKOUT' => $checkOut,
							];

							$freightsParams_indx = json_encode($freightsParams);

							$frightResp_arr = $this->requestsData['loadedFreights'][$freightsParams_indx];
							if (! $frightResp_arr)
							{
								list($frightResp, $toSaveRequest) = $this->requestsData['loadedFreights'][$freightsParams_indx] = 
									$this->request('FreightMonitor_FREIGHTS', $freightsParams, $filter, true);
								if ($toSaveRequest && $filter['_in_resync_request'])
									$rawReqs[] = $toSaveRequest;
							}
							else
							{
								list($frightResp, $toSaveRequest) = $frightResp_arr;
							}
							
							// decode xml
							$freightXml = json_decode(json_encode(simplexml_load_string($frightResp)));
							
							// exit if no flight found
							if ((!$freightXml->direct->direct) || (!$freightXml->back->back))
							{
								if ($debug)
									qvardump("frights issue", $freightsParams);
								continue;
								//return;
							}

							$freights[$roomOffer->tourKey] = $freightXml;
							
							// get departure flight info
							$departureFlightInfo = $freights[$roomOffer->tourKey]->direct->direct;

							// get return flight info
							$returnFlightInfo = is_array($freights[$roomOffer->tourKey]->back->back) ? q_reset($freights[$roomOffer->tourKey]->back->back) : $freights[$roomOffer->tourKey]->back->back;
						}
					}

					//echo "<div style='color: green;'>" . $filter["travelItemId"] . "|" . $hotelCode . "</div>";

					// // filter hotel by code
					if ((!($hotelCode = trim($roomOffer->hotelKey))) || ($filter["travelItemId"] && ($filter["travelItemId"] != $hotelCode)))
					{
						//echo "<div style='color: green;'>" . $hotelCode . "|" . $filter["travelItemId"] . "</div>";
						if ($debug)
							qvardump("skip hotel because no code or not in filter", $roomOffer, $filter);
						continue;
					}

					// init hotel object
					$hotel = $indexedHotels[$hotelCode] ?: ($indexedHotels[$hotelCode] = new \stdClass());

					// set hotel id as the code from sejour
					$hotel->Id = $hotelCode;

					// ignore offers with no hotel name
					if (!($hotel->Name = trim($roomOffer->hotel)))
					{
						//echo "<div style='color: green;'>no hotel name</div>";
						if ($debug)
							qvardump("no hotel name", $roomOffer);
						continue;
					}

					// ignore offers with no room type
					if (!($roomOffer->roomKey && $roomOffer->htPlaceKey))
					{
						//echo "<div style='color: green;'>ignore offers with no room type: " . $roomOffer->roomKey . "|" . $roomOffer->htPlaceKey . "</div>";
						if ($debug)
							qvardump("no room type", $roomOffer);
						continue;
					}

					//echo "<div style='color: green;'>" . $roomOffer->hotel . "</div>";

					// init offers if not already done this
					if (!$hotel->Offers)
						$hotel->Offers = [];

					// setup offer code
					$offerCode = $hotel->Id . '~' . $roomOffer->roomKey . '~' . $roomOffer->htPlaceKey . '~' . $roomOffer->mealKey . '~' . $toUseCheckIn . '~' . $toUseCheckOut;

					$eoffPricesByCode[$offerCode][] = $roomOffer;

					// init new offer
					$offer = $eoffs[$offerCode] ?: ($eoffs[$offerCode] = new \stdClass());
					$offer->Code = $offerCode;
					$offer->Claim = $roomOffer->id;

					// set offer currency
					$offer->Currency = new \stdClass();
					$offer->Currency->Code = $roomOffer->currency;

					// net price
					$offer->Net = (float)$roomOffer->price;

					// offer total price
					$offer->Gross = (float)$roomOffer->price;

					// get initial price
					$offer->InitialPrice = $roomOffer->priceOld ? (float)$roomOffer->priceOld : $offer->Gross;

					// number of days needed for booking process
					$offer->Days = $roomOffer->nights;

					// room
					$roomType = new \stdClass();
					$roomType->Id = $roomOffer->roomKey;
					$roomType->Title = $roomOffer->room;

					$roomMerch = new \stdClass();
					//$roomMerch->Id = $roomOffer->roomKey;
					$roomMerch->Title = $roomOffer->room;
					$roomMerch->Type = $roomType;
					$roomMerch->Code = $roomOffer->htPlaceKey;
					$roomMerch->Name = $roomOffer->htPlace;

					$roomItm = new \stdClass();
					$roomItm->Merch = $roomMerch;
					$roomItm->Id = $roomOffer->roomKey;

					if ($roomOffer->programType || $roomOffer->programTypeAlt)
						$roomItm->InfoTitle = $roomOffer->programType ?: $roomOffer->programTypeAlt;

					//required for indexing
					$roomItm->Code = $roomOffer->roomKey;
					$roomItm->CheckinAfter = $toUseCheckIn;
					$roomItm->CheckinBefore = $toUseCheckOut;
					$roomItm->Currency = $offer->Currency;
					$roomItm->Quantity = 1;

					// set ne price on room
					$roomItm->Net = $roomOffer->price;

					// Q: set initial price :: priceOld
					$roomItm->InitialPrice = $roomOffer->price;
					
					if (isset(static::$Offer_Availability_Codes[$this->TourOperatorRecord->Handle]))
					{
						$offer_availability = static::$Offer_Availability_Codes[$this->TourOperatorRecord->Handle][$roomOffer->hotelAvailability];
						
						$offer->Availability = $roomItm->Availability = $offer_availability ?: 'no';
					}
					else
					{
						// for identify purpose
						// hotelAvailability can be also N or NNNN
						$offer->Availability = $roomItm->Availability = ($roomOffer->hotelAvailability == 'Y') ? 'yes' : ((($roomOffer->hotelAvailability == 'R') || $roomOffer->hotelAvailability == 'RRRR') ? 'ask' : 'no');
					}

					if (!$offer->Rooms)
						$offer->Rooms = [];
					$offer->Rooms[] = $roomItm;

					// board
					$boardType = new \stdClass();
					$boardType->Id = $roomOffer->mealKey;
					$boardType->Title = $roomOffer->meal;

					$boardMerch = new \stdClass();
					// $boardMerch->Id = $roomOffer->mealKey;
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

					// add items to offer
					$offer->Item = $roomItm;
					$offer->MealItem = $boardItm;

					// departure transport item
					$departureTransportMerch = new \stdClass();
					$departureTransportMerch->Title = "Dus: ".($toUseCheckIn ? date("d.m.Y", strtotime($toUseCheckIn)) : "");
					$departureTransportMerch->TransportType = $transportType;
					$departureTransportMerch->Category = new \stdClass();
					$departureTransportMerch->Category->Code = 'other-outbound';

					$departureTransportMerch->From = new \stdClass();
					$departureTransportMerch->From->City = new \stdClass();
					$departureTransportMerch->From->City->Id = $filter["departureCity"];
					
					if (isset(static::$Get_Freights_Method[$this->TourOperatorRecord->Handle]))
					{						
						$departureTransportMerch->DepartureTime = date('Y-m-d H:i', strtotime($departure_flight_date . ' ' . $departureFlightInfo->departure->time));
						$departureTransportMerch->ArrivalTime = date('Y-m-d H:i', strtotime($departure_flight_date . ' ' . $departureFlightInfo->arrival->time));
						
						// departure transport merch
						$departureTransportMerch->DepartureAirport = $departureFlightInfo->departure->portAlias;
						$departureTransportMerch->ReturnAirport = $departureFlightInfo->arrival->portAlias;
					}
					else
					// de la prestige poate sa vina array
					{
						$departureTransportMerch->DepartureTime = date('Y-m-d H:i', strtotime($departureFlightInfo->date . ' ' . $departureFlightInfo->departure->time));
						$departureTransportMerch->ArrivalTime = date('Y-m-d H:i', strtotime($departureFlightInfo->date . ' ' . $departureFlightInfo->arrival->time));
						
						// departure transport merch
						$departureTransportMerch->DepartureAirport = $departureFlightInfo->departure->portAlias;
						$departureTransportMerch->ReturnAirport = $departureFlightInfo->arrival->portAlias;
					}

					// departure transport itm
					$departureTransportItm = new \stdClass();
					$departureTransportItm->Merch = $departureTransportMerch;
					$departureTransportItm->Quantity = 1;
					$departureTransportItm->Currency = $offer->Currency;
					$departureTransportItm->UnitPrice = 0;
					$departureTransportItm->Gross = 0;
					$departureTransportItm->Net = 0;
					$departureTransportItm->InitialPrice = 0;
					
					if (isset(static::$Get_Freights_Method[$this->TourOperatorRecord->Handle]))
					{
						$departureTransportItm->DepartureDate = date('Y-m-d', strtotime($departure_flight_date));
						$departureTransportItm->ArrivalDate = date('Y-m-d', strtotime($departure_flight_date));
					}
					else
					{
						$departureTransportItm->DepartureDate = date('Y-m-d', strtotime($departureFlightInfo->date));
						$departureTransportItm->ArrivalDate = date('Y-m-d', strtotime($departureFlightInfo->date));
					}

					// for identify purpose
					$departureTransportItm->Id = $departureTransportMerch->Id;

					// return transport item
					$returnTransportMerch = new \stdClass();
					$returnTransportMerch->Title = "Retur: ".($toUseCheckOut ? date("d.m.Y", strtotime($toUseCheckOut)) : "");
					$returnTransportMerch->TransportType = $transportType;
					$returnTransportMerch->Category = new \stdClass();
					$returnTransportMerch->Category->Code = 'other-inbound';
					
					if (isset(static::$Get_Freights_Method[$this->TourOperatorRecord->Handle]))
					{// de la prestige poate sa vina array $returnFlightInfo
						$returnTransportMerch->DepartureTime = date('Y-m-d H:i', strtotime($return_flight_date . ' ' . $returnFlightInfo->departure->time));
						$returnTransportMerch->ArrivalTime = date('Y-m-d H:i', strtotime($return_flight_date . ' ' . $returnFlightInfo->arrival->time));
						
						$returnTransportMerch->DepartureAirport = $returnFlightInfo->departure->portAlias;
						$returnTransportMerch->ReturnAirport = $returnFlightInfo->arrival->portAlias;
					}
					else
					{
						$returnTransportMerch->DepartureTime = date('Y-m-d H:i', strtotime($returnFlightInfo->date . ' ' . $returnFlightInfo->departure->time));
						$returnTransportMerch->ArrivalTime = date('Y-m-d H:i', strtotime($returnFlightInfo->date . ' ' . $returnFlightInfo->arrival->time));
						
						$returnTransportMerch->DepartureAirport = $returnFlightInfo->departure->portAlias;
						$returnTransportMerch->ReturnAirport = $returnFlightInfo->arrival->portAlias;
					}

					$returnTransportItm = new \stdClass();
					$returnTransportItm->Merch = $returnTransportMerch;
					$returnTransportItm->Quantity = 1;
					$returnTransportItm->Currency = $offer->Currency;
					$returnTransportItm->UnitPrice = 0;
					$returnTransportItm->Gross = 0;
					$returnTransportItm->Net = 0;
					$returnTransportItm->InitialPrice = 0;
					
					if (isset(static::$Get_Freights_Method[$this->TourOperatorRecord->Handle]))
					{
						$returnTransportItm->DepartureDate = date('Y-m-d', strtotime($return_flight_date));
						$returnTransportItm->ArrivalDate = date('Y-m-d', strtotime($return_flight_date));
					}
					else
					{
						$returnTransportItm->DepartureDate = date('Y-m-d', strtotime($returnFlightInfo->date));
						$returnTransportItm->ArrivalDate = date('Y-m-d', strtotime($returnFlightInfo->date));
					}

					// for identify purpose
					$returnTransportItm->Id = $returnTransportMerch->Id;
					$departureTransportItm->Return = $returnTransportItm;

					$offer->DepartureTransportItem = $departureTransportItm;
					$offer->ReturnTransportItem = $returnTransportItm;

					// save offer on hotel
					$hotel->Offers[] = $offer;

					// add hotel to array
					$hotels[$hotel->Id] = $hotel;
				}
			}
		}
		catch (\Exception $ex)
		{
			$chartersEx = $ex;
		}

		// return hotels
		return [$hotels, $chartersEx, $rawReqs];
	}

	public function prepareForBooking($offerClaim, $orderData = null)
	{
		// init data
		$data = 
			'<WorkRequest version="3.0">
				<proc>GET_PACKET_FOR_AGENT</proc>
				<params>
					<CLAIM>' . $offerClaim . '</CLAIM>
					<NAME>' . ($this->TourOperatorRecord->ApiUsername__ ?: $this->TourOperatorRecord->ApiUsername) . '</NAME>
					<PSW>' . ($this->TourOperatorRecord->ApiPassword__ ?: $this->TourOperatorRecord->ApiPassword) . '</PSW>
				</params>
			</WorkRequest>';
		
		// new aes crypt
		$crypt = new CryptAes();
		
		// set key
		$crypt->setKey($this->TourOperatorRecord->BookingApiPassword);
		
		// encrypt data
		$encryptedData = base64_encode($crypt->encrypt(gzencode($data)));
		
		// set created date
		$created = date('Y-m-d\TH:i:s\Z');
		
		// set nonce
		$nonce = uniqid();
		
		// gen soap password
		$soapPassword = base64_encode(sha1($nonce . $created . ($this->TourOperatorRecord->BookingApiPassword)));
		
		$namespace_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
		$namespace_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
		
		$sh_username = new \SoapVar($this->TourOperatorRecord->BookingApiUsername, XSD_STRING, null, null, null, $namespace_wsse);
		
		$sh_password = new \SoapVar($soapPassword, XSD_STRING, 'PasswordDigest', $namespace_wsse, 'Password', $namespace_wsse);
		
		$sh_nonce = new \SoapVar(base64_encode($nonce), XSD_STRING, null, null, null, $namespace_wsse);
		
		$sh_created = new \SoapVar($created, XSD_STRING, null, null, null, $namespace_wsu);
		
		$sh_usernameToken = new \SoapVar(
			['UsernameToken' => (object)['Username' => $sh_username, 'Password' => $sh_password, 'Nonce' => $sh_nonce, 'Created' => $sh_created]], 
			SOAP_ENC_OBJECT,
			null, 
			null,
			'UsernameToken',
			$namespace_wsse
		);

		// setup soap security header
		$securityHeader = new \SoapHeader($namespace_wsse, 'Security', $sh_usernameToken);
		
		// setup soap agent info header
		$agentInfoHeader = new \SoapHeader('http://www.samo.ru/xml', 'agentinfo', (object)['version' => '3.0']);

		$soapClientOptions = ["trace" => 1];
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			$soapClientOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$soapClientOptions["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$soapClientOptions["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$soapClientOptions["proxy_password"] = $proxyPassword;
		// init soap client
		$soapClient = new SoapClient($this->TourOperatorRecord->BookingUrl, $soapClientOptions);

		// set security header on soap client
		$soapClient->__setSoapHeaders([$securityHeader, $agentInfoHeader]);

		$exception = null;
		try
		{
			$_data = new \SoapVar($encryptedData, XSD_STRING);
			$xml = $soapClient->__soapCall('WORK', ['data' => $_data]);
		}
		catch (\Exception $ex)
		{
			$exception = $ex;
			$this->logError(["prepareBooking" => true, "reqXML" => $data, "\$sh_usernameToken" => $sh_usernameToken, "respXML" => $soapClient->__getLastResponse()], $ex);
			throw $ex;
		}

		try
		{
			$xml = $crypt->decrypt(base64_decode($xml));
			$xml = gzinflate(substr($xml, 10, -8));
		}
		catch (\Exception $ex)
		{
			$this->logError([
				"prepareBooking" => true, 
				"reqXML" => $data, 
				"\$sh_usernameToken" => $sh_usernameToken, 
				"xml" => $xml,
				"respXML" => $soapClient->__getLastResponse()
			], $ex);
			throw $ex;
		}

		$this->logData("book: prepare", [
			"\$offerClaim" => $offerClaim,
			"\$orderData" => $orderData,
			"ERR" => $exception ? true : false,
			"\$sh_usernameToken" => $sh_usernameToken, 
			"reqXML" => $data,
			"respXML" => $xml,
		]);

		return $xml;
	}
	
	public function recalculatePrice($xml, $passengers)
	{
		// xml to object
		$xml = new \SimpleXMLElement($xml);
		
		// set guid
		$xml->claim->claimDocument['guid'] = '{' . $this->GUIDv4() . '}';
		
		// get peoples from claim
		$people = $xml->claim->claimDocument->peoples->people;
		
		foreach ($passengers as $key => $passenger)
			$passengers[$key]['Checked'] = false;
		
		$passportExpiryDate = date("Y", strtotime("+2 years")) . '-01-01';
		foreach ($people as $person)
		{			
			foreach ($passengers as $key => $passenger)
			{				
				if ($passengers[$key]['Checked'])
					continue;
				
				if (($passenger['Type'] == 'adult') && ((string)$person->attributes()->age == 'ADL'))
				{					
					$person['human'] = ($passenger['Gender'] == 'male') ? 'MR' : 'MRS';
					$person['sex'] = ($passenger['Gender'] == 'male') ? 'MALE' : 'FEMALE';
					$person['name'] = $passenger['Firstname'];
					$person['lname'] = $passenger['Lastname'];
					$person['born'] = $passenger['BirthDate'];
					$person['pserie'] = '273288';
					$person['pexpire'] = $passportExpiryDate;
					$person['pgiven'] = $passenger['BirthDate'];
					
					$passengers[$key]['Checked'] = true;
					
					break;
				}
				else if (($passenger['Type'] == 'child') && ((string)$person->attributes()->age == 'CHD'))
				{
					$person['human'] = ($passenger['Gender'] == 'male') ? 'MR' : 'MRS';
					$person['sex'] = ($passenger['Gender'] == 'male') ? 'MALE' : 'FEMALE';
					$person['name'] = $passenger['Firstname'];
					$person['lname'] = $passenger['Lastname'];
					$person['born'] = $passenger['BirthDate'];
					$person['pserie'] = '273288';
					$person['pexpire'] = $passportExpiryDate;
					$person['pgiven'] = $passenger['BirthDate'];
					
					$passengers[$key]['Checked'] = true;
					
					break;
				}
			}
		}
		
		// get claim as text xml
		$xml = $xml->claim->asXML();
		
		// init data
		$data = 
			'<WorkRequest version="3.0">
				<proc>GET_BRON_PRICE_FOR_AGENT</proc>
				<params>
					' . $xml . '
					<NAME>' . ($this->TourOperatorRecord->ApiUsername__ ?: $this->TourOperatorRecord->ApiUsername) . '</NAME>
					<PSW>' . ($this->TourOperatorRecord->ApiPassword__ ?: $this->TourOperatorRecord->ApiPassword) . '</PSW>
				</params>
			</WorkRequest>';
		
		// new aes crypt
		$crypt = new \Crypt_AES();
		
		// set key
		$crypt->setKey($this->TourOperatorRecord->BookingApiPassword);
		
		// encrypt data
		$encryptedData = base64_encode($crypt->encrypt(gzencode($data)));
		
		// set created date
		$created = date('Y-m-d\TH:i:s\Z');
		
		// set nonce
		$nonce = uniqid();
		
		// gen soap password
		$soapPassword = base64_encode(sha1($nonce . $created . ($this->ApiPassword)));
		
		$namespace_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
		$namespace_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
		
		$sh_username = new \SoapVar($this->TourOperatorRecord->BookingApiUsername, XSD_STRING, null, null, null, $namespace_wsse);
		
		$sh_password = new \SoapVar($soapPassword, XSD_STRING, 'PasswordDigest', $namespace_wsse, 'Password', $namespace_wsse);
		
		$sh_nonce = new \SoapVar(base64_encode($nonce), XSD_STRING, null, null, null, $namespace_wsse);
		
		$sh_created = new \SoapVar($created, XSD_STRING, null, null, null, $namespace_wsu);
		
		$sh_usernameToken = new \SoapVar(
			['UsernameToken' => (object)['Username' => $sh_username, 'Password' => $sh_password, 'Nonce' => $sh_nonce, 'Created' => $sh_created]], 
			SOAP_ENC_OBJECT,
			null, 
			null,
			'UsernameToken',
			$namespace_wsse
		);
		
		
		// setup soap security header
		$securityHeader = new \SoapHeader($namespace_wsse, 'Security', $sh_usernameToken);
		
		// setup soap agent info header
		$agentInfoHeader = new \SoapHeader('http://www.samo.ru/xml', 'agentinfo', (object)['version' => '3.0']);
		
		$soapClientOptions = ["trace" => 1];
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			$soapClientOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$soapClientOptions["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$soapClientOptions["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$soapClientOptions["proxy_password"] = $proxyPassword;

		// init soap client
		$soapClient = new \Omi\Util\SoapClientAdvanced($this->TourOperatorRecord->BookingUrl, $soapClientOptions);
		
		// set security header on soap client
		$soapClient->__setSoapHeaders([$securityHeader, $agentInfoHeader]);
		
		try
		{
			$_data = new \SoapVar($encryptedData, XSD_STRING);
			
			$xml2 = $soapClient->__soapCall('WORK', ['data' => $_data]);
		}
		catch (\Exception $ex)
		{
			qvar_dump($ex->getMessage(), $ex->getTrace());
			throw $ex;
		}
		
		// echo 'REQUEST: '.$soapClient->__getlastrequest()."\n\n\n\n";
		// echo 'RESPONSE: '.$soapClient->__getlastresponse()."\n\n\n\n";
		
		
		$xml2 = $crypt->decrypt(base64_decode($xml2));
		
		$xml2 = gzinflate(substr($xml2,10,-8));
		
		return $xml2;
	}

	public function confirmReservation($xml, $passengers, $orderData = null)
	{
		// xml to object
		$xml = new \SimpleXMLElement($xml);
		
		// set guid
		$xml->claim->claimDocument['guid'] = '{' . $this->GUIDv4() . '}';
		
		$xmlData = $this->simpleXML2Array($xml);
			
		$checkNameValidator = isset($xmlData["claim"]["checkFields"]["people"]["lname"]["@attrs"]["regular"]) ?
			trim($xmlData["claim"]["checkFields"]["people"]["lname"]["@attrs"]["regular"]) : null;
		$checkNameValidatorMessage = isset($xmlData["claim"]["checkFields"]["people"]["lname"]["@attrs"]["message"]) ?
			trim($xmlData["claim"]["checkFields"]["people"]["lname"]["@attrs"]["message"]) : null;
		
		$useCheckNameValidator = ($checkNameValidator && ($checkNameValidator != 'true') && ($checkNameValidator != 'false'));
		
		$checkIn = $orderData["Params"]["CheckIn"];
		// get peoples from claim
		# index people from claim & passengers by type
		$people = $xml->claim->claimDocument->peoples->people;
		$peopleByType = [];
		foreach ($people as $person)
		{
			$type = (string)$person->attributes()->age;
			$peopleByType[$type][] = $person;
		}
		
		$passengersByType = [];
		foreach ($passengers as $key => $passenger)
		{
			$passengerType = $this->passengersTypesTranslations[$passenger['Type']];
			if (!($passengerType))
				throw new \Exception("Passenger type not accepted for [{$passenger['Type']}]");
				
			if ($passengerType == 'CHD')
			{
				$passengerAge = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($passenger['BirthDate']))->format("%y");
				if ($passengerAge < $this->infantAgeBelow)
					$passengerType = 'INF';
			}
			
			$passengersByType[$passengerType][] = $passenger;
		}
		
		$adultType = $this->passengersTypesTranslations['adult'];
		$childType = $this->passengersTypesTranslations['child'];
		$infantType = $this->passengersTypesTranslations['infant'];
		
		# sort children by age from older to younger
		$childrenByAge = [];
		foreach ($passengersByType[$childType] ?: [] as $child)
		{
			$childYearsOld = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($child['BirthDate']))->format("%Y");
			$childrenByAge[$childYearsOld][] = $child;
		}
		krsort($childrenByAge);
		$passengersByType[$childType] = [];
		foreach ($childrenByAge ?: [] as $children)
		{
			foreach ($children ?: [] as $child)
				$passengersByType[$childType][] = $child;
		}
		ksort($peopleByType);
		$passportExpiryDate = date("Y", strtotime("+2 years")) . '-01-01';
		#the to do:
		#fill adults first
		#fill adults remaining rows with children (from older to younger)
		#fill children rows with remaining children
		$ppos = 0;
		foreach ($peopleByType ?: [] as $ptype => $peoples)
		{			
			$pByTypePos = 0;
			$peopleAreAdults = ($ptype === $adultType);
			$peopleAreChildren = (($ptype === $childType) || ($ptype === $infantType));
			
			foreach ($peoples ?: [] as $person)
			{
				# to use passenger is the passenger for type and on index
				$toUsePassenger = $passengersByType[$ptype][$pByTypePos];
				
				#if we don't have the passenger from our system we have 2 cases:
				#1. is adult and we need to send an older child to fill the adult record
				#2. is child and we should have an error for this
				if (!($toUsePassenger))
				{
					if ($peopleAreAdults)
					{
						$toUsePassenger = array_shift($passengersByType[$childType]);
					}
					else
						throw new \Exception("Nu se poate pune tipul [{$ptype}] pe pasagerul de la pozitia [{$pByTypePos}]");
				}

				if (!($toUsePassenger))
					throw new \Exception("Nu se poate pune tipul [{$ptype}] pe pasagerul de la pozitia [{$pByTypePos}]");
				$pByTypePos++;

				$isMale = ($toUsePassenger['Gender'] == 'male');
				$toUsePassengerAge = (int)date_diff(date_create($checkIn ?: date("Y-m-d")), date_create($toUsePassenger['BirthDate']))->format("%y");
				$isInfant = ($peopleAreChildren && ($toUsePassengerAge < $this->infantAgeBelow));
				$human = $peopleAreChildren ? ($isInfant ? "INF" : "CHD") : ($isMale ? 'MR' : 'MRS');
				#if (!$peopleAreChildren)
				{
					$person['human'] = $human;
					$person['sex'] = $isMale ? 'MALE' : 'FEMALE';
				}

				$lastName = strtoupper($toUsePassenger['Lastname']);
				$firstname = strtoupper($toUsePassenger['Firstname']);

				if ($checkNameValidator && $useCheckNameValidator)
				{
					if (!preg_match($checkNameValidator, $lastName))
						throw new \Exception($checkNameValidatorMessage);
				}
				
				$ppos++;

				$person['name'] = $firstname;
				$person['lname'] = $lastName . ' ' . $firstname;

				$person['born'] = $toUsePassenger['BirthDate'];
				$person['pserie'] = '887' . $ppos;
				$person['pnumber'] = '27328' . $ppos;
				$person['pexpire'] = $passportExpiryDate;
				$person['pgiven'] = $toUsePassenger['BirthDate'];
				$person['pgivenorg'] = 'RO';
				$person['bornplace'] = 'ROMANIA';
				$person['nationality'] = 'ROMANIA';
				$person['bornplaceKey'] = '80';				
				$person['nationalityKey'] = '80';
				
				if ($this->TourOperatorRecord->Handle == 'join_up')
				{
					$person['mobile'] = '+4071111111';
					$person['email'] = 'test@test.com';
					
					if ($toUsePassenger['Type'] == 'adult')
						$person['age'] = 'ADL';
					else if ($isInfant)
						$person['age'] = $person['human'] = 'INF';
					else
						$person['human'] = $person['age'] = 'CHD';
					
					$person['nationalityKey'] = '82';
					
					unset($person['bornplaceKey']);
				}
			}
		}

		// get claim as text xml
		$xml = $xml->claim->asXML();

		// init data
		$data = 
			'<WorkRequest version="3.0">
				<proc>GET_BRON_RESULT_FOR_AGENT</proc>
				<params>
					' . $xml . '
					<NAME>' . ($this->TourOperatorRecord->ApiUsername__ ?: $this->TourOperatorRecord->ApiUsername) . '</NAME>
					<PSW>' . ($this->TourOperatorRecord->ApiPassword__ ?: $this->TourOperatorRecord->ApiPassword) . '</PSW>
				</params>
			</WorkRequest>';
		
		// new aes crypt
		$crypt = new CryptAes();
		
		// set key
		$crypt->setKey($this->TourOperatorRecord->BookingApiPassword);
		
		// encrypt data
		$encryptedData = base64_encode($crypt->encrypt(gzencode($data)));
		
		// set created date
		$created = date('Y-m-d\TH:i:s\Z');
		
		// set nonce
		$nonce = uniqid();
		
		// gen soap password
		$soapPassword = base64_encode(sha1($nonce . $created . ($this->TourOperatorRecord->BookingApiPassword)));
		
		$namespace_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
		$namespace_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
		
		$sh_username = new \SoapVar($this->TourOperatorRecord->BookingApiUsername, XSD_STRING, null, null, null, $namespace_wsse);
		
		$sh_password = new \SoapVar($soapPassword, XSD_STRING, 'PasswordDigest', $namespace_wsse, 'Password', $namespace_wsse);
		
		$sh_nonce = new \SoapVar(base64_encode($nonce), XSD_STRING, null, null, null, $namespace_wsse);
		
		$sh_created = new \SoapVar($created, XSD_STRING, null, null, null, $namespace_wsu);
		
		$sh_usernameToken = new \SoapVar(
			['UsernameToken' => (object)['Username' => $sh_username, 'Password' => $sh_password, 'Nonce' => $sh_nonce, 'Created' => $sh_created]], 
			SOAP_ENC_OBJECT,
			null, 
			null,
			'UsernameToken',
			$namespace_wsse
		);

		// setup soap security header
		$securityHeader = new \SoapHeader($namespace_wsse, 'Security', $sh_usernameToken);
		
		// setup soap agent info header
		$agentInfoHeader = new \SoapHeader('http://www.samo.ru/xml', 'agentinfo', (object)['version' => '3.0', 'answer' => 1]);
		
		$soapClientOptions = ["trace" => 1];
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			$soapClientOptions["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$soapClientOptions["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$soapClientOptions["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$soapClientOptions["proxy_password"] = $proxyPassword;
		// init soap client
		$soapClient = new SoapClient($this->TourOperatorRecord->BookingUrl, $soapClientOptions);
		
		// set security header on soap client
		$soapClient->__setSoapHeaders([$securityHeader, $agentInfoHeader]);
		
		$exception = null;
		try
		{
			$_data = new \SoapVar($encryptedData, XSD_STRING);
			$xml2 = $soapClient->__soapCall('WORK', ['data' => $_data]);	
		}
		catch (\Exception $ex)
		{
			$this->logError(["confirmReservation" => true, "reqXML" => $data, "\$sh_usernameToken" => $sh_usernameToken, "respXML" => $soapClient->__getLastResponse()], $ex);
			throw new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!"
				. "\nRaspuns tur operator: " . $ex->getMessage());
		}

		$xml2 = $crypt->decrypt(base64_decode($xml2));
		$xml2 = gzinflate(substr($xml2,10,-8));
		$this->logData("book: confirm", [
			"\$orderData" => $orderData,
			"\$xml" => $xml,
			"\$passengers" => $passengers,
			"ERR" => $exception ? true : false,
			"\$sh_usernameToken" => $sh_usernameToken, 
			"reqXML" => $xml,
			"respXML" => $xml2,
		]);

		return $xml2;
	}
	
	/**
	 * Map countries by ISO code.
	 * 
	 * @return type
	 * @throws \Exception
	 */
	protected function getCountriesMapping()
	{
		if ($this->TourOperatorRecord->Handle == 'join_up')
			$ret = $this->getCountriesMappingJoinUP();
		else
			$ret = $this->getCountriesMappingDefault();
			
		return $ret;
	}
	
	protected function getCountriesMappingJoinUP()
	{
		$mapping = [
			"Anguilla" => "AI",
			"Albania" => "AL",
			"Andorra" => "AD",
			"Argentina" => "AR",
			"Armenia" => "AM",
			"Aruba" => "AW",
			"Australia" => "AU",
			"Austria" => "AT",
			"Azerbaijan" => "AZ",
			"Bahamas" => "BS",
			"Bahrain" => "BH",
			"Barbados" => "BB",
			"Belarus" => "BY",
			"Belgium" => "BE",
			"Bolivia" => "BO",
			"Botswana" => "BW",
			"Belize" => "BZ",
			"Brazil" => "BR",
			"British Virgin Islands" => "VG",
			"Bulgaria" => "BG",
			"Cambodia" => "KH",
			"Cameroon" => "CM",
			"Canada" => "CA",
			"Cayman Islands" => "KY",
			"Chile" => "CL",
			"China" => "CN",
			"Colombia" => "CO",
			"DEMOCRATIC REPUBLIC OF THE CONGO" => "CG",
			"Cook Islands" => "CK",
			"Costa Rica" => "CR",
			"Cuba" => "CU",
			"Cyprus" => "CY",
			"Czech Republic" => "CZ",
			"Denmark" => "DK",
			"Dominican Republic" => "DO",
			"Ecuador" => "EC",
			"Egypt" => "EG",
			"Estonia" => "EE",
			"Ethiopia" => "ET",
			"Fiji" => "FJ",
			"Finland" => "FI",
			"France" => "FR",
			"Gabon" => "GA",
			"Gambia" => "GM",
			"Georgia" => "GE",
			"Germany" => "DE",
			"Ghana" => "GH",
			"Greece" => "GR",
			"Greenland" => "GL",
			"Guatemala" => "GT",
			"Haiti" => "HT",
			"Honduras" => "HN",
			"Hong Kong" => "HK",
			"Hungary" => "HU",
			"Iceland" => "IS",
			"India" => "IN",
			"Indonesia" => "ID",
			"Iran" => "IR",
			"Iraq" => "IQ",
			"Israel" => "IL",
			"Italy" => "IT",
			"Jamaica" => "JM",
			"Japan" => "JP",
			"Jordan" => "JO",
			"Kazakhstan" => "KZ",
			"Kenya" => "KE",
			"Kuwait" => "KW",
			"Kyrgyzstan" => "KG",
			"Laos" => "LA",
			"Latvia" => "LV",
			"Lebanon" => "LB",
			"Libya" => "LY",
			"Liechtenstein" => "LI",
			"Lithuania" => "LT",
			"Luxembourg" => "LU",
			"Macedonia" => "MK",
			"Maldives" => "MV",
			"Mali" => "ML",
			"Malta" => "MT",
			"Mauritius" => "MU",
			"Mexico" => "MX",
			"Micronesia" => "FM",
			"Monaco" => "MC",
			"Mongolia" => "MN",
			"Montenegro" => "ME",
			"Morocco" => "MA",
			"Mozambique" => "MZ",
			"Myanmar" => "MM",
			"Namibia" => "NA",
			"Nepal" => "NP",
			//"Netherlands" => "NL",
			"New Caledonia" => "NC",
			"New Zealand" => "NZ",
			"Nicaragua" => "NI",
			"Nigeria" => "NG",
			"North Korea" => "KP",
			"Norway" => "NO",
			"Oman" => "OM",
			"Pakistan" => "PK",
			"Palau" => "PW",
			"Panama" => "PA",
			"Paraguay" => "PY",
			"Peru" => "PE",
			"Philippines" => "PH",
			"Poland" => "PL",
			"Portugal" => "PT",
			"Puerto Rico" => "PR",
			"Qatar" => "QA",
			"Cape Verde" => "CV",
			"RÃ©union" => "RE",
			"Romania" => "RO",
			"Russia" => "RU",
			"San Marino" => "SM",
			"Saudi Arabia" => "SA",
			"Senegal" => "SN",
			"Serbia" => "RS",
			"Seychelles" => "SC",
			"Slovakia" => "SK",
			"Slovenia" => "SI",
			"South Africa" => "ZA",
			"South Korea" => "KR",
			"Spain" => "ES",
			"Sri Lanka" => "LK",
			"Swaziland" => "SZ",
			"Sweden" => "SE",
			"Switzerland" => "CH",
			"Syria" => "SY",
			"Taiwan" => "TW",
			"Tanzania (Zanzibar)" => "TZ",
			"Thailand" => "TH",
			"Togo" => "TG",
			"Tonga" => "TO",
			"Tunisia" => "TN",
			"Turkey" => "TR",
			"Turkmenistan" => "TM",
			"Uganda" => "UG",
			"Ukraine" => "UA",
			#"United Arab Emirates" => "AE",
			"UAE" => "AE",
			"United Kingdom" => "GB",
			"Uruguay" => "UY",
			"Vanuatu" => "VU",
			"Venezuela" => "VE",
			"Vietnam" => "VN",
			"Zaire" => "CD",
			"Zambia" => "ZM",
			"Zimbabwe" => "ZW",
			"Afghanistan" => "AF",
			"Algeria" => "DZ",
			"American Samoa" => "AS",
			"Angola" => "AO",
			"Bangladesh" => "BD",
			"Benin" => "BJ",
			"Bhutan" => "BT",
			"Bonaire" => "BK",
			"Burkina Faso" => "BF",
			"Burundi" => "BI",
			"Chad" => "TD",
			"Djibouti" => "DJ",
			"Dominica" => "DM",
			"East Timor" => "TP",
			"El Salvador" => "SV",
			"Equatorial Guinea" => "GQ",
			"Eritrea" => "ER",
			"Faroe Islands" => "FO",
			"Guinea" => "GN",
			"Guinea Bissau" => "GW",
			"Ivory Coast" => "CI",
			"Kiribati" => "KI",
			"Lesotho" => "LS",
			"Liberia" => "LR",
			"Madagascar" => "MG",
			"Malawi" => "MW",
			"Marshall Islands" => "MH",
			"Martinique" => "MQ",
			"Mauritania" => "MR",
			"Montserrat" => "MS",
			"Nauru" => "NR",
			"Niue" => "NU",
			"Papua New Guinea" => "PG",
			"Sierra Leone" => "SL",
			"Solomon Islands" => "SB",
			"Somalia" => "SO",
			"Sudan" => "SD",
			"Suriname" => "SR",
			"Trinidad And Tobago" => "TT",
			"Tuvalu" => "TV",
			"Croatia" => "HR",
			"Comoros" => "KM",
			"FRENCH POLYNESIA" => "PF",
			"GRENADA" => "GD",
			"GUAM" => "GU",
			"MALAYSIA" => "MY",
			"MOLDOVA" => "MD",
			"antigua and barbuda" => "AG",
			"bermuda" => "BM",
			"bosnia" => "BA",
			"brunei" => "BN",
			"central africa" => "CF",
			"french guiana" => "GF",
			
			"GUADALUPE" => "GP",
			"Ireland" => "IE",
			"MACAO" => "MO",
			"YEMEN" => "YE",
			"Northern Mariana Islands" => "MP",
			"RWANDA" => "RW",
			"Saint BarthÃ©lemy" => "SH",
			"SAINT KITTS AND NEVIS" => "KN",
			"SAINT LUCIA" => "LC",
			"Saint Martin" => "MF",
			"SAINT VINCENT AND THE GRENADINES" => "VC",
			"SAMOA" => "WS",
			"SAO TOME AND PRINCIPE" => "ST",
			"SINGAPORE" => "SG",
			"THE NETHERLANDS" => "NL",
			"THE NETHERLANDS ANTILLES" => "AN",
			"TURKS AND  CAICOS ISLANDS" => "TC",
			"U.S. VIRGIN ISLANDS" => "VI",
			"U.S.A." => "US",
			"UZBEKISTAN" => "UZ"
		];

		$codes = [];
		$ret = [];
		foreach ($mapping ?:  [] as $k => $v)
		{
			if (isset($codes[$v]))
			{
				throw new \Exception("Duplicate code [{$v}]");
			}
			$codes[$v] = $v;
			$ret[trim(strtolower($k))] = $v;
		}
		
		return $ret;
	}

	protected function getCountriesMappingDefault()
	{
		$mapping = [
			"Anguilla" => "AI",
			"Albania" => "AL",
			"Andorra" => "AD",
			"Argentina" => "AR",
			"Armenia" => "AM",
			"Aruba" => "AW",
			"Australia" => "AU",
			"Austria" => "AT",
			"Azerbaijan" => "AZ",
			"Bahamas" => "BS",
			"Bahrain" => "BH",
			"Barbados" => "BB",
			"Belarus" => "BY",
			"Belgium" => "BE",
			"Bolivia" => "BO",
			"Botswana" => "BW",
			"Belize" => "BZ",
			"Brazil" => "BR",
			"British Virgin Islands" => "VG",
			"Bulgaria" => "BG",
			"Cambodia" => "KH",
			"Cameroon" => "CM",
			"Canada" => "CA",
			"Cayman Islands" => "KY",
			"Chile" => "CL",
			"China" => "CN",
			"Colombia" => "CO",
			"DEMOCRATIC REPUBLIC OF THE CONGO" => "CG",
			"Cook Islands" => "CK",
			"Costa Rica" => "CR",
			"Cuba" => "CU",
			"North Cyprus" => "CY",
			"Czech Republic" => "CZ",
			"Denmark" => "DK",
			"Dominican Republic" => "DO",
			"Ecuador" => "EC",
			"Egypt" => "EG",
			"Estonia" => "EE",
			"Ethiopia" => "ET",
			"Fiji" => "FJ",
			"Finland" => "FI",
			"France" => "FR",
			"Gabon" => "GA",
			"Gambia" => "GM",
			"Georgia" => "GE",
			"Germany" => "DE",
			"Ghana" => "GH",
			"Greece" => "GR",
			"Greenland" => "GL",
			"Guatemala" => "GT",
			"Haiti" => "HT",
			"Honduras" => "HN",
			"Hong Kong" => "HK",
			"Hungary" => "HU",
			"Iceland" => "IS",
			"India" => "IN",
			"Indonesia" => "ID",
			"Iran" => "IR",
			"Iraq" => "IQ",
			"Israel" => "IL",
			"Italy" => "IT",
			"Jamaica" => "JM",
			"Japan" => "JP",
			"Jordan" => "JO",
			"Kazakhstan" => "KZ",
			"Kenya" => "KE",
			"Kuwait" => "KW",
			"Kyrgyzstan" => "KG",
			"Laos" => "LA",
			"Latvia" => "LV",
			"Lebanon" => "LB",
			"Libya" => "LY",
			"Liechtenstein" => "LI",
			"Lithuania" => "LT",
			"Luxembourg" => "LU",
			"Macedonia" => "MK",
			"Maldives" => "MV",
			"Mali" => "ML",
			"Malta" => "MT",
			"Mauritius" => "MU",
			"Mexico" => "MX",
			"Micronesia" => "FM",
			"Monaco" => "MC",
			"Mongolia" => "MN",
			"Montenegro" => "ME",
			"Morocco" => "MA",
			"Mozambique" => "MZ",
			"Myanmar" => "MM",
			"Namibia" => "NA",
			"Nepal" => "NP",
			//"Netherlands" => "NL",
			"New Caledonia" => "NC",
			"New Zealand" => "NZ",
			"Nicaragua" => "NI",
			"Nigeria" => "NG",
			"North Korea" => "KP",
			"Norway" => "NO",
			"Oman" => "OM",
			"Pakistan" => "PK",
			"Palau" => "PW",
			"Panama" => "PA",
			"Paraguay" => "PY",
			"Peru" => "PE",
			"Philippines" => "PH",
			"Poland" => "PL",
			"Portugal" => "PT",
			"Puerto Rico" => "PR",
			"Qatar" => "QA",
			"Cape Verde" => "CV",
			"RÃ©union" => "RE",
			"Romania" => "RO",
			"Russia" => "RU",
			"San Marino" => "SM",
			"Saudi Arabia" => "SA",
			"Senegal" => "SN",
			"Serbia" => "RS",
			"Seychelles" => "SC",
			"Slovakia" => "SK",
			"Slovenia" => "SI",
			"South Africa" => "ZA",
			"South Korea" => "KR",
			"Spain" => "ES",
			"Sri Lanka" => "LK",
			"Swaziland" => "SZ",
			"Sweden" => "SE",
			"Switzerland" => "CH",
			"Syria" => "SY",
			"Taiwan" => "TW",
			"Tanzania" => "TZ",
			"Thailand" => "TH",
			"Togo" => "TG",
			"Tonga" => "TO",
			"Tunisia" => "TN",
			"Turkey" => "TR",
			"Turkmenistan" => "TM",
			"Uganda" => "UG",
			"Ukraine" => "UA",
			#"United Arab Emirates" => "AE",
			"UAE" => "AE",
			"United Kingdom" => "GB",
			"Uruguay" => "UY",
			"Vanuatu" => "VU",
			"Venezuela" => "VE",
			"Vietnam" => "VN",
			"Zaire" => "CD",
			"Zambia" => "ZM",
			"Zimbabwe" => "ZW",
			"Afghanistan" => "AF",
			"Algeria" => "DZ",
			"American Samoa" => "AS",
			"Angola" => "AO",
			"Bangladesh" => "BD",
			"Benin" => "BJ",
			"Bhutan" => "BT",
			"Bonaire" => "BK",
			"Burkina Faso" => "BF",
			"Burundi" => "BI",
			"Chad" => "TD",
			"Djibouti" => "DJ",
			"Dominica" => "DM",
			"East Timor" => "TP",
			"El Salvador" => "SV",
			"Equatorial Guinea" => "GQ",
			"Eritrea" => "ER",
			"Faroe Islands" => "FO",
			"Guinea" => "GN",
			"Guinea Bissau" => "GW",
			"Ivory Coast" => "CI",
			"Kiribati" => "KI",
			"Lesotho" => "LS",
			"Liberia" => "LR",
			"Madagascar" => "MG",
			"Malawi" => "MW",
			"Marshall Islands" => "MH",
			"Martinique" => "MQ",
			"Mauritania" => "MR",
			"Montserrat" => "MS",
			"Nauru" => "NR",
			"Niue" => "NU",
			"Papua New Guinea" => "PG",
			"Sierra Leone" => "SL",
			"Solomon Islands" => "SB",
			"Somalia" => "SO",
			"Sudan" => "SD",
			"Suriname" => "SR",
			"Trinidad And Tobago" => "TT",
			"Tuvalu" => "TV",
			"Croatia" => "HR",
			"Comoros" => "KM",
			"FRENCH POLYNESIA" => "PF",
			"GRENADA" => "GD",
			"GUAM" => "GU",
			"MALAYSIA" => "MY",
			"MOLDOVA" => "MD",
			"antigua and barbuda" => "AG",
			"bermuda" => "BM",
			"bosnia" => "BA",
			"brunei" => "BN",
			"central africa" => "CF",
			"french guiana" => "GF",
			
			"GUADALUPE" => "GP",
			"Ireland" => "IE",
			"MACAO" => "MO",
			"YEMEN" => "YE",
			"Northern Mariana Islands" => "MP",
			"RWANDA" => "RW",
			"Saint BarthÃ©lemy" => "SH",
			"SAINT KITTS AND NEVIS" => "KN",
			"SAINT LUCIA" => "LC",
			"Saint Martin" => "MF",
			"SAINT VINCENT AND THE GRENADINES" => "VC",
			"SAMOA" => "WS",
			"SAO TOME AND PRINCIPE" => "ST",
			"SINGAPORE" => "SG",
			"THE NETHERLANDS" => "NL",
			"THE NETHERLANDS ANTILLES" => "AN",
			"TURKS AND  CAICOS ISLANDS" => "TC",
			"U.S. VIRGIN ISLANDS" => "VI",
			"U.S.A." => "US",
			"UZBEKISTAN" => "UZ"
		];

		$codes = [];
		$ret = [];
		foreach ($mapping ?:  [] as $k => $v)
		{
			if (isset($codes[$v]))
			{
				throw new \Exception("Duplicate code [{$v}]");
			}
			$codes[$v] = $v;
			$ret[trim(strtolower($k))] = $v;
		}
		
		return $ret;
	}
	
	function GUIDv4 ($trim = true)
	{
		// OSX/Linux
		if (function_exists('openssl_random_pseudo_bytes') === true) {
			$data = openssl_random_pseudo_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}
	}

	/**
	 * System is touroperator name.
	 * 
	 * @return string
	 */
	public function getSystem()
	{
		return "calypso";
	}

	public function getSimpleCacheFileForUrl($url, $format = "xml")
	{
		$cacheDir = $this->getResourcesDir() . "cache/";
		if (!is_dir($cacheDir))
			qmkdir($cacheDir);
		return $cacheDir . "cache_" . md5($url . "|" . $format) . "." . $format;
	}

	/**
	 * First will try to login and then prepare params for the SOAP request.
	 * 
	 * @param type $module
	 * @params type $params
	 * 
	 * @return type
	 * 
	 * @throws \Exception
	 */
	public function request($module, $params = [], $filter = [], $skipCache = false, $showErr = false)
	{
		$logData = ((defined('DO_LOGGING') && DO_LOGGING && DO_LOGGING[$this->TourOperatorRecord->Handle]));

		if (!$params)
			$params = [];

		// set params
		$params = [
			'samo_action' => 'api', 
			'oauth_token' => ($this->TourOperatorRecord->ApiCode__),
			'version' => '1.0',
			'type' => 'xml',
			'action' => $module
		] + $params;

		$url = ($this->TourOperatorRecord->ApiUrl__ ?: $this->TourOperatorRecord->ApiUrl);
		$req_url = $url . ($params ? "?" . http_build_query($params) : "");

		$response = null;
		// cache file
		$cache_file = null;
		// if we are using cache check if the cache file exists
		if (!($skipCache))
		{
			// get cache file path
			$cache_file = $this->getSimpleCacheFileForUrl($req_url);
			// last modified
			$cf_last_modified = ($f_exists = file_exists($cache_file)) ? filemtime($cache_file) : null;
			$cache_time_limit = time() - $this->cacheTimeLimit;
			// if exists - last modified
			if (($f_exists) && ($cf_last_modified >= $cache_time_limit))
				$response = file_get_contents($cache_file);
		}

		if ($response === null)
		{
			$curlHandle = $this->_curl_handle = q_curl_init_with_log();
			$headers = [];
			
			# q_curl_setopt_with_log($curlHandle, CURLOPT_HTTPHEADER, $headers);
			
			q_curl_setopt_with_log($curlHandle, CURLOPT_FAILONERROR, false);
			q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
			q_curl_setopt_with_log($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
			q_curl_setopt_with_log($curlHandle, CURLOPT_FOLLOWLOCATION, true);

			# q_curl_setopt_with_log($curlHandle, CURLOPT_CONNECTTIMEOUT, 10);

			q_curl_setopt_with_log($curlHandle, CURLOPT_RETURNTRANSFER, 1);

			q_curl_setopt_with_log($curlHandle, CURLOPT_URL, $req_url);

			list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
			if ($proxyUrl)
				q_curl_setopt_with_log($curlHandle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
			
			#if ($proxyPort)
			#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
			if ($proxyUsername)
				q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERNAME, $proxyUsername);
			if ($proxyPassword)
				q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYUSERPWD, $proxyPassword);

			$t1 = microtime(true);
			
			$response = curl_exec($curlHandle);
						
			$info = curl_getinfo($curlHandle);
			$_trq = (microtime(true) - $t1);
			#qvardump((microtime(true) - $t1) . " seconds!");

			curl_close($curlHandle);
			if ($response === false)
			{
				$ex = new \Exception("Invalid response from server - " . curl_error($curlHandle));
				$this->logError([
					"module" => $module,
					"req_url" => $req_url,
					 "params" => $params, 
					"filter" => $filter, 
				], $ex);
				throw $ex;
			}

			$resp = $this->simpleXML2Array(simplexml_load_string($response));

			if (isset($resp['Response']['Error']))
			{
				$errMsg = is_scalar($resp['Response']['Error']) ? $resp['Response']['Error'] : 
					(is_array($resp['Response']['Error']) ? q_reset($resp['Response']['Error']) : 'Eroare sistem tur operator');
				$ex = new \Exception($errMsg);
				$this->logError([
					"module" => $module,
					"respXML" => $response,
					"req_url" => $req_url,
					 "params" => $params, 
					"filter" => $filter, 
				], $ex);
				throw $ex;
			}
			
			if (!($skipCache))
			{
				file_put_contents($cache_file, $response);
			}
		}

		$logSearch = ($filter && ($filter["__booking_search__"] || $filter["__on_setup_search__"]));
		if ($logSearch || $logData)
		{
			$logMethod = $logSearch ? "logData" : "logDataSimple";
			$this->{$logMethod}(($logSearch ? "book:search" : $module), ["url" => $req_url, "\$module" => $module, "params" => $params, "filter" => $filter, "respXML" => $response]);
		}

		if ((!$this->xmlIsValid($response)))
		{
			$ex = new \Exception("Invalid response from server!");
			if ($showErr)
				echo $response;

			$this->logError([
				"module" => $module,
				"req_url" => $req_url,
				 "params" => $params, 
				"filter" => $filter, 
				"respXML" => $response
			], $ex);
		}
		
		$reqData = [
			'\$url' => $req_url,
			'\$toSendPostData' => $params,
			'\$topRetInfo' => $info,
		];
		
		$callKeyIdf = md5(json_encode($reqData));

		$toProcessRequest = [
			$module,
			json_encode($reqData),
			$response,
			$callKeyIdf,
			$_trq
		];

		return [$response, $toProcessRequest];
	}
	
	public function getRequestMode()
	{
		return static::RequestModeCurl;
	}

	public function setCacheKeyCallbackOnSoapClient($soapClient)
	{
		$soapClient->topinstance = $this;
		$soapClient->_cache_get_key = function ($method, $params, $request, $location)
		{
			// if it's a hotel search, we return 2 keys
			$isHotelSearch = false;
			unset($params["getFeesAndInstallments"]);
			unset($params["getFeesAndInstallmentsFor"]);
			unset($params["VacationType"]);

			// cleanup cache params
			unset($params["_cache_use"]);
			unset($params["_cache_create"]);
			unset($params["_multi_request"]);
			unset($params["_cache_force"]);
			unset($params["ParamsFile"]);

			// duration comes when on tour
			unset($params["Duration"]);

			$origParams = $params;
			ksort($origParams);

			// check if we are on hotel search
			$isHotelSearch = ($params["travelItemId"]) ? true : false;

			$params["travelItemId"] = null;
			$params["travelItemType"] = null;
			$params["travelItemName"] = null;

			if ($params)
				ksort($params);

			TOStorage::KSortTree($params);
			TOStorage::KSortTree($origParams);

			$this->cleanupBeforeGenerateCacheKey($method, $origParams, $params, $location);

			$ret = $isHotelSearch ? 
				[
					sha1(var_export([$method, $origParams, $location, $this->TourOperatorRecord->Handle], true)),
					sha1(var_export([$method, $origParams, $location, $this->TourOperatorRecord->Handle], true))
					//sha1(var_export([$method, $params, $location, $this->TourOperatorRecord->Handle], true)), 
				] : 
				sha1(var_export([$method, $params, $location, $this->TourOperatorRecord->Handle], true));

			return $ret;
		};
	}

	// ============================================= do request =================================

	public function getRequestParams($method, $filter)
	{
		
	}
	
	public function getRequestModule($method, $filter)
	{
		
	}

	public function getRequestIndex($method, $filter)
	{
		
	}

	public function doRestAPI_Exec_UseCache_GetCalls($method, $filter)
	{
		
	}

	public function getSoapClientByMethodAndFilter($method, $filter = null)
	{
		
	}

	public function setupCurlCallback($curlHandle, $method, $filter, $headers = [])
	{
		
	}
}