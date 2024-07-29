<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Entity\Order;

use CustomBonusSystem\Core\Entity\Bonus\BonusBookingDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    /**
     * Allows to add fields to an entity.
     */
    public function extendFields(FieldCollection $collection): void
    {

        $collection->add(
            (new OneToOneAssociationField('customBonusSystemBooking', 'id', 'order_id',BonusBookingDefinition::class, false))
        );
    }

    /**
     * Defines which entity definition should be extended by this class
     */
    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
