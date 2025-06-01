<?php

namespace Integrations\BrosTravel;

use App\Filters\BookHotelFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class BrosTravelValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Hotel->InTourOperatorId)) {
            throw new Exception('InTourOperatorId is mandatory');
        }
        if (empty($filter->Items->get(0)->Room_CheckinBefore)) {
            throw new Exception('Room_CheckinBefore is mandatory');
        }
        if (empty($filter->Items->get(0)->Room_CheckinAfter)) {
            throw new Exception('Room_CheckinAfter is mandatory');
        }
        if (empty($filter->Items->get(0)->Room_Type_InTourOperatorId)) {
            throw new Exception('Room_Type_InTourOperatorId is mandatory');
        }
        if (empty($filter->Items->get(0)->Board_Def_InTourOperatorId)) {
            throw new Exception('Board_Def_InTourOperatorId is mandatory');
        }
        return $this;
    }

    public static function make(): Validator
    {
        return new self;
    }
}
