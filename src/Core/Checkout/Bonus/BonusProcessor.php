<?php
declare(strict_types = 1);


namespace CustomBonusSystem\Core\Checkout\Bonus;

use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\ConfigData;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class BonusProcessor
{
    final public const POINT_REDEEM_BASKET_DISCOUNT = 'basketDiscount';

    final public const ROUTES_TO_BE_SKIPPED = [
        'frontend.account.order.single.page'
    ];

    private readonly ConfigService $configService;

    /**
     * @var SessionInterface
     */
    private $sessionService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        ConfigService $configService,
        private readonly CalculationService $calculationService,
        RequestStack $requestStack)
    {
        $this->configService = $configService;
        $this->requestStack = $requestStack;

        // quick fix to prevent errors in a console
        $this->sessionService = new Session();
        if ($requestStack->getMainRequest() && $requestStack->getMainRequest()->hasSession()) {
            $this->sessionService = $requestStack->getMainRequest()->getSession();
        }
    }

    /**
     * Check if point redeem value is valid
     * @param $bonusPointsWantToRedeem
     * @param Cart $cart
     * @param $bonusSystemConversionFactor
     * @param $basketAmountRedeemRestriction
     * @param $basketAmountRedeemRestrictionValue
     * @param $hasPoints
     * @param SalesChannelContext $context
     */
    public function isPointRedeemOk(
        $bonusPointsWantToRedeem,
        Cart $cart,
        $bonusSystemConversionFactor,
        $basketAmountRedeemRestriction,
        $basketAmountRedeemRestrictionValue,
        $hasPoints,
        SalesChannelContext $context
    ): bool {
        if ($bonusPointsWantToRedeem > $hasPoints) {
            return false;
        }

        $settings = $this->configService->getConfig($context);
        $settingVars = $settings->getVars();

        //$discount = $this->calculationService->calculateDiscountForBonusPoints($bonusPointsWantToRedeem, $bonusSystemConversionFactor, $context);

        $lineItems = $cart->getLineItems();

        if ($context->getCurrentCustomerGroup()->getDisplayGross()) {
        //if ($context->getCurrentCustomerGroup()->getDisplayGross() || !$settingVars['grossPricesForCalculation']) {
            $totalPrice = $cart->getShippingCosts()->getTotalPrice();
        } else {
            // Shipping costs total price is net when customer has no gross prices set. Calculate with gross price
            $totalPrice = $cart->getShippingCosts()->getTotalPrice() + $cart->getShippingCosts()->getCalculatedTaxes()->getAmount();
        }


        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            // Don't reduce basket value with current bonus discount
            if ($lineItem->hasExtension('custom_bonus_system_discount')) {
                continue;
            }

            if ($context->getCurrentCustomerGroup()->getDisplayGross()) {
            //if ($context->getCurrentCustomerGroup()->getDisplayGross() || !$settingVars['grossPricesForCalculation']) {
                $totalPrice += $lineItem->getPrice()->getTotalPrice();
            } else {
                // Line item total price is net when customer has no gross prices set. Calculate with gross price
                $totalPrice += $lineItem->getPrice()->getTotalPrice() + $lineItem->getPrice()->getCalculatedTaxes()->getAmount();
            }
        }

        $totalPriceDefaultCurrency = $this->calculationService->getCurrencyCalculationService()->calculateToDefaultPrice($context->getCurrency(), $totalPrice);

        if ($basketAmountRedeemRestriction == ConfigData::BASKET_AMOUNT_REDEEM_RESTRICTION_MIN_ORDER_VALUE) {
            //$availableBasketAmountForRedeemPoints = floor($totalPriceDefaultCurrency - $basketAmountRedeemRestrictionValue);
            $availableBasketAmountForRedeemPoints = $totalPriceDefaultCurrency - $basketAmountRedeemRestrictionValue;
        }
        if ($basketAmountRedeemRestriction == ConfigData::BASKET_AMOUNT_REDEEM_RESTRICTION_MAX_VALUE) {
            if ($basketAmountRedeemRestrictionValue > $totalPriceDefaultCurrency) {
                //$availableBasketAmountForRedeemPoints = floor($totalPriceDefaultCurrency);
                $availableBasketAmountForRedeemPoints = $totalPriceDefaultCurrency;
            } else {
                //$availableBasketAmountForRedeemPoints = floor($basketAmountRedeemRestrictionValue);
                $availableBasketAmountForRedeemPoints = $basketAmountRedeemRestrictionValue;
            }
        }

        $availableBasketAmountForRedeemPoints = $this->calculationService->calculateAvailableBasketAmountWithConditions(
            $availableBasketAmountForRedeemPoints,
            $cart->getLineItems(),
            $context,
            false
        );

        foreach ($lineItems as $lineItem) {
            if (!$lineItem->hasExtension('customBonusSystem')) {
                continue;
            }

            $lineItemPrice = $lineItem->getExtension('customBonusSystem')->get('originalPrice')->getTotalPrice();
            $availableBasketAmountForRedeemPoints += $this->calculationService->getCurrencyCalculationService()->calculateToDefaultPrice($context->getCurrency(), $lineItemPrice);
        }

        // $calculatedMaxBonusPoints for availableBasketAmountForRedeemPoints (points are ceil, so that it is possible to create a 100% discount)
        $calculatedMaxBonusPoints = $this->calculationService->calculateBonusPointsForAmount($availableBasketAmountForRedeemPoints, $bonusSystemConversionFactor, $context);
        return $bonusPointsWantToRedeem <= $calculatedMaxBonusPoints;
    }

    /**
     * Remove points for a specific redeem type
     */
    public function removePointRedeemByType(string $redeemType = self::POINT_REDEEM_BASKET_DISCOUNT)
    {
        if ($this->sessionService->has('custom_bonus_system_redeem_points')) {
            $bonusEntries = $this->sessionService->get('custom_bonus_system_redeem_points');
            unset($bonusEntries[$redeemType]);
            $this->sessionService->set('custom_bonus_system_redeem_points', $bonusEntries);
        }
    }

    /**
     * Store points for a redeem type
     * @param $bonusPoints
     */
    public function storePointRedeem($bonusPoints, string $redeemType = self::POINT_REDEEM_BASKET_DISCOUNT, $amount = 0)
    {
        $bonusEntry = [$redeemType => [
            'points' => $bonusPoints,
            'amount' => $amount
        ]];
        $this->sessionService->set('custom_bonus_system_redeem_points', array_merge($this->sessionService->get('custom_bonus_system_redeem_points', []), $bonusEntry));
    }

    /**
     * Get points customer wants to redeem
     * @return int
     */
    public function getPointRedeem()
    {
        if ($this->sessionService->has('custom_bonus_system_redeem_points')) {
            $bonusEntries = $this->sessionService->get('custom_bonus_system_redeem_points');
            $points = 0;
            foreach ($bonusEntries as $bonusEntry) {
                $points += $bonusEntry['points'];
            }

            return (int)$points;
        }

        return 0;
    }

    public function getPointRedeemByType(string $redeemType = self::POINT_REDEEM_BASKET_DISCOUNT)
    {
        if (!$this->shouldSkipCurrentRoute() && $this->sessionService->has('custom_bonus_system_redeem_points')) {
            $bonusEntries = $this->sessionService->get('custom_bonus_system_redeem_points');
            $points = 0;
            $amount = 0;
            foreach ($bonusEntries as $key => $bonusEntry) {
                if ($key !== $redeemType) {
                    continue;
                }

                $points += $bonusEntry['points'];
                $amount += $bonusEntry['amount'];
            }

            return ['points' => $points, 'amount' => $amount];
        }

        return ['points' => 0, 'amount' => 0];
    }

    public function removePointRedeem()
    {
        $this->sessionService->remove('custom_bonus_system_redeem_points');
    }

    public function removePointRedeemFromCart(Cart $cart)
    {
        /** @var LineItem $lineItem */
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->hasExtension('custom_bonus_system_discount')) {
                $lineItem->setRemovable(true);
                $cart->remove($lineItem->getId());
            }
        }
        $this->removePointRedeemByType(self::POINT_REDEEM_BASKET_DISCOUNT);
    }

    /**
     * @return bool
     */
    public function shouldSkipCurrentRoute(): bool
    {
        if($this->requestStack->getMainRequest() === null) {
            return true;
        }
        $currentRoute = $this->requestStack->getCurrentRequest()->attributes->get('_route') ?: '';
        return in_array($currentRoute, self::ROUTES_TO_BE_SKIPPED, true);
    }
}
