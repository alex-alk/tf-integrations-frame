<?php

namespace Integrations\Etrip;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\PaymentPlansFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class EtripValidator extends Validator
{
    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateIndividualOffersFilter($filter);
        if (empty($filter->checkOut)) {
            throw new Exception('args[0][checkOut] is mandatory');
        }
        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception('args[0][cityId] or args[0][regionId] is mandatory');
        }
        return $this;
    }
    
    public function validateCharterOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateCharterOffersFilter($filter);
        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception('args[0][cityId] or args[0][regionId] must be provided');
        }
        if (empty($filter->transportTypes)) {
            throw new Exception('args[0][transportTypes][0] must be provided');
        }
        
        return $this;
    }

    public function validateTourOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateTourOffersFilter($filter);
        if (empty($filter->cityId) && empty($filter->regionId)) {
            throw new Exception('args[0][cityId] or args[0][regionId] must be provided');
        }
        if (empty($filter->transportTypes)) {
            throw new Exception('args[0][transportTypes][0] must be provided');
        }
        
        return $this;
    }

    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (!isset($filter->Items->first()->Offer_offerId) || $filter->Items->first()->Offer_offerId === null || $filter->Items->first()->Offer_offerId === '') {
            throw new Exception('args[0][Items][0][Offer_offerId] is missing');
        }
        if (empty($filter->Items->first()->Offer_InitialData)) {
            throw new Exception('args[0][Items][0][Offer_InitialData] is missing');
        }
        if (empty($post['args'][0]['Items'][0]['Room_CheckinAfter'])) {
            throw new Exception('args[0][Items][0][Room_CheckinAfter] is missing');
        }
        
        return $this; 
    }

    public function validateOfferPaymentPlansFilter(PaymentPlansFilter $filter): self
    {
        parent::validateOfferPaymentPlansFilter($filter);
        if (empty($filter->OriginalOffer->offerId) && $filter->OriginalOffer->offerId != 0) {
            throw new Exception('args[0][OriginalOffer][offerId] is missing');
        }
        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('args[0][OriginalOffer][InitialData] is missing');
        }
        if (empty($post['args'][0]['rooms'][0]['adults'])) {
            throw new Exception('args[0][Rooms][0][adults] is missing');
        }
        
        return $this;
    }

    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): self
    {
        parent::validateOfferCancelFeesFilter($filter);
        if (empty($filter->OriginalOffer->offerId) && $filter->OriginalOffer->offerId != 0) {
            throw new Exception('args[0][OriginalOffer][offerId] is missing');
        }
        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('args[0][OriginalOffer][InitialData] is missing');
        }
        if (empty($post['args'][0]['rooms'][0]['adults'])) {
            throw new Exception('args[0][Rooms][0][adults] is missing');
        }
        
        return $this;
    }

    public static function make(): self
    {
        return new self;
    }
}
