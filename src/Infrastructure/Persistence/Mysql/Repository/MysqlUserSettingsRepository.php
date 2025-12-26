<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use DateTimeImmutable;
use Domain\Identity\Entity\UserSettings;
use Domain\Identity\Repository\UserSettingsRepositoryInterface;
use Domain\Identity\ValueObject\Theme;
use Domain\Identity\ValueObject\UserId;
use PDO;

/**
 * MySQL implementation of UserSettingsRepositoryInterface.
 */
final class MysqlUserSettingsRepository extends AbstractMysqlRepository implements UserSettingsRepositoryInterface
{
    public function findByUserId(UserId $userId): ?UserSettings
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM user_settings WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(UserSettings $settings): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO user_settings (
                id, user_id, theme, locale, timezone, date_format, number_format,
                email_notifications, browser_notifications, session_timeout_minutes,
                backup_codes_hash, backup_codes_generated_at, extra_settings_json,
                created_at, updated_at
            ) VALUES (
                :id, :user_id, :theme, :locale, :timezone, :date_format, :number_format,
                :email_notifications, :browser_notifications, :session_timeout_minutes,
                :backup_codes_hash, :backup_codes_generated_at, :extra_settings_json,
                :created_at, :updated_at
            ) ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                locale = VALUES(locale),
                timezone = VALUES(timezone),
                date_format = VALUES(date_format),
                number_format = VALUES(number_format),
                email_notifications = VALUES(email_notifications),
                browser_notifications = VALUES(browser_notifications),
                session_timeout_minutes = VALUES(session_timeout_minutes),
                backup_codes_hash = VALUES(backup_codes_hash),
                backup_codes_generated_at = VALUES(backup_codes_generated_at),
                extra_settings_json = VALUES(extra_settings_json),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            'id' => $settings->id(),
            'user_id' => $settings->userId()->toString(),
            'theme' => $settings->theme()->value,
            'locale' => $settings->locale(),
            'timezone' => $settings->timezone(),
            'date_format' => $settings->dateFormat(),
            'number_format' => $settings->numberFormat(),
            'email_notifications' => $settings->emailNotifications() ? 1 : 0,
            'browser_notifications' => $settings->browserNotifications() ? 1 : 0,
            'session_timeout_minutes' => $settings->sessionTimeoutMinutes(),
            'backup_codes_hash' => $settings->backupCodesHash(),
            'backup_codes_generated_at' => $settings->backupCodesGeneratedAt()?->format('Y-m-d H:i:s'),
            'extra_settings_json' => $settings->extraSettings() !== null ? json_encode($settings->extraSettings()) : null,
            'created_at' => $settings->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $settings->updatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(UserId $userId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM user_settings WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId->toString()]);
    }

    private function hydrate(array $row): UserSettings
    {
        return UserSettings::reconstitute(
            id: $row['id'],
            userId: UserId::fromString($row['user_id']),
            theme: Theme::fromString($row['theme']),
            locale: $row['locale'],
            timezone: $row['timezone'],
            dateFormat: $row['date_format'],
            numberFormat: $row['number_format'],
            emailNotifications: (bool)$row['email_notifications'],
            browserNotifications: (bool)$row['browser_notifications'],
            sessionTimeoutMinutes: (int)$row['session_timeout_minutes'],
            backupCodesHash: $row['backup_codes_hash'],
            backupCodesGeneratedAt: $row['backup_codes_generated_at'] 
                ? new DateTimeImmutable($row['backup_codes_generated_at']) 
                : null,
            extraSettings: $row['extra_settings_json'] 
                ? json_decode($row['extra_settings_json'], true) 
                : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at'])
        );
    }
}
