<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;

interface BonusProductCartServiceInterface
{
    public function createBonusProductLineItem(int $points, string $productId, int $productQuantity): LineItem;
}
