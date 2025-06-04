<?php

function env(string $key): string
{
    $config = [
        // local or production
        'APP_ENV' => 'local',
        'APP_FOLDER' => '/public',
    ];
    return $config[$key];
}