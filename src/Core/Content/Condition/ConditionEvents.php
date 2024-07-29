<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Content\Condition;

class ConditionEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const CONDITION_WRITTEN_EVENT = 'custom_bonus_system_condition.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const CONDITION_DELETED_EVENT = 'custom_bonus_system_condition.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const CONDITION_LOADED_EVENT = 'custom_bonus_system_condition.loaded';
}
