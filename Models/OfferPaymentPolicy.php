<?php

namespace Models;

use JsonSerializable;

class OfferPaymentPolicy implements JsonSerializable
{
    public function __construct(private string $payAfter, private string $payUntil, private float $amount, private string $currency)
    { 
    }

    public function jsonSerialize(): array
    {
       return [
            'PayAfter' => $this->payAfter,
            'PayUntil' => $this->payUntil,
            'Amount' => $this->amount,
            'Currency' => ['Code' => $this->currency]
       ]; 
    }
}