<?php

namespace Models;

use JsonSerializable;

class Booking implements JsonSerializable
{
    public function __construct(private ?string $id)
    { 
    }

    public function jsonSerialize(): array
    {
        if ($this->id !== null) {
            return [
                'Id' => $this->id
            ];
        } else {
            return [];
        } 
    }
}