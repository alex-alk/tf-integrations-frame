<?php

namespace Integrations\TeztourV2;

use App\Entities\Availability\AirportTaxesCategory;
use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\MealMerchType;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\ReturnTransportItem;
use App\Entities\Availability\Room;
use App\Entities\Availability\RoomCollection;
use App\Entities\Availability\RoomMerch;
use App\Entities\Availability\RoomMerchType;
use App\Entities\Availability\TransferCategory;
use App\Entities\Availability\TransportMerch;
use App\Entities\Availability\TransportMerchCategory;
use App\Entities\Availability\TransportMerchLocation;
use App\Entities\AvailabilityDates\AvailabilityDates;
use App\Entities\AvailabilityDates\array;
use App\Entities\AvailabilityDates\DateNight;
use App\Entities\AvailabilityDates\DateNightCollection;
use App\Entities\AvailabilityDates\TransportCity;
use App\Entities\AvailabilityDates\TransportContent;
use App\Entities\AvailabilityDates\TransportDate;
use App\Entities\AvailabilityDates\TransportDateCollection;
use App\Entities\Booking;
use App\Entities\City;
use App\Entities\Country;
use App\Entities\Hotels\Facility;
use App\Entities\Hotels\FacilityCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Region;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\Passenger;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\array;
use App\Support\Collections\Custom\[];
use App\Support\Collections\Custom\[];
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Log;
use DateTime;
use Exception;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\CountryCodeMap;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\Validator;
use SimpleXMLElement;
use Utils\Utils;

class TeztourV2ApiService extends AbstractApiService
{
	private static $regionGroups = [
		[
			4433,// Alanya
			1285,// Antalya
			12689,// Belek
			5736,// Kemer
			12691// Side
		],
		[
			12706,// Bodrum
			149827, // Cesme
			9004247, // Didim
			12705, // Fethiye
			70616, // Izmir
			139343, // Kusadasi
			4434 // Marmaris
		],
		[
			9004230, // Crete-Chania
			7067584, // Crete-Heraklion
			199545, // CRETE-LASSITHI
			9004227, // CRETE-RETHYMNO
		],
		[
			360178, // RHODES-FANES
			9003981, // RHODES-IALYSOS/RODOS
			348099, // RHODES-KALLITHEA/FALIRAKI
			348097, // RHODES-KOLYMBIA
			348102, // RHODES-LINDOS
		],
		[
			602992, // PELOPONNESE - KALAMATA
			343876, // PELOPONNESE
			665647, // PELOPONNESE -WEST GREECE - AITOLOAKARNANIA
		],
		[
			26313, // Dahab
			5735 // SHARM EL-SHEIKH
		],
		[
			14351, // EL GOUNA
			5734, // HURGHADA
			14355, // MAKADY BAY
			111466, // MARSA ALAM
			14353, // SAFAGA
			416623, // SAHL HASHEESH
			14354, // SOMA BAY
		]
	];

	private const TEST_HANDLE = 'localhost-teztour_v2';

	private const ROMANIA_ID = 156505;
	
	public $cachedData = [];
	
	public $freeSeatsStatuses = ['Есть', 'Мало']; // exists, few
	
	private const EUR_ID = 18864;
	
	private const BUCHAREST_ID = '9001185';

	private const API_KEY = 'ea60d0b6f6697a4a6e6e1b8267294087';
	
	public function apiTestConnection(): bool
	{
		$session = $this->getSession();
		$dataXml = simplexml_load_string($session);

		$sessionId = (string) $dataXml->sessionId;
		if (!empty($sessionId)) {
			return true;
		}

		return false;
	}

	private function getAviaReferenceFlight($params = null, $flight = null): ?array
	{
		$url = 'https://www.tez-tour.com';
		if ($this->handle === self::TEST_HANDLE) {
			$url = $this->apiUrl;
		}

		$useUrl = $url . '/avia-reference/flights?' . http_build_query($params);

		$client = HttpClient::create();
		
		$request = $client->request(HttpClient::METHOD_GET, $useUrl);
		$data = $request->getBody();
		$this->showRequest(HttpClient::METHOD_GET, $useUrl, [], $data, $request->getStatusCode());

		$this->cachedData["aviaFlights"][$useUrl] = $data;
		
		$dataDec = json_decode($data, true);

		$ret = null;
		
		$flightsById = $this->getFlightsById(true);

		$flights = $dataDec["flights"];
		$fullFlight = $flightsById[$flight["Flight"]];
		$flightNumber = $fullFlight["number"];
		

		if (isset($flights['airCompany'])) {
			$flights = [$flights];
		}
		
		$indexedFlights = [];
		foreach ($flights as $flightReq) {
			if (isset($indexedFlights[$flightReq["flightNumber"]])) {
				throw new Exception("multiple flights with same flight number!");
			}
			$indexedFlights[$flightReq["flightNumber"]] = $flightReq;
		}

		$ret = $indexedFlights[$flightNumber] ?? null;

		return $ret;
	}

	public function apiGetCountries(): array
	{
		Validator::make()
			->validateUsernameAndPassword($this->post);

		$countriesXml = $this->getXml('countries');

		$countriesFromB2B = ['TR', 'EG', 'ES', 'AE', 'GR', 'CY', 'RO', 'IT', 'PT'];

		$countriesMapping = CountryCodeMap::getCountryCodeMap();
		
		$countries = [];
		foreach ($countriesXml->item as $countryItem) {
	
			$country = new Country();
			$country->Id = (string)$countryItem->id;

			$country->Name = (string)$countryItem->name;
			$countryNameTrimmed = trim(ucfirst(strtolower( $country->Name)));
			
			if (!isset( $countriesMapping[$countryNameTrimmed])) {
				continue;
			}

			$country->Code = $countriesMapping[$countryNameTrimmed];

			if (!in_array($country->Code, $countriesFromB2B)) {
				continue;
			}
			$countries->put($country->Id, $country);
		}
		$this->cacheFiles();

		return $countries;
	}

	private function cacheFiles(): void
	{
		$this->getXml('flights');
		$this->getXml('seatSets');
		$this->getXml('genders');
		$this->getXml('stayTypes');
	}

	public function apiGetRegions(): []
	{
		Validator::make()
			->validateUsernameAndPassword($this->post);

		$regionsXml = $this->getXml('regions');
		$countries = $this->apiGetCountries();

		$regions = [];
		foreach ($regionsXml->item as $regionData)
		{
			$region = new Region();
			$region->Id = (string)$regionData->id;
			$region->Name = (string)$regionData->name;
			$countryExternalId = (string) $regionData->prop;
			
			$country = $countries->get($countryExternalId);

			if ($country === null) {
				continue;
			}
			$region->Country = $country;
			
			$regions->put($region->Id, $region);
		}

		return $regions;
	}


	public function apiGetCities(CitiesFilter $params = null): array
	{
		Validator::make()
			->validateUsernameAndPassword($this->post);

		$regions = $this->apiGetRegions();
		$citiesXml = $this->getXml('cities');
		
		$cities = [];
		foreach ($citiesXml->item as $cityData) {
			$city = new City();
			$city->Id = (string)$cityData->id;
			$city->Name = trim((string)$cityData->name);

			$region = $regions->get(trim((string)$cityData->region));
			if ($region === null) {
				continue;
			}

			$city->County = $region;
			$city->Country = $region->Country;
			$cities->put($city->Id, $city);
		}

		return $cities;
	}
	
	public function apiGetHotels(): []
	{
		$cities = $this->apiGetCities();
		$hotelsXml = $this->getXml('hotels');
	
		$hotels = [];
		foreach ($hotelsXml->item as $hotelData) {
	
			$cityId = '';

			$hotelProps = $hotelData->prop;
			foreach ($hotelProps as $hotelProperty) {

				$propName = (string) $hotelProperty['name'];
				$propValue = (string) $hotelProperty;
				if ($propName == 'City') {
					$cityId = $propValue;
					break;
				}
			}

			$city = $cities->get($cityId);

			if ($city === null) {
				continue;
			}

			$hotel = Hotel::create((string) $hotelData->id, (string) $hotelData->name, $city);

			$hotels->put($hotel->Id, $hotel);
		}

		return $hotels;
	}

	public function apiGetOffers(AvailabilityFilter $filter): array
	{
		$availabilities = [];
        if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            $availabilities = $this->getIndividualOffers($filter);
        } else if ($filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            $availabilities = $this->getCharterOffers($filter);
        }
        return $availabilities;
	}
	
	public function getChartersAvailabilityDates()
	{
		$cities = $this->apiGetCities();
		$airportsMappedData = $this->getAirportsFromFile();
		$seats = $this->getSeatTypesByFlightIds();

		// getting RO airports
		$roAirports = [];
		foreach ($airportsMappedData as $airportData) {
			if ($airportData["Country"] == self::ROMANIA_ID) {
				$roAirports[$airportData['Id']] = $airportData['IATA'];
			}
		}

		// getting departures
		$flightDeparturesXml = $this->getXml('flightDepartures');

		$roDepartures = [];
		$returnDates = [];
		foreach ($flightDeparturesXml->item as $flightDeparturesItem) {
			$flightDeparture = ['Id' => (string)$flightDeparturesItem->id];
			
			$isActive = true;
			foreach ($flightDeparturesItem->prop as $flightDeparturesItemProp) {
				$flightDeparturesItemPropName = (string)$flightDeparturesItemProp['name'];
				$flightDeparturesItemPropValue = (string)$flightDeparturesItemProp;
				
				if ($flightDeparturesItemPropName == 'active' && $flightDeparturesItemPropValue == 'false') {
					$isActive = false;	
				}
				$flightDeparture[$flightDeparturesItemPropName] = $flightDeparturesItemPropValue;
			}

			if (!$isActive) {
				continue;
			}

			if (!isset($seats[$flightDeparture['Id']])) {
				continue;
			}
			$seat = $seats[$flightDeparture['Id']];
			if ($seat['leftNumber'] === 'no') {
				continue;
			}

			$flightDepartureData = $flightDeparture;

			$fromAirport = $airportsMappedData[$flightDepartureData['departureAirport']];
			$toAirport = $airportsMappedData[$flightDepartureData['arrivalAirport']];

			if (array_key_exists($flightDepartureData['departureAirport'], $roAirports)) {
				$roDepartures[date('Y-m-d', strtotime($flightDepartureData['departureDatetime']))][] = [
					'FlightData' => $flightDepartureData,
					'FromAirport' => $fromAirport,
					'ToAirport' => $toAirport,
					'id' => $flightDeparture['Id']
				];
			}

			if (!isset($returnDates[$flightDepartureData['departureAirport']])) {
				$returnDates[$flightDepartureData['departureAirport']] = [];
			}

			if (!isset($returnDates[$flightDepartureData['departureAirport']][$flightDepartureData['arrivalAirport']])) {
				$returnDates[$flightDepartureData['departureAirport']][$flightDepartureData['arrivalAirport']] = [];
			}

			$returnDates[$flightDepartureData['departureAirport']][$flightDepartureData['arrivalAirport']][$flightDepartureData['departureDatetime']] = $flightDepartureData;
		}

		$transports = [];
		$transportType = 'plane';

		$extraCityIds = [];
		foreach ($roDepartures as $date => $roDeparturesRows) {				
			foreach ($roDeparturesRows as $roDeparture) {

				$flightData = $roDeparture['FlightData'];

				$departureDate = (new DateTime($flightData['departureDatetime']))->setTime(0,0);
				$arrivalDate = (new DateTime($flightData['arrivalDatetime']))->setTime(0,0);

				if ($departureDate != $arrivalDate) {
					Log::warning($this->handle . ': departure date is different to arrival!');
				}

				$fromAirport = $roDeparture['FromAirport'];
				$toAirport = $roDeparture['ToAirport'];

				$backDates = $returnDates[$flightData['arrivalAirport']][$flightData['departureAirport']];

				$backDatePlus1Day = new DateTime($date . ' +1 day');
				$backDatePlus31Day = new DateTime($date . ' +31 day');

				$cityTo= $cities->get($toAirport['City']);
								
				if ($cityTo === null) {
					if (!isset($extraCityIds[$toAirport['City']])) {
						Log::warning($this->handle . ': availability dates: city ' . $toAirport['City'] . ' does not exist!');
						$extraCityIds[$toAirport['City']] = $toAirport['City'];
					}
					continue;
				}

				$transportId = $transportType . "~city|" . $fromAirport['City'] . "~city|" . $cityTo->Id;

				$existingTransport = $transports->get($transportId);

				$dateObj = new TransportDate();
				$dateObj->Date = date('Y-m-d', strtotime($date));

				$nights = new DateNightCollection();
				foreach ($backDates as $backDate => $flightDepartureData) {
					$backDateObj = new DateTime($backDate);

					if (($backDateObj > $backDatePlus1Day) && ($backDateObj < $backDatePlus31Day)) {			

						$days = $backDateObj->diff(new DateTime($date))->format('%a');
						$nightsObj = new DateNight;
						$nightsObj->Nights = $days;
						$nights->add($nightsObj);
					}
				}
				if (count($nights) === 0) {
					continue;
				}

				if ($existingTransport === null) {
					$transport = new AvailabilityDates();
					$transport->Id =  $transportId;
					$transportContent = new TransportContent();
					$transportContent->Active = true;
					$transport->Content = $transportContent;
					$transport->TransportType = $transportType;

					$from = new TransportCity();
					$from->City = $cities->get($fromAirport['City']);
					$transport->From = $from;

					$to = new TransportCity();
					$to->City = $cityTo;
					$transport->To = $to;

					$dates = new TransportDateCollection();
					$dateObj->Nights = $nights;
					$dates->put($dateObj->Date, $dateObj);
					$transport->Dates = $dates;
					$existingTransport = $transport;
				} else {
					// add date and nights to this transport
					$dates = $existingTransport->Dates;

					$dateObj->Nights = $nights;
					$dates->put($dateObj->Date, $dateObj);
					$existingTransport->Dates = $dates;
				}
				$transports->put($existingTransport->Id, $existingTransport);
			}					
		}
		return $transports;
	}

	public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
	{
		$ret = [];
		if ($filter->type === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
			$ret = $this->getChartersAvailabilityDates();
		}
		return $ret;
	}

	public function apiDoBooking(BookHotelFilter $filter): array
	{
		TeztourV2Validator::make()
			->validateBookHotelFilter($filter);
		
		// get offer
		$offer = $filter->Items->first();
		
		// get hotel
		$hotel = $offer->Hotel;

		$passengers = $post['args'][0]['Items'][0]['Passengers'];

		$bookingDataJson = $offer->Offer_bookingDataJson;

		$bookingData = json_decode($bookingDataJson, true);

		$services = [];
		$services[1] = 
			'<Residence>
				<serviceId>1</serviceId>
				<checkIn>' . date('d.m.Y', strtotime($offer->Room_CheckinAfter)) . '</checkIn>
				<checkOut>' . date('d.m.Y', strtotime($offer->Room_CheckinBefore)) . '</checkOut>
				<hotel>' . $hotel->InTourOperatorId . '</hotel>
				<hotelPansion>' . $offer->Board_Def_InTourOperatorId . '</hotelPansion>
				<hotelRoom>' . $offer->Room_Type_InTourOperatorId . '</hotelRoom>
				<regionId>' . $hotel->City_Code . '</regionId>
			</Residence>';
		if (isset($bookingData['flightDeparture'])) {
			$services[2] = 
				'<Ticket>
					<serviceId>2</serviceId>
					<flightDeparture>' . $bookingData['flightDeparture'] . '</flightDeparture>
					<seatType>' . $bookingData['seatTypeFlight'] . '</seatType>
				</Ticket>';
		
			$services[3] = 
				'<Ticket>
					<serviceId>3</serviceId>
					<flightDeparture>' . $bookingData['flightDepartureBack'] . '</flightDeparture>
					<seatType>' . $bookingData['seatTypeFlightBack'] . '</seatType>
				</Ticket>';
		
			$services[4] = 
				'<Transfer>
					<serviceId>4</serviceId>
					<date>' . date("d.m.Y", strtotime($offer->Room_CheckinAfter)) . '</date>
					<fromId>' . $bookingData['transferAirportId'] . '</fromId>
					<toId>' . $hotel->InTourOperatorId . '</toId>
					<type>' . $bookingData['transferTypeId'] . '</type>
					<flightDeparture>' . $bookingData['flightDeparture'] . '</flightDeparture>
				</Transfer>';
		
			$services[5] = 
				'<Transfer>
					<serviceId>5</serviceId>
					<date>' . date('d.m.Y', strtotime($offer->Room_CheckinBefore)) . '</date>
					<fromId>' . $hotel->InTourOperatorId . '</fromId>
					<toId>' . $bookingData['transferAirportId'] . '</toId>
					<type>' . $bookingData['backTransferTypeId'] . '</type>
					<flightDeparture>' . $bookingData['flightDepartureBack'] . '</flightDeparture>
				</Transfer>';
		}
		// get genders
		$gendersFromFile = $this->getGendersFromFile();

		$servicesXml = '';
		foreach ($services as $key => $service) {
			$servicesXml .= $service;
		}

		$checkInDT = new DateTime($offer->Room_CheckinAfter);

		$passengersXml = '';
		$serviceTouristXml = '';
		$count = 1;
		/** @var Passenger $passenger */
		foreach ($passengers as $passenger) {
			$passengerBirtDate = date('d.m.Y', strtotime($passenger['BirthDate']));
			if ($passenger['IsAdult']) {
				if ($passenger['Gender'] == 'male')
					$genderId = $gendersFromFile['MR.']['Id'];
				else if ($passenger['Gender'] == 'female')
					$genderId = $gendersFromFile['MRS.']['Id'];
			} else {
				$birthDate = $passenger['BirthDate'];
			
				$birthDT = new DateTime($birthDate);
				$age = $checkInDT->diff($birthDT)->y;
				
				if ($age <= 2) {
					$genderId = $gendersFromFile['INF.']['Id'];
				} else {
					$genderId = $gendersFromFile['CHD.']['Id'];
				}
			}
			$passengersXml .= '<Tourist>
					<touristId>' . $count . '</touristId>
					<surname>' . $passenger['Lastname'] . '</surname>
					<name>' . $passenger['Firstname'] . '</name>
					<gender>' . $genderId . '</gender>
					<birthday>' . $passengerBirtDate . '</birthday>
				</Tourist>';
			foreach ($services as $key => $service) {
				$serviceTouristXml .= '<ServiceTourist>
					<serviceId>' . $key . '</serviceId>
					<touristId>' . $count . '</touristId>
				</ServiceTourist>';
			}
			$count++;
		}
		$requestXml = '<?xml version="1.0"?>
			<order>
				<OrderType>
					<type>Touristic</type>
				</OrderType>
				<Country>
					<id>' . $hotel->Country_InTourOperatorId . '</id>
				</Country>
				' . $passengersXml .'
				' . $servicesXml . '
				' . $serviceTouristXml . '
			</order>';

		$session = $this->getSession();
		$dataXml = simplexml_load_string($session);
		$sessionId = (string) $dataXml->sessionId;

		$order = new Booking();
		if (empty($sessionId)) {
			return [$order, $session];
		}

		$calculateBookingStr = $this->calculateBooking($sessionId, $requestXml);
		$calculateBooking = simplexml_load_string($calculateBookingStr);

		if (!$calculateBooking->price) {
			return [$order, $calculateBookingStr];
		}

		// create booking
		$bookOrderResponseStr = $this->createBooking($sessionId, $requestXml);
		$bookOrderResponse = simplexml_load_string($bookOrderResponseStr);
		if (empty($bookOrderResponse->orderId)) {
			return [$order, $bookOrderResponseStr];
		}

		$order->Id = $bookOrderResponse->orderId;

		return [$order, $bookOrderResponseStr];
	}
	
	private function getSession(): ?string
	{
		$authorizationUrl = $this->bookingUrl. '/auth_data.jsp?j_login_request=1&j_login=' 
			. $this->bookingApiUsername . '&j_passwd=' . $this->bookingApiPassword;

		$client = HttpClient::create();
		$req = $client->request(HttpClient::METHOD_GET,$authorizationUrl);
		$data = $req->getBody();
		$this->showRequest(HttpClient::METHOD_GET,$authorizationUrl, [], $data, $req->getStatusCode());
		
		return $data;
	}
	
	private function calculateBooking(string $sessionId, string $requestXml): string
	{
		$calculateBookingUrl = $this->bookingUrl . '/order/calculate?aid=' . $sessionId;

		$options = [
			'body' => $requestXml,
			'headers' => [
				'Content-Type' => 'application/xml'
			]
		];

		$client = HttpClient::create();
		$req = $client->request(HttpClient::METHOD_POST, $calculateBookingUrl, $options);
		$content = $req->getBody();
		$this->showRequest(HttpClient::METHOD_POST, $calculateBookingUrl, $options, $content, 0);
		
		return $content;
	}
	
	public function createBooking(string $sessionId, string $requestXml)
	{		
		$bookingUrl = $this->bookingUrl . '/order/book?aid=' . $sessionId;

		$options = [
			'body' => $requestXml,
			'headers' => [
				'Content-Type' => 'application/xml'
			]
		];

		$client = HttpClient::create();
		$req = $client->request(HttpClient::METHOD_POST, $bookingUrl, $options);
		$content = $req->getBody();
		$this->showRequest(HttpClient::METHOD_POST, $bookingUrl, $options, $content, 0);
		
		return $content;
	}
	
	private function getXml(string $serviceNameParam): SimpleXMLElement
	{

		// check if we can we cache
		$contentXmlString = Utils::getFromCache($this->handle, $serviceNameParam);
		if ($contentXmlString === null) {

			// check if we can use cache
			$file = 'fileList';
			$xml = Utils::getFromCache($this->handle, $file);
			if ($xml === null) {

				$body = [
					'auth' => [
						'user' => ($this->username),
						'parola' => ($this->password)
					],
					'cerere' => [
						'serviciu' => 'fileList'
					]
				];
				$options['body'] = http_build_query($body);
				$options['headers'] = [
					'Content-Type' => 'application/x-www-form-urlencoded'
				];

				$client = HttpClient::create();
				$response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
				$xmlString = $response->getBody();

				$this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $xmlString, $response->getStatusCode());

				$filesOk = false;

				$filesXml = @simplexml_load_string($xmlString);
				
				if (isset($filesXml->DataSet->Body->files->service)) {
					$services = $filesXml->DataSet->Body->files->service;
					if (count($services) > 0) {
						$filesOk = true;
					}
				}
				if ($filesOk) {
					Utils::writeToCache($this->handle, $file, $xmlString);
				} else {
					Log::warning($this->handle . ': fileList error');
					// take from old cache
					$xmlString = Utils::getFromCache($this->handle, $file, true);
					$filesXml = simplexml_load_string($xmlString);
				}
			} else {
				$filesXml = simplexml_load_string($xml);
			}

			// creating a map with urls
			$services = [];
			foreach ($filesXml->DataSet->Body->files->service as $serviceFile) {
				$serviceName = (string)$serviceFile->name;
				$serviceUrl = (string)$serviceFile->url;
				$services[$serviceName] = $serviceUrl;	
			}

			$url = $services[$serviceNameParam];
			
			// check if file is ok
			$fileOk = false;
			$contentXmlString = file_get_contents($url);
			$this->showRequest(HttpClient::METHOD_GET, $url, [], $contentXmlString, 0);

			$contentXml = @simplexml_load_string($contentXmlString);
			if (isset($contentXml->item)) {
				if (count($contentXml->item) > 0) {
					$fileOk = true;
				}
			}
			if ($fileOk) {
				Utils::writeToCache($this->handle, $serviceNameParam, $contentXmlString);
			} else {
				Log::warning($this->handle . ': service - '. $serviceNameParam .' error');
				// take from old cache
				$contentXmlString = Utils::getFromCache($this->handle, $serviceNameParam, true);
			}
		} else {
			$contentXml = simplexml_load_string($contentXmlString);
		}

		return $contentXml;
	}

	private function getFlightsById()
	{//todo
		// if (isset($this->cachedData['loadedFlightsById']))
		// 	return $this->cachedData['loadedFlightsById'];	

		$flightsData = $this->getXml('flights');

		$flightsDataItems = $flightsData->item;

		$this->cachedData['loadedFlightsById'] = [];

		foreach ($flightsDataItems ?: [] as $flightItm) {

			if (((string) $flightItm->attributes()->type === "Flight") && (string) $flightItm->id) {
				$flightData = [];

				//dump($flightItm);
				foreach ($flightItm->prop as $fp) {
					if (!($propName = (string) $fp->attributes()->name)) {
						continue;
					}
					//dump($propName);
					$flightData[$propName] = (string) $fp;
				}
				$this->cachedData['loadedFlightsById'][(string) $flightItm->id] = $flightData;
			}
		}
		//dump($this->cachedData['loadedFlightsById']);
		return $this->cachedData['loadedFlightsById'];
	}
	
	private function getAirportsFromFile(): array
	{
		$airportsXml = $this->getXml('airports');
		
		$airports = [];
		foreach ($airportsXml->item as $airportItem) {

			$airport = [
				'Id' => (string)$airportItem->id,
				'Name' => (string)$airportItem->name
			];
			
			foreach ($airportItem->prop as $airportProp) {
				$airportPropName = (string)$airportProp['name'];
				$airportPropValue = (string)$airportProp;
				$airport[$airportPropName] = $airportPropValue;
			}
			
			$airports[$airport['Id']] = $airport;
		}
		return $airports;
	}

	private function getAiportsByRegion(): array
	{
		$airportsMappedData = $this->getAirportsFromFile();
		$airportsByRegion = [];
		foreach ($airportsMappedData as $airport) {
			$airportsByRegion[$airport["Region"]][$airport["Id"]] = $airport;
		}
		return $airportsByRegion;
	}

	public function getFlightDepartures()
	{
		$flightDeparturesXml = $this->getXml('flightDepartures');
		$flightDepartures = [];

		foreach ($flightDeparturesXml->item as $flightDeparturesItem) {
			$flightDeparture = [
				'Id' => (string)$flightDeparturesItem->id
			];
			
			if (!$flightDeparturesItem->prop)
				continue;
			
			$isActive = true;
			
			foreach ($flightDeparturesItem->prop as $flightDeparturesItemProp) {
				$flightDeparturesItemPropName = (string)$flightDeparturesItemProp['name'];
				$flightDeparturesItemPropValue = (string)$flightDeparturesItemProp;
				
				if (($flightDeparturesItemPropName == 'active') && ($flightDeparturesItemPropValue == 'false')) {
					$isActive = false;	
				}
				$flightDeparture[$flightDeparturesItemPropName] = $flightDeparturesItemPropValue;
			}
			$flightDeparture['IsActive'] = $isActive;
			$flightDepartures[$flightDeparture['Id']] = $flightDeparture;
		}
		
		// return flight departures
		return $flightDepartures;
	}

	private function getSeatTypesFromFile(): array
	{
		$seatTypesXml = $this->getXml('seatSets');
		
		$seatTypes = [];
		foreach ($seatTypesXml->item as $seatTypeItem) {
			$seatType = [
				'Id' => (string)$seatTypeItem->id
			];
			
			if (!$seatTypeItem->prop)
				continue;
			
			foreach ($seatTypeItem->prop as $seatTypeItemProp) {
				$seatTypeItemPropName = (string)$seatTypeItemProp['name'];
				$seatTypeItemPropValue = (string)$seatTypeItemProp;
				
				$seatType[$seatTypeItemPropName] = $seatTypeItemPropValue;
			}
			
			$seatTypes[$seatType['Id']] = $seatType;
		}
		
		return $seatTypes;
	}
	
	private function getGendersFromFile(): array
	{
		$gendersXml = $this->getXml('genders');

		$genders = [];
		foreach ($gendersXml->item as $genderItem)
		{
			$gender = [
				'Id' => (string)$genderItem->id,
				'Name' => (string)$genderItem->name
			];	
			$genders[$gender['Name']] = $gender;
		}

		return $genders;
	}

	public function isAviaFlightAvailable(array $aviaRefFlight, int $passengers): bool
	{
		return (
			
			$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberF'], $passengers) || 
			$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberB'], $passengers) ||
			$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberC'], $passengers) ||
			$this->isAviaFlightAvailable_checkField($aviaRefFlight['freeSeatNumberE'], $passengers)
		);
	}

	public function isAviaFlightAvailable_checkField($freeSeatNumber, int $passengers): bool
	{
		return ((is_numeric($freeSeatNumber) && ((int)$freeSeatNumber) >= $passengers) || (in_array($freeSeatNumber, $this->freeSeatsStatuses)));
	}
	
	public function getIndividualOffers(AvailabilityFilter $filter)
	{
		TeztourV2Validator::make()
			->validateUsernameAndPassword($this->post)
			->validateIndividualOffersFilter($filter);
		
		$adults = (int) $filter->rooms->get(0)->adults;
		$children = (int) $filter->rooms->get(0)->children;

		$checkInParam = (new DateTime($filter->checkIn))->format('d.m.Y');

		$minAcceptedHotelCategories = $this->getMinAcceptableCategories($filter);
		$minAcceptedHotelCategory = $minAcceptedHotelCategories[array_keys($minAcceptedHotelCategories)[0]];

		$allOffersXml = [];
		$i = 0;
		$minPrice = 0;
		while (true) {
			$i++;
			$params = [
				'xml' => 'true',
				'locale' => 'ro',
				'tariffsearch' => 1,
				'currency' => self::EUR_ID,
				'cityId' => self::BUCHAREST_ID,
				'countryId' => (int) $filter->countryId, // id of destination country
				'after'	=> $checkInParam,
				'before' => $checkInParam,
				'nightsMin'	=> (int) $filter->days,
				'nightsMax'	=> (int) $filter->days,
				'priceMin' => $minPrice,
				'priceMax' => 999999,
				'tourType' => 3, // for individual
				'accommodationId' => (int) $this->getAccommodationId($adults, $children),
				'hotelClassId' => (int) $minAcceptedHotelCategory,
				'hotelClassBetter' => true, // min accepted hotel category and better
				'hotelInStop' => true, // also hotels with unavailable rooms,
			];
			$minRAndBs = $this->getMinRAndBs($filter);

			if (!empty($minRAndBs)) {
				$params["rAndBId"] = $minRAndBs;
			}

			if (!empty($filter->regionId)) {
				$params['tourId'] = (int) $filter->regionId;
			}

			for ($i = 0; $i < $post['args'][0]['rooms'][0]['children']; $i++) {
				$childPos = $i + 1;
				$childAge = $post['args'][0]['rooms'][0]['childrenAges']->get($i);
				$params["birthday" . $childPos] = date("d.m.Y", strtotime("- " . $childAge . " years"));
			}

			if ($filter->hotelId) {
				$params["hotelId"] = (int) $filter->hotelId;
			}

			$url = 'http://www.travelio.ro';
			if ($this->handle === self::TEST_HANDLE ) {
				$url = $this->apiUrl;
			}

			$url =  $url . '/proxy-tez/?' . q_url_encode($params);
			$client = HttpClient::create();
			
			$request = $client->request(HttpClient::METHOD_GET, $url);
			
			$this->showRequest(HttpClient::METHOD_GET, $url, [], $request->getBody(), $request->getStatusCode());
			$offersResp = $request->getBody();

			// get the next category if error
			$offersXml = simplexml_load_string($offersResp);
			if (!empty($offersXml->message) && $offersXml->message === '[Звездность отеля 269506: Special Cat. отсутствует в ценах запрошенного направления]') {
				$minAcceptedHotelCategory = $minAcceptedHotelCategories[array_keys($minAcceptedHotelCategories)[1]];
				continue;
			}

			$offersXml = $offersXml->data->item;

			if (empty($offersXml) || count($offersXml) === 0) {
				break;
			}

			// get the last price
			$lastOffer = $offersXml[count($offersXml) - 1];

			if (empty($lastOffer->price->total)) {
				break;
			}
			
			if ($i > 10) { // safe break;
				break;
			}

			$allOffersXml[] = $offersXml;
			if ($minPrice === (float) $lastOffer->price->total) {
				break;
			}

			$minPrice = (float) $lastOffer->price->total;
		}

		$hotels = [];
		
		foreach ($allOffersXml as $offersXml) {
			foreach ($offersXml as $offerXmlItem) {

				$hotelId = (string) $offerXmlItem->hotel->id;
				$hotel = $hotels->get($hotelId);
				if ($hotel === null) {
					$hotel = new Availability();
					$hotel->Id = $hotelId;

					if ($filter->showHotelName) {
						$hotel->Name = (string) $offerXmlItem->hotel->name;
					}
				}

				$offer = new Offer();

				$roomId = (string) $offerXmlItem->hotelRoomType->id;
				$roomTypeName = (string) $offerXmlItem->hotelRoomType->name;

				$mealId = (string) $offerXmlItem->pansion->id;
				$meal = (string) $offerXmlItem->pansion->name;

				$offerCheckInDT = new DateTime((string) $offerXmlItem->checkIn);
				$offerCheckIn = $offerCheckInDT->format('Y-m-d');

				$checkOut = date("Y-m-d", strtotime("+ {$offerXmlItem->nightCount} days", strtotime($offerCheckIn)));
				$price = (float) $offerXmlItem->price->total;

				$offerCode = $hotel->Id . '~' . $roomId . '~' . $mealId . '~' . $offerCheckIn . '~' . $checkOut . '~' . $price . '~' 
					. $filter->rooms->first()->adults . ($post['args'][0]['rooms'][0]['childrenAges'] ? '~' 
					. implode('|', $post['args'][0]['rooms'][0]['childrenAges']->toArray()) : '');

				$offer->Code = $offerCode;

				if ((string) $offerXmlItem->price->insurance) {
					Log::warning($this->handle . ': insurance price!');
					continue;
				}

				if ((string) $offerXmlItem->price->other) {
					Log::warning($this->handle . ': other price!');
					continue;
				}

				$currency = new Currency();
				$currency->Code = (string) $offerXmlItem->price->currency;
				$offer->Currency = $currency;
				
				$offer->Net = $price;
				$offer->Gross = $offer->Net;
				$offer->InitialPrice = $offer->Gross;
				
				$offer->Availability = ((string) $offerXmlItem->existsRoom == 'true') ? Offer::AVAILABILITY_YES : Offer::AVAILABILITY_NO;

				//$offer->Days = $filter->days;
				
				// room
				$roomType = new RoomMerchType();
				$roomType->Id = $roomId;
				$roomType->Title = $roomTypeName;
				
				$roomMerch = new RoomMerch();
				$roomMerch->Title = $roomTypeName;
				$roomMerch->Type = $roomType;
				$roomMerch->Code = $roomId;
				$roomMerch->Id = $roomId;
				$roomMerch->Name = $roomTypeName;
				
				$roomItm = new Room();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = $roomId;
				$roomItm->CheckinAfter = $offerCheckIn;
				$roomItm->CheckinBefore = $checkOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Availability = $offer->Availability;

				// todo: testat cu early booking
				if ((string) $offerXmlItem->icons->earlyBooking->value == 'true') {
					$roomItm->InfoTitle = 'Early booking';
				}

				$offer->Rooms = new RoomCollection([$roomItm]);

				// board
				$boardType = new MealMerchType();
				$boardType->Id = $mealId;
				$boardType->Title = $meal;

				$boardMerch = new MealMerch();
				$boardMerch->Id = $mealId;
				$boardMerch->Title = $meal;
				$boardMerch->Type = $boardType;

				$boardItm = new MealItem();
				$boardItm->Merch = $boardMerch;
				$boardItm->Currency = $offer->Currency;
				
				$offer->MealItem = $boardItm;

				// departure transport item
				$departureTransportMerch = new TransportMerch();
				$departureTransportMerch->Title = "CheckIn: ". date("d.m.Y", strtotime($offerCheckIn));

				$departureTransportItm = new DepartureTransportItem();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Currency = $offer->Currency;
				$departureTransportItm->DepartureDate = $filter->checkIn;
				$departureTransportItm->ArrivalDate = $filter->checkIn;

				// return transport item
				$returnTransportMerch = new TransportMerch();
				$returnTransportMerch->Title = "CheckOut: ". date("d.m.Y", strtotime($checkOut));

				$returnTransportItm = new ReturnTransportItem();
				$returnTransportItm->Merch = $returnTransportMerch;
				$returnTransportItm->Currency = $offer->Currency;
				$returnTransportItm->DepartureDate = $checkOut;
				$returnTransportItm->ArrivalDate = $checkOut;
				$departureTransportItm->Return = $returnTransportItm;

				// add items to offer
				$offer->Item = $roomItm;
				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;

				$offers = $hotel->Offers;
				if ($offers === null) {
					$offers = new OfferCollection();
				}
				$offers->put($offer->Code, $offer);
				$hotel->Offers = $offers;
				$hotels->put($hotel->Id, $hotel);
			}
		}

		return $hotels;
	}

	// 1. Din lista de aeroporturi se selecteaza aeroporturile din orasul de plecare.
	// 2. Din lista de aeroporturi se selecteaza aeroporturile din regiunea de sosire din oferta.
	// 3. Se ia data de plecare dupa data de checkin.
	// 4. Se ia lista de plecari din fisier.
	// 5. Se parcurg listele de aeroporturi de plecare si aeroporturile de sosire si lista de plecari pentru a se face o lista cu potriviri.
	// 6. Pentru fiecare potrivire se apleseaza serviciul avia-reference folosind aeroportul de plecare, aerop de sosire si data de plecare.
	// 7. Din avia-refence se vor returna 1-2 zboruri.
	// 8. Se ia lista de zboruri.
	// 9. Se selecteaza zborul dupa id-ul de zbor din parametrii.
	// 10. Se ia numarul de zbor din zborul selectat.
	// 11. Se selecteaza zborul din avia dupa numarul de zbor.
	// 12. Se selecteaza ultimul zbor din lista de avia.

	public function getCharterOffers(AvailabilityFilter $filter)
	{
		TeztourV2Validator::make()
			->validateUsernameAndPassword($this->post)
			->validateCharterOffersFilter($filter);

		$adults = (int) $filter->rooms->get(0)->adults;
		$children = (int) $filter->rooms->get(0)->children;
		$checkInParam = (new DateTime($filter->checkIn))->format('d.m.Y');

		$accommodationId = $this->getAccommodationId($adults, $children);
		$minAcceptedHotelCategories = $this->getMinAcceptableCategories($filter);
		$minAcceptedHotelCategory = $minAcceptedHotelCategories[array_keys($minAcceptedHotelCategories)[0]];

		// if the region is in the array, do parallel requests for all the regions in that array
		$regionsQuery = [$filter->regionId];

		if (!empty($filter->regionId)) {
			foreach (self::$regionGroups as $regionGroup) {
				if (in_array($filter->regionId, $regionGroup)) {
					$regionsQuery = $regionGroup;
					break;
				}
			}
		}

		$client = HttpClient::create();
		$requests = [];
		foreach ($regionsQuery as $regionId) {

			$allOffersXml = [];
			$i = 0;

			//while (true) {
				$params = [
					'xml' => 'true',
					'locale' => 'ro',
					'tariffsearch' => 1,
					'cityId' => (int) $filter->departureCity,
					'countryId' => (int) $filter->countryId,
					'after' => $checkInParam,
					'before' => $checkInParam,
					'nightsMin' => (int) $filter->days,
					'nightsMax' => (int) $filter->days,
					'priceMin' => 0,
					'priceMax' => 999999,
					'tourType' => 1, // for charters
					'currency' => self::EUR_ID,
					'accommodationId' => (int) $accommodationId,
					'hotelClassId' => (int) $minAcceptedHotelCategory,
					'hotelClassBetter' => true, // min accepted hotel category and better
					'hotelInStop' => true, // also hotels with unavailable rooms	
				];

				for ($i = 0; $i < $post['args'][0]['rooms'][0]['children']; $i++) {
					$childPos = $i + 1;
					$childAge = $post['args'][0]['rooms'][0]['childrenAges']->get($i);
					$params["birthday" . $childPos] = date("d.m.Y", strtotime("- " . $childAge . " years"));
				}

				if (!empty($regionId)) {
					$params["tourId"] = $regionId;
				}

				if ($filter->hotelId) {
					$params["hotelId"] = $filter->hotelId;
				}
				
				$minRAndBs = $this->getMinRAndBs($filter);

				if (!empty($minRAndBs)) {
					$params["rAndBId"] = $minRAndBs;
				}
				
				$offerXmlItems = [];

				$url = 'http://www.travelio.ro';
				if ($this->handle === self::TEST_HANDLE ) {
					$url = $this->apiUrl;
				}

				$url =  $url . '/proxy-tez/?' . q_url_encode($params);

				$request = $client->request(HttpClient::METHOD_GET, $url);
				$requests[] = [$request, $url, 0];
				
			//}
		}

		$limit = 0;
		while (true) {
			$limit++;
			if ($limit > 50) {
				break;
			}
			$client = HttpClient::create();
			$currentRequests = [];

			//$break = false;
			foreach ($requests as $requestObj) {
				$request = $requestObj[0];
				$url = $requestObj[1];
				$minPrice = $requestObj[2];

				$this->showRequest(HttpClient::METHOD_GET, $url, [], $request->getBody(), $request->getStatusCode());
				$offersResp = $request->getBody();

				$offersXml = simplexml_load_string($offersResp);

				// prepare url
				$params = [
					'xml' => 'true',
					'locale' => 'ro',
					'tariffsearch' => 1,
					'cityId' => (int) $filter->departureCity,
					'countryId' => (int) $filter->countryId,
					'after' => $checkInParam,
					'before' => $checkInParam,
					'nightsMin' => (int) $filter->days,
					'nightsMax' => (int) $filter->days,
					'priceMin' => $minPrice,
					'priceMax' => 999999,
					'tourType' => 1, // for charters
					'currency' => self::EUR_ID,
					'accommodationId' => (int) $accommodationId,
					'hotelClassId' => (int) $minAcceptedHotelCategory,
					'hotelClassBetter' => true, // min accepted hotel category and better
					'hotelInStop' => true, // also hotels with unavailable rooms	
				];

				for ($i = 0; $i < $post['args'][0]['rooms'][0]['children']; $i++) {
					$childPos = $i + 1;
					$childAge = $post['args'][0]['rooms'][0]['childrenAges']->get($i);
					$params["birthday" . $childPos] = date("d.m.Y", strtotime("- " . $childAge . " years"));
				}

				if (!empty($regionId)) {
					$params["tourId"] = $regionId;
				}

				if ($filter->hotelId) {
					$params["hotelId"] = $filter->hotelId;
				}
				
				$minRAndBs = $this->getMinRAndBs($filter);

				if (!empty($minRAndBs)) {
					$params["rAndBId"] = $minRAndBs;
				}
				
				$offerXmlItems = [];

				$url = 'http://www.travelio.ro';
				if ($this->handle === self::TEST_HANDLE ) {
					$url = $this->apiUrl;
				}

				$url =  $url . '/proxy-tez/?' . q_url_encode($params);

				// get the next category if error
				if (!empty($offersXml->message) && (string) $offersXml->message === '[Звездность отеля 269506: Special Cat. отсутствует в ценах запрошенного направления]') {
					$minAcceptedHotelCategory = $minAcceptedHotelCategories[array_keys($minAcceptedHotelCategories)[1]];
					$request = $client->request(HttpClient::METHOD_GET, $url);
					$currentRequests[] = [$request, $url, 0];
					continue;
				}

				$offersXml = $offersXml->data->item;

				if (empty($offersXml) || count($offersXml) === 0) {
					continue; // do not add to requests and results anymore
				}

				// get the last price
				$lastOffer = $offersXml[count($offersXml) - 1];

				if (empty($lastOffer->price->total)) {
					continue; // do not add to requests and results anymore
				}

				if ($minPrice === (float) $lastOffer->price->total) {
					$allOffersXml[] = $offersXml;
					continue; // do not add to requests anymore
				}

				$minPrice = (float) $lastOffer->price->total;
				$allOffersXml[] = $offersXml;
				$request = $client->request(HttpClient::METHOD_GET, $url);
				$currentRequests[] = [$request, $url, $minPrice];
			}

			$requests = $currentRequests;
			// if no requests left, break
			if (count($currentRequests) === 0) {
				break;
			}
		}

		$hotels = [];

		$airportsMappedData = $this->getAirportsFromFile();
		$airportsByRegion = $this->getAiportsByRegion();
		$flightDeparturesFromFile = $this->getFlightDepartures();
		$seatTypesByFlightIds = $this->getSeatTypesByFlightIds();


		//dd($airportsMappedData);

		// foreach ($seatTypesByFlightIds as $seat) {
		// 	if ($seat['leftNumber'] == 2) {
		// 		$departure = $flightDeparturesFromFile[$seat['FlightDeparture']];
				
		// 		$airport = $airportsMappedData[$departure['departureAirport']];
		// 		if ($airport['City'] == 9001185) {
		// 			dd($departure);
		// 		}
		// 	}
		// }

		// foreach ($seatTypesByFlightIds as $seats) {
		// 	foreach ($seats as $seat) {
		// 		if ($seat['SeatTypeName'] == 'Premium-Economy') {
		// 			$departure = $flightDeparturesFromFile[$seat['FlightDeparture']];
					
		// 			$airport = $airportsMappedData[$departure['departureAirport']];

		// 			if ($airport['City'] == 523) {
		// 				//dd($departure, $seat);
		// 			}
		// 		}
		// 	}
		// }
		//dd($allOffersXml);
//dd($allOffersXml);
		$cities= $this->apiGetCities();
		$possibleDepartureAirports = $airportsByRegion[$cities->get($filter->departureCity)->County->Id]; 

		$flightsDepartureChecked = [];
		$flightsReturnChecked = [];

		foreach ($allOffersXml as $offerXmlItems) {
			foreach ($offerXmlItems as $offerXmlItem) {

				$hotelCode = (string) $offerXmlItem->hotel->id;

				$hotel = $hotels->get($hotelCode);
				if ($hotel === null) {
					$hotel = new Availability();
					$hotel->Id = $hotelCode;
					$hotel->Name = (string) $offerXmlItem->hotel->name;
				}

				$offer = new Offer();

				$roomId = (string) $offerXmlItem->hotelRoomType->id;
				
				$roomTypeName = (string) $offerXmlItem->hotelRoomType->name;

				$mealId = (string) $offerXmlItem->pansion->id ?? 'no_meal';
				$mealName = (string) $offerXmlItem->pansion->name ?? 'Fara masa';

				$offerCheckInDT = new DateTime((string) $offerXmlItem->checkIn);
				$offerCheckIn = $offerCheckInDT->format('Y-m-d');
				$offerDays = $offerXmlItem->nightCount;

				$offerCheckOut = date("Y-m-d", strtotime("+ {$offerXmlItem->nightCount} days", strtotime($offerCheckIn)));
				$price = (float) $offerXmlItem->price->total;

				$offerCode = $hotel->Id . '~' . $roomId . '~' . $mealId . '~' . $offerCheckIn . '~' . $offerDays . '~' . $price . '~' 
					. $filter->rooms->first()->adults . (!empty($post['args'][0]['rooms'][0]['childrenAges']->toArray()) ? '~' 
					. implode('|', $post['args'][0]['rooms'][0]['childrenAges']->toArray()) : '');

				$offer->Code = $offerCode;


				if ((string) $offerXmlItem->price->insurance) {
					Log::warning($this->handle . ': insurance price!');
					continue;
				}

				if ((string) $offerXmlItem->price->other) {
					Log::warning($this->handle . ': other price!');
					continue;
				}

				$currency = new Currency();
				$currency->Code = (string) $offerXmlItem->price->currency;
				$offer->Currency = $currency;
				
				$offer->Net = $price;
				$offer->Gross = $offer->Net;
				$offer->InitialPrice = $offer->Gross;
				
				$offer->Availability = ((string) $offerXmlItem->existsRoom == 'true') ? Offer::AVAILABILITY_YES : Offer::AVAILABILITY_NO;
				
				// room
				$roomType = new RoomMerchType();
				$roomType->Id = $roomId;
				$roomType->Title = $roomTypeName;
				
				$roomMerch = new RoomMerch();
				$roomMerch->Title = $roomTypeName;
				$roomMerch->Type = $roomType;
				$roomMerch->Code = $roomId;
				$roomMerch->Id = $roomId;
				$roomMerch->Name = $roomTypeName;
				
				$roomItm = new Room();
				$roomItm->Merch = $roomMerch;
				$roomItm->Id = $roomId;
				$roomItm->CheckinAfter = $offerCheckIn;
				$roomItm->CheckinBefore = $offerCheckOut;
				$roomItm->Currency = $offer->Currency;
				$roomItm->Availability = $offer->Availability;

				// todo: testat cu early booking
				if ((string) $offerXmlItem->icons->earlyBooking->value == 'true') {
					$roomItm->InfoTitle = 'Early booking';
				}

				$offer->Rooms = new RoomCollection([$roomItm]);

				// board
				$boardType = new MealMerchType();
				$boardType->Id = $mealId;
				$boardType->Title = $mealName;

				$boardMerch = new MealMerch();
				$boardMerch->Id = $mealId;
				$boardMerch->Title = $mealName;
				$boardMerch->Type = $boardType;

				$boardItm = new MealItem();
				$boardItm->Merch = $boardMerch;
				$boardItm->Currency = $offer->Currency;
				
				$offer->MealItem = $boardItm;
				
				$offer->Item = $roomItm;

				$possibleArrivalAirports = $airportsByRegion[(string) $offerXmlItem->region->resortArrivalRegionId];

				// get flights
				// get arrival and departure airports ids
				// check in flights file
				// after the flight is found, check with the file
				
				$flightDepartureDate = $offerCheckIn;
				$flightReturnDate = $offerCheckOut;

				$departureKey = $flightDepartureDate . '-';
				$returnKey = $flightReturnDate . '-';

				$returnKeyEnd = '';
				foreach ($possibleDepartureAirports as $da) {
					$departureKey .= $da['Id'] . '-';
					$returnKeyEnd .= $da['Id'] . '-';
				}
				foreach ($possibleArrivalAirports as $aa) {
					$departureKey .= $aa['Id'] . '-';
					$returnKey .= $aa['Id'] . '-';
				}
				$returnKey .= $returnKeyEnd;

				$passengers = $adults + $children;

				$seatsFromOfferTo = $offerXmlItem->seatSets->seatSetPair->to->econom;
				$seatsFromOfferFrom = $offerXmlItem->seatSets->seatSetPair->from->econom;

				if (
					(int)$seatsFromOfferTo->charge != 0 || 
					(int)$seatsFromOfferTo->childCharge != 0 ||
					(int)$seatsFromOfferTo->infantCharge != 0 ||
					(int)$seatsFromOfferFrom->charge != 0 ||
					(int)$seatsFromOfferFrom->childCharge != 0 ||
					(int)$seatsFromOfferFrom->infantCharge != 0

				) {
					Log::warning($this->handle . ': price for economy? '. json_encode($this->post));
					return $hotels;
				}

				$seatsTo = (string) $seatsFromOfferTo->seatSet;
				$seatsFrom = (string) $seatsFromOfferFrom->seatSet;

				if (is_numeric($seatsFrom) && ((int)$seatsFrom) < $passengers) {
					return $hotels;
				}

				if (is_numeric($seatsTo) && ((int)$seatsTo) < $passengers) {
					return $hotels;
				}

				if (!is_numeric($seatsFrom) && $seatsFrom != 'Available' && $seatsFrom != 'Few') {

					Log::warning($this->handle . ': flight text? '. json_encode($this->post));
					return $hotels;
				}
				if (!is_numeric($seatsTo) && $seatsTo != 'Available' && $seatsTo != 'Few') {
					Log::warning($this->handle . ': flight text? '. json_encode($this->post));
					return $hotels;
				}

				if (isset($flightsDepartureChecked[$departureKey])) {
					$flightDeparture = $flightsDepartureChecked[$departureKey];
				} else {
					$flightDeparture = $this->getFlight($possibleDepartureAirports, $possibleArrivalAirports, $flightDepartureDate, $flightDeparturesFromFile, $seatTypesByFlightIds, $passengers);

					$flightsDepartureChecked[$departureKey] = $flightDeparture;
				}
				if (isset($flightsReturnChecked[$returnKey])) {
					$flightReturn = $flightsReturnChecked[$returnKey];
				} else {
					$flightReturn = $this->getFlight($possibleArrivalAirports, $possibleDepartureAirports, $flightReturnDate, $flightDeparturesFromFile, $seatTypesByFlightIds, $passengers);
					$flightsReturnChecked[$returnKey] = $flightReturn;
				}

				if ($flightDeparture === null || $flightReturn === null) {
					Log::warning($this->handle . ': no flight for ' . $url);
					return $hotels;
				}

				$bookingData = [
					'flightDeparture' => $flightDeparture['Id'],
					'seatTypeFlight' => $flightDeparture['seatType']['SeatTypeId'],
					'flightDepartureBack' => $flightReturn['Id'],
					'seatTypeFlightBack' => $flightReturn['seatType']['SeatTypeId'],
					'transferAirportId' => $flightDeparture['arrivalAirport'],
					'transferTypeId' => (string) $offerXmlItem->transferTypes->firstTransferTypeId,
					'backTransferTypeId' => (string) $offerXmlItem->transferTypes->lastTransferTypeId
				];

				$offer->bookingDataJson = json_encode($bookingData);

				// transports
				// departure transport item
				$departureTransportMerch = new TransportMerch();
				$departureTransportMerch->Title = "Dus: ". date("d.m.Y", strtotime($offerCheckIn));
				$departureTransportMerch->Category = new TransportMerchCategory();
				$departureTransportMerch->Category->Code = 'other-outbound';
				$departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
				$departureTransportMerch->DepartureTime = date('Y-m-d', strtotime($flightDeparture['departureDatetime'])) . ' ' . date('H:i', strtotime($flightDeparture['departureDatetime']));
				$departureTransportMerch->ArrivalTime = date('Y-m-d', strtotime($flightDeparture['arrivalDatetime'])) . ' ' . date('H:i', strtotime($flightDeparture['arrivalDatetime']));
				
				$departureTransportMerch->DepartureAirport = $airportsMappedData[$flightDeparture['departureAirport']]["IATA"];
				$departureTransportMerch->ReturnAirport = $airportsMappedData[$flightDeparture['arrivalAirport']]["IATA"];

				$departureTransportMerch->From = new TransportMerchLocation();
				$departureTransportMerch->From->City = $cities->get($airportsMappedData[$flightDeparture['departureAirport']]["City"]);

				$departureTransportMerch->To = new TransportMerchLocation();
				$departureTransportMerch->To->City = $cities->get($airportsMappedData[$flightDeparture['arrivalAirport']]["City"]);

				$departureTransportItm = new DepartureTransportItem();
				$departureTransportItm->Merch = $departureTransportMerch;
				$departureTransportItm->Currency = $offer->Currency;
				$departureTransportItm->DepartureDate = $offerCheckIn;
				$departureTransportItm->ArrivalDate = $offerCheckIn;

				// return transport item
				$returnTransportMerch = new TransportMerch();
				$returnTransportMerch->Title = "Retur: ". date("d.m.Y", strtotime($offerCheckOut));
				$returnTransportMerch->Category = new TransportMerchCategory();
				$returnTransportMerch->Category->Code = 'other-inbound';
				$returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
				$returnTransportMerch->DepartureTime = date('Y-m-d', strtotime($flightReturn['departureDatetime'])) . ' ' . date('H:i', strtotime($flightReturn['departureDatetime']));
				$returnTransportMerch->ArrivalTime = date('Y-m-d', strtotime($flightReturn['arrivalDatetime'])) . ' ' . date('H:i', strtotime($flightReturn['arrivalDatetime']));

				$returnTransportMerch->DepartureAirport = $airportsMappedData[$flightReturn['departureAirport']]["IATA"];
				$returnTransportMerch->ReturnAirport = $airportsMappedData[$flightReturn['arrivalAirport']]["IATA"];

				$returnTransportMerch->From = new TransportMerchLocation();
				$returnTransportMerch->From->City = $cities->get($airportsMappedData[$flightReturn['departureAirport']]["City"]);

				$location = new TransportMerchLocation();
				$location->City = $cities->get($airportsMappedData[$flightReturn['arrivalAirport']]["City"]);
				$returnTransportMerch->To = $location;

				$returnTransportItm = new ReturnTransportItem();
				$returnTransportItm->Merch = $returnTransportMerch;
				$returnTransportItm->Currency = $offer->Currency;
				$returnTransportItm->DepartureDate = $offerCheckOut;
				$returnTransportItm->ArrivalDate = $offerCheckOut;
				$departureTransportItm->Return = $returnTransportItm;

				$offer->DepartureTransportItem = $departureTransportItm;
				$offer->ReturnTransportItem = $returnTransportItm;

				if ((string)$offerXmlItem->price->priceTypes->boolean[2] === 'true') {
					$offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);
				}

				if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE) {
					$offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
				}
            	
				$offers = $hotel->Offers;
				if ($offers === null) {
					$offers = new OfferCollection();
				}
				$offers->put($offer->Code, $offer);
				$hotel->Offers = $offers;
				$hotels->put($hotel->Id, $hotel);
			}
		}

		return $hotels;
	}

	private function getSeatTypesByFlightIds(): array
	{
		$seatTypesFromFile = $this->getSeatTypesFromFile();

		$seatTypesByFlightIDs = [];
		foreach ($seatTypesFromFile as $stffData) {
			if ($stffData['SeatTypeName'] === 'Economy') {
				if (isset($seatTypesByFlightIDs[$stffData["FlightDeparture"]])) {
					throw new Exception('invalid seat ' . json_encode($this->post));
				}
				$seatTypesByFlightIDs[$stffData["FlightDeparture"]] = $stffData;
			}
		}
		return $seatTypesByFlightIDs;
	}
	
	private function getMinRAndBs(AvailabilityFilter $filter): ?int
	{
		$countryId = $filter->countryId;
		$cityId = $filter->departureCity ?: self::BUCHAREST_ID;

		$file = 'min-randbs-' . $countryId . '-' . $cityId;

		$minRAndBs = Utils::getFromCache($this->handle, $file);

		if ($minRAndBs === null) {
			$params = [
				'locale' => 'ro',
				'xml' => 'true',
				'countryId' => $countryId,
				'cityId' => $cityId
			];

			$url = 'https://xml.tez-tour.com';
			if ($this->handle === self::TEST_HANDLE) {
				$url = $this->apiUrl;
			}

			$url = $url . '/tariffsearch/randbs?' . http_build_query($params);

			$client = HttpClient::create();
			$resp = $client->request(HttpClient::METHOD_GET, $url, []);
			$this->showRequest(HttpClient::METHOD_POST, $url, [], $resp->getBody(), $resp->getStatusCode());
			$data = $resp->getBody();
			
			$randbsXml = @simplexml_load_string($data);

			if (isset($randbsXml->rAndBs->rAndB)) {
				$acceptableRAndBs = $randbsXml->rAndBs->rAndB;
				$min = null;
				$minRAndBs = null;
				foreach ($acceptableRAndBs as $randbs) {
					$weight = (int) $randbs->weight;
					$classId = (int) $randbs->rAndBId;

					if (!isset($min)) {
						$min = $weight;
					}
					
					// set index of minimum if no set
					if (!isset($minRAndBs)) {
						$minRAndBs = $classId;
					}
					
					if ($min > $weight) {
						$min = $weight;
						$minRAndBs = $classId;
					}
				}
				Utils::writeToCache($this->handle, $file, $minRAndBs);
			} else {
				// error or no class
				// trying old cache
				$minRAndBs = Utils::getFromCache($this->handle, $file, true);
			}
		}

		return $minRAndBs;
	}
	
	private function getFlight(array $possibleDepartureAirports, array $possibleArrivalAirports, string $departureDate, array $flightDeparturesFromFile, array $seatTypesByFlightIDs, int $passengers): ?array
	{
		$availableFlight = null;
		foreach ($possibleDepartureAirports as $departureAirport) {
			foreach ($possibleArrivalAirports as $arrivalAirport) {
				$identifiedFlights = [];
				foreach ($flightDeparturesFromFile as $fd) {
					$depDate = (new DateTime($fd['departureDatetime']))->format('Y-m-d');

					if (($fd["departureAirport"] == $departureAirport["Id"]) 
						&& ($fd["arrivalAirport"] == $arrivalAirport["Id"]) 
						&& $depDate == $departureDate) {

						$identifiedFlights[$depDate][] = $fd;
					}
				}

				ksort($identifiedFlights);

				foreach ($identifiedFlights as $dateFlights) {
					foreach ($dateFlights as $dateFlight) {
						$aviaRefFlightsParams = [
							"depIds" => $dateFlight["departureAirport"],
							"arrIds" => $dateFlight["arrivalAirport"],
							"depDate" => date("d.m.Y", strtotime($dateFlight["departureDatetime"])),
							"arrDate" => date("d.m.Y", strtotime($dateFlight["arrivalDatetime"])),
							"deviation" => 0
						];

						// get flights from avia reference
						$aviaRefFlight = $this->getAviaReferenceFlight($aviaRefFlightsParams, $dateFlight);

						if ($aviaRefFlight && $this->isAviaFlightAvailable($aviaRefFlight, $passengers)) {
							$dateFlight['airCompany'] = $aviaRefFlight['airCompany'];

							if (isset($seatTypesByFlightIDs[$dateFlight["Id"]])) {
								$dateFlight['seatType'] = $seatTypesByFlightIDs[$dateFlight["Id"]];
							} else {
								return null;
							}

							$availableFlight = $dateFlight;
						}
					}
				}
			}
		}
		return $availableFlight;
	}
	
	private function getMinAcceptableCategories(AvailabilityFilter $filter): array
	{
		$countryId = $filter->countryId;
		$cityId = $filter->departureCity ?: self::BUCHAREST_ID;

		$file = 'min-categories-' . $countryId . '-' . $cityId;

		$minCategories = Utils::getFromCache($this->handle, $file);
		if ($minCategories === null) {
			$params = [
				'locale' => 'ro',
				'xml' => 'true',
				'countryId' => $countryId,
				'cityId' => $cityId
			];

			$url = 'https://xml.tez-tour.com';
			if ($this->handle === self::TEST_HANDLE) {
				$url = $this->apiUrl;
			}

			$url = $url . '/tariffsearch/hotelClasses?' . http_build_query($params);

			$client = HttpClient::create();
			$resp = $client->request(HttpClient::METHOD_GET, $url, []);
			$this->showRequest(HttpClient::METHOD_POST, $url, [], $resp->getBody(), $resp->getStatusCode());
			$hotelClassesResp = $resp->getBody();
			
			$hotelClassesXml = @simplexml_load_string($hotelClassesResp);
			if (isset($hotelClassesXml->hotelClasses->hotelClass) && count($hotelClassesXml->hotelClasses->hotelClass) > 0) {

				$minCategories = [];
				// sort categories
				foreach ($hotelClassesXml->hotelClasses->hotelClass as $hotelClassData) {
					$weight = (int) $hotelClassData->weight;
					$classId = (int) $hotelClassData->classId;
					$minCategories[$weight] = $classId;
				}

				ksort($minCategories);

				$minCategories = json_encode($minCategories);

				Utils::writeToCache($this->handle, $file, $minCategories);
			} else {
				// error or no class
				// trying old cache
				$minCategories = Utils::getFromCache($this->handle, $file, true);
			}
		}
		$minCategories = json_decode($minCategories, true);

		return $minCategories;
	}
	
	private function getAccommodationId(int $adults, int $children): string
	{
		$accommodationsXml = $this->getXml('stayTypes');

		$accommodationTypes = [];
		foreach ($accommodationsXml->item as $accommodationData) {
			$accommodationTypes[str_replace(' ', '', (string) $accommodationData->name)] =
				(string) $accommodationData->id;
		}

		$map = [
			'10' => 'SGL',
			'11' => 'SGL+CHD',
			'12' => 'SGL+2CHD',
			'13' => '',
			'14' => '',
			'20' => 'DBL',
			'21' => 'DBL+CHD',
			'22' => 'DBL+2CHD',
			'23' => '',
			'24' => '',
			'30' => 'DBL+EXB',
			'31' => 'DBL+EXB+CHD',
			'32' => 'TRPL+2CHD',
			'33' => '',
			'34' => '',
			'40' => '4 PAX',
			'41' => '4 PAX+CHD',
			'42' => '4 PAX+2CHD',
			'43' => '',
			'44' => ''
		];

		// search for code in accommodation types
        $accommodationId = $accommodationTypes[$map[$adults . $children]] ?? '';
		return $accommodationId;
	}

	private function getHotelMapping(): array
	{
		$file = 'hotel-mapping';
		$json = Utils::getFromCache($this->handle, $file);

		if ($json === null) {
			$body = [
				'auth' => [
					'user' => ($this->username),
					'parola' => ($this->password)
				]
			];

			$body['cerere']['serviciu'] = 'getTari';

			$options['body'] = http_build_query($body);
			$options['headers'] = [ 'Content-Type' => 'application/x-www-form-urlencoded' ];

			$client = HttpClient::create();
			$response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
			$xmlString = $response->getBody();
			$this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $xmlString, $response->getStatusCode());

			$xmlCountries = simplexml_load_string($xmlString)->DataSet->Body->Tari;

			$map = [];
			foreach ($xmlCountries->Tara as $data) {
				$countryId = (string) $data->attributes()->id;
				$body['cerere']['serviciu'] = 'getStatiuni';
				$body['cerere']['param']['id_tara'] = $countryId;
				$options['body'] = http_build_query($body);

				$response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
				$xmlString = $response->getBody();
				$this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $xmlString, $response->getStatusCode());

				$xmlStatiuni = simplexml_load_string($xmlString)->DataSet->Body->Statiuni;
				foreach ($xmlStatiuni->Statiune as $data) {
					$statiuneId = (string) $data->attributes()->id;
					$body['cerere']['serviciu'] = 'getHoteluri';
					$body['cerere']['param']['id_statiune'] = $statiuneId;
					$options['body'] = http_build_query($body);

					$response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);
					$xmlString = $response->getBody();
					$this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $xmlString, $response->getStatusCode());

					$xmlHoteluri = simplexml_load_string($xmlString)->DataSet->Body->Hoteluri;
					if ($xmlHoteluri->Hotel !== null) {
						foreach ($xmlHoteluri->Hotel as $data) {
							$idExtern = (string) $data->attributes()->id_extern;
							$id = (string) $data->attributes()->id;
							$map[$idExtern] = $id;
						}
					}
				}
			}
			Utils::writeToCache($this->handle, $file, json_encode($map));
		} else {
			$map = json_decode($json, true);
		}

		return $map;
	}

	public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
	{
		Validator::make()
			->validateUsernameAndPassword($this->post)
			->validateHotelDetailsFilter($filter);

		$hotelId = $filter->hotelId;

		$base = 'https://api.tezhub.com';
		if ($this->handle === self::TEST_HANDLE) {
			$base = $this->apiUrl;
		}

		$url = $base . '/agent/v2/tours/content?hotelId='.$hotelId . '&locale=ro&apikey='.self::API_KEY;

		$client = HttpClient::create();

		$responseObj = $client->request(HttpClient::METHOD_GET, $url);
		$content = $responseObj->getBody();

		$this->showRequest(HttpClient::METHOD_GET, $url, [], $content, $responseObj->getStatusCode());

		$response = json_decode($content, true)['hotel'];

		if (!empty($response['error'])) {
			throw new Exception(json_encode($response['error']));
		}

		$items = new HotelImageGalleryItemCollection();
		foreach ($response['images'] as $responseImage) {
			$image = new HotelImageGalleryItem();
			$image->RemoteUrl = $responseImage['orig'];
			
			$image->Alt = $responseImage['title'] ?? null;
			$items->add($image);
		}

		$hotelList = $this->apiGetHotels();
		$hotelFromList= $hotelList->get($response['id']);

		if (empty($hotelFromList)) {
			return new Hotel();
		}

		$facilities = new FacilityCollection();
		foreach ($response['facilities'] as $facilityResponse) {
			if (isset($facilityResponse['list'])) {
				foreach ($facilityResponse['list'] as $facilitySub) {
					$facility = new Facility();
					$facility->Id = md5($facilitySub['name']);
					$facility->Name = $facilitySub['name'];
					$facilities->put($facility->Id, $facility);
				}
			}
		}

		$description = $response['description'];
		if (!empty($response['location'])) {
			$description .= '<br><br><b>Locatie:</b> ' . $response['location'];
		}
		if (!empty($response['distance'])) {
			$description .= '<br><br><b>Distante:</b> ' . $response['location'];
		}

		$details = Hotel::create(
			$response['id'], 
			$response['title'], 
			$hotelFromList->Address->City,
			(int) $response['category']['title'], 
			$description, 
			$response['address'], 
			$response['coordinates']['lat'],
			$response['coordinates']['lng'],
			$facilities,
			$items
		);

		return $details;
	}

	public function apiGetHotelDetailsOld(HotelDetailsFilter $filter): Hotel
	{
		Validator::make()
			->validateUsernameAndPassword($this->post)
			->validateHotelDetailsFilter($filter);

		$map = $this->getHotelMapping();

		$hotel = new Hotel();
		if (!isset($map[$filter->hotelId])) {
			return $hotel;
		}

		$body = [
			'auth' => [
				'user' => ($this->username),
				'parola' => ($this->password)
			]
		];

		$options['headers'] = [ 'Content-Type' => 'application/x-www-form-urlencoded' ];
		$body['cerere']['serviciu'] = 'getInfoHotel';
		$body['cerere']['param']['id_hotel'] = $map[$filter->hotelId];
		$options['body'] = http_build_query($body);

		$client = HttpClient::create();
		$response = $client->request(HttpClient::METHOD_POST, $this->apiUrl, $options);

		$this->showRequest(HttpClient::METHOD_POST, $this->apiUrl, $options, $response->getBody(), $response->getStatusCode());
		$xmlString = $response->getBody();
		$xmlDetails = simplexml_load_string($xmlString);
	
		$hotelXml = $xmlDetails->DataSet->Body->Hoteluri->Hotel;
		
		$hotel->Id = $hotelXml->Id_extern;
		$hotel->Name = (string) $hotelXml->Nume;
		$hotel->Stars = (int) $hotelXml->Stele;

		$content = new HotelContent();

		$description = '';
		if ($hotelXml->Descriere) {
			$description .= '<h5>Descriere hotel</h5>' . (string) $hotelXml->Descriere . '<br />';
		}
		if ($hotelXml->Camera) {
			$description .= '<h5>Camera hotel</h5>' . (string) $hotelXml->Camera . '<br />';
		}
		if ($hotelXml->Teritoriu) {
			$description .= '<h5>Spatiu exterior</h5>' . (string) $hotelXml->Teritoriu . '<br />';
		}
		if ($hotelXml->Relaxare) {
			$description .= '<h5>Relaxare si sport</h5>' . (string) $hotelXml->Relaxare . '<br />';
		}
		if ($hotelXml->Copii) {
			$description .= '<h5>Pentru copii</h5>' . (string) $hotelXml->Copii . '<br />';
		}
		if ($hotelXml->Plaja) {
			$description .= '<h5>Plaja</h5>' . (string) $hotelXml->Plaja . '<br />';
		}
		if ($hotelXml->Comentariu) {
			$description .= '<h5>Comentariul nostru</h5>' . (string) $hotelXml->Comentariu . '<br />';
		}

		$content->Content = $description;

		$items = new HotelImageGalleryItemCollection();
		foreach ($hotelXml->Imagini->Imagine as $img) {
			$item = new HotelImageGalleryItem();
			$item->RemoteUrl = (string) $img;
			$items->add($item);
		}
		$gallery = new HotelImageGallery();
		$gallery->Items = $items;

		$content->ImageGallery = $gallery;

		$hotel->Content = $content;

		return $hotel;
	}
}