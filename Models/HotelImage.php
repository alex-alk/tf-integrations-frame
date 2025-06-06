<?php

namespace Models;

use JsonSerializable;

class HotelImage implements JsonSerializable
{
    public function __construct(private string $url, private ?string $alt = null)
    { 
    }

    public function jsonSerialize(): array
    {
        $array = [
            'RemoteUrl' => $this->url
        ];
        if (!empty($this->alt)) {
            $array['Alt'] = $this->alt;
        }
        return $array; 
    }
}