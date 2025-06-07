<?php

use Controllers\ApiController;
use Controllers\IntegrationController;
use Controllers\WebController;
use Router\Routes;

$routes = new Routes();

// --------------- routes --------------------------
$routes->post('/api', [ApiController::class, 'post']);
$routes->get('/', [WebController::class, 'index']);
$routes->get('/countries', [IntegrationController::class, 'countries']);
$routes->get('/regions', [IntegrationController::class, 'regions']);
$routes->get('/cities', [IntegrationController::class, 'cities']);
$routes->get('/hotels', [IntegrationController::class, 'hotels']);
$routes->get('/offers', [IntegrationController::class, 'offers']);
$routes->get('/cancel-fees', [IntegrationController::class, 'cancelFees']);
$routes->get('/payment-plans', [IntegrationController::class, 'paymentPlans']);
$routes->get('/test-connection', [IntegrationController::class, 'testConnection']);
$routes->get('/book', [IntegrationController::class, 'book']);

// -------------------------------------------------

return $routes;