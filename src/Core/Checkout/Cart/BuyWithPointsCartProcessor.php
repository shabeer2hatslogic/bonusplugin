<?php

namespace CustomBonusSystem\Core\Checkout\Cart;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Checkout\Bonus\BonusProcessor;
use CustomBonusSystem\Core\Entity\Bonus\BonusProductCollection;
use CustomBonusSystem\Core\Entity\Bonus\BonusProductEntity;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Cart\MinOrderQuantityError;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Content\Product\Cart\ProductStockReachedError;
use Shopware\Core\Content\Product\Cart\PurchaseStepsError;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BuyWithPointsCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    final public const SKIP_PRODUCT_STOCK_VALIDATION = 'skipProductStockValidation';

    public function __construct(private readonly EntityRepository $bonusProductRepository, private readonly Calculator $calculator, private readonly BonusService $bonusService, private readonly BonusProcessor $bonusProcessor)
    {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $processableLineItems = $original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $ids = $this->getNotCompleted($data, $processableLineItems, $context);
        if (!empty($ids)) {
            $productConfigurations = $this->loadConfigurationsForProducts($ids, $context, true);
            foreach ($productConfigurations as $productConfiguration) {
                $data->set($this->hash($productConfiguration->getProductId()), $productConfiguration);
            }
        }

        // lineItem validations
        foreach ($processableLineItems as $processableLineItem) {

            // Set max purchase quantity for bonus product
            if (($processableLineItem->hasPayLoadValue('buyWithPoints') && $processableLineItem->getPayloadValue('buyWithPoints')) && $processableLineItem->hasPayloadValue('maxPurchaseQuantity')) {
                $processableLineItem->getQuantityInformation()->setMaxPurchase($processableLineItem->getPayloadValue('maxPurchaseQuantity'));
            }

            $this->validateStock($processableLineItem, $original, $processableLineItems, $behavior);
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $processableLineItems = $toCalculate->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $this->enrichData($data, $toCalculate, $processableLineItems, $context);

        /** @var LineItem $lineItem */
        foreach ($processableLineItems as $lineItem) {
            if (!$lineItem->hasExtension('customBonusSystem')) {
                continue;
            }

            if ($toCalculate->has($lineItem->getId())) {
                $toCalculate->remove($lineItem->getId());
            }

            $taxRules = new TaxRuleCollection();
            if (method_exists($lineItem->getPriceDefinition(), 'getTaxRules')) {
                $taxRules = $lineItem->getPriceDefinition()->getTaxRules();
            }

            $lineItem->setPriceDefinition(new QuantityPriceDefinition(0, $taxRules, $lineItem->getQuantity()));
            $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), $taxRules, $lineItem->getQuantity()));
            $toCalculate->add($lineItem);
        }
    }

    private function getNotCompleted(CartDataCollection $data, LineItemCollection $processableLineItems, SalesChannelContext $context)
    {
        return $processableLineItems->getReferenceIds();
    }

    private function loadConfigurationsForProducts(array $productIds, SalesChannelContext $context, bool $activeOnly = false): BonusProductCollection
    {
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR, [
                new RangeFilter(
                    'validFrom',
                    [
                        RangeFilter::LT => $now
                    ]
                ),
                new EqualsFilter('validFrom', null)
            ]
            )

        );
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR, [
                new RangeFilter(
                    'validUntil',
                    [
                        RangeFilter::GT => $now
                    ]
                ),
                new EqualsFilter('validUntil', null)
            ]
            )
        );

        if ($activeOnly) {
            $criteria->addFilter(
                new EqualsFilter('active', true)
            );
        }

        $criteria->addFilter(new EqualsFilter('product.visibilities.salesChannelId', $context->getSalesChannelId()));
        /** @var BonusProductCollection $result */
        $result = $this->bonusProductRepository->search($criteria, $context->getContext())->getEntities();

        return $result;
    }

    private function enrichData(CartDataCollection $data, Cart $cart, LineItemCollection $processableLineItems, SalesChannelContext $context)
    {
        $currentBonusSumForCustomer = (int) $this->bonusService->getBonusSumForUser($context);
        /** @var LineItem $lineItem */
        foreach ($processableLineItems as $lineItem) {
            /** @var ?BonusProductEntity $config */
            $config = $data->get($this->hash($lineItem->getReferencedId()));

            if (!$config instanceof BonusProductEntity) {
                if ($lineItem->getPayLoadValue('buyWithPoints')) {
                    $cart->getLineItems()->remove($lineItem->getId());
                }
                continue;
            }

            if ($config->isOnlyBuyableWithPoints() && !$lineItem->getPayLoadValue('buyWithPoints')) {
                $cart->getLineItems()->remove($lineItem->getId());
                continue;
            }

            if ($lineItem->getPayLoadValue('buyWithPoints')) {
                $this->bonusProcessor->removePointRedeemByType($this->hash($lineItem->getId()));
                $points = $this->calculator->calculatePointsForProduct($config, $lineItem->getPrice(), $context, $lineItem->getQuantity());
                $currentPointsInRedeem = $this->bonusProcessor->getPointRedeem();

                if ($points > ($currentBonusSumForCustomer - $currentPointsInRedeem)) {
                    $quantity = $this->calculator->calculateMaxOrderQuantityForBonusProduct($config->getValue(), $currentBonusSumForCustomer);
                    $lineItem->setQuantity($quantity);
                    $cart->getLineItems()->remove($lineItem->getId());
                    continue;
                }
                $this->bonusProcessor->storePointRedeem($points, $this->hash($lineItem->getId()));

                $lineItem->setPayloadValue('customBonusSystem', ['costs' => $points, 'single' => $points / $lineItem->getQuantity(), 'originalPrice' => $lineItem->getPrice()]);
                $lineItem->addExtension('customBonusSystem', new ArrayStruct(['costs' => $points, 'single' => $points / $lineItem->getQuantity(), 'originalPrice' => $lineItem->getPrice()]));
            }
        }
    }

    /**
     * @param LineItem $item
     * @param Cart $cart
     * @param LineItemCollection $scope
     * @param CartBehavior $behavior
     */
    private function validateStock(LineItem $item, Cart $cart, LineItemCollection $scope, CartBehavior $behavior): void
    {
        if ($behavior->hasPermission(self::SKIP_PRODUCT_STOCK_VALIDATION)) {
            return;
        }

        $minPurchase = 1;
        $steps = 1;
        $available = $item->getQuantity();

        if ($item->getQuantityInformation() !== null) {
            $minPurchase = $item->getQuantityInformation()->getMinPurchase();
            $available = $item->getQuantityInformation()->getMaxPurchase() ?? 0;
            $steps = $item->getQuantityInformation()->getPurchaseSteps() ?? 1;
        }

        if ($available < $minPurchase) {
            $scope->remove($item->getId());

            $cart->addErrors(
                new ProductOutOfStockError((string) $item->getReferencedId(), (string) $item->getLabel())
            );

            return;
        }

        if ($available < $item->getQuantity()) {
            $maxAvailable = $this->fixQuantity($minPurchase, $available, $steps);

            $item->setQuantity($maxAvailable);

            $cart->addErrors(
                new ProductStockReachedError((string) $item->getReferencedId(), (string) $item->getLabel(), $maxAvailable)
            );

            return;
        }

        if ($item->getQuantity() < $minPurchase) {
            $item->setQuantity($minPurchase);

            $cart->addErrors(
                new MinOrderQuantityError((string) $item->getReferencedId(), (string) $item->getLabel(), $minPurchase)
            );

            return;
        }

        $fixedQuantity = $this->fixQuantity($minPurchase, $item->getQuantity(), $steps);
        if ($item->getQuantity() !== $fixedQuantity) {
            $item->setQuantity($fixedQuantity);

            $cart->addErrors(
                new PurchaseStepsError((string) $item->getReferencedId(), (string) $item->getLabel(), $fixedQuantity)
            );
        }
    }

    private function fixQuantity(int $min, int $current, int $steps): int
    {
        return (int) (floor(($current - $min) / $steps) * $steps + $min);
    }

    private function hash(string $productId)
    {
        return 'customBonusConfig-' . $productId;
    }
}
