<?php

namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointDefinition;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use CustomBonusSystem\FlowEvent\BonusPointsChangedFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BonusPointsChangedSubscriber implements EventSubscriberInterface
{
    private readonly BonusService $bonusService;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $bonusUserPointRepository,
        BonusService $bonusService
    )
    {
        $this->bonusService = $bonusService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'onPreValidate',
            'custom_bonus_system_user_point.written' => 'onEntityWritten'
        ];
    }

    public function onPreValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== BonusUserPointDefinition::ENTITY_NAME) {
                continue;
            }

            if ($command instanceof UpdateCommand) {
                $command->requestChangeSet();
            }
        }
    }

    public function onEntityWritten(EntityWrittenEvent $writtenEvent): void
    {
        foreach ($writtenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getEntityName() !== BonusUserPointDefinition::ENTITY_NAME) {
                continue;
            }
            if ($writeResult->getOperation() === 'insert' || ($writeResult->getOperation() === 'update' && $writeResult->getChangeSet() && $writeResult->getChangeSet()->getAfter('points'))) {
                $criteria = new Criteria([$writeResult->getPrimaryKey()]);
                $criteria->addAssociations(['customer.salutation', 'customer.salesChannel']);
                $userPointsEntity = $this->bonusUserPointRepository->search($criteria, $writtenEvent->getContext())->getEntities()->first();

                if (!$userPointsEntity instanceof BonusUserPointEntity) {
                    continue;
                }

                $customer = $userPointsEntity->getCustomer();
                $salesChannelId = $customer->getSalesChannelId();
                $salesChannelContext = $this->bonusService->createSalesChannelContext(
                    $salesChannelId,
                    [
                        SalesChannelContextService::CUSTOMER_ID => $customer->getId()
                    ]
                );

                $this->eventDispatcher->dispatch(new BonusPointsChangedFlow($userPointsEntity, $salesChannelId, $salesChannelContext->getContext()));
            }
        }
    }
}