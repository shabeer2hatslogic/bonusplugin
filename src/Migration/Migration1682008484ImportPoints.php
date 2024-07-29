<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1682008484ImportPoints extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1682008484;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `custom_bonus_system_import_point` (
              `id` BINARY(16) NOT NULL,
              `customer_number` INT NOT NULL DEFAULT \'0\',
              `points` INT NOT NULL DEFAULT \'0\',
              `reason` VARCHAR(255) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,       
              PRIMARY KEY (`id`)
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
