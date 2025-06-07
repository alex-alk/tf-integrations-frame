<?php

namespace Models;

use JsonSerializable;

class OfferCancelFee implements JsonSerializable
{
    public function __construct(private string $dateStart, private string $dateEnd, private float $price, private string $currency)
    { 
    }

    public function jsonSerialize(): array
    {
       return [
            'DateStart' => $this->dateStart,
            'DateEnd' => $this->dateEnd,
            'Price' => $this->price,
            'Currency' => ['Code' => $this->currency]
       ]; 
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getDateStart(): string
    {
        return $this->dateStart;
    }
}