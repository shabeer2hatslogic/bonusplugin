<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1696063668AddOrderVersionIdToBookingTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696063668;
    }

    public function update(Connection $connection): void
    {
        $this->dropConstraint($connection);
        $this->dropIndex($connection);
        $this->addOrderVersionIdColumn($connection);
        $this->updateBookingEntries($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function dropConstraint(Connection$connection): void
    {
        $sql = <<<SQL
            ALTER TABLE `custom_bonus_system_booking` DROP FOREIGN KEY `fk.bonus_system_booking_order.order_id`;
        SQL;

        try {
            $connection->executeStatement($sql);
        } catch (DriverException $exception) {
            // Do nothing if constraint doesn't exist
        }
    }

    private function dropIndex(Connection $connection): void
    {
        if ($this->hasIndex($connection)) {
            $sql = <<<SQL
                ALTER TABLE `custom_bonus_system_booking` DROP INDEX `fk.bonus_system_booking_order.order_id`
            SQL;

            $connection->executeStatement($sql);
        }
    }

    private function addOrderVersionIdColumn(Connection $connection): void
    {
        if (!$this->isOrderVersionIdFieldExist($connection)) {
            $sql = <<<SQL
                ALTER TABLE `custom_bonus_system_booking` ADD `order_version_id` BINARY(16) DEFAULT NULL AFTER `order_id`;
            SQL;

            $connection->executeStatement($sql);
        }

        $sql = <<<SQL
            ALTER TABLE `custom_bonus_system_booking`
            ADD CONSTRAINT `fk.bonus_system_booking_order.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE;
        SQL;

        $connection->executeStatement($sql);
    }

    private function updateBookingEntries(Connection $connection): void
    {
        $sql = <<<SQL
            UPDATE `custom_bonus_system_booking` SET `order_version_id` = :versionId WHERE `order_id` = :orderId;
        SQL;

        $orders = $this->getOrders($connection);
        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            $connection->executeStatement($sql, [
                'orderId'   => $order['id'],
                'versionId' => $order['version_id']
            ]);
        }
    }

    private function getOrders(Connection $connection): array
    {
        $sql = <<<SQL
            SELECT `id`, `version_id` FROM `order`;
        SQL;

        return $connection->fetchAllAssociative($sql);
    }

    private function isOrderVersionIdFieldExist(Connection $connection): bool
    {
        $sql = <<<SQL
            SHOW COLUMNS FROM `custom_bonus_system_booking` where field = 'order_version_id';
        SQL;

        return (bool) $connection->fetchOne($sql);
    }

    private function hasIndex(Connection $connection): bool
    {
        $sql = <<<SQL
            SHOW INDEXES IN `custom_bonus_system_booking` WHERE `Key_name` = 'fk.bonus_system_booking_order.order_id';
        SQL;

        return (bool) $connection->fetchOne($sql);
    }
}
