<?php
declare(strict_types = 1);


namespace CustomBonusSystem\Core\Bonus\Calculation;

use CustomBonusSystem\Core\Bonus\Calculation\Condition\Condition;
use CustomBonusSystem\Core\Bonus\Calculation\Condition\ConditionService;
use CustomBonusSystem\Core\Bonus\Calculation\Struct\CalculationStruct;
use CustomBonusSystem\Core\Bonus\ConfigData;
use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Entity\Bonus\BonusConditionEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CalculationService
{
    final public const ROUND_CEIL = 0;
    final public const ROUND_NATURAL = 1;
    final public const ROUND_FLOOR = 2;

    /**
     * @var CurrencyCalculationService
     */
    private $currencyCalculationService;

    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    private readonly ConfigService $configService;

    public function __construct(
        CurrencyCalculationService $defaultCurrencyCalculationService,
        private readonly ConditionService $conditionService,
        QuantityPriceCalculator $quantityPriceCalculator,
        ConfigService $configService
    ) {
        $this->currencyCalculationService = $defaultCurrencyCalculationService;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
        $this->configService = $configService;
    }

    public function buildCalculationStruct($wantToRedeem,
                                           $pointRedeem,
                                           $hasPoints,
                                           $basketAmountRedeemRestriction,
                                           $basketAmountRedeemRestrictionValue,
                                           $bonusSystemConversionFactorRedeem,
                                           $disallowRedeemPoints,
                                           $collectPointsWithoutShippingCosts,
                                           $collectPointsRound,
                                           $cart,
                                           $context,
                                           $defaultBonusSystemConversionFactor = 0
    )
    {

        $bonusSystemConversionFactorCurrencyRedeem = 0;
        if ($bonusSystemConversionFactorRedeem && !$disallowRedeemPoints) {
            $bonusSystemConversionFactorRedeem = $this->getConversionFactorRedeem($context->getCustomer(), $bonusSystemConversionFactorRedeem);
        }

        $wantToRedeemBasketDiscountType = $pointRedeem['points'];

        if ($context->getCurrentCustomerGroup()->getDisplayGross()) {
            //if ($settingVars['grossPricesForCalculation']) {
            // gross price calculation
            $totalPrice = $cart->getPrice()->getTotalPrice();
        } else {
            // net price calculation
            $totalPrice = $cart->getPrice()->getNetPrice();
        }

        $pointsPossibleAmount = 0;
        if ($bonusSystemConversionFactorRedeem) {
            $pointsPossibleAmount = $this->calculateDiscountForBonusPoints(
                $hasPoints,
                $bonusSystemConversionFactorRedeem,
                $context,
                true
            );
        }
        $availableBasketAmountForRedeemPoints = $this->getAvailableBasketAmountForRedeemPoints(
        //$totalPriceDefaultCurrency,
            $totalPrice,
            $basketAmountRedeemRestriction,
            $basketAmountRedeemRestrictionValue,
            $pointsPossibleAmount,
            $cart,
            $context
        );

        $oneAmount = $this->getCurrencyCalculationService()->calculateToCurrencyPrice($context->getCurrency(), 1.00);
        $factorFor1Amount = 0;
        $maxRedeemPoints = 0;
        if ($bonusSystemConversionFactorRedeem) {
            $factorFor1Amount = $this->calculateBonusPointsForAmount(
                1,
                $bonusSystemConversionFactorRedeem,
                $context
            );

            if ($pointsPossibleAmount < $availableBasketAmountForRedeemPoints) {
                $maxRedeemPoints = $this->calculateBonusPointsForAmount(
                    $pointsPossibleAmount,
                    $bonusSystemConversionFactorRedeem,
                    $context
                );
            } else {
                $maxRedeemPoints = $this->calculateBonusPointsForAmount(
                    $availableBasketAmountForRedeemPoints,
                    $bonusSystemConversionFactorRedeem,
                    $context
                );
            }
        }

        if ($bonusSystemConversionFactorRedeem && !$disallowRedeemPoints) {
            $bonusSystemConversionFactorCurrencyRedeem = $this->getCurrencyCalculationService()->calculateConversionFactor($context->getCurrency(), $bonusSystemConversionFactorRedeem, $factorFor1Amount, $oneAmount);
        }

        // Remove shipping costs from calculation, if shipping costs excluded by plugin option
        $shippingPrice = $cart->getShippingCosts()->getTotalPrice();
        if ($collectPointsWithoutShippingCosts) {
            $shippingPrice = 0;
        }

        $getPoints = $this->calculateBonusPointsWithConditions(
            $cart->getLineItems(),
            $context,
            true,
            (int) $collectPointsRound,
            $defaultBonusSystemConversionFactor,
            $shippingPrice
        );
        $wantToRedeemBonusProductDiscountType = $wantToRedeem - $wantToRedeemBasketDiscountType;

        $calculationStruct = new CalculationStruct();
        $calculationStruct->setAvailableBasketAmountForRedeemPoints($availableBasketAmountForRedeemPoints);
        $calculationStruct->setBonusSystemConversionFactorCurrencyRedeem($bonusSystemConversionFactorCurrencyRedeem);
        $calculationStruct->setBonusSystemConversionFactorRedeem($bonusSystemConversionFactorRedeem);
        $calculationStruct->setFactorFor1Amount($factorFor1Amount);
        $calculationStruct->setGetPoints($getPoints);
        $calculationStruct->setHasPoints($hasPoints);
        $calculationStruct->setMaxRedeemPoints($maxRedeemPoints);
        $calculationStruct->setOneAmount($oneAmount);
        $calculationStruct->setPointsPossibleAmount($pointsPossibleAmount);
        $calculationStruct->setWantToRedeem($wantToRedeem);
        $calculationStruct->setWantToRedeemBasketDiscountType($wantToRedeemBasketDiscountType);
        $calculationStruct->setWantToRedeemBonusProductDiscountType($wantToRedeemBonusProductDiscountType);
        $calculationStruct->setBasketAmountRedeemRestriction($basketAmountRedeemRestriction);
        $calculationStruct->setBasketAmountRedeemRestrictionValue($basketAmountRedeemRestrictionValue);

        return $calculationStruct;
    }

    /**
     * @return CurrencyCalculationService
     */
    public function getCurrencyCalculationService()
    {
        return $this->currencyCalculationService;
    }

    /**
     * Return calculated bonus points for a given amount
     * @TODO: Check if used and needed
     * @param $amount
     * @param $bonusSystemConversionFactor
     * @param CurrencyEntity $currency
     * @return int
     */
    public function calculateBonusPointsForAmountByCurrency($amount, $bonusSystemConversionFactor, CurrencyEntity $currency)
    {
        $amount = $this->currencyCalculationService->calculateToDefaultPrice($currency, $amount);
        return (int)round($bonusSystemConversionFactor * $amount);
    }

    /**
     * Return calculated bonus points for a given amount
     * @param float $amount
     * @param float $bonusSystemConversionFactor
     * @param SalesChannelContext $context
     * @param false $currencyConversion
     * @return float
     */
    public function calculateBonusPointsForAmount($amount, $bonusSystemConversionFactor, SalesChannelContext $context, $currencyConversion = false, $round = 0)
    {
        if ($currencyConversion) {
            $amount = $this->currencyCalculationService->calculateToDefaultPrice($context->getCurrency(), $amount);
        }

        //$bonusPoints = (round($bonusSystemConversionFactor * $amount, 2));
        if ($round == self::ROUND_CEIL) {
            // Ceil instead of round, because to serve´0 EUR baskets
            $bonusPoints = ceil($bonusSystemConversionFactor * $amount);
        } elseif ($round == self::ROUND_NATURAL) {
            $bonusPoints = round($bonusSystemConversionFactor * $amount);
        } elseif ($round == self::ROUND_FLOOR) {
            $bonusPoints = floor($bonusSystemConversionFactor * $amount);
        }

        return $bonusPoints;
    }

    /**
     * Return discount for given bonus points
     * @param int $bonusPoints
     * @param float $bonusSystemConversionFactor
     * @param SalesChannelContext $context
     * @param false $currencyConversion
     * @return float
     */
    public function calculateDiscountForBonusPoints($bonusPoints, $bonusSystemConversionFactor, SalesChannelContext $context, $currencyConversion = false)
    {
        $amount = $bonusPoints * (1 / $bonusSystemConversionFactor);

        if ($currencyConversion) {
            $amount = $this->currencyCalculationService->calculateToCurrencyPrice($context->getCurrency(), $amount);
        }

        return (float)round($amount, 4);
    }

    /**
     * Get conversion factor redeem. Check if there is a condition with an individual factor for user.
     * If not then use default factor transmitted by plugin settings.
     *
     * @param CustomerEntity $customer
     * @param int $defaultBonusSystemConversionFactorRedeem
     * @return float|mixed
     */
    public function getConversionFactorRedeem(CustomerEntity $customer = null, $defaultBonusSystemConversionFactorRedeem = 0)
    {
        return $this->getConversionFactor(BonusConditionEntity::SUB_TYPE_CONVERSION_FACTOR_REDEEM, $customer, $defaultBonusSystemConversionFactorRedeem);
    }

    /**
     * Get conversion factor collect. Check if there is a condition with an individual factor for user.
     * If not then use default factor transmitted by plugin settings.
     *
     * @param CustomerEntity $customer
     * @param int $defaultBonusSystemConversionFactorCollect
     * @return float|mixed
     */
    public function getConversionFactorCollect(CustomerEntity $customer = null, $defaultBonusSystemConversionFactorCollect = 0, $product = null)
    {
        return $this->getConversionFactor(BonusConditionEntity::SUB_TYPE_CONVERSION_FACTOR_COLLECT, $customer, $defaultBonusSystemConversionFactorCollect, $product);
    }

    /**
     * Get conversion factor by subType collect or redeem. Check if there is a condition with an individual factor for user.
     * If not then use default factor transmitted by plugin settings.
     *
     * @param $subType
     * @param CustomerEntity|null $customer
     * @param int $defaultBonusSystemConversionFactor
     * @param null $product
     * @return float|mixed
     */
    protected function getConversionFactor($subType, CustomerEntity $customer = null, $defaultBonusSystemConversionFactor = 0, $product = null)
    {
        $conditions = $this->conditionService->determineConditionsForConversionFactor($customer, $product, $subType);
        if ($conditions && $conditions->getElements()) {
            $conditions = $conditions->sortByFactor('desc');

            /**
             * Return only condition with the highest factor
             * @var Condition $condition
             */
            $condition = reset($conditions);
            return $condition->getFactor();
        }

        return $defaultBonusSystemConversionFactor;
    }

    /**
     * Checks whether there are conditions through which individual elements of the shopping cart can not be used
     * to calculate for bonus points. Return bonus points.
     *
     * @param float $amount
     * @param float $bonusSystemConversionFactor
     * @param SalesChannelContext $context
     * @param false $currencyConversion
     * @return float
     */
    public function calculateAvailableBasketAmountWithConditions(
        $amount,
        $lineItems,
        SalesChannelContext $context,
        $currencyConversion = false
    ) {
        $config = $this->configService->getConfig($context);

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            if (
                $lineItem->getType() == LineItem::PRODUCT_LINE_ITEM_TYPE ||
                $lineItem->getType() == 'dvsn-set-configurator' ||
                ($lineItem->getType() == LineItem::CUSTOM_LINE_ITEM_TYPE)) {
                // Detect all conditions matching for current lineItem
                $match = $this->conditionService->conditionsExcludePointsForLineItemMatch($lineItem, BonusConditionEntity::SUB_TYPE_EXCLUDE_FOR_REDEEM);

                // If lineItem is excluded by one or more conditions, then don't calculate bonus points for it
                if ($match) {
                    $amount -= $lineItem->getPrice()->getTotalPrice();
                }
            }
        }

        if ($currencyConversion) {
            $amount = $this->currencyCalculationService->calculateToDefaultPrice($context->getCurrency(), $amount);
        }
        return $amount;
    }

    /**
     * Calculate high of basket amount that is allowed for point redemption.
     * Maybe there's a plugin setting restriction for max allowed basket amount or for a min order value.
     * Or some products are excluded from point redemption by conditions
     *
     * @param $totalPriceDefaultCurrency
     * @param $basketAmountRedeemRestriction
     * @param $basketAmountRedeemRestrictionValue
     * @param $pointsPossibleAmount
     * @param $cart
     * @param $context
     * @return mixed
     */
    public function getAvailableBasketAmountForRedeemPoints(
        $totalPriceDefaultCurrency,
        $basketAmountRedeemRestriction,
        $basketAmountRedeemRestrictionValue,
        $pointsPossibleAmount,
        $cart,
        $context,
        $currencyConversion = true
    ) {
        $availableBasketAmountForRedeemPoints = $totalPriceDefaultCurrency;
        $availableBasketAmountForRedeemPoints = $this->calculateAvailableBasketAmountWithConditions(
            $availableBasketAmountForRedeemPoints,
            $cart->getLineItems(),
            $context,
            $currencyConversion
        );

        // Is there a min order value before point redemption is allowed?
        if ($basketAmountRedeemRestriction == ConfigData::BASKET_AMOUNT_REDEEM_RESTRICTION_MIN_ORDER_VALUE) {
            if ($basketAmountRedeemRestrictionValue >= $availableBasketAmountForRedeemPoints) {
                $availableBasketAmountForRedeemPoints = 0;
            } else {
                $availableBasketAmountForRedeemPoints -= $basketAmountRedeemRestrictionValue;
            }
        }
        if ($pointsPossibleAmount < $availableBasketAmountForRedeemPoints) {
            //echo "pointsPossibleAmount: $pointsPossibleAmount<br />";
            $availableBasketAmountForRedeemPoints = $pointsPossibleAmount;
        }
        if ($basketAmountRedeemRestriction == ConfigData::BASKET_AMOUNT_REDEEM_RESTRICTION_MAX_VALUE && $totalPriceDefaultCurrency > $basketAmountRedeemRestrictionValue) {
            $availableBasketAmountForRedeemPoints = $basketAmountRedeemRestrictionValue;
        }

        //echo "availableBasketAmountForRedeemPoints nach condition: $availableBasketAmountForRedeemPoints<br />";
        return $availableBasketAmountForRedeemPoints;
    }

    /**
     * Checks whether there are conditions through which individual elements of the shopping cart can not be used
     * to calculate for bonus points. Return bonus points.
     *
     * @param LineItemCollection $lineItems
     * @param SalesChannelContext $context
     */
    public function calculateBonusPointsWithConditions(
        LineItemCollection $lineItems,
        SalesChannelContext $context,
        bool $currencyConversion = false,
        int $roundType = 0,
        float|int $defaultBonusSystemConversionFactor = 0,
        float|int $customAmountToCalculate = 0
    ): float|int
    {
        $discountAmount = $this->getSumOfDiscountLineItemsAmount($lineItems);
        $lineItemsWithoutDiscount = $this->getLineItemsWithoutDiscountPrice($lineItems);

        $totalPriceWithoutDiscount = 0;
        $lineItemsWithoutDiscount->filter(function (LineItem $lineItem) use (&$totalPriceWithoutDiscount) {
            $totalPriceWithoutDiscount += $lineItem->getPrice()->getTotalPrice();
        });

        $config = $this->configService->getConfig($context);

        $points = 0;
        foreach ($lineItemsWithoutDiscount as $lineItem) {

            $isExcludedProduct = $this->conditionService->conditionsExcludePointsForLineItemMatch($lineItem, BonusConditionEntity::SUB_TYPE_EXCLUDE_FOR_COLLECT);
            if (
                !$isExcludedProduct &&
                $lineItem->getType() == LineItem::PRODUCT_LINE_ITEM_TYPE ||
                $lineItem->getType() == 'dvsn-set-configurator' ||
                ($lineItem->getType() == LineItem::CUSTOM_LINE_ITEM_TYPE)) {
                $discount = ($lineItem->getPrice()->getTotalPrice() / $totalPriceWithoutDiscount) * $discountAmount;
                $amount = $lineItem->getPrice()->getTotalPrice() - $discount;

                if ($currencyConversion) {
                    $amount = $this->currencyCalculationService->calculateToDefaultPrice($context->getCurrency(), $amount);
                }

                $conversionFactor = $this->getConversionFactor(
                    BonusConditionEntity::SUB_TYPE_CONVERSION_FACTOR_COLLECT,
                    $context->getCustomer(),
                    $defaultBonusSystemConversionFactor,
                    $lineItem
                );

                $points += $this->calculateAndRoundPoints($amount, $conversionFactor, $roundType);
            }
        }

        // calculate also some custom amount, example a sipping costs
        if ($customAmountToCalculate) {
            $conversionFactor = $this->getConversionFactor(
                BonusConditionEntity::SUB_TYPE_CONVERSION_FACTOR_COLLECT,
                $context->getCustomer(),
                $defaultBonusSystemConversionFactor
            );
            $points += $this->calculateAndRoundPoints($customAmountToCalculate, $conversionFactor, $roundType);
        }

        return $points;
    }

    /**
     * Calculates product price by quantity
     * Calculates also price depending on advanced pricing.
     *
     * @param SalesChannelProductEntity $product
     * @param SalesChannelContext $context
     * @return CalculatedPrice
     */
    public function calculateProductPriceByQuantity(SalesChannelProductEntity $product, int $quantity, SalesChannelContext $context): CalculatedPrice
    {
        if ($product->getCalculatedPrices()->count() === 0) {
            $definition = $this->buildPriceDefinition($product->getCalculatedPrice(), $quantity);
            return $this->quantityPriceCalculator->calculate($definition, $context);
        }

        $price = $product->getCalculatedPrice();
        foreach ($product->getCalculatedPrices() as $price) {
            if ($quantity <= $price->getQuantity()) {
                break;
            }
        }

        $definition = $this->buildPriceDefinition($price, $quantity);
        return $this->quantityPriceCalculator->calculate($definition, $context);
    }

    /**
     * Build price definition
     *
     * @param CalculatedPrice $price
     * @return QuantityPriceDefinition
     */
    private function buildPriceDefinition(CalculatedPrice $price, int $quantity): QuantityPriceDefinition
    {
        $definition = new QuantityPriceDefinition($price->getUnitPrice(), $price->getTaxRules(), $quantity);
        if ($price->getListPrice() !== null) {
            $definition->setListPrice($price->getListPrice()->getPrice());
        }

        if ($price->getReferencePrice() !== null) {
            $definition->setReferencePriceDefinition(
                new ReferencePriceDefinition(
                    $price->getReferencePrice()->getPurchaseUnit(),
                    $price->getReferencePrice()->getReferenceUnit(),
                    $price->getReferencePrice()->getUnitName()
                )
            );
        }

        return $definition;
    }

    /**
     * Returns sum of discount line items amount
     *
     * @param LineItemCollection $lineItems
     */
    private function getSumOfDiscountLineItemsAmount(LineItemCollection $lineItems): float|int
    {
        $amount = 0;
        $lineItems->filter(function (LineItem $lineItem) use (&$amount) {
            if ($lineItem->getPrice()->getTotalPrice() < 0) {
                $amount += $lineItem->getPrice()->getTotalPrice();
            }
        });

        return $amount * (-1);
    }

    /**
     * Returns the line items without discount (negative) price
     *
     * @param LineItemCollection $lineItems
     * @return LineItemCollection
     */
    private function getLineItemsWithoutDiscountPrice(LineItemCollection $lineItems): LineItemCollection
    {
        return $lineItems->filter(fn(LineItem $lineItem) => $lineItem->getPrice()->getTotalPrice() > 0);
    }

    /**
     * Returns calculated and rounded points
     *
     * @param $amount
     * @param $conversionFactor
     * @param $roundType
     */
    private function calculateAndRoundPoints($amount, $conversionFactor, $roundType): float|int
    {
        $amount = ($conversionFactor * $amount);
        return $this->roundValueByRoundType($amount, $roundType);
    }

    /**
     * @param $value
     * @param $roundType
     * @return int|mixed
     */
    private function roundValueByRoundType($value, $roundType)
    {
        return match ($roundType) {
            self::ROUND_CEIL => (int) ceil($value),
            self::ROUND_NATURAL => (int) round($value),
            self::ROUND_FLOOR => (int) floor($value),
            default => $value,
        };
    }
}
