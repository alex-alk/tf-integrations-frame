<?php

namespace Service\IntegrationSupport;

use Service\Omi\TF\TOInterface;

class AbstractApiServiceAdaptor extends TOInterface
{

    private readonly AbstractApiService $apiService;

    public function __construct(AbstractApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function getResponses(): RequestLogCollection
    {
        return $this->apiService->getResponses();
    }

    public function getApiService(): AbstractApiService
    {
        return $this->apiService;
    }

    function get_TOP_minimum_requests()
    {
        return $this->apiService->getTOPminimumRequests();
    }

    function cache_TOP_Data(string $operation, array $config = [], array $filters = []): array
    {
        return $this->apiService->cacheTopData($operation, $config, $filters);
    }

    /**
     * ON RETURN:
     * {
     * 			Coutry: {Id: 10}
     * 		}
     *
     *
     * 		ON FILTER:
     * 		{
     * 			'Country.Id' => 10,
     *
     * 		}
     *
     * 		{
     * 			'Country.Id' => ['>', 10],
     *
     * 		}
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_testConnection(array $filter = null)
    {
        return $this->apiService->apiTestConnection();
    }

    /**
     * Gets the countries.
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter Apply a filter like: [Id => , Name => , Code => ]
     *                           For more complex: [Name => ['like' => '...']]
     *
     * @return mixed
     */
    function api_getCountries(array $params = null)
    {
        return $this->apiService->apiGetCountries();
    }

    /**
     * Gets the regions.
     * Response format:
     * array of: Id,Name,Code,CountryId,CountryCode
     *
     * @param array|null $filter See $filter in general, CountryCode, CountryId
     *
     * @return mixed
     */
    function api_getRegions(array $filter = null)
    {
        return $this->apiService->apiGetRegions();
    }

    /**
     * Gets the regions.
     * Response format:
     * array of: Id,Name,Code,IsResort,ParentCity.Id,ParentCity.Code,Region.Code,Region.Id,Country.Code,Country.Id
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getCities(array $filter = null)
    {
        return $this->apiService->apiGetCities(new CitiesFilter($filter));
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getBoardTypes(array $filter = null)
    {
        
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getRoomTypes(array $filter = null)
    {
        return $this->apiService->getRoomTypes();
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getRoomsFacilities(array $filter = null)
    {
        
    }

    /**
     * $filter: CountryId, CountryCode, ...city
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getHotels(array $params = null)
    {
        return $this->apiService->apiGetHotels(new HotelsFilter($params));
    }

    /**
     * $filter: CountryId, CountryCode, ...city
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getHotelDetails(array $filter = null)
    {
        return $this->apiService->apiGetHotelDetails(new HotelDetailsFilter($filter));
    }

    /**
     * $filter: CountryId, CountryCode, ...city
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getHotelsCategories(array $filter = null)
    {
        
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getHotelsFacilities(array $filter = null)
    {
        
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getHotelsRooms(array $filter = null)
    {
        
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getRates(array $filter = null)
    {
        
    }

    /**
     * $filter:
     * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getHotelsBoards(array $filter = null)
    {
        
    }

    /**
     * $filter: CountryId, CountryCode, ...city
     * * Response format:
     * array of: Id,Name,Code
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getTours(array $filter = null)
    {
        return $this->apiService->apiGetTours();
    }

    /**
     * Array of: charter, tours, hotel
     *
     * @return mixed
     */
    function api_getServiceTypes()
    {
        
    }

    /**
     * $filter: Array of: charter, tours, hotel
     *
     * Returns Array of: bus, plane, individual
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getTransportTypes(array $filter = null)
    {
        
    }

    /**
     * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days,
     * departureCounty, departureCity, departureLocation, rooms
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getOffers(array $filter = null)
    {
        return $this->apiService->apiGetOffers(new AvailabilityFilter($filter));
    }

    /**
     * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days,
     * departureCounty, departureCity, departureLocation, rooms
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getOfferAvailability(array $filter = null)
    {
        
    }

    /**
     * $filter: serviceTypes, transportTypes(array), CountryId, CountryCode, ...city, IsResort, checkIn, days,
     * departureCounty, departureCity, departureLocation, rooms
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getOfferDetails(array $filter = null)
    {
        
    }

    function api_getOfferCancelFees(array $filter = null)
    {
        return $this->apiService->apiGetOfferCancelFees(new CancellationFeeFilter($filter));
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getOfferPaymentsPlan(array $filter = null)
    {
        return $this->apiService->getOfferPaymentPlans(new PaymentPlansFilter($filter));
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getOfferCancelFeesPaymentsAvailabilityAndPrice(array $filter = null)
    {
        return $this->apiService->apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(new PaymentPlansFilter($filter));
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getOfferExtraServices(array $filter = null)
    {
        
    }

    function api_downloadOffers()
    {
        return $this->apiService->downloadOffers();
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getAvailabilityDates(array $filter = null)
    {
        return $this->apiService->apiGetAvailabilityDates(new AvailabilityDatesFilter($filter));   
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_prepareBooking(array $filter = null)
    {
        
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_doBooking(array $filter = null)
    {
        return $this->apiService->apiDoBooking(new BookHotelFilter($filter));
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getBookings(array $filter = null)
    {
        
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_cancelBooking(array $filter = null)
    {
        
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getCarriers(array $filter = null)
    {
        
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getAirports(array $filter = null)
    {
        
    }

    /**
     *
     * @param array|null $filter
     *
     * @return mixed
     */
    function api_getRoutes(array $filter = null)
    {
        
    }

    /**
     *
     * @return mixed
     */
    function getSystem()
    {
        
    }

    /**
     *
     * @return mixed
     */
    function getRequestMode()
    {
        
    }

}
