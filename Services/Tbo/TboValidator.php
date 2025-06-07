<?php

namespace Integrations\Tbo;

use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class TboValidator extends Validator
{

    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): Validator
    {
        parent::validateOfferCancelFeesFilter($filter);
        if (empty($post['args'][0]['OriginalOffer']['bookingDataJson'])) {
            throw new Exception('bookingDataJson is mandatory');
        }
        if (empty($post['args'][0]['CheckIn'])) {
            throw new Exception('CheckIn is mandatory');
        }
        return $this;
    }

    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);

        if (empty($post['args'][0]['Items'][0]['Offer_bookingDataJson'])) {
            throw new Exception('Offer_bookingDataJson is mandatory');
        }
        if (empty($filter->BillingTo->Email)) {
            throw new Exception('Email is mandatory');
        }
        if (empty($filter->BillingTo->Phone)) {
            throw new Exception('Phone is mandatory');
        }
        if (empty($filter->Items->first()->Offer_Gross)) {
            throw new Exception('Offer_Gross is mandatory');
        }

        // if (empty($post['args'][0]['Items'][0]['Hotel']['InTourOperatorId'])) {
        //     throw new Exception('args[0][Items][0][Hotel][InTourOperatorId] is mandatory');
        // }
        // if (empty($post['args'][0]['Items'][0]['Room_Type_InTourOperatorId']) {
        //     throw new Exception('args[0][Items][0][Room_Type_InTourOperatorId] is mandatory');
        // }
        // if (empty($filter->Items->first()->Room_Def_Code)) {
        //     throw new Exception('args[0][Items][0][Room_Def_Code] is mandatory');
        // }
        // if (empty($post['args'][0]['Items'][0]['Offer_bookingDataJson'])) {
        //     throw new Exception('args[0][Items][0][Offer_bookingDataJson] is mandatory');
        // }
        // if (empty($post['args'][0]['Items'][0]['Board_Def_InTourOperatorId'])) {
        //     throw new Exception('args[0][Items][0][Board_Def_InTourOperatorId] is mandatory');
        // }
        // if (empty($post['args'][0]['Items'][0]['Room_CheckinAfter'])) {
        //     throw new Exception('args[0][Items][0][Room_CheckinAfter] is mandatory');
        // }
        // if (empty($post['args'][0]['Items'][0]['Room_CheckinBefore'])) {
        //     throw new Exception('args[0][Items][0][Room_CheckinBefore] is mandatory');
        // }
        // if (empty($filter->Items->first()->Offer_Days)) {
        //     throw new Exception('args[0][Items][0][Offer_Days] is mandatory');
        // }

        return $this;
    }
/*
    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateIndividualOffersFilter($filter);

        if (empty($filter->hotelId) && empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception('args[0][cityId] or args[0][regionId] or args[0][travelItemId] must be passed');
        }
        if (empty($filter->checkOut)) {
            throw new Exception("args[0][checkOut] is mandatory");
        }
        if (empty($filter->days)) {
            throw new Exception("args[0][days] is mandatory");
        }

        return $this;
    }

    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): Validator
    {
        parent::validateOfferCancelFeesFilter($filter);
        if (empty($post['args'][0]['CheckIn'])) {
            throw new Exception('args[0][CheckIn] is mandatory');
        }
        if (empty($post['args'][0]['Duration'])) {
            throw new Exception('args[0][Duration] is mandatory');
        }
        if (empty($post['args'][0]['CheckOut'])) {
            throw new Exception('args[0][CheckOut] is mandatory');
        }
        if (empty($post['args'][0]['Hotel']['InTourOperatorId'])) {
            throw new Exception('args[0][Hotel][InTourOperatorId] is mandatory');
        }
        if (empty($post['args'][0]['rooms'][0]['adults'])) {
            throw new Exception('args[0][Rooms][0][adults] is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['Rooms'][0]['Id'])) {
            throw new Exception('args[0][OriginalOffer][Rooms][0][Id] is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['MealItem']['Merch']['Id'])) {
            throw new Exception('args[0][OriginalOffer][MealItem][Merch][Id] is mandatory');
        }
        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('args[0][OriginalOffer][InitialData] is mandatory');
        }
        if (empty($post['args'][0]['SuppliedPrice'])) {
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
        if (empty($post['args'][0]['CheckIn'])) {
            throw new Exception('args[0][CheckIn] is mandatory');
        }
        if (empty($post['args'][0]['Duration'])) {
            throw new Exception('args[0][Duration] is mandatory');
        }
        if (empty($post['args'][0]['CheckOut'])) {
            throw new Exception('args[0][CheckOut] is mandatory');
        }
        if (empty($post['args'][0]['Hotel']['InTourOperatorId'])) {
            throw new Exception('args[0][Hotel][InTourOperatorId] is mandatory');
        }
        if (empty($post['args'][0]['rooms'][0]['adults'])) {
            throw new Exception('args[0][Rooms][0][adults] is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['Rooms'][0]['Id'])) {
            throw new Exception('args[0][OriginalOffer][Rooms][0][Id] is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['MealItem']['Merch']['Id'])) {
            throw new Exception('args[0][OriginalOffer][MealItem][Merch][Id] is mandatory');
        }
        if (empty($post['args'][0]['SuppliedPrice'])) {
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
