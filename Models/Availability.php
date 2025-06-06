<?php

namespace Models;

use JsonSerializable;

class Availability implements JsonSerializable
{
    public function __construct(
        private string $id, 
        private array $offers, 
        private ?string $name = null,
        private ?int $stars = null
    ){ 
    }

    public function jsonSerialize(): array
    {
    
        $array = [
            'Id' => $this->id,
            'Offers' => $this->offers
        ];

        if (!empty($this->name)) {
            $array['Name'] = $this->name;
        }
        if (!empty($this->stars)) {
            $array['Stars'] = $this->stars;
        }

       return $array;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOffers(): array
    {
        return $this->offers;
    }
}