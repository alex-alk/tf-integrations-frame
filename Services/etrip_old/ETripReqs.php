<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Omi\TF;

/**
 * Description of ETripReqs
 *
 * @author Omi-Mihai
 */
trait ETripReqs 
{
	//put your code here
	protected $resp_client;

	public function reqs_GetSoapInstance($pcfg)
	{
		if ($this->resp_client)
			return $this->resp_client;

		$params = ['trace' => 1];
		$params["login"] = ($pcfg->ApiUsername__ ?: $pcfg->ApiUsername);
		$params["password"] = ($pcfg->ApiPassword__ ?: $pcfg->ApiPassword);

		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			$params["proxy_host"] = $proxyUrl . ($proxyPort ? ":" . $proxyPort : "");
		#if ($proxyPort)
		#	$params["proxy_port"] = $proxyPort;
		if ($proxyUsername)
			$params["proxy_login"] = $proxyUsername;
		if ($proxyPassword)
			$params["proxy_password"] = $proxyPassword;

		try
		{
			// resp_client
			$this->resp_client = new \Omi\Util\SoapClient_Wrap(($pcfg->ApiUrl__ ?: $pcfg->ApiUrl), $params);
		}
		catch (\Exception $ex)
		{
			return null;
		}

		// client
		return $this->resp_client;
	}

	public function reqs_GetChartersDatesCfg($pcfg)
	{
		$soapInstance = $this->reqs_GetSoapInstance($pcfg);
		if (!$soapInstance)
			throw new \Exception("Cannot connect with soap!");

		try
		{
			$packages = $soapInstance->GetPackages();
		}
		catch (\Exception $ex)
		{
			// log exception
		}

		$transports = [];
		foreach ($packages ?: [] as $package)
		{
			// filter by all data like destination, hotel, departure points, etc
			if ($package->IsTour || !$package->Hotel || !$package->Destination || !$package->DeparturePoints)
			{
				continue;
			}

			// ++skip hotel with source for now
			if ($package->HotelSource)
			{
				continue;
			}

			// determine transport type
			$transportType = $package->IsBus ? "bus" : ($package->IsFlight ? "plane" : "individual");
			if (($transportType !== "bus") && ($transportType !== "plane"))
				continue;

			foreach ($package->DeparturePoints ?: [] as $departure)
			{
				$transportID = $pcfg->TourOperatorHandle . "|" . $transportType . "|" . $departure . ":global" . "|" . $package->Destination . ":global";
				if (!isset($transports[$transportID]))
				{
					$transport = [
						"Id" => $transportID,
						"TransportType" => $transportType,
						"TourOperator" => $pcfg->TourOperatorHandle,
						"From" => [
							"Global" => [
								"Id" => $package->Destination
							]
						],
						"To" => [
							"Global" => [
								"Id" => $departure
							]
						],
						"Dates" => []
					];
					$transports[$transportID] = $transport;
				}	

				foreach ($package->DepartureDates ?: [] as $date)
				{
					$isArr = is_array($date);
					$date = $isArr ? $date[0] : $date;
					
					if (!isset($transports[$transportID]["Dates"][$date]))
						$transports[$transportID]["Dates"][$date] = ["Date" => $date, "Nights" => []];
					$transports[$transportID]["Dates"][$date]["Nights"][$package->Duration] = $package->Duration;
				}
			}
		}
		return [$transports, $transports];
	}

	public function reqs_GetToursDatesCfg($pcfg)
	{
		$soapInstance = $this->reqs_GetSoapInstance($pcfg);
		if (!$soapInstance)
			throw new \Exception("Cannot connect with soap!");

		try
		{
			$packages = $soapInstance->GetPackages();
		}
		catch (\Exception $ex)
		{
			// log exception
		}

		$transports = [];
		foreach ($packages ?: [] as $package)
		{
			// filter by all data like destination, hotel, departure points, etc
			if (!$package->IsTour || !$package->Hotel || !$package->Destination || !$package->DeparturePoints)
			{
				continue;
			}

			// ++skip hotel with source for now
			if ($package->HotelSource)
			{
				continue;
			}

			// determine transport type
			$transportType = $package->IsBus ? "bus" : ($package->IsFlight ? "plane" : "individual");
			if (($transportType !== "bus") && ($transportType !== "plane"))
				continue;

			foreach ($package->DeparturePoints ?: [] as $departure)
			{
				$transportID = $pcfg->TourOperatorHandle . "|" . $transportType . "|" . $departure . ":global" . "|" . $package->Destination . ":global";
				if (!isset($transports[$transportID]))
				{
					$transport = [
						"Id" => $transportID,
						"TransportType" => $transportType,
						"TourOperator" => $pcfg->TourOperatorHandle,
						"From" => [
							"Global" => [
								"Id" => $package->Destination
							]
						],
						"To" => [
							"Global" => [
								"Id" => $departure
							]
						],
						"Dates" => []
					];
					$transports[$transportID] = $transport;
				}	

				foreach ($package->DepartureDates ?: [] as $date)
				{
					$isArr = is_array($date);
					$date = $isArr ? $date[0] : $date;
					
					if (!isset($transports[$transportID]["Dates"][$date]))
						$transports[$transportID]["Dates"][$date] = ["Date" => $date, "Nights" => []];
					$transports[$transportID]["Dates"][$date]["Nights"][$package->Duration] = $package->Duration;
				}
			}
		}
		return [$transports, $transports];
	}
}
