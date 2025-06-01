<?php

namespace Service\Omi\TF;

use App\Support\Http\Async\RequestCollection;
use App\Support\Http\RequestLog;
use App\Support\Http\RequestLogCollection;
use App\Support\Http\ResponseInterfaceCollection;
use Omi\TF\TOInterface_API;
use Omi\TF\TOInterface_Util;

/**
 * Objectives:
 *		REST API
 * 
 */
abstract class TOInterface
{
	use TOInterface_API, TOInterface_Util;
	
	const RequestModeSoap = 1;
	
	const RequestModeCurl = 2;
	
	const RequestModeCustom = 3;

	/**
	 * @var string[]
	 */
	protected $cookies;
	/**
	 * @var string
	 */
	protected $session_id;

	public $dev_mode = true;
	
	public $useMultiInTop = false;

	private RequestLogCollection $requests;
	private ?bool $isWeb = null;

	public function getResponses(): RequestLogCollection
	{
		if (!isset($this->requests)) {
			$this->requests = new RequestLogCollection();
		}
		return $this->requests;
	}

	public function showRequest(string $method, string $url, array $options, string $response, int $statusCode): void
    {
		if (isset($_POST['get-raw-data'])) {
            $this->isWeb = true;
        }
        if (!isset($this->requests)) {
            $this->requests = new RequestLogCollection();
        }
        if ($this->isWeb) {
            $requestLog = new RequestLog($method, $url, $options);
            $requestLog->response = $response;
            $requestLog->statusCode = $statusCode;
            $this->requests->add($requestLog);
        }
    }

	function cache_TOP_Data(string $operation, array $config = [], array $filters = []): array
    {
        $result = [];

        switch ($operation) {
            case 'Hotel_Details':
                foreach ($filters['Hotels'] as $hotelFilter) {
                    $hotel = $this->api_getHotelDetails(['HotelId' => $hotelFilter['$id']]);
                    $result[] = $hotel;
                }
                break;
            case 'Countries':
                break;
            case 'Counties':
                break;
            case 'Cities':
                break;
            case 'Hotels':
                break;
        }

        return $result;
    }

	/**
	 * ON RETURN: 
	 * {
			Coutry: {Id: 10}
		}


		ON FILTER:
		{
			'Country.Id' => 10,

		}

		{
			'Country.Id' => ['>', 10],

		}
	 */

	public abstract function api_testConnection(array $filter = null);

	/**
	 * Gets the countries.
	 * Response format: 
	 *		array of: Id,Name,Code
	 * 
	 * @param array $filter Apply a filter like: [Id => , Name => , Code => ]
	 *						For more complex: [Name => ['like' => '...']]
	 * 
	 */
	public abstract function api_getCountries(array $filter = null);

	/**
	 * Gets the regions.
	 * Response format: 
	 *		array of: Id,Name,Code,CountryId,CountryCode
	 * 
	 * @param array $filter See $filter in general, CountryCode, CountryId
	 */
	public abstract function api_getRegions(array $filter = null);

	/**
	 * Gets the regions.
	 * Response format: 
	 *		array of: Id,Name,Code,IsResort,ParentCity.Id,ParentCity.Code,Region.Code,Region.Id,Country.Code,Country.Id
	 * 
	 * @param array $filter
	 */
	public abstract function api_getCities(array $filter = null);
	
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getBoardTypes(array $filter = null);
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getRoomTypes(array $filter = null);
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getRoomsFacilities(array $filter = null);

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getHotels(array $filter = null);
	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getHotelDetails(array $filter = null);

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getHotelsCategories(array $filter = null);

	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getHotelsFacilities(array $filter = null);

	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getHotelsRooms(array $filter = null);
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getRates(array $filter = null);
	/**
	 * $filter: 
	 * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getHotelsBoards(array $filter = null);

	/**
	 * $filter: CountryId, CountryCode, ...city
	 * * Response format: 
	 *		array of: Id,Name,Code
	 */
	public abstract function api_getTours(array $filter = null);

	/**
	 * Array of: charter, tours, hotel
	 */
	public abstract function api_getServiceTypes();
	/**
	 * $filter: Array of: charter, tours, hotel
	 * 
	 * Returns Array of: bus, plane, individual
	 */
	public abstract function api_getTransportTypes(array $filter = null);

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public abstract function api_getOffers(array $filter = null);

	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public abstract function api_getOfferAvailability(array $filter = null);
	
	/**
	 * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days, 
	 *				departureCounty, departureCity, departureLocation, rooms
	 */
	public abstract function api_getOfferDetails(array $filter = null);


	/**
	 * 
	 */
	public abstract function api_getOfferCancelFees(array $filter = null);

	/**
	 * 
	 */
	public abstract function api_getOfferPaymentsPlan(array $filter = null);
	
	/**
	 * 
	 */
	public abstract function api_getOfferCancelFeesPaymentsAvailabilityAndPrice(array $filter = null);

	/**
	 * 
	 */
	public abstract function api_getOfferExtraServices(array $filter = null);

	/**
	 * 
	 */
	public abstract function api_getAvailabilityDates(array $filter = null);
	
	/**
	 * @param array $filter
	 */
	public abstract function api_prepareBooking(array $filter = null);

	/**
	 * @param array $filter
	 */
	public abstract function api_doBooking(array $filter = null);

	/**
	 * @param array $filter
	 */
	public abstract function api_getBookings(array $filter = null);

	/**
	 * @param array $filter
	 */
	public abstract function api_cancelBooking(array $filter = null);


	/* ----------------------------plane tickets operators---------------------*/

	public abstract function api_getCarriers(array $filter = null);

	public abstract function api_getAirports(array $filter = null);

	public abstract function api_getRoutes(array $filter = null);

	/* ----------------------------end plane tickets operators---------------------*/

	/**
	 * @return string[] Key value pair
	 */
	public function api_getCookies()
	{
		return $this->cookies;
	}
	/**
	 * @return string
	 */
	public function api_getSessionId()
	{
		return $this->session_id;
	}

	/**
	 * @param string[] $cookies Key value pair
	 */
	public function api_setCookies(array $cookies = null)
	{
		$this->cookies = $cookies;
	}
	
	/**
	 * @param string $session_id
	 */
	public function api_setSessionId(string $session_id = null)
	{
		$this->session_id = $session_id;
	}
	
	public abstract function getSystem();
	
	public abstract function getRequestMode();
	
	public function useSoap()
	{
		return ($this->getRequestMode() === static::RequestModeSoap);
	}
	
	public function useCurl()
	{
		return ($this->getRequestMode() === static::RequestModeCurl);
	}

	//public abstract function getSoapClientByMethodAndFilter($method, $filter = null);
}