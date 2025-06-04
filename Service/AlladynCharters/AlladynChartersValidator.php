<?php

namespace Integrations\AlladynCharters;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class AlladynChartersValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Offer_departureFlightId)) {
            throw new Exception('Offer_departureFlightId is mandatory');
        }
        
        if (empty($filter->Items->get(0)->Offer_returnFlightId)) {
            throw new Exception('Offer_returnFlightId is mandatory');
        }
        
        if (empty($filter->Items->get(0)->Offer_InitialData)) {
            throw new Exception('Offer_InitialData is mandatory');
        }
        return $this;
    }

    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {
        parent::validateAvailabilityFilter($filter);

        if (empty($filter->departureCity)) {
            throw new Exception("departureCity is mandatory");
        }
        return $this;
    }

    public static function make(): Validator
    {
        return new self();
    }
}
