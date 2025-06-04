<?php

namespace Integrations\GoGlobal_old;

class GoGlobalSoap extends \SoapClient
{
	public $currentRequestXML = null;

	private $lastRequest = null;
	
	public $agencyID = null;
	
	public $apiOperation = null;
	
	public $sendAdditionalHeaders = false;

	public $_reqHeaders = null;
	
	public $_respHeaders = null;
	
	public $_lastReq = null;

	public $_lastResp = null;
	
	function __doRequest($request, $location, $action, $version, $onw_way = 0)
	{
		$this->_reqHeaders = null;
		$this->_respHeaders = null;
		$this->_lastReq = null;
		$this->_lastResp = null;

		if (!$this->currentRequestXML)
			throw new \Exception("current request xml not found!");	

		$headerSection = '';
		if ($this->sendAdditionalHeaders)
		{
			$headerSection = '<soap12:Header>
					<Content-type>application/soap+xml; charset=utf-8</Content-type>' 
					. ($this->apiOperation ? '<API-Operation>' . $this->apiOperation . '</API-Operation>' : '')
					. ($this->agencyID ? '<API-AgencyID>' . $this->agencyID . '</API-AgencyID>' : '')
				. '</soap12:Header>';
		}

		$this->lastRequest = null;
		$xmlns = rtrim(parse_url($action, PHP_URL_SCHEME) . "://" . parse_url($action, PHP_URL_HOST), "\\/") . "/";
		$request = '<?xml version="1.0" encoding="utf-8"?>
			<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
				' . $headerSection . '
				<soap12:Body>
					<MakeRequest xmlns="' . $xmlns . '">';

		$request .= $this->currentRequestXML;

		$request .= '</MakeRequest>
				</soap12:Body>
			</soap12:Envelope>';

		$this->lastRequest = $request;
		$urlp = parse_url($location);

		$headers = array(
			"Host: " . $urlp['host'],
			"SOAPAction: " . $action,
			"Content-Type: text/xml; charset=utf-8",
			"Content-length: " . strlen($request),
		);

		if ($this->ReqOperation)
			$headers[] = "API-Operation: " . $this->ReqOperation;

		if ($this->ReqApiContext)
			$headers[] = "API-AgencyID: " . $this->ReqApiContext;

		$ch = q_curl_init_with_log();
		q_curl_setopt_with_log($ch, CURLOPT_URL, $location);
		q_curl_setopt_with_log($ch, CURLOPT_POST, true);
		q_curl_setopt_with_log($ch, CURLOPT_ENCODING, "");
		q_curl_setopt_with_log($ch, CURLOPT_POSTFIELDS, $request);
		q_curl_setopt_with_log($ch, CURLOPT_TIMEOUT, $this->connection_timeout ?: 120);
		q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, 1);
		q_curl_setopt_with_log($ch, CURLOPT_FAILONERROR, false);
		q_curl_setopt_with_log($ch, CURLOPT_USERPWD, $this->ReqApiUsername . ':' . $this->ReqApiPassword);
		q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($ch, CURLINFO_HEADER_OUT, true);
		q_curl_setopt_with_log($ch, CURLOPT_HEADER, 1);
		q_curl_setopt_with_log($ch, CURLOPT_HTTPHEADER, $headers);

		$resp = q_curl_exec_with_log($ch);		
		$info = curl_getinfo($ch);

		curl_close($ch);
		$respParts = preg_split("/\\r?\\n\\r?\\n/us", $resp, 2);
		if ($respParts === false)
		{
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$hasHeaders = ($header_size !== false);
		}
		else
			$hasHeaders = (q_count($respParts) > 1);
		$resp_header = null;
		$resp_body = null;
		if ($hasHeaders)
		{
			$resp_header = substr($resp, 0, $info['header_size']);
			$resp_body = substr($resp, $info['header_size']);
			$req_content = $resp_body;
		}
		else
		{
			$req_content = $resp_body = $resp;
		}

		$this->_respHeaders = $resp_header;
		$this->_reqHeaders = $info['request_header'];
		$this->_lastReq = $request;
		$this->_lastResp = $req_content;

		#$req_content = parent::__doRequest($request, $location, $action, $version, $onw_way);
		return $req_content;
		
	}

	public function __getLastRequest()
	{
		if ($this->lastRequest)
			return $this->lastRequest;
		else
			return parent::__getLastRequest();
	}
}
