<?php

namespace IntegrationSupport;

class AirportMap
{
    public static function getAirportMap(): array
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
}
