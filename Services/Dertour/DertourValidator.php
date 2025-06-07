<?php

namespace Integrations\Dertour;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\PaymentPlansFilter;
use Exception;
use IntegrationSupport\Validator;

// some fields are mandatory only for this service
class DertourValidator extends Validator
{
    #override
    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        parent::validateBookHotelFilter($filter);
        if (empty($filter->Items->get(0)->Offer_InitialData)) {
            throw new Exception('Offer_InitialData is mandatory');
        }

        if (empty($filter->Items->get(0)->Room_CheckinBefore)) {
            throw new Exception('Room_CheckinBefore is mandatory');
        }
        return $this;
    }

    public function validateOfferPaymentPlansFilter(PaymentPlansFilter $filter): self
    {
        if (empty($post['args'][0]['rooms'][0]['adults'])) {
            throw new Exception('adults is mandatory');
        }

        if (!isset($post['args'][0]['rooms'][0]['children']) || $post['args'][0]['rooms'][0]['children'] === '') {
            throw new Exception('children is mandatory');
        }

        if (!empty($post['args'][0]['rooms'][0]['children']) && empty($post['args'][0]['rooms'][0]['childrenAges'])) {
            throw new Exception('childrenAges is mandatory');
        }

        if (empty($post['args'][0]['CheckOut'])) {
            throw new Exception('CheckOut is mandatory');
        }

        if (empty($filter->OriginalOffer->InitialData)) {
            throw new Exception('InitialData is mandatory');
        }

        if (empty($filter->SuppliedCurrency)) {
            throw new Exception('SuppliedCurrency is mandatory');
        }

        return $this;
    }

    public function validateCharterOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateCharterOffersFilter($filter);
        if (empty($filter->cityId)) {
            throw new Exception('args[0][cityId] is mandatory');
        }
        return $this;
    }

    public function validateTourOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateTourOffersFilter($filter);
        if (empty($filter->cityId)) {
            throw new Exception('args[0][cityId] is mandatory');
        }
        return $this;
    }

    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        parent::validateIndividualOffersFilter($filter);
        if (empty($filter->days)) {
            throw new Exception('args[0][days] is mandatory');
        }
        if (empty($filter->cityId)) {
            throw new Exception('args[0][cityId] is mandatory');
        }

        return $this;
    }

    public static function make(): self
    {
        return new self;
    }
}
