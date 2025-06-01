<?php

namespace Router;

use Exception;

class RouteNotFoundException extends Exception
{
    protected $message = 'route not found';

    public function __construct()
    {
        http_response_code(404);

    }
}
