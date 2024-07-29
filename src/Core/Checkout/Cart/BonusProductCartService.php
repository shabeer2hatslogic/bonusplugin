<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;

class BonusProductCartService implements BonusProductCartServiceInterface
{
    public function createBonusProductLineItem(
        int $points,
        string $productId,
        int $productQuantity
    ): LineItem {
        $bonusProductLineItem = new LineItem(
            Uuid::randomHex(),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $productId,
            $productQuantity
        );
        $bonusProductLineItem->setRemovable(true);

        $bonusProductLineItem->setPayloadValue('CustomBonusSystem', $points);

        return $bonusProductLineItem;
    }
}
