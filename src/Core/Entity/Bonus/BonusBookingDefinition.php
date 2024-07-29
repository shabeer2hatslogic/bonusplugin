<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class BonusBookingDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_bonus_system_booking';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BonusBookingCollection::class;
    }

    public function getEntityClass(): string
    {
        return BonusBookingEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                (new IntField('points', 'points')),
                (new FkField('customer_id', 'customerId', CustomerDefinition::class)),
                (new FkField('order_id', 'orderId', OrderDefinition::class)),
                (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
                (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
                new StringField('description', 'description'),
                new BoolField('approved', 'approved'),
                new CustomFields(),
                new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::class, true),
                new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', true),
                (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false)),
                new UpdatedAtField(),
                new CreatedAtField(),
            ]);
    }
}
