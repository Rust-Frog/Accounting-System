<?php

declare(strict_types=1);

namespace Domain\Identity\Repository;

use Domain\Identity\Entity\UserSettings;
use Domain\Identity\ValueObject\UserId;

/**
 * Repository interface for UserSettings persistence.
 */
interface UserSettingsRepositoryInterface
{
    /**
     * Find settings by user ID.
     */
    public function findByUserId(UserId $userId): ?UserSettings;

    /**
     * Save or update user settings.
     */
    public function save(UserSettings $settings): void;

    /**
     * Delete user settings (cascade handled by DB).
     */
    public function delete(UserId $userId): void;
}
