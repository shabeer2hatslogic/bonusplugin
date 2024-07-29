<?php
declare(strict_types = 1);


namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BonusProductDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_bonus_system_bonus_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BonusProductCollection::class;
    }

    public function getEntityClass(): string
    {
        return BonusProductEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                new BoolField('active', 'active'),
                new DateTimeField('valid_from', 'validFrom'),
                new DateTimeField('valid_until', 'validUntil'),

                (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
                (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),

                new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),

                new BoolField('only_buyable_with_points', 'onlyBuyableWithPoints'),
                (new IntField('type', 'type')),
                (new FloatField('value', 'value')),
                (new IntField('max_order_quantity', 'maxOrderQuantity')),

                new UpdatedAtField(),
                new CreatedAtField(),
            ]
        );
    }
}
