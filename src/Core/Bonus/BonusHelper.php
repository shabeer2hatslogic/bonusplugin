<?php

namespace CustomBonusSystem\Core\Bonus;

use CustomBonusSystem\Core\Entity\Bonus\BonusBookingCollection;

class BonusHelper
{
    public static function getCountForCollectionPoints(BonusBookingCollection $points)
    {
        $redeemedSum = 0;
        $getPointSum = 0;

        $getPoints = $points->filter(
            fn($booking) => $booking->getPoints() > 0
        );
        $redeemedPoints = $points->filter(
            fn($booking) => $booking->getPoints() < 0
        );

        foreach ($redeemedPoints as $redeemedPoint) {
            $redeemedSum += $redeemedPoint->getPoints();
        }

        foreach ($getPoints as $getPoint) {
            $getPointSum += $getPoint->getPoints();
        }

        return [
            'redeemed' => $redeemedSum,
            'get' => $getPointSum
        ];
    }
}
