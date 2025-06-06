<?php

namespace Integrations\Dertour;

use App\Entities\Availability\AirportTaxesCategory;
use App\Entities\Availability\Availability;
use App\Entities\Availability\Currency;
use App\Entities\Availability\DepartureTransportItem;
use App\Entities\Availability\MealItem;
use App\Entities\Availability\MealMerch;
use App\Entities\Availability\MealMerchType;
use App\Entities\Availability\Offer;
use App\Entities\Availability\OfferCancelFee;
use App\Entities\Availability\OfferCollection;
use App\Entities\Availability\OfferPaymentPolicy;
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
use App\Entities\AvailabilityDates\AvailabilityDatesCollection;
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
use App\Entities\Hotels\HotelImageGallery;
use App\Entities\Hotels\HotelImageGalleryItem;
use App\Entities\Hotels\HotelImageGalleryItemCollection;
use App\Entities\Hotels\Hotel;
use App\Entities\Hotels\HotelAddress;
use App\Entities\Hotels\HotelContent;
use App\Entities\Tours\Location;
use App\Entities\Tours\Tour;
use App\Entities\Tours\TourCollection;
use App\Entities\Tours\TourContent;
use App\Entities\Tours\TourImageGallery;
use App\Entities\Tours\TourImageGalleryItem;
use App\Entities\Tours\TourImageGalleryItemCollection;
use App\Filters\AvailabilityDatesFilter;
use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CitiesFilter;
use App\Filters\HotelsFilter;
use App\Filters\PaymentPlansFilter;
use App\Support\Collections\Custom\AvailabilityCollection;
use App\Support\Collections\Custom\CityCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Collections\Custom\HotelCollection;
use App\Support\Collections\Custom\OfferCancelFeeCollection;
use App\Support\Collections\Custom\OfferPaymentPolicyCollection;
use App\Support\Collections\StringCollection;
use App\Support\Ftp\FtpsClient;
use App\Support\Http\SimpleAsync\HttpClient;
use App\Support\Log;
use App\TestHandles;
use DateTime;
use DateTimeImmutable;
use Exception;
use FTP\Connection;
use IntegrationSupport\AbstractApiService;
use IntegrationSupport\IntegrationFunctions;
use IntegrationSupport\ResponseConverter;
use IntegrationSupport\Validator;
use SimpleXMLElement;
use Utils\Utils;

class DertourApiService extends AbstractApiService
{
    private const DOWNLOAD_HOTELS = 'downloadHotels';
    private const DOWNLOAD_MASTER_DATA = 'downloadMasterData';
    private const FOLDER_OFFERS_CHARTER = 'offers-charter';
    private const FOLDER_OFFERS_HOTEL = 'offers-hotel';
    private const FOLDER_OFFERS_TOUR = 'offers-tour';
    private const TEST_HANDLE = 'localhost-dertour';
    private const SANDBOX_HANDLE = 'dertour_test';

    public function __construct()
    {
        parent::__construct();
    }

    public function apiGetCountries(): CountryCollection
    {
        $cities = $this->apiGetCities();

        $countries = new CountryCollection();
        foreach ($cities as $city) {
            $countries->put($city->Country->Id, $city->Country);
        }

        return $countries;
    }

    public function apiGetCities(CitiesFilter $params = null): CityCollection
    {
        $citiesJson = Utils::getFromCache($this, 'cities');

        if ($citiesJson === null) {

            $response = $this->requestFile(self::DOWNLOAD_HOTELS);

            $xml = file_get_contents($response);
            $xmlObj = new SimpleXMLElement($xml);

            $cities = new CityCollection();

            $romanaia = new Country();
            $romanaia->Code = 'RO';
            $romanaia->Id = $romanaia->Code;
            $romanaia->Name = 'Romania';

            $folderOffers = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_CHARTER . '/uncompressed/*';

            // $hotels = $this->getHotels();
            // $folderOffersHotels = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_HOTEL . '/uncompressed/*';
            // $files = glob($folderOffersHotels);
            // $cities = [];
            // if (isset($files[0])) {
            //     dump('ok');
            //     $handle = fopen($files[0], 'r');
            //     while (($row = fgets($handle)) !== false) {
            //         $array['hotelCode'] = trim(substr($row, 377, 8));
            //         $hotel = $hotels->get($array['hotelCode']);
            //         $cities[$hotel->Address->City->Id] = $hotel->Address->City;
            //     }
            // }
            // dump($cities);
            // foreach ($cities as $city) {
            //     echo $city->Id . ', ' . $city->Name . '<br>';
            // }
            // die;

            // $apirportMap = [];
            foreach ($xmlObj->tour as $v) {
                if (!isset($v->location)) {
                    // Log::warning('No address found for hotel id ' . $v->code);
                    continue;
                }

                $country = new Country();
                $country->Id = $v->location->country->attributes()->code;
                $country->Code = $country->Id;
                $country->Name = $v->location->country->attributes()->name;

                $city = new City();
                $city->Id = $v->location->country->destination->resort->attributes()->code;
                $city->Name = $v->location->country->destination->resort->attributes()->name;

                $city->Country = $country;
                $cities->put($city->Id, $city);
                // $airportMap[substr($city->Id, 0, 3)] = $city;
            }
            
            $airportMapFromFile = $this->getAirportMap();

            $files = glob($folderOffers);
            if (!isset($files[0])) {
                throw new Exception('There is no charter offers file downloaded');
            }

            if (isset($files[0])) {
                $handle = fopen($files[0], 'r');
                while (($row = fgets($handle)) !== false) {
                    $array['departureAirport'] = substr($row, 53, 3);
                    $array['arrivalAirport'] = substr($row, 56, 3);
                    $array['returnDepartureAirport'] = substr($row, 59, 3);

                    if (isset($airportMapFromFile[$array['departureAirport']])) {
                        // create from file
                        $departureAirport = $airportMapFromFile[$array['departureAirport']];
                        $departureCity = new City();
                        $departureCity->Id = $array['departureAirport'];
                        $departureCity->Name = $departureAirport['cityName'];

                        $country = new Country();
                        $country->Code = $departureAirport['countryCode'];
                        $country->Id = $country->Code;
                        $country->Name = $departureAirport['countryName'];

                        $departureCity->Country = $country;
                        $cities->put($departureCity->Id, $departureCity);

                    } else {
                        throw new Exception('airport not found: ' . $array['departureAirport']);
                    }

                    $airports[$array['arrivalAirport']] = $array['arrivalAirport'];
                    if (isset($airportMapFromFile[$array['arrivalAirport']])) {
                        // create from file
                        
                        $arrivalAirport = $airportMapFromFile[$array['arrivalAirport']];
                        $arrivalCity = new City();
                        $arrivalCity->Id = $array['arrivalAirport'];
                        $arrivalCity->Name = $arrivalAirport['cityName'];

                        $country = new Country();
                        $country->Code = $arrivalAirport['countryCode'];
                        $country->Id = $country->Code;
                        $country->Name = $arrivalAirport['countryName'];

                        $arrivalCity->Country = $country;

                        $cities->put($arrivalCity->Id, $arrivalCity);
                    } else {
                        throw new Exception('airport not found: ' . $array['arrivalAirport']);
                    }

                    $airports[$array['returnDepartureAirport']] = $array['returnDepartureAirport'];
                    if (isset($airportMapFromFile[$array['returnDepartureAirport']])) {
                        // create from file
                        
                        $arrivalAirport = $airportMapFromFile[$array['returnDepartureAirport']];
                        $arrivalCity = new City();
                        $arrivalCity->Id = $array['returnDepartureAirport'];
                        $arrivalCity->Name = $arrivalAirport['cityName'];

                        $country = new Country();
                        $country->Code = $arrivalAirport['countryCode'];
                        $country->Id = $country->Code;
                        $country->Name = $arrivalAirport['countryName'];

                        $arrivalCity->Country = $country;

                        $cities->put($arrivalCity->Id, $arrivalCity);
                    } else {
                        throw new Exception('airport not found: ' . $array['returnDepartureAirport']);
                    }
                }
                fclose($handle);
            }

            $folderOffers = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_TOUR . '/uncompressed/*';
            $files = glob($folderOffers);
            if (!isset($files[0])) {
                throw new Exception('There is no tour offers file downloaded');
            }

            if (isset($files[0])) {
                $handle = fopen($files[0], 'r');
                while (($row = fgets($handle)) !== false) {
                    $array['departureAirport'] = substr($row, 53, 3);
                    $array['arrivalAirport'] = substr($row, 56, 3);
                    $array['returnDepartureAirport'] = substr($row, 59, 3);

                    if (isset($airportMapFromFile[$array['departureAirport']])) {
                        // create from file
                        $departureAirport = $airportMapFromFile[$array['departureAirport']];
                        $departureCity = new City();
                        $departureCity->Id = $array['departureAirport'];
                        $departureCity->Name = $departureAirport['cityName'];

                        $country = new Country();
                        $country->Code = $departureAirport['countryCode'];
                        $country->Id = $country->Code;
                        $country->Name = $departureAirport['countryName'];

                        $departureCity->Country = $country;
                        $cities->put($departureCity->Id, $departureCity);

                    } else {
                        throw new Exception('airport not found: ' . $array['departureAirport']);
                    }
                    $airports[$array['arrivalAirport']] = $array['arrivalAirport'];
                    if (isset($airportMapFromFile[$array['arrivalAirport']])) {
                        // create from file
                        
                        $arrivalAirport = $airportMapFromFile[$array['arrivalAirport']];
                        $arrivalCity = new City();
                        $arrivalCity->Id = $array['arrivalAirport'];
                        $arrivalCity->Name = $arrivalAirport['cityName'];

                        $country = new Country();
                        $country->Code = $arrivalAirport['countryCode'];
                        $country->Id = $country->Code;
                        $country->Name = $arrivalAirport['countryName'];

                        $arrivalCity->Country = $country;

                        $cities->put($arrivalCity->Id, $arrivalCity);
                    } else {
                        throw new Exception('airport not found: ' . $array['arrivalAirport']);
                    }

                    $airports[$array['returnDepartureAirport']] = $array['returnDepartureAirport'];
                    if (isset($airportMapFromFile[$array['returnDepartureAirport']])) {
                        // create from file
                        
                        $arrivalAirport = $airportMapFromFile[$array['returnDepartureAirport']];
                        $arrivalCity = new City();
                        $arrivalCity->Id = $array['returnDepartureAirport'];
                        $arrivalCity->Name = $arrivalAirport['cityName'];

                        $country = new Country();
                        $country->Code = $arrivalAirport['countryCode'];
                        $country->Id = $country->Code;
                        $country->Name = $arrivalAirport['countryName'];

                        $arrivalCity->Country = $country;

                        $cities->put($arrivalCity->Id, $arrivalCity);
                    } else {
                        throw new Exception('airport not found: ' . $array['returnDepartureAirport']);
                    }
                }
                fclose($handle);
            }

            Utils::writeToCache($this->handle, 'cities', json_encode($cities));

        } else {
            $citiesArray = json_decode($citiesJson, true);
            $cities = ResponseConverter::convertToCollection($citiesArray, CityCollection::class);
        }

        return $cities;
    }

    private function getAirportMap(): array
    {
        $map = [];
        if (($handle = fopen(__DIR__ . '/airports.csv', 'r')) !== FALSE) {
            $i = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $i++; if ($i === 1) continue;

                $map[$data[0]] = [
                    'cityId' => $data[1],
                    'cityName' => $data[2],
                    'countryCode' => $data[3],
                    'countryName' => $data[4]
                ];
            }
            fclose($handle);
        }
        return $map;
    }

    public function apiGetAvailabilityDates(AvailabilityDatesFilter $filter): AvailabilityDatesCollection
    {
        $availabilityDatesCollection = new AvailabilityDatesCollection();
        $transportType = AvailabilityDates::TRANSPORT_TYPE_PLANE;
        $cities = $this->apiGetCities();

        $hotels = $this->apiGetHotels();

        $filesLoc = null;
        if ($filter->type === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            $filesLoc = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_CHARTER . '/uncompressed/*';
        } elseif ($filter->type === AvailabilityFilter::SERVICE_TYPE_TOUR) {
            $filesLoc = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_TOUR . '/uncompressed/*';
        }
        
        $files = glob($filesLoc);
        if (isset($files[0])) {
            $handle = fopen($files[0], 'r');

            while (($row = fgets($handle)) !== false) {
                $hotelCode = trim(substr($row, 377, 8));
                $hotel = $hotels->get($hotelCode);

                // from the mail:
                // It this case it is fault of ProducManager - he has hotel calculated in CRS but NBC content is not ready yet...
                // You have to ignore this hotel.

                if ($hotel === null) {
                    continue;
                }
                
                $departureCity = $cities->get(substr($row, 53, 3));

                if ($departureCity->Country->Code !== 'RO' && $departureCity->Country->Code !== 'HU') {
                    continue;
                }

                // $arrivalCity = $cities->get(substr($row, 56, 3));
                $arrivalCity = $hotel->Address->City;
                
                $id = $transportType 
                    . "~city|" . $departureCity->Id 
                    . "~city|" . $arrivalCity->Id;
                $existingAvailabilityDates = $availabilityDatesCollection->get($id);

                $accomodationDateTime = new DateTimeImmutable(substr($row, 33, 10));
                $offsetArrivalToAccom = trim(substr($row, 385, 2));

                //$arrivalDateTime = $accomodationDateTime->modify($offsetArrivalToAccom . ' days');
                $departureArrivalOffset = trim(substr($row, 411, 2));

                $totalOffset = $offsetArrivalToAccom + $departureArrivalOffset;

                $departureDateTime = $accomodationDateTime->modify('-' . $totalOffset . ' days');
                $departureDateStr = $departureDateTime->format('Y-m-d');

                // ckeckin + nights + offset
                $nightsInt = (int) trim(substr($row, 43, 10)) + $totalOffset;

                // if the city combination exists, add to it's Dates and Nights
                // if not, create new

                $availabilityDates = null;
                if ($existingAvailabilityDates === null) {
                    $transportCityFrom = new TransportCity();
                    $transportCityFrom->City = $departureCity;
                    $transportCityTo = new TransportCity();
                    $transportCityTo->City = $arrivalCity;

                    $transportDate = new TransportDate();
                    $transportDate->Date = $departureDateStr;

                    $availabilityDates = new AvailabilityDates();
                    $availabilityDates->Id = $id;
                    $availabilityDates->From = $transportCityFrom;
                    $availabilityDates->To = $transportCityTo;
                    $availabilityDates->TransportType = $transportType;
                    $availabilityDates->Content = new TransportContent();

                    // creating Dates array
                    $night = new DateNight();
                    $night->Nights = $nightsInt;

                    $nights = new DateNightCollection();
                    $nights->put($nightsInt, $night);
                    $transportDate->Nights = $nights;

                    $transportDateCollection = new TransportDateCollection();
                    $transportDateCollection->put($transportDate->Date, $transportDate);
                    $availabilityDates->Dates = $transportDateCollection;
                    
                } else {
                    $dateObj = $existingAvailabilityDates->Dates;

                    // check if date index exists
                    $existingDateIndex = $dateObj->get($departureDateStr);

                    if ($existingDateIndex === null) {

                        // adding date to cities index
                        $transportDate = new TransportDate();
                        $transportDate->Date = $departureDateStr;

                        // creating Dates array
                        $night = new DateNight();
                        $night->Nights = $nightsInt;

                        $nights = new DateNightCollection();
                        $nights->put($nightsInt, $night);
                        $transportDate->Nights = $nights;

                        $dateObj->put($transportDate->Date, $transportDate);
                        $existingAvailabilityDates->Dates = $dateObj;
                        $availabilityDates = $existingAvailabilityDates;

                    } else {

                        // add nights to date object
                        $night = new DateNight();
                        $night->Nights = $nightsInt;

                        $nights = $existingDateIndex->Nights;
                        $nights->put($nightsInt, $night);
                        $existingDateIndex->Nights = $nights;

                        $dateObj->put($existingDateIndex->Date, $existingDateIndex);
                        $existingAvailabilityDates->Dates = $dateObj;
                        $availabilityDates = $existingAvailabilityDates;
                    }
                }
                $availabilityDatesCollection->put($id, $availabilityDates);
            }
        } else {
            Log::warning($this->handle . ': offers file not present!');
        }

        return $availabilityDatesCollection;
    }

    private function unzipGzip($srcName, $dstName): void
    {
        $sfp = gzopen($srcName, "r");
        $fp = fopen($dstName, "w");

        while (!gzeof($sfp)) {
            $string = gzread($sfp, 4096);
            fwrite($fp, $string, strlen($string));
        }
        gzclose($sfp);
        fclose($fp);
    }

    public function downloadHotelOffers(): bool
    {
        $url = env('DERTOUR_FTP_URL');
        $username = env('DERTOUR_FTP_USERNAME');
        $password = env('DERTOUR_FTP_PASSWORD');

        // get latest file name from dertour server
        $serverDir = 'DERTOURISTIK_RO';

        if ($this->handle === self::SANDBOX_HANDLE) {
            $serverDir .= '/DEV';
        }

        if ($this->handle === self::TEST_HANDLE) {
            $file = '5DSA-20231025_0530-INFX2.gz';
        } else {
            $file = $this->getLatestOfferFileName($url, $username, $password, $serverDir, '5DSA');
        }

        $offersDownloaded = false;

        // if file is null, do nothing, log error; old offer split files will be used
        if ($file === null) {
            Log::warning($this->handle . ': No dertour hotel offers files exist on their server');
        } else {
            $downloadsPath = Utils::getDownloadsPath();
            $localFolder = $downloadsPath . '/'
                . $this->handle . '/'
                . self::FOLDER_OFFERS_HOTEL;
            $localGzipFilePath =  $localFolder. '/'
                . $file;

            // download file if it is a new file
            if (!file_exists($localGzipFilePath)) {

                $relativeFolder = $this->handle . '/' . self::FOLDER_OFFERS_HOTEL;
                //$absoluteFilePath = $downloadsPath . '/' . $relativeFolder. '/' . $file;

                // $options = [
                //     'username' => $username,
                //     'password' => $password,
                //     'localFile' => $file,
                //     'relativeFolder' => $relativeFolder,
                //     'body' => $serverDir . '/' . $file
                // ];

                // download file
                $createdForTest = false;
                if ($this->handle === self::TEST_HANDLE) {
                    // create file
                    // create gz

                    // Name of the file we're compressing
                    $fileTxt = "5DSA-20231025_0530-INFX2";
                    if (!is_dir($localFolder)) {
                        mkdir($localFolder, 0755, true);
                    }
                    
                    file_put_contents($localFolder . '/' . $fileTxt, $this->getDummyDataHotelOffers());
                    // Open the gz file (w9 is the highest compression)
                    $fp = gzopen ($localGzipFilePath, 'w9');
                    // Compress the file
                    gzwrite ($fp, file_get_contents($localFolder . '/' . $fileTxt));
                    gzclose($fp);
                    // remove test file
                    unlink($localFolder . '/' . $fileTxt);

                    $createdForTest = true;
                } else {
                    $ftpClient = FtpsClient::create();
                    $url = $url . '/' . $serverDir . '/' . $file;
                    $response = $ftpClient->request($url, $localFolder, $file, $username, $password);
                }

                if ($createdForTest || $response->fileDownloaded()) {

                    // after download, remove old gzip and uncompressed file
                    // after split, remove split files

                    $dir = $downloadsPath . '/' . $this->handle . '/' . self::FOLDER_OFFERS_HOTEL . '/uncompressed';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $localFileName = str_replace('.gz', '', $file);
                    $localFilePath = $dir . '/' . $localFileName;
                    $this->unzipGzip($localGzipFilePath, $localFilePath);

                    // remove previous gzip and uncompressed files

                    $gzipFiles = glob($downloadsPath . '/' . $relativeFolder . "/*");
                    foreach ($gzipFiles as $gzfile) {
                        if (is_file($gzfile) && $gzfile !== $localGzipFilePath) {
                            unlink($gzfile);
                        }
                    }

                    $uncomprFiles = glob($dir . '/*');
                    foreach ($uncomprFiles as $ucfile) {
                        if (is_file($ucfile) && $ucfile !== $localFilePath) {
                            unlink($ucfile);
                        }
                    }

                    $fileDate = substr($file, 5, 13);
                    $this->chunkOffers($localFilePath, $fileDate, self::FOLDER_OFFERS_HOTEL);
                    Log::debug($this->handle . ': hotel offers downloaded');
                    $offersDownloaded = true;
                } else {
                    Log::warning($this->handle . ': hotel offers file cannot be downloaded');
                }
            } else {
                $offersDownloaded = true;
            }
        }
        return $offersDownloaded;
    }

    public function downloadCharterOffers(): bool
    {
        $url = env('DERTOUR_FTP_URL');
        $username = env('DERTOUR_FTP_USERNAME');
        $password = env('DERTOUR_FTP_PASSWORD');

        // get latest file name from dertour server
        $serverDir = 'DERTOURISTIK_RO';

        if ($this->handle === self::SANDBOX_HANDLE) {
            $serverDir .= '/DEV';
        }

        if ($this->handle === self::TEST_HANDLE) {
            $file = '5DSA-20231025_0530-INFX2.gz';
        } else {
           $file = $this->getLatestOfferFileName($url, $username, $password, $serverDir, '5DSP');
        }

        $offersDownloaded = false;
        // if file is null, do nothing, log error; old offer split files will be used
        if ($file === null) {
            Log::warning($this->handle . ': No charter offers files exist on their server');
        } else {
            $downloadsPath = Utils::getDownloadsPath();
            $localFolder = $downloadsPath . '/'
                . $this->handle . '/'
                . self::FOLDER_OFFERS_CHARTER;
            $localGzipFilePath =  $localFolder. '/'
                . $file;

            // download file if it is a new file
            if (!file_exists($localGzipFilePath)) {

                $relativeFolder = $this->handle . '/' . self::FOLDER_OFFERS_CHARTER;
                //$absoluteFilePath = $downloadsPath . '/' . $relativeFolder. '/' . $file;

                // $options = [
                //     'username' => $username,
                //     'password' => $password,
                //     'localFile' => $file,
                //     'relativeFolder' => $relativeFolder,
                //     'body' => $serverDir . '/' . $file
                // ];

                // download file
                $createdForTest = false;
                if ($this->handle === self::TEST_HANDLE) {
                    // create file
                    // create gz

                    // Name of the file we're compressing
                    $fileTxt = "5DSA-20231025_0530-INFX2";
                    if (!is_dir($localFolder)) {
                        mkdir($localFolder, 0755, true);
                    }
                    
                    file_put_contents($localFolder . '/' . $fileTxt, $this->getDummyDataCharterOffers());
                    // Open the gz file (w9 is the highest compression)
                    $fp = gzopen ($localGzipFilePath, 'w9');
                    // Compress the file
                    gzwrite ($fp, file_get_contents($localFolder . '/' . $fileTxt));
                    gzclose($fp);
                    // remove test file
                    unlink($localFolder . '/' . $fileTxt);

                    $createdForTest = true;
                } else {
                    $ftpClient = FtpsClient::create();
                    $url = $url . '/' . $serverDir . '/' . $file;
                    $response = $ftpClient->request($url, $localFolder, $file, $username, $password);
                }

                if ($createdForTest || $response->fileDownloaded()) {

                    // after download, remove old gzip and uncompressed file
                    // after split, remove split files

                    $dir = $downloadsPath . '/' . $this->handle . '/' . self::FOLDER_OFFERS_CHARTER . '/uncompressed';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $localFileName = str_replace('.gz', '', $file);
                    $localFilePath = $dir . '/' . $localFileName;
                    $this->unzipGzip($localGzipFilePath, $localFilePath);

                    // remove previous gzip and uncompressed files

                    $gzipFiles = glob($downloadsPath . '/' . $relativeFolder . "/*");
                    foreach ($gzipFiles as $gzfile) {
                        if (is_file($gzfile) && $gzfile !== $localGzipFilePath) {
                            unlink($gzfile);
                        }
                    }

                    $uncomprFiles = glob($dir . '/*');
                    foreach ($uncomprFiles as $ucfile) {
                        if (is_file($ucfile) && $ucfile !== $localFilePath) {
                            unlink($ucfile);
                        }
                    }

                    $fileDate = substr($file, 5, 13);
                    $this->chunkOffers($localFilePath, $fileDate, self::FOLDER_OFFERS_CHARTER);
                    Log::debug($this->handle . ': charter offers downloaded');
                    $offersDownloaded = true;
                    
                } else {
                    Log::warning($this->handle . ': charter offers file cannot be downloaded');
                }
            } else {
                $offersDownloaded = true;
            }
        }
        return $offersDownloaded;
    }

    public function downloadTourOffers(): bool
    {
        $url = env('DERTOUR_FTP_URL');
        $username = env('DERTOUR_FTP_USERNAME');
        $password = env('DERTOUR_FTP_PASSWORD');

        // get latest file name from dertour server
        $serverDir = 'DERTOURISTIK_RO';

        if ($this->handle === self::SANDBOX_HANDLE) {
            $serverDir .= '/DEV';
        }

        if ($this->handle === self::TEST_HANDLE) {
            $file = '5DSR-20231025_0530-INFX2.gz';
        } else {
            $file = $this->getLatestOfferFileName($url, $username, $password, $serverDir, '5DSR');
        }

        $offersDownloaded = false;
        // if file is null, do nothing, log error; old offer split files will be used
        if ($file === null) {
            Log::warning($this->handle . ': No tour offers files exist on their server');
        } else {
            $downloadsPath = Utils::getDownloadsPath();
            $localFolder = $downloadsPath . '/'
                . $this->handle . '/'
                . self::FOLDER_OFFERS_TOUR;
            $localGzipFilePath =  $localFolder. '/'
                . $file;

            // download file if it is a new file
            if (!file_exists($localGzipFilePath)) {

                $relativeFolder = $this->handle . '/' . self::FOLDER_OFFERS_TOUR;

                // download file
                $createdForTest = false;
                if ($this->handle === self::TEST_HANDLE) {
                    // create file
                    // create gz

                    // Name of the file we're compressing
                    $fileTxt = "5DSR-20231025_0530-INFX2";
                    if (!is_dir($localFolder)) {
                        mkdir($localFolder, 0755, true);
                    }
                    
                    file_put_contents($localFolder . '/' . $fileTxt, $this->getDummyDataTourOffers());
                    // Open the gz file (w9 is the highest compression)
                    $fp = gzopen ($localGzipFilePath, 'w9');
                    // Compress the file
                    gzwrite ($fp, file_get_contents($localFolder . '/' . $fileTxt));
                    gzclose($fp);
                    // remove test file
                    unlink($localFolder . '/' . $fileTxt);

                    $createdForTest = true;
                } else {
                    $ftpClient = FtpsClient::create();
                    $url = $url . '/' . $serverDir . '/' . $file;
                    $response = $ftpClient->request($url, $localFolder, $file, $username, $password);
                }

                if ($createdForTest || $response->fileDownloaded()) {

                    // after download, remove old gzip and uncompressed file
                    // after split, remove split files

                    $dir = $downloadsPath . '/' . $this->handle . '/' . self::FOLDER_OFFERS_TOUR . '/uncompressed';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $localFileName = str_replace('.gz', '', $file);
                    $localFilePath = $dir . '/' . $localFileName;
                    $this->unzipGzip($localGzipFilePath, $localFilePath);

                    // remove previous gzip and uncompressed files

                    $gzipFiles = glob($downloadsPath . '/' . $relativeFolder . "/*");
                    foreach ($gzipFiles as $gzfile) {
                        if (is_file($gzfile) && $gzfile !== $localGzipFilePath) {
                            unlink($gzfile);
                        }
                    }

                    $uncomprFiles = glob($dir . '/*');
                    foreach ($uncomprFiles as $ucfile) {
                        if (is_file($ucfile) && $ucfile !== $localFilePath) {
                            unlink($ucfile);
                        }
                    }

                    $fileDate = substr($file, 5, 13);
                    $this->chunkOffers($localFilePath, $fileDate, self::FOLDER_OFFERS_TOUR);
                    Log::debug($this->handle . ': tour offers downloaded');
                    $offersDownloaded = true;
                    
                } else {
                    Log::warning($this->handle . ': tour offers file cannot be downloaded');
                }
            } else {
                $offersDownloaded = true;
            }
        }
        return $offersDownloaded;
    }

    private function chunkOffers(string $localFilePath, string $fileDate, string $type): void
    {
        $hotels = $this->apiGetHotels();
        $handle = fopen($localFilePath, 'r');

        $folder = $type . '/' . $fileDate;
        $dir = Utils::getCachePath() . '/' . $this->handle . '/' . $folder;

        while (($row = fgets($handle)) !== false) {

            $array['checkin'] = substr($row, 33, 10);
            $checkInDateTime = new DateTimeImmutable($array['checkin']);
            // $array['version'] = substr($row, 0, 2);
            // $array['action'] = substr($row, 2, 1);
            // $array['brand'] = trim(substr($row, 3, 5));
            $array['nights'] = trim(substr($row, 43, 10));
            $array['departureAirport'] = substr($row, 53, 3);
            $array['arrivalAirport'] = substr($row, 56, 3);
            // $array['returnDepartureAirport'] = substr($row, 59, 3);
            // $array['returnArrivalAirport'] = substr($row, 62, 3);
            // $array['flightAirline'] = trim(substr($row, 65, 3));
            // $array['returnFlightAirline'] = trim(substr($row, 68, 3));
            // $array['departureTime'] = substr($row, 77, 4);
            // $array['arrivalTime'] = substr($row, 82, 4);
            // $array['returnDepartureTime'] = substr($row, 86, 4);
            // $array['returnArrivalTime'] = substr($row, 91, 4);
            // $array['flightNumber'] = trim(substr($row, 95, 4));
            // $array['returnFlightNumber'] = trim(substr($row, 99, 4));
            $array['adults'] = substr($row, 103, 1);
            $array['children'] = substr($row, 104, 1);
            // $array['minPersonCountInRoom'] = substr($row, 105, 1);
            // $array['maxPersonCountInRoom'] = substr($row, 106, 1);
            // $array['minAdultsCountInRoom'] = substr($row, 107, 1);
            // $array['maxAdultsCountInRoom'] = substr($row, 108, 1);
            // $array['currency'] = substr($row, 109, 3);
            // $array['pricePerAdult'] = trim(substr($row, 112, 12));
            // $array['infantPrice'] = trim(substr($row, 136, 12));

            // $array['resortName'] = trim(substr($row, 187, 24));
            // $array['hotelName'] = trim(substr($row, 212, 25));
            $array['hotelCode'] = trim(substr($row, 377, 8));
            // $array['stars'] = substr($row, 237, 3);
            // $array['mainRoomType'] = substr($row, 240, 2);
            // $array['roomSpecification'] = trim(substr($row, 242, 25));
            // $array['boardCode'] = substr($row, 267, 2);
            // $array['boardTypeName'] = trim(substr($row, 269, 25));
            // $array['toCode'] = substr($row, 321, 4);

            // $array['flightCode'] = trim(substr($row, 343, 17));
            // $array['returnFlightCode'] = trim(substr($row, 360, 17));
            // $array['hotelCodeAndOffset'] = trim(substr($row, 377, 17));
            // $array['flightOffset'] = trim(substr($row, 411, 2));
            // $array['returnFlightOffset'] = trim(substr($row, 417, 6));

            // $array['roomAndBoardCodes'] = substr($row, 423, 6);
            // $array['pricePerNextAdults'] = trim(substr($row, 435, 80));
            // $array['childrenPrices'] = trim(substr($row, 515, 80));
            // $array['groupCode'] = trim(substr($row, 595, 80));
            // $array['apiHkey'] = substr($row, 675, 80);
            // $array['childrenPricesContinuation'] = trim(substr($row, 825, 160));
            // $array['adultDiscounts'] = trim(substr($row, 985, 80));
            // $array['childDiscounts'] = trim(substr($row, 1065, 160));

            $hotel = $hotels->get($array['hotelCode']);

            if ($hotel === null) {
                continue;
            }

            // create folders according to groups

            if ($type === self::FOLDER_OFFERS_CHARTER || $type === self::FOLDER_OFFERS_TOUR) {
                if (
                    empty($array['checkin'])
                    || empty($array['nights'])
                    || empty($array['adults'])
                    || trim($array['children']) === ''
                    || empty($array['departureAirport'])
                ) {
                    Log::warning($this->handle . ': The folders for this charter or tour offer cannot be created! Data: ' . json_encode($array));
                    continue;
                }

                $offsetArrivalToAccom = (int) trim(substr($row, 385, 2));
                $departureArrivalOffset = (int) trim(substr($row, 411, 2));
                $totalOffset = $offsetArrivalToAccom + $departureArrivalOffset;

                $checkIn = $checkInDateTime->modify('-' . $totalOffset . ' days' )->format('Y-m-d');

                $dirOffers = $dir . '/'
                    . $array['departureAirport'] . '/'
                    . $hotel->Address->City->Id . '/'
                    . $checkIn . '/'
                    . ($array['nights'] + $totalOffset). '/'
                    . $array['adults'] . '/'
                    . $array['children'];
            } else {
                if (
                    empty($array['checkin'])
                    || empty($array['nights'])
                    || empty($array['adults'])
                    || trim($array['children']) === ''
                ) {
                    Log::warning($this->handle . ': The folders for this hotel offer cannot be created! Data: ' . json_encode($array));
                    continue;
                }

                $dirOffers = $dir . '/'
                    . $hotel->Address->City->Id . '/'
                    . $checkInDateTime->format('Y-m-d') . '/'
                    . $array['nights'] . '/'
                    . $array['adults'] . '/'
                    . $array['children'];
            }

            if (!is_dir($dirOffers)) {
                mkdir($dirOffers, 0755, true);
            }

            file_put_contents($dirOffers . '/' . 'offers', $row, FILE_APPEND);
        }
        fclose($handle);

        // remove all folders from cached offers except current
        $dirOffers = Utils::getCachePath() . '/' . $this->handle . '/' . $type;
        $offerFolders = glob($dirOffers . "/*");
        foreach ($offerFolders as $folder) {
            $currentDir = $dirOffers . '/' . $fileDate;
            if (is_dir($folder) && $folder !== $currentDir) {
                Utils::deleteDirectory($folder);
            }
        }
    }

    public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        if ($filter->serviceTypes->get(0) === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            return $this->getCharterOffers($filter);
        } elseif ($filter->serviceTypes->get(0) === AvailabilityFilter::SERVICE_TYPE_HOTEL) {
            return $this->getHotelOffers($filter);
        } else {
            return $this->getTourOffers($filter);
        }
    }

    private function getTourOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        DertourValidator::make()->validateTourOffersFilter($filter);

        $roomMap = $this->getHotelRoomMapping();

        $availabilityCollection = new AvailabilityCollection();
        if ($filter->bigFile) {
            $folderOffers = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_TOUR . '/uncompressed/*';
        } else {
            $folderOffers = Utils::getCachePath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_TOUR . '/*';
        }

        $cities = $this->apiGetCities();
        
        $folders = glob($folderOffers);
        if (isset($folders[0])) {
            $checkIn = $filter->checkIn;
            $nights = $filter->days;
            $adults = (int) $filter->rooms->first()->adults;
            $childrenAges = $filter->rooms->first()->childrenAges;
            $departureCity = $filter->departureCity ?: $filter->departureCityId;
            $cityId = $filter->cityId;
            $hotelCodeFilter = $filter->hotelId;

            $folder = $folders[0];

            $children = 0;
            $infants = 0;
            if (!empty($childrenAges)) {
                foreach ($childrenAges as $age) {
                    if ($age >= 2) {
                        $children++;
                    } else {
                        $infants++;
                    }
                }
            }

            if ($filter->bigFile) {
                $file = $folders[0];
                $hotels = $this->apiGetHotels();
            } else {
                $file = $folder . '/' . $departureCity . '/' . $cityId . '/' . $checkIn . '/' . $nights . '/' . $adults . '/' . $children . '/offers';
                if (is_file($file)) {
                    $this->showRequest('local file :' .$file, '', [], file_get_contents($file), 0);
                }
            }

            if (is_file($file)) {
                
                $handle = fopen($file, 'r');
                while (($row = fgets($handle)) !== false) {

                    $array['hotelCode'] = trim(substr($row, 377, 8));
                    
                    if (!empty($hotelCodeFilter)) {
                        if ($array['hotelCode'] !== $hotelCodeFilter) {
                            continue;
                        }
                    }

                    $array['infantPrice'] = trim(substr($row, 136, 12));
                    // if infaints > 0, take only with infant price
                    if ($infants > 0) {
                        // todo: testat
                        if ($array['infantPrice'] === '') {
                            continue;
                        }
                    }

                    $array['checkin'] = (new DateTime(substr($row, 33, 10)))->format('Y-m-d');
                    $array['nights'] = trim(substr($row, 43, 10));
                    $array['departureAirport'] = substr($row, 53, 3);
                    $array['arrivalAirport'] = substr($row, 56, 3);
                    $array['returnDepartureAirport'] = substr($row, 59, 3);
                    $array['returnArrivalAirport'] = substr($row, 62, 3);
                    $array['departureTime'] = substr($row, 77, 4);
                    $array['arrivalTime'] = substr($row, 82, 4);
                    $array['returnDepartureTime'] = substr($row, 86, 4);
                    $array['returnArrivalTime'] = substr($row, 91, 4);
                    // $array['flightNumber'] = trim(substr($row, 95, 4));
                    // $array['returnFlightNumber'] = trim(substr($row, 99, 4));
                    $array['adults'] = substr($row, 103, 1);
                    $array['children'] = substr($row, 104, 1);
                    // $array['minPersonCountInRoom'] = substr($row, 105, 1);
                    // $array['maxPersonCountInRoom'] = substr($row, 106, 1);
                    // $array['minAdultsCountInRoom'] = substr($row, 107, 1);
                    // $array['maxAdultsCountInRoom'] = substr($row, 108, 1);
                    $array['currency'] = substr($row, 109, 3);
                    $array['pricePerAdult'] = trim(substr($row, 112, 12));

                    // $array['resortName'] = trim(substr($row, 187, 24));
                    // $array['hotelName'] = trim(substr($row, 212, 25));
                    // $array['stars'] = substr($row, 237, 3);
                    $array['mainRoomType'] = substr($row, 240, 2);
                    $array['roomSpecification'] = trim(substr($row, 242, 25));
                    
                    $array['boardCode'] = substr($row, 267, 2);
                    $array['boardTypeName'] = trim(substr($row, 269, 25));
                    // $array['toCode'] = substr($row, 321, 4);

                    // $array['flightCode'] = trim(substr($row, 343, 17));
                    // $array['returnFlightCode'] = trim(substr($row, 360, 17));
                    // $array['hotelCodeAndOffset'] = trim(substr($row, 377, 17));
                    
                    // $array['departureOffset'] = trim(substr($row, 385, 2));
                    // $array['flightOffset'] = trim(substr($row, 411, 2));
                    $array['returnFlightOffset'] = trim(substr($row, 417, 6));

                    // $array['roomAndBoardCodes'] = substr($row, 423, 6);
                    // $array['pricePerNextAdults'] = trim(substr($row, 435, 80));
                    $array['childrenPrices'] = trim(substr($row, 515, 80)) . trim(substr($row, 825, 160));
                    // $array['groupCode'] = trim(substr($row, 595, 80));
                    $array['apiHkey'] = trim(substr($row, 675, 120));
                    $array['adultDiscounts'] = trim(substr($row, 985, 80));
                    $array['childDiscounts'] = trim(substr($row, 1065, 160));

                    $accomodationDateTime = new DateTimeImmutable(substr($row, 33, 10));
                    $offerReturnDateTime = $accomodationDateTime->modify("{$array['nights']} days");

                    if ($filter->bigFile) {
                        $hotelCity = $hotels->get($array['hotelCode']);

                        if ($filter->departureCity !== $array['departureAirport']) {
                            continue;
                        }
                        
                        if ($hotelCity === null || $filter->cityId !== $hotelCity->Address->City->Id) {
                            continue;
                        }
                        if ($filter->checkIn !== $array['checkin']) {
                            continue;
                        }
                        if ($filter->days !=  $array['nights']) {
                            continue;
                        }
                        if ($adults != $array['adults']) {
                            continue;
                        }
                        if ($children != $array['children']) {
                            continue;
                        }
                        $this->showRequest('local file :' .$file, '', [], $row, 0);
                    }

                    //$onlinePrice = $this->getOnlinePrice($adults, $children, $childrenAges, $offerReturnDateTime, $array['apiHkey']);

                    $hotelCode = $array['hotelCode'];

                    $offers = new OfferCollection();

                    $offer = new Offer();
                    $offer->InitialData = $array['apiHkey'];

                    $currency = new Currency();
                    $currency->Code = $array['currency'];

                    if ($infants > 1) {
                        $offer->Availability = Offer::AVAILABILITY_ASK;
                    } else {
                        $offer->Availability = Offer::AVAILABILITY_YES;
                    }

                    $roomId = $array['mainRoomType'];

                    $mealCode = $array['boardCode'];

                    $infantPrice = 0;
                    if (!empty($childrenAges)) {
                        foreach ($childrenAges as $age) {
                            if ($age < 2) {
                                $infantPrice += (float) $array['infantPrice'];
                            }
                        }
                    }

                    $pricePerAdult = (float) $array['pricePerAdult'];

                    $childrenPrice = 0;
                    $childrenDiscount = 0;
                    $adultsDiscount = 0;

                    if (!empty($childrenAges)) {
                        foreach ($childrenAges as $age) {
                            if ($age >= 2) {
                                $price = $this->getChildPrice($children, $age, $array['childrenPrices']);
                                if ($price === null) {
                                    $childrenPrice += $pricePerAdult;
                                } else {
                                    $childrenPrice += $price;
                                }

                                $discount = $this->getChildPrice($children, $age, $array['childDiscounts']);
                                if ($discount !== null) {
                                    $childrenDiscount += $discount;
                                }
                            }
                        }
                    }

                    $price = (int) $array['adults'] * $pricePerAdult + $childrenPrice + $infantPrice;

                    $adultsDiscount = $this->getAdultsDiscount($adults, $array['adultDiscounts']);

                    $initialPrice = $price + $adultsDiscount + $childrenDiscount;

                    $offer->Code = $hotelCode . '~'
                        . $roomId . '~'
                        . $mealCode . '~'
                        . $array['checkin'] . '~'
                        . $filter->days . '~'
                        . $price . '~'
                        . $filter->rooms->get(0)->adults
                        . (count($filter->rooms->first()->childrenAges) > 0 ? '~' . implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '');

                    $offsetArrivalToAccom = trim(substr($row, 385, 2));
                    $arrivalDateTime = $accomodationDateTime->modify($offsetArrivalToAccom . ' days');
                    $departureArrivalOffset = trim(substr($row, 411, 2));
                    $totalOffset = $offsetArrivalToAccom + $departureArrivalOffset;
                    $departureDateTime = $arrivalDateTime->modify('-' . $totalOffset . ' days');
                    $departureDateStr = $departureDateTime->format('Y-m-d');

                    $offer->CheckIn = $departureDateStr;

                    $offer->Currency = $currency;

                    $offer->Days = $array['nights'];

                    $taxes = 0;

                    $offer->Net = $price;
                    $offer->Gross = $price;
                    $offer->InitialPrice = $initialPrice;
                    $offer->Comission = $taxes;

                    $offerReturnArrivalDateTime = $offerReturnDateTime->modify("{$array['returnFlightOffset']} days");

                    // Rooms
                    $room1 = new Room();
                    $room1->Id = $roomId;
                    $room1->CheckinBefore = $offerReturnDateTime->format('Y-m-d');
                    $room1->CheckinAfter = $accomodationDateTime->format('Y-m-d');

                    $room1->Currency = $offer->Currency;
                    $room1->Quantity = 1;
                    $room1->Availability = $offer->Availability;

                    $roomTitle = '';
                    $roomTitleParts = explode('|', $array['roomSpecification']);

                    foreach ($roomTitleParts as $part) {
                        if ($part !== '-') {
                            $roomTitle .= ($roomMap[$part] ?? '') . ' ';
                        }
                    }

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;
                    $merch->Title = $roomTitle;

                    $merchType = new RoomMerchType();
                    $merchType->Id = $roomId;
                    $merchType->Title = $merch->Title;
                    $merch->Type = $merchType;
                    $merch->Code = $merch->Id;
                    $merch->Name = $merch->Title;

                    $room1->Merch = $merch;

                    $offer->Rooms = new RoomCollection([$room1]);

                    $offer->Item = $room1;

                    $mealItem = new MealItem();

                    $boardTypeName = $array['boardTypeName'];

                    // MealItem Merch
                    $boardMerch = new MealMerch();
                    $boardMerch->Title = $boardTypeName;

                    $boardMerchType = new MealMerchType();
                    $boardMerchType->Id = $mealCode;
                    $boardMerchType->Title = $boardTypeName;
                    $boardMerch->Type = $boardMerchType;

                    // MealItem
                    $mealItem->Merch = $boardMerch;
                    $mealItem->Currency = $offer->Currency;

                    $offer->MealItem = $mealItem;

                    $departureDateTime = DateTimeImmutable::createFromFormat('Y-m-d Hi', $departureDateStr . ' ' . $array['departureTime']);
                    $arrivalDateTime = DateTimeImmutable::createFromFormat('Y-m-d Hi', $arrivalDateTime->format('Y-m-d') . ' ' . $array['arrivalTime']);
                    $returnDepartureDateTime = DateTimeImmutable::createFromFormat('Y-m-d Hi', $offerReturnDateTime->format('Y-m-d') . ' ' . $array['returnDepartureTime']);
                    $returnArrivalDateTime =  DateTimeImmutable::createFromFormat('Y-m-d Hi', $offerReturnArrivalDateTime->format('Y-m-d') . ' ' . $array['returnArrivalTime']);

                    $departureFlightDate = $departureDateTime->format('Y-m-d H:i');
                    $departureFlightDateArrival = $arrivalDateTime->format('Y-m-d H:i');

                    $returnFlightDate = $returnDepartureDateTime->format('Y-m-d H:i');
                    $returnFlightDateArrival = $returnArrivalDateTime->format('Y-m-d H:i');

                    // departure transport item merch
                    $departureTransportMerch = new TransportMerch();
                    $departureTransportMerch->Title = "Dus: " . $departureDateTime->format('d.m.Y');
                    $departureTransportMerch->Category = new TransportMerchCategory();
                    $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                    $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $departureTransportMerch->DepartureTime = $departureFlightDate;
                    $departureTransportMerch->ArrivalTime = $departureFlightDateArrival;

                    $departureTransportMerch->DepartureAirport = $array['departureAirport'];
                    $departureTransportMerch->ReturnAirport = $array['arrivalAirport'];

                    $departureCity = $cities->get($array['departureAirport']);
                    if ($departureCity === null) {
                        Log::warning($this->handle . ': ' . $array['departureAirport'] . ' is not in cities list, please add.');
                        continue;
                    }

                    $departureTransportMerch->From = new TransportMerchLocation();
                    $departureTransportMerch->From->City = $departureCity;

                    $arrivalCity = $cities->get($array['arrivalAirport']);

                    $departureTransportMerch->To = new TransportMerchLocation();
                    $departureTransportMerch->To->City = $arrivalCity;

                    $departureTransportItem = new DepartureTransportItem();
                    $departureTransportItem->Merch = $departureTransportMerch;
                    $departureTransportItem->Currency = $offer->Currency;
                    $departureTransportItem->DepartureDate = $departureDateTime->format('Y-m-d');
                    $departureTransportItem->ArrivalDate = $arrivalDateTime->format('Y-m-d');

                    // return transport item
                    $returnTransportMerch = new TransportMerch();
                    $returnTransportMerch->Title = "Retur: " . $returnDepartureDateTime->format('d.m.Y');
                    $returnTransportMerch->Category = new TransportMerchCategory();
                    $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                    $returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $returnTransportMerch->DepartureTime = $returnFlightDate;
                    $returnTransportMerch->ArrivalTime = $returnFlightDateArrival;

                    $returnTransportMerch->DepartureAirport = $array['returnDepartureAirport'];
                    $returnTransportMerch->ReturnAirport = $array['returnArrivalAirport'];

                    $returnDepartureCity = $cities->get($array['returnDepartureAirport']);
                    $returnArrivalCity = $cities->get($array['returnArrivalAirport']);

                    $returnTransportMerch->From = new TransportMerchLocation();
                    $returnTransportMerch->From->City = $returnDepartureCity;

                    $returnTransportMerch->To = new TransportMerchLocation();
                    $returnTransportMerch->To->City = $returnArrivalCity;

                    $returnTransportItem = new ReturnTransportItem();
                    $returnTransportItem->Merch = $returnTransportMerch;
                    $returnTransportItem->Currency = $offer->Currency;
                    $returnTransportItem->DepartureDate = $returnDepartureDateTime->format('Y-m-d');
                    $returnTransportItem->ArrivalDate = $returnArrivalDateTime->format('Y-m-d');

                    $departureTransportItem->Return = $returnTransportItem;

                    // add items to offer
                    $offer->Item = $room1;
                    $offer->DepartureTransportItem = $departureTransportItem;
                    $offer->ReturnTransportItem = $returnTransportItem;

                    $offer->Items = [];

                    if ($this->handle === self::TEST_HANDLE) {
                        $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory, '');
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory, '');
                    } else {
                        $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                    }

                    $existingAvailability = $availabilityCollection->get($array['hotelCode']);
                    if ($existingAvailability === null) {
                        // creating new availability
                        $availability = new Availability();
                        $availability->Id = $array['hotelCode'];

                        $offers = new OfferCollection();
                        $offers->put($offer->Code, $offer);
                        $availability->Offers = $offers;
                    } else {
                        // adding offers to the existing availability
                        $availability = $existingAvailability;
                        $availability->Offers->put($offer->Code, $offer);
                    }

                    $availabilityCollection->put($availability->Id, $availability);
                }
                fclose($handle);
            }
        } else {
            throw new Exception($this->handle . ': tour offers folder does not exist');
        }

        return $availabilityCollection;
    }

    private function getCharterOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        if (!$filter->bigFile) {
            DertourValidator::make()->validateCharterOffersFilter($filter);
        }

        $roomMap = $this->getHotelRoomMapping();

        $availabilityCollection = new AvailabilityCollection();

        if ($filter->bigFile) {
            $folderOffers = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_CHARTER . '/uncompressed/*';
        } else {
            $folderOffers = Utils::getCachePath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_CHARTER . '/*';
        }
 
        $cities = $this->apiGetCities();
        
        $folders = glob($folderOffers);
        if (isset($folders[0])) {
            $checkIn = $filter->checkIn;
            $nights = $filter->days;
            $adults = (int) $filter->rooms->first()->adults;
            $childrenAges = $filter->rooms->first()->childrenAges;
            $departureCity = $filter->departureCity;
            $cityId = $filter->cityId;
            $hotelCodeFilter = $filter->hotelId;

            $folder = $folders[0];

            $children = 0;
            $infants = 0;
            if (!empty($childrenAges)) {
                foreach ($childrenAges as $age) {
                    if ($age >= 2) {
                        $children++;
                    } else {
                        $infants++;
                    }
                }
            }

            if ($filter->bigFile) {
                $file = $folders[0];
                $hotels = $this->apiGetHotels();
            } else {
                $file = $folder . '/' . $departureCity . '/' . $cityId . '/' . $checkIn . '/' . $nights . '/' . $adults . '/' . $children . '/offers';
                if (is_file($file)) {
                    $this->showRequest('local file :' .$file, '', [], file_get_contents($file), 0);
                }
            }//dump($file);

            if (is_file($file)) {
                
                $handle = fopen($file, 'r');
                while (($row = fgets($handle)) !== false) {
                    $array['hotelCode'] = trim(substr($row, 377, 8));
                    
                    if (!empty($hotelCodeFilter)) {
                        if ($array['hotelCode'] !== $hotelCodeFilter) {
                            continue;
                        }
                    }

                    $array['infantPrice'] = trim(substr($row, 136, 12));

                    // if infants > 0, take only with infant price
                    if ($infants > 0) {
                        // todo: testat
                        if ($array['infantPrice'] === '') {
                            continue;
                        }
                    }

                    $array['checkin'] = (new DateTime(substr($row, 33, 10)))->format('Y-m-d');
                    $array['nights'] = trim(substr($row, 43, 10));
                    $array['departureAirport'] = substr($row, 53, 3);
                    $array['arrivalAirport'] = substr($row, 56, 3);
                    $array['returnDepartureAirport'] = substr($row, 59, 3);
                    $array['returnArrivalAirport'] = substr($row, 62, 3);
                    $array['departureTime'] = substr($row, 77, 4);
                    $array['arrivalTime'] = substr($row, 82, 4);
                    $array['returnDepartureTime'] = substr($row, 86, 4);
                    $array['returnArrivalTime'] = substr($row, 91, 4);
                    // $array['flightNumber'] = trim(substr($row, 95, 4));
                    // $array['returnFlightNumber'] = trim(substr($row, 99, 4));
                    $array['adults'] = substr($row, 103, 1);
                    $array['children'] = substr($row, 104, 1);
                    // $array['minPersonCountInRoom'] = substr($row, 105, 1);
                    // $array['maxPersonCountInRoom'] = substr($row, 106, 1);
                    // $array['minAdultsCountInRoom'] = substr($row, 107, 1);
                    // $array['maxAdultsCountInRoom'] = substr($row, 108, 1);
                    $array['currency'] = substr($row, 109, 3);
                    $array['pricePerAdult'] = trim(substr($row, 112, 12));

                    // $array['resortName'] = trim(substr($row, 187, 24));
                    // $array['hotelName'] = trim(substr($row, 212, 25));
                    // $array['stars'] = substr($row, 237, 3);
                    $array['mainRoomType'] = substr($row, 240, 2);
                    $array['roomSpecification'] = trim(substr($row, 242, 25));
                    
                    $array['boardCode'] = substr($row, 267, 2);
                    $array['boardTypeName'] = trim(substr($row, 269, 25));
                    // $array['toCode'] = substr($row, 321, 4);

                    // $array['flightCode'] = trim(substr($row, 343, 17));
                    // $array['returnFlightCode'] = trim(substr($row, 360, 17));
                    // $array['hotelCodeAndOffset'] = trim(substr($row, 377, 17));
                    
                    // $array['departureOffset'] = trim(substr($row, 385, 2));
                    // $array['flightOffset'] = trim(substr($row, 411, 2));
                    $array['returnFlightOffset'] = trim(substr($row, 417, 6));

                    // $array['roomAndBoardCodes'] = substr($row, 423, 6);
                    // $array['pricePerNextAdults'] = trim(substr($row, 435, 80));
                    $array['childrenPrices'] = trim(substr($row, 515, 80)) . trim(substr($row, 825, 160));
                    // $array['groupCode'] = trim(substr($row, 595, 80));
                    $array['apiHkey'] = trim(substr($row, 675, 120));
                    $array['adultDiscounts'] = trim(substr($row, 985, 80));
                    $array['childDiscounts'] = trim(substr($row, 1065, 160));
                    $array['flightOnRequest'] = substr($row, 1225, 1);
                    $array['outboundFlightOnRequest'] = substr($row, 1226, 1);
                    $array['accommodationOnRequest'] = substr($row, 1227, 1);

                    $accomodationDateTime = new DateTimeImmutable(substr($row, 33, 10));
                    $offerReturnDateTime = $accomodationDateTime->modify("{$array['nights']} days");

                    if ($filter->bigFile) {
                        // if ($infants > 0) {
                        //     dump($array);die;
                        // }
                        $hotelCity = $hotels->get($array['hotelCode']);

                        if ($filter->departureCity !== $array['departureAirport']) {
                            continue;
                        }
                        
                        if ($hotelCity === null || $filter->cityId !== $hotelCity->Address->City->Id) {
                            continue;
                        }

                        if ($filter->checkIn !== $array['checkin']) {
                            continue;
                        }

                        if ($filter->days !=  $array['nights']) {
                            continue;
                        }
                        
                        if ($adults != $array['adults']) {
                            continue;
                        }

                        if ($children != $array['children']) {
                            continue;
                        }

                        $this->showRequest('local file :' .$file, '', [], $row, 0);
                    }

                    //$onlinePrice = $this->getOnlinePrice($adults, $children, $childrenAges, $offerReturnDateTime, $array['apiHkey']);

                    $hotelCode = $array['hotelCode'];

                    $offers = new OfferCollection();

                    $offer = new Offer();
                    $offer->InitialData = $array['apiHkey'];

                    $currency = new Currency();
                    $currency->Code = $array['currency'];

                    if ($infants > 1 || strtolower($array['accommodationOnRequest']) === 'y') {
                        $offer->Availability = Offer::AVAILABILITY_ASK;
                    } else {
                        $offer->Availability = Offer::AVAILABILITY_YES;
                    }

                    if (strtolower($array['flightOnRequest']) === 'y' || strtolower($array['outboundFlightOnRequest']) === 'y') {
                        $offer->Availability = Offer::AVAILABILITY_NO;
                    }

                    $roomId = $array['mainRoomType'];

                    $mealCode = $array['boardCode'];

                    $infantPrice = 0;
                    if (!empty($childrenAges)) {
                        foreach ($childrenAges as $age) {
                            if ($age < 2) {
                                $infantPrice += (float) $array['infantPrice'];
                            }
                        }
                    }

                    $pricePerAdult = (float) $array['pricePerAdult'];

                    $childrenPrice = 0;
                    $childrenDiscount = 0;
                    $adultsDiscount = 0;

                    if (!empty($childrenAges)) {
                        foreach ($childrenAges as $age) {
                            if ($age >= 2) {
                                $price = $this->getChildPrice($children, $age, $array['childrenPrices']);
                                if ($price === null) {
                                    $childrenPrice += $pricePerAdult;
                                } else {
                                    $childrenPrice += $price;
                                }

                                $discount = $this->getChildPrice($children, $age, $array['childDiscounts']);
                                if ($discount !== null) {
                                    $childrenDiscount += $discount;
                                }
                            }
                        }
                    }

                    $price = (int) $array['adults'] * $pricePerAdult + $childrenPrice + $infantPrice;

                    $adultsDiscount = $this->getAdultsDiscount($adults, $array['adultDiscounts']);

                    $initialPrice = $price + $adultsDiscount + $childrenDiscount;

                    $offer->Code = $hotelCode . '~'
                        . $roomId . '~'
                        . $mealCode . '~'
                        . $array['checkin'] . '~'
                        . $filter->days . '~'
                        . $price . '~'
                        . $filter->rooms->get(0)->adults
                        . (count($filter->rooms->first()->childrenAges) > 0 ? '~' . implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '');

                    $offsetArrivalToAccom = trim(substr($row, 385, 2));
                    $arrivalDateTime = $accomodationDateTime->modify($offsetArrivalToAccom . ' days');
                    $departureArrivalOffset = trim(substr($row, 411, 2));
                    $totalOffset = $offsetArrivalToAccom + $departureArrivalOffset;
                    $departureDateTime = $arrivalDateTime->modify('-' . $totalOffset . ' days');
                    $departureDateStr = $departureDateTime->format('Y-m-d');

                    $offer->CheckIn = $departureDateStr;

                    $offer->Currency = $currency;

                    $offer->Days = $array['nights'];

                    $taxes = 0;

                    $offer->Net = $price;
                    $offer->Gross = $price;
                    $offer->InitialPrice = $initialPrice;
                    $offer->Comission = $taxes;

                    $offerReturnArrivalDateTime = $offerReturnDateTime->modify("{$array['returnFlightOffset']} days");

                    // Rooms
                    $room1 = new Room();
                    $room1->Id = $roomId;
                    $room1->CheckinBefore = $offerReturnDateTime->format('Y-m-d');
                    $room1->CheckinAfter = $accomodationDateTime->format('Y-m-d');

                    $room1->Currency = $offer->Currency;
                    $room1->Quantity = 1;
                    $room1->Availability = $offer->Availability;

                    $roomTitle = '';
                    $roomTitleParts = explode('|', $array['roomSpecification']);

                    foreach ($roomTitleParts as $part) {
                        if ($part !== '-') {
                            $roomTitle .= ($roomMap[$part] ?? '') . ' ';
                        }
                    }

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;
                    $merch->Title = $roomTitle;

                    $merchType = new RoomMerchType();
                    $merchType->Id = $roomId;
                    $merchType->Title = $merch->Title;
                    $merch->Type = $merchType;
                    $merch->Code = $merch->Id;
                    $merch->Name = $merch->Title;

                    $room1->Merch = $merch;

                    $offer->Rooms = new RoomCollection([$room1]);

                    $offer->Item = $room1;

                    $mealItem = new MealItem();

                    $boardTypeName = $array['boardTypeName'];

                    // MealItem Merch
                    $boardMerch = new MealMerch();
                    $boardMerch->Title = $boardTypeName;

                    $boardMerchType = new MealMerchType();
                    $boardMerchType->Id = $mealCode;
                    $boardMerchType->Title = $boardTypeName;
                    $boardMerch->Type = $boardMerchType;

                    // MealItem
                    $mealItem->Merch = $boardMerch;
                    $mealItem->Currency = $offer->Currency;

                    $offer->MealItem = $mealItem;

                    $departureDateTime = DateTimeImmutable::createFromFormat('Y-m-d Hi', $departureDateStr . ' ' . $array['departureTime']);
                    $arrivalDateTime = DateTimeImmutable::createFromFormat('Y-m-d Hi', $arrivalDateTime->format('Y-m-d') . ' ' . $array['arrivalTime']);
                    $returnDepartureDateTime = DateTimeImmutable::createFromFormat('Y-m-d Hi', $offerReturnDateTime->format('Y-m-d') . ' ' . $array['returnDepartureTime']);
                    $returnArrivalDateTime =  DateTimeImmutable::createFromFormat('Y-m-d Hi', $offerReturnArrivalDateTime->format('Y-m-d') . ' ' . $array['returnArrivalTime']);

                    $departureFlightDate = $departureDateTime->format('Y-m-d H:i');
                    $departureFlightDateArrival = $arrivalDateTime->format('Y-m-d H:i');

                    $returnFlightDate = $returnDepartureDateTime->format('Y-m-d H:i');
                    $returnFlightDateArrival = $returnArrivalDateTime->format('Y-m-d H:i');

                    // departure transport item merch
                    $departureTransportMerch = new TransportMerch();
                    $departureTransportMerch->Title = "Dus: " . $departureDateTime->format('d.m.Y');
                    $departureTransportMerch->Category = new TransportMerchCategory();
                    $departureTransportMerch->Category->Code = TransportMerchCategory::CODE_OUTBOUND;
                    $departureTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $departureTransportMerch->DepartureTime = $departureFlightDate;
                    $departureTransportMerch->ArrivalTime = $departureFlightDateArrival;

                    $departureTransportMerch->DepartureAirport = $array['departureAirport'];
                    $departureTransportMerch->ReturnAirport = $array['arrivalAirport'];

                    $departureCity = $cities->get($array['departureAirport']);
                    if ($departureCity === null) {
                        Log::warning($this->handle . ': ' . $array['departureAirport'] . ' is not in cities list, please add.');
                        continue;
                    }

                    $departureTransportMerch->From = new TransportMerchLocation();
                    $departureTransportMerch->From->City = $departureCity;

                    $arrivalCity = $cities->get($array['arrivalAirport']);

                    $departureTransportMerch->To = new TransportMerchLocation();
                    $departureTransportMerch->To->City = $arrivalCity;

                    $departureTransportItem = new DepartureTransportItem();
                    $departureTransportItem->Merch = $departureTransportMerch;
                    $departureTransportItem->Currency = $offer->Currency;
                    $departureTransportItem->DepartureDate = $departureDateTime->format('Y-m-d');
                    $departureTransportItem->ArrivalDate = $arrivalDateTime->format('Y-m-d');

                    // return transport item
                    $returnTransportMerch = new TransportMerch();
                    $returnTransportMerch->Title = "Retur: " . $returnDepartureDateTime->format('d.m.Y');
                    $returnTransportMerch->Category = new TransportMerchCategory();
                    $returnTransportMerch->Category->Code = TransportMerchCategory::CODE_INBOUND;
                    $returnTransportMerch->TransportType = TransportMerch::TRANSPORT_TYPE_PLANE;
                    $returnTransportMerch->DepartureTime = $returnFlightDate;
                    $returnTransportMerch->ArrivalTime = $returnFlightDateArrival;

                    $returnTransportMerch->DepartureAirport = $array['returnDepartureAirport'];
                    $returnTransportMerch->ReturnAirport = $array['returnArrivalAirport'];

                    $returnDepartureCity = $cities->get($array['returnDepartureAirport']);
                    $returnArrivalCity = $cities->get($array['returnArrivalAirport']);

                    $returnTransportMerch->From = new TransportMerchLocation();
                    $returnTransportMerch->From->City = $returnDepartureCity;

                    $returnTransportMerch->To = new TransportMerchLocation();
                    $returnTransportMerch->To->City = $returnArrivalCity;

                    $returnTransportItem = new ReturnTransportItem();
                    $returnTransportItem->Merch = $returnTransportMerch;
                    $returnTransportItem->Currency = $offer->Currency;
                    $returnTransportItem->DepartureDate = $returnDepartureDateTime->format('Y-m-d');
                    $returnTransportItem->ArrivalDate = $returnArrivalDateTime->format('Y-m-d');

                    $departureTransportItem->Return = $returnTransportItem;

                    // add items to offer
                    $offer->Item = $room1;
                    $offer->DepartureTransportItem = $departureTransportItem;
                    $offer->ReturnTransportItem = $returnTransportItem;

                    $offer->Items = [];

                    $offer->Items[] = IntegrationFunctions::getApiTransferItem($offer, new TransferCategory);

                    if ($filter->transportTypes->first() === AvailabilityFilter::TRANSPORT_TYPE_PLANE) {
                        $offer->Items[] = IntegrationFunctions::getApiAirpotTaxesItem($offer, new AirportTaxesCategory);
                    }

                    $existingAvailability = $availabilityCollection->get($array['hotelCode']);
                    if ($existingAvailability === null) {
                        // creating new availability
                        $availability = new Availability();
                        $availability->Id = $array['hotelCode'];
                        $offers = new OfferCollection();
                        $offers->put($offer->Code, $offer);
                        $availability->Offers = $offers;
                    } else {
                        // adding offers to the existing availability
                        $availability = $existingAvailability;
                        $availability->Offers->put($offer->Code, $offer);
                    }

                    $availabilityCollection->put($availability->Id, $availability);
                }
                fclose($handle);
            }
        } else {
            throw new Exception($this->handle . ': charter offers folder does not exist');
        }
        return $availabilityCollection;
    }

    // todo: marit prima transa cu 10% si scazut diferenta din urmatoarea
    public function apiGetOfferCancelFeesPaymentsAvailabilityAndPrice(PaymentPlansFilter $filter): array
    {
        DertourValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateOfferPaymentPlansFilter($filter);

        $offFees = new OfferCancelFeeCollection();
        $offPayments = new OfferPaymentPolicyCollection();

        $adults = $filter->Rooms->first()->adults;
        $children = $filter->Rooms->first()->children;
        $childrenAges = $filter->Rooms->first()->childrenAges;
        $checkOut = new DateTimeImmutable($filter->CheckOut);
        $checkIn = new DateTimeImmutable($filter->CheckIn);
        $apiHKey = $filter->OriginalOffer->InitialData;

        $online = $this->getOnlinePrice($adults, $children, $childrenAges, $checkOut, $apiHKey);

        $offPrice = $online['priceCalculation']['totalPrice']['amount'];

        $currency = new Currency();
        $currency->Code = $filter->SuppliedCurrency;
        
        if (isset($online['payments'])) {
            
            $endDate = new DateTimeImmutable();
            $datePayAfterDT = new DateTimeImmutable();
            $today = new DateTimeImmutable();

            $price = 0;
            $diff = 0;
            for ($j = 0; $j < count($online['payments']); $j++) {
                
                $datePayAfter = $datePayAfterDT->format('Y-m-d');
                $policy = new OfferPaymentPolicy();
                $policy->Currency = $currency;

                $amount = $online['payments'][$j]['amount'];
                if (count($online['payments']) > 1) {
                    if ($j === 0) {
                        $diff = $amount * 0.1;
                        $amount = $amount + $diff;
                    } elseif ($j === 1) {
                        $amount = $amount - $diff;
                    }
                }

                $policy->Amount = $amount;
                
                $policy->PayAfter = $datePayAfter;
                $policy->PayUntil = (new DateTime($online['payments'][$j]['dueDate']))->modify('-2 day')->format('Y-m-d');
                $offPayments->add($policy);
                $datePayAfterDT = (new DateTimeImmutable($online['payments'][$j]['dueDate']))->modify('-1 day');

                $payEnd = new DateTimeImmutable();
                    
                if (isset($online['payments'][$j + 1])) {
                    $payEnd = (new DateTimeImmutable($online['payments'][$j + 1]['dueDate']))->modify('-2 day');
                } else {
                    $payEnd = $checkIn;
                }

                if ($j === 0) {
                    $payEndDateFirst = (new DateTimeImmutable($online['payments'][$j]['dueDate']))->modify('-2 day');
                    if ($today < $payEndDateFirst) {
                        $cancelPolicy = new OfferCancelFee();
                        $cancelPolicy->Price = 0;
                        $cancelPolicy->Currency = $currency;
                        $cancelPolicy->DateStart = $today->format('Y-m-d');
                        $cancelPolicy->DateEnd = $payEndDateFirst->format('Y-m-d');
                        $offFees->add($cancelPolicy);
                        $endDate = new DateTimeImmutable($cancelPolicy->DateEnd);
                    }
                }

                $payStart = new DateTimeImmutable();

                if ($endDate->modify('+1 day') >= $checkIn) {
                    $payStart = $checkIn;
                } else {
                    $payStart = $endDate->modify('+1 day');
                }

                if ($today >= $payEndDateFirst && $j === 0) {
                    $payStart = $today;
                    $payEnd = $today;
                }

                $price += $amount;

                $cancelPolicy = new OfferCancelFee();
                $cancelPolicy->Price = $price;
                $cancelPolicy->Currency = $currency;
                $cancelPolicy->DateStart = $payStart->format('Y-m-d');
                $cancelPolicy->DateEnd = $payEnd->format('Y-m-d');
                $offFees->add($cancelPolicy);
                $endDate = $payEnd;
            }
        }

        $availability = Offer::AVAILABILITY_NO;

        $avail = $online['availability'];

        if ($avail === 'AVAILABLE') {
            $availability = Offer::AVAILABILITY_YES;
        } elseif($avail === 'ON_REQUEST') {
            $availability = Offer::AVAILABILITY_ASK;
        }

        $offAvailability = $availability;
        $offInitialPrice = $offPrice;
        $offCurrency = $filter->SuppliedCurrency;

        $notesText = '';
        foreach ($online['notes'] ?? [] as $note) {
            $notesText .= strip_tags($note['text'], '<br>') . '<br>';
        }
        $notesText = rtrim($notesText, '<br>');

        return [$offFees, $offPayments, $offAvailability, $offPrice, $offInitialPrice, $offCurrency, null, $notesText];
    }

    private function getOnlinePrice(int $adults, int $children, ?StringCollection $childrenAges, DateTimeImmutable $offerReturnDateTime, string $apiHKey): array
    {
        $url = $this->apiUrl . '/auth/realms/beapi/protocol/openid-connect/token';

        // get token
        $options['body'] = "client_id={$this->username}&client_secret={$this->password}&grant_type=client_credentials";

        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);

        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getContent(false), $resp->getStatusCode());

        $bearer = json_decode($resp->getContent(), true)['access_token'];

        $passengerAssignments = [];
        $passengers = [];
        for ($i = 0; $i < $adults; $i++) {
            $passengerAssignments[$i] = ['passengerRefId' => $i + 1];
            $passengers[$i] = [
                'passengerRefId' => $i + 1,
                'passengerType' => 'ADULT',
                'birthDate' => '1990-01-01'
            ];
        }

        if (!empty($childrenAges)) {
            for ($c = 0; $c < (int) $children; $c++) {
                $age = $childrenAges->get($c);

                $birthDate = $offerReturnDateTime->modify("-$age years");
                $passengerAssignments[$c + $i] = ['passengerRefId' => $c + $i + 1];
                $type = $age < 2 ? 'INFANT' : 'CHILD';
                $passengers[$c + $i] = [
                    'passengerRefId' => $i + $c + 1,
                    'passengerType' => $type,
                    'birthDate' => $birthDate->format('Y-m-d')
                ];
            }
        }

        $options['body'] = json_encode([
            "rooms" => [
                [
                    "roomRefId" => 1,
                    "tourRoomId" =>  $apiHKey,
                    "passengerAssignments" => $passengerAssignments
                ]
            ],
            "passengers" => $passengers
        ]);
        
        $options['headers'] = [
            'Authorization' => 'Bearer ' . $bearer,
            'Content-Type' => 'application/json',
            'x-upe-agent-code' => $this->apiContext
        ];

        $url = $this->apiUrl . '/booking/v1/availability-check';
        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);

        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getContent(false), $resp->getStatusCode());

        $content = json_decode($resp->getContent(), true);

        return $content;
    }

    private function getHotelRoomMapping(): array
    {
        $file = $this->requestFile(self::DOWNLOAD_MASTER_DATA);

        $xml = file_get_contents($file);
        $xmlObj = new SimpleXMLElement($xml);

        $arr = [];

        foreach ($xmlObj->room_type->mt_rt as $roomType) {
            $arr[(string) $roomType->attributes()->code] = (string) $roomType->name[0];
        }
        foreach ($xmlObj->room_specification->mt_rs as $roomSpec) {
            $arr[(string) $roomSpec->attributes()->code] = (string) $roomSpec->name[0];
        }

        return $arr;
    }

    private function getHotelOffers(AvailabilityFilter $filter): AvailabilityCollection
    {
        DertourValidator::make()->validateIndividualOffersFilter($filter);

        $roomMap = $this->getHotelRoomMapping();
        $availabilityCollection = new AvailabilityCollection();

        if ($filter->bigFile) {
            $folderOffers = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_HOTEL . '/uncompressed/*';
        } else {
            $folderOffers = Utils::getCachePath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_HOTEL . '/*';
        }

        $folders = glob($folderOffers);
        if (isset($folders[0])) {
            $checkIn = $filter->checkIn;
            $nights = $filter->days;
            $adults = (int) $filter->rooms->first()->adults;
            $childrenAges = $filter->rooms->first()->childrenAges;
            $cityId = $filter->cityId;
            $hotelCodeFilter = $filter->hotelId;

            $children = 0;
            $infants = 0;

            if (!empty($childrenAges)) {
                foreach ($childrenAges as $age) {
                    if ($age >= 2) {
                        $children++;
                    } else {
                        $infants++;
                    }
                }
            }

            $folder = $folders[0];

            if ($filter->bigFile) {
                $file = $folders[0];
                $hotels = $this->apiGetHotels();
            } else {
                $file = $folder . '/' . $cityId  . '/' . $checkIn . '/' . $nights . '/' . $adults . '/' .$children . '/offers';
                if (is_file($file)) {
                    $this->showRequest('local file :' .$file, '', [], file_get_contents($file), 0);
                }
            }
            
            $offers = new OfferCollection();

            if (is_file($file)) {

                $handle = fopen($file, 'r');
                while (($row = fgets($handle)) !== false) {

                    $array['hotelCode'] = trim(substr($row, 377, 8));
                    
                    if (!empty($hotelCodeFilter)) {
                        if ($array['hotelCode'] !== $hotelCodeFilter) {
                            continue;
                        }
                    }

                    $array['infantPrice'] = trim(substr($row, 136, 12));
                    // if infaints > 0, take only with infant price
                    if ($infants > 0) {
                        // todo: testat
                        if ($array['infantPrice'] === '') {
                            continue;
                        }
                    }

                    $array['checkin'] = (new DateTime(substr($row, 33, 10)))->format('Y-m-d');
                    $array['nights'] = trim(substr($row, 43, 10));
                    // $array['departureAirport'] = substr($row, 53, 3);
                    // $array['arrivalAirport'] = substr($row, 56, 3);
                    // $array['returnDepartureAirport'] = substr($row, 59, 3);
                    // $array['returnArrivalAirport'] = substr($row, 62, 3);
                    // $array['departureTime'] = substr($row, 77, 4);
                    // $array['arrivalTime'] = substr($row, 82, 4);
                    // $array['returnDepartureTime'] = substr($row, 86, 4);
                    // $array['returnArrivalTime'] = substr($row, 91, 4);
                    // $array['flightNumber'] = trim(substr($row, 95, 4));
                    // $array['returnFlightNumber'] = trim(substr($row, 99, 4));
                    $array['adults'] = substr($row, 103, 1);
                    $array['children'] = substr($row, 104, 1);
                    // $array['minPersonCountInRoom'] = substr($row, 105, 1);
                    // $array['maxPersonCountInRoom'] = substr($row, 106, 1);
                    // $array['minAdultsCountInRoom'] = substr($row, 107, 1);
                    // $array['maxAdultsCountInRoom'] = substr($row, 108, 1);
                    $array['currency'] = substr($row, 109, 3);
                    $array['pricePerAdult'] = trim(substr($row, 112, 12));

                    // $array['resortName'] = trim(substr($row, 187, 24));
                    // $array['hotelName'] = trim(substr($row, 212, 25));
                    // $array['stars'] = substr($row, 237, 3);
                    $array['mainRoomType'] = substr($row, 240, 2);
                    $array['roomSpecification'] = trim(substr($row, 242, 25));
                    $array['boardCode'] = substr($row, 267, 2);
                    $array['boardTypeName'] = trim(substr($row, 269, 25));
                    // $array['toCode'] = substr($row, 321, 4);

                    // $array['flightCode'] = trim(substr($row, 343, 17));
                    // $array['returnFlightCode'] = trim(substr($row, 360, 17));
                    // $array['hotelCodeAndOffset'] = trim(substr($row, 377, 17));
                    // $array['departureOffset'] = trim(substr($row, 385, 2));
                    // $array['flightOffset'] = trim(substr($row, 411, 2));
                    // $array['returnFlightOffset'] = trim(substr($row, 417, 6));

                    $array['roomAndBoardCodes'] = substr($row, 423, 6);
                    // $array['pricePerNextAdults'] = trim(substr($row, 435, 80));
                    $array['childrenPrices'] = trim(substr($row, 515, 80)) . trim(substr($row, 825, 160));
                    // $array['groupCode'] = trim(substr($row, 595, 80));
                    $array['apiHkey'] = trim(substr($row, 675, 120));
                    $array['adultDiscounts'] = trim(substr($row, 985, 80));
                    $array['childDiscounts'] = trim(substr($row, 1065, 160));
                    $array['flightOnRequest'] = substr($row, 1225, 1);
                    $array['outboundFlightOnRequest'] = substr($row, 1226, 1);
                    $array['accommodationOnRequest'] = substr($row, 1227, 1);

                    $accomodationDateTime = new DateTimeImmutable(substr($row, 33, 10));
                    $offerReturnDateTime = $accomodationDateTime->modify("{$array['nights']} days");

                    if ($filter->bigFile) {
                        $hotelCity = $hotels->get($array['hotelCode']);
                        if ($hotelCity === null || $filter->cityId !== $hotelCity->Address->City->Id) {
                            continue;
                        }
                        if ($filter->checkIn !== $array['checkin']) {
                            continue;
                        }
                        if ($filter->days !=  $array['nights']) {
                            continue;
                        }
                        if ($adults != $array['adults']) {
                            continue;
                        }
                        if ($children != $array['children']) {
                            continue;
                        }
                        $this->showRequest('local file :' .$file, '', [], $row, 0);
                    }

                    //$onlinePrice = $this->getOnlinePrice($adults, $children, $childrenAges, $offerReturnDateTime, $array['apiHkey']);

                    $currency = new Currency();
                    $currency->Code = $array['currency'];

                    $offer = new Offer();
                    $offer->InitialData = $array['apiHkey'];

                    if ($infants > 1 || strtolower($array['accommodationOnRequest']) === 'y') {
                        $offer->Availability = Offer::AVAILABILITY_ASK;
                    } else {
                        $offer->Availability = Offer::AVAILABILITY_YES;
                    }

                    $roomId = $array['mainRoomType'];
                    $mealCode = $array['boardCode'];

                    $pricePerAdult = (float) $array['pricePerAdult'];
                    $infantPrice = 0;
                    if (!empty($childrenAges)) {
                        foreach ($childrenAges as $age) {
                            if ($age < 2) {
                                $infantPrice += (float) $array['infantPrice'];
                            }
                        }
                    }
                    $childrenPrice = 0;
                    $childrenDiscount = 0;
                    $adultsDiscount = 0;

                    if (!empty($childrenAges)) {
                        foreach ($childrenAges as $age) {
                            if ($age >= 2) {
                                $price = $this->getChildPrice($children, $age, $array['childrenPrices']);
                                if ($price === null) {
                                    $childrenPrice += $pricePerAdult;
                                } else {
                                    $childrenPrice += $price;
                                }

                                $discount = $this->getChildPrice($children, $age, $array['childDiscounts']);
                                if ($discount !== null) {
                                    $childrenDiscount += $discount;
                                }
                            }
                        }
                    }

                    $price = (int) $array['adults'] * $pricePerAdult + $childrenPrice + $infantPrice;

                    $adultsDiscount = $this->getAdultsDiscount($adults, $array['adultDiscounts']);

                    $initialPrice = $price + $adultsDiscount + $childrenDiscount;

                    $offer->CheckIn = $array['checkin'];

                    $offer->Code = $array['hotelCode'] . '~'
                        . $roomId . '~'
                        . $mealCode . '~'
                        . $offer->CheckIn . '~'
                        . $filter->days . '~'
                        . $price . '~'
                        . $filter->rooms->get(0)->adults
                        . (count($filter->rooms->first()->childrenAges) > 0 ? '~' . implode('|', $filter->rooms->get(0)->childrenAges->toArray()) : '');

                    $offer->Currency = $currency;
                    $offer->Days = $array['nights'];

                    $taxes = 0;

                    $offer->Net = $price;
                    $offer->Gross = $price;
                    $offer->InitialPrice = $initialPrice;
                    $offer->Comission = $taxes;


                    $checkOut = $offerReturnDateTime->format('Y-m-d');

                    // Rooms
                    $room1 = new Room();
                    $room1->Id = $roomId;
                    $room1->CheckinBefore = $offerReturnDateTime->format('Y-m-d');
                    $room1->CheckinAfter = $offer->CheckIn;

                    $room1->Currency = $offer->Currency;
                    $room1->Quantity = 1;
                    $room1->Availability = $offer->Availability;

                    $roomName = '';
                    $roomTitleParts = explode('|', $array['roomSpecification']);

                    foreach ($roomTitleParts as $part) {
                        if ($part !== '-') {
                            $roomName .= ($roomMap[$part] ?? '') . ' ';
                        }
                    }

                    $merch = new RoomMerch();
                    $merch->Id = $roomId;
                    $merch->Title = $roomName;

                    $merchType = new RoomMerchType();
                    $merchType->Id = $roomId;
                    $merchType->Title = $merch->Title;
                    $merch->Type = $merchType;
                    $merch->Code = $merch->Id;
                    $merch->Name = $merch->Title;

                    $room1->Merch = $merch;

                    $offer->Rooms = new RoomCollection([$room1]);

                    $offer->Item = $room1;

                    $mealItem = new MealItem();

                    $boardTypeName = $array['boardTypeName'];

                    // MealItem Merch
                    $mealItemMerch = new MealMerch();
                    $mealItemMerch->Title = $boardTypeName;

                    $mealMerchType = new MealMerchType();
                    $mealMerchType->Id = $mealCode;
                    $mealMerchType->Title = $boardTypeName;
                    $mealItemMerch->Type = $mealMerchType;

                    // MealItem
                    $mealItem->Merch = $mealItemMerch;
                    $mealItem->Currency = $offer->Currency;

                    $offer->MealItem = $mealItem;

                    // departure transport item merch
                    $departureTransportItemMerch = new TransportMerch();
                    $departureTransportItemMerch->Title = 'CheckIn: ' . $checkIn;

                    // DepartureTransportItem Return Merch
                    $departureTransportItemReturnMerch = new TransportMerch();
                    $departureTransportItemReturnMerch->Title = 'CheckOut: ' . $offerReturnDateTime->format('Y-m-d');;

                    // DepartureTransportItem Return
                    $departureTransportItemReturn = new ReturnTransportItem();
                    $departureTransportItemReturn->Merch = $departureTransportItemReturnMerch;
                    $departureTransportItemReturn->Currency = $currency;
                    $departureTransportItemReturn->DepartureDate = $checkOut;
                    $departureTransportItemReturn->ArrivalDate = $checkOut;

                    // DepartureTransportItem
                    $departureTransportItem = new DepartureTransportItem();
                    $departureTransportItem->Merch = $departureTransportItemMerch;
                    $departureTransportItem->Currency = $currency;
                    $departureTransportItem->DepartureDate = $checkIn;
                    $departureTransportItem->ArrivalDate = $checkIn;
                    $departureTransportItem->Return = $departureTransportItemReturn;

                    $offer->DepartureTransportItem = $departureTransportItem;

                    $offer->ReturnTransportItem = $departureTransportItemReturn;

                    $existingAvailability = $availabilityCollection->get($array['hotelCode']);
                    if ($existingAvailability === null) {
                        // creating new availability
                        $availability = new Availability();
                        $availability->Id = $array['hotelCode'];

                        $offers = new OfferCollection();
                        $offers->put($offer->Code, $offer);
                        $availability->Offers = $offers;
                    } else {
                        // adding offers to the existing availability
                        $availability = $existingAvailability;
                        $availability->Offers->put($offer->Code, $offer);
                    }

                    $availabilityCollection->put($availability->Id, $availability);
                }
                fclose($handle);
            }
        } else {
            throw new Exception($this->handle . ': hotel offers folder does not exist');
        }

        return $availabilityCollection;
    }

    private function getChildPrice(string $children, string $age, string $chunk): ?float
    {
        if ($chunk === '') {
            return null;
        }

        $posStart = strpos($chunk, $children . 'c') + strlen($children . 'c') + 1;

        $posEnd = strpos($chunk, '||', $posStart);
        $segment = substr($chunk, $posStart, $posEnd - $posStart);
        $segment = explode('|', $segment);

        $list = [];
        foreach ($segment as $part) {
            $intervals = explode('-', $part);
            if (count($intervals) === 2) {
                $list[] = $intervals;
            } else {
                $list[] = $part;
            }
        }

        $price = null;
        $i = 0;
        foreach ($list as $item) {
            if (is_array($item)) { // age interval
                if ($age >= $item[0] && $age <= $item[1]) {
                    $price = (float) $list[$i + 1];
                }
            }
            $i++;
        }

        return $price;
    }

    private function getAdultsDiscount($adults, string $chunk): float
    {
        if ($chunk === '') {
            return 0;
        }

        $segments = explode('|', $chunk);

        $price = 0;
        $i = 0;
        foreach ($segments as $part) {
            if (!strpos($part, 'a')) {
                $i++;
                if ($i > $adults) {
                    break;
                }
                $price += (float) $part;
            }
        }

        return $price;
    }

    public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    {
        $file = $this->requestFile(self::DOWNLOAD_HOTELS);

        $xml = file_get_contents($file);
        $xmlObj = new SimpleXMLElement($xml);

        $hotels = new HotelCollection();

        foreach ($xmlObj->tour as $hotelApi) {
            if (!isset($hotelApi->location)) {
                continue;
            }

            $country = new Country();
            $country->Id = $hotelApi->location->country->attributes()->code;
            $country->Code = $country->Id;
            $country->Name = $hotelApi->location->country->attributes()->name;

            $city = new City();
            $city->Id = $hotelApi->location->country->destination->resort->attributes()->code;
            $city->Name = $hotelApi->location->country->destination->resort->attributes()->name;
            $city->Country = $country;

            // $hotel->Address
            $address = new HotelAddress();
            $address->Latitude = $hotelApi->gps->lat;
            $address->Longitude = $hotelApi->gps->lon;
            $address->Details = null;
            $address->City = $city;

            $items = new HotelImageGalleryItemCollection();

            if (isset($hotelApi->images->image)) {
                foreach ($hotelApi->images->image as $imageApi) {
                    $image = new HotelImageGalleryItem();
                    $image->RemoteUrl = $imageApi->attributes()->url;
                    if (strlen($image->RemoteUrl) > 255) {
                        continue;
                    }

                    $image->Alt = null;
                    $items->add($image);
                }
            }

            // $hotel->Content->ImageGallery
            $imageGallery = new HotelImageGallery();
            $imageGallery->Items = $items;

            $facilities = new FacilityCollection();

            if (isset($hotelApi->params->param)) {
                foreach ($hotelApi->params->param as $facilityApi) {
                    $facility = new Facility();
                    $facility->Id = md5($facilityApi);
                    $facility->Name = $facilityApi;
                    $facilities->put($facility->Id, $facility);
                }
            }
            
            $descriptionText = '';
            if (!empty($hotelApi->descriptions->text)) {
                foreach ($hotelApi->descriptions->text as $description) {
                    $title = (string) $description->attributes()->title;
                    $descriptionText .= '<b>'. $title .'</b><br>';
                    $descriptionText .= (string) $description . '<br>';
                }
            }

            // $hotel->Content
            $content = new HotelContent();
            $content->ImageGallery = $imageGallery;
            $content->Content = $descriptionText;

            $hotel = new Hotel();
            $hotel->Id = $hotelApi->code;
            $hotel->Name = $hotelApi->name;
            $hotel->Facilities = $facilities;

            $hotel->Stars = (int) $hotelApi->category;
            $hotel->Content = $content;
            $hotel->Address = $address;
            $hotel->WebAddress = null;

            $hotels->put($hotel->Id, $hotel);
        }

        return $hotels;
    }

    public function apiDoBooking(BookHotelFilter $filter): array
    {
        DertourValidator::make()
            ->validateUsernameAndPassword($this->post)
            ->validateBookHotelFilter($filter);

        $endDate = $filter->Items->get(0)->Room_CheckinBefore;
        $apiHKey = $filter->Items->get(0)->Offer_InitialData;

        $passengersInput = $filter->Items->first()->Passengers;

        $url = $this->apiUrl . '/auth/realms/beapi/protocol/openid-connect/token';

        // get token
        $options['body'] = "client_id={$this->username}&client_secret={$this->password}&grant_type=client_credentials";

        $client = HttpClient::create();
        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getContent(false), $resp->getStatusCode());

        $bearer = json_decode($resp->getContent(false), true)['access_token'];

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $bearer,
            'Content-Type' => 'application/json',
            'x-upe-agent-code' => $this->apiContext
        ];

        $endDateTime = new DateTimeImmutable($endDate);

        $passengerAssignments = [];
        $passengers = [];

        $i = 0;
        /** @var \App\Filters\Passenger $passengerInput */
        foreach ($passengersInput as $passengerInput) {
            if ($passengerInput->Firstname === '') {
                continue;
            }
            $birthDateTime = new DateTimeImmutable($passengerInput->BirthDate);
            $age = $endDateTime->diff($birthDateTime)->y;

            $type = 'ADULT';
            if ($age < 2) {
                $type = 'INFANT';
            } elseif($age < 18) {
                $type = 'CHILD';
            }

            $passengerAssignments[] = ['passengerRefId' => $i + 1];
            $passenger = [
                "passengerRefId" => $i + 1,
                "passengerType" => $type,
                "firstName" => $passengerInput->Firstname,
                "lastName" => $passengerInput->Lastname,
                "birthDate" => $passengerInput->BirthDate
            ];
            if (!empty($passengerInput->Gender)) {
                $passenger['gender'] = strtoupper($passengerInput->Gender);
            } else {
                $passenger['gender'] = 'MALE';
            }

            if (!$passengerInput->IsAdult) {
                $passenger['title'] = $type;
            } else {
                $passenger['title'] = $passengerInput->Gender === 'male' ? 'MR' : 'MRS';
            }

            $passengers[] = $passenger;

            $i++;
        }

        $phone = '+420 738 456 235';
        if (!empty($filter->BillingTo->Phone)) {
            $phone = $filter->BillingTo->Phone;
        }

        $options['body'] =  json_encode([
            'action' => 'FIX',
            'rooms' => [
                [
                    'roomRefId' => 1,
                    'tourRoomId' => $apiHKey,
                    'passengerAssignments' => $passengerAssignments
                ]
            ],
            'customer' => [
                'title' => $passengers[0]['title'],
                'firstName' => $passengers[0]['firstName'],
                'lastName' => $passengers[0]['lastName'],
                'birthDate' => $passengers[0]['birthDate'],
                'phoneNumber' => $phone,
                'email' => $filter->AgencyDetails->Email ?? 'john.wick@google.com',
                'address' => [
                    'street' => 'Abc',
                    'streetNumber' => '1',
                    'city' => 'Abc',
                    'zipCode' => '1',
                    'countryCode' => 'ROU'
                ]
            ],
            'passengers' => $passengers,
        ]);
        

        $url = $this->apiUrl . '/booking/v1/bookings';
        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getContent(false), $resp->getStatusCode());

        $json = $resp->getContent(false);
        $content = json_decode($json, true);

        $response = new Booking();
        if (!isset($content['booking']['bookingNumber'])) {
            throw new Exception($json);
        }
        $response->Id = $content['booking']['bookingNumber'];

        return [$response, $json];
    }

    private function getLatestOfferFileName($url, $username, $password, $dir, $prefix): ?string
    {
        /** @var Connection $ftp */
        $ftp = ftp_ssl_connect($url);
        ftp_login($ftp, $username, $password);
        ftp_pasv($ftp, true);
        ftp_chdir($ftp, $dir);
        $files = ftp_nlist($ftp, '.');
        //ftp_close($ftp);

        $offerFiles = [];

        $now = new DateTime();
        foreach ($files as $file) {
            if (substr($file, 0, 4) === $prefix) {
                // get date from file name
                $date = substr($file, 5, 13);
                // check that datetime format is correct, if not, log error

                $dateOk = DateTime::createFromFormat('Ymd_Hi', $date);
                if (!$dateOk) {
                    Log::warning('Dertour file ' . $file . ' has invalid date. Another way to sort is required!');
                }
                if ($dateOk > $now) {
                    Log::warning($this->handle . ': offer file ' . $file . ' has a future date');
                    continue;
                }

                $offerFiles[] = $file;
            }
        }
        rsort($offerFiles);

        $fileName = null;

        if (isset($offerFiles[0])) {
            $fileName = $offerFiles[0];
        }

        return $fileName;
    }

    public function requestFile(string $file): string
    {
        $response = null;

        $username = env('DERTOUR_FTP_USERNAME');
        $password = env('DERTOUR_FTP_PASSWORD');

        $url = env('DERTOUR_FTP_URL');

        if ($file === self::DOWNLOAD_HOTELS) {
            $remoteFolder = 'DERTOURISTIK_RO';
            $remoteFilename = 'DERTOURRO_NBC_RO.xml';
            $folder = 'static-data-hotels';
        } elseif ($file === self::DOWNLOAD_MASTER_DATA) {
            $remoteFolder = '/';
            $remoteFilename = 'MASTER_DATA.xml';
            $folder = 'static-data-master';
        }

        $downloadsPath = Utils::getDownloadsPath();
        $date = (new DateTime())->format('Y-m-d');

        $localFolder = $downloadsPath . '/' . $this->handle . '/' . $folder;
        $localFilename = $remoteFilename . $date . '.xml';
        $absoluteFilePath = $localFolder . '/' . $localFilename;

        if (file_exists($absoluteFilePath)) {
            return $absoluteFilePath;
        }
 
        $downloadUrl = Utils::getDownloadsBaseUrl() . '/' . $this->handle . '/' . $folder;

        // for localhost handle: create file
        if ($this->handle === self::TEST_HANDLE) {

            if ($file === self::DOWNLOAD_HOTELS) {
                $dummyData = $this->getDummyHotelData();
            } elseif ($file === self::DOWNLOAD_MASTER_DATA) {
                $dummyData = $this->getDummyMasterData();
            }
            if (!is_dir($localFolder)) {
                mkdir($localFolder, 0755, true);
            }
            file_put_contents($absoluteFilePath, $dummyData);
            // remove the other files
            $files = glob($localFolder . "/*");
            if (count($files) > 0) {
                foreach ($files as $file) {
                    if (is_file($file) && $file !== $absoluteFilePath) {
                        unlink($file);
                    }
                }
            }

        } else {
            // try to download
            $ftpClient = FtpsClient::create();
            $url = $url . '/' . $remoteFolder . '/' . $remoteFilename;

            $response = $ftpClient->request($url, $localFolder, $localFilename, $username, $password);
            $this->showRequest(FtpsClient::METHOD_FTPS, $url, [], $downloadUrl . '/' . $localFilename, 0);
            $files = glob($localFolder . "/*");

            if (!$response->fileDownloaded()) {
                Log::warning($this->handle . ': file cannot be downloaded');
                // use the last cached file if exist
                if (isset($files[0]) && is_file($files[0])) {
                    $localFilename = basename($files[0]);
                    $absoluteFilePath = $localFolder . '/' . $localFilename;
                } else {
                    throw new Exception($this->handle . ': no files are available');
                }
            } else {
                // if a fresh file is downloaded, remove old ones
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        if (is_file($file) && $file !== $absoluteFilePath) {
                            @unlink($file);
                        }
                    }
                }
            }
        }
        
        return $absoluteFilePath;
    }

    public function getDummyDataTourOffers(): string
    {
        return '01VDERO                          25.02.2024        10OTPAYTAYTOTPRO RO       0045 03300430 0720101 102 112313EUR        1799                                                               Cairo                    Circuit Premium Egipt: Cr5,0DR-|-|DR|-|-|-             ITAs Itinerary                                        5DSR                  CAIOTP11OTPCAI   CAIOTP11CAIOTP   AYT14141+0                        +0    +0    DR01IT                                                                                      ||1c|2-11|1799||                                                                ||EGR50003|240225|OTPCAIRO101|10|CAIOTPRO102||-|-|DR01|-|-|-|IT||               STVEU1JDQUlFR1I1MDAwMzI0MDIyNTI0MDIyNTAxMERSMDEgSVQgKzArMENBSU9UUDExIE9UUENBSU9UUDExIENBSU9UUDI0MDMwNg==                                                                                                                                                                                                                                                                                                                                                                                                                                                              ';
    }

    public function getDummyDataCharterOffers(): string
    {
        return '01VDERO                          24.12.2023         5OTPAYTAYTOTPFH FH       0000 00000000 0000123 123 101211EUR         489                                                               Alanya                   Riviera                  4,0SR-|-|SR|SSV|-|-           HBHalf Board                                          5DSP                  AYTOTP21OTPAYT   AYTOTP21AYTOTP   AYT14141+0                        +0    +0    SR01HB                                                                                      ||                                                                              ||AYT14141|231224|OTPAYTFH123|5|AYTOTPFH123||-|-|SR01|SSV|-|-|HB||              STVEU1BBWVRBWVQxNDE0MTIzMTIyNDIzMTIyNDAwNVNSMDEgSEIgKzArMEFZVE9UUDIxIE9UUEFZVE9UUDIxIEFZVE9UUDIzMTIyOQ==                                                                                                                                                                                                              1a|37                                                                                                                                                                                                                                           ';
    }

    public function getDummyDataHotelOffers(): string
    {
        return '01VDERO                          01.11.2023         1   AYT                                            101211EUR          62                                                               Alanya                   Riviera                  4,0SR-|-|SR|SSV|-|-           HBHalf Board                                          5DSA                                                    AYT14141+0                                    SR01HB                                                                                      ||                                                                              ||AYT14141|-|-|1|-||-|-|SR01|SSV|-|-|HB||                                       QTVEU0FBWVQxNDE0MTIzMTEwMTAwMVNSMDEgSEIgMDAwMA==                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      ';
    }

    public function getDummyHotelData(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
        <tours locale="ro_RO">
        <tour><code>AYT14141</code><name>Barut Cennet &amp; Acanthus</name><category>5</category><gps><lat>36.78183</lat><lon>31.388468</lon></gps><giata>4862</giata><location><country code="TR" name="Turcia"><destination code="AYT" name="Turkish Riviera"><resort code="AYTSID" name="Side " /></destination></country></location><descriptions>
        <text title="Informatii despre hotel" order="1">
        <![CDATA[Hotel situat in populara statiune Side, la aproximativ 2 km de mers pe jos de centrul istoric al orasului Side. La aproximativ 62 km de Aeroportul Antalya. Optiuni de cumparaturi la cativa pasi de complex.]]>
        </text>
        
        </descriptions>
        <images>
        <image url="https://img.eximtours.cz/hotels/turecko/turecka-riviera/side/acanthus-a-cennet-barut-colletion/h12586_c0_38895.jpg" order="1" />
        </images>
        <params>
        <param category="4" title="Sport/divertisment" id="3">Tenis</param>
        </params><ratings><rating provider="TripAdvisor" score="5" count="1992" /></ratings><preferences><preference type="TOP" value="0" /></preferences></tour></tours>';
    }

    public function getDummyMasterData(): string
    {
        return '<master_data>
            <board_type>
                <mt_bt code="AD">
                    <name locale="en_EN">All Inclusive Dine Around</name>
                    <name locale="cs_CZ">All Inclusive Dine Around</name>
                    <name locale="sk_SK">All Inclusive Dine Around</name>
                    <name locale="hu_HU">All Inclusive Dine Around</name>
                    <name locale="ro_RO">All Inclusive Dine Around</name>
                </mt_bt>
            </board_type>
            <room_type>
                <mt_rt code="SR">
                    <name locale="en_EN">Single Room</name>
                    <name locale="cs_CZ">Jednolůžkový pokoj</name>
                    <name locale="sk_SK">Jednolôžková izba</name>
                    <name locale="hu_HU">Egyágyas szoba</name>
                    <name locale="ro_RO">Single Room</name>
                </mt_rt>
                <mt_rt code="DR">
                    <name locale="en_EN">Double Room</name>
                    <name locale="cs_CZ">Jednolůžkový pokoj</name>
                    <name locale="sk_SK">Jednolôžková izba</name>
                    <name locale="hu_HU">Egyágyas szoba</name>
                    <name locale="ro_RO">Single Room</name>
                </mt_rt>
            </room_type>
            <room_specification>
                <mt_rs code="!NS">
                    <name locale="en_EN">No Stopsale</name>
                    <name locale="cs_CZ">No Stopsale</name>
                    <name locale="sk_SK">No Stopsale</name>
                    <name locale="hu_HU">No Stopsale</name>
                    <name locale="ro_RO">Fara Stopsale</name>
                </mt_rs>
            </room_specification>
        </master_data>';
    }

    // will not download if the file is not new
    public function downloadOffers(): void
    {
        set_time_limit(6000);
        Log::debug($this->handle . ': downloading and processing offers');
        $chartersOk = $this->downloadCharterOffers();
        $hotelsOk = $this->downloadHotelOffers();
        $toursOk = $this->downloadTourOffers();
        if ($chartersOk && $hotelsOk && $toursOk) {
            Log::debug($this->handle . ': offers downloaded and processed');
        } else {
            Log::debug($this->handle . ': cannot download offers');
        }


        $filter = new CitiesFilter();
        $filter->clearCache = true;

        // refreshing cities cache
        $this->apiGetCities($filter);
        Log::debug($this->handle . ': cities cache refreshed');
    }

    public function apiGetTours(): TourCollection
    {
        $tours = new TourCollection();

        $cities = $this->apiGetCities();
        $hotels = $this->apiGetHotels();

        $filesLoc = Utils::getDownloadsPath() . '/' . $this->handle . '/' . self::FOLDER_OFFERS_TOUR . '/uncompressed/*';
        $files = glob($filesLoc);
        if (isset($files[0])) {
            $handle = fopen($files[0], 'r');

            while (($row = fgets($handle)) !== false) {
                $hotelCode = trim(substr($row, 377, 8));
                $hotel = $hotels->get($hotelCode);

                // from the mail:
                // It this case it is fault of ProducManager - he has hotel calculated in CRS but NBC content is not ready yet...
                // You have to ignore this hotel.

                if ($hotel === null) {
                    continue;
                }
                
                $departureCity = $cities->get(substr($row, 53, 3));

                if ($departureCity->Country->Code !== 'RO' && $departureCity->Country->Code !== 'HU') {
                    continue;
                }

                $tour = new Tour();
                $offsetArrivalToAccom = (int) trim(substr($row, 385, 2));
                $departureArrivalOffset = (int) trim(substr($row, 411, 2));

                $totalOffset = $offsetArrivalToAccom + $departureArrivalOffset;
                $tour->Period = (int) trim(substr($row, 43, 10)) + $totalOffset;
                
                $arrivalCity = $hotel->Address->City;
                $tour->Id = $hotel->Id;
                $tour->Title = $hotel->Name;
                $destinations = new CityCollection();
                $destinations->add($arrivalCity);
                $tour->Destinations = $destinations;
                $destCountries = new CountryCollection();
                $destCountries->add($hotel->Address->City->Country);
                $tour->Destinations_Countries = $destCountries;

                $tourContent = new TourContent();
                $tourContent->Content = $hotel->Content->Content;

                $tourImageGallery = new TourImageGallery();

                $tourIGIC = new TourImageGalleryItemCollection();

                foreach ($hotel->Content->ImageGallery->Items as $image) {
                    $tourIGIC->add(TourImageGalleryItem::create($image->RemoteUrl));
                }

                $tourImageGallery->Items = $tourIGIC;

                $tourContent->ImageGallery = $tourImageGallery;
                $tour->Content = $tourContent;

                $transportTypes = new StringCollection();
                $transportTypes->add('plane');
                $tour->TransportTypes = $transportTypes;
                $location = new Location();
                $location->City = $hotel->Address->City;
                $tour->Location = $location;
                
                $tours->put($tour->Id, $tour);
            }
        }

        return $tours;
    }

    public function apiTestConnection(): bool
    {
        $ok = false;

        $url = $this->apiUrl . '/auth/realms/beapi/protocol/openid-connect/token';
        // get token
        $options['body'] = "client_id={$this->username}&client_secret={$this->password}&grant_type=client_credentials";
        $options['headers'] = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $client = HttpClient::create(['verify_peer' => false]);
        $resp = $client->request(HttpClient::METHOD_POST, $url, $options);
        $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getContent(false), $resp->getStatusCode());
        $json = $resp->getContent();

        $arr = json_decode($json, true);

        $apiCodeOk = true;

        if (!empty($this->apiContext)) {
            if (isset($arr['access_token'])) {

                $bearer = $arr['access_token'];

                $options['body'] = json_encode([
                    "rooms" => [
                        [
                            "roomRefId" => 1,
                            "tourRoomId" =>  'a',
                            "passengerAssignments" => [['passengerRefId' => 1]]
                        ]
                    ],
                    "passengers" => [[
                        'passengerRefId' => 1,
                        'passengerType' => 'ADULT',
                        'birthDate' => '1990-01-01'
                    ]]
                ]);

                $options['headers'] = [
                    'Authorization' => 'Bearer ' . $bearer,
                    'Content-Type' => 'application/json',
                    'x-upe-agent-code' => $this->apiContext
                ];
                $url = $this->apiUrl . '/booking/v1/availability-check';
                $resp = $client->request(HttpClient::METHOD_POST, $url, $options);

                $this->showRequest(HttpClient::METHOD_POST, $url, $options, $resp->getContent(false), $resp->getStatusCode());

                $content = json_decode($resp->getContent(false), true);
                if (isset($content['code'])) {
                    $code = $content['code'];
                    if ($code === 'ERR_BE_030') {
                        $apiCodeOk = false;
                    }
                }
            }
        }

        if (isset($arr['access_token']) && $apiCodeOk) {
            $ok = true;
        }

        return $ok;
    }
}
