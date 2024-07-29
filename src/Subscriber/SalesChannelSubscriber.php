<?php declare(strict_types=1);

namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Bonus\BonusProduct\BonusProductService;
use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelSubscriber implements EventSubscriberInterface
{
    /**
     * @var ConfigService
     */
    private $configService;

    public function __construct(
        ConfigService $configService,
        private readonly BonusProductService $bonusProductService,
        private readonly CalculationService $calculationService
    ) {
        $this->configService = $configService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
           'sales_channel.product.loaded' => 'onSalesChannelProductLoaded',
            ProductPageLoadedEvent::class => 'onSalesChannelProductLoaded'
        ];
    }

    /**
     * Enrich product with bonus product data if bonus product is available
     * @param $event
     */
    public function onSalesChannelProductLoaded($event): void
    {
        if (!$event instanceof SalesChannelEntityLoadedEvent && !$event instanceof ProductPageLoadedEvent) {
            return;
        }

        $entities = new SalesChannelProductCollection();
        if ($event instanceof SalesChannelEntityLoadedEvent) {
            $entities = $event->getEntities();
        }

        if ($event instanceof ProductPageLoadedEvent) {
            $entities->add($event->getPage()->getProduct());
        }

        $context = $event->getSalesChannelContext();
        $bonusSettings = $this->configService->getConfig($context);
        $settingVars = $bonusSettings->getVars();

        if (!$settingVars['useBonusSystem']) {
            return;
        }

        $bonusSystemConversionFactor = $settingVars['bonusSystemConversionFactorRedeem'];
        /** @var SalesChannelProductEntity $entity */
        foreach ($entities as $entity) {
            $bonusProduct = $this->bonusProductService->getBonusProduct($context, $entity->getId());

            if (!$bonusProduct) {
                continue;
            }

            if ($bonusProduct['type'] == 1) {
                $points = $bonusProduct['value'];
                $entity->customBonusSystemStaticPoint = true;
            } else {
                $price = $entity->getCalculatedPrices()->count() ? $entity->getCalculatedPrices()->first() : $entity->getCalculatedPrice();
                $points = $this->calculationService->calculateBonusPointsForAmount(
                    $price->getUnitPrice(),
                    $bonusSystemConversionFactor,
                    $context,
                    true
                );
            }

            $this->bonusProductService->enrichProductWithBonusProductData($entity, $points, $bonusProduct['onlyBuyableWithPoints']);

            if ($event instanceof ProductPageLoadedEvent) {
                $this->bonusProductService->overrideMaxOrderQuantityForBonusProduct($entity, $bonusProduct['maxOrderQuantity'], $bonusProduct['onlyBuyableWithPoints']);
            }
        }
    }
}
