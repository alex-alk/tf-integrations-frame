<?php

namespace IntegrationSupport;

use App\Filters\AvailabilityFilter;
use App\Filters\BookHotelFilter;
use App\Filters\CancellationFeeFilter;
use App\Filters\HotelDetailsFilter;
use App\Filters\PaymentPlansFilter;
use Exception;

class Validator
{
    
    public function validateAllCredentials(array $param): self
    {
        if (empty($param['to']['ApiUsername'])) {
            throw new Exception('to[ApiUsername] is mandatory');
        }
        if (empty($param['to']['ApiPassword'])) {
            throw new Exception('to[ApiPassword] is mandatory');
        }
        if (empty($param['to']['ApiContext'])) {
            throw new Exception('to[ApiContext] is mandatory');
        }
        return $this;
    }

    public function validateApiCode(array $post): self
    {
        if (empty($post['to']['ApiCode'])) {
            throw new Exception('to[ApiCode] is mandatory');
        }
        return $this;
    }

    public function validateUsernameAndPassword(array $post): self
    {
        if (empty($post['to']['ApiUsername'])) {
            throw new Exception('to[ApiUsername] is mandatory');
        }
        if (empty($post['to']['ApiPassword'])) {
            throw new Exception('to[ApiPassword] is mandatory');
        }
        return $this;
    }

    public function validateBookingCredentials(array $post): self
    {
        if (empty($post['to']['BookingApiUsername'])) {
            throw new Exception('to[BookingApiUsername] is mandatory');
        }
        if (empty($post['to']['BookingApiPassword'])) {
            throw new Exception('to[BookingApiPassword] is mandatory');
        }
        if (empty($post['to']['BookingUrl'])) {
            throw new Exception('to[BookingUrl] is mandatory');
        }
        return $this;
    }

    public function validateBookingUrl(array $post): self
    {
        if (isset($post['http_referer']) && (
            $post['http_referer'] === 'startup-plus.travelfuse.ro' ||
            $post['http_referer'] === 'startup-alex.testing002.travelfuse.ro' || 
            $post['http_referer'] === 'search-dev.testing002.travelfuse.ro' ||
            $post['http_referer'] === 'tf-startup-test1.testing002.travelfuse.ro'
        )) {
            return $this;
        }
        if (empty($post['to']['BookingUrl'])) {
            throw new Exception('to[BookingUrl] is mandatory');
        }
        return $this;
    }

    public function validateBookingUsernameAndPassword(array $post): self
    {
        if (empty($post['to']['BookingApiUsername'])) {
            throw new Exception('to[BookingApiUsername] is mandatory');
        }
        if (empty($post['to']['BookingApiPassword'])) {
            throw new Exception('to[BookingApiPassword] is mandatory');
        }
        return $this;
    }

    public function validateApiContext(array $post): self
    {
        if (empty($post['to']['ApiContext'])) {
            throw new Exception('to[ApiContext] is mandatory');
        }
        return $this;
    }


    public function validateAvailabilityFilter(AvailabilityFilter $filter): self
    {
        if (empty($filter->serviceTypes)) {
            throw new Exception("serviceTypes is mandatory");
        }

        if (empty($filter->checkIn)) {
            throw new Exception("args[0][checkIn] is mandatory");
        }

        if (empty($filter->rooms->get(0)->adults) || ((int) $filter->rooms->get(0)->adults) === 0) {
            throw new Exception('args[0][rooms][0][adults] is mandatory');
        }

        if ((int) $filter->rooms->get(0)->children > 0 
            && count($filter->rooms->first()->childrenAges) !== (int) $filter->rooms->get(0)->children) {
                throw new Exception('childrenAges count is not correct');
        }

        if ((int) $filter->rooms->get(0)->children > 0) {
            foreach ($filter->rooms->first()->childrenAges as $age) {
                if ($age === null) {
                    throw new Exception('age cannot be null');
                }
            }
        }
        
        return $this;
    }

    public function validateIndividualOffersFilter(AvailabilityFilter $filter): self
    {
        if (empty($filter->serviceTypes)) {
            throw new Exception("serviceTypes is mandatory");
        }

        if (empty($filter->checkIn)) {
            throw new Exception("args[0][checkIn] is mandatory");
        }

        if (empty($filter->checkOut)) {
            throw new Exception("checkout is mandatory");
        }

        if (empty($filter->rooms->get(0)->adults)) {
            throw new Exception('args[0][rooms][0][adults] is mandatory');
        }

        if ((int) $filter->rooms->get(0)->children > 0 
            && count($filter->rooms->first()->childrenAges) !== (int) $filter->rooms->get(0)->children) {
                throw new Exception('childrenAges count is not correct');
        }

        if ((int) $filter->rooms->get(0)->children > 0) {
            foreach ($filter->rooms->first()->childrenAges as $age) {
                if ($age === null) {
                    throw new Exception('age cannot be null');
                }
            }
        }
        return $this;
    }

    public function validateCharterOffersFilter(AvailabilityFilter $filter): self
    {
        if (empty($filter->checkIn)) {
            throw new Exception("args[0][checkIn] is mandatory");
        }
        
        if (empty($filter->rooms->get(0)->adults)) {
            throw new Exception('args[0][rooms][0][adults] is mandatory');
        }

        if (empty($filter->departureCity) && empty($filter->departureCityId)) {
            throw new Exception('args[0][departureCity] or args[0][departureCityId] is mandatory');
        }
        if (empty($filter->days)) {
            throw new Exception('args[0][days] is mandatory');
        }
        return $this;
    }

    public function validateTourOffersFilter(AvailabilityFilter $filter): self
    {
        if (empty($filter->departureCityId) && empty($filter->departureCity)) {
            throw new Exception('args[0][departureCityId] or args[0][departureCity] is missing');
        }
        return $this;
    }

    public function validateBookHotelFilter(BookHotelFilter $filter): self
    {
        if (count($filter->Items->get(0)->Passengers) === 0) {
            throw new Exception('No passengers!');
        }
        if (!($filter->Items->get(0)->Passengers->get(0)->Gender ==='male' || 
            $filter->Items->get(0)->Passengers->get(0)->Gender === 'female')
        ) {
            throw new Exception('Passenger gender is invalid');
        }

        if(!(is_bool($filter->Items->get(0)->Passengers->get(0)->IsAdult) || 
            is_numeric($filter->Items->get(0)->Passengers->get(0)->IsAdult))
        ) {
            throw new Exception('IsAdult must be boolean or numeric');
        }

        if (empty($filter->Items->get(0)->Passengers->get(0)->Firstname)) {
            throw new Exception('Passenger Firstname is mandatory');
        }

        if (empty($filter->Items->get(0)->Passengers->get(0)->Lastname)) {
            throw new Exception('Passenger Lastname is mandatory');
        }

        if (empty($filter->Items->get(0)->Passengers->get(0)->BirthDate)) {
            throw new Exception('Passenger BirthDate is mandatory');
        }
        
        if (strlen($filter->Items->get(0)->Passengers->get(0)->IsAdult) < 1) {
            throw new Exception('Passenger IsAdult is mandatory');
        }
        return $this;
    }
    
    public function validateHotelDetailsFilter(HotelDetailsFilter $filter): void
    {
        if (empty($filter->hotelId)) {
            throw new Exception("HotelId is mandatory");
        }
    }

    public function validateOfferPaymentPlansFilter(PaymentPlansFilter $filter): self
    {
        return $this;
    }

    public function validateOfferCancelFeesFilter(CancellationFeeFilter $filter): self
    {
        return $this;
    }

    public static function make(): Validator
    {
        return new self;
    }
}
