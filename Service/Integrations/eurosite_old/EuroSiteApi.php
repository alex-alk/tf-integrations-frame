<?php

namespace Omi\TF;
/**
 * This is just to help with our example. We allow the browser to make some requests without any authentication.
 * This is by no means a good idea! It's just to keep our example simple.
 * 
 * This trait exposes CRUD to API/Ajax calls without making any creditentials checks.
 */
class EuroSiteApi
{
	use TOInterface_Util;

	public $cacheTimeLimit = 60 * 60 * 48;

	public $ApiUrl = null;

	public $ApiUsername = null;

	public $ApiPassword = null;

	public $ApiContext = null;

	public $TourOperatorRecord = null;

	public static $_CurrentResponse = null;

	protected $_curl_handle;

	public $soap_client = null;

	public function initApi()
	{
		$params = ["login" => ($this->ApiUsername__ ?: $this->ApiUsername), "password" => ($this->ApiPassword__ ?: $this->ApiPassword)];
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);

		if ($proxyUrl)
			$params["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");

		#if ($proxyPort)
		#	$params["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$params["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$params["proxy_password"] = $proxyPassword;

		// do nothing here for now
		$this->soap_client = new \Omi\Util\SoapClientAdvanced(null, $params);
		
		$this->soap_client->__top_handle__ = $this->TourOperatorRecord->Handle;

		$this->soap_client->_validate_response = function($response)
		{
			return \Omi\TFuse\Api\TravelFuse::IsValidXML($response);
		};

		$this->soap_client->_cache_get_key = function ($method, $params, $request, $location)
		{
			$initial_params = $params;
			// if it's a hotel search, we return 2 keys
			$is_hotel_search = false;
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

			$orig_params = $params;
			ksort($orig_params);

			if (($method === "getPackageNVPriceRequest") || 
				($method === "getHotelPriceRequest") ||
				($method === "CircuitSearchRequest"))
			{
				$is_hotel_search = ($params["ProductCode"] || $params["ProductName"]) ? true : false;
				$params["ProductCode"] = null;
				$params["ProductName"] = null;
				$params["ProductType"] = null;
			}

			if ($params)
				ksort($params);

			TOStorage::KSortTree($params);
			TOStorage::KSortTree($orig_params);

			$ret = $is_hotel_search ? 
				[sha1(var_export([$method, $orig_params, $location, $this->TourOperatorRecord->Handle], true)), sha1(var_export([$method, $params, $location, $this->TourOperatorRecord->Handle], true))] :
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

				$this->client->__setCookie($cookie_name, $cookie_str);
				$this->client->_cookies[$cookie_name] = $cookie_str;
			}
		}

		$this->client->_x_cookies_callback = function ($_cookies, $soap_handle)
		{
			$cookies = $soap_handle->_cookies ?: $_cookies;
			foreach ($cookies as $k => $v)
				Q_SESSION(["soap-sid-" . $this->TourOperatorRecord->Handle, $k], $v);
		};
	}

	/**
	 * 
	 * @param type $requestType
	 * @param type $requestParams
	 * @param type $xmlRequest
	 * @param type $saveAttrs
	 * @param type $encode
	 * @return type
	 * @throws Exception
	 * @throws \Exception
	 */
	public function makeRequest($requestType, $requestParams, $xmlRequest, $saveAttrs = true, $encode = true, $useCache = false)
	{
		if (!$xmlRequest)
			throw new Exception("Xml Request not provided!");
		$req_method = is_string($requestType) ? $requestType : json_encode($requestType);

		if ($requestParams["__request_data__"])
		{
			$this->soap_client->_cache_last_data = $requestParams["__request_data__"];
			unset($requestParams["__request_data__"]);
		}

		$this->soap_client->_cache_last_method =  $req_method;
		$this->soap_client->_cache_last_args = $requestParams;

		$log = [];

		try 
		{
			$t0 = microtime(true);
			self::$_CurrentResponse = null;
			
			list($data, $ch) = $this->request(($this->ApiUrl__ ?: $this->ApiUrl), $xmlRequest, $log, $requestParams, $useCache);
			
			self::$_CurrentResponse = $data;
			$t1 = microtime(true);

			//echo "Request took: ".round(($t1 - $t0) * 1000, 4)." ms<br/>\n";
			if (($data === null) || ($data === false))
			{
				if ($ch)
				{
					$error = curl_error($ch);
					//curl_close($ch);
				}
				throw new \Exception("Problem when receiving data from eurosite!" . $error);
			}

			//echo "json_decode took: ".round(($t1 - $t0) * 1000, 4)." ms<br/>\n";
			if ($ch)
			{
				//curl_close($ch);
			}
			#echo $data . "<br/>";

			$ret =  ["success" => true, "data" => $this->decodeResponse($data, $saveAttrs, $encode, $req_method), "rawResp" => $data, "curl_handle" => $ch];

			return $ret;
		}
		catch (Exception $ex)
		{
			throw new \Exception(\Omi\App::Q_ERR_TP . ": " . $ex->getMessage());
			#return ["success" => false, "data" => 'Message: ' . $e->getMessage()];
		}
	}

	public function decodeResponse($data, $saveAttrs = true, $encode = true, $req_method = null)
	{
		try
		{
			try {
				// do fix for nbsp
				$data = str_replace(["&nbsp"], ["&#160;"], $data);
				$xml = simplexml_load_string($encode ? utf8_encode($data) : $data);
			} catch (\Exception $ex) {
				throw new \Exception("Invalid Response from api provider!");
			}

			$data = json_decode(json_encode($xml), true);

			if ($saveAttrs && $xml)
				TOStorage::SaveAttributes($xml, $data);

			$t1 = microtime(true);

			if ($data["ResponseDetails"] && $data["ResponseDetails"]["Errors"] && $data["ResponseDetails"]["Errors"]["Error"])
			{
				$err = isset($data["ResponseDetails"]["Errors"]["Error"]["ErrorText"]) ?
						$data["ResponseDetails"]["Errors"]["Error"]["ErrorText"] :
					reset($data["ResponseDetails"]["Errors"]["Error"])["ErrorText"];
				throw new \Exception($err);
			}
			else if ($req_method && ($resp_method = str_replace("Request", "Response", $req_method)) && $data["ResponseDetails"][$resp_method]["Error"]["ErrorText"])
			{
				throw new \Exception(is_array($data["ResponseDetails"][$resp_method]["Error"]["ErrorText"]) ? 
					reset($data["ResponseDetails"][$resp_method]["Error"]["ErrorText"]) : $data["ResponseDetails"][$resp_method]["Error"]["ErrorText"]);

			}
		}
		catch (\Exception $ex)
		{
			throw new \Exception(\Omi\App::Q_ERR_TP . ": " . $ex->getMessage());
		}
		
		return $data;
	}

	public function testConnectivity_doRawRequest($xmlRequest)
	{
		$headers = array (
			API_HEADERS1,
			API_HEADERS2,
			API_HEADERS3,
			API_HEADERS4,
			API_HEADERS5
		);

		$curl_handle = q_curl_init_with_log();

		q_curl_setopt_with_log($curl_handle, CURLOPT_URL, ($this->ApiUrl__ ?: $this->ApiUrl));
		q_curl_setopt_with_log($curl_handle, CURLOPT_POST, 1);

		// send xml request to a server
		q_curl_setopt_with_log($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
		q_curl_setopt_with_log($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
		q_curl_setopt_with_log($curl_handle, CURLINFO_HEADER_OUT, true);

		q_curl_setopt_with_log($curl_handle, CURLOPT_POSTFIELDS, $xmlRequest);
		q_curl_setopt_with_log($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		q_curl_setopt_with_log($curl_handle, CURLOPT_FOLLOWLOCATION, 1);

		q_curl_setopt_with_log($curl_handle, CURLOPT_VERBOSE, 0);
		q_curl_setopt_with_log($curl_handle, CURLOPT_HTTPHEADER, $headers);
		q_curl_setopt_with_log($curl_handle, CURLOPT_HEADER, 1);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curl_handle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curl_handle, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curl_handle, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curl_handle, CURLOPT_PROXYUSERPWD, $proxyPassword);
		
		$data = q_curl_exec_with_log($curl_handle);
		if ($data === false)
			throw new \Exception("Invalid response from server - " . curl_error($curl_handle));

		$curl_info = curl_getinfo($curl_handle);
		// $resp_header = substr($data, 0, $curl_info['header_size']);
		return substr($data, $curl_info['header_size']);
	}

	public function testConnectivity()
	{
		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Request RequestType="getCountryRequest">'
				. '<AuditInfo>'
					. '<RequestId>001</RequestId>'
					. '<RequestUser>' . htmlspecialchars($this->ApiUsername__ ?: $this->ApiUsername) . '</RequestUser>'
					. '<RequestPass>' . htmlspecialchars($this->ApiPassword__ ?: $this->ApiPassword) . '</RequestPass>'
					. '<RequestTime>'.date(DATE_ATOM).'</RequestTime>'
					. '<RequestLang>EN</RequestLang>'
				. '</AuditInfo>'
				. '<RequestDetails>'
					. '<getCountryRequest></getCountryRequest>'
				. '</RequestDetails>'
			. '</Request>';

		$resp_body = $this->testConnectivity_doRawRequest($xmlRequest);

		try
		{
			$ret = $this->decodeResponse($resp_body, true, true, "getCountryRequest");
			$connected = ($ret && $ret["ResponseDetails"] && $ret["ResponseDetails"]["getCountryResponse"] && $ret["ResponseDetails"]["getCountryResponse"]["Country"]);	
		}
		catch (\Exception $ex)
		{
			// leave it to test the cities too
		}

		if (!$connected)
		{
			echo $resp_body;

			$countryCode = 'RO';
			$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
				<Request RequestType="getCityRequest">
					<AuditInfo>
						<RequestId>001</RequestId>
						<RequestUser>' . htmlspecialchars($this->ApiUsername__ ?: $this->ApiUsername) . '</RequestUser>
						<RequestPass>' . htmlspecialchars($this->ApiPassword__ ?: $this->ApiPassword) . '</RequestPass>
						<RequestTime>'.date(DATE_ATOM).'</RequestTime>
						<RequestLang>EN</RequestLang>
					</AuditInfo>
					<RequestDetails>
						<getCityRequest CountryCode="' . $countryCode . '"></getCityRequest>
					 </RequestDetails>
				</Request>';
			
			$resp_body2 = $this->testConnectivity_doRawRequest($xmlRequest);
			$ret2 = $this->decodeResponse($resp_body2, true, true, "getCityRequest");

			$connected = ($ret2 && $ret2["ResponseDetails"] && $ret2["ResponseDetails"]["getCityResponse"] && $ret2["ResponseDetails"]["getCityResponse"]["City"]);
			if (!$connected)
			{
				echo $resp_body2;
			}
			
		}

		return $connected;
	}

	public function request($url, $xmlRequest, &$log = [], $requestParams = null, $use_cache = false)
	{
		/*
		if ($this->_curl_handle)
			curl_reset($this->_curl_handle);
		else
			$this->_curl_handle = q_curl_init_with_log();
		$curl_handle = $this->_curl_handle;
		*/
		
		$rq_method = $this->soap_client->_cache_last_method;
		$rq_params = $this->soap_client->_cache_last_args;
		
		
		$reqid = $rq_method . "_" . uniqid();

		if ($use_cache)
		{
			$xmlRequestTemp = $xmlRequest;

			// remove request time cause it's variable
			$xmlRequestTemp = preg_replace('#(<RequestTime>(.*?)</RequestTime>)#', '', $xmlRequestTemp);

			// get cache file path
			$cache_file = $this->getSimpleCacheFile(['xmlRequest' => $xmlRequestTemp, 'requestParams' => $requestParams], $url, "xml");

			$cf_last_modified = ($f_exists = file_exists($cache_file)) ? filemtime($cache_file) : null;
			$cache_time_limit = time() - $this->cacheTimeLimit;

			// if exists - last modified
			if (($f_exists) && ($cf_last_modified >= $cache_time_limit))
			{
				$data = file_get_contents($cache_file);
				// return 
				return [$data, null];
			}
		}

		$curl_handle = q_curl_init_with_log();

		
		$use_cust_cache = in_array($rq_method, ["getItemPaymentDLSRequest", "getItemFeesRequest"]);

		$tourOperator = $this->TourOperatorRecord;
		$curl_callback = $this->soap_client->__curl_callback = function () use($curl_handle, $tourOperator, $url, $xmlRequest, &$log)
		{
			$headers = array (
				API_HEADERS1,
				API_HEADERS2,
				API_HEADERS3,
				API_HEADERS4,
				API_HEADERS5
			);

			q_curl_setopt_with_log($curl_handle, CURLOPT_URL, $url);
			q_curl_setopt_with_log($curl_handle, CURLOPT_POST, 1);

			// send xml request to a server
			q_curl_setopt_with_log($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
			q_curl_setopt_with_log($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
			q_curl_setopt_with_log($curl_handle, CURLINFO_HEADER_OUT, true);

			q_curl_setopt_with_log($curl_handle, CURLOPT_POSTFIELDS, $xmlRequest);
			q_curl_setopt_with_log($curl_handle, CURLOPT_RETURNTRANSFER, 1);
			q_curl_setopt_with_log($curl_handle, CURLOPT_FOLLOWLOCATION, 1);

			q_curl_setopt_with_log($curl_handle, CURLOPT_VERBOSE, 0);
			q_curl_setopt_with_log($curl_handle, CURLOPT_HTTPHEADER, $headers);

			list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($tourOperator);
			if ($proxyUrl)
				q_curl_setopt_with_log($curl_handle, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
			#if ($proxyPort)
			#	q_curl_setopt_with_log($curl_handle, CURLOPT_PROXYPORT, $proxyPort);
			if ($proxyUsername)
				q_curl_setopt_with_log($curl_handle, CURLOPT_PROXYUSERNAME, $proxyUsername);
			if ($proxyPassword)
				q_curl_setopt_with_log($curl_handle, CURLOPT_PROXYUSERPWD, $proxyPassword);
			
			q_curl_setopt_with_log($curl_handle, CURLOPT_HEADER, 1);
			$log["RequestSentAt"] = date("Y-m-d H:i:s");
	
			if (\Omi\Comm\Order::$InAfterPaid && false)
			{
				$now = \DateTime::createFromFormat('U.u', microtime(true));
				ob_start();
				qvardump('attach the call', $now->format("m-d-Y H:i:s.u"), $url, $xmlRequest, $headers);
				file_put_contents('Dump_' . \QWebRequest::$RequestId . '_AfterPaid.html', ob_get_clean(), FILE_APPEND);
			}

			// $data = q_curl_exec_with_log($ch);
			return $curl_handle;
		};
		$rfThis = $this;
		$this->soap_client->__request_callback = function () use ($curl_callback, $url, $xmlRequest, $rfThis, $requestParams, $use_cust_cache, &$log, $rq_method, $rq_params, $reqid)
		{
			$curl = $curl_callback($url, $xmlRequest, $log);
			$log["RequestSentAt"] = date("Y-m-d H:i:s");
			$doReq = true;
			$data = null;
			$request_id = null;
			$use_cust_cache = ((strpos($xmlRequest, "getItemPaymentDLSRequest") !== false) || (strpos($xmlRequest, "getItemFeesRequest") !== false));
			// use custom cache - only on some methods
			if ($use_cust_cache)
			{
				// get the request id for the current request
				$request_id = $rfThis->soap_client->__getCacheRequestKey($rfThis->soap_client->_cache_last_method, 
					$rfThis->soap_client->_cache_last_args, serialize($requestParams), $requestParams);
				// create an empty context
				$context = \Omi\Util\SoapClientContext::CreateSoapContext(false, true, false, true);
				// check if we have cache for the request id
				if ($context->hasCache($request_id))
				{
					// get the data from cache
					$data = $context->getFromCache($request_id);
					$curl_info = null;				
					//qvardump("\$resp_body", $respParts);
					// don't execute request if we have cache
					$doReq = false;
				}
			}

			// if we need to do the request - just execute
			if ($doReq)
			{
				if (!\Omi\Util\SoapClientAdvanced::$InMultiRequest || \Omi\TF\EuroSite::$Exec)
				{
					$data = q_curl_exec_with_log($curl);
					$curl_info = curl_getinfo($curl);
					if ($data === false)
					{
						$ex = new \Exception("Invalid response from tour operator server - " . curl_error($curl));
						\Omi\App::ThrowExWithTypePrefix($ex, \Omi\App::Q_ERR_TP);
					}

					$respParts = preg_split("/\\r?\\n\\r?\\n/us", $data, 2);
					// if failed try to get it by header size provided by curl
					if ($respParts === false)
					{
						$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
						$hasHeaders = ($header_size !== false);
					}
					else
						$hasHeaders = (count($respParts) > 1);
					$resp_header = null;
					$resp_body = null;
					if ($hasHeaders)
					{
						$resp_header = substr($data, 0, $curl_info['header_size']);
						$resp_body = substr($data, $curl_info['header_size']);
						#$req_content = $resp_body;
					}
					else
					{
						$resp_body = $data;
						#$req_content = $resp_body = $_response;
					}
					
					$data = $resp_body;
					
					if (!\Omi\TFuse\Api\TravelFuse::IsValidXML($data))
					{
						$ex = new \Exception("Response from tour operator server is not a valid xml - " . $data);
						\Omi\App::ThrowExWithTypePrefix($ex, \Omi\App::Q_ERR_TP);
					}
					if ($use_cust_cache)
						$context->cacheResponse($data, $request_id);
				}
				else
				{
					$data = "";
					$curl_info = [];
				}
			}
			//$respParts = preg_split("/\\r?\\n\\r?\\n/us", $data, 2);
			$resp_body = $data;
			return [$resp_body, $curl, $data, $xmlRequest];
		};

		$request_resp = $this->soap_client->__doRequest($requestParams ? serialize($requestParams) : $xmlRequest, $url, "", "", 0, ["xmlRequest" => $xmlRequest]);
		
		q_remote_log_sub_entry([
						[
							'Timestamp_ms' => (string)microtime(true),
							'Traces' => (new \Exception())->getTraceAsString(),
							'Tags' => ['tag' => 'eurosite - request - ', '$url' => $url,],
							'Data' => ['request' => $requestParams ? serialize($requestParams) : $xmlRequest, '$request_resp' => $request_resp, ],
						]
					]);
		
		if (is_array($request_resp))
		{

			// save cache file for later usage
			if ($use_cache)
			{
				file_put_contents($cache_file, reset($request_resp));
			}

			return $request_resp;
		}
		else
		{
			if ($use_cache)
			{
				file_put_contents($cache_file, $request_resp);
			}

			// @todo : grab curl also
			return [$request_resp, $curl_handle];
		}
	}
	
	/**
	 * Do request
	 * 
	 * @param string $requestType
	 * @param mix $params[]
	 * @param type $saveAttrs
	 * 
	 * @return type
	 */
	public function doRequest($requestType, $params = null, $saveAttrs = false, $useCache = false)
	{
		try
		{
			if ((!$this->ApiUsername) || (!$this->ApiPassword))
				throw new \Exception("Login credentials are missing!");
			$reqType = (is_array($requestType) ? $requestType[0] : $requestType);
			
			if (is_string($reqType) && is_array($params))
				static::fix_params_before_request($reqType, $this->TourOperatorRecord->Handle ?? null, $params);
			
			$xmlrequest = $this->getXMLRequest($requestType, $params);
			$encode = true;

			// make request
			$ret = $this->makeRequest($reqType, $params, $xmlrequest, $saveAttrs, $encode, $useCache);
			
			if (is_string($reqType) && isset($ret['data']) && is_array($ret['data']))
				static::fix_response_after_request($reqType, $this->TourOperatorRecord->Handle ?? null, $ret);

			$ret["rawReq"] = $xmlrequest;
		}
		finally
		{
			if ($this->TourOperatorRecord->Handle === 'eximtur_eurosite')
			{
				try
				{
					# file_put_contents("/home/omi/_debug/eurosite_log/TourOperatorRecord.log", ($this->TourOperatorRecord->Handle ?? 'n/a') . "\n", FILE_APPEND);
					
					$log_dir = "/home/omi/_debug/eurosite_log/{$this->TourOperatorRecord->Handle}/{$reqType}/";
					if (!is_dir($log_dir))
						mkdir($log_dir, 0777, true);
					$log_path = $log_dir . uniqid("", true) . ".html";
					ob_start();
					qvar_dump(['$requestType' => $requestType, '$params' => $params, 
								'$saveAttrs' => $saveAttrs, '$useCache' => $useCache, 
								'$ret' => $ret]);
					file_put_contents($log_path, ob_get_clean());
				}
				catch (\Exception $exxx)
				{
					# nothing
				}
			}
		}	
		
		return $ret;
	}

	/**
	 * 
	 * @param string|array $requestType
	 * @param array $params
	 * @return boolean|string
	 */
	public function getXMLRequest($requestType, $params = null)
	{
		// remove params request daqta from xml request
		if (isset($params["__request_data__"]))
			unset($params["__request_data__"]);

		if (!$this->ApiUsername || !$this->ApiPassword)
			throw new \Exception("Login credentials are missing!");

		if (!$requestType)
			return false;

		if (is_array($requestType))
		{
			$requestTypeAttributes = "";
			foreach ($requestType as $key => $value) 
			{
				if (is_numeric($key))
					$requestTypeEndTag = $requestTypeBeginTag = $value;
				else 
					$requestTypeAttributes .= " $key='$value'";
			}
		}
		else
			$requestTypeBeginTag = $requestTypeEndTag = $requestType;

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
			<Request RequestType="'.$requestTypeBeginTag.'">
				<AuditInfo>
					<RequestId>001</RequestId>
					<RequestUser>'.htmlspecialchars($this->ApiUsername__ ?: $this->ApiUsername).'</RequestUser>
					<RequestPass>'.htmlspecialchars($this->ApiPassword__ ?: $this->ApiPassword).'</RequestPass>
					<RequestTime>'.date(DATE_ATOM).'</RequestTime>
					<RequestLang>EN</RequestLang>
				</AuditInfo>
				<RequestDetails>
					<'.$requestTypeBeginTag.$requestTypeAttributes.'>';
		if ($params)
			$xmlRequest .=  self::FillInParams($params);

		$xmlRequest .= '</'.$requestTypeEndTag.'>
				 </RequestDetails>
			</Request>';
		return $xmlRequest;
	}

	public static function FillInParams($params) 
	{
		$xmlRequest = "";
		foreach ($params as $tag => $value)
		{
			$numeric_key = is_numeric($tag);
			$array_value = is_array($value);
			
			if ($numeric_key && $array_value)
			{
				$xmlRequest .= self::FillInParams($value);
			}
			else if ($array_value) // key is string
			{
				$xmlRequest .= "<$tag";
				$text = "";
				$more_elements = [];
				foreach ($value as $k => $v)
				{
					if (is_array($v))
						$more_elements[$k] = $v;
					else if (is_numeric($k))
						$text .= $v;
					else // if (is_scalar($v))
						$xmlRequest .= " {$k}=\"".htmlentities($v)."\"";
				}
				if ($text || $more_elements)
				{
					$xmlRequest .= ">";
					if ($text)
						$xmlRequest .= "".htmlspecialchars($text)."";
					if ($more_elements)
						$xmlRequest .= self::FillInParams($more_elements);
					$xmlRequest .= "</$tag>";
				}
				else
					$xmlRequest .= " />";				
			}
			else if ($numeric_key) // and not scalar
			{
				$xmlRequest .= htmlspecialchars($value);
			}
			else // scalar value with string key
				$xmlRequest .= "<$tag>".htmlspecialchars($value)."</$tag>";
		}
		//var_dump($xmlRequest);
		return $xmlRequest;
	}
	
	public static function fix_params_before_request(string $reqType, string $to_handle, array &$params = null)
	{

		if (!(EuroSite::$Config[$to_handle]['prefix_hotels_with_reseller_code'] ?? false))
			return;
		
		$patterns = [];
		$identification = [];

		switch ($reqType)
		{
			case "AddBookingRequest":
			{
				$patterns = ['BookingItems.$N.BookingItem.HotelItem.$N.ProductCode'];
				$identification = ['BookingItems.$N.BookingItem' => ['$N.TourOpCode' => 'TourOpCode']];
				break;
			}
			case "getItemFeesRequest":
			{
				$patterns = ['BookingItems.BookingItem.HotelItem.$N.ProductCode',
								'BookingItems.$N.BookingItem.HotelItem.$N.ProductCode'];
				$identification = ['BookingItems.BookingItem.$N' => ['TourOpCode' => 'TourOpCode'],
									'BookingItems.$N.BookingItem.$N' => ['TourOpCode' => 'TourOpCode'],];
				break;
			}
			case "getHotelPriceRequest":
			{
				$patterns = ['ProductCode'];
				$identification = ['TourOpCode' => 'TourOpCode'];
				# throw new \Exception('No way to idf RESELLER CODE !!!');
				break;
			}
			case "getItemPaymentDLSRequest":
			{
				$patterns = ['BookingItems.$N.BookingItem.HotelItem.$N.ProductCode', 
								'BookingItems.BookingItem.HotelItem.$N.ProductCode'];
				$identification = ['BookingItems.$N.BookingItem' => ['$N.TourOpCode' => 'TourOpCode'],
									'BookingItems.BookingItem' => ['$N.TourOpCode' => 'TourOpCode']];
				break;
			}
			case "getProductInfoRequest":
			{
				$patterns = ['ProductCode'];
				$identification = ['TourOpCode' => 'TourOpCode'];
				
				break;
			}
			
			# these need no action
			case "getBookingRequest":
			case "getOwnCityRequest":
			case "getCityRequest":
			case "getOwnHotelsRequest":
			{
				break;
			}
			default:
				break;
		}

		if ($patterns)
		{
			static::fix_replace_pattern($params, $patterns, $identification, 
				function (&$matched_pattern, array $idf_data, $key_of_match, string $path) use ($reqType, &$params, $identification)
				{
					list ($tour_op_code, $to_hotel_id) = explode("~", $matched_pattern, 2);
					
					if ((!empty($tour_op_code)) && (!empty($to_hotel_id)))
					{
						$matched_pattern = $to_hotel_id;
						switch ($reqType)
						{
							case "getHotelPriceRequest":
							case "getProductInfoRequest":
							{
								$params['TourOpCode'] = $tour_op_code;
								break;
							}
							default:
								break;
						}
					}
				});
		}
	}
			
	public static function fix_response_after_request(string $reqType, string $to_handle, array &$ret)
	{
		if (!(EuroSite::$Config[$to_handle]['prefix_hotels_with_reseller_code'] ?? false))
			return;
		
		$patterns = [];
		$identification = [];

		switch ($reqType)
		{
			case "getHotelPriceRequest":
			{
				$patterns = ['data.ResponseDetails.getHotelPriceResponse.Hotel.$N.Product.ProductCode', 
							 'data.ResponseDetails.getHotelPriceResponse.Hotel.Product.ProductCode'];
				$identification = ['data.ResponseDetails.getHotelPriceResponse.Hotel.Product' => ['TourOpCode' => 'TourOpCode'],
								   'data.ResponseDetails.getHotelPriceResponse.Hotel.$N.Product' => ['TourOpCode' => 'TourOpCode'],];
				break;
			}
			case "getBookingRequest":
			{
				$patterns = ['data.ResponseDetails.getBookingResponse.BookingItems.BookingItem.ProductCode', 
							 'data.ResponseDetails.getBookingResponse.BookingItems.$N.BookingItem.ProductCode', ];
				$identification = ['data.ResponseDetails.getBookingResponse.BookingItems.BookingItem' => ['TourOpCode' => 'TourOpCode'],
									'data.ResponseDetails.getBookingResponse.BookingItems.$N.BookingItem' => ['TourOpCode' => 'TourOpCode']];
				break;
			}
			case "getOwnHotelsRequest":
			{
				$patterns = ['data.ResponseDetails.getOwnHotelsResponse.Hotel.$N.Product.HotelCode',
							 'data.ResponseDetails.getOwnHotelsResponse.Hotel.Product.HotelCode',];
				$identification = ['data.ResponseDetails.getOwnHotelsResponse.Hotel' =>    ['Touropcode' => 'TourOpCode'],
								   'data.ResponseDetails.getOwnHotelsResponse.Hotel.$N' => ['Touropcode' => 'TourOpCode'],];
				
				break;
			}
			case "getProductInfoRequest":
			{
				$patterns = ['data.ResponseDetails.getProductInfoResponse.Product.ProductCode'];
				$identification = ['data.ResponseDetails.getProductInfoResponse.Product' => ['TourOpCode' => 'TourOpCode']];
				break;
			}
			
			# these need no action
			case "AddBookingRequest":
			case "getItemFeesRequest":
			case "getItemPaymentDLSRequest":
			case "getOwnCityRequest":
			case "getCityRequest":
			{
				break;
			}
			default:
				break;
		}

		if ($patterns)
		{
			static::fix_replace_pattern($ret, $patterns, $identification, 
				function (&$matched_pattern, array $idf_data, $key_of_match, string $path)
				{
					if (empty($idf_data['TourOpCode']))
						throw new \Exception('Unable to find TourOpCode');
					if (substr($matched_pattern, 0, strlen($idf_data['TourOpCode'] . "~")) !== $idf_data['TourOpCode'] . "~")
						$matched_pattern = $idf_data['TourOpCode'] . "~" . $matched_pattern;
				});
		}
	}
	
	static function fix_replace_pattern(array &$data, array $patterns_list, array $identification, callable $replace_callback, string $path = '', int $max_depth = 64, array $idf_data = [])
	{
		if ($max_depth < 0)
			return;
		
		foreach ($data as $k => &$v)
		{
			$k_path = ((!empty($path)) ? $path.'.' : '') . ((is_numeric($k) && ((string)((int)$k) === (string)$k)) ? '$N' : $k);
			# echo $k_path, "\n";
			
			if (isset($identification[$k_path]))
			{
				if (is_array($v) && is_array($identification[$k_path]))
					$sub_idf_data = static::fix_find_identification($v, $identification[$k_path]);
				else if (!is_array($identification[$k_path]))
					$sub_idf_data[$identification[$k_path]] = $v;
				
				foreach ($sub_idf_data ?? [] as $subid_k => $subid_v)
					$idf_data[$subid_k] = $subid_v;
			}
			
			if (in_array($k_path, $patterns_list))
			{
				$replace_callback($v, $idf_data, $k, $k_path);
			}
			
			if (is_array($v))
				static::fix_replace_pattern($v, $patterns_list, $identification, $replace_callback, $k_path, $max_depth - 1, $idf_data);
		}
	}
	
	static function fix_find_identification(array &$data, array $identification, string $path = '', int $max_depth = 64)
	{
		if ($max_depth < 0)
			return;
		
		$ret = [];
		
		foreach ($data as $k => &$v)
		{
			$k_path = ((!empty($path)) ? $path.'.' : '') . ((is_numeric($k) && ((string)((int)$k) === (string)$k)) ? '$N' : $k);
			# echo $k_path, "\n";
			
			if (isset($identification[$k_path]))
			{
				$ret[$identification[$k_path]] = $v;
			}
			
			if (is_array($v))
			{
				$sub_ret = static::fix_find_identification($v, $identification, $k_path, $max_depth - 1);
				if (($sub_ret ?? null))
				{
					foreach ($sub_ret as $srk => $srv)
						$ret[$srk] = $srv;
				}
			}
		}
		
		return $ret;
	}
	
}