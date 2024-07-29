<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Checkout\Bonus;

use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Doctrine\DBAL\Connection;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BonusCollector implements CartProcessorInterface
{
    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        QuantityPriceCalculator $calculator,
        private readonly CalculationService $calculationService,
        private readonly BonusProcessor $bonusProcessor,
        ConfigService $configService,
        AbstractTranslator $translator,
        private readonly SalesChannelRepository $productRepository,
        Connection $connection,
        private readonly BonusService $bonusService,
        private readonly BonusDiscountCalculator $bonusDiscountCalculator
    ) {
        $this->calculator = $calculator;
        $this->configService = $configService;
        $this->translator = $translator;
        $this->connection = $connection;
    }

    protected function trans(string $snippet, array $parameters = []): string
    {
        return $this->translator
            ->trans($snippet, $parameters);
    }

    private function getTax(
        LineItem $productLineItem,
        SalesChannelContext $salesChannelContext
    ): ?array
    {
        if ($productLineItem->hasChildren()) {
            $products = $productLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
            $productLineItem = $products->first();
            if ($productLineItem === null) {
                return null;
            }
        }

        $productReferenceId = $productLineItem->getReferencedId();

        $product = $this->productRepository->search(
            new Criteria([$productReferenceId]),
            $salesChannelContext
        )->get(
            $productReferenceId
        );

        if (!($product instanceof ProductEntity)) {
            return null;
        }
        return [
            'taxRate' => $product->getTax()->getTaxRate(),
            'taxId' => $product->getTaxId()
        ];
    }

    /**
     * Get Tax ID for a tax_rate
     * @param $rate
     * @return mixed
     */
    private function getTaxIdForRate($rate)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->from('tax')
            ->select('tax.*')
            ->where('tax.tax_rate = :rate')
            ->setParameter('rate', $rate);
        $tax = $queryBuilder->execute()->fetch();

        return Uuid::fromBytesToHex($tax['id']);
    }

    /**
     * Calculate basket discount positions. If there are several vat products, discount need to be seperated. For each
     * var an own discount position. Otherwise, vat is calculated wrong (Discount also needs a vat)
     * @param $grossPrices
     * @param $lineItems
     * @param $bonusPoints
     * @param $discount
     * @param $bonusSystemConversionFactor
     * @param SalesChannelContext $context
     * @return array|void
     */
    protected function getDiscounts($grossPrices, $lineItems, $shippingCosts, $bonusPoints, $discount, $bonusSystemConversionFactor, $context)
    {
        // Determine number of discount positions on basket. If there are multi tax products and discount attaches
        // products with different taxes, then create discount position for each tax rate
        $discounts = [];
        $calculationDiscount = -1 * $discount;

        /** @var LineItem $lineItem */
        foreach($lineItems as $lineItem) {
            if ($lineItem->getPayloadValue('buyWithPoints')) {
                continue;
            }
            if ($bonusPoints <= 0) {
                break(1);
            }

            if ($lineItem->getType() == LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $tax = $lineItem->getPrice()->getCalculatedTaxes()->first();
                if ($tax) {
                    $taxId = (string) $tax->getTaxRate();
                    $taxRate = $tax->getTaxRate();
                }

                // No tax found? Then it's tax free
                if (empty($taxId)) {
                    $taxId = 'taxFree';
                }

                $totalPrice = $lineItem->getPrice()->getTotalPrice();
                // Calculate how many bonus points are needed for a 100% discount for this article
                $requiredBonusPoints = $this->calculationService->calculateBonusPointsForAmount(
                    $totalPrice, $bonusSystemConversionFactor, $context, true);
                $requiredBonusPoints = ceil($requiredBonusPoints);

                // if for a 100% bonus point discount there are not enough points, then use only current sum of bonus points
                $calculatedBonusPoints = $requiredBonusPoints > $bonusPoints ? $bonusPoints : $requiredBonusPoints;
                $bonusPoints -= $calculatedBonusPoints;
                // Is there a discount bonus entry for tax?
                if (!array_key_exists($taxId, $discounts)) {
                    $discounts[$taxId] = [
                        'amount' => 0,
                        'points' => 0
                    ];
                }
                $discounts[$taxId]['amount'] -= $this->calculationService->calculateDiscountForBonusPoints($calculatedBonusPoints, $bonusSystemConversionFactor, $context, true);
                $discounts[$taxId]['points'] +=  $calculatedBonusPoints;
            }
        }

        // Maybe regular basket value is not high enough. Use also shipping costs for calculation
        if ($bonusPoints > 0 && $shippingCosts->getTotalPrice() > 0) {
            $calculatedTaxes = $shippingCosts->getCalculatedTaxes();
            /** @var CalculatedTax $calculatedTax */
            foreach($calculatedTaxes as $calculatedTax) {
                if ($bonusPoints <= 0) {
                    break(1);
                }
//                $taxId = $this->getTaxIdForRate(number_format($calculatedTax->getTaxRate(), 2, '.', ''));
                $taxId = (string) $calculatedTax->getTaxRate();
                $totalPrice = $calculatedTax->getPrice();
                // Calculate how many bonus points are needed for a 100% discount for this article
                $requiredBonusPoints = $this->calculationService->calculateBonusPointsForAmount(
                    $totalPrice, $bonusSystemConversionFactor, $context, true);
                // if for a 100% bonus point discount there are not enough points, then use only current sum of bonus points
                $calculatedBonusPoints = $requiredBonusPoints > $bonusPoints ? $bonusPoints : $requiredBonusPoints;
                $bonusPoints -= $calculatedBonusPoints;
                // Is there a discount bonus entry for tax?
                if (!array_key_exists($taxId, $discounts)) {
                    $discounts[$taxId] = [
                        'amount' => 0,
                        'points' => 0
                    ];
                }
                if ($grossPrices) {
                    $discounts[$taxId]['amount'] -= ($this->calculationService->calculateDiscountForBonusPoints($calculatedBonusPoints, $bonusSystemConversionFactor, $context, true) * ($taxRate / 100 + 1));
                } else {
                    $discounts[$taxId]['amount'] -= ($this->calculationService->calculateDiscountForBonusPoints($calculatedBonusPoints, $bonusSystemConversionFactor, $context, true));
                }
                $discounts[$taxId]['points'] +=  $calculatedBonusPoints;
            }
        }

        // Discount is higher than basket value? Then maybe it's a 100% discount and last bonus point only for a few cents.
        // Remove this few cents from discount, so that there is no negative basket value.
        $discountSum = 0;
        foreach($discounts as $taxId => $discountTaxPosition) {
            $discountSum += $discountTaxPosition['amount'];
            $lastTaxId = $taxId;
        }

        if ($discountSum < $calculationDiscount) {
            if ($grossPrices) {
                $calculationDiscount *= $taxRate / 100 + 1;
            }

            $diff = $discountSum - $calculationDiscount;

            $discounts[$lastTaxId]['amount'] -= $diff;
        }

        return $discounts;
    }

    /**
     * @param CartDataCollection $data
     * @param Cart $original
     * @param Cart $toCalculate
     * @param SalesChannelContext $context
     * @param CartBehavior $behavior
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $settings = $this->configService->getConfig($context);
        $settingVars = $settings->getVars();
        if (!$settingVars['bonusSystemConversionFactorRedeem']) {
            return;
        }
        if ($settingVars['disallowRedeemPoints']) {
            return;
        }

        $customerGroup = $context->getCurrentCustomerGroup();
        $pointRedeem = $this->bonusProcessor->getPointRedeemByType();
        $bonusPoints = $pointRedeem['points'];
        $bonusAmount = $pointRedeem['amount'];

        if ($bonusPoints == 0) {
            return;
        }

        $bonusSystemConversionFactor = $settingVars['bonusSystemConversionFactorRedeem'];
        $bonusSystemConversionFactor = $this->calculationService->getConversionFactorRedeem($context->getCustomer(), $bonusSystemConversionFactor);

        // If bonusAmount is set, discount is already calculated
        if ($bonusAmount > 0) {
            $discount = $bonusAmount;
        } else {
            $discount = $this->calculationService->calculateDiscountForBonusPoints($bonusPoints, $bonusSystemConversionFactor, $context, true);
        }

        if ($discount == 0) {
            return;
        }
        $useGross = true;

        // If bonusAmount is set, the discount is calculated already with tax in gross mode.
        $useGross = $context->getCurrentCustomerGroup()->getDisplayGross() && $bonusAmount == 0;

        $discounts = $this->getDiscounts($useGross, $original->getLineItems(), $original->getShippingCosts(), $bonusPoints, $discount, $bonusSystemConversionFactor, $context);

        $pointsSum = 0;
        $discountLineItemCollection = new LineItemCollection();
        foreach($discounts as $taxId => $calculationDiscount) {
            $discountLineItem = $this->createDiscount(Uuid::randomHex(), $calculationDiscount['points']);
            $pointsSum += $calculationDiscount['points'];
            // declare price definition to define how this price is calculated
            $definition = new QuantityPriceDefinition(
                $calculationDiscount['amount'],
                new TaxRuleCollection(),
                1
            );

            $discountLineItem->setPriceDefinition($definition);

            // calculate price
            $discountLineItem->setPrice(
                $this->calculator->calculate($definition, $context)
            );

            $discountLineItem->setExtensions(['custom_bonus_system_discount' => 1]);
            $discountLineItemCollection->add($discountLineItem);
        }
        // calculate and add to cart bonus discount
        $this->bonusDiscountCalculator->calculate($discountLineItemCollection, $toCalculate, $context);
    }

    private function createDiscount(string $name, $bonusPoints): LineItem
    {
        $discountLineItem = new LineItem($name, 'custom_bonus_system', null, 1);
        $discountLineItem->setLabel($this->trans('custom-bonus-system.checkout.pointCartItemText', ['%points%' => $bonusPoints]));
        $discountLineItem->setGood(false);
        $discountLineItem->setStackable(false);
        $discountLineItem->setRemovable(false);
        $discountLineItem->setPayload([
            'bonus_points' => $bonusPoints,
            'custom_bonus_system_discount' => 1,
            'discount_id' => $discountLineItem->getId(),
            'discountScope' => 'cart',
            'discountType' => 'absolute'
        ]);
        return $discountLineItem;
    }
}
