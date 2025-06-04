<?php

namespace Integrations\Amara;

use App\Support\Collections\StringCollection;
use App\Support\Log;
use DOMDocument;
use Exception;
use SoapClient;
use Utils\Utils;

class AmaraSoap_old extends SoapClient
{
	public string $currentRequestUrlWSDL;
	public string $currentRequestDealerCode;
	public string $currentRequestHash;
	public string $currentRequestPassword;
	public string $currentRequestUsername;
	public string $currentRequestMethod;
	public string $currentRequestUrl;
	public string $xmlRequest;
	public ?string $adults;
	public ?StringCollection $childrenAges;
	public ?string $transportId;
	public ?string $hotelId;
	public ?string $bookingPrice;
	public ?string $bookingCurrency;
	public ?string $roomCombinationDescription;
	public ?string $roomCombinationId;
	public ?array $passengers;
	public ?string $unificationCode;
	public ?string $pictureName;
	public ?string $handle;
	private const TEST_HANDLE = 'localhost-amara_v2';

	public function getRequestXml():string 
	{
		return $this->xmlRequest;
	}

	// GetRoutesInfo is cached 1 day
	public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
	{
		$downloadPictureRequestString = '';
		$onlineSearch = '';
		$booking = '';
		
		if ($this->currentRequestMethod === 'DownloadPictureByUnificationCode') {
			$downloadPictureRequestString = '<tem:unificationCode>' . $this->unificationCode . '</tem:unificationCode>
				<tem:pictureName>' . htmlspecialchars($this->pictureName) . '</tem:pictureName>';
		}

		$action = '';
		if ($this->currentRequestMethod === 'MakeReservation' || $this->currentRequestMethod === 'ValidateBeforeReservation') {
			$passengers = '';
			$action = 'IReservations';
			foreach ($this->passengers as $passenger) {
				$passengers .= 
					'<web:ReservationTourist>
						<web:BirthDate>'.$passenger['BirthDate'].'</web:BirthDate>
						<web:FirstName>'.$passenger['FirstName'].'</web:FirstName>
						<web:IsAdult>'.$passenger['IsAdult'].'</web:IsAdult>
						<web:IsInfant>'.$passenger['IsInfant'].'</web:IsInfant>
						<web:IsMale>'.$passenger['IsMale'].'</web:IsMale>
						<web:LastName>'.$passenger['LastName'].'</web:LastName>
					</web:ReservationTourist>';
			}

			$booking = '<tem:requestInfo>
				<web:BookIfOnRequest>true</web:BookIfOnRequest>
				<web:CachedPrice>'.$this->bookingPrice.'</web:CachedPrice>
				<web:CachedPriceCurrency>'.$this->bookingCurrency.'</web:CachedPriceCurrency>
				<web:Rooms>
					<web:ReservationRoom>
						<web:RoomCombinationDescription>'.$this->roomCombinationDescription.'</web:RoomCombinationDescription>
						<web:RoomCombinationID>'.$this->roomCombinationId.'</web:RoomCombinationID>
						<web:Tourists>
							'.$passengers.'
						</web:Tourists>
					</web:ReservationRoom>
				</web:Rooms>
			</tem:requestInfo>';
		} else {
			$action = 'IOffer';
		}

		if ($this->currentRequestMethod === 'OnlineSearch') {
			$childrenAges = '';
			$infantsEl = '';
			$hotelId = '';

			if (!empty($this->hotelId)) {
				$hotelId = '<web:UnificationCode>'.$this->hotelId.'</web:UnificationCode>';
			}

			if (isset($this->childrenAges) && $this->childrenAges !== null && count($this->childrenAges) > 0) {

				$newChildrenAges = [];
				$infants = 0;

				foreach ($this->childrenAges as $age) {
					if (empty($age)) {
						continue;
					}
					
					if ($age < 2) {
						$infants++;
					} else {
						$newChildrenAges[] = $age;
					}
				}
				if ($infants > 0) {
					$infantsEl = '<kar:InfantsNo>'.$infants.'</kar:InfantsNo>';
				}

				$childrenAges = '<kar:ChildrenAges>';
				foreach ($newChildrenAges as $childrenAge) {
					$childrenAges .= '<arr:int>'.$childrenAge.'</arr:int>';
				}
				$childrenAges .= '</kar:ChildrenAges>';
			}


			$onlineSearch = '<tem:requestInfo>
				<web:RequestedRooms>
					<kar:OnlineSearchRoom>
						<kar:AdultsNo>'.$this->adults.'</kar:AdultsNo>
						'.$childrenAges.'
						'.$infantsEl.'
					</kar:OnlineSearchRoom>
				</web:RequestedRooms>
				<web:SeasonTransportTimeTableID>'.$this->transportId.'</web:SeasonTransportTimeTableID>
				'.$hotelId.'
			</tem:requestInfo>';
		}
		
		$this->xmlRequest = '<soap:Envelope 
			xmlns:soap="http://www.w3.org/2003/05/soap-envelope" 
			xmlns:tem="http://tempuri.org/" 
			xmlns:kar="http://schemas.datacontract.org/2004/07/KartagoBL.ExportXML.Oferte" 
			xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays"
			xmlns:web="http://schemas.datacontract.org/2004/07/WebAPI.Model">
		   <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">
				<wsa:To>' . $this->currentRequestUrl . '</wsa:To>
				<wsa:Action>http://tempuri.org/'.$action.'/' . $this->currentRequestMethod . '</wsa:Action>
			</soap:Header>
		   <soap:Body>
			  <tem:' . $this->currentRequestMethod . '>
				 <tem:userToken>
					<web:DealerCode>' . $this->currentRequestDealerCode . '</web:DealerCode>
					<web:Hash>' . $this->currentRequestHash . '</web:Hash>
					<web:Password>' . $this->currentRequestPassword . '</web:Password>
					<web:UserName>' . $this->currentRequestUsername . '</web:UserName>
				 </tem:userToken>'
				. $onlineSearch
				. $booking
				. $downloadPictureRequestString .
			  '</tem:' . $this->currentRequestMethod . '>
		   </soap:Body>
		</soap:Envelope>';

		$index1 = strpos($this->currentRequestUrl, '://');
		$hostSub = substr($this->currentRequestUrl, $index1 + 3);
        $index2 = strpos($hostSub, '.ro');
        $host = substr($hostSub, 0, $index2 + 3);

		$headers = [
			'Host: '.$host,
			'Content-Type: application/soap+xml;charset=UTF-8',
			'Accept: text/xml',
			'Cache-Control: no-cache',
			'Pragma: no-cache',
			'SOAPAction: http://tempuri.org/'.$action.'/' . $this->currentRequestMethod,
			'Content-length: '.strlen($this->xmlRequest),
		];

		$xml = null;
		if ($this->currentRequestMethod === 'GetRoutesInfo') {
			$xml = Utils::getFromCache($this->handle, 'routes-info');
		}

		if ($xml === null) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($ch, CURLOPT_URL, $this->currentRequestUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERPWD, $this->currentRequestUsername.":".$this->currentRequestPassword);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_TIMEOUT, 300);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xmlRequest);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, false);
	
			$response = curl_exec($ch); 

			curl_close($ch);

			$xml = "<?xml version='1.0' encoding='UTF-8' ?>";
			
			$ns = 's';
			if ($this->handle === self::TEST_HANDLE) {
				$ns = 'env';
			}
			$pos = strpos($response, '<'.$ns.':Envelope');
			$xml =  $xml . substr($response, $pos);

			$posEnd = strpos($xml, '</'.$ns.':Envelope>');

			$xml = substr($xml, 0, $posEnd + strlen('</'.$ns.':Envelope>'));

			try {
				simplexml_load_string($xml);
			} catch (Exception $e) {
				return $response;
			}

			if ($this->currentRequestMethod === 'GetRoutesInfo') {
				Utils::writeToCache($this->handle, 'routes-info', $xml);
			}
		}

		return $xml;
	}
}