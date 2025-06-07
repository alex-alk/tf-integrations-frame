<?php

namespace Integrations\TeztourV2;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\HotelDetailsFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class TeztourV2Validator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->first()->Hotel->Country_InTourOperatorId)) {
            throw new Exception('args[0][Items][0][Hotel][Country_InTourOperatorId] is mandatory');
        }
        
        if (empty($post['args'][0]['Items'][0]['Hotel']['InTourOperatorId'])) {
            throw new Exception('args[0][Items][0][Hotel][InTourOperatorId] is mandatory');
        }

        if (empty($post['args'][0]['Items'][0]['Board_Def_InTourOperatorId'])) {
            throw new Exception('args[0][Items][0][Board_Def_InTourOperatorId] is mandatory');
        }

        if (empty($post['args'][0]['Items'][0]['Room_Type_InTourOperatorId']) {
            throw new Exception('args[0][Items][0][Room_Type_InTourOperatorId] is mandatory');
        }

        if (empty($post['args'][0]['Items'][0]['Room_CheckinAfter'])) {
            throw new Exception('args[0][Items][0][Room_CheckinAfter] is mandatory');
        }

        if (empty($post['args'][0]['Items'][0]['Room_CheckinBefore'])) {
            throw new Exception('args[0][Items][0][Room_CheckinBefore] is mandatory');
        }

        if (empty($filter->Items->first()->Hotel->City_Code)) {
            throw new Exception('args[0][Items][0][Hotel][City_Code] is mandatory');
        }

        // if (empty($filter->BillingTo->Email)) {
        //     throw new Exception('args[0][BillingTo][Email] is mandatory');
        // }

        // if (empty($filter->Items->first()->Offer_offerId)) {
        //     throw new Exception('args[0][Items][0][Offer_offerId] is mandatory');
        // }

        // if (empty($filter->Items->first()->Offer_bookingCurrency)) {
        //     throw new Exception('args[0][Items][0][Offer_bookingCurrency] is mandatory');
        // }

        return $this;
    }

    #overrride
    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateIndividualOffersFilter($filter);

        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception("args[0][cityId] or args[0][regionId] is mandatory");
        }

        if (empty($filter->countryId)) {
            throw new Exception("args[0][countryId] is mandatory");
        }

        return $this;
    }

    #overrride
    public function validateCharterOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateCharterOffersFilter($filter);

        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception("args[0][cityId] or args[0][regionId] is mandatory");
        }

        if (empty($filter->departureCity)) {
            throw new Exception("args[0][departureCity] is mandatory");
        }

        if (empty($filter->days)) {
            throw new Exception("args[0][days] is mandatory");
        }

        if (empty($filter->countryId)) {
            throw new Exception("args[0][countryId] is mandatory");
        }

        return $this;
    }

    public function validateHotelDetailsFilter(HotelDetailsFilter $filter): void
    {
        parent::validateHotelDetailsFilter($filter);
        $hotelFilter = [];
        if (!empty($filter->hotelId)) {
            $hotelFilter = explode('-', $filter->hotelId);
        } else {
            $hotelFilter = explode('-', $filter->hotelId);
        }
        if (count($hotelFilter) < 2) {
            throw new Exception("Hotel id format must be like: 1234-2");
        }
    }

    public static function make(): self
    {
        return new self();
    }
}
