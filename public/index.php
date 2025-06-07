<?php

use Container\Container;
use RequestHandler\RequestHandler;
use RequestHandler\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Utils\Utils;

set_time_limit(3600);

// todo: daca nu exista folderul support, notificare de clonare

require_once '../autoload.php';
require_once '../env.php';
require_once '../support/Utils/functions.php';

// turn warnings into exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    //Log::error($message . ' in ' . $file .  ' line ' . $line . PHP_EOL);
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$clientIp = Utils::getClientIp();
if (!($clientIp === '84.232.237.17' || $clientIp === '::1')) {
    die($clientIp . ' not authorized');
}

// load unit test on dev only
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

$request = ServerRequest::createFromGlobals();

$container = new Container();
// solve ServerRequest dependencies for Controllers or other objects
$container->set(ServerRequestInterface::class, function(Container $c) use ($request) {
    return $request;
});

$routes = require_once __DIR__.'/../routes/routes.php';
$requestHandler = new RequestHandler($routes->getRoutes(), $container);
$response = $requestHandler->handle($request);

echo $response->getBody();