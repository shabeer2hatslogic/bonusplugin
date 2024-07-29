<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1612693732BonusSystem extends MigrationStep
{
    /**
     * get creation timestamp
     */
    public function getCreationTimestamp(): int
    {
        return 1_612_693_732;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `custom_bonus_system_bonus_product` (
            `id` BINARY(16) NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `product_version_id` BINARY(16) NOT NULL,
            `only_buyable_with_points` TINYINT(1) NOT NULL DEFAULT \'0\',
            `type` INT NOT NULL DEFAULT \'0\',
            `value` DOUBLE NULL,
            `active` TINYINT(1) NOT NULL DEFAULT \'0\',
            `valid_from` DATETIME(3) NULL,
            `valid_until` DATETIME(3) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            `max_order_quantity` int NULL
            PRIMARY KEY (`id`),
            CONSTRAINT `fk.bonus_product.product_id__product_version_id` FOREIGN KEY (`product_id`, `product_version_id`)
            REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');
    }
    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
