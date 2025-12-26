<?php

declare(strict_types=1);

namespace Application\Handler\Settings;

use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\Repository\UserSettingsRepositoryInterface;
use Domain\Identity\ValueObject\Password;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\Exception\NotFoundException;

/**
 * Handler for security-related settings (password change, OTP).
 */
final class SecuritySettingsHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserSettingsRepositoryInterface $settingsRepository,
        private readonly \Infrastructure\Service\TotpService $totpService
    ) {
    }

    /**
     * Change user password.
     */
    public function changePassword(
        UserId $userId,
        string $currentPassword,
        string $newPassword
    ): void {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        // Verify current password
        if (!$user->verifyPassword($currentPassword)) {
            throw new BusinessRuleException('Current password is incorrect');
        }

        // Validate new password using Password value object
        $newPasswordVO = Password::fromString($newPassword);

        // Update password using reflection (since User doesn't expose password update)
        $this->updateUserPassword($user, $newPasswordVO);
        $this->userRepository->save($user);
    }

    /**
     * Enable OTP/2FA for user.
     * @return array{secret: string, qr_uri: string, backup_codes: array}
     */
    public function enableOtp(UserId $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        if ($user->isOtpEnabled()) {
            throw new BusinessRuleException('OTP is already enabled');
        }

        // Generate secret
        $secret = $this->totpService->generateSecret();
        $qrUri = $this->totpService->getProvisioningUri($user->email()->toString(), $secret);

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();
        $backupCodesHash = $this->hashBackupCodes($backupCodes);

        // Enable OTP on user
        $user->enableOtp($secret);
        $this->userRepository->save($user);

        // Save backup codes to settings
        $settingsHandler = new UpdateSettingsHandler($this->settingsRepository, $this->userRepository);
        $settings = $settingsHandler->getOrCreateSettings($userId);
        $settings->setBackupCodes($backupCodesHash);
        $this->settingsRepository->save($settings);

        return [
            'secret' => $secret,
            'qr_uri' => $qrUri,
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * Verify and confirm OTP setup.
     */
    public function confirmOtp(UserId $userId, string $otpCode): bool
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        if (!$user->isOtpEnabled()) {
            throw new BusinessRuleException('OTP is not enabled');
        }

        return $this->totpService->verify($user->otpSecret(), $otpCode);
    }

    /**
     * Disable OTP/2FA for user.
     */
    public function disableOtp(UserId $userId, string $password): void
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        // Require password to disable OTP
        if (!$user->verifyPassword($password)) {
            throw new BusinessRuleException('Password is incorrect');
        }

        $user->disableOtp();
        $this->userRepository->save($user);

        // Clear backup codes
        $settings = $this->settingsRepository->findByUserId($userId);
        if ($settings !== null) {
            $settings->clearBackupCodes();
            $this->settingsRepository->save($settings);
        }
    }

    /**
     * Regenerate backup codes.
     * @return array<string> New backup codes
     */
    public function regenerateBackupCodes(UserId $userId, string $password): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        // Require password
        if (!$user->verifyPassword($password)) {
            throw new BusinessRuleException('Password is incorrect');
        }

        $backupCodes = $this->generateBackupCodes();
        $backupCodesHash = $this->hashBackupCodes($backupCodes);

        $settingsHandler = new UpdateSettingsHandler($this->settingsRepository, $this->userRepository);
        $settings = $settingsHandler->getOrCreateSettings($userId);
        $settings->setBackupCodes($backupCodesHash);
        $this->settingsRepository->save($settings);

        return $backupCodes;
    }

    /**
     * Generate backup codes.
     * @return array<string>
     */
    private function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage.
     */
    private function hashBackupCodes(array $codes): string
    {
        $hashed = array_map(fn($code) => password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]), $codes);
        return json_encode($hashed);
    }

    /**
     * Update user password hash directly.
     */
    private function updateUserPassword($user, Password $newPassword): void
    {
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('passwordHash');
        $property->setAccessible(true);
        $property->setValue($user, password_hash($newPassword->toString(), PASSWORD_BCRYPT, ['cost' => 12]));

        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setAccessible(true);
        $updatedAtProperty->setValue($user, new \DateTimeImmutable());
    }
}
