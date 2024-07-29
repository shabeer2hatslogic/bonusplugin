<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BonusImportPointDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'custom_bonus_system_import_point';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BonusImportPointCollection::class;
    }

    public function getEntityClass(): string
    {
        return BonusImportPointEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                (new IntField('points', 'points')),
                (new IntField('customer_number', 'customerNumber')),
                (new StringField('reason', 'reason')),
                new UpdatedAtField(),
                new CreatedAtField(),
            ]);
    }
}
