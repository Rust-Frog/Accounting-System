<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Application\Command\Identity\ActivateUserCommand;
use Application\Command\Identity\ApproveUserCommand;
use Application\Command\Identity\DeactivateUserCommand;
use Application\Command\Identity\DeclineUserCommand;
use Application\Handler\Identity\ActivateUserHandler;
use Application\Handler\Identity\ApproveUserHandler;
use Application\Handler\Identity\DeactivateUserHandler;
use Application\Handler\Identity\DeclineUserHandler;
use Domain\Identity\Repository\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for user management (admin functions).
 */
final class UserController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ApproveUserHandler $approveHandler,
        private readonly DeclineUserHandler $declineHandler,
        private readonly DeactivateUserHandler $deactivateHandler,
        private readonly ActivateUserHandler $activateHandler,
        private readonly ?\Domain\Audit\Service\SystemActivityService $activityService = null,
    ) {
    }

    /**
     * GET /api/v1/users
     * List all users (admin only).
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $role = $queryParams['role'] ?? null;
        $status = $queryParams['status'] ?? null;

        if ($role !== null) {
            $users = $this->userRepository->findByRole($role);
        } else {
            $users = $this->userRepository->findAll();
        }

        // Filter by status if provided
        if ($status !== null) {
            $users = array_filter($users, fn($user) => $user->registrationStatus()->value === $status);
        }

        $data = array_map(fn($user) => [
            'id' => $user->id()->toString(),
            'username' => $user->username(),
            'email' => $user->email()->toString(),
            'role' => $user->role()->value,
            'status' => $user->registrationStatus()->value,
            'is_active' => $user->isActive(),
            'company_id' => $user->companyId()?->toString(),
            'last_login_at' => $user->lastLoginAt()?->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $user->createdAt()->format('Y-m-d\TH:i:s\Z'),
        ], $users);

        return JsonResponse::success(array_values($data));
    }

    /**
     * POST /api/v1/users
     * Create a new user (admin only).
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        // Validate required fields
        $required = ['username', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return JsonResponse::error("Missing required field: $field", 422);
            }
        }

        // Validate role
        $roleValue = strtolower($body['role']);
        if (!in_array($roleValue, ['admin', 'tenant'], true)) {
            return JsonResponse::error('Invalid role. Must be admin or tenant', 422);
        }

        // Tenants require company_id
        if ($roleValue === 'tenant' && empty($body['company_id'])) {
            return JsonResponse::error('Tenants must have a company assigned', 422);
        }

        try {
            $email = \Domain\Shared\ValueObject\Email::fromString($body['email']);
            
            // Check if email already exists
            $existingUser = $this->userRepository->findByEmail($email);
            if ($existingUser !== null) {
                return JsonResponse::error('Email already registered', 409);
            }

            // Check if username already exists
            $existingByUsername = $this->userRepository->findByUsername($body['username']);
            if ($existingByUsername !== null) {
                return JsonResponse::error('Username already taken', 409);
            }

            $companyId = !empty($body['company_id']) 
                ? \Domain\Company\ValueObject\CompanyId::fromString($body['company_id']) 
                : null;

            $user = \Domain\Identity\Entity\User::createByAdmin(
                \Domain\Identity\ValueObject\Username::fromString($body['username']),
                $email,
                \Domain\Identity\ValueObject\Password::fromString($body['password']),
                \Domain\Identity\ValueObject\Role::from($roleValue),
                $companyId
            );

            $this->userRepository->save($user);

            // Log user creation
            $this->activityService?->log(
                activityType: 'user.created',
                entityType: 'user',
                entityId: $user->id()->toString(),
                description: "User {$user->username()} created by admin",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($request->getAttribute('user_id') ?? ''),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info',
                metadata: ['role' => $user->role()->value]
            );

            return JsonResponse::created([
                'id' => $user->id()->toString(),
                'username' => $user->username(),
                'email' => $user->email()->toString(),
                'role' => $user->role()->value,
                'status' => $user->registrationStatus()->value,
                'is_active' => $user->isActive(),
                'company_id' => $user->companyId()?->toString(),
            ]);
        } catch (\Domain\Shared\Exception\InvalidArgumentException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/users/{id}
     * Get a single user by ID.
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('id');

        $user = $this->userRepository->findById(
            \Domain\Identity\ValueObject\UserId::fromString($userId)
        );

        if ($user === null) {
            return JsonResponse::error('User not found', 404);
        }

        return JsonResponse::success([
            'id' => $user->id()->toString(),
            'username' => $user->username(),
            'email' => $user->email()->toString(),
            'role' => $user->role()->value,
            'status' => $user->registrationStatus()->value,
            'is_active' => $user->isActive(),
            'company_id' => $user->companyId()?->toString(),
            'last_login_at' => $user->lastLoginAt()?->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $user->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $user->updatedAt()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * POST /api/v1/users/{id}/approve
     * Approve a pending user registration.
     */
    public function approve(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('id');
        $approverId = $request->getAttribute('user_id');

        if ($approverId === null) {
            return JsonResponse::error('Authentication required', 401);
        }

        try {
            $command = new ApproveUserCommand($userId, $approverId);
            $dto = $this->approveHandler->handle($command);

            // Log user approval
            $this->activityService?->log(
                activityType: 'user.approved',
                entityType: 'user',
                entityId: $userId,
                description: "User {$dto->username} approved",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($approverId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info'
            );

            return JsonResponse::success($dto->toArray());
        } catch (\Domain\Shared\Exception\EntityNotFoundException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Domain\Shared\Exception\BusinessRuleException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * POST /api/v1/users/{id}/decline
     * Decline a pending user registration.
     */
    public function decline(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('id');
        $declinerId = $request->getAttribute('user_id');

        if ($declinerId === null) {
            return JsonResponse::error('Authentication required', 401);
        }

        try {
            $command = new DeclineUserCommand($userId, $declinerId);
            $dto = $this->declineHandler->handle($command);

            // Log user decline
            $this->activityService?->log(
                activityType: 'user.declined',
                entityType: 'user',
                entityId: $userId,
                description: "User {$dto->username} declined",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($declinerId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'warning'
            );

            return JsonResponse::success($dto->toArray());
        } catch (\Domain\Shared\Exception\EntityNotFoundException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Domain\Shared\Exception\BusinessRuleException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * POST /api/v1/users/{id}/deactivate
     * Deactivate a user account.
     */
    public function deactivate(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('id');
        $currentUserId = $request->getAttribute('user_id');

        // Prevent self-deactivation
        if ($userId === $currentUserId) {
            return JsonResponse::error('You cannot deactivate your own account', 422);
        }

        try {
            $command = new DeactivateUserCommand($userId);
            $dto = $this->deactivateHandler->handle($command);

            return JsonResponse::success($dto->toArray());
        } catch (\Domain\Shared\Exception\EntityNotFoundException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * POST /api/v1/users/{id}/activate
     * Reactivate a deactivated user account.
     */
    public function activate(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('id');

        try {
            $command = new ActivateUserCommand($userId);
            $dto = $this->activateHandler->handle($command);

            return JsonResponse::success($dto->toArray());
        } catch (\Domain\Shared\Exception\EntityNotFoundException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }
}
