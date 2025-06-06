<?php

namespace IntegrationTraits;

trait EmptyBoardItemTrait
{
    public function getEmptyBoardItem($offer)
    {
        // board
        $boardType = new \stdClass();
        $boardType->Id = "no_meal";
        $boardType->Title = "Fara masa";
        $boardMerch = new \stdClass();
        //$boardMerch->Id = $roomOffer->mealKey;
        $boardMerch->Title = "Fara masa";
        $boardMerch->Type = $boardType;
        $boardItm = new \stdClass();
        $boardItm->Merch = $boardMerch;
        $boardItm->Currency = $offer->Currency;
        $boardItm->Quantity = 1;
        $boardItm->UnitPrice = 0;
        $boardItm->Gross = 0;
        $boardItm->Net = 0;
        $boardItm->InitialPrice = 0;
        // for identify purpose
        //$boardItm->Id = $boardMerch->Id;
        $boardItm->Id = null;
        return $boardItm;
    }

}
