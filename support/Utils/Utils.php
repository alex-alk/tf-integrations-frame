<?php

namespace Utils;

use App\Support\Collections\AbstractTypedCollection;
use App\Support\Collections\Custom\CountryCollection;
use App\Support\Log;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DirectoryIterator;
use DOMDocument;
use DOMElement;
use Exception;
use IntegrationSupport\AbstractApiService;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;

class Utils
{

    public static function arrayToXml(array $data): string
    {
        $rootTag   = array_key_first($data);
        $rootValue = $data[$rootTag];

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element
        $root = $dom->createElement($rootTag);
        $dom->appendChild($root);

        // Recursive function to add children
        $addChildren = function (array $nodeData, DOMElement $parent) use (&$addChildren, $dom): void {
            foreach ($nodeData as $key => $value) {
                if ($key === '@attributes' && is_array($value)) {
                    foreach ($value as $attrName => $attrValue) {
                        $parent->setAttribute($attrName, (string)$attrValue);
                    }
                    continue;
                }

                $element = $dom->createElement($key);

                if (is_array($value)) {
                    if (isset($value['@value'])) {
                        $element->appendChild($dom->createTextNode((string)$value['@value']));
                    } elseif (isset($value['@cdata'])) {
                        $element->appendChild($dom->createCDATASection((string)$value['@cdata']));
                    }

                    // Add any other nested elements
                    $nested = array_filter($value, fn($k) => !in_array($k, ['@value', '@cdata', '@attributes']), ARRAY_FILTER_USE_KEY);
                    if (!empty($nested)) {
                        $addChildren($nested, $element);
                    }

                    if (isset($value['@attributes']) && is_array($value['@attributes'])) {
                        foreach ($value['@attributes'] as $attrName => $attrValue) {
                            $element->setAttribute($attrName, (string)$attrValue);
                        }
                    }
                } else {
                    $element->appendChild($dom->createTextNode((string)$value));
                }

                $parent->appendChild($element);
            }
        };

        $addChildren($rootValue, $root);

        return $dom->saveXML();
    }

    // attributes example: 'Adults' => [['Adult' => ['[a]' => 'b']], ['Adult' => 'b']],
    public static function arrayToXmlString(array $array, ?SimpleXMLElement $xml = null): string
    {
        $xmlNew = self::arrayToXmlObj($array, $xml);
        $domxml = new DOMDocument();
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xmlNew->asXML());
        return $domxml->saveXML();
    }

    public static function arrayToXmlObj(array $array, ?SimpleXMLElement $xml = null): SimpleXMLElement
    {
        foreach ($array as $key => $value) {
            if ($xml === null) {
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><' . $key . ' />');
                self::arrayToXmlObj($value, $xml);
            } else if (is_array($value)) {
                if (!is_numeric($key)) {


                    if (array_key_exists(0, $value) && !is_array($value[0])) {
                        $subnode = $xml->addChild($key, $value[0]);
                    } else {
                        $subnode = $xml->addChild($key);
                    }
                    self::arrayToXmlObj($value, $subnode);
                } else {

                    self::arrayToXmlObj($value, $xml);
                }
            } else {
                preg_match("#\[([a-z0-9-_]+)\]#i", $key, $attr);
                if (count($attr)) {
                    $xml->addAttribute($attr[1], $value);
                } else {
                    if ($key !== 0)
                        $xml->addChild($key, $value);
                }
            }
        }
        return $xml;
    }

    public static function getShortJson($data): string
    {
        if ($data instanceof AbstractTypedCollection) {
            $data = $data->toArray();
        }
        $slice = (is_countable($data) && (count($data) > 1000)) ? array_slice($data, 0, 1000) : $data;
        $shortJson =  json_encode_pretty(['response' => $slice]);
        return $shortJson;
    }

    public static function writeToCache(string|AbstractApiService $handle, string $file, string $data, int $daysToExpire = 0): void
    {
        if (!is_string($handle)) {
            if ($handle->getSkipTopCache()) {
                return;
            }
            $handle = $handle->gethandle();
        }
        $expirationDate = (new DateTime())->add(new DateInterval('P' . $daysToExpire . 'D'));
        $date = $expirationDate->format('Y-m-d');
        $dir = __DIR__ . '/../Storage/Cache/' . $handle . '/' . $file;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        } else {
            foreach (new DirectoryIterator($dir) as $item) {
                if (!$item->isDot() && $item->isFile()) {
                    @unlink($item->getPathname());
                }
            }
        }

        $fileName =  $date . '_' . $file;
        $filePath = $dir . '/' . $fileName;
        file_put_contents($filePath, $data);
    }

    /**
     * Creates a lock for the current function
     */
    public static function createLock(string $handle)
    {
        $bt = debug_backtrace();
        $functionName = $bt[1]['function'];

        $dir = __DIR__ . '/../Storage/Cache/' . $handle . '/locks/' . $functionName;

        if (is_dir($dir)) {
            $dateCreated = new DateTimeImmutable(date('Y-m-d H:i:s', filectime($dir)));
            $now = new DateTimeImmutable();
            $minutesDif = $now->diff($dateCreated)->i;
            if ($minutesDif >= 1) {
                @rmdir($dir);
            }
        }

        return @mkdir($dir, 0755, true);
    }

    /**
     * Check if a lock exists for the current function
     */
    // public static function getLock(string $handle, int $minutes = 60): bool
    // {
    //     $bt = debug_backtrace();
    //     $functionName = $bt[1]['function'];

    //     $dir = __DIR__.'/../Storage/Cache/'. $handle . '/locks/' . $functionName;

    //     $locked = true;
    //     clearstatcache();
    //     if (!is_dir($dir)) {
    //         $locked = false;
    //     } else {
    //         $dateCreated = new DateTimeImmutable(date('Y-m-d H:i:s', filectime($dir)));
    //         $now = new DateTimeImmutable();
    //         $minutesDif = $now->diff($dateCreated)->i;
    //         if ($minutesDif >= $minutes) {
    //             $locked = false;
    //         }
    //     }

    //     return $locked;
    // }

    /**
     * Remove the lock for the current function
     */
    public static function removeLock(string $handle): void
    {
        $bt = debug_backtrace();
        $functionName = $bt[1]['function'];

        $dir = __DIR__ . '/../Storage/Cache/' . $handle . '/locks/' . $functionName;
        @rmdir($dir);
    }

    public static function getFromCache(string|AbstractApiService $handle, string $file, bool $getExpired = false): ?string
    {

        if (!is_string($handle)) {
            if ($handle->getSkipTopCache() || $handle->getRenewTopCache()) {
                return null;
            }
            $getExpired = $handle->hasGetLatestCache();
            $handle = $handle->gethandle();
        }

        $today = (new DateTime())->setTime(0, 0);
        $date = $today->format('Y-m-d');
        $dir = __DIR__ . '/../Storage/Cache/' . $handle . '/' . $file;

        // get file
        $files = glob($dir . '/*');

        if (!isset($files[0])) {
            return null;
        }

        $date = substr(basename($files[0]), 0, 10);

        try {
            $fileDateTime = new DateTime($date);
        } catch (Exception $e) {
            return null;
        }

        $fileDateTime = $fileDateTime->setTime(0, 0);

        if ($fileDateTime >= $today || $getExpired) {
            return file_get_contents($files[0]);
        } else {
            return null;
        }
    }

    public static function cachedFileExists(string|AbstractApiService $handle, string $file, bool $getExpired = false): bool
    {
        if (!is_string($handle)) {
            if ($handle->getSkipTopCache() || $handle->getRenewTopCache()) {
                return false;
            }
            $handle = $handle->gethandle();
        }

        $today = (new DateTime())->setTime(0, 0);
        $date = $today->format('Y-m-d');
        $dir = __DIR__ . '/../Storage/Cache/' . $handle . '/' . $file;

        // get file
        $files = glob($dir . '/*');

        if (!isset($files[0])) {
            return false;
        }

        $date = substr(basename($files[0]), 0, 10);

        try {
            $fileDateTime = new DateTime($date);
        } catch (Exception $e) {
            return false;
        }

        $fileDateTime = $fileDateTime->setTime(0, 0);

        if ($fileDateTime >= $today || $getExpired) {
            return true;
        } else {
            return false;
        }
    }

    public static function clearCache(string $handle, string $file): void
    {
        $dir = __DIR__ . '/../Storage/Cache/' . $handle . '/' . $file;
        if (is_dir($dir)) {
            foreach (new DirectoryIterator($dir) as $item) {
                if (!$item->isDot() && $item->isFile()) {
                    unlink($item->getPathname());
                }
            }
        }
    }

    public static function getRootPath(): string
    {
        return __DIR__ . '/../';
    }

    public static function getStoragePath(): string
    {
        return __DIR__ . '/../' . self::getStorageFolderName();
    }

    public static function getDownloadsRelativePath(): string
    {
        return self::getStorageFolderName() . '/Downloads';
    }
    public static function getCacheRelativePath(): string
    {
        return self::getStorageFolderName() . '/Cache';
    }

    public static function getStorageFolderName(): string
    {
        return 'Storage';
    }

    public static function getDownloadsPath(): string
    {
        return self::getStoragePath() . '/Downloads';
    }

    public static function getCachePath(): string
    {
        return self::getStoragePath() . '/Cache';
    }

    public static function getDownloadsBaseUrl(): string
    {
        return self::getBaseUrl() . '/' . self::getDownloadsRelativePath();
    }

    public static function getCacheBaseUrl(): string
    {
        return self::getBaseUrl() . '/' . self::getCacheRelativePath();
    }

    public static function getBaseUrl(): string
    {
        return (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost') . env('APP_FOLDER');
    }

    public static function getLogsPath(): string
    {
        return self::getStoragePath() . '/' . 'Logs';
    }

    public static function setTestVariable(string $key, string|int|float|bool $value): void
    {
        $dir = self::getLogsPath();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $existingJson = file_get_contents($dir . '/test.json');
        if ($existingJson !== false) {
            $jsonObj = json_decode($existingJson, true);
            $jsonObj[$key] = $value;
            file_put_contents($dir . '/test.json', json_encode($jsonObj));
        }

        file_put_contents($dir . '/test.json', json_encode([$key => $value]));
    }

    public static function removeTestVariable(string $key): void
    {
        $dir = self::getLogsPath();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $existingJson = file_get_contents($dir . '/test.json');
        if ($existingJson !== false) {
            $jsonObj = json_decode($existingJson, true);
            unset($jsonObj[$key]);
            file_put_contents($dir . '/test.json', json_encode($jsonObj));
        }
    }

    public static function getTestVariable(string $key): string|int|float|bool|null
    {
        $file = self::getLogsPath() . '/test.json';
        if (!is_file($file)) {
            return null;
        }
        $contentJson = file_get_contents($file);
        $content = json_decode($contentJson, true);

        if (isset($content[$key])) {
            return $content[$key];
        } else {
            return null;
        }
    }

    public static function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    public static function getClientIp(): ?string
    {
         $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // Sometimes multiple IPs are passed (e.g. “client, proxy1, proxy2”)
                $ipList = array_map('trim', explode(',', $_SERVER[$header]));

                foreach ($ipList as $ip) {
                    // Validate: must be a valid public IP (not private, not reserved)
                    if (filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    )) {
                        return $ip;
                    }
                }
            }
        }

        // Fallback—always return something (could be private or localhost)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function stripSpaces(string $string)
    {
        return preg_replace('/\s+/', '', $string);
    }

    public static function createCsv(string $file, array $columns, array $data): void
    {
        file_put_contents($file, '');
        $f = fopen($file, 'w');
        fputcsv($f, $columns, ',', '"');
        foreach ($data as $row) {
            fputcsv($f, $row, ',', '"');
        }
        fclose($f);
    }

    public static function readCreatedCsv(string $file): array
    {
        $i = 0;
        $handle = @fopen($file, 'r');
        $array = [];

        if ($handle) {
            while (($row = fgetcsv($handle)) !== false) {
                if (empty($fields)) {
                    $fields = $row;
                    continue;
                }

                foreach ($row as $k => $value) {
                    $array[$i][$fields[$k]] = $value;
                }
                $i++;
            }
            fclose($handle);
        }
        return $array;
    }
}
