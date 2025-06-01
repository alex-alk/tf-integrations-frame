<?php

namespace Integrations\EuroSite;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Support\Log;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class EuroSiteValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Offer_Code)) {
            throw new Exception('Offer_Code is mandatory');
        }

        if (empty($filter->Items->get(0)->Offer_Code)) {
            throw new Exception('Offer_InitialData is mandatory');
        }

        if (empty($filter->Items->get(0)->Offer_bookingCurrency)) {
            throw new Exception('Offer_bookingCurrency is mandatory');
        }

        if (empty($filter->Items->first()->Hotel->Country_Code)) {
            throw new Exception('Country_Code is mandatory');
        }

        if (empty($filter->Items->first()->Hotel->City_Code)) {
            throw new Exception('City_Code is mandatory');
        }

        if (empty($filter->Items->first()->Hotel->InTourOperatorId)) {
            throw new Exception('InTourOperatorId is mandatory');
        }

        if (empty($filter->Items->first()->Room_CheckinAfter)) {
            throw new Exception('Room_CheckinAfter is mandatory');
        }

        if (empty($filter->Items->first()->Room_CheckinBefore)) {
            throw new Exception('Room_CheckinBefore is mandatory');
        }

        if (empty($filter->Items->first()->Room_Type_InTourOperatorId)) {
            throw new Exception('Room_Type_InTourOperatorId is mandatory');
        }

        if (!isset($filter->Params->Adults)) {
            throw new Exception('Adults is mandatory');
        }

        if (!isset($filter->Params->Children)) {
            throw new Exception('Children is mandatory');
        }
        
        return $this;
    }

    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {
        parent::validateAvailabilityFilter($filter);

        if (empty($filter->countryId)) {
            throw new Exception("countryId is mandatory");
        }
        if (empty($filter->cityId)) {
            throw new Exception("cityId is mandatory");
        }

        if ($filter->serviceTypes === AvailabilityFilter::SERVICE_TYPE_CHARTER && empty($filter->departureCity)) {
            throw new Exception("departureCity is mandatory");
        }
        return $this;
    }

    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): self
    {
        parent::validateOfferCancelFeesFilter($filter);
        if (empty($filter->Rooms->first()->adults)) {
            throw new Exception('adults is mandatory');
        }

        if (!empty($filter->Rooms->first()->childrenAges) && empty($filter->Rooms->first()->childrenAges)) {
            throw new Exception('childrenAges is mandatory');
        }

        if (empty($filter->SuppliedCurrency)) {
            throw new Exception('currency is mandatory');
        }
        if (empty($filter->Hotel)) {
            throw new Exception('country is mandatory');
        }

        if (empty($filter->Hotel->InTourOperatorId)) {
            throw new Exception('hotel id is mandatory');
        }
        if (empty($filter->CheckIn)) {
            throw new Exception('checkin is mandatory');
        }
        if (empty($filter->CheckOut)) {
            throw new Exception('checkOut is mandatory');
        }
        if (empty($filter->OriginalOffer->Rooms->first()->Id)) {
            throw new Exception('roomId is mandatory');
        }

        return $this;
    }

    public static function make(): EurositeValidator
    {
        return new self();
    }
}
