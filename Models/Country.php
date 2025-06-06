<?php

namespace Models;

use JsonSerializable;

class Country implements JsonSerializable
{
    public function __construct(private string $id, private string $code, private string $name)
    { 
    }

    public function jsonSerialize(): array
    {
       return [
            'Id' => $this->id,
            'Code' => $this->code,
            'Name' => $this->name
       ]; 
    }

    public function getId(): string
    {
        return $this->id;
    }
}