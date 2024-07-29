<?php

namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Checkout\Bonus\BonusProcessor;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoveRedeemedBonusPointsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly BonusProcessor $bonusProcessor)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeLineItemRemovedEvent::class => 'beforeLineItemRemoved'
        ];
    }

    public function beforeLineItemRemoved(BeforeLineItemRemovedEvent $event)
    {
        $lineItem = $event->getLineItem();

        if ($lineItem->hasExtension('customBonusSystem')) {
            $this->bonusProcessor->removePointRedeemByType('customBonusConfig-'.$lineItem->getId());
        }
    }
}
