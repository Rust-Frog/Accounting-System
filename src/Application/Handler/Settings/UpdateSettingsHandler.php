<?php

declare(strict_types=1);

namespace Application\Handler\Settings;

use Domain\Identity\Entity\UserSettings;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\Repository\UserSettingsRepositoryInterface;
use Domain\Identity\ValueObject\Theme;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\NotFoundException;

/**
 * Handler for updating user settings/preferences.
 */
final class UpdateSettingsHandler
{
    public function __construct(
        private readonly UserSettingsRepositoryInterface $settingsRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Get or create settings for user.
     */
    public function getOrCreateSettings(UserId $userId): UserSettings
    {
        $settings = $this->settingsRepository->findByUserId($userId);
        
        if ($settings === null) {
            // Verify user exists
            $user = $this->userRepository->findById($userId);
            if ($user === null) {
                throw new NotFoundException('User not found');
            }
            
            $settings = UserSettings::createDefault($userId);
            $this->settingsRepository->save($settings);
        }
        
        return $settings;
    }

    /**
     * Update theme preference.
     */
    public function updateTheme(UserId $userId, string $theme): UserSettings
    {
        $settings = $this->getOrCreateSettings($userId);
        $settings->updateTheme(Theme::fromString($theme));
        $this->settingsRepository->save($settings);
        return $settings;
    }

    /**
     * Update localization preferences.
     */
    public function updateLocalization(
        UserId $userId,
        ?string $locale = null,
        ?string $timezone = null,
        ?string $dateFormat = null,
        ?string $numberFormat = null
    ): UserSettings {
        $settings = $this->getOrCreateSettings($userId);
        
        if ($locale !== null) {
            $settings->updateLocale($locale);
        }
        if ($timezone !== null) {
            $settings->updateTimezone($timezone);
        }
        if ($dateFormat !== null) {
            $settings->updateDateFormat($dateFormat);
        }
        if ($numberFormat !== null) {
            $settings->updateNumberFormat($numberFormat);
        }
        
        $this->settingsRepository->save($settings);
        return $settings;
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(
        UserId $userId,
        bool $emailNotifications,
        bool $browserNotifications
    ): UserSettings {
        $settings = $this->getOrCreateSettings($userId);
        $settings->updateNotificationPreferences($emailNotifications, $browserNotifications);
        $this->settingsRepository->save($settings);
        return $settings;
    }

    /**
     * Update session timeout.
     */
    public function updateSessionTimeout(UserId $userId, int $minutes): UserSettings
    {
        $settings = $this->getOrCreateSettings($userId);
        $settings->updateSessionTimeout($minutes);
        $this->settingsRepository->save($settings);
        return $settings;
    }
}
