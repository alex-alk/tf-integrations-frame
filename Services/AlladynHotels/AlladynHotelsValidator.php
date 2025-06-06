<?php

namespace Integrations\AlladynHotels;

use App\Filters\BookHotelFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class AlladynHotelsValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Offer_Code)) {
            throw new Exception('Offer_Code is mandatory');
        }
        return $this;
    }

    public static function make(): Validator
    {
        return new self;
    }
}
