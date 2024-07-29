<?php

namespace CustomBonusSystem\Subscriber\Twig;

use CustomBonusSystem\Core\Bonus\BonusHelper;
use CustomBonusSystem\Core\Bonus\BonusService;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddPointsToInvoiceDocumentSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly BonusService $bonusService)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            DocumentTemplateRendererParameterEvent::class => 'onDocumentRender',
        ];
    }

    public function onDocumentRender(DocumentTemplateRendererParameterEvent $event)
    {
        $parameters = $event->getParameters();

        if (!isset($parameters['order'])) {
            return;
        }
        /** @var OrderEntity $order */
        $order = $parameters['order'];

        if ($order instanceof OrderEntity) {
            $pointsCollection = $this->bonusService->getPointsForOrder($order, Context::createDefaultContext());

            $calculatedPoints = BonusHelper::getCountForCollectionPoints($pointsCollection);

            if ($calculatedPoints['redeemed'] === 0 && $calculatedPoints['get'] === 0) {
                return;
            }

            $event->addExtension(
                'customBonusSystem',
                new ArrayStruct(
                    [
                        'get' => $calculatedPoints['get'],
                        'redeemed' => $calculatedPoints['redeemed']
                    ]
                )
            );
        }
    }
}
