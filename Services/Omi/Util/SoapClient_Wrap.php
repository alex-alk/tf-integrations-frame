<?php

namespace Omi\Util;

use SoapClient;

class SoapClient_Wrap extends SoapClient
{
	
	protected $_call_index__ = null;
	
	public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false)
	{
		$this->_call_index__ = $index = uniqid("", true);
		$ret = parent::__doRequest($request, $location, $action, $version, $oneWay);
		return $ret;
	}

	public function __soapCall(string $name, array $args, array $options = null, $inputHeaders = null, &$outputHeaders = null)
	{
		$log_fault = function (\Exception $fault)
		{
			if ($fault instanceof \SoapFault)
			{
				$err_code = $fault->faultcode;
				$err_message = $fault->faultstring;
			}
			else
			{
				$err_code = $fault->getCode();
				$err_message = $fault->getMessage();
			}
		};
		
		try
		{
			$ret = parent::__soapCall($name, $args, $options, $inputHeaders, $outputHeaders);
			if (is_soap_fault($ret) && ($ret instanceof \Exception))
				$log_fault($ret);
		}
		catch (\Exception $ex)
		{
			$log_fault($ex);
			throw $ex;
		}
		return $ret;
	}
}

