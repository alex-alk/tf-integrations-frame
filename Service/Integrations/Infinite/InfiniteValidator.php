<?php

namespace Integrations\Infinite;

use App\Filters\BookHotelFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class InfiniteValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Offer_Code)) {
            throw new Exception('Offer_Code is mandatory');
        }
        if (empty($filter->Items->get(0)->Room_CheckinBefore)) {
            throw new Exception('Room_CheckinBefore is mandatory');
        }

        if (empty($filter->Items->get(0)->Room_CheckinAfter)) {
            throw new Exception('Room_CheckinAfter is mandatory');
        }
        return $this;
    }

    public static function make(): Validator
    {
        return new self;
    }
}
