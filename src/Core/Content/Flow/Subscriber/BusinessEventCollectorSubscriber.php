<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Content\Flow\Subscriber;

use CustomBonusSystem\Core\Framework\Event\ChangePointsAware;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BusinessEventCollectorSubscriber implements EventSubscriberInterface
{
    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BusinessEventCollectorEvent::NAME => 'addChangePointsAware',
        ];
    }

    /**
     * @param BusinessEventCollectorEvent $event
     */
    public function addChangePointsAware(BusinessEventCollectorEvent $event): void
    {
        foreach ($event->getCollection()->getElements() as $definition) {
            $className = \explode('\\', ChangePointsAware::class);
            $definition->addAware(\lcfirst(\end($className)));
        }
    }
}