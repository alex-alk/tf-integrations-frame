<?php

namespace Omi\TF;

use App\Support\Http\CurlRequest;
use App\Support\Http\CurlResponseMutable;
use App\Support\Http\RequestLog;
use App\Support\Http\RequestLogCollection;
use App\Support\Http\ResponseInterfaceCollection;
use App\Support\Request;
use DOMDocument;
use Utils\Utils;

trait TOInterface_Util
{	
	public function simpleObjToArr($obj) 
	{
		if (is_object($obj))
			$obj = (array) $obj;
		if (is_array($obj))
		{
			$new = array();
			foreach($obj as $key => $val) 
				$new[$key] = $this->simpleObjToArr($val);
		}
		else
			$new = $obj;
		return $new;
	}

	public function simpleXML2Array($xmlChild, $skipItem = false)
	{
		if (is_scalar($xmlChild))
			return $xmlChild;
		$namesapces = $xmlChild->getNamespaces(true);
		$ret = [];
		$allAttrs = [];
		if (($existingAttrs = $xmlChild->attributes()))
			$allAttrs[] = $existingAttrs;
		foreach ($namesapces ?: [] as $ns)
		{
			if (($nsAtttrs = $xmlChild->attributes($ns)))
				$allAttrs[] = $existingAttrs;
		}
		if ($allAttrs && (count($allAttrs) > 0))
		{
			$toSaveAttrs = [];
			foreach ($allAttrs as $attrs)
			{
				foreach ($attrs ?: [] as $attrk => $attrv)
					$toSaveAttrs[$attrk] = $attrv . "";
			}
			if ($toSaveAttrs)
				$ret["@attrs"] = $toSaveAttrs;
		}
		$allChildren = [];
		if (($children = $xmlChild->children()))
			$allChildren[] = $children;
		foreach ($namesapces ?: [] as $ns)
		{
			if (($nsChildren = $xmlChild->children($ns)))
				$allChildren[] = $nsChildren;
		}
		$hasNodeChild = false;
		if ($allChildren && (count($allChildren) > 0))
		{
			foreach ($allChildren as $children)
			{
				$cntProps = [];
				$hasItm = false;
				foreach ($children as $k => $child)
				{
					if (!isset($cntProps[$k]))
						$cntProps[$k] = 0;
					$cntProps[$k]++;
				}
				
				foreach ($children as $k => $child)
				{
					$hasNodeChild = true;
					$childData = $this->simpleXML2Array($child, $skipItem);
					if ($skipItem && ($k === "item"))
					{
						$ret[] = $childData;
					}
					else
					{
						if ($cntProps[$k] > 1)
						{
							if (!isset($ret[$k]))
								$ret[$k] = [];
							$ret[$k][] = $childData;
						}
						else
							$ret[$k] = $childData;
					}

				}				
			}
		}
		if (!$hasNodeChild)
		{
			$value = $xmlChild . "";
			$toSetValue = (((!empty($value)) || is_numeric($value)) ? $value . "" : null);
			if (isset($ret["@attrs"]))
			{
				if ($toSetValue)
					$ret[] = $toSetValue;
			}
			else
				$ret = $toSetValue;
		}
		return $ret;
	}

	public function logError(array $data = [], \Exception $ex = null, int $keep = 1)
	{
		// \Omi\TFuse\Api\TravelFuse::DoDataLoggingError($this->TourOperatorRecord->Handle, $data, $ex, null, $keep);
	}

	public function logDataSimple(string $label, array $data)
	{
		// if (\Omi\Comm\Order::$InSendingToSystemProcess && \Omi\Comm\Order::$SendToSystemOrderId)
		// 	$label = "booking_log." . \Omi\Comm\Order::$SendToSystemOrderId . "." . $label;
		
		// \Omi\TFuse\Api\TravelFuse::DoDataLoggingSimple($this->TourOperatorRecord->Handle, $label, $data);
	}

	public function logData(string $label, array $data, int $keep = 30, bool $err = false)
	{

		// if (\Omi\Comm\Order::$InSendingToSystemProcess && \Omi\Comm\Order::$SendToSystemOrderId)
		// 	$label = "booking_log." . \Omi\Comm\Order::$SendToSystemOrderId . "." . $label;

		// \Omi\TFuse\Api\TravelFuse::DoDataLogging($this->TourOperatorRecord->Handle, $label, $data, $keep, $err);
	}

	public function getResourcesDir()
	{
		//return \Omi\App::GetResourcesDir($this->TourOperatorRecord->Handle);
		$dir = Utils::getCachePath() . '/'. $this->TourOperatorRecord->Handle . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		return $dir;
	}

	public function getTravelResourcesDir($topHandle = null)
	{
		if ($topHandle === null)
			$topHandle = $this->TourOperatorRecord->Handle;		
		return \Omi\App::GetTravelResourcesDir($topHandle);
	}

	public function getTravelResourcesUrl($topHandle = null)
	{
		if ($topHandle === null)
			$topHandle = $this->TourOperatorRecord->Handle;
		return \Omi\App::GetTravelResourcesUrl($topHandle);
	}

	public function getTravelImagesDir($topHandle = null)
	{
		if ($topHandle === null)
			$topHandle = $this->TourOperatorRecord->Handle;
		return \Omi\App::GetTravelImagesDir($topHandle);
	}

	public function getTravelImagesUrl($topHandle = null)
	{
		if ($topHandle === null)
			$topHandle = $this->TourOperatorRecord->Handle;
		return \Omi\App::GetTravelImagesUrl($topHandle);
	}

	public function getSharedResourcesDir($all = false)
	{
		$relPath = ($topHandle = $this->TourOperatorRecord->Handle) . "/" . ($all ? "all" : md5(($this->TourOperatorRecord->ApiUrl__ ?: $this->TourOperatorRecord->ApiUrl) . "~"
			. $this->TourOperatorRecord->ApiContext . "~"
			. $this->TourOperatorRecord->ApiUsername . "~"
			. $this->TourOperatorRecord->ApiPassword));
		return \Omi\App::GetResourcesMainDir($relPath);
	}
	
	public function getDataFromSimpleCache($params, $url = null)	
	{
		$loadedFromCache = false;
		$resp = null;
		// get cache file path
		$cacheCile = $this->getSimpleCacheFile($params);

		$cfLastModified = ($fExists = file_exists($cacheCile)) ? filemtime($cacheCile) : null;
		$cacheTimeLimit = time() - $this->cacheTimeLimit;

		// if exists - last modified
		if (($fExists) && ($cfLastModified >= $cacheTimeLimit))
		{
			$resp = file_get_contents($cacheCile);
			$loadedFromCache = true;
		}
		
		return [$resp, $cacheCile, $loadedFromCache];
	}

	public function getSimpleCacheFile($params = [], $url = null, $format = "json")
	{
		$cacheDir = $this->getResourcesDir() . "cache/";
		if (!is_dir($cacheDir))
			qmkdir($cacheDir);
		return $cacheDir . "cache_" . md5(implode("|", $params) . "|" . $url . "|" . $format) . "." . $format;
	}

	public static function GetCsvDocument($url, $force = false, $time = "+ 12hrs")
	{
		$name = preg_replace('/[^\w\d]/ui', '', $url);
		$localFile = rtrim(\QAutoload::GetTempWebPath(), "\\/") . "/{$name}.csv";

		if (!file_exists($localFile) || (filemtime($localFile) < strtotime($time)) || $force)
		{
			$fileHandle = fopen($localFile, 'w+');
			$ch = q_curl_init_with_log($url);
			q_curl_setopt_with_log($ch, CURLOPT_FILE, $fileHandle);	// output to file
			q_curl_setopt_with_log($ch, CURLOPT_FOLLOWLOCATION, 1);
			q_curl_setopt_with_log($ch, CURLOPT_TIMEOUT, 1000);	// some large value to allow curl to run for a long time
			q_curl_setopt_with_log($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
			$raw = q_curl_exec_with_log($ch);
			curl_close($ch);
			if ($raw === false)
				throw new \Exception("Invalid response from server - " . curl_error($ch));
			fclose($fileHandle);
		}

		return $localFile;
	}

	/**
	 * sort array recursive
	 * 
	 * @param type $array
	 * @return boolean
	 */
	public static function KSortTree(&$array)
	{
		if (!is_array($array))
			return false;
		ksort($array);
		foreach ($array as $k => $v)
			static::KSortTree($array[$k]);
		return true;
	}

	public function getTransportItem($title, $transportType, $departureTime, $arrivalTime, $departureCity, $currency, 
		$departureAirport = null, $returnAirport = null, $isReturn = false)
	{
		// departure transport item
		$transportMerch = new \stdClass();
		$transportMerch->Title = $title;
		$transportMerch->DepartureTime = $departureTime;
		$transportMerch->ArrivalTime = $arrivalTime;
		$transportMerch->TransportType = $transportType;
		$transportMerch->Category = new \stdClass();
		$transportMerch->Category->Code = $isReturn ? 'other-inbound' : 'other-outbound';

		$transportMerch->From = new \stdClass();
		$transportMerch->From->City = $departureCity;

		// departure transport merch
		if ($departureAirport)
			$transportMerch->DepartureAirport = $departureAirport;

		if ($returnAirport)
			$transportMerch->ReturnAirport = $returnAirport;

		// departure transport itm
		$transportItm = new \stdClass();
		$transportItm->Merch = $transportMerch;
		$transportItm->Quantity = 1;
		$transportItm->Currency = $currency;
		$transportItm->UnitPrice = 0;
		$transportItm->Gross = 0;
		$transportItm->Net = 0;
		$transportItm->InitialPrice = 0;
		$transportItm->DepartureDate = date('Y-m-d', strtotime($departureTime));
		$transportItm->ArrivalDate = date('Y-m-d', strtotime($arrivalTime));

		// for identify purpose
		#$transportItm->Id = $transportMerch->Id;

		return $transportItm;
	}

	public function getEmptyBoardItem($offer)
	{
		// board
		$boardType = new \stdClass();
		$boardType->Id = "no_meal";
		$boardType->Title = "Fara masa";
		$boardMerch = new \stdClass();
		//$boardMerch->Id = $roomOffer->mealKey;
		$boardMerch->Title = "Fara masa";
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
		return $boardItm;
	}

	public function getApiTransferItem($offer, $category)
	{
		$transferMerch = new \stdClass();
		$transferMerch->Code = uniqid();
		$transferMerch->Category = $category;
		$transferMerch->Title = "Transfer inclus";
		$transferItem = new \stdClass();
		$transferItem->Merch = $transferMerch;
		$transferItem->Currency = $offer->Currency;
		$transferItem->Quantity = 1;
		$transferItem->UnitPrice = 0;
		$transferItem->Availability = "yes";
		$transferItem->Gross = 0;
		$transferItem->Net = 0;
		$transferItem->InitialPrice = 0;
		// for identify purpose
		$transferItem->Id = $transferMerch->Id;
		return $transferItem;
	}

	public function getApiAirpotTaxesItem($offer, $category)
	{
		$airportTaxesMerch = new \stdClass();
		$airportTaxesMerch->Title = "Taxe aeroport";
		$airportTaxesMerch->Code = uniqid();
		$airportTaxesMerch->Category = $category;
		$airportTaxesItem = new \stdClass();
		$airportTaxesItem->Merch = $airportTaxesMerch;
		$airportTaxesItem->Currency = $offer->Currency;
		$airportTaxesItem->Quantity = 1;
		$airportTaxesItem->UnitPrice = 0;
		$airportTaxesItem->Availability = "yes";
		$airportTaxesItem->Gross = 0;
		$airportTaxesItem->Net = 0;
		$airportTaxesItem->InitialPrice = 0;
		// for identify purpose
		$airportTaxesItem->Id = $airportTaxesItem->Id;
		return $airportTaxesItem;
	}
	
	/**
	 * Returns the cancel fees from an array like:
	 *	endDate1 => amount1,
	 *  endDate2 => amount2
	 * 
	 * @param array $cancelFees
	 * @return []
	 */
	public function getCancelFeesFromFormat(array $cancelFees, \stdClass $currency) : array
	{
		$prevFee = null;
		$todayTime = strtotime(date("Y-m-d"));
		$dateStart = null;
		$ret = [];
		foreach ($cancelFees ?: [] as $dateEnd => $amount)
		{
			$dateEnd = date("Y-m-d", strtotime($dateEnd));

			#echo 'Date Start: ' . $dateStart . '<br/>';
			if (($dateStart === null) || ($todayTime > strtotime($dateStart)))
				$dateStart = date("Y-m-d");

			$cpObj = new \stdClass();
			$cpObj->DateStart = $dateStart;
			$cpObj->DateEnd = $dateEnd;

			#$cpObj->Price = ($amount * $price)/100;
			$cpObj->Price = $amount;
			$cpObj->Currency = $currency;
			$ret[] = $cpObj;
			$prevFee = $cpObj;
			$dateStart = date('Y-m-d', strtotime("+1 day", strtotime($dateEnd)));
		}
		return $ret;
	}

	public function xmlIsValid($xmlStr)
	{
		if (empty(trim($xmlStr)))
			return false;
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$doc = new DOMDocument();
		$doc->loadXML($xmlStr);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		return empty($errors);
	}

	public function jsonIsValid($jsonStr)
	{
		json_decode($jsonStr);
		return (json_last_error() == JSON_ERROR_NONE);
    	}
	
	public function setRequestDumpData_JsonFile(&$reqData, $prop, $useReqID, $jsonFile)
	{
		$this->setRequestDumpData_File($reqData, $prop, $useReqID, $jsonFile);
	}

	public function setRequestDumpData_XmlFile(&$reqData, $prop, $useReqID, $xmlFile)
	{
		$this->setRequestDumpData_File($reqData, $prop, $useReqID, $xmlFile);
	}

	public function getDecodedResponseFromXML($ret)
	{
		$rawXml = null;
		$isValidXml = false;
		try
		{
			$rawXml = simplexml_load_string($ret);
			if ((!$rawXml) && ($isValidXml = $this->xmlIsValid($ret)))
			{
				$dom = new \DOMDocument();
				$dom->loadXML($ret);
				$rawXml = simplexml_load_string($dom->saveXML(), NULL, NULL, 'http://schemas.xmlsoap.org/soap/envelope/');
			}
			return $this->simpleXML2Array($rawXml);
		}
		catch (\Exception $ex)
		{
			throw new \Exception('Return XML is not valid - ' . $ex->getMessage());
		}	
	}

	public function setRequestDumpData_File(&$reqData, $prop, $useReqID, $file, $isJson = false)
	{
		return;

		if (!($saveData = $reqData[$prop]))
			return;
		if (!($isJson))
		{
			$rawXml = null;
			$isValidXml = false;
			try
			{
				$rawXml = simplexml_load_string($saveData);
				if ((!$rawXml) && ($isValidXml = $this->xmlIsValid($saveData)))
				{
					$dom = new \DOMDocument();
					$dom->loadXML($saveData);
					$rawXml = simplexml_load_string($dom->saveXML(), NULL, NULL, 'http://schemas.xmlsoap.org/soap/envelope/');
				}
			}
			catch (\Exception $ex)
			{
				echo "<div style='color: red;'>Err on decoding xml: {$ex->getMessage()}</div>";
			}
		}
		#if ($isJson || ($rawXml || $isValidXml))
		{
			file_put_contents($file, $saveData);
			$reqData[$prop . "_File"] = \QWebRequest::GetBaseUrl() . $file;
		}
		unset($reqData[$prop]);
		$decodedData = $isJson ? json_decode($saveData) : ($rawXml ? $this->simpleXML2Array($rawXml) : null);
		if (!($_POST['__q__in_remote_req']))
		{
			ob_start();
			qvardump($decodedData);
			$str = ob_get_clean();
		}
		else
			$str = qvardump($decodedData);
		$dumpFile = \Omi\App::GetLogsDir('requests_dump') . $useReqID . "." . $prop . ".html";
		file_put_contents($dumpFile, $str);
		$reqData[$prop . "_Dump_File"] = \QWebRequest::GetBaseUrl() . $dumpFile;
	}

	public function setRequestDumpData($filter, $data, $newID = null)
	{
		return;

		if (!($qReqID = $filter["__q__request_id"]))
			return;
		$requestsDumpDir = \Omi\App::GetLogsDir('requests_dump');
		if (!$newID)
			$newID = uniqid("", true);
		$useReqID = $qReqID . "_" . $newID;
		$requestXMLFile = $requestsDumpDir . $useReqID . ".Request.xml";
		$respXmlFile = $requestsDumpDir . $useReqID . ".Response.xml";
		$requestJsonFile = $requestsDumpDir . $useReqID . ".Request.json";
		$respJsonFile = $requestsDumpDir . $useReqID . ".Response.json";
		if ($data["xmlREQ"])
			$this->setRequestDumpData_XmlFile($data, "xmlREQ", $useReqID, $requestXMLFile);
		if ($data["xmlRESP"])
			$this->setRequestDumpData_XmlFile($data, "xmlRESP", $useReqID, $respXmlFile);
		if ($data["jsonREQ"])
			$this->setRequestDumpData_JsonFile($data, "jsonREQ", $useReqID, $requestJsonFile);
		if ($data["jsonRESP"])
			$this->setRequestDumpData_JsonFile($data, "jsonRESP", $useReqID, $respJsonFile);
		// requests
		\Omi\App::$RequestsCollectedData[$qReqID][$newID] = $data;
	}

	function setupInMulti_CURL(string $url, string $method, array $request = null, array $filter = null, bool $logData = false, $headers = [])
	{
		if (!$this->soap_client)
		{
			$this->soap_client = new \Omi\Util\SoapClientAdvanced(null, [
				"login" => ($this->ApiUsername__ ?: $this->ApiUsername), 
				"password" => ($this->ApiPassword__ ?: $this->ApiPassword)
			]);
			$this->soap_client->__top_handle__ = $this->TourOperatorRecord->Handle;
			if ($this->_validate_response)
				$this->soap_client->_validate_response = $this->_validate_response;		
			if ($this->_cache_get_key)
				$this->soap_client->_cache_get_key = $this->_cache_get_key;
		}
		$this->soap_client->_cache_last_method =  $method;
		if ($filter["__request_data__"])
		{
			$this->soap_client->_cache_last_data = $filter["__request_data__"];
			unset($filter["__request_data__"]);
		}
		$this->soap_client->_cache_last_args = $filter;

		$refThis = $this;
		$curl = q_curl_init_with_log();

		$func_setup_curl = function(&$curl, $url, $request) use ($refThis, $headers)
		{
			#$headers = [];
			q_curl_setopt_with_log($curl, CURLOPT_URL, $url);

			// send xml request to a server
			q_curl_setopt_with_log($curl, CURLOPT_SSL_VERIFYHOST, 0);
			q_curl_setopt_with_log($curl, CURLOPT_SSL_VERIFYPEER, 0);
			q_curl_setopt_with_log($curl, CURLINFO_HEADER_OUT, true);

			if ($request !== null)
			{
				q_curl_setopt_with_log($curl, CURLOPT_POST, 1);
				q_curl_setopt_with_log($curl, CURLOPT_POSTFIELDS, $request);
			}

			q_curl_setopt_with_log($curl, CURLOPT_RETURNTRANSFER, 1);
			q_curl_setopt_with_log($curl, CURLOPT_FOLLOWLOCATION, 1);

			q_curl_setopt_with_log($curl, CURLOPT_VERBOSE, 0);
			q_curl_setopt_with_log($curl, CURLOPT_HTTPHEADER, $headers);

			list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($refThis->TourOperatorRecord);
			if ($proxyUrl)
				q_curl_setopt_with_log($curl, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
			#if ($proxyPort)
			#	q_curl_setopt_with_log($curlHandle, CURLOPT_PROXYPORT, $proxyPort);
			if ($proxyUsername)
				q_curl_setopt_with_log($curl, CURLOPT_PROXYUSERNAME, $proxyUsername);
			if ($proxyPassword)
				q_curl_setopt_with_log($curl, CURLOPT_PROXYUSERPWD, $proxyPassword);

			$this->_reqHeaders = [];
			$headers_out = &$this->_reqHeaders;
			q_curl_setopt_with_log($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers_out) {
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
		};

		$curl_callback = $this->soap_client->__curl_callback = function () use($func_setup_curl, $curl, $url, $request)
		{
			$func_setup_curl($curl, $url, $request);
			return $curl;
		};

		$this->soap_client->__request_callback = function () use ($refThis, $curl_callback, $url, $request)
		{
			$curl = $curl_callback($curl, $url, $request);
			if (!\Omi\Util\SoapClientAdvanced::$InMultiRequest || $refThis->Storage::$Exec)
			{
				$data = q_curl_exec_with_log($curl);
				if ($data === false)
					throw new \Exception("Invalid response from server - " . curl_error($curl));
			}
			return $data;
		};

		return $this->soap_client->__doRequest($request, $url, "get", "1.0");
	}

	public function rmDir($dir)
	{
		$dirFiles = scandir($dir);
		foreach ($dirFiles ?: [] as $file) 
		{
			if (($file == ".") || ($file == ".."))
				continue;
			$ffp = rtrim($dir, "\\/") . "/" . $file;
			if (is_dir($ffp))
				$this->rmDir($ffp);
			else
				unlink($ffp);
		}
		rmdir($dir);
	}

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public function api_getOffers_forIndividualSync(array $filter = null)
	{
		return $this->api_getOffers($filter);
	}
	
	public function api_getHotelDetails_forIndividualSync(array $filter = null)
	{
		return $this->api_getHotelDetails($filter);
	}

	public static function markReportStartpoint($filter, $type)
	{
		// if (!isset($filter['skip_report']))
		// {
		// 	$output = "TOP_RAW: Process for {$type} started at " . date('Y-m-d H:i:s');
		// 	$outputLen = strlen($output);
		// 	$output = "<strong>{$output}</strong>";
		// 	$output .= "<br/>" . str_repeat('=', $outputLen) . "<br/>";
		// 	echo str_repeat('>', $outputLen) . "<br/>" . $output;
		// }
	}

	public static function markReportQuickError($filter, $text, $offset = 0)
	{
		static::markReportData($filter, $text, [], $offset, true);
	}

	public static function markReportError($filter, $format, $values = [], $offset = 0)
	{
		//static::markReportData($filter, $format, $values, $offset, true);
	}

	public static function markReportData($filter, $format, $values = [], $offset = 0, $error = false)
	{
		// if (!isset($filter['skip_report']))
		// 	echo '<div style="padding-left: ' . $offset . 'px;' . ($error ? 'color: red;' : '') 
		// 	. '">TOP_RAW: ' . call_user_func_array('sprintf', array_merge([$format], $values)) . "</div>";
	}

	public static function markReportEndpoint($filter, $type)
	{
		// if (!isset($filter['skip_report']))
		// {
		// 	$output = "TOP_RAW: Process for {$type} ended at " . date('Y-m-d H:i:s');
		// 	$outputLen = strlen($output);
		// 	$output = "<strong>{$output}</strong>";
		// 	$output .= "<br/>" . str_repeat('>', $outputLen) . "<br/><br/>";
		// 	echo $output;
		// }
	}
}
