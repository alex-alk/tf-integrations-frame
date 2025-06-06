<?php

namespace Models;

use DateTime;
use DateTimeInterface;
use JsonSerializable;

class Offer implements JsonSerializable
{
    const AVAILABILITY_YES = 'yes';
    const AVAILABILITY_ASK = 'ask';
    const AVAILABILITY_NO = 'no';

    public function __construct(
        private string $hotelId,
        private string $roomId,
        private string $mealId,
        private	string $offerCheckIn, 
		private string $offerCheckOut,
        private float $priceGross,
        private string $adults,
        private ?array $childrenAges,
        private string $currency,
        private string $availability,
        private float $priceNet,
        private float $priceInitial,
        private float $comission,
        private string $roomTypeId,
        private string $roomName,
        private ?string $roomInfo,
        private ?string $exclamationMark,
        private string $mealName,
        private ?string $bookingDataJson
    ){}

    public static function createIndividualOffer(
		string $hotelId, 
		string $roomId,
		string $roomTypeId,
		string $roomName, 
		string $mealId,
		string $mealName,
		DateTimeInterface $offerCheckInDT, 
		DateTimeInterface $offerCheckOutDT,
		string $adults,
		?array $childrenAges,
		string $offerCurrency,
		float $priceNet,
		float $priceInitial,
		float $priceGross,
		float $comission,
		string $availability,
		?string $roomInfo = null,
		?string $exclamationMark = null,
		?string $bookingDataJson = null
		): self
	{
		return new self(
            $hotelId, 
            $roomId, 
            $mealId, 
            $offerCheckInDT->format('Y-m-d'),
            $offerCheckOutDT->format('Y-m-d'),
            $priceGross,
            $adults,
            $childrenAges,
            $offerCurrency,
            $availability,
            $priceNet,
            $priceInitial,
            $comission,
            $roomTypeId,
            $roomName,
            $roomInfo,
            $exclamationMark,
            $mealName,
            $bookingDataJson
        );
    }


    public function jsonSerialize(): array
    {
        $room = [
            'Id' => $this->roomId,
            'Availability' => $this->availability,
            'CheckinAfter' => $this->offerCheckIn,
            'CheckinBefore' => $this->offerCheckOut,
            'Currency' => ['Code' => $this->currency],
            'Code' => $this->roomTypeId,
            'Merch' => [
                'Code' => $this->roomId,
                'Id' => $this->roomId,
                'Name' => $this->roomName,
                'Title' => $this->roomName,
                'Type' => [
                    'Id' => $this->roomTypeId,
                    'Title' => $this->roomName
                ]
            ]
        ];

        if ($this->roomInfo !== null) {
			$roomInfo = substr($this->roomInfo, 0, 255);

			if (strlen($roomInfo) === 255) {
				$roomInfo .= '...';
			}
			$room['InfoTitle'] = $roomInfo;
		}
		
		if (!empty($this->exclamationMark)) {
			$room['InfoDescription'] = $this->exclamationMark;
		}

        $mealItem = [
            'Currency' => ['Code' => $this->currency],
            'Merch' => [
                'Id' => $this->mealId,
                'Title' => $this->mealName,
                'Type' => [
                    'Id' => $this->mealId,
                    'Title' => $this->mealName
                ]
            ]
        ];


        $dtir = [
            'Merch' => [
                'Title' => 'CheckOut: ' . (new DateTime($this->offerCheckOut))->format('d.m.Y')
            ],
            'Currency' => ['Code' => $this->currency],
            'DepartureDate' => $this->offerCheckOut,
            'ArrivalDate' => $this->offerCheckOut
        ];

        $dti = [
            'Merch' => [
                'Title' => 'CheckIn: ' . (new DateTime($this->offerCheckIn))->format('d.m.Y')
            ],
            'Currency' => ['Code' => $this->currency],
            'DepartureDate' => $this->offerCheckIn,
            'ArrivalDate' => $this->offerCheckIn,
            'Return' => $dtir
        ];
        
        $array = [
            'Code' => $this->hotelId . '~' . $this->roomId . '~' . $this->mealId . '~' . $this->offerCheckIn . '~' . $this->offerCheckOut . '~' . $this->priceGross . '~' . $this->adults . ($this->childrenAges ? '~' . implode('|', $this->childrenAges) : ''),
            'Currency' => ['Code' => $this->currency],
            'Availability' => $this->availability,
            'Net' => $this->priceNet,
            'InitialPrice' => $this->priceInitial,
            'Gross' => $this->priceGross,
            'Comission' => $this->comission,
            'Item' => $room,
            'Rooms' => [$room],
            'MealItem' => $mealItem,
            'DepartureTransportItem' => $dti,
            'ReturnTransportItem' => $dtir,
            'bookingDataJson' => $this->bookingDataJson
        ];

       return $array;
    }
}