<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1603396690BonusSystem extends MigrationStep
{
  /**
   * get creation timestamp
   */
  public function getCreationTimestamp(): int
  {
    return 1_603_396_690;
  }

  /**
   * update non-destructive changes
   */
  public function update(Connection $connection): void
  {
    $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `custom_bonus_system_booking` (
          `id` BINARY(16) NOT NULL,
          `customer_id` BINARY(16) NOT NULL,
          `order_id` BINARY(16) NULL,
          `sales_channel_id` BINARY(16) NOT NULL,
          `description` VARCHAR(255) NULL,
          `points` INT NOT NULL DEFAULT \'0\',
          `approved` TINYINT(1) NOT NULL DEFAULT \'0\',
          `custom_fields` JSON NULL
          `created_at` DATETIME(3) NOT NULL,
          `updated_at` DATETIME(3) NULL,
           PRIMARY KEY (`id`),
           CONSTRAINT `fk.bonus_system_booking_customer.customer_id` FOREIGN KEY (`customer_id`)
            REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
           CONSTRAINT `fk.bonus_system_booking_order.order_id` FOREIGN KEY (`order_id`)
            REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
           CONSTRAINT `fk.bonus_system_booking_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
            REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
           CONSTRAINT `json.custom_bonus_system_booking.custom_fields` CHECK (JSON_VALID(`custom_fields`))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');

      $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `custom_bonus_system_user_point` (
          `id` BINARY(16) NOT NULL,
          `customer_id` BINARY(16) NOT NULL,
          `points` INT NOT NULL DEFAULT \'0\',
          `can_send_points_expiration_notification` TINYINT(1) NOT NULL DEFAULT "1",
          `created_at` DATETIME(3) NOT NULL,
          `updated_at` DATETIME(3) NULL,
          `last_checked_at` DATETIME(3) DEFAULT NULL,
          `custom_fields` JSON NULL
          PRIMARY KEY (`id`),
           CONSTRAINT `fk.bonus_system_user_point_customer.customer_id` FOREIGN KEY (`customer_id`)
            REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `json.custom_bonus_system_user_point.custom_fields` CHECK (JSON_VALID(`custom_fields`))
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
