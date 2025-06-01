<?php

namespace Omi\TF;

trait SolvexReservation
{
	protected function createBookingRequest($data)
	{
		if (!$data->OrderOffers || !$data->OrderOffers[0] || (!($offer = $data->OrderOffers[0]->Offer)) || (!($book_data = $offer->BookData)) || 
			(!($roomItm = $offer->getRoomItem())))
			throw new \Exception("No data for request!");

		try
		{
			$this->SOAPInstance->login();
		}
		catch (\Exception $ex)
		{
			$ttex = new \Exception('Login-ul in sistemul tur operatorului a esuat: ' . $ex->getMessage());
			$this->logError([
				"booking:err_login" => true, 

				"ApiUrl" => $this->TourOperatorRecord->ApiUrl, 
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername, 
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword, 
				
				"ApiUrl__" => $this->TourOperatorRecord->ApiUrl__, 
				"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__, 
				"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__, 

				"\$data" => $data, 
				"reqXML" => $this->SOAPInstance->client->__getLastRequest(),
				"respXML" => $this->SOAPInstance->client->__getLastResponse(),
				"reqHeaders" => $this->SOAPInstance->client->__getLastRequestHeaders(),
				"respHeaders" => $this->SOAPInstance->client->__getLastResponseHeaders()
			], $ttex);
			throw $ttex;
		}

		try 
		{
			// find proper rate
			$rates = $this->SOAPInstance->client->GetRates();
		} 
		catch (\Exception $ex) 
		{
			$ttex = new \Exception('Datele pentru pre-booking nu au putut fi preluate din sistemul tur operatorului: ' . $ex->getMessage());
			$this->logError([
				"booking:err_getting_rates" => true, 
				"ApiUrl" => $this->TourOperatorRecord->ApiUrl, 
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername, 
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword, 
				
				"ApiUrl__" => $this->TourOperatorRecord->ApiUrl__, 
				"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__, 
				"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__, 
				
				"\$data" => $data, 
				"reqXML" => $this->SOAPInstance->client->__getLastRequest(),
				"respXML" => $this->SOAPInstance->client->__getLastResponse(),
				"reqHeaders" => $this->SOAPInstance->client->__getLastRequestHeaders(),
				"respHeaders" => $this->SOAPInstance->client->__getLastResponseHeaders()
			], $ttex);
			throw $ttex;
		}

		/*
		$accomodations = $this->SOAPInstance->client->GetAccommodations([
			'guid' => $this->SOAPInstance->connect_result->ConnectResult,
		]);
		*/

		// $pansions = $this->SOAPInstance->client->GetPansions();
		
		$currency_rate = null;
		foreach ($rates->GetRatesResult->Rate as $rate)
		{
			if (strtoupper($rate->Code) === "EU")
			{
				$currency_rate = $rate;
				break;
			}
		}

		$adults_count = 0;
		$start_date = $roomItm->CheckinAfter;
		$end_date = $roomItm->CheckinBefore;
		$interval = date_diff(date_create($end_date), date_create($start_date));
		$duration = (int)$interval->format('%a');

		$create_date = date('Y-m-d'); // year-00-00
		$price = $book_data->Cost;
	
		$partner_id = ($this->SOAPInstance->ApiContext__ ?: $this->SOAPInstance->ApiContext);
		$reservation_name = $book_data->HotelName; # @TODO - include no. of adults & misc data
		$rate_code = "";
		$price_brut = $price;
		$price_net = $price;
		
		$hotel_id = $book_data->HotelKey;
		$country_id = $book_data->CountryKey;
		$city_id = $book_data->CityKey;
		
		$room_type_id = $book_data->RtKey;
		$room_categ_id = $book_data->RcKey;
		$room_acc_id = $book_data->AcKey;
		$meal_id = $book_data->PnKey;
		
		$firstOrderOff = $data->OrderOffers[0];
		
		$tourists = [];
		if (($info = (($orderRoom = $firstOrderOff->getRoomItem())) ? $orderRoom->Info : null))
		{
			foreach ($info->Adults ?: [] as $adult)
			{
				$tourists[] = [
					'Lastname' => $adult->Name,
					'Firstname' => $adult->Firstname,
					'Surname' => '',
					'Birthdate' => $adult->BirthDate,
					'Gender' => ($adult->Gender == 1) ? 'Male' : 'Female', // Male
					'AgeType' => "Adult", // age type(Adult = 0, Child = 1, Infant = 2)
					"IsChild" => false,
					"IsInfant" => false
				];
				$adults_count++;
			}

			foreach ($info->Children ?: [] as $child)
			{
				$isInfant = false;

				$tourists[] = [
					'Lastname' => $child->Name,
					'Firstname' => $child->Firstname,
					'Surname' => '',
					'Birthdate' => $child->BirthDate,
					//'Gender' => "Child", // Male
					'Gender' => "Child", // Male
					'AgeType' => $isInfant ? "Infant" : "Child", // age type(Adult = 0, Child = 1, Infant = 2)
					'IsChild' => !$isInfant,
					"IsInfant" => $isInfant
				];
			}
		}

		$services = [
			'hotel' => [
				[
					'tourists' => $tourists,
					'price' => $price,
					'adults_count' => $adults_count,
					'partner_id' => $partner_id,
					'reservation_name' => $reservation_name,
					'start_date' => $start_date,
					'days' => $duration,
					'rate_code' => $rate_code,
					'cost_brut' => $price_brut,
					'cost_net' => $price_net,
					'hotel' => [
						'id' => $hotel_id,
						'country_id' => $country_id,
						'city_id' => $city_id,
					],
					'room' => [
						'type_id' => $room_type_id,
						'categ_id' => $room_categ_id,
						'acc_id' => $room_acc_id,
					],
					'meal_id' => $meal_id
				]
			]
		];
		
		$tourists_count = q_count($tourists);
		$services_count = 1; // total number of services
		
		
		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

		$xml = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" '
				. 'xmlns="http://www.megatec.ru/" '
				. 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
				. 'xmlns:xsd="http://www.w3.org/2001/XMLSchema"><SOAP-ENV:Body>';

		$xml .= "<CreateReservation>"
			. "<guid>" . $this->SOAPInstance->connect_result->ConnectResult . "</guid>"
			. "<reserv HasInvoices=\"false\">" .
	"<agentDiscount>0</agentDiscount>
	<Rate>
		<Name />
		<ID>{$currency_rate->ID}</ID>
		<Description />
		<NameLat />
		<Code>EU</Code>
		<CodeLat />
		<Unicode />
		<IsMain>false</IsMain>
		<IsNational>false</IsNational>
	</Rate>\n";
		
		$xml .= $this->reservationLinkTouristServices($tourists_count, $services_count);
		
		$xml .= $this->reservationSetupServices($this->escapeData($services));
		
		$xml .= "	
	
	<ID>-1</ID>
	<Name />
	<Netto>{$book_data->Cost}</Netto>
	<Brutto>{$book_data->Cost}</Brutto>
	<CountryID>{$country_id}</CountryID>
	<CityID>{$city_id}</CityID>
	<PartnerID>" . ($this->SOAPInstance->ApiContext__ ?: $this->SOAPInstance->ApiContext) . "</PartnerID>
	<AgentDiscount>0</AgentDiscount>
	<Status>WaitingConfirmation</Status>
	<StartDate>{$start_date}T00:00:00</StartDate>
	<EndDate>{$end_date}T00:00:00</EndDate>
	<Duration>{$duration}</Duration>
	<CreationDate>{$create_date}T00:00:00</CreationDate>
	<CreatorID>0</CreatorID>\n";
		
		$xml .= $this->reservationSetupTouristsData($this->escapeData($tourists));
		
		$xml .= "	<OwnerID>0</OwnerID>
	<ExternalID>0</ExternalID>
	<AdditionalParams>
		<ParameterPair Key=\"PCNId\">
			<Value xsi:type=\"xsd:int\">0</Value>
		</ParameterPair>
	</AdditionalParams>\n";

		$xml .= "</reserv></CreateReservation>\n";
		$xml .= "</SOAP-ENV:Body></SOAP-ENV:Envelope>";

		/*
		echo "<pre>\n";
		echo htmlspecialchars($xml);
		echo "</pre>\n";
		*/

		/*
		POST /iservice/integrationservice.asmx HTTP/1.1
		Host: evaluation.solvex.bg
		Connection: Keep-Alive
		User-Agent: PHP-SOAP/7.0.20
		Content-Type: text/xml; charset=utf-8
		SOAPAction: "http://www.megatec.ru/Connect"
		Content-Length: 294
		Authorization: Basic c29sMDI3czpkZXZz
		*/
		
		//qvardump($this->TourOperatorRecord);
		//q_die();

		$method = "CreateReservation";
		$soapAction = "http://www.megatec.ru/" . $method;
		$headers = array(
			"Connection: Keep-Alive",
			"User-Agent: PHP-SOAP/" . phpversion(),
			"Content-type: text/xml;charset=\"utf-8\"",
			"Content-length: ".strlen($xml),
			"Accept: text/xml",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"SOAPAction: \"{$soapAction}\"",
		);

		$ch = q_curl_init_with_log();
		q_curl_setopt_with_log($ch, CURLOPT_URL, $this->TourOperatorRecord->ApiUrl);
		q_curl_setopt_with_log($ch, CURLOPT_USERPWD, ($this->TourOperatorRecord->ApiUsername__ ?: $this->TourOperatorRecord->ApiUsername) . ":" 
			. ($this->TourOperatorRecord->ApiPassword__ ?: $this->TourOperatorRecord->ApiPassword));
		q_curl_setopt_with_log($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		q_curl_setopt_with_log($ch, CURLOPT_CONNECTTIMEOUT, 20);
		q_curl_setopt_with_log($ch, CURLOPT_TIMEOUT, 120);
		q_curl_setopt_with_log($ch, CURLOPT_RETURNTRANSFER, true);
		q_curl_setopt_with_log($ch, CURLOPT_FOLLOWLOCATION, true);
		q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYPEER, false);
		q_curl_setopt_with_log($ch, CURLOPT_SSL_VERIFYHOST, false);
		q_curl_setopt_with_log($ch, CURLOPT_POST, true);
		q_curl_setopt_with_log($ch, CURLOPT_POSTFIELDS, $xml);
		q_curl_setopt_with_log($ch, CURLOPT_HTTPHEADER, $headers);
		q_curl_setopt_with_log($ch, CURLINFO_HEADER_OUT, true);

		list ($proxyUrl, $proxyPort, $proxyUsername, $proxyPassword) = \Omi\TFuse\Api\TravelFuse::GetTourOperatorProxyUrl($this->TourOperatorRecord);
		if ($proxyUrl)
			q_curl_setopt_with_log($ch, CURLOPT_PROXY, $proxyUrl . ($proxyPort ? ":" . $proxyPort : ""));
		#if ($proxyPort)
		#	q_curl_setopt_with_log($ch, CURLOPT_PROXYPORT, $proxyPort);
		if ($proxyUsername)
			q_curl_setopt_with_log($ch, CURLOPT_PROXYUSERNAME, $proxyUsername);
		if ($proxyPassword)
			q_curl_setopt_with_log($ch, CURLOPT_PROXYUSERPWD, $proxyPassword);

		$resp = q_curl_exec_with_log($ch);

		$this->logData("booking", [
			"ApiUrl" => $this->TourOperatorRecord->ApiUrl, 
			"ApiUsername" => $this->TourOperatorRecord->ApiUsername, 
			"ApiPassword" => $this->TourOperatorRecord->ApiPassword, 
			
			"ApiUrl__" => $this->TourOperatorRecord->ApiUrl__, 
			"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__, 
			"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__, 

			"\$data" => $data, 
			"reqXML" => $xml,
			"respXML" => $resp
		]);

		$err = ($resp === false) ? curl_error($ch) : null;

		if ($err)
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError([
				"booking:err_invalid_response" => true, "ApiUrl" => $this->TourOperatorRecord->ApiUrl, 
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername, 
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword, 
				
				"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__, 
				"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__, 
				
				"\$data" => $data, 
				"reqXML" => $xml,
				"respXML" => $resp
			], $ex);
			throw $ex;
		}

		$info = curl_getinfo($ch);
		curl_close($ch);

		if ($err || (!$resp) || (!($decodedData = $this->decodeBooking($resp))))
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError([
				"booking:err_cannot_process" => true, 
				"ApiUrl" => $this->TourOperatorRecord->ApiUrl, 
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername, 
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword, 
				
				"ApiUrl__" => $this->TourOperatorRecord->ApiUrl__, 
				"ApiUsername__" => $this->TourOperatorRecord->ApiUsername__, 
				"ApiPassword__" => $this->TourOperatorRecord->ApiPassword__, 
				"\$data" => $data, 
				"reqXML" => $xml,
				"\$decodedData" => $decodedData,
				"respXML" => $resp
			], $ex);
			throw $ex;
		}

		// set tour operator
		if (!($data->InTourOperatorId = $decodedData["CreateReservationResponse"]["CreateReservationResult"]["ExternalID"]))
		{
			$ex = new \Exception("Comanda a fost trimisa la tur operator insa acesta a raspuns cu eroare!" 
				. "\nIn unele cazuri tur operatorul poate procesa comanda!"
				. "\nVa rugam verificati b2b-ul tur operatorului!");
			$this->logError([
				"booking:err_cannot_decode" => true, 
				"ApiUrl" => $this->TourOperatorRecord->ApiUrl, 
				"ApiUsername" => $this->TourOperatorRecord->ApiUsername, 
				"ApiPassword" => $this->TourOperatorRecord->ApiPassword, 
				"\$data" => $data, 
				"reqXML" => $xml,
				"\$decodedData" => $decodedData,
				"respXML" => $resp
			], $ex);
			throw $ex;
		}

		//$request, $response, $wsdl, $options, $login, $password, $method, $args, $location, $action, $version, $one_way
		// save the call

		\Omi\Util\SoapClientAdvanced::SaveCall($xml, $resp, ($this->TourOperatorRecord->ApiUrl__ ?: $this->TourOperatorRecord->ApiUrl), null, 
				($this->TourOperatorRecord->ApiUsername__ ?: $this->TourOperatorRecord->ApiUsername), 
				($this->TourOperatorRecord->ApiPassword__ ?: $this->TourOperatorRecord->ApiPassword), 
				$method, 
				null, $this->TourOperatorRecord->ApiUrl, $soapAction, 1, false);

		$data->save("InTourOperatorId, ApiOrderData, InTourOperatorStatus, InTourOperatorRef, Status");

		$data->_top_response_message = "A fost efecutata comanda cu "
			. ($data->InTourOperatorRef ? "numarul [{$data->InTourOperatorRef}] si cu " : "")
			. "referinta [{$data->InTourOperatorId}].\n"
			. ($data->InTourOperatorStatus ? "Statusul comenzii este [{$data->InTourOperatorStatus}]" : "") . ".";
		
		return $data;
	}

	public function decodeBooking($resp)
	{
		$ret = null;
		try
		{
			$xml = simplexml_load_string($resp);
			$xmlChild = $xml->children('soap', true)->Body;

			$ret = $this->simpleXML2Array($xmlChild);
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		
		return $ret;
	}

	protected function reservationLinkTouristServices(int $tourists, int $services)
	{
		if ((!$tourists) || (!$services))
			return "";
		
		$xml = "\t<TouristServices>\n";
		
		for ($t_id = -1; $t_id >= -$tourists; $t_id--)
		{
			for ($s_id = -1; $s_id >= -$services; $s_id--)
			{
				//<ServiceID>{$s_id}</ServiceID>
				$xml .= 
"		<TouristService>
			<ID>0</ID>
			<Name />
			<TouristID>{$t_id}</TouristID>
			<ServiceID>1</ServiceID>
		</TouristService>\n";
			}
		}
		$xml .= "\t</TouristServices>\n";
		
		return $xml;
	}
	
	protected function reservationSetupTouristsData(array $tourists, int $depth = 0)
	{
		if (!$tourists)
			return "";

		$xml .= "	<Tourists>\n";
		$tourist_id = -1;
		$is_main = true;
		foreach ($tourists as $tourist)
		{
			$xml .= 
"		<Tourist Name=\"\" Sex=\"{$tourist['Gender']}\" FirstName=\"\" LastName=\"\" SurName=\"{$tourist['Lastname']}\" BirthDate=\"{$tourist['Birthdate']}T00:00:00\" ".
						"FirstNameLat=\"{$tourist['Firstname']}\" LastNameLat=\"{$tourist['Lastname']}\" SurNameLat=\"{$tourist['Lastname']}\" ".
						"AgeType=\"{$tourist['AgeType']}\" Citizen=\"\" IsMain=\"".($is_main ? 'true' : 'false')."\" ExternalID=\"0\" ID=\"{$tourist_id}\">
			<LocalPassport xsi:nil=\"true\" />
            <ForeignPassport xsi:nil=\"true\" />
            <AdditionalParams xsi:nil=\"true\" />
		</Tourist>\n";
						
			$is_main = false;
			$tourist_id--;
		}
		$xml .= "	</Tourists>\n";
		
		if ($depth > 0)
		{
			$xml = str_pad("", $depth, "\t").preg_replace("/(\\n)/us", "\n".str_pad("", $depth, "\t"), rtrim($xml))."\n";
		}
		
		return $xml;
	}
	
	protected function reservationSetupHotelService(array $data, int $index)
	{	
		$xml = "\t\t<Service xsi:type=\"HotelService\">
			<ExternalID>0</ExternalID>
			<Price>{$data['price']}</Price>
			<NMen>" . q_count($data['tourists']) . "</NMen>
			<PartnerID>{$data['partner_id']}</PartnerID>
			<PacketKey>0</PacketKey>" . 
			$this->reservationSetupTouristsData($data['tourists'], 2) . 
			"<DetailNetto />
			<DetailBrutto />
			<Notes />
			<Name>{$data['reservation_name']}</Name>
			<StartDate>{$data['start_date']}T00:00:00</StartDate>
			<StartDay>0</StartDay>
			<Duration>{$data['days']}</Duration>
			<RateBrutto>{$data['rate_code']}</RateBrutto>
			<Brutto>{$data['cost_brut']}</Brutto>
			<RateNetto />
			<Netto>{$data['cost_net']}</Netto>
			<ServiceClassID>0</ServiceClassID>
			<TouristCount>" . q_count($data['tourists']) . "</TouristCount>
			<ID>1</ID>
			<Hotel>
				<Name/>
				<ID>{$data['hotel']['id']}</ID>
				<Description />
				<NameLat />
				<Code />
				<CodeLat />
				<Unicode />
				<City>
					<Name />
					<ID>{$data['hotel']['city_id']}</ID>
					<Description />
					<NameLat />
					<Code />
					<CodeLat />
					<Unicode />
					<CountryID>{$data['hotel']['country_id']}</CountryID>
					<RegionID>0</RegionID>
				</City>
				<RegionID>0</RegionID>
				<PriceType>None</PriceType>
				<CountCosts>0</CountCosts>
				<CityID>{$data['hotel']['city_id']}</CityID>
				<HotelCategoryID>0</HotelCategoryID>
			</Hotel>
			<Room>
				<RoomTypeID>{$data['room']['type_id']}</RoomTypeID>
				<RoomCategoryID>{$data['room']['categ_id']}</RoomCategoryID>
				<RoomAccomodationID>{$data['room']['acc_id']}</RoomAccomodationID>
				<ID>0</ID>
				<Name />
			</Room>
			<RoomID>0</RoomID>
			<PansionID>{$data['meal_id']}</PansionID>
		</Service>\n";
		return $xml;
	}
	
	protected function reservationSetupServices(array $services)
	{
		if (!$services)
			return "";
		
		$xml = "\t<Services>\n";
		$s_index = 1;
		foreach ($services as $serv_type => $serv_data_list)
		{
			foreach ($serv_data_list as $serv_data)
			{
				if ($serv_type === 'hotel')
				{
					$xml .= $this->reservationSetupHotelService($serv_data, $s_index++);
				}
				else
					throw new \Exception('Service not implemented');
			}
		}
		$xml .= "\t</Services>\n";
		return $xml;
	}

	public function escapeData(array $data)
	{
		array_walk_recursive($data, function (&$value, $key) {
			if (is_string($value))
				$value = htmlentities($value);
		});
		return $data;
	}
}