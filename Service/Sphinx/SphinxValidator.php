<?php

namespace Integrations\Sphinx;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class SphinxValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);

        if (empty($filter->Items->first()->Offer_bookingDataJson)) {
            throw new Exception('Offer_bookingDataJson is mandatory');
        }

        if (empty($filter->Items->first()->Offer_Gross)) {
            throw new Exception('Offer_Gross is mandatory');
        }

        if (empty($filter->Items->first()->Room_Def_Code)) {
            throw new Exception('Room_Def_Code is mandatory');
        }

        return $this;
    }

    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateIndividualOffersFilter($filter);

        if (empty($filter->checkOut)) {
            throw new Exception("args[0][checkOut] is mandatory");
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

    public static function make(): SphinxValidator
    {
        return new SphinxValidator;
    }
}
