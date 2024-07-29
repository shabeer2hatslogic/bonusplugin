<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BonusConditionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_bonus_system_condition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BonusConditionCollection::class;
    }

    public function getEntityClass(): string
    {
        return BonusConditionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                new StringField('name', 'name'),
                new BoolField('active', 'active'),
                new DateTimeField('valid_from', 'validFrom'),
                new DateTimeField('valid_until', 'validUntil'),
                (new IntField('type', 'type')),
                (new IntField('sub_type', 'subType')),

                (new FloatField('factor', 'factor')),

                (new ObjectField('category_condition', 'categoryCondition')),
                (new ObjectField('product_condition', 'productCondition')),
                (new ObjectField('stream_condition', 'streamCondition')),
                (new ObjectField('customer_number_condition', 'customerNumberCondition')),
                (new ObjectField('customer_group_condition', 'customerGroupCondition')),

                new UpdatedAtField(),
                new CreatedAtField(),
            ]);
    }
}
