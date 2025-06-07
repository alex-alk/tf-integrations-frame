<?php

namespace Integrations\TourVisio;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\HotelDetailsFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class TourVisioValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->BillingTo->Email)) {
            throw new Exception('args[0][BillingTo][Email] is mandatory');
        }

        if (empty($filter->Items->first()->Offer_offerId)) {
            throw new Exception('args[0][Items][0][Offer_offerId] is mandatory');
        }

        if (empty($filter->Items->first()->Offer_bookingCurrency)) {
            throw new Exception('args[0][Items][0][Offer_bookingCurrency] is mandatory');
        }

        if (empty($post['args'][0]['Items'][0]['Room_CheckinAfter'])) {
            throw new Exception('args[0][Items][0][Room_CheckinAfter] is mandatory');
        }
        return $this;
    }

    #overrride
    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {
        parent::validateAvailabilityFilter($filter);

        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception("args[0][cityId] or args[0][regionId] is mandatory");
        } else {

            $cityFilter = [];
            if (!empty($filter->regionId)) {
                $cityFilter = explode('-', $filter->regionId);
            } else {
                $cityFilter = explode('-', $filter->cityId);
            }
            if (count($cityFilter) < 2) {
                throw new Exception("Wrong city or region format!");
            }
        }

        if (empty($filter->days)) {
            throw new Exception("args[0][days] is mandatory");
        }

        return $this;
    }

    #overrride
    public function validateCharterOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateCharterOffersFilter($filter);

        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception("args[0][cityId] or args[0][regionId] is mandatory");
        } else {

            $cityFilter = [];
            if (!empty($filter->regionId)) {
                $cityFilter = explode('-', $filter->regionId);
            } else {
                $cityFilter = explode('-', $filter->cityId);
            }
            if (count($cityFilter) < 2) {
                throw new Exception("Wrong city or region format!");
            }
        }

        if (empty($filter->departureCity)) {
            throw new Exception("args[0][departureCity] is mandatory");
        } else {

            $cityFilter = explode('-', $filter->departureCity);

            if (count($cityFilter) < 2) {
                throw new Exception("Wrong departure city format!");
            }
        }

        if (empty($filter->days)) {
            throw new Exception("args[0][days] is mandatory");
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
