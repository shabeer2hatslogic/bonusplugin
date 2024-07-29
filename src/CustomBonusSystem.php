<?php declare(strict_types=1);

namespace CustomBonusSystem;

use Doctrine\DBAL\Connection;
use CustomBonusSystem\FlowEvent\BonusPointsChangedFlow;
use CustomBonusSystem\Service\ScheduledTask\BonusPointActivationTask;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class CustomBonusSystem extends Plugin
{
    public const POINTS_EXPIRATION_NOTIFICATION_TECHNICAL_NAME = 'custom.bonus_system.points_expiration_notification_type';

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
        if ($context->keepUserData()) {
            return;
        }
        
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_bonus_system_booking`');
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_bonus_system_user_point`');
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_bonus_system_condition`');
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_bonus_system_bonus_product`');
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_bonus_system_import_point`');
        $connection->executeStatement('DELETE FROM `scheduled_task` WHERE `scheduled_task`.`name` = ' . BonusPointActivationTask::TASK_NAME);
    }

    protected function getActionEventClasses(): array
    {
        return [
            BonusPointsChangedFlow::class
        ];
    }
}
