<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Entity\Customer;

use CustomBonusSystem\Core\Entity\Bonus\BonusBookingDefinition;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomerExtension extends EntityExtension
{
    /**
     * Allows to add fields to an entity.
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('customBonusSystemUserPoint', 'id', 'customer_id', BonusUserPointDefinition::class, true))->addFlags(new CascadeDelete())
        );
        $collection->add(
            (new OneToManyAssociationField('customBonusBooking', BonusBookingDefinition::class, 'customer_id'))->addFlags(new CascadeDelete())
        );
    }

    /**
     * Defines which entity definition should be extended by this class
     */
    public function getDefinitionClass(): string
    {
        return CustomerDefinition::class;
    }
}
