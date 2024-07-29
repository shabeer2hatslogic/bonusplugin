<?php declare(strict_types = 1);

namespace CustomBonusSystem\Core\Entity\SalesChannel;

use CustomBonusSystem\Core\Entity\Bonus\BonusBookingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelExtension extends EntityExtension
{
    /**
     * Allows to add fields to an entity.
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('customBonusSystemBooking', BonusBookingDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete())
        );
    }

    /**
     * Defines which entity definition should be extended by this class
     */
    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }
}
