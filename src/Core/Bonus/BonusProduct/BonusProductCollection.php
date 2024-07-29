<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\BonusProduct;

use Shopware\Core\Framework\Struct\Collection;
use CustomBonusSystem\Core\Bonus\BonusProduct\BonusProduct;

class BonusProductCollection extends Collection
{
    public function filterProductId(string $productId): BonusProductCollection
    {
        return $this->filter(
            fn(BonusProduct $bonusProduct) => $bonusProduct->getProductId() === $productId
        );
    }

    public function filterOutdated(): BonusProductCollection
    {
        $today = new \DateTime();

        return $this->filter(
            function (BonusProduct $bonusProduct) use ($today): bool {
                $validFrom = $bonusProduct->getValidFrom();
                $validUntil = $bonusProduct->getValidUntil();

                if ($validFrom && $validUntil && ($validFrom > $today || $validUntil < $today)) {
                    return false;
                } elseif ($validFrom && $validFrom > $today) {
                    return false;
                } elseif ($validUntil && $validUntil < $today) {
                    return false;
                }

                return true;
            }
        );
    }
}
