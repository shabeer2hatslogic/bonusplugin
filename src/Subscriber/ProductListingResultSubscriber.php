<?php

namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\Calculation\Condition\ConditionService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Entity\Bonus\BonusConditionEntity;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingResultSubscriber implements EventSubscriberInterface
{
    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * @var CalculationService
     */
    protected $calculationService;

    /**
     * @var ConditionService
     */
    protected $conditionService;

    /**
     * @param ConfigService $configService
     * @param CalculationService $calculationService
     * @param ConditionService $conditionService
     */
    public function __construct(
        ConfigService $configService,
        CalculationService $calculationService,
        ConditionService $conditionService
    )
    {
        $this->configService = $configService;
        $this->calculationService = $calculationService;
        $this->conditionService = $conditionService;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LISTING_RESULT => 'onProductListingResultEvent',
            ProductEvents::PRODUCT_SEARCH_RESULT => 'onProductListingResultEvent'
        ];
    }

    /**
     * @param ProductListingResultEvent $event
     * @return void
     */
    public function onProductListingResultEvent(ProductListingResultEvent $event)
    {
        $bonusSettings = $this->configService->getConfig($event->getSalesChannelContext());
        $settingVars = $bonusSettings->getVars();

        if ($settingVars['useBonusSystem']) {
            $products = $event->getResult()->getElements();
            if ($products) {

                $bonusSystemConversionFactor = $settingVars['bonusSystemConversionFactorCollect'];

                /** @var SalesChannelProductEntity $product */
                foreach ($products as $product) {

                    $quantity = $product->getMinPurchase() ?: 1;
                    $points = 0;

                    $match = $this->conditionService->conditionsExcludePointsForProductMatch($product);

                    // If product is excluded by one or more conditions, then don't calculate bonus points for it
                    if ($match) {
                        $product->removeExtension('customBonusSystem');
                    } else {
                        $bonusSystemConversionFactor = $this->calculationService->getConversionFactorCollect($event->getSalesChannelContext()->getCustomer(), $bonusSystemConversionFactor, $product);
                        $points = $this->calculationService->calculateBonusPointsForAmount(
                            $this->getProductUnitPrice($product),
                            $bonusSystemConversionFactor,
                            $event->getSalesChannelContext(),
                            true,
                            $settingVars['collectPointsRound']
                        );
                        $product->addExtension('customBonusSystem', $bonusSettings);
                        $product->assign([
                            'customBonusSystemPoints' => [
                                'get' => $points
                            ]
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param SalesChannelProductEntity $product
     */
    protected function getProductUnitPrice(SalesChannelProductEntity $product): float
    {
        $price = $product->getCalculatedPrice();
        $displayParent = (method_exists($product, 'getVariantListingConfig') && ($product->getVariantListingConfig() && $product->getVariantListingConfig()->getDisplayParent()));

        if ($product->getCalculatedPrices()->count() > 0) {
            $price = $product->getCalculatedPrices()->last();
        }

        if ($displayParent && $product->getParentId() === null) {
            $price = $product->getCalculatedCheapestPrice();
        }

        return $price->getUnitPrice();
    }
}