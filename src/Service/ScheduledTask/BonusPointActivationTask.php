<?php declare(strict_types=1);

namespace CustomBonusSystem\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class BonusPointActivationTask extends ScheduledTask
{
    /**
     * Task name
     */
    const TASK_NAME = 'custom_bonus_system.bonus_point_activation_task';

    /**
     * Task interval
     *
     * Default: 3600 = 1 hour
     */
    const TASK_INTERVAL = 3600;

    /**
     * @return string
     */
    public static function getTaskName(): string
    {
        return self::TASK_NAME;
    }

    /**
     * @return int
     */
    public static function getDefaultInterval(): int
    {
        return self::TASK_INTERVAL;
    }
}