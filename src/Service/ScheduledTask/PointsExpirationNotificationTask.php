<?php declare(strict_types=1);

namespace CustomBonusSystem\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class PointsExpirationNotificationTask extends ScheduledTask
{
    const TASK_NAME = 'custom_bonus_system.points_expiration_notification_task';

    const TASK_INTERVAL = 60 * 60; // 1 hour

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