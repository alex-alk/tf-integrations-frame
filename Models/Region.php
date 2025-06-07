<?php

namespace Models;

use JsonSerializable;

class Region implements JsonSerializable
{
    public function __construct(private string $id, private string $name, private Country $country)
    { 
    }

    public function jsonSerialize(): array
    {
       return [
            'Id' => $this->id,
            'Name' => $this->name,
            'Country' => $this->country->jsonSerialize()
       ]; 
    }

    public function getId(): string
    {
        return $this->id;
    }
}