<?php

namespace Services\Megatec;

use Exception;
use Services\IntegrationSupport\Validator;

// some fields are mandatory only for this service
class MegatecValidator extends Validator
{
    public function validateBookHotelFilter(array $post): self
    {
        parent::validateBookHotelFilter($post);

        if (empty($post['args'][0]['Items'][0]['Hotel']['InTourOperatorId'])) {
            throw new Exception('args[0][Items][0][Hotel][InTourOperatorId] is mandatory');
        }
        if (empty($post['args'][0]['Items'][0]['Room_Type_InTourOperatorId'])) {
            throw new Exception('args[0][Items][0][Room_Type_InTourOperatorId] is mandatory');
        }
        if (empty($post['args'][0]['Items'][0]['Board_Def_InTourOperatorId'])) {
            throw new Exception('args[0][Items][0][Board_Def_InTourOperatorId] is mandatory');
        }
        if (empty($post['args'][0]['Items'][0]['Room_CheckinAfter'])) {
            throw new Exception('args[0][Items][0][Room_CheckinAfter] is mandatory');
        }
        if (empty($post['args'][0]['Items'][0]['Room_CheckinBefore'])) {
            throw new Exception('args[0][Items][0][Room_CheckinBefore] is mandatory');
        }
        if (empty($post['args'][0]['Params']['Adults'][0])) {
            throw new Exception('args[0][Params][Adults][0] is mandatory');
        }
        if (empty($post['args'][0]['Items'][0]['Offer_bookingDataJson'])) {
            throw new Exception('bookingDataJson is mandatory');
        }

        return $this;
    }

    public function validateIndividualOffersFilter(array $post): self
    {
        parent::validateIndividualOffersFilter($post);


        // if (empty($filter->checkOut)) {
        //     throw new Exception("args[0][checkOut] is mandatory");
        // }
        // if (empty($filter->days)) {
        //     throw new Exception("args[0][days] is mandatory");
        // }

        return $this;
    }

    public function validateOfferCancelFeesFilter(array $post): Validator
    {
        parent::validateOfferCancelFeesFilter($post);
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
        if (empty($post['args'][0]['Rooms'][0]['adults'])) {
            throw new Exception('args[0][Rooms][0][adults] is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['Rooms'][0]['Id'])) {
            throw new Exception('args[0][OriginalOffer][Rooms][0][Id] is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['MealItem']['Merch']['Id'])) {
            throw new Exception('args[0][OriginalOffer][MealItem][Merch][Id] is mandatory');
        }
        if (empty($post['args'][0]['SuppliedPrice'])) {
            throw new Exception('price is mandatory');
        }
        if (empty($post['args'][0]['OriginalOffer']['bookingDataJson'])) {
            throw new Exception('bookingDataJson is mandatory');
        }

        return $this;
    }

    // public function validateOfferPaymentPlansFilter(PaymentPlansFilter $filter): Validator
    // {
    //     parent::validateOfferPaymentPlansFilter($filter);
    //     if (empty($post['args'][0]['CheckIn'])) {
    //         throw new Exception('args[0][CheckIn] is mandatory');
    //     }
    //     if (empty($post['args'][0]['Duration'])) {
    //         throw new Exception('args[0][Duration] is mandatory');
    //     }
    //     if (empty($post['args'][0]['CheckOut'])) {
    //         throw new Exception('args[0][CheckOut] is mandatory');
    //     }
    //     if (empty($post['args'][0]['Hotel']['InTourOperatorId'])) {
    //         throw new Exception('args[0][Hotel][InTourOperatorId] is mandatory');
    //     }
    //     if (empty($post['args'][0]['rooms'][0]['adults'])) {
    //         throw new Exception('args[0][Rooms][0][adults] is mandatory');
    //     }
    //     if (empty($post['args'][0]['OriginalOffer']['Rooms'][0]['Id'])) {
    //         throw new Exception('args[0][OriginalOffer][Rooms][0][Id] is mandatory');
    //     }
    //     if (empty($post['args'][0]['OriginalOffer']['MealItem']['Merch']['Id'])) {
    //         throw new Exception('args[0][OriginalOffer][MealItem][Merch][Id] is mandatory');
    //     }
    //     if (empty($post['args'][0]['SuppliedPrice'])) {
    //         throw new Exception('args[0][SuppliedPrice] is mandatory');
    //     }
    //     if (empty($post['args'][0]['OriginalOffer']['bookingDataJson'])) {
    //         throw new Exception('bookingDataJson is mandatory');
    //     }

    //     return $this;
    // }

    public static function make(): self
    {
        return new self;
    }
}
