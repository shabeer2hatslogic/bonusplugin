<?php declare(strict_types=1);

namespace CustomBonusSystem\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1678119139 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678119139;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $englishName = 'Bonus Points changing notification';
        $germanName = 'Bonuspunkte Änderungshinweis';

        $connection->executeStatement('
            INSERT IGNORE INTO `mail_template_type`
                (id, technical_name, available_entities, created_at)
            VALUES
                (:id, :technicalName, :availableEntities, :createdAt)
        ', [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technicalName' => 'custom.bonus_system.customer_points_changed_type',
            'availableEntities' => json_encode(['bonusPoints' => 'custom_bonus_system_user_point']),
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

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
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
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
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
        SELECT `language`.`id`
        FROM `language`
        INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
        WHERE `locale`.`code` = :code
        SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

        if (empty($languageId)) {
            return null;
        }

        return $languageId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->executeStatement('
        INSERT IGNORE INTO `mail_template`
            (id, mail_template_type_id, system_default, created_at)
        VALUES
            (:id, :mailTemplateTypeId, :systemDefault, :createdAt)
        ', [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'systemDefault' => 0,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (!empty($enGbLangId)) {
            $connection->executeStatement('
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ', [
                'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $enGbLangId,
                'senderName' => '{{ salesChannel.name }}',
                'subject' => 'Bonus points have changed',
                'description' => 'Notification mail to customer when bonus points have changed',
                'contentHtml' => $this->getContentHtmlEn(),
                'contentPlain' => $this->getContentPlainEn(),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!empty($deDeLangId)) {
            $connection->executeStatement('
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ', [
                'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $deDeLangId,
                'senderName' => '{{ salesChannel.name }}',
                'subject' => 'Bonuspunkte haben sich geändert',
                'description' => 'Hinweis E-Mail an den Kunden, wenn sich seine Bonuspunkte geändert haben',
                'contentHtml' => $this->getContentHtmlDe(),
                'contentPlain' => $this->getContentPlainDe(),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            <p>
            Hello {{customer.salutation.letterName }} {{customer.firstName}} {{customer.lastName}},
            <br><br>
            your bonus point balance has changed. You have {{ bonusPoints->points }} bonus points now.
            <br><br>
            You can check your bonus points balance everytime on our website at "My account" area: {{ rawUrl('frontend.account.home.page', {}, salesChannel.domains|first.url) }}
            <br><br>
            If you have any questions, do not hesitate to contact us.
            </p>
        </div>
        MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
            Hello {{customer.salutation.letterName }} {{customer.firstName}} {{customer.lastName}},
            
            your bonus point balance has changed. You have {{ bonusPoints->points }} bonus points now.
            
            You can check your bonus points balance everytime on our website at "My account" area: {{ rawUrl('frontend.account.home.page', {}, salesChannel.domains|first.url) }}
            
            If you have any questions, do not hesitate to contact us.
        MAIL;
    }

    private function getContentHtmlDe(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            <p>
            Hallo {{customer.salutation.letterName }} {{customer.firstName}} {{customer.lastName}},
            <br><br>
            Ihr Bonuspunkte Kontostand hat sich geändert. Sie haben jetzt {{ bonusPoints->points }} Bonuspunkte
            <br><br>
            Den aktuellen Bonuspunkte Kontostand können Sie jederzeit auf unserer Webseite im Bereich "Mein Konto" abrufen: {{ rawUrl('frontend.account.home.page', {}, salesChannel.domains|first.url) }}
            <br><br>
            Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
            </p>
        </div>
        MAIL;
    }

    private function getContentPlainDe(): string
    {
        return <<<MAIL
            Hallo {{customer.salutation.letterName }} {{customer.firstName}} {{customer.lastName}},
            
            Ihr Bonuspunkte Kontostand hat sich geändert. Sie haben jetzt {{ bonusPoints->points }} Bonuspunkte
            
            Den aktuellen Bonuspunkte Kontostand können Sie jederzeit auf unserer Webseite im  Bereich "Mein Konto" abrufen: {{ rawUrl('frontend.account.home.page', {}, salesChannel.domains|first.url) }}
            
            Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
        MAIL;
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
