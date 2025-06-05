<?php

namespace Service\IntegrationSupport;

use App\Entities\AvailabilityDates\AvailabilityDatesCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Tours\TourCollection;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use App\Filters\PaymentPlansFilter;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\BoardTypeCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\HotelFacilitiesCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\Custom\RegionCollection;
use App\Support\Collections\Custom\RoomTypeCollection;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Request;
use Exception;
use Models\Country;
use Models\RequestLog;
use Psr\Http\Message\ServerRequestInterface;
use Utils\Utils;

/**
 * All new integrations must extend this
 */

abstract class AbstractApiService
{
    protected string $apiUrl;

    protected ?string $bookingUrl = null;
    protected ?string $bookingApiUsername = null;
    protected ?string $bookingApiPassword = null;

    /** @var RequestLog[] */
    protected array $requests;
    protected array $post;
    // ApiUsername
    protected string $username;
    // ApiPassword
    protected string $password;
    // ApiContext
    protected ?string $apiContext;
    protected ?string $apiCode;
    // Handle
    protected string $handle;
    // System_Software
    protected string $software;

    protected bool $isWeb = false;
    protected bool $isApi = false;
    protected bool $skipTopCache = false;
    protected bool $renewTopCache = false;
    protected bool $clearDownloads = false;
    protected bool $getLatestCache = false;

    public function __construct(protected ServerRequestInterface $request)
    {
  
        $post = $this->request->getParsedBody();

        if (!empty($post['json'])) {
            $post = json_decode($post['json'], true);
            $post['get-raw-data'] = true;
        }

        // if (count($post) == 0) {
        //     $post = $request->getInputParams();
        // }
        // $this->post = $post;

        $this->username = $post['to']['ApiUsername'];
        $this->password = $post['to']['ApiPassword'];
        $this->apiUrl = $post['to']['ApiUrl'];
        $this->bookingUrl = $post['to']['BookingUrl'] ?? null;
        $this->bookingApiUsername = $post['to']['BookingApiUsername'] ?? null;
        $this->bookingApiPassword = $post['to']['BookingApiPassword'] ?? null;
        $this->apiContext = $post['to']['ApiContext'] ?? null;
        $this->apiCode = $post['to']['ApiCode'] ?? null;
        $this->handle = $post['to']['Handle'];
        // $this->software = $post['to']['System_Software'];

        // $this->getLatestCache = filter_var($post['to']['getLatestCache'] ?? false, FILTER_VALIDATE_BOOL);

        // if (($this->post['method'] ?? '') === 'api_getOffers') {
        //     $this->getLatestCache = true;
        // }

        // $this->skipTopCache = filter_var($post['to']['skipTopCache'] ?? false, FILTER_VALIDATE_BOOL);
        // $this->renewTopCache = filter_var($post['to']['renewTopCache'] ?? false, FILTER_VALIDATE_BOOL);
        // $this->clearDownloads = filter_var($post['to']['clearDownloads'] ?? false, FILTER_VALIDATE_BOOL);

        // $this->requests = new RequestLogCollection();
        // $this->request = new Request();

        if ($post['get-raw-data'] ?? false) {
            $this->isWeb = true;
        } else {
            $this->isApi = true;
        }

        if ($this->clearDownloads) {
            Utils::deleteDirectory(Utils::getDownloadsPath() . '/' . $this->handle);
        }
    }

    public function getSkipTopCache(): bool
    {
        return $this->skipTopCache;
    }

    public function getRenewTopCache(): bool
    {
        return $this->renewTopCache;
    }

    public function hasGetLatestCache(): bool
    {
        return $this->getLatestCache;
    }

    public function gethandle(): string
    {
        return $this->handle;
    }

    function cacheTopData(string $operation, array $config = [], array $filters = []): array
    {
        $result = [];
        switch ($operation) {
            case 'Hotels_Details':
                foreach ($filters['Hotels'] as $hotelFilter) {
                    if (!empty($hotelFilter['InTourOperatorId'])) {
                        $hotelDetailsFilter = new HotelDetailsFilter(['HotelId' => $hotelFilter['InTourOperatorId']]);
                        $hotel = $this->apiGetHotelDetails($hotelDetailsFilter);

                        if (empty($hotel->Id)) {
                            continue;
                        }
                        $result[] = $hotel;
                    }
                }
                break;
            case 'Countries':
                break;
            case 'Counties':
                break;
            case 'Cities':
                break;
            case 'Hotels':
                foreach ($filters['Cities'] as $hotelFilter) {
                    if (!empty($hotelFilter['InTourOperatorId'])) {
                        $hotelsFilter = new HotelsFilter(['CityId' => $hotelFilter['InTourOperatorId']]);
                        $hotels = $this->apiGetHotels($hotelsFilter)->toArray();
                        $result += $hotels;
                    }
                }
                break;
            default:
                throw new Exception('Operation not found!');
        }

        return $result;
    }

    /** @return Country[] */
    abstract function apiGetCountries(): array;

    
    // abstract function apiGetCities(?CitiesFilter $filter = null): CityCollection;

    // public function getBoardTypes(): BoardTypeCollection
    // {
    //     return new BoardTypeCollection();
    // }

    // public function getTOPminimumRequests() {}

    // public function apiGetRegions(): RegionCollection
    // {
    //     return new RegionCollection();
    // }

    // public function getHotelFacilities(): HotelFacilitiesCollection
    // {
    //     return new HotelFacilitiesCollection();
    // }

    // public function getRoomTypes(): RoomTypeCollection
    // {
    //     return new RoomTypeCollection();
    // }

    // /** hotel list can be created from the hotels returned from offers */
    // public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    // {
    //     return new HotelCollection();
    // }

    // /**  use only if there is a separate api endpoint */
    // public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    // {
    //     return new Hotel();
    // }

    // public function apiGetOfferCancelFees(CancellationFeeFilter $filter): OfferCancelFeeCollection
    // {
    //     return new OfferCancelFeeCollection();
    // }

    // public function getOfferPaymentPlans(PaymentPlansFilter $filter): OfferPaymentPolicyCollection
    // {
    //     return new OfferPaymentPolicyCollection();
    // }

    // /**
    //  * Check Dertour
    //  * [$offFees, $offPayments, $offAvailability, $offPrice, $offInitialPrice, $offCurrency]
    //  */
    // public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    // {
    //     return [];
    // }

    // public function apiGetTours(): TourCollection
    // {

    //     return new TourCollection();
    // }

    // public function downloadOffers(): void {}

    // abstract function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection;

    // /** array with booking object and raw response */
    // abstract function apiDoBooking(BookHotelFilter $filter): array;

    // public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    // {
    //     return new AvailabilityDatesCollection();
    // }

    // public function apiTestConnection(): bool
    // {
    //     $this->skipTopCache = true;

    //     try {
    //         $countries = $this->apiGetCountries();
    //     } catch (Exception $e) {
    //         return false;
    //     }
    //     $ok = false;
    //     if (count($countries) > 0) {
    //         $ok = true;
    //     }
    //     return $ok;
    // }

    // get response/responses objects from api calls
    function getResponses(): array
    {
        return $this->requests;
    }

    public function showRequest(string $method, string $url, string $body, $headers, string $response, int $statusCode, float $duration = 0): void
    {
        // $storagePath = Utils::getStoragePath();

        // if (file_exists($storagePath . '/Logs/settings.json')) {
        //     $settingsJson = file_get_contents($storagePath . '/Logs/settings.json');
        //     $settings = json_decode($settingsJson, true);
        // }

        // $activatedRequestsLogs = [];
        // if (isset($settings['activatedRequestsLogs'])) {
        //     $activatedRequestsLogs = $settings['activatedRequestsLogs'];
        // }

        // $activatedLog = false;
        // foreach ($activatedRequestsLogs as $top) {
        //     if ($top === $this->handle) {
        //         $activatedLog = true;
        //         break;
        //     }
        // }

        $requestLog = new RequestLog($method, $url, $body, $headers);
        $requestLog->statusCode = $statusCode;
        $requestLog->duration = $duration;

        if ($this->isWeb || isset($this->post['method']) && $this->post['method'] === 'api_doBooking') {
            $requestLog->response = $response;
        } else {
            $shortResp = substr($response, 0, 500);

            if (strlen($shortResp) === 500) {
                $shortResp .= ' ...';
            }
            $requestLog->response = $shortResp;
        }
        $this->requests[] = $requestLog;
    }
}
