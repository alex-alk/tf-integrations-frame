<?php

namespace Integrations\Odeon;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class OdeonValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->first()->Hotel->InTourOperatorId)) {
            throw new Exception('args[0][Items][0][Hotel][InTourOperatorId] is mandatory');
        }
        
        if (empty($filter->Items->first()->Board_Def_InTourOperatorId)) {
            throw new Exception('args[0][Items][0][Board_Def_InTourOperatorId] is mandatory');
        }
        
        if (empty($filter->Items->first()->Room_Type_InTourOperatorId)) {
            throw new Exception('args[0][Items][0][Room_Type_InTourOperatorId] is mandatory');
        }
        //todo: departure city?
        // if (empty($filter->Items->first()->Room_Type_InTourOperatorId)) {
        //     throw new Exception('args[0][Items][0][Room_Type_InTourOperatorId]');
        // }

        if (empty($filter->Items->first()->Room_CheckinAfter)) {
            throw new Exception('args[0][Items][0][Room_CheckinAfter] is mandatory');
        }

        // todo: nights
        // if (empty($filter->Items->first()->Room_CheckinAfter)) {
        //     throw new Exception('args[0][Items][0][Room_CheckinAfter] is mandatory');
        // }

        if (empty($filter->Params->Adults->first())) {
            throw new Exception('args[0][Params][Adults][0] is mandatory');
        }
        if (empty($filter->Items->get(0)->Offer_Days)) {
            throw new Exception('args[0][Items][0][Offer_Days] is mandatory');
        }
        if (empty($filter->Items->get(0)->Offer_InitialData)) {
            throw new Exception('args[0][Items][0][Offer_InitialData] is mandatory');
        }
        if (empty($filter->Items->get(0)->Offer_InitialData)) {
            throw new Exception('args[0][Items][0][Offer_departureFlightId] is mandatory');
        }
        if (empty($filter->Items->get(0)->Offer_InitialData)) {
            throw new Exception('args[0][Items][0][Offer_returnFlightId] is mandatory');
        }

        if (empty($filter->Items->get(0)->Offer_departureFlightId)) {
            throw new Exception('args[0][Items][0][Offer_departureFlightId] is mandatory');
        }
        if (empty($filter->Items->get(0)->Offer_returnFlightId)) {
            throw new Exception('args[0][Items][0][Offer_returnFlightId] is mandatory');
        }
        return $this;
    }

    #overrride
    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {
        parent::validateAvailabilityFilter($filter);

        if (empty($filter->countryId)) {
            throw new Exception("countryId is mandatory");
        }

        if (empty($filter->departureCity)) {
            throw new Exception("departureCity is mandatory");
        }
        if (empty($filter->days)) {
            throw new Exception("days is mandatory");
        }
        return $this;
    }

    public static function make(): self
    {
        return new self();
    }
}
