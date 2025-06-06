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
}
