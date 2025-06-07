<?php

namespace Services\IntegrationSupport;

use App\Filters\HotelDetailsFilter;
use App\Filters\HotelsFilter;
use Exception;
use Models\City;
use Models\Country;
use Models\Hotel;
use Models\OfferCancelFee;
use Models\OfferPaymentPolicy;
use Models\Region;
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
    //protected array $post;
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
            $post['get-to-requests'] = true;
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

        if ($post['get-to-requests'] ?? false) {
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

    /** @return City[] */
    abstract function apiGetCities(): array;

    // public function getBoardTypes(): BoardTypeCollection
    // {
    //     return new BoardTypeCollection();
    // }

    // public function getTOPminimumRequests() {}

    /** @return Region[] */
    public function apiGetRegions(): array
    {
        return [];
    }

    /** Note: hotels can be imported from offers 
     * @return Hotel[]
    */
    public function apiGetHotels(): array
    {
        return [];
    }

    /**
     * @return OfferCancelFee[]
     */
    public function apiGetOfferCancelFees(): array
    {
        return [];
    }

    /**
     * @return OfferPaymentPolicy[]
     */
    public function apiGetOfferPaymentsPlan(): array
    {
        return [];
    }

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

    abstract function apiGetOffers(): array;

    /** array with booking object and raw response */
    abstract function apiDoBooking(): array;

    // public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): array
    // {
    //     return [];
    // }

    abstract function apiTestConnection(): bool;

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
