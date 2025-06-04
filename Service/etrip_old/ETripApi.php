<?php
	
namespace Omi\TF;
	
class ETripApi 
{
	use TOInterface_Util;

	public $ApiUrl = null;

	public $ApiUsername = null;

	public $ApiPassword = null;

	public $ApiContext = null;

	public $TourOperatorRecord = null;

	public $debug = false;

	private $countries;

	public $isProxy = true;

	public $geographyMap = [];
	
	protected $loadedGeography = null;

	protected $geoLoaded = false;
	
	protected $geoTypesMap = [
		"City" => ["City", "Regiuni"],
		"Country" => ["Country"],
		"Continent" => ["Continent"],
		"Area" => ["Area", "Localitate"]
	];

	public function initApi()
	{
		//$this->debug = 1;
		//$params = $this->debug ? ['trace' => 1] : array();
		$params = ['trace' => 1];
		$params["login"] = ($this->ApiUsername__ ?: $this->ApiUsername);
		$params["password"] = ($this->ApiPassword__ ?: $this->ApiPassword);

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

		// top handle
		$this->client->__top_handle__ = $this->TourOperatorRecord->Handle;

		$this->client->_stop_cookies_send = true;

		$this->client->_request_headers = [
			"Content-Type: text/xml; charset=utf-8",
			"Connection: Keep-Alive",
			"User-Agent: PHP-SOAP/" . phpversion(),
			"Accept: ",
			"Accept-Encoding: ",
			"Expect: "
		];

		$this->client->_set_soap_action_header = true;
		$this->setupCookies();
		$this->client->_validate_response = function($response)
		{
			return \Omi\TFuse\Api\TravelFuse::IsValidXML($response);
		};

		$this->client->_cache_get_key = function ($method, $params, $request, $location)
		{
			$initial_params = $use_params = $params[0];

			// if it's a hotel search, we return 2 keys
			$is_hotel_search = false;

			// cleanup cache params
			unset($use_params["_cache_use"]);
			unset($use_params["_cache_create"]);
			unset($use_params["_multi_request"]);
			unset($use_params["_cache_force"]);

			// to be verified
			unset($use_params["AgentCode"]);
			unset($use_params["ParamsFile"]);

			$orig_params = $use_params;
			if ($orig_params)
				ksort($orig_params);

			if ($method === "PackageSearch")
			{
				$is_hotel_search = $use_params["Hotel"] ? true : false;
				$use_params["Hotel"] = null;
				$use_params["Tour"] = null;
			}
			else if ($method === "HotelSearch")
			{
				$is_hotel_search = $use_params["Hotel"] ? true : false;
				$use_params["Hotel"] = null;
			}

			if ($use_params && is_array($use_params))
				ksort($use_params);

			TOStorage::KSortTree($use_params);
			TOStorage::KSortTree($orig_params);

			$ret = $is_hotel_search ? 
				[
					sha1(var_export([$method, $orig_params, $location, $this->TourOperatorRecord->Handle], true)), 
					sha1(var_export([$method, $use_params, $location, $this->TourOperatorRecord->Handle], true))
				] : 
				sha1(var_export([$method, $use_params, $location, $this->TourOperatorRecord->Handle], true));

			if ($_GET['q_show_for_cache'] && (defined('TO_SHOW_DUMP_IP') && (TO_SHOW_DUMP_IP === $_SERVER["REMOTE_ADDR"])))
			{
				qvardump("q_show: cache_hashes, initial_params, travel_params, search_params, method, location, tour_op_handle", 
					$ret, $initial_params, $use_params, $orig_params, $method, $location, $this->TourOperatorRecord->Handle);
			}

			return $ret;
		};
	}

	public function request($method, $params = null)
	{
		#ini_set('soap.wsdl_cache_enabled',0);
		#ini_set('soap.wsdl_cache_ttl',0);
		try {
			$resp = $this->client->{$method}($params);			
		}
		catch (\Exception $ex) {
			$toLogExData = [
				"\$method" => $method, 
				"\$TourOperator" => $this->TourOperatorRecord, 
				"reqXML" => $this->client->__getLastRequest(), 
				"respXML" => $this->client->__getLastResponse(),
				"reqHeaders" => $this->client->__getLastRequestHeaders(),
				"respHeaders" => $this->client->__getLastResponseHeaders(),
			];
			$this->logError($toLogExData, $ex);
			throw new \Exception(\Omi\App::Q_ERR_TP . ": " . $ex->getMessage());
		}
		#qvardump('$this->client->__getLastRequestHeaders()', $this->client->__getLastRequestHeaders(), $this->client->__getLastRequest());
		return $resp;
	}

	public function setupRequestCookies($reqId)
	{
		if (($topSessionData = Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle . '-' . $reqId)))
		{
			$prevCookies = $this->client->__getCookies();
			$this->client->_prevCookies = [];
			foreach ($prevCookies ?: [] as $prvCookieKey => $prvCookieVal)
				$this->client->_prevCookies[$prvCookieKey] = $prvCookieVal;

			foreach ($topSessionData as $key => $val)
			{
				$cookie_name = $key;
				if (is_array($val))
				{
					$cookie_str = $val[0];
				}
				else
					$cookie_str = $val;

				if (!isset($this->client->_prevCookies[$cookie_name]))
					$this->client->_prevCookies[$cookie_name] = null;
				$this->client->__setCookie($cookie_name, $cookie_str);
				$this->client->_cookies[$cookie_name] = $cookie_str;
			}
		}
	}

	public function restorePrevCookies()
	{
		foreach ($this->client->_prevCookies ?: [] as $prevCookieKey => $prevCookieValue)
		{
			$this->client->__setCookie($prevCookieKey, $prevCookieValue);
			$this->client->_cookies[$prevCookieKey] = $prevCookieValue;
		}
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
				}
				else
					$cookie_str = $val;
				$this->client->__setCookie($cookie_name, $cookie_str);
				$this->client->_cookies[$cookie_name] = $cookie_str;
			}
		}

		$refThis = $this;
		$this->client->_x_cookies_callback = function ($_cookies, $soap_handle, $reqId, $tfid) use ($refThis)
		{
			$cookies = $soap_handle->_cookies ?: $_cookies;
			Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle, $cookies);

			if ($tfid)
				Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle . '-' . $tfid, $cookies);
		};
	}

	public function setCachingAndMultiState($_cache_use = false, $_cache_create = false, $_multi_request = false)
	{
		return;
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
	public function apiGetCities($country)
	{
		$ret = $this->getGeography(null, "City");
		if ($country === null)
		{
			$new_list = array();
			if ($ret && count($ret))
			{
				foreach ($ret as $city)
				{
					$new_list[$city->Id] = $city;
				}
			}
			return $new_list;
		}
		
		$new_list = array();
		
		if ($ret && count($ret))
		{
			foreach ($ret as $city)
			{
				if ($city->CountryID == $country)
					$new_list[] = $city;
			}
		}
		
		return $new_list;
	}
	
	/**
	 * Get the list of countries from the ETrip system
	 * 
	 * @param stdClass $geometry
	 * @return array of stdClass
	 */
	public function getCountries($geography = null)
	{
		return $this->getGeography(null, "Country");
	}

	/**
	 * Map geography
	 * 
	 * @param type $geoItms
	 * @param type $parent
	 * 
	 * @throws \Exception
	 */
	public function mapGeography($geoItms, $parent = null)
	{		
		foreach ($geoItms ?: [] as $geoItm)
		{
			if (!$geoItm->Id)
				throw new \Exception("Geo item does not have id!");
			
			$geoItm->Parent = $parent;
			
			if (($parent->ChildLabel == 'Country') && (strtolower($geoItm->ChildLabel) == 'area'))
				$geoItm->ChildLabel = 'City';
			
			$this->geographyMap[$geoItm->Id] = $geoItm;
			
			if ($geoItm->Children && (count($geoItm->Children) > 0))
				$this->mapGeography($geoItm->Children, $geoItm);
		}
	}

	/**
	 * Searches in the geometry structure for a location with a specified ID
	 * Also adds to the return the Country of the location
	 * Recursive function!
	 * 
	 * @param type $geo_id The id of the location we are searching for
	 * @param type $geo The geometry structure we are looking in; if not specified the root geometry will be used
	 * @return boolean
	 */
	public function getGeographyItems($geoId)
	{		
		if (!($this->geoLoaded))
		{
			$this->loadedGeography = $this->request("GetGeography");			
			$this->mapGeography([$this->loadedGeography]);
			$this->geoLoaded = true;
		}

		$rootGeo = $this->loadedGeography;
		if ((!$rootGeo) || (!$rootGeo->Name) || ($rootGeo->Name !== "<root>"))
			throw new \Exception("ROOT GEO NOT OK");
		
		if (!($geoItm = $this->geographyMap[$geoId]))
			throw new \Exception("Geography itm not found by " . $geoId);

		$city = $this->getGeographyItmByType($geoItm, "City");
		$country = $this->getGeographyItmByType($geoItm, "Country");
		$continent = $this->getGeographyItmByType($geoItm, "Continent");

		// city, country, continent
		if ((!$city) && (!$country) && (!$continent))
		{
			// we need to get the area
			throw new \Exception("THE PACKAGE IS LINKED TO AREA!");
		}

		// city, country, continent
		return [$city, $country, $continent];
	}

	private function getGeographyItmByType($geoItm, $type)
	{
		if (!($geoItm->Parent))
			return false;

		if (in_array($geoItm->Parent->ChildLabel, ($this->geoTypesMap[$type] ?: [$type])))
			return $geoItm;

		return $this->getGeographyItmByType($geoItm->Parent, $type);
	}

	/**
	 * Test connectivity
	 * 
	 * @return boolean
	 * 
	 * @throws \Exception
	 */
	public function testConnectivity()
	{
		// get geography
		$resp = $this->request("GetGeography");
		if (!$resp)
			throw new \Exception("Not connected!");

		// get the first 
		$currentGeo = $resp;
		$wh_pos = 0;
		$lastChildren = null;
		$firstCountryDestination = null;
		while (!($firstCountryDestination))
		{
			$parent = $currentGeo;
			$currentGeo = $currentGeo->Children ? reset($currentGeo->Children) : null;
			if (!$currentGeo)
			{
				if ($lastChildren)
				{
					$nextChild = null;
					$c_lastChildren = count($lastChildren);
					for ($i = 0; $i < $c_lastChildren; $i++)
					{
						$child = $lastChildren[$i];
						if ($parent === $child)
						{
							$nextChild = $lastChildren[$i + 1];
							break;
						}
					}
					
					if (!$nextChild)
					{
						break;
					}
					else
						$currentGeo = $nextChild;
				}
				else
					break;	
			}

			$currentGeo->parent = $parent;
			$lastChildren = $parent->Children;

			#if ((strtolower($parent->ChildLabel) === "city"))
			if (isset($currentGeo->parent->parent) && (strtolower($currentGeo->parent->parent->ChildLabel) === "country"))
			{
				$firstCountryDestination = $currentGeo;
				break;
			}

			$wh_pos++;
			// issue - avoid recurssion
			if ($wh_pos > 1000)
				break;
		}

		if (!$firstCountryDestination)
			throw new \Exception("Cannot determine first callable destination");

		$checkIn = date("Y-m-d", strtotime("+ 10 days"));
		$currencyCode = "EUR";
		$rooms = [
			[
				"Adults" => 2,
				"ChildAges" => []
			]
		];

		$params = [
			"Destination" => $firstCountryDestination->Id, 
			"CheckIn" => $checkIn, 
			"Stay" => 4,
			"Rooms" => $rooms,
			"Currency" => $currencyCode,
			"MinStars" => 0,
			"ForPackage" => false,
			"PricesAsOf" => null,
			"ShowBlackedOut" => true,
		];

		if (($apiCode = ($this->TourOperatorRecord->ApiCode__ ?: $this->TourOperatorRecord->ApiCode)))
			$params["AgentCode"] = $apiCode;

		$resp = $this->request('HotelSearch', $params);
		
		return true;
	}

	/**
	 * A SOAP request to get the geography tree
	 * The georaphy tree contains continents, countries, cities, areas...
	 */
	public function getRawGeography()
    {
	    $result = $this->request('GetGeography');
    }
	
	/**
	 * Gets pieces of the geography tree, used to search for countries, cities, etc..
	 * 
	 * @param stdClass $geometry usually null, it asks for the geography from ETrip
	 * @param string $type Can be one of: City, Country, County, Continent, Area
	 * @return stdClass or array
	 */
	public function getGeography($geography = null, $type = "Country")
	{
		if ($geography)
		{
			$result = $geography;
		}
		else
		{
			$result = $this->request('GetGeography');
			$this->countries = array();
		}
		$comp_type = $type ?: "Country";
		$this->getGeography_Rec($result, $comp_type);
		return $this->countries;
	}
	
	public function getGeography_Rec($result, $comp_type = "Country")
	{
		if (!is_array($result))
		{
			if ($result && $result->ChildLabel == $comp_type)
			{
				if ($result && $result->Children)
				{
					foreach ($result->Children as $ch)
					{
						if ($comp_type == "City")
						{
							$ch->CountryID = $result->Id;
						}
						$this->countries[] = $ch;
					}
				}
			}
		}

		if ($result && $result->Children)
		{
			foreach ($result->Children as $ch)
			{
				$this->getGeography_Rec($ch, $comp_type);
			}
		}
		return $this->countries;
	}

	/**
	 * Searches for available packages in the ETrip system
	 * @param array() $params
	 * @return array() of stdClass
	 * @throws \Exception
	 */
	public function PackageSearch($params)
	{
		$result = null;
		$packageSearchEx = null;
		try
		{
			if (($apiCode = ($this->TourOperatorRecord->ApiCode__ ?: $this->TourOperatorRecord->ApiCode)))
				$params["AgentCode"] = $apiCode;
			
			$result = $this->request('PackageSearch', $params);
		}
		catch (\Exception $ex)
		{
			$packageSearchEx = $ex;
		}

		if ($packageSearchEx)
		{
			$this->logError([
				"callMethod" => "PackageSearch",
				"\$params" => $params,
				"reqHeaders" => $this->client->__getLastRequestHeaders(),
				"respHeaders" => $this->client->__getLastResponseHeaders(),
				"reqXML" => $this->client->__getLastRequest(), 
				"respXML" => $this->client->__getLastResponse()
			], $packageSearchEx);
			if ($packageSearchEx->getMessage() == "looks like we got no XML document")
			{
				return null;
			}
			throw $packageSearchEx;
		}

		return $result;
	}
	
	/**
	 * Searches for available hotels and their prices
	 * 
	 * @param array() $params - The search params
	 * @return array() of stdClass
	 * @throws \Exception
	 */
	public function HotelSearch($params)
	{
		try
		{
			if (($apiCode = $this->TourOperatorRecord->ApiCode))
				$params["AgentCode"] = $apiCode;
			$result = $this->request('HotelSearch', $params);
		}
		catch (\Exception $ex)
		{
			$result = null;
		}
		
		return $result;
	}
	
	/**
	 * Returns a list of all hotels in the ETrip system
	 * No input
	 * 
	 * @return array()
	 * @throws \Exception
	 */
	public function GetHotels()
	{
		return $this->request('GetHotels');
	}
	
	/**
	 * Returns a list with all packages in the ETrip system
	 * No input
	 * 
	 * @return array()
	 * @throws \Exception
	 */
	public function GetPackages()
	{
		return $this->request('GetPackages');
	}

	public function MakeReservation($params, $etripInstance, $order)
	{		
		$hotel_params = $this->preparseFromEurositeParams($params);
		$new_params = array();
		
		$new_params["ResultIndex"] = (int)$params["IndexOffer"];
		$new_params["PaxInfo"] = array();
		$orderCallParams = $order->CallParams ? json_decode($order->CallParams, true) : null;
		$now_date = date_create(($orderCallParams && $orderCallParams["CheckIn"]) ? date("Y-m-d", strtotime($orderCallParams["CheckIn"])) : date("Y-m-d"));
		if ($hotel_params["Rooms"]["Room"]["PaxNames"])
		{
			foreach ($hotel_params["Rooms"]["Room"]["PaxNames"] as $pname)
			{
				$isChild = ($pname["PaxName"]["PaxType"] == "child");
				$person = [];
				$person["Title"] = ($pname["PaxName"]["TGender"] == "B") ? "Mr" : "Mrs";
				/*
				$names = explode(" ", $pname["PaxName"][0][0]);
				$person["FirstName"] = array_shift($names);
				$person["LastName"] = implode(" ", $names);
				 */
				$person["FirstName"] = $pname["PaxName"]["Firstname"];
				$person["LastName"] = $pname["PaxName"]["Name"];
				$years = 0;
				if ($isChild)
				{
					$dob_diff = date_diff($now_date, date_create($pname["PaxName"]["DOB"]));
					$years = (int)$dob_diff->format('%y');
				}
				#$person["Type"] = (!$isChild) ? "ADT" : (($years > 2) ? "CHD" : "INF");
				$person["Type"] = (!$isChild) ? "ADT" : (($years < 2) ? "INF" : "CHD");
				$person["Birthdate"] = $pname["PaxName"]["DOB"];
				$person["Gender"] = ($pname["PaxName"]["TGender"] == "B") ? "Male" : "Female";
				$new_params["PaxInfo"][] = $person;
			}
		}

		$new_params["HotelOptions"] = array("MealPlanIndex" => (int)$params["IndexMeal"]);
		$new_params["FlightOptions"] = array("OutboundIndex" => 0, "InboundIndex" => 0, 'JourneyIndices' => []);
		$new_params["Status"] = "confirm";
		//$new_params["Status"] = "option";
		$new_params["ClientReference"] = "";

		if (defined("SEND_BOOKING_CLIENT_DETAILS") && SEND_BOOKING_CLIENT_DETAILS)
		{
			$customer = $order->BillingTo->Company ? $order->BillingTo->Company : $order->BillingTo;
			$new_params["Client"] = [
				"Title" => "Dl",
				"FirstName" => $customer->Firstname ?: "",
				"LastName" => $customer->Name ?: "",
				"BirthDate" => "",
				"Email" => $customer->Email ?: "",
				"Phone" => $customer->Phone ?: "",
				"IDInfo" => "",
				"BankName" => $customer->Bank ?: "",
				"BankAccount" => $customer->BankAccount ?: "",
				"City" => null,
				"Address1" => $customer->HeadOffice ? $customer->HeadOffice->Details : "",
				"Address2" => "",
				"Address3" => "",
				"AddressCity" => "",
				"PostalCode" => "",
				"Country" => "",
			];
		}
		else
		{
			$new_params["Client"] = null;
		}
		$new_params["Notes"] = null;
		$r = $this->request('Book', $new_params);
		return $r;
	}

	public function preparseFromEurositeParams($params, $index = 0)
	{
		$ret = array();
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
}