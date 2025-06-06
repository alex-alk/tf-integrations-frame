<?php

namespace Services\Amara;

use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class AmaraValidator extends Validator
{
    #override
    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {
        parent::validateAvailabilityFilter($filter);
        if (empty($filter->departureCity)) {
            throw new Exception("departureCity is mandatory");
        }
        return $this;
    }

    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);

        if (empty($filter->Items->get(0)->Offer_bookingPrice)) {
            throw new Exception('Offer_bookingPrice is mandatory');
        }
        
        if (empty($filter->Items->get(0)->Offer_bookingCurrency)) {
            throw new Exception('Offer_bookingCurrency is mandatory');
        }

        if (strlen($filter->Items->get(0)->Offer_roomCombinationId) < 1) {
            throw new Exception('Offer_roomCombinationId is mandatory');
        }

        if (empty($filter->Items->get(0)->Offer_roomCombinationPriceDescription)) {
            throw new Exception('Offer_roomCombinationPriceDescription is mandatory');
        }
        return $this;
    }

    public static function make(): Validator
    {
        return new AmaraValidator;
    }
}
