<?php

namespace Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Router\View;

class IntegrationController
{
    public function __construct(private ServerRequestInterface $request)
    {}

    public function countries(): View
    {
        return View::make('integration/countries', []);
    }

    public function regions(): View
    {
        return View::make('integration/regions', []);
    }

    public function cities(): View
    {
        return View::make('integration/cities', []);
    }

    public function hotels(): View
    {
        return View::make('integration/hotels', []);
    }

    public function offers(): View
    {
        return View::make('integration/offers', []);
    }

    public function cancelFees(): View
    {
        return View::make('integration/cancel-fees', []);
    }

    public function paymentPlans(): View
    {
        return View::make('integration/payment-plans', []);
    }

    public function testConnection(): View
    {
        return View::make('integration/test-connection', []);
    }

    public function book(): View
    {
        return View::make('integration/book', []);
    }
}
