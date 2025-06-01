<?php

namespace Omi\TF;

/**
 * @TODO
 */
trait TOInterface_API
{
	public $_use_direct_call_on_doRestAPI_Remote_Exec = false;

	public $_use_api_url = false;
	
	public $_force_remote_call = false;
	
	public $_extra_post_data = [];
	
	public $_decode_array = false;
	
	public $_debug_response = false;

	public $soapClients = [];

	public $mode = null;

	public $requestsData = [];
	
	public $dumpdatauuid = null;

	public function runRestAPI()
	{
		$t0 = microtime(true);
		
		try
		{
			$call_method = filter_input(INPUT_GET, 'method', FILTER_SANITIZE_STRING);
			if (substr($call_method, 0, strlen('api_')) !== 'api_')
				throw new \Exception('Not allowed - ' . getcwd());
			if (!method_exists($this, $call_method))
				throw new \Exception('Method not found: ' . $call_method);

			$filter = null;
			if (isset($_POST['filter']))
			{
				$max_filter_len = (4 * 1024 * 1024);
				$filter_str = $_POST['filter'];
				if (strlen($filter_str) > $max_filter_len)
					throw new \Exception('Filter is larger than ' . $max_filter_len . ' bytes');
				$filter = json_decode($filter_str, true);
				if ($filter === false)
					throw new \Exception('JSON Decode failed (filter)');
			}

			if (isset($_POST['cookies']))
			{
				$max_cookies_len = (128 * 1024);
				$cookies_str = $_POST['cookies'];
				if (strlen($cookies_str) > $max_cookies_len)
					throw new \Exception('Cookies are larger than ' . $max_cookies_len . ' bytes');
				$cookies = json_decode($cookies_str);
				if ($cookies === false)
					throw new \Exception('JSON Decode failed (cookies)');
				$this->api_setCookies($cookies);
			}

			if (isset($_POST['session_id']))
			{
				$max_session_id_len = (128 * 1024);
				$session_id = $_POST['session_id'];
				if (strlen($session_id) > $max_session_id_len)
					throw new \Exception('Session id is larger than ' . $max_session_id_len . ' bytes');

				if ($session_id === false)
					throw new \Exception('Session Id does not exist!');

				$this->api_setSessionId($session_id);
			}

			if (isset($_POST['credentials']))
			{
				$max_credentials_length = (5 * 16 * 1024);
				if (strlen($_POST['credentials']) > $max_credentials_length)
					throw new \Exception('Credentials are larger than ' . $max_credentials_length . ' bytes');
				$credentials = json_decode($_POST['credentials']);
				if ($credentials === false)
					throw new \Exception('JSON Decode failed (credentials)');

				foreach ($credentials ?: [] as $k => $v)
					$this->{$k} = $v;
			}

			if (isset($_POST['const']))
			{
				$max_credentials_length = (5 * 16 * 1024);
				if (strlen($_POST['const']) > $max_credentials_length)
					throw new \Exception('Constants are larger than ' . $max_credentials_length . ' bytes');
				$const = json_decode($_POST['const']);
				if ($const === false)
					throw new \Exception('JSON Decode failed (const)');

				foreach ($const ?: [] as $k => $v)
				{
					if (($v === "true") || ($v === "false"))
						$v = (bool)$v;
					if (!defined($k))
						define($k, $v);
				}
			}

			if (isset($_POST['topdata']))
			{
				$max_credentials_length = (5 * 16 * 1024);
				if (strlen($_POST['topdata']) > $max_credentials_length)
					throw new \Exception('Top data is larger than ' . $max_credentials_length . ' bytes');
				$topdata = json_decode($_POST['topdata']);
				if ($topdata === false)
					throw new \Exception('JSON Decode failed (topdata)');

				$this->TourOperatorRecord = new \stdClass();
				foreach ($topdata ?: [] as $k => $v)
					$this->TourOperatorRecord->{$k} = $v;
			}

			// receive the flags for determine if in booking process
			if (isset($_POST['InSendingToSystemProcess']) && class_exists('Omi\\Comm\\Order'))
			{
				\Omi\Comm\Order::$InSendingToSystemProcess = $_POST['InSendingToSystemProcess'];
			}

			if (isset($_POST['SendToSystemOrderId']) && (class_exists('Omi\\Comm\\Order')))
			{
				\Omi\Comm\Order::$SendToSystemOrderId = $_POST['SendToSystemOrderId'];
			}

			ob_start();
			$resp = $this->$call_method($filter);
			$call_output = ob_get_clean();
			
			if ($resp && is_array($resp))
				$resp = array_values($resp);
			$response_data = ['response' => $resp ?: [], 'call_output' => $call_output];
		}
		catch (\Exception $ex)
		{
			if ($this->dev_mode)
			{
				$response_data['error'] =
					[
						'message' => $ex->getMessage(),
						'trace' => $ex->getTraceAsString(),
						//'debug' => debug_backtrace()
					];
			}
			else
				$response_data = ['error' => ['message' => $ex->getMessage()]];
		}
		finally
		{
			$response_data['cookies'] = $this->api_getCookies();
		}

		// in dev mode - save dump data
		#if ($this->dev_mode)
		#	$response_data["dump_data"] = qvar_getdump_data();

		// requests collected data
		if (\Omi\App::$RequestsCollectedData)
			$response_data["requests_collected_data"] = json_encode(\Omi\App::$RequestsCollectedData);
		
		# $t0 = microtime(true);
		$resp_str = \QModel::QToJSon($response_data); # json_encode($response_data);
		# header('Content-type: application/json');
		# echo json_encode(['error' => ['message' => (microtime(true) - $t0) . " | ".strlen($resp_str)." |" . Q_GetJsonLastError() . "\n<pre>\n" . $resp_str]]);
		# return;
		
		if ($resp_str === false)
		{
			$resp_str = json_encode(['error' => ['message' => Q_GetJsonLastError()]]);
		}
		else
		{
			
		}

		header('Content-type: application/json');
		echo $resp_str;
	}
	
	public function cleanupBeforeGenerateCacheKey($method, &$origParams, &$params, $location)
	{
		
	}
	
	public function decodeCachedResponse($requestID)
	{
		return (class_exists('\Omi\Util\SoapClientContext')) ? \Omi\Util\SoapClientContext::CreateSoapContext()->getFromCache($requestID) : null;
	}

	public function initApi()
	{
		
	}

	public function setupCookies()
	{
		if (($topSessionData = Q_SESSION("soap-sid-" . $this->TourOperatorRecord->Handle)))
		{
			foreach ($topSessionData as $key => $val)
			{
				$cookie_name = $key;
				if (is_array($val))
					$cookie_str = $val[0];
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
	 * Do rest api
	 * @param string $method
	 * @param array $filter
	 * @return type
	 */
	public function doRestAPI($method, $filter = null)
	{
		#$this->_force_exec_call = true;
		$forceExecCall = ($this->_force_exec_call || ($filter['_force_exec_call'] || $_GET['_force_exec_call']));
		$bookingOrSetupSearch = (isset($filter['__booking_search__']) || isset($filter['__on_setup_search__']));
		$doRemoteRequests = ($this->_force_remote_call || ((!$forceExecCall) && (!$this->useMultiInTop) && in_array($method, ["api_getOffers"]) && (!$bookingOrSetupSearch)));

		#$storage = $this->Storage;

		unset($filter['_force_remote_call']);
		unset($filter['_force_exec_call']);

		// current search id & save requets dump
		if (\Omi\App::$CurrentSearchID && \Omi\App::$SaveRequestsDump)
		{
			$postDataReqID = md5($method . "_" . json_encode($filter));
			$filter["__q__request_id"] = $postDataReqID;
		}
		
		if ($doRemoteRequests)
		{
			$ret = $this->doRestAPI_Remote($method, $filter);
		}
		else
		{
			$ret = $this->doRestAPI_Exec($method, $filter);
		}

		return $ret;
	}

	public function doRestAPI_Exec_GetCalls($method, $filter)
	{
		return null;
	}

	/**
	 * Exec a rest api - locally if the mapping is done on our server
	 * 
	 * @param type $method
	 * @param type $filter
	 * @return type
	 * @throws type
	 */
	public function doRestAPI_Exec($method, $filter)
	{
		try
		{
			$this->requestsData = [];
			$calls = $this->doRestAPI_Exec_GetCalls($method, $filter);
			if ($calls === null)
			{				
				// go further with the exec
				$ret = $this->{$method}($filter);

				if ($filter['rawResponse'])
					return $ret;

				if ($ret && is_array($ret))
					$ret = array_values($ret);
			}
			else
			{
				$__t = microtime(true);

				$ret = [];
				$itms = [];

				foreach ($calls ?: [] as $callData)
				{
					list($callMethod, $callFilter) = $callData;
					$callRet = $this->{$callMethod}($callFilter);

					if ($callRet && is_array($callRet))
						$callRet = array_values($callRet);

					if (is_array($callRet))
					{
						foreach ($callRet ?: [] as $itm)
						{
							if ($itm->Id)
							{
								if (!isset($itms[$itm->Id]))
								{
									$itms[$itm->Id] = $itm;
									$ret[] = $itm;
								}
								else if ($itm->Offers)
								{
									if (!$itms[$itm->Id]->Offers)
										$itms[$itm->Id]->Offers = [];
									foreach ($itm->Offers ?: [] as $off)
										$itms[$itm->Id]->Offers[] = $off;
								}
							}
							else
								$ret[] = $itm->Id;
						}
					}
					else
						$ret[] = $callRet;
				}
			}

			//echo "<div style='color: red;'>" . (microtime(true) - $__t) . " seconds</div>";
			$this->requestsData = [];
			return $ret;
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		finally
		{
			
		}
	}

	/**
	 * @param type $method
	 * @param type $filter
	 * @return type
	 * @throws \Exception
	 */
	public function doRestAPI_Remote($method, $filter)
	{
		$topsRemoteUrl = $this->_use_api_url ? ($this->ApiUrl__ ?: $this->ApiUrl) : rtrim(\QWebRequest::GetBaseUrl(), "\\/") . "/" . qUrl('self-remote');
		$postDataConstants = [];
		if (defined("IS_LIVE"))
			$postDataConstants["IS_LIVE"] = IS_LIVE;
		if (defined("PACKAGE"))
			$postDataConstants["PACKAGE"] = PACKAGE;
		if (defined("HAS_PLANE_TICKETS"))
			$postDataConstants["HAS_PLANE_TICKETS"] = HAS_PLANE_TICKETS;
		if (defined("IS_AGREGATOR"))
			$postDataConstants["IS_AGREGATOR"] = IS_AGREGATOR;
		if (defined("STARTUP_ADMIN"))
			$postDataConstants["STARTUP_ADMIN"] = STARTUP_ADMIN;

		$filterRequestData = $filter["__request_data__"];
		unset($filter["__request_data__"]);

		$postData = [
			"__q__in_remote_req" => true,
			"__request_data__" => $filterRequestData,
			"InSendingToSystemProcess" => \Omi\Comm\Order::$InSendingToSystemProcess,
			"SendToSystemOrderId" => \Omi\Comm\Order::$SendToSystemOrderId,
			"remote_request" => true,
			"system" => $this->getSystem(),
			"method" => $method,
			"filter" => json_encode($filter),
			"cookies" => json_encode($this->cookies),
			'session_id' => Q_SESSION_GET_ID(),
			"credentials" => json_encode([
				"ApiUrl" => $this->ApiUrl,
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername,
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword,
				"ApiContext" => $this->TourOperatorRecord->ApiContext,
				"ApiCode" => $this->TourOperatorRecord->ApiCode,

				"ApiUrl__" => $this->ApiUrl__,
				"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__,
				"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__,
				"ApiContext__" => $this->TourOperatorRecord->ApiContext__,
				"ApiCode__" => $this->TourOperatorRecord->ApiCode__,

				"BookingApiPassword" => $this->TourOperatorRecord->BookingApiPassword,
				"BookingApiUsername" => $this->TourOperatorRecord->BookingApiUsername,
				"BookingUrl" => $this->TourOperatorRecord->BookingUrl
			]),
			"const" => json_encode($postDataConstants),
			"topdata" => json_encode([
				"Handle" => $this->TourOperatorRecord->Handle,
				"ApiUrl" => $this->TourOperatorRecord->ApiUrl,
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername,
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword,
				"ApiContext" => $this->TourOperatorRecord->ApiContext,
				"ApiCode" => $this->TourOperatorRecord->ApiCode,

				"ApiUrl__" => $this->ApiUrl__,
				"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__,
				"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__,
				"ApiContext__" => $this->TourOperatorRecord->ApiContext__,
				"ApiCode__" => $this->TourOperatorRecord->ApiCode__,
				
				"BookingApiPassword" => $this->TourOperatorRecord->BookingApiPassword,
				"BookingApiUsername" => $this->TourOperatorRecord->BookingApiUsername,
				"BookingUrl" => $this->TourOperatorRecord->BookingUrl,
				
				'SystemProxyPassword' => $this->TourOperatorRecord->SystemProxyPassword,
				'SystemProxyUrl' => $this->TourOperatorRecord->SystemProxyUrl,
				'System_Software' => $this->TourOperatorRecord->System_Software
			])
		];
		
		foreach ($this->_extra_post_data ?: [] as $k => $v)
			$postData[$k] = $v;
		
		$is_self_remote = (!$this->_use_api_url);
		$ret = $this->doRestAPI_Remote_Exec($topsRemoteUrl, $postData, $method, $filter, $is_self_remote);
		return $ret;
	}

	public function doRestAPI_Remote_Exec($topsRemoteUrl, $postData, $method, $filter = null, $is_self_remote = null)
	{
		$use_advance_client = (!$this->_use_direct_call_on_doRestAPI_Remote_Exec);

		/*
		if (!$this->curl_client)
			$this->curl_client = q_curl_init_with_log();
		else
			curl_reset($this->curl_client);
		$curl = $this->curl_client;
		*/
		
		$curl = q_curl_init_with_log();

		$final_url = rtrim($topsRemoteUrl, "\\/") . "?method=" . $method . 
				($is_self_remote ? "&__Request_Id_Log=". urlencode(\QWebRequest::Get_Request_Id_For_Logs()) : "");
		
		if (\QAutoload::In_Debug_Mode())
			$final_url .= "&_debug_mode_key_=" . urlencode(Q_DEBUG_MODE_KEY);

		$func_setup_curl = function(&$curl, $topsRemoteUrl, $postData, $method, $filter, $final_url)
		{
			q_curl_setopt_with_log($curl, CURLOPT_URL, $final_url);
			q_curl_setopt_with_log($curl, CURLOPT_POST, 1);
			
			// send xml request to a server
			q_curl_setopt_with_log($curl, CURLOPT_SSL_VERIFYHOST, 0);
			q_curl_setopt_with_log($curl, CURLOPT_SSL_VERIFYPEER, 0);
			q_curl_setopt_with_log($curl, CURLINFO_HEADER_OUT, true);

			q_curl_setopt_with_log($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
			q_curl_setopt_with_log($curl, CURLOPT_RETURNTRANSFER, 1);
			q_curl_setopt_with_log($curl, CURLOPT_FOLLOWLOCATION, true);

			q_curl_setopt_with_log($curl, CURLOPT_VERBOSE, 0);

			$headers = [];
			q_curl_setopt_with_log($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers) {
				$len = strlen($header);
				$header = explode(':', $header, 2);
				if (count($header) < 2) // ignore invalid headers
					return $len;
				$name = strtolower(trim($header[0]));
				if (!array_key_exists($name, $headers))
					$headers[$name] = [trim($header[1])];
				else
					$headers[$name][] = trim($header[1]);
				return $len;
			});
		};

		if ($use_advance_client)
		{
			if (!$this->soap_client)
			{
				$this->soap_client = new \Omi\Util\SoapClientAdvanced(null, 
					["login" => ($this->ApiUsername__ ?: $this->ApiUsername), "password" => ($this->ApiPassword__ ?: $this->ApiPassword)]
				);

				$this->soap_client->__top_handle__ = $this->TourOperatorRecord->Handle;

				if ($this->_validate_response)
					$this->soap_client->_validate_response = $this->_validate_response;

				$this->soap_client->_cache_get_key = function ($method, $params, $request, $location)
				{
					# @TODO
					$orig_params = null;
					$useParams = null;
					if (($useParams = ($params["filter"] ? json_decode($params["filter"], true) : null)))
					{
						// if it's a hotel search, we return 2 keys
						$isHotelSearch = false;
						unset($useParams["getFeesAndInstallments"]);
						unset($useParams["getFeesAndInstallmentsFor"]);
						unset($useParams["getFeesAndInstallmentsForOffer"]);
						unset($useParams["VacationType"]);
						unset($useParams["__skipOfferDetailsRequest"]);

						// cleanup cache params
						unset($useParams["_cache_use"]);
						unset($useParams["_cache_create"]);
						unset($useParams["_multi_request"]);
						unset($useParams["_cache_force"]);
						unset($useParams["__on_add_travel_offer__"]);
						unset($useParams["ParamsFile"]);

						// duration comes when on tour
						unset($useParams["Duration"]);

						$origParams = $useParams;
						ksort($origParams);

						// check if we are on hotel search
						$isHotelSearch = ($useParams["travelItemId"]);

						unset($useParams["travelItemId"]);
						unset($useParams["travelItemType"]);
						unset($useParams["travelItemName"]);
						
						unset($useParams["TravelItemId"]);
						unset($useParams["TravelItemType"]);
						unset($useParams["TravelItemName"]);

						unset($useParams["__q__request_id"]);

						if ($useParams)
							ksort($useParams);

						TOStorage::KSortTree($origParams);
						TOStorage::KSortTree($useParams);
						
						#qvardump('$origParams, $useParams', $origParams, $useParams);

						$this->cleanupBeforeGenerateCacheKey($method, $origParams, $useParams, $location);

						$isFullLocation = ((substr($location, 0, 7) === "http://") || (substr($location, 0, 8) === "https://"));
						$useLocation = $isFullLocation ? str_replace(\QWebRequest::GetBaseUrl(), "", $location) : $location;

						if (substr($useLocation, 0, 1) === "/")
							$useLocation = substr($useLocation, 1);

						$ret = $isHotelSearch ? 
							[
								sha1(var_export([$method, $origParams, $useLocation, $this->TourOperatorRecord->Handle], true)), 
								sha1(var_export([$method, $useParams, $useLocation, $this->TourOperatorRecord->Handle], true))
							] :
							sha1(var_export([$method, $useParams, $useLocation, $this->TourOperatorRecord->Handle], true));
					}
					else 
					{
						$ret = sha1(var_export([uniqid()]));
					}

					if ($_GET['q_show_for_cache'] && (defined('TO_SHOW_DUMP_IP') && (TO_SHOW_DUMP_IP === $_SERVER["REMOTE_ADDR"])))
					{
						qvardump("q_show: cache_hashes, initial_params, travel_params, search_params, method, location, tour_op_handle", 
							$ret, $params, $useParams, $orig_params, $method, $location, $this->TourOperatorRecord->Handle);
					}

					return $ret;
				};
			}

			$this->soap_client->_cache_last_method =  $method;
			if ($postData["__request_data__"])
			{
				$this->soap_client->_cache_last_data = $postData["__request_data__"];
				unset($postData["__request_data__"]);
			}

			$this->soap_client->_cache_last_args = $postData;

			$curl_callback = $this->soap_client->__curl_callback = function () use($func_setup_curl, $curl, $topsRemoteUrl, $postData, $method, $filter, $final_url)
			{
				$func_setup_curl($curl, $topsRemoteUrl, $postData, $method, $filter, $final_url);
				return $curl;
			};

			$refThis = $this;
			$this->soap_client->__request_callback = function () use ($refThis, $curl_callback, $topsRemoteUrl, $postData, $method, $filter, $final_url)
			{
				$curl = $curl_callback($curl, $topsRemoteUrl, $postData, $method, $filter, $final_url);
				if (!\Omi\Util\SoapClientAdvanced::$InMultiRequest || $refThis->Storage::$Exec)
				{
					$data = q_curl_exec_with_log($curl);
					if ($data === false)
						throw new \Exception(\Omi\App::Q_ERR_SYS . ": Invalid response from server - " . curl_error($curl));
					//$curl_info = curl_getinfo($curl);
				}
				return $data;
			};

			#qvardump("\$this->soap_client->_cache_last_data", $this->soap_client->_cache_last_data);

			$resp = $this->soap_client->__doRequest($postData, $final_url, "get", "1.0");
			
			if (($resp === null) || ($resp === ""))
			{
				# init step / do not process it !
				return null;
			}
		}
		else
		{
			$func_setup_curl($curl, $topsRemoteUrl, $postData, $method, $filter, $final_url);
			$resp = q_curl_exec_with_log($curl);

			if ($resp === false)
				throw new \Exception(\Omi\App::Q_ERR_SYS . ": Invalid response from server - " . curl_error($curl));
		}
		
		if ($resp === false)
		{
			// @TODO handle error
			$error = curl_errno($curl);
			throw new \Exception(\Omi\App::Q_ERR_SYS . ": " . $error);
		}

		if ($this->_debug_response)
			echo $resp;
		
		$data = $this->_decode_array ? json_decode($resp, true) : json_decode($resp);
		
		if ($data === false)
			throw new \Exception(\Omi\App::Q_ERR_SYS . 'Failed to decode response in JSON format');

		if (\Omi\App::$CurrentSearchID && \Omi\App::$SaveRequestsDump)
		{
			$requestsDump = \Omi\App::GetLogsDir('requests_dump');
			if ($data->requests_collected_data)
			{
				$data_req_collected = json_decode($data->requests_collected_data);
				foreach ($data_req_collected ?: [] as $k => $v)
				{
					$indx = \Omi\App::$RemoteRequestsCollectedData[$k];
					if ($indx)
					{
						file_put_contents($requestsDump . $indx . "_top_requests.json", json_encode($v));
						\Omi\App::$RequestsCollectedData[$indx]["top_requests"] = (array)$v;
					}
				}
			}
			else
			{
				foreach (\Omi\App::$RequestsCollectedData ?: [] as $indx => $reqData)
				{
					if (file_exists($requestsDump . $indx . "_top_requests.json"))
						\Omi\App::$RequestsCollectedData[$indx]["top_requests"] = (array)json_decode(file_get_contents($requestsDump . $indx . "_top_requests.json"));
				}
			}
		}

		if (($err = ($this->_decode_array ? ($data["Error"] ?: ($data["error"] ?: $data["EXCEPTION"])): ($data->Error ?: ($data->error ?: $data->EXCEPTION)))))
		{
			if (\QAutoload::GetDevelopmentMode())
			{
				//echo $data;
				//qvardump("\$err", $err);
				//q_die("STOP - with err:");
				#if ($data && ($dumpData = ($this->_decode_array ? $data["dump_data"] : $data->dump_data)))
				{
					//echo '<hr />';
					//echo $dumpData;
					//echo '<hr />';
				}
			}

			$errMessage = $this->_decode_array ? ($err["message"] ?: $err["Message"]) : ($err->message ?: $err->Message);
			$ex = new \Exception("call failed on [" . $this->TourOperatorRecord->Handle . "] - " . $errMessage);
			\Omi\App::ThrowExWithTypePrefix($ex, \Omi\App::Q_ERR_SYS);
		}

		#if ($data && $data->dump_data && (\QAutoload::GetDevelopmentMode()))
		#	echo $data->dump_data;
		
		return $this->_decode_array ? $data["response"] : $data->response;
	}



	//========================================================= released from use =================
	
	/**
	 * Setup the curl callback - on each tour operator that is using curl
	 * 
	 * @param type $curl_handle
	 * @param type $method
	 * @param type $filter
	 */
	public function setupCurlCallback($curl_handle, $method, $filter)
	{
		
	}
	/**
	 * On curl callback - setup on each tour operator integration
	 * 
	 * @param type $curl_callback
	 * @param type $curl_handle
	 * @param type $method
	 * @param type $filter
	 */
	public function onCurlCallback($curl_callback, $curl_handle, $method, $filter)
	{
		
	}

	public function getSoapClient($url, $options)
	{
		
	}

	public function useCache($method)
	{
		//return in_array($method, ["api_getOffers"]);
	}

	public function doRestAPI_Exec_UseCache_GetCalls($method, $filter)
	{
		//return [[$method, $filter]];
	}

	public function doRestAPI_Exec_UseCache($method, $filter)
	{
	
	}

	public function saveResponseForLaterUsage($method, $filter, $resp)
	{
	
	}

	public function getCachedResponse($method, $filter)
	{
	
	}
	/**
	 * The request index method + filter
	 * 
	 * @param type $method
	 * @param type $filter
	 * @return string
	 */
	public function getRequestIndex($method, $filter)
	{
	
	}

	public function collectDumpData()
	{
		if (!$this->dumpdatauuid)
			$this->dumpdatauuid = uniqid();
		$requestsDump = \Omi\App::GetLogsDir('collect_data');
		ob_start();
		qvardump(func_get_args());
		file_put_contents($requestsDump . $this->dumpdatauuid . ".rq.html", ob_get_clean());
	}
}