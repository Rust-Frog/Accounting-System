<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Application\Handler\Admin\SetupAdminHandler;
use Domain\Identity\Repository\UserRepositoryInterface;
use Infrastructure\Service\TotpService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SetupController
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SetupAdminHandler $setupHandler,
        private TotpService $totpService
    ) {
    }

    public function status(): ResponseInterface
    {
        $initialized = $this->userRepository->hasAnyAdmin();
        return JsonResponse::success(['is_setup_required' => !$initialized]);
    }

    public function init(): ResponseInterface
    {
        if ($this->userRepository->hasAnyAdmin()) {
            return JsonResponse::error('System already initialized', 403);
        }

        $secret = $this->totpService->generateSecret();
        // In a real app, hostname would be dynamic
        $uri = $this->totpService->getProvisioningUri('admin@local', $secret, 'AccountingSystem');

        return JsonResponse::success([
            'secret' => $secret,
            'provisioning_uri' => $uri
        ]);
    }

    public function complete(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        
        if (!isset($body['username'], $body['email'], $body['password'], $body['otp_secret'], $body['otp_code'])) {
            return JsonResponse::error('Missing required fields', 422);
        }

        try {
            $command = new \Application\Command\Admin\SetupAdminCommand(
                $body['username'],
                $body['email'],
                $body['password'],
                $body['otp_secret'],
                $body['otp_code']
            );
            $result = $this->setupHandler->handle($command);
            return JsonResponse::created($result);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return JsonResponse::error('Setup failed: ' . $e->getMessage(), 500);
        }
    }
}
