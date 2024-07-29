<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use DateTime;
use Doctrine\DBAL\Connection;
use CustomBonusSystem\CustomBonusSystem;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1693983452AddPointsExpirationNotificationMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1693983452;
    }

    public function update(Connection $connection): void
    {
        $this->createMailTemplate($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createMailTemplate(Connection $connection): void
    {
        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $mailTemplateId = Uuid::randomHex();
        $mailTemplateTypeId = $this->createMailTemplateType($connection, $enGbLangId, $deDeLangId);

        // Insert mail template type
        $this->insertMailTemplate($connection, $mailTemplateId, $mailTemplateTypeId);

        // Insert EN content
        if (!empty($enGbLangId)) {
            $this->insertEnContent($connection, $mailTemplateId, $enGbLangId);
        }

        // Insert DE content
        if (!empty($deDeLangId)) {
            $this->insertDeContent($connection, $mailTemplateId, $deDeLangId);
        }
    }

    private function createMailTemplateType(Connection $connection, string $enGbLangId, string $deDeLangId): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $englishName = 'Bonus points expiration notification';
        $germanName = 'Bonuspunkte Ablaufbenachrichtigung';

        $connection->executeStatement('
            INSERT IGNORE INTO `mail_template_type` 
                (id, technical_name, available_entities, created_at) 
            VALUES 
                (:id, :technicalName, :availableEntities, :createdAt)
            ', [
                'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'technicalName' => CustomBonusSystem::POINTS_EXPIRATION_NOTIFICATION_TECHNICAL_NAME,
                'availableEntities' => json_encode(['bonusPoints' => 'custom_bonus_system_user_point']),
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        if (!empty($enGbLangId)) {
            $connection->executeStatement('
                INSERT IGNORE INTO `mail_template_type_translation` 
                    (mail_template_type_id, language_id, name, created_at)
                VALUES 
                    (:mailTemplateTypeId, :languageId, :name, :createdAt)
                ', [
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'languageId' => $enGbLangId,
                    'name' => $englishName,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (!empty($deDeLangId)) {
            $connection->executeStatement('
                INSERT IGNORE INTO `mail_template_type_translation` 
                    (mail_template_type_id, language_id, name, created_at)
                VALUES 
                    (:mailTemplateTypeId, :languageId, :name, :createdAt)
                ', [
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'languageId' => $deDeLangId,
                    'name' => $germanName,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $mailTemplateTypeId;
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
            SELECT `language`.`id`
            FROM `language`
            INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            WHERE `locale`.`code` = :locale
        SQL;

        $languageId = $connection->executeQuery($sql, ['locale' => $locale])->fetchOne();

        return $languageId ?: null;
    }

    private function insertMailTemplate(Connection $connection, string $mailTemplateId, string $mailTemplateTypeId): void
    {
        $sql = <<<SQL
            INSERT IGNORE INTO `mail_template`
                (id, mail_template_type_id, system_default, created_at)
            VALUES
                (:id, :mailTemplateTypeId, :systemDefault, :createdAt)
        SQL;

        $connection->executeStatement($sql, [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'systemDefault' => 0,
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function insertEnContent(Connection $connection, string $mailTemplateId, string $langId): void
    {
        $sql = <<<SQL
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
        SQL;

        $connection->executeStatement($sql, [
            'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
            'languageId' => $langId,
            'senderName' => '{{ salesChannel.name }}',
            'subject' => 'Your points are about to expire',
            'description' => 'This email is sent to customers when their points are about to expire',
            'contentHtml' => $this->getEnContentHtml(),
            'contentPlain' => $this->getEnContentPlain(),
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function insertDeContent(Connection $connection, string $mailTemplateId, string $langId)
    {
        $sql = <<<SQL
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
        SQL;

        $connection->executeStatement($sql, [
            'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
            'languageId' => $langId,
            'senderName' => '{{ salesChannel.name }}',
            'subject' => 'Ihre Punkte laufen ab',
            'description' => 'Diese E-Mail wird an Kunden gesendet, wenn ihre Punkte ablaufen',
            'contentHtml' => $this->getDeContentHtml(),
            'contentPlain' => $this->getDeContentPlain(),
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getEnContentHtml(): string
    {
        $content = trim((string) file_get_contents(__DIR__ . '/../Core/MailTemplates/customer_point_expire_html_en.txt'));

        return <<<MAIL
            $content
        MAIL;
    }

    private function getEnContentPlain(): string
    {
        $content = trim((string) file_get_contents(__DIR__ . '/../Core/MailTemplates/customer_point_expire_plain_en.txt'));

        return <<<MAIL
            $content
        MAIL;
    }

    private function getDeContentHtml(): string
    {
        $content = trim((string) file_get_contents(__DIR__ . '/../Core/MailTemplates/customer_point_expire_html_de.txt'));

        return <<<MAIL
            $content
        MAIL;
    }

    private function getDeContentPlain(): string
    {
        $content = trim((string) file_get_contents(__DIR__ . '/../Core/MailTemplates/customer_point_expire_plain_de.txt'));

        return <<<MAIL
            $content
        MAIL;
    }
}
