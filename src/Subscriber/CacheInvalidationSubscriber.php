<?php declare(strict_types=1);

namespace CustomBonusSystem\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use CustomBonusSystem\Core\Entity\Bonus\BonusProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    private readonly CacheInvalidator $cacheInvalidator;

    public function __construct(
        CacheInvalidator $cacheInvalidator,
        private readonly EntityRepository $bonusProductRepository
    ) {
        $this->cacheInvalidator = $cacheInvalidator;
    }

    public static function getSubscribedEvents()
    {
        return [EntityWrittenContainerEvent::class => 'onUpdatedEntity'];
    }

    /**
     * Invalidate Cache of product of there is a change on a corresponding bonus product.
     * @param EntityWrittenContainerEvent $event
     */
    public function onUpdatedEntity(EntityWrittenContainerEvent $event): void
    {
        $updatedData = $event->getEventByEntityName(BonusProductDefinition::ENTITY_NAME);

        if ($updatedData === null) {
            return;
        }

        $bonusProductIds = $updatedData->getIds();

        $productIds = $this->getProductIds($bonusProductIds, $event->getContext());

        $this->cacheInvalidator->invalidate(array_map([CachedProductDetailRoute::class, 'buildName'], $productIds));
    }

    private function getProductIds(array $bonusProductIds, Context $context): array
    {
        if ($bonusProductIds === []) {
            return [];
        }

        $entities = $this->bonusProductRepository->search(new Criteria($bonusProductIds), $context);

        $productIds = [];
        foreach ($entities as $entity) {
            $productIds[] = $entity->getProductId();
        }

        return $productIds;
    }
}