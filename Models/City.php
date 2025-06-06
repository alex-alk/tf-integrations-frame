<?php

namespace Models;

use JsonSerializable;

class City implements JsonSerializable
{
    public function __construct(private string $id, private string $name, private Country $country, private ?Region $region = null)
    { 
    }

    public function jsonSerialize(): array
    {
       return [
            'Id' => $this->id,
            'Name' => $this->name,
            'Country' => $this->country->jsonSerialize(),
            'County' => $this->region->jsonSerialize()
       ]; 
    }
}