<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610355960BonusSystem extends MigrationStep
{
  /**
   * get creation timestamp
   */
  public function getCreationTimestamp(): int
  {
    return 1_609_155_480;
  }

  /**
   * update non-destructive changes
   */
  public function update(Connection $connection): void
  {
      $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `custom_bonus_system_condition` (
            `id` BINARY(16) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT \'0\',
            `valid_from` DATETIME(3) NULL,
            `valid_until` DATETIME(3) NULL,
            `type` INT NOT NULL DEFAULT \'0\',
            `sub_type` INT NULL DEFAULT \'0\',

            `factor` DOUBLE NULL,

            `category_condition` TEXT NULL,
            `product_condition` TEXT NULL,
            `stream_condition` TEXT NULL,
            `customer_number_condition` TEXT NULL,
            `customer_group_condition` TEXT NULL,

            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');

    $connection->executeStatement('UPDATE `custom_bonus_system_condition`  SET `sub_type` = 1  WHERE  `sub_type` IS NULL AND `type` = 1');
  }
  /**
   * update destructive changes
   */
  public function updateDestructive(Connection $connection): void
  {
  }
}
