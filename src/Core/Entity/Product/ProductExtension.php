<?php
declare(strict_types = 1);


namespace CustomBonusSystem\Core\Entity\Product;

use CustomBonusSystem\Core\Entity\Bonus\BonusProductDefinition;
use Custom\Plugin\CustomPriceOnRequest\Core\Entity\PriceRequest\PriceRequestDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    /**
     * Allows to add fields to an entity.
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'bonusProduct',
                BonusProductDefinition::class,
                'product_id'
            ))->addFlags(new CascadeDelete(), new Extension())
        );
    }

    /**
     * Defines which entity definition should be extended by this class
     */
    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
