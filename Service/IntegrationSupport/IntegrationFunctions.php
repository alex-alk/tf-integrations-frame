<?php

namespace IntegrationSupport;

use App\Entities\Availability\AirportTaxesCategory;
use App\Entities\Availability\AirportTaxesItem;
use App\Entities\Availability\AirportTaxesMerch;
use App\Entities\Availability\Offer;
use App\Entities\Availability\TransferCategory;
use App\Entities\Availability\TransferItem;
use App\Entities\Availability\TransferMerch;

class IntegrationFunctions
{
    public static function getApiTransferItem(Offer $offer, TransferCategory $category, ?string $code = null): TransferItem
	{
		$transferMerch = new TransferMerch();
		$transferMerch->Code = (env('APP_ENV') === 'local') ? '' : uniqid();
		$transferMerch->Category = $category;
		$transferMerch->Title = TransferMerch::TITLE;
		$transferItem = new TransferItem();
		$transferItem->Merch = $transferMerch;
		$transferItem->Currency = $offer->Currency;
		$transferItem->Quantity = 1;
		$transferItem->UnitPrice = 0;
		$transferItem->Availability = TransferItem::AVAILABILITY_YES;
		$transferItem->Gross = 0;
		$transferItem->Net = 0;
		$transferItem->InitialPrice = 0;
		return $transferItem;
	}

    public static function getApiAirpotTaxesItem(Offer $offer, AirportTaxesCategory $category, ?string $code = null): AirportTaxesItem
	{
		$airportTaxesMerch = new AirportTaxesMerch();
		$airportTaxesMerch->Title = AirportTaxesMerch::TITLE;
		$airportTaxesMerch->Code = (env('APP_ENV') === 'local') ? '' : uniqid();
		$airportTaxesMerch->Category = $category;
		$airportTaxesItem = new AirportTaxesItem();
		$airportTaxesItem->Merch = $airportTaxesMerch;
		$airportTaxesItem->Currency = $offer->Currency;
		$airportTaxesItem->Quantity = 1;
		$airportTaxesItem->UnitPrice = 0;
		$airportTaxesItem->Availability = AirportTaxesItem::AVAILABILITY_YES;
		$airportTaxesItem->Gross = 0;
		$airportTaxesItem->Net = 0;
		$airportTaxesItem->InitialPrice = 0;
		return $airportTaxesItem;
	}
}