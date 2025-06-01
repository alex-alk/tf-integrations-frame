<?php

namespace Omi\TF;

final class API_Wrap
{
	public static $_profiling_ = null;
	
	static function q_curl_find($handle)
	{
		global $__q_remote_log_curls;
		if (is_array($__q_remote_log_curls))
		{
			foreach ($__q_remote_log_curls as $pos => $curl_data)
			{
				if ($curl_data[0] === $handle)
				{
					return $__q_remote_log_curls[$pos];
				}
			}
		}
		return [];
	}
	public static function curl(\CurlHandle $curlHandle, bool $with_response_header = null)
	{
		$t0 = microtime(true);
		$m0 = memory_get_usage();
		
		list(, , $curl_opts) = self::q_curl_find($curlHandle);
		
		/*
		$headers_out = "";
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers_out) {
			$len = strlen($header);
			$headers_out .= $header;
			return $len;
		});
		*/
		
		# try to detect it
		if ($with_response_header === null)
		{
			if (isset($curl_opts))
				$with_response_header = ($curl_opts[CURLOPT_HEADER] ?? false) ? true : false;
			else
			{
				# this is very bad as we do not know if we need to send it or not
				\Omi\Email::Send("ealexs@gmail.com", "API_Wrap curl with_response_header #1", nl2br((new \Exception())->getTraceAsString()));
				throw new \Exception('Please specify `$with_response_header` : true / false (unable to detect it)');
			}
		}
		else if (isset($curl_opts))
		{
			$tmp_with_response_header = ($curl_opts[CURLOPT_HEADER] ?? false) ? true : false;
			if ($tmp_with_response_header !== $with_response_header)
			{
				\Omi\Email::Send("ealexs@gmail.com", "API_Wrap curl with_response_header #2", nl2br((new \Exception())->getTraceAsString()));
				throw new \Exception('Missmatching `$with_response_header` with `$curl_opts`');
			}
		}
			
		$inf = curl_getinfo($curlHandle);
		
		# we need this for logging
		q_curl_setopt_with_log($curlHandle, CURLOPT_HEADER, true);
		# we want this always
		q_curl_setopt_with_log($curlHandle, CURLINFO_HEADER_OUT, true);
		
		# JUST TO TEST !
		q_curl_setopt_with_log($curlHandle, CURLOPT_TIMEOUT_MS, 120 * 1000);
		
		# updated curl info !
		list(, , $final_curl_opts) = self::q_curl_find($curlHandle);
		
		$ret = null;
		$the_ex = null;
		try
		{
			$request_id = ($ctx = \Q_Ctx::closest('tf_search::ApiQuery::', true)) ? $ctx->get('request_id') : null;
			$to_handle = ($ctx = \Q_Ctx::closest('tf_search::ApiQuery::', true)) ? $ctx->get('to_handle') : null;
			
			$log_data = ['$.tab' => 'API_Call',
						'@fiber' => ($tmp_f = \Fiber::getCurrent()) ? spl_object_id($tmp_f) : '',
						'@url' => $inf['url'], 
					];
			if ($request_id)
				$log_data['@request_id'] = $request_id;
			if ($to_handle)
				$log_data['@to_handle'] = $to_handle;
			
			q_log_block($log_data);
			# $uid = uniqid("", true);
			# echo "URL [START@{$uid}] ".date("H:i:s")." " , $curl_opts[CURLOPT_URL], " => POST => " , htmlspecialchars(str_replace(["\n", "\r"], ["", ""], $curl_opts[CURLOPT_POSTFIELDS])) , "\n";
			
			# $t1 = microtime(true);
			$ret = \Fiber::getCurrent() ? \Q_Async::curl_exec($curlHandle) : q_curl_exec_with_log($curlHandle);
			# $ret = q_curl_exec_with_log($curlHandle);
			# $t2 = microtime(true);
			
			$inf = curl_getinfo($curlHandle);
			
			if ((!curl_errno($curlHandle)) && (!($inf['http_code'] ?? null)) && \QAutoload::GetDevelopmentMode())
			{
				qvar_dump("LOOOOK INTO THIS !", ['$.tab' => 'API_Call',
									'@request_header' => $inf['request_header'], 
									'@request_load' => $curl_opts ? $curl_opts[CURLOPT_POSTFIELDS] : null, 
									'@return_head' => is_string($ret) ? substr($ret, 0, $inf['header_size']) : $ret, 
									'@return' => is_string($ret) ? substr($ret, $inf['header_size']) : $ret, 
									'@za_ret' => $ret, 
									'@error' => curl_errno($curlHandle) ? ('['.curl_errno($curlHandle).'] ' . curl_error($curlHandle)) : null,
									'@ex' => $the_ex ? $the_ex->getMessage() : null,
									# '@info' => $inf,
									'@curl' => $inf,
									'@curl_opts' => $final_curl_opts,
									'@caption' => $inf['url'],
								]);
				if (\QWebRequest::IsAjaxRequest())
				{
					throw new \Exception("we need to look into this !!!!");
				}
				else
				{
					die;
				}
			}
			
			if (($ret === '') && \QAutoload::GetDevelopmentMode())
			{
				qvar_dump(['$.tab' => 'API_Call',
									'@request_header' => $inf['request_header'], 
									'@request_load' => $curl_opts ? $curl_opts[CURLOPT_POSTFIELDS] : null, 
									'@return_head' => is_string($ret) ? substr($ret, 0, $inf['header_size']) : $ret, 
									'@return' => is_string($ret) ? substr($ret, $inf['header_size']) : $ret, 
									# '@za_ret' => $ret, 
									'@error' => curl_errno($curlHandle) ? ('['.curl_errno($curlHandle).'] ' . curl_error($curlHandle)) : null,
									'@ex' => $the_ex ? $the_ex->getMessage() : null,
									# '@info' => $inf,
									'@curl' => $inf,
									'@curl_opts' => $final_curl_opts,
									'@caption' => $inf['url'],
								]);
				throw new \Exception("we need to look into this !!!!");
			}
			
			# if (curl_errno($curlHandle))
			{
				# echo "URL [ERROR@{$uid}] ".date("H:i:s")." time=".round($t2 - $t1, 3)." sec: " , $curl_opts[CURLOPT_URL], " => ERR => [".curl_errno($curlHandle)."] ".curl_error($curlHandle)."\n";
			}
			# else
			{
				/*
				echo "URL [DONE@{$uid}] ".date("H:i:s")." time=".round($t2 - $t1, 3)." sec: " , $curl_opts[CURLOPT_URL], " => RET => " , 
						htmlspecialchars(str_replace(["\n", "\r"], ["", ""], substr($ret, 0, 1024 * 32))) , "\n";
				*/
			}
			
			if ((!$with_response_header) && is_string($ret))
			{
				# we need to remove the header
				return substr($ret, $inf['header_size']);
			}
			else
				return $ret;
		}
		catch (\Exception $ex)
		{
			$the_ex = $ex;
			throw $ex;
		}
		finally
		{
			if (static::$_profiling_ !== null)
			{
				static::$_profiling_['curl'][] = [$t0, microtime(true) - $t0, $m0, memory_get_usage() - $m0, (new \Exception())->getTraceAsString(), $inf['url'], 
						'download_size' => strlen($ret)];
			}
			
			q_log_block_end(['$.tab' => 'API_Call',
								'@request_header' => $inf['request_header'], 
								'@request_load' => $curl_opts ? $curl_opts[CURLOPT_POSTFIELDS] : null, 
								'@return_head' => is_string($ret) ? substr($ret, 0, $inf['header_size']) : null, 
								'@return' => is_string($ret) ? substr($ret, $inf['header_size']) : $ret, 
								# '@za_ret' => $ret, 
								'@error' => curl_errno($curlHandle) ? ('['.curl_errno($curlHandle).'] ' . curl_error($curlHandle)) : null,
								'@ex' => $the_ex ? $the_ex->getMessage() : null,
								# '@info' => $inf,
								'@curl' => $inf,
								'@curl_opts' => $final_curl_opts,
								'@caption' => $inf['url'],
							]);
		}
	}
	
	public static function soap_via_curl(\Omi\Util\SoapClientAdvanced $soap, string $method, array $params = [])
	{
		# $t1 = microtime(true);
		$ret = $soap->$method(...$params);
		# $t2 = microtime(true);
		return $ret;
	}
	
	public static function soap_via_curl_custom(string $request, string $location, string $action, int $version, bool $one_way = false, 
											array $extraData = [], \Omi\Util\SoapClientAdvanced $soap = null)
	{
		$t0 = microtime(true);
		$curl_handle = static::soap_advanced_initBasic($soap, $location, $request, null, $action, $version, $one_way);
		$t1 = microtime(true);
		
		$ret_str = null;
				
		if ($curl_handle instanceof \CurlHandle)
		{
			# ok !
			# qvar_dump($t1 - $t0);
			$ret = static::curl($curl_handle);
			
			if (is_string($ret))
			{
				$h_size = curl_getinfo($curl_handle, CURLINFO_HEADER_SIZE);
				
				#ob_start();
				#qvar_dump($location, $action, $request, substr($ret, 0, 5000));
				#\QApp::Log_To_File(ob_get_clean());
				$ret_str = substr($ret, $h_size);
			}
			else
				throw new \Exception("Failed. " . curl_error($curl_handle));
		}
		else
		{
			qvar_dump("??????", $curl_handle);
			die;
		}
		
		return $ret_str;
	}
	
	public static function soap_advanced_initBasic(\Omi\Util\SoapClientAdvanced $soap, $location, $request, $request_id, $action, $version, $one_way = 0)
	{
		if (($curl_cbk = $soap->__curl_callback))
		{
			$ch = $curl_cbk();
		}
		else
		{
			$ch = $soap->__curl = q_curl_init_with_log();
			if ($soap->_proxy_host)
				q_curl_setopt_with_log($ch, CURLOPT_PROXY, $soap->_proxy_host);
			
			q_curl_setopt_with_log($ch, CURLOPT_URL, $location);
			q_curl_setopt_with_log($ch, CURLOPT_POST, true);
			q_curl_setopt_with_log($ch, CURLOPT_ENCODING, "");
			q_curl_setopt_with_log($ch, CURLOPT_POSTFIELDS, $request);
			q_curl_setopt_with_log($ch, CURLOPT_TIMEOUT, $soap->connection_timeout ?: 120);
			q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, 1);
			q_curl_setopt_with_log($ch, CURLOPT_FAILONERROR, false);
			q_curl_setopt_with_log($ch, CURLOPT_USERPWD, $soap->login . ':' . $soap->password);
			q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYPEER, false);
			q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYHOST, false);
			q_curl_setopt_with_log($ch, CURLINFO_HEADER_OUT, true);
			q_curl_setopt_with_log($ch, CURLOPT_HEADER, 1);

			if ($soap->_set_soap_action_header)
			{
				if (!$soap->_request_headers)
					$soap->_request_headers = [];
				$newRequestsHeaders = [];
				foreach ($soap->_request_headers ?: [] as $header)
				{
					if (strpos($header, "SOAPAction") !== false)
						continue;
					$newRequestsHeaders[] = $header;
				}
				$soap->_request_headers = $newRequestsHeaders;
				$soap->_request_headers[] = "SOAPAction: \"{$action}\"";
			}

			if ($soap->_request_headers)
				q_curl_setopt_with_log($ch, CURLOPT_HTTPHEADER, $soap->_request_headers);
		}

		if ($ch)
		{
			q_curl_setopt_with_log($ch, CURLOPT_TIMEOUT, $soap->timeout ?: 120);
			if ($soap->connection_timeout !== null)
				q_curl_setopt_with_log($ch, CURLOPT_CONNECTTIMEOUT, $soap->connection_timeout);

			// control if sending cookies
			if ($soap->_cookies && ((!$soap->_stop_cookies_send) || \Omi\Util\SoapClientAdvanced::$SendCookies))
			{
				$cookies_str = "";
				foreach ($soap->_cookies as $key => $val)
				{
					// ensure $soap SOAP object also gets the cookies
					$soap->__setCookie($key, $val);

					// name1=content1; 
					$cookies_str .= $key . "=" . (is_array($val) ? $val[0] : $val)."; ";
				}
				// now set the cookie(s)
				q_curl_setopt_with_log($ch, CURLOPT_COOKIE, $cookies_str);
			}
		}

		return $ch;
	}
	
	public static function start_profiling(bool $reset = false)
	{
		if ($reset || (static::$_profiling_ === null))
			static::$_profiling_ = [];
	}
	
	public static function collect_profiling()
	{
		return static::$_profiling_;
	}
}
