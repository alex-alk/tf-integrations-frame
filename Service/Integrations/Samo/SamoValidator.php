<?php

namespace Integrations\Samo;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\PaymentPlansFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class SamoValidator extends Validator
{
    
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);

        if (empty($filter->Items->first()->Offer_bookingDataJson)) {
            throw new Exception('args[0][Items][0][Offer_bookingDataJson] is mandatory');
        }

        return $this;
    }

    public function validateOfferPaymentPlansFilter(PaymentPlansFilter $paymentPlansFilter): self
    {
        if (empty($paymentPlansFilter->OriginalOffer->bookingDataJson)) {
            throw new Exception('bookingDataJson is mandatory');
        }
        if (empty($paymentPlansFilter->Rooms->first()->adults)) {
            throw new Exception('adults is mandatory');
        }
        if (empty($paymentPlansFilter->__type__)) {
            throw new Exception('type is mandatory');
        }
        return $this;
    }
    
    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateIndividualOffersFilter($filter);

        if (empty($filter->countryId)) {
            throw new Exception('args[0][countryId] is mandatory');
        }
        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception('args[0][cityId] or args[0][regionId] is mandatory');
        }
        if (empty($filter->days)) {
            throw new Exception('args[0][days] is mandatory');
        }

        return $this;
    }

    public function validateCharterOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateCharterOffersFilter($filter);

        if (empty($filter->countryId) && $filter->serviceTypes->first() === AvailabilityFilter::SERVICE_TYPE_CHARTER) {
            throw new Exception('args[0][countryId] is mandatory');
        }
        // if (empty($filter->cityId) && empty($filter->regionId)) {
        //     throw new Exception('args[0][cityId] is mandatory');
        // }
        if (empty($filter->days)) {
            throw new Exception('args[0][days] is mandatory');
        }
        if (empty($filter->departureCity) && empty($filter->departureCityId)) {
            throw new Exception('args[0][departureCity] or args[0][departureCityId] is mandatory');
        }

        return $this;
    }
    /*
    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): Validator
    {
        parent::validateOfferCancelFeesFilter($filter);
        if (empty($filter->CheckIn)) {
            throw new Exception('args[0][CheckIn] is mandatory');
        }
        if (empty($filter->Duration)) {
            throw new Exception('args[0][Duration] is mandatory');
        }
        if (empty($filter->CheckOut)) {
            throw new Exception('args[0][CheckOut] is mandatory');
        }
        if (empty($filter->Hotel->InTourOperatorId)) {
            throw new Exception('args[0][Hotel][InTourOperatorId] is mandatory');
        }
        if (empty($filter->Rooms->first()->adults)) {
            throw new Exception('args[0][Rooms][0][adults] is mandatory');
        }
        if (empty($filter->OriginalOffer->Rooms->first()->Id)) {
            throw new Exception('args[0][OriginalOffer][Rooms][0][Id] is mandatory');
        }
        if (empty($filter->OriginalOffer->MealItem->Merch->Id)) {
            throw new Exception('args[0][OriginalOffer][MealItem][Merch][Id] is mandatory');
        }
        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('args[0][OriginalOffer][InitialData] is mandatory');
        }
        if (empty($filter->SuppliedPrice)) {
            throw new Exception('args[0][SuppliedPrice] is mandatory');
        }
        if (empty($filter->OriginalOffer->roomCombinationPriceDescription)) {
            throw new Exception('args[0][OriginalOffer][roomCombinationPriceDescription] is mandatory');
        }

        return $this;
    }

    public function validateOfferPaymentPlansFilter(PaymentPlansFilter $filter): Validator
    {
        parent::validateOfferPaymentPlansFilter($filter);
        if (empty($filter->CheckIn)) {
            throw new Exception('args[0][CheckIn] is mandatory');
        }
        if (empty($filter->Duration)) {
            throw new Exception('args[0][Duration] is mandatory');
        }
        if (empty($filter->CheckOut)) {
            throw new Exception('args[0][CheckOut] is mandatory');
        }
        if (empty($filter->Hotel->InTourOperatorId)) {
            throw new Exception('args[0][Hotel][InTourOperatorId] is mandatory');
        }
        if (empty($filter->Rooms->first()->adults)) {
            throw new Exception('args[0][Rooms][0][adults] is mandatory');
        }
        if (empty($filter->OriginalOffer->Rooms->first()->Id)) {
            throw new Exception('args[0][OriginalOffer][Rooms][0][Id] is mandatory');
        }
        if (empty($filter->OriginalOffer->MealItem->Merch->Id)) {
            throw new Exception('args[0][OriginalOffer][MealItem][Merch][Id] is mandatory');
        }
        if (empty($filter->SuppliedPrice)) {
            throw new Exception('args[0][SuppliedPrice] is mandatory');
        }
        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('args[0][OriginalOffer][InitialData] is mandatory');
        }
        if (empty($filter->OriginalOffer->roomCombinationPriceDescription)) {
            throw new Exception('args[0][OriginalOffer][roomCombinationPriceDescription] is mandatory');
        }

        return $this;
    }*/

    public static function make(): self
    {
        return new self;
    }
}
