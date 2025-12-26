<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Application\Handler\Settings\SecuritySettingsHandler;
use Application\Handler\Settings\UpdateSettingsHandler;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Settings controller for user preferences and security settings.
 */
final class SettingsController
{
    public function __construct(
        private readonly UpdateSettingsHandler $settingsHandler,
        private readonly SecuritySettingsHandler $securityHandler,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * GET /api/v1/settings
     * Get current user's settings.
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $settings = $this->settingsHandler->getOrCreateSettings($userId);

            // Also include user info
            $user = $this->userRepository->findById($userId);

            return JsonResponse::success([
                'user' => [
                    'id' => $user->id()->toString(),
                    'username' => $user->username(),
                    'email' => $user->email()->toString(),
                    'role' => $user->role()->value,
                    'otp_enabled' => $user->isOtpEnabled(),
                    'created_at' => $user->createdAt()->format('Y-m-d\TH:i:s\Z'),
                ],
                'settings' => $settings->toArray(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/settings/theme
     * Update theme preference.
     */
    public function updateTheme(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            if (empty($body['theme'])) {
                return JsonResponse::error('Theme is required', 422);
            }

            $settings = $this->settingsHandler->updateTheme($userId, $body['theme']);

            return JsonResponse::success([
                'settings' => $settings->toArray(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/v1/settings/localization
     * Update localization preferences.
     */
    public function updateLocalization(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            $settings = $this->settingsHandler->updateLocalization(
                $userId,
                $body['locale'] ?? null,
                $body['timezone'] ?? null,
                $body['date_format'] ?? null,
                $body['number_format'] ?? null
            );

            return JsonResponse::success([
                'settings' => $settings->toArray(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/v1/settings/notifications
     * Update notification preferences.
     */
    public function updateNotifications(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            $settings = $this->settingsHandler->updateNotifications(
                $userId,
                $body['email_notifications'] ?? true,
                $body['browser_notifications'] ?? true
            );

            return JsonResponse::success([
                'settings' => $settings->toArray(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/v1/settings/session
     * Update session timeout.
     */
    public function updateSessionTimeout(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            if (!isset($body['session_timeout_minutes'])) {
                return JsonResponse::error('Session timeout is required', 422);
            }

            $settings = $this->settingsHandler->updateSessionTimeout(
                $userId,
                (int)$body['session_timeout_minutes']
            );

            return JsonResponse::success([
                'settings' => $settings->toArray(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/settings/password
     * Change password.
     */
    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            if (empty($body['current_password']) || empty($body['new_password'])) {
                return JsonResponse::error('Current and new passwords are required', 422);
            }

            $this->securityHandler->changePassword(
                $userId,
                $body['current_password'],
                $body['new_password']
            );

            return JsonResponse::success([
                'message' => 'Password changed successfully',
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/settings/otp/enable
     * Enable OTP/2FA.
     */
    public function enableOtp(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $result = $this->securityHandler->enableOtp($userId);

            return JsonResponse::success([
                'secret' => $result['secret'],
                'qr_uri' => $result['qr_uri'],
                'backup_codes' => $result['backup_codes'],
                'message' => 'OTP enabled. Please save your backup codes securely.',
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/settings/otp/verify
     * Verify OTP code (to confirm setup).
     */
    public function verifyOtp(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            if (empty($body['otp_code'])) {
                return JsonResponse::error('OTP code is required', 422);
            }

            $valid = $this->securityHandler->confirmOtp($userId, $body['otp_code']);

            if (!$valid) {
                return JsonResponse::error('Invalid OTP code', 401);
            }

            return JsonResponse::success([
                'message' => 'OTP verified successfully',
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/settings/otp/disable
     * Disable OTP/2FA.
     */
    public function disableOtp(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            if (empty($body['password'])) {
                return JsonResponse::error('Password is required to disable OTP', 422);
            }

            $this->securityHandler->disableOtp($userId, $body['password']);

            return JsonResponse::success([
                'message' => 'OTP disabled successfully',
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/settings/backup-codes/regenerate
     * Regenerate backup codes.
     */
    public function regenerateBackupCodes(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getCurrentUserId($request);
            $body = $request->getParsedBody();

            if (empty($body['password'])) {
                return JsonResponse::error('Password is required', 422);
            }

            $codes = $this->securityHandler->regenerateBackupCodes($userId, $body['password']);

            return JsonResponse::success([
                'backup_codes' => $codes,
                'message' => 'New backup codes generated. Please save them securely.',
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Get current user ID from request.
     */
    private function getCurrentUserId(ServerRequestInterface $request): UserId
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            throw new \RuntimeException('Not authenticated');
        }
        return UserId::fromString($userId);
    }
}
