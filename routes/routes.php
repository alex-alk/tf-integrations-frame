<?php

use Controllers\ApiController;
use Controllers\GeographyController;
use Controllers\WebController;
use Router\Routes;

$routes = new Routes();

// --------------- routes --------------------------
$routes->post('/api', [ApiController::class, 'post']);
$routes->get('/', [WebController::class, 'index']);
$routes->get('/countries', [GeographyController::class, 'countries']);
$routes->post('/countries', [GeographyController::class, 'getCountries']);

// -------------------------------------------------

return $routes;