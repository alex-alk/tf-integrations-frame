<?php

namespace Integrations\Travelio;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class TravelioValidator extends Validator
{
    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): self
    {
        parent::validateOfferCancelFeesFilter($filter);
        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('args[0][OriginalOffer][InitialData] is mandatory');
        }
        if (empty($post['args'][0]['SuppliedPrice'])) {
            throw new Exception('SuppliedPrice is mandatory');
        }
        if (empty($filter->OriginalOffer->roomCombinationPriceDescription)) {
            throw new Exception('args[0][OriginalOffer][roomCombinationPriceDescription] is mandatory');
        }
        return $this;
    }
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Room_Type_InTourOperatorId)) {
            throw new Exception('Room_Type_InTourOperatorId is mandatory');
        }
        
        if (empty($filter->Items->get(0)->Board_Def_InTourOperatorId)) {
            throw new Exception('Board_Def_InTourOperatorId is mandatory');
        }
        
        if (empty($filter->Items->get(0)->Offer_bookingDataJson)) {
            throw new Exception('Offer_bookingDataJson is mandatory');
        }

        if (empty($filter->Items->get(0)->Room_CheckinAfter)) {
            throw new Exception('Room_CheckinAfter is mandatory');
        }

        if (empty($filter->Items->get(0)->Room_CheckinBefore)) {
            throw new Exception('Room_CheckinBefore is mandatory');
        }
        
        return $this;
    }

    #overrride
    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {        
        parent::validateAvailabilityFilter($filter);
        
        if (empty($filter->checkOut)) {
            throw new Exception("args[0][checkOut] is mandatory");
        }

        return $this;
    }

    public function validateTourOffersFilter(AvailabilityFilter $filter): Validator
    {
        parent::validateTourOffersFilter($filter);
        if (empty($filter->cityId)) {
            throw new Exception("cityId is mandatory");
        }
        return $this;
    }

    public static function make(): Validator
    {
        return new self();
    }
}
