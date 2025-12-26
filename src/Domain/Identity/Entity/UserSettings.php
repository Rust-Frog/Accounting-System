<?php

declare(strict_types=1);

namespace Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Identity\ValueObject\Theme;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Uuid;

/**
 * UserSettings entity - stores per-user preferences and settings.
 * Follows DDD patterns with immutable value objects.
 */
final class UserSettings
{
    private function __construct(
        private readonly string $id,
        private readonly UserId $userId,
        private Theme $theme,
        private string $locale,
        private string $timezone,
        private string $dateFormat,
        private string $numberFormat,
        private bool $emailNotifications,
        private bool $browserNotifications,
        private int $sessionTimeoutMinutes,
        private ?string $backupCodesHash,
        private ?DateTimeImmutable $backupCodesGeneratedAt,
        private ?array $extraSettings,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * Create default settings for a new user.
     */
    public static function createDefault(UserId $userId): self
    {
        $now = new DateTimeImmutable();
        return new self(
            id: Uuid::generate()->toString(),
            userId: $userId,
            theme: Theme::LIGHT,
            locale: 'en-US',
            timezone: 'UTC',
            dateFormat: 'YYYY-MM-DD',
            numberFormat: 'en-US',
            emailNotifications: true,
            browserNotifications: true,
            sessionTimeoutMinutes: 30,
            backupCodesHash: null,
            backupCodesGeneratedAt: null,
            extraSettings: null,
            createdAt: $now,
            updatedAt: $now
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        string $id,
        UserId $userId,
        Theme $theme,
        string $locale,
        string $timezone,
        string $dateFormat,
        string $numberFormat,
        bool $emailNotifications,
        bool $browserNotifications,
        int $sessionTimeoutMinutes,
        ?string $backupCodesHash,
        ?DateTimeImmutable $backupCodesGeneratedAt,
        ?array $extraSettings,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            theme: $theme,
            locale: $locale,
            timezone: $timezone,
            dateFormat: $dateFormat,
            numberFormat: $numberFormat,
            emailNotifications: $emailNotifications,
            browserNotifications: $browserNotifications,
            sessionTimeoutMinutes: $sessionTimeoutMinutes,
            backupCodesHash: $backupCodesHash,
            backupCodesGeneratedAt: $backupCodesGeneratedAt,
            extraSettings: $extraSettings,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    // ========== Mutators ==========

    public function updateTheme(Theme $theme): void
    {
        $this->theme = $theme;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDateFormat(string $dateFormat): void
    {
        $this->dateFormat = $dateFormat;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateNumberFormat(string $numberFormat): void
    {
        $this->numberFormat = $numberFormat;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateNotificationPreferences(bool $email, bool $browser): void
    {
        $this->emailNotifications = $email;
        $this->browserNotifications = $browser;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateSessionTimeout(int $minutes): void
    {
        if ($minutes < 5 || $minutes > 480) {
            throw new \InvalidArgumentException('Session timeout must be between 5 and 480 minutes');
        }
        $this->sessionTimeoutMinutes = $minutes;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setBackupCodes(string $hash): void
    {
        $this->backupCodesHash = $hash;
        $this->backupCodesGeneratedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function clearBackupCodes(): void
    {
        $this->backupCodesHash = null;
        $this->backupCodesGeneratedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateExtraSettings(array $settings): void
    {
        $this->extraSettings = $settings;
        $this->updatedAt = new DateTimeImmutable();
    }

    // ========== Getters ==========

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function theme(): Theme
    {
        return $this->theme;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function dateFormat(): string
    {
        return $this->dateFormat;
    }

    public function numberFormat(): string
    {
        return $this->numberFormat;
    }

    public function emailNotifications(): bool
    {
        return $this->emailNotifications;
    }

    public function browserNotifications(): bool
    {
        return $this->browserNotifications;
    }

    public function sessionTimeoutMinutes(): int
    {
        return $this->sessionTimeoutMinutes;
    }

    public function backupCodesHash(): ?string
    {
        return $this->backupCodesHash;
    }

    public function backupCodesGeneratedAt(): ?DateTimeImmutable
    {
        return $this->backupCodesGeneratedAt;
    }

    public function extraSettings(): ?array
    {
        return $this->extraSettings;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId->toString(),
            'theme' => $this->theme->value,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'date_format' => $this->dateFormat,
            'number_format' => $this->numberFormat,
            'email_notifications' => $this->emailNotifications,
            'browser_notifications' => $this->browserNotifications,
            'session_timeout_minutes' => $this->sessionTimeoutMinutes,
            'has_backup_codes' => $this->backupCodesHash !== null,
            'backup_codes_generated_at' => $this->backupCodesGeneratedAt?->format('Y-m-d\TH:i:s\Z'),
            'extra_settings' => $this->extraSettings,
            'created_at' => $this->createdAt->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updatedAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
