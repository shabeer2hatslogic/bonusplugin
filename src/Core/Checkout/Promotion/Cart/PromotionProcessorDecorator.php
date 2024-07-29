<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;

class PromotionProcessorDecorator
{
    /**
     * @var PromotionProcessor
     */
    protected PromotionProcessor $promotionProcessor;

    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @param PromotionProcessor $promotionProcessor
     * @param ConfigService $configService
     */
    public function __construct(PromotionProcessor $promotionProcessor, ConfigService $configService)
    {
        $this->promotionProcessor = $promotionProcessor;
        $this->configService = $configService;
    }

    /**
     * @param CartDataCollection $data
     * @param Cart $original
     * @param Cart $toCalculate
     * @param SalesChannelContext $context
     * @param CartBehavior $behavior
     * @return void
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bonusDiscounts = $toCalculate->getLineItems()->filterType('custom_bonus_system');

        if ($this->shouldSkipPromotion($data, $toCalculate, $bonusDiscounts, $context)) {
            return;
        }

        if (!$bonusDiscounts->count()) {
            $this->promotionProcessor->process($data, $original, $toCalculate, $context, $behavior);
            return;
        }

        $oldPrices = [];
        $shippingCosts = $this->getTotalShippingCosts($original->getDeliveries());

        // Remove shipping costs from bonus discounts to calculate the correct promotion discount
        /** @var LineItem $bonusDiscount */
        foreach ($bonusDiscounts as $bonusDiscount) {
            $price = $bonusDiscount->getPrice();
            $oldPrices[$bonusDiscount->getId()] = clone $bonusDiscount->getPrice();

            $price->assign([
                'totalPrice' => $price->getTotalPrice() + $shippingCosts,
                'unitPrice' => $price->getUnitPrice() + $shippingCosts
            ]);
        }

        $this->promotionProcessor->process($data, $original, $toCalculate, $context, $behavior);

        // Reset prices after promotion discount calculation
        /** @var LineItem $bonusDiscount */
        foreach ($bonusDiscounts as $bonusDiscount) {
            $bonusDiscount->setPrice($oldPrices[$bonusDiscount->getId()]);
        }
    }

    /**
     * @param DeliveryCollection $deliveries
     * @return float
     */
    protected function getTotalShippingCosts(DeliveryCollection $deliveries): float
    {
        $shippingCosts = 0;
        foreach ($deliveries as $delivery) {
            $shippingCosts += $delivery->getShippingCosts()->getTotalPrice();
        }

        return $shippingCosts;
    }

    /**
     * Should skip a promotion
     *
     * @param CartDataCollection $data
     * @param Cart $toCalculate
     * @param LineItemCollection $bonusDiscounts
     * @param SalesChannelContext $context
     * @return bool
     */
    protected function shouldSkipPromotion(CartDataCollection $data, Cart $toCalculate, LineItemCollection $bonusDiscounts, SalesChannelContext $context): bool
    {
        $pluginConfig    = $this->configService->getConfig($context);
        $useBonusSystem  = $pluginConfig->isUseBonusSystem();
        $disableVouchers = $pluginConfig->isDisableVouchersWhenPointsAreInBasket();

        if ($data->has('promotions') && $useBonusSystem && $disableVouchers && ($this->containsBonusProduct($toCalculate) || $bonusDiscounts->count() > 0)) {

            return true;
        }

        return false;
    }

    /**
     * Check if cart contains a bonus product
     *
     * @param Cart $cart
     * @return bool
     */
    protected function containsBonusProduct(Cart $cart): bool
    {
        $payloads = $cart->getLineItems()->getPayload();
        foreach ($payloads as $payload) {
            if (isset($payload['buyWithPoints']) && $payload['buyWithPoints']) {
                return true;
            }
        }

        return false;
    }
}