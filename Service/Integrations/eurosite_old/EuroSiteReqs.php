<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Omi\TF;

/**
 * Description of EuroSiteReqs
 *
 * @author Omi-Mihai
 */
trait EuroSiteReqs 
{
	public function reqs_DoRequest($pcfg, $xmlRequest)
	{
		$m = null;
		preg_match('/RequestType=[\"|\'](.*?)[\"|\']/', $xmlRequest, $m);
		$request = ($m && $m[1]) ? $m[1] : null;
		
		$responseMethod = $request ? preg_replace("/Request/", "Response", $request) : null;

		$headers = [
			"Content-type: text/xml;charset=\"utf-8\"",
			"Accept: text/xml",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"SOAPAction: \"run\""
		];
		
		$curl = q_curl_init_with_log();

		q_curl_setopt_with_log($curl, CURLOPT_URL, ($pcfg->ApiUrl__ ?: $pcfg->ApiUrl));
		q_curl_setopt_with_log($curl, CURLOPT_POST, 1);

		// send xml request to a server
		q_curl_setopt_with_log($curl, CURLOPT_SSL_VERIFYHOST, 0);
		q_curl_setopt_with_log($curl, CURLOPT_SSL_VERIFYPEER, 0);
		q_curl_setopt_with_log($curl, CURLINFO_HEADER_OUT, true);

		q_curl_setopt_with_log($curl, CURLOPT_POSTFIELDS, $xmlRequest);
		q_curl_setopt_with_log($curl, CURLOPT_RETURNTRANSFER, 1);
		q_curl_setopt_with_log($curl, CURLOPT_FOLLOWLOCATION, 1);

		q_curl_setopt_with_log($curl, CURLOPT_VERBOSE, 0);
		q_curl_setopt_with_log($curl, CURLOPT_HTTPHEADER, $headers);
		q_curl_setopt_with_log($curl, CURLOPT_HEADER, 1);
		
		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($curl, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($curl, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($curl, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($curl, CURLOPT_PROXYUSERPWD, $proxyPassword);

		$data = q_curl_exec_with_log($curl);
		$curlInfo = curl_getinfo($curl);
		
		if ($data === false)
			throw new \Exception("Invalid response from server - " . curl_error($curl));

		$respBody = substr($data, $curlInfo['header_size']);

		try
		{
			$xml = simplexml_load_string($respBody);
			$ret = $this->simpleXML2Array($xml);
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}

		if ($ret && $ret["ResponseDetails"] && $ret["ResponseDetails"][$responseMethod] && 
			$ret["ResponseDetails"][$responseMethod] && $ret["ResponseDetails"][$responseMethod]["Error"])
		{
			$firstError = isset($ret["ResponseDetails"][$responseMethod]["Error"]["ErrorId"]) ? 
				$ret["ResponseDetails"][$responseMethod]["Error"] : reset($ret["ResponseDetails"][$responseMethod]["Error"]);
			throw new \Exception($firstError["ErrorText"]);
		}

		return $ret;
	}

	public function reqs_GetChartersDatesCfg_setTransports(&$transports, $pcfg, $countries, $transportType)
	{
		foreach ($countries ?: [] as $countryData)
		{
			$destinations = $countryData["Destinations"]["Destination"];
			if (isset($destinations["CityCode"]))
				$destinations = [$destinations];

			foreach ($destinations ?: [] as $dest)
			{
				$departures = $dest["Departures"]["Departure"];
				if (isset($departures["CityCode"]))
					$departures = [$departures];

				foreach ($departures ?: [] as $departure)
				{					
					$dates = $departure["Dates"]["Date"];
					if (isset($dates["@attrs"]))
						$dates = [$dates];

					foreach ($dates ?: [] as $date_q)
					{
						$dateAttrs = $date_q["@attrs"];
						$date = $date_q[0];

						// date had passed
						if (strtotime(date("Y-m-d")) > strtotime($date))
							continue;

						// date is not for this tour operator
						if ($dateAttrs["TourOpCode"] && ($dateAttrs["TourOpCode"] != $pcfg->ApiContext))
							continue;

						$transportID = $pcfg->TourOperatorHandle . "|" . $transportType . "|" . $departure["CityCode"] . ":city" . "|" . $dest["CityCode"] . ":city";
						if (!isset($transports[$transportID]))
						{
							$transport = [
								"Id" => $transportID,
								"TransportType" => $transportType,
								"TourOperator" => $pcfg->TourOperatorHandle,
								"From" => [
									"City" => [
										"Id" => $departure["CityCode"]
									]
								],
								"To" => [
									"City" => [
										"Id" => $dest["CityCode"]
									]
								],
								"Dates" => []
							];
							$transports[$transportID] = $transport;
						}

						if (!isset($transports[$transportID]["Dates"][$date]))
							$transports[$transportID]["Dates"][$date] = ["Date" => $date, "Nights" => []];

						$nights = $dateAttrs["Nights"] ? explode(",", $dateAttrs["Nights"]) : [];
						foreach ($nights ?: [] as $n)
							$transports[$transportID]["Dates"][$date]["Nights"][$n] = $n;
					}
				}
			}
		}
	}

	public function reqs_GetChartersDatesCfg($pcfg)
	{
		$transports = [];
		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Request RequestType="getPackageNVRoutesRequest">'
				. '<AuditInfo>'
					. '<RequestId>001</RequestId>'
					. '<RequestUser>' . htmlspecialchars($pcfg->ApiUsername__ ?: $pcfg->ApiUsername) . '</RequestUser>'
					. '<RequestPass>' . htmlspecialchars($pcfg->ApiPassword__ ?: $pcfg->ApiPassword) . '</RequestPass>'
					. '<RequestTime>'.date(DATE_ATOM).'</RequestTime>'
					. '<RequestLang>EN</RequestLang>'
				. '</AuditInfo>'
				. '<RequestDetails>'
					. '<getPackageNVRoutesRequest>'
						. '<Transport>plane</Transport>'
					. '</getPackageNVRoutesRequest>'
				. '</RequestDetails>'
			. '</Request>';
		
		$planeChartersRet = $this->reqs_DoRequest($pcfg, $xmlRequest);
		
		$planeCharters = ($planeChartersRet && $planeChartersRet["ResponseDetails"] && $planeChartersRet["ResponseDetails"]["getPackageNVRoutesResponse"]) ? 
			$planeChartersRet["ResponseDetails"]["getPackageNVRoutesResponse"] : [];

		if ($planeCharters && $planeCharters["Country"] && isset($planeCharters["Country"]["CountryCode"]))
			$planeCharters["Country"] = [$planeCharters["Country"]];

		$this->reqs_GetChartersDatesCfg_setTransports($transports, $pcfg, $planeCharters["Country"], "plane");

		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Request RequestType="getPackageNVRoutesRequest">'
				. '<AuditInfo>'
					. '<RequestId>001</RequestId>'
					. '<RequestUser>' . htmlspecialchars($pcfg->ApiUsername__ ?: $pcfg->ApiUsername) . '</RequestUser>'
					. '<RequestPass>' . htmlspecialchars($pcfg->ApiPassword__ ?: $pcfg->ApiPassword) . '</RequestPass>'
					. '<RequestTime>'.date(DATE_ATOM).'</RequestTime>'
					. '<RequestLang>EN</RequestLang>'
				. '</AuditInfo>'
				. '<RequestDetails>'
					. '<getPackageNVRoutesRequest>'
						. '<Transport>bus</Transport>'
					. '</getPackageNVRoutesRequest>'
				. '</RequestDetails>'
			. '</Request>';

		$busChartersRet = $this->reqs_DoRequest($pcfg, $xmlRequest);
		$busCharters = ($busChartersRet && $busChartersRet["ResponseDetails"] && $busChartersRet["ResponseDetails"]["getPackageNVRoutesResponse"]) ? 
			$busChartersRet["ResponseDetails"]["getPackageNVRoutesResponse"] : [];
		
		if ($busCharters && $busCharters["Country"] && isset($busCharters["Country"]["CountryCode"]))
			$busCharters["Country"] = [$busCharters["Country"]];

		$this->reqs_GetChartersDatesCfg_setTransports($transports, $pcfg, $busCharters["Country"], "bus");

		return [$transports, $transports];
	}

	public function reqs_GetToursDatesCfg($pcfg)
	{
		$xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Request RequestType="CircuitSearchCityRequest">'
				. '<AuditInfo>'
					. '<RequestId>001</RequestId>'
					. '<RequestUser>' . htmlspecialchars($pcfg->ApiUsername__ ?: $pcfg->ApiUsername) . '</RequestUser>'
					. '<RequestPass>' . htmlspecialchars($pcfg->ApiPassword__ ?: $pcfg->ApiPassword) . '</RequestPass>'
					. '<RequestTime>'.date(DATE_ATOM).'</RequestTime>'
					. '<RequestLang>EN</RequestLang>'
				. '</AuditInfo>'
				. '<RequestDetails>'
					. '<CircuitSearchCityRequest>'
					. '</CircuitSearchCityRequest>'
				. '</RequestDetails>'
			. '</Request>';

		$toursRet = $this->reqs_DoRequest($pcfg, $xmlRequest);
		$tours = ($toursRet && $toursRet["ResponseDetails"] && $toursRet["ResponseDetails"]["CircuitSearchCityResponse"]) ? 
			$toursRet["ResponseDetails"]["CircuitSearchCityResponse"] : [];

		if ($tours && $tours["Country"] && isset($tours["Country"]["CountryCode"]))
			$tours["Country"] = [$tours["Country"]];

		$transports = [];
		foreach ($tours["Country"] ?: [] as $td)
		{
			$cities = ($onlyOneCity = isset($td["Cities"]["City"]["CityCode"])) ? [$td["Cities"]["City"]] : $td["Cities"]["City"];
			foreach ($cities ?: [] as $city)
			{
				$toursXmlSearchRequest = '<?xml version="1.0" encoding="UTF-8"?>'
				. '<Request RequestType="CircuitSearchRequest">'
					. '<AuditInfo>'
						. '<RequestId>001</RequestId>'
						. '<RequestUser>' . htmlspecialchars($pcfg->ApiUsername__ ?: $pcfg->ApiUsername) . '</RequestUser>'
						. '<RequestPass>' . htmlspecialchars($pcfg->ApiPassword__ ?: $pcfg->ApiPassword) . '</RequestPass>'
						. '<RequestTime>'.date(DATE_ATOM).'</RequestTime>'
						. '<RequestLang>EN</RequestLang>'
					. '</AuditInfo>'
					. '<RequestDetails>'
						. '<CircuitSearchRequest>'
							. '<CountryCode>' . $td["CountryCode"]  . '</CountryCode>'
							. '<CityCode>' . $city["CityCode"]  . '</CityCode>'
							. '<CurrencyCode>EUR</CurrencyCode>'
							. '<Year>' . date("Y") . '</Year>'
							. '<Month>13</Month>'
							. '<Rooms>'
								. '<Room Code="DB" NoAdults="2"></Room>'
							. '</Rooms>'
						. '</CircuitSearchRequest>'
					. '</RequestDetails>'
				. '</Request>';

				$cityResp = $this->reqs_DoRequest($pcfg, $toursXmlSearchRequest);

				$allCircuits = ($cityResp && $cityResp["ResponseDetails"] && $cityResp["ResponseDetails"]["CircuitSearchResponse"]) ? 
					$cityResp["ResponseDetails"]["CircuitSearchResponse"] : [];

				$circuits = ($allCircuits && $allCircuits["Circuit"]) ? $allCircuits["Circuit"] : null;

				if ($circuits && isset($circuits["CircuitId"]))
					$circuits = [$circuits];

				// circuits
				foreach ($circuits ?: [] as $circuit)
				{
					// skip because not the context
					if (!$circuit["TourOpCode"] || $circuit["TourOpCode"] != $pcfg->ApiContext)
						continue;

					$tourDestinations = 
						($circuit["Destinations"] && $circuit["Destinations"]["CircuitDestination"]) ? $circuit["Destinations"]["CircuitDestination"] : null;

					if ($tourDestinations && $tourDestinations["CityCode"])
						$tourDestinations = [$tourDestinations];

					$isDestinationTour = false;
					foreach ($tourDestinations ?: [] as $dest)
					{
						if ($dest['CityCode'] == $city['CityCode'])
						{
							$isDestinationTour = true;
							break;
						}
					}

					if (!$isDestinationTour)
					{
						// not in destination
						continue;
					}

					if (!($period = $circuit["Period"]))
						throw new \Exception("Tour must have period!");

					$variants = ($circuit["Variants"] && $circuit["Variants"]["Variant"]) ? $circuit["Variants"]["Variant"] : null;
					if ($variants && $variants["UniqueId"])
						$variants = [$variants];

					foreach ($variants ?: [] as $variant)
					{
						$variantTopCode = $variant["UniqueId"] ? end(explode("|", $variant["UniqueId"])) : null;

						// filter variant by top code
						if (!$variantTopCode || ($variantTopCode != $pcfg->ApiContext))
						{
							continue;
						}

						$transportType = null;
						$services = $variant["Services"]["Service"];

						if ($services)
						{
							if ($services["Type"])
								$services = [$services];

							foreach ($services as $serv)
							{
								if ($serv["Type"] == "7")
								{
									$transportType = strtolower($serv["Transport"]);
									break;
								}
							}
						}

						if (!$transportType)
							throw new \Exception("Transport type cannot be determined!");

						if (!($date = date("Y-m-d", strtotime($variant["InfoCharter"]["DepDate"]))))
							throw new \Exception("Date cannot be determined!");

						foreach ($tourDestinations ?: [] as $dest)
						{
							$transportID = $pcfg->TourOperatorHandle . "|" . $transportType . "|:" . "|" . $dest["CityCode"] . ":city";
							if (!isset($transports[$transportID]))
							{
								$transport = [
									"Id" => $transportID,
									"TransportType" => "plane",
									"TourOperator" => $pcfg->TourOperatorHandle,
									"From" => [],
									"To" => [
										"City" => [
											"Id" => $dest["CityCode"]
										]
									],
									"Dates" => []
								];
								$transports[$transportID] = $transport;
							}

							if (!isset($transports[$transportID]["Dates"][$date]))
								$transports[$transportID]["Dates"][$date] = ["Date" => $date, "Nights" => []];
							$transports[$transportID]["Dates"][$date]["Nights"][$period] = $period;
						}
					}
				}
			}
		}
		return [$transports, $transports];
	}
}
