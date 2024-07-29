<?php

namespace CustomBonusSystem\Core\Checkout\Bonus;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BonusDiscountCalculator
{
    /**
     * @var DiscountPackager
     */
    private readonly DiscountPackager $discountPackager;

    /**
     * @var AbsolutePriceCalculator
     */
    private readonly AbsolutePriceCalculator $absolutePriceCalculator;

    /**
     * @var LineItemQuantitySplitter
     */
    private readonly LineItemQuantitySplitter $lineItemQuantitySplitter;

    /**
     * @param DiscountPackager $discountPackager
     * @param AbsolutePriceCalculator $absolutePriceCalculator
     * @param LineItemQuantitySplitter $lineItemQuantitySplitter
     */
    public function __construct(
        DiscountPackager $discountPackager,
        AbsolutePriceCalculator $absolutePriceCalculator,
        LineItemQuantitySplitter $lineItemQuantitySplitter
    )
    {
        $this->discountPackager = $discountPackager;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->lineItemQuantitySplitter = $lineItemQuantitySplitter;
    }

    /**
     * @param LineItemCollection $discountLineItems
     * @param Cart $toCalculate
     * @param SalesChannelContext $context
     * @return void
     */
    public function calculate(LineItemCollection $discountLineItems, Cart $toCalculate, SalesChannelContext $context)
    {
        foreach ($discountLineItems as $lineItem) {
            $price = $this->calculateDiscountPrice($lineItem, $toCalculate, $context);

            $lineItem->setPrice($price);
            $toCalculate->add($lineItem);
        }
    }

    /**
     * @param LineItem $lineItem
     * @param Cart $calculatedCart
     * @param SalesChannelContext $context
     * @return CalculatedPrice
     */
    private function calculateDiscountPrice(LineItem $lineItem, Cart $calculatedCart, SalesChannelContext $context): CalculatedPrice
    {
        $discount = new DiscountLineItem(
            $lineItem->getLabel(),
            $lineItem->getPriceDefinition(),
            $lineItem->getPayload(),
            $lineItem->getReferencedId()
        );

        $packages = $this->discountPackager->getMatchingItems($discount, $calculatedCart, $context);
        $packages = $this->enrichPackagesWithCartData($packages, $calculatedCart, $context);
        return $this->absolutePriceCalculator->calculate($lineItem->getPrice()->getTotalPrice(), $packages->getAffectedPrices(), $context);
    }

    /**
     * @param DiscountPackageCollection $result
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return DiscountPackageCollection
     */
    private function enrichPackagesWithCartData(DiscountPackageCollection $result, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        // set the line item from the cart for each unit
        foreach ($result as $package) {
            $cartItemsForUnit = new LineItemFlatCollection();

            foreach ($package->getMetaData() as $item) {
                /** @var LineItem $cartItem */
                $cartItem = $cart->get($item->getLineItemId());

                $cartItem->setStackable(true);

                // create a new item with only a quantity of x
                // including calculated price for our original cart item
                $qtyItem = $this->lineItemQuantitySplitter->split($cartItem, $item->getQuantity(), $context);

                // add the single item to our unit
                $cartItemsForUnit->add($qtyItem);
            }

            $package->setCartItems($cartItemsForUnit);
        }

        return $result;
    }

}