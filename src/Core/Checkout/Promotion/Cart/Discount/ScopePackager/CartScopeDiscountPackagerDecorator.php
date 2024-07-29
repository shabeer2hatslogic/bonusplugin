<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;

class CartScopeDiscountPackagerDecorator extends DiscountPackager
{
    /**
     * @var DiscountPackager
     */
    protected DiscountPackager $cartScopeDiscountPackager;

    /**
     * @param DiscountPackager $cartScopeDiscountPackager
     */
    public function __construct(DiscountPackager $cartScopeDiscountPackager)
    {
        $this->cartScopeDiscountPackager = $cartScopeDiscountPackager;
    }

    /**
     * @return DiscountPackager
     */
    public function getDecorated(): DiscountPackager
    {
        return $this->cartScopeDiscountPackager;
    }

    /**
     * @param DiscountLineItem $discount
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return DiscountPackageCollection
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        $allItems = $cart->getLineItems()->filterType('custom_bonus_system');
        $discountPackageCollection = $this->getDecorated()->getMatchingItems($discount, $cart, $context);

        foreach ($allItems as $item) {
            $itemQuantity = new LineItemQuantity($item->getId(), 1);
            $discountPackageCollection->first()->getMetaData()->add($itemQuantity);
        }

        return $discountPackageCollection;
    }
}