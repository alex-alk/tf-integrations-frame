<?php

namespace Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Router\View;

class GeographyController
{
    public function __construct(private ServerRequestInterface $request)
    {}

    public function countries(): View
    {
        // $call = $this->request->getQueryParam('call');

        // if ($call == null) {
        //     return View::make('index');
        // }

        // $form = new Forms($call);

        return View::make('geography/countries', []);
    }
}
