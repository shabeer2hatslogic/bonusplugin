<?php

namespace CustomBonusSystem\FlowEvent;

use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;

class BonusPointsChangedFlow implements FlowEventAware, CustomerAware, MailAware
{
    private Context $context;
    private string $salesChannelId;
    private BonusUserPointEntity $userPointEntity;
    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(BonusUserPointEntity $userPointEntity, string $salesChannelId, Context $context)
    {
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
        $this->userPointEntity = $userPointEntity;
    }

    public function getCustomerId(): string
    {
        return $this->userPointEntity->getCustomerId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('bonusPoints', new EntityType(BonusUserPointEntity::class));
    }

    public function getName(): string
    {
        return 'bonus_system.customer_points_changed';
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $customer = $this->userPointEntity->getCustomer();

            $this->mailRecipientStruct = new MailRecipientStruct([
                $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->userPointEntity->getCustomer();
    }

    public function getBonusPoints(): BonusUserPointEntity
    {
        return $this->userPointEntity;
    }
}
