<?php
	
namespace Omi\TF;
	
class SolvexApi 
{
	use TOInterface_Util;

	private $countries;

	public $ApiUrl = null;

	public $ApiUsername = null;

	public $ApiPassword = null;

	public $ApiContext = null;

	public $TourOperatorRecord = null;
	
	public $connect_result;

	public $debug = false;

	public $isProxy = true;

	// Temporary map, to be used to track the parents for each geometry object
	public $tempGeoMap;
	
	protected $loadedGeography = null;

	public function initApi()
	{
		//$this->debug = 1;
		//$params = $this->debug ? ['trace' => 1] : [];
		$params = ['trace' => 1];
		$params["login"] = ($this->ApiUsername__ ?: $this->ApiUsername);
		$params["password"] = ($this->ApiPassword__ ?: $this->ApiPassword);
		$params["connection_timeout"] = 320;
		$params["timeout"] = 320;

		// $params["soap_version"] = SOAP_1_2;
		// $params["pm_process_idle_timeout"] = 3;
		// $params["pm_max_requests"] = 4;
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			$params["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$params["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$params["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$params["proxy_password"] = $proxyPassword;

		$this->client = new \Omi\Util\SoapClientAdvanced(($this->ApiUrl__ ?: $this->ApiUrl), $params);
		$this->client->__top_handle__ = $this->TourOperatorRecord->Handle;
		$this->client->_request_headers = ['Content-Type: text/xml; charset=utf-8'];

		$this->setupCookies();
		
		$this->client->_cache_get_key = function ($method, $params, $request, $location)
		{
			$initial_params = $params;
			// if it's a hotel search, we return 2 keys
			$is_hotel_search = false;

			// cleanup cache params
			unset($params["_cache_use"]);
			unset($params["_cache_create"]);
			unset($params["_multi_request"]);
			unset($params["_cache_force"]);
			unset($params["ParamsFile"]);
			
			$orig_params = $params;
			if ($orig_params[0])
				ksort($orig_params[0]);

			if ($method === "SearchHotelServices")
			{
				$is_hotel_search = $params[0]["Hotel"] ? true : false;
				$params[0]["Hotel"] = null;
			}
			
			if ($params && $params[0] && is_array($params[0]))
			{
				unset($params[0]['guid']);
				ksort($params[0]);
			}
			if ($orig_params && $orig_params[0] && is_array($orig_params[0]))
			{
				unset($orig_params[0]['guid']);
				ksort($orig_params[0]);
			}

			TOStorage::KSortTree($params);
			TOStorage::KSortTree($orig_params);
			
			$ret = $is_hotel_search ? 
				[
					sha1(var_export([$method, $orig_params, $location, $this->TourOperatorRecord->Handle], true)), 
					sha1(var_export([$method, $params, $location, $this->TourOperatorRecord->Handle], true))
				] : 
				sha1(var_export([$method, $params, $location, $this->TourOperatorRecord->Handle], true));
			
			if ($_GET['q_show_for_cache'] && (defined('TO_SHOW_DUMP_IP') && (TO_SHOW_DUMP_IP === $_SERVER["REMOTE_ADDR"])))
			{
				qvardump("q_show: cache_hashes, initial_params, travel_params, search_params, method, location, tour_op_handle", 
					$ret, $initial_params, $params, $orig_params, $method, $location, $this->TourOperatorRecord->Handle);
			}

			return $ret;
		};
	}

	public function setupCookies()
	{
		if (($topSessionData = Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle)))
		{
			foreach ($topSessionData as $key => $val)
			{
				$cookie_name = $key;
				if (is_array($val))
				{
					$cookie_str = $val[0];
					/*if ($val[1])
						$cookie_str .= "; path=".$val[1];
					if ($val[2])
						$cookie_str .= "; domain=".$val[2];*/
				}
				else
					$cookie_str = $val;

				//qdumptofile("set cookie on {$this->TourOperatorRecord->Handle} at ".date("Y-m-d H:i:s"), $cookie_name, $cookie_str);
				//qvardump("set cookie on {$this->TourOperatorRecord->Handle} at ".date("Y-m-d H:i:s"), $cookie_name, $cookie_str);
				$this->client->__setCookie($cookie_name, $cookie_str);
				$this->client->_cookies[$cookie_name] = $cookie_str;
			}
		}

		$this->client->_x_cookies_callback = function ($_cookies, $soap_handle)
		{
			$cookies = $soap_handle->_cookies ?: $_cookies;
			foreach ($cookies as $k => $v)
			{
				Q_SESSION(["soap-sid-" . $this->TourOperatorRecord->Handle, $k], $v);
			}
		};
	}

	public function setCachingAndMultiState($_cache_use = false, $_cache_create = false, $_multi_request = false)
	{
		
	}
	
	public function resetCachingAndMultiState()
	{
		$this->setCachingAndMultiState(false, false, false);
	}
	
	/**
	 * Get list of cities from a certain country
	 * 
	 * @param stdClass $country
	 * @return array of stdClass
	 */
	public function apiGetCities($countryId = null, $countyId = null)
	{
		// public Megatec.Travel.Entities.City[] GetCities( int countryKey, int regionKey)
		if ($countryId === null)
			$countryId = -1;
		if ($countyId === null)
			$countyId = -1;
		
		// qvar_dump($countryId, $countyId);
		$cities = $this->client->GetCities(['countryKey' => $countryId, 'regionKey' => $countyId]);
		// GetCitiesResult->City
		if (!is_object($cities) && (!is_object($cities->GetCitiesResult)))
		{
			$this->addError('requestCities', [$countryId, $countyId], $counties);
			return false; // error
		}
		
		$ret = [];
		
		if (isset($cities->GetCitiesResult->City))
		{
			if (!is_array($cities->GetCitiesResult->City))
				$cities->GetCitiesResult->City = [$counties->GetCitiesResult->City];
			foreach ($cities->GetCitiesResult->City as $city)
			{
				if (strtolower($city->Name) === '<all>')
					continue;
				
				# ID[int]:			9
				# Name[string]:		"Bansko"
				# Code[string]:		"BAN"
				# CountryID[int]:	4
				# RegionID[int]:	21
				/*
				$tf_city = new \Omi\City();
				$tf_city->setName($city->Name);
				
				$tf_city->setInTourOperatorId($city->ID);
				$tf_city->setTourOperator($this->getTourOperatorObject());
				
				$master_country = $this->getMasterCountry($city->CountryID);
				if ($master_country)
					$tf_city->setCountry($master_country);
				
				$master_county = $this->getMasterCounty($city->RegionID);
				if ($master_county)
					$tf_city->setCounty($master_county);
				*/
				$ret[] = $city;
			}
		}
		
		return $ret;
	}
	
	/**
	 * Get the list of countries from the Solvex system
	 * 
	 * @param stdClass $geometry
	 * @return array of stdClass
	 */
	public function getCountries()
	{
		// ensure login
		$this->login();
		
		$countries = $this->client->GetCountries();
		if (!is_object($countries) && (!is_object($countries->GetCountriesResult)))
		{
			$this->addError('requestCountries', [], $countries);
			return false; // error
		}
		
		$ret = [];
		
		if (isset($countries->GetCountriesResult->Country))
		{
			if (!is_array($countries->GetCountriesResult->Country))
				$countries->GetCountriesResult->Country = [$countries->GetCountriesResult->Country];
			foreach ($countries->GetCountriesResult->Country as $country)
			{
				$ret[] = $country;
			}
		}
		
		return $ret;
	}
	
	public function getCounties(int $country_id = null)
	{
		// ensure login
		$this->login();
		
		$country_ids = [];
		if ($country_id === null)
		{
			$countries = $this->getCountries();
			foreach ($countries as $c)
				$country_ids[$c->ID] = $c->ID;
		}
		else
			$country_ids[$country_id] = $country_id;
		
		$ret = [];
		
		foreach ($country_ids as $country_id)
		{
			// qvar_dump(['countryKey' =>]);
			$counties = $this->client->GetRegions(['countryKey' => $country_id]);

			if (!is_object($counties) && (!is_object($counties->GetRegionsResult)))
			{
				$this->addError('requestCounties', [$country_id], $counties);
				return false; // error
			}

			if (isset($counties->GetRegionsResult->Region))
			{
				if (!is_array($counties->GetRegionsResult->Region))
					$counties->GetRegionsResult->Region = [$counties->GetRegionsResult->Region];
				foreach ($counties->GetRegionsResult->Region as $county)
				{
					if (strtolower($county->Name) === '<all>')
						continue;
					/*
					$tf_county = new \Omi\County();
					$tf_county->setName($county->Name);
					$tf_county->setInTourOperatorId($county->ID);

					$tf_county->setTourOperator($this->getTourOperatorObject());

					$master_country = $this->getMasterCountry($county->CountryID);
					if ($master_country)
						$tf_county->setCountry($master_country);
					*/
					$ret[$county->ID] = $county;
				}
			}
		}
		
		return $ret;
	}
	
	public function getTourOperatorObject()
	{
		return $this->TourOperatorRecord;
	}
	
	public function login(bool $force = false)
	{
		if ((!$force) && $this->connect_result)
			return $this->connect_result;
		else
			return ($this->connect_result = $this->client->Connect(['login' => ($this->ApiUsername__ ?: $this->ApiUsername), 'password' => ($this->ApiPassword ?: $this->ApiPassword__)]));
	}
	

	public function testConnectivity()
	{
		$this->login(true);
		$connected = ($this->connect_result && ($this->connect_result->ConnectResult != -1));
		if (!$connected)
		{
			qvardump($this->connect_result);
		}
		return $connected;

		/*
		$resp = $this->client->GetCountries();
		if (is_soap_fault($resp)) 
			throw new \Exception("Error: faultcode: {$resp->faultcode}, faultstring: {$resp->faultstring}!");

		qvardump($resp);
		if (!$resp)
		{
			//var_dump($this->client->__getLastRequestHeaders(), $this->client->__getLastRequest(), $this->client->__getLastResponseHeaders(), $this->client->__getLastResponse());
			throw new \Exception("Not connected!");
		}

		return $resp;
		*/
	}
	
	/**
	 * Returns a list of all hotels in the Solvex system
	 * No input
	 * 
	 * @return []
	 * @throws \Exception
	 */
	public function getHotels(int $cityId, int $county = null, int $country = null)
	{
		if (!$cityId)
			return null;

		$countryId = $country ?: -1;
		$countyId = $county ?: -1;

		// qvar_dump($countryId, $countyId);
		$hotels = $this->client->GetHotels(['countryKey' => $countryId, 'regionKey' => $countyId, 'cityKey' => $cityId]);
		$hotel_ratings = $this->getHotelRatings();
		if (!is_object($hotels) && (!is_object($hotels->GetHotelsResult)))
		{
			$this->addError('requestServiceInfo', [$service, $country, $county, $city]);
			return false; // error
		}
		
		$ret = [];

		if (isset($hotels->GetHotelsResult->Hotel))
		{
			if (!is_array($hotels->GetHotelsResult->Hotel))
				$hotels->GetHotelsResult->Hotel = [$hotels->GetHotelsResult->Hotel];
			foreach ($hotels->GetHotelsResult->Hotel as $hotel)
			{
				# Name[string]: "Adeona SKI & SPA"
				# ID[int]: 4719
				# Description[string]: "3*  (\\\\Bansko)"
				# NameLat[string]: ""
				# Code[string]: "BA042"
				# CodeLat[string]: ""
				# Unicode[string]: ""
				# City[stdClass#10]:

				# RegionID[int]: 21
				# PriceType[string]: "None"
				# CountCosts[int]: 0
				# CityID[int]: 9
				# HotelCategoryID[int]: 4

				// @storage.mergeBy Name,InTourOperatorId,Address.City,TourOperator

				$tf_hotel = new \Omi\Travel\Merch\Hotel();

				// step 1: Setup Merge By Elements
				$tf_hotel->setName($hotel->Name);

				$tf_hotel->setInTourOperatorId($hotel->ID);
				$tf_hotel->setTourOperator($this->getTourOperatorObject());

				$master_city = $this->getMasterCity($hotel->CityID);
				if (!($master_city))
				{
					echo "<div style='Hotel cannot be saved because city not yet in db - {$hotel->CityID}'></div>";
					continue;
				}
				$tf_hotel->setAddress(new \Omi\Address());
				$tf_hotel->Address->setCity($master_city);

				// step 2: Sync with the DB
				$sync_hotel = $this->syncHotelFromDB($tf_hotel);

				// step 3: populate the rest of the data

				# ensure we get all the reference fields from the DB
				if ($sync_hotel->getId())
					$sync_hotel->populate('Content'); // add elements as needed

				# setup the rest of the data
				$sync_hotel->setCode($hotel->Code);

				if (!($sync_hotel->Content))
					$sync_hotel->setContent(new \Omi\Cms\Content());
				$sync_hotel->Content->setContent($hotel->Description);

				$hr = $hotel_ratings[$hotel->HotelCategoryID];
				if (isset($hr['stars']))
					$sync_hotel->setStars($hr['stars']);
				
				if (!$sync_hotel->getId())
				{
					$hotelIsActive = true;
					if (function_exists("q_getTopHotelActiveStatus"))
						$hotelIsActive = q_getTopHotelActiveStatus($sync_hotel);
					$sync_hotel->setActive($hotelIsActive);
				}


				$ret[] = $sync_hotel;
			}
		}

		return $ret;
	}
	
	
	public function MakeReservation($params, $solvexInstance)
	{
		$hotel_params = $this->preparseFromEurositeParams($params);
		$new_params = [];
		
		$new_params["ResultIndex"] = (int)$params["IndexOffer"];
		$new_params["PaxInfo"] = [];
		if ($hotel_params["Rooms"]["Room"]["PaxNames"])
		{
			foreach ($hotel_params["Rooms"]["Room"]["PaxNames"] as $pname)
			{
				$person = [];
				$names = explode(" ", $pname["PaxName"][0][0]);
				$person["Title"] = ($pname["PaxName"]["TGender"] == "B") ? "Mr" : "Mrs";
				$person["FirstName"] = $names[0];
				$person["LastName"] = $names[1];
				$person["Type"] = $pname["PaxName"]["PaxType"] == "adult" ? "ADT" : "CHD";
				$person["BirthDate"] = $pname["PaxName"]["DOB"];
				$person["Gender"] = ($pname["PaxName"]["TGender"] == "B") ? "Male" : "Female";
				$new_params["PaxInfo"][] = $person;
			}
		}

		$new_params["HotelOptions"] = array("MealPlanIndex" => (int)$params["IndexMeal"]);
		$new_params["FlightOptions"] = array("OutboundIndex" => 0, "InboundIndex" => 0);
		$new_params["Status"] = "confirm";
		//$new_params["Status"] = "option";
		$new_params["ClientReference"] = "";
		$new_params["Client"] = null;
		$new_params["Notes"] = null;

		$r = $this->client->Book($new_params);
		return $r;
	}
	
	public function preparseFromEurositeParams($params, $index = 0)
	{
		$ret = [];
		
		$items = $params["BookingItems"][$index]["BookingItem"]["HotelItem"]  ? 
					$params["BookingItems"][$index]["BookingItem"]["HotelItem"] :  
						$params["BookingItems"][$index]["BookingItem"]["CircuitItem"];
		
		if ($items)
			foreach ($items as $itm)
			{
				if (is_array($itm) && $itm)
					foreach ($itm as $k=>$i)
					{
						$ret[$k] = $i;
					}
			}
		
		return $ret;
	}
	
	public function getHotelRatings()
	{
		if ($this->hotel_ratings !== null)
			return $this->hotel_ratings;
		
		$this->hotel_ratings = [];
		$hr = $this->client->GetRatings();
		if (isset($hr->GetRatingsResult->HotelCategory))
		{
			foreach ($hr->GetRatingsResult->HotelCategory as $rating)
			{
				if ($rating->ID < 1)
					continue;
				// Name[string]: "2*"
				// ID[int]: 2
				$matches = null;
				$stars = 0;
				$rc = preg_match("/\\b(\\d+)\\\\*/ui", $rating->Name, $matches);
				if ($rc && $matches)
					$stars = (int)$matches[1];
				
				$this->hotel_ratings[$rating->ID] = ['stars' => $stars, 'name' => $rating->Name];
			}
		}
		
		return $this->hotel_ratings;
	}
	
	public function getMasterCity($to_city_id)
	{
		$master_city = \Omi\City::QueryFirst("Id,Code,Name WHERE TourOperator.Id=? AND InTourOperatorId=?", 
								[$this->getTourOperatorObject()->getId(), $to_city_id]);
		return $master_city;
	}
	
	public function syncHotelFromDB(\Omi\Travel\Merch\Hotel $hotel)
	{
		// get merge by fields
		$h_id = $hotel->getIdFromMergeBy(true);
		$db_hotel = $h_id ? QQueryItem('Hotels', 'Name,InTourOperatorId,Address.City,TourOperator WHERE Id=?', [$h_id]) : null;
		return $db_hotel ?: $hotel;
	}
	
	public function getPensions()
	{
		$cache_path = \Omi\App::GetLogsMainDir('solvex-pensions') . sha1(json_encode([$this->ApiUrl, $this->ApiUsername, $this->ApiContext])).".php";
		if (file_exists($cache_path) && ( (time() - filemtime($cache_path)) < (60*60*24) )) // 24 hours cache
		{
			$__CACHE = null;
			require($cache_path);
			return $__CACHE;
		}
		
		$pansions = $this->client->GetPansions();
		$pansions = $pansions ? $pansions->GetPansionsResult->Pansion : null;
		$pansions = $pansions ? json_decode(json_encode($pansions), true) : null;
		
		if ($pansions)
		{
			$new_p = [];
			// index by key !
			foreach ($pansions as $p)
				$new_p[(int)$p['ID']] = $p;
			$pansions = $new_p;
		}
		else
			$pansions = [];
		
		$str = "<?php \$__CACHE = ".var_export($pansions, true).";\n";
		file_put_contents($cache_path, $str);

		return $pansions;
	}
}
