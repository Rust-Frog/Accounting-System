<?php

declare(strict_types=1);

namespace Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Event\UserRegistered;
use Domain\Identity\ValueObject\RegistrationStatus;
use Domain\Identity\ValueObject\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Exception\AuthenticationException;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\Exception\InvalidArgumentException;
use Domain\Shared\ValueObject\Email;

final class User
{
    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    private function __construct(
        private readonly UserId $userId,
        private ?CompanyId $companyId,
        private string $username,
        private Email $email,
        private string $passwordHash,
        private Role $role,
        private RegistrationStatus $registrationStatus,
        private bool $isActive,
        private ?DateTimeImmutable $lastLoginAt,
        private ?string $lastLoginIp,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
    }

    public static function register(
        string $username,
        Email $email,
        string $password,
        Role $role,
        ?CompanyId $companyId = null
    ): self {
        // BR-IAM-003: Password validation
        self::validatePassword($password);

        // BR-IAM-006: Admins have no company
        if ($role === Role::ADMIN && $companyId !== null) {
            throw new InvalidArgumentException('Admins cannot belong to a company');
        }

        // BR-IAM-007: Tenants must have company
        if ($role === Role::TENANT && $companyId === null) {
            throw new InvalidArgumentException('Tenants must belong to a company');
        }

        $now = new DateTimeImmutable();

        $user = new self(
            userId: UserId::generate(),
            companyId: $companyId,
            username: $username,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            role: $role,
            registrationStatus: RegistrationStatus::PENDING, // BR-IAM-005
            isActive: true,
            lastLoginAt: null,
            lastLoginIp: null,
            createdAt: $now,
            updatedAt: $now
        );

        $user->recordEvent(new UserRegistered($user->userId, $user->email, $user->role));

        return $user;
    }

    public function authenticate(string $password): bool
    {
        // BR-IAM-009: Deactivated users cannot authenticate
        if (!$this->isActive) {
            throw new AuthenticationException('User account is deactivated');
        }

        // BR-IAM-005: Pending users cannot authenticate
        if ($this->registrationStatus === RegistrationStatus::PENDING) {
            throw new AuthenticationException('User registration pending approval');
        }

        if ($this->registrationStatus === RegistrationStatus::DECLINED) {
            throw new AuthenticationException('User registration was declined');
        }

        $valid = password_verify($password, $this->passwordHash);

        if ($valid) {
            $this->lastLoginAt = new DateTimeImmutable();
            $this->updatedAt = new DateTimeImmutable();
        }

        return $valid;
    }

    public function approve(UserId $approverId): void
    {
        // BR-IAM-010: Cannot self-approve
        if ($this->userId->equals($approverId)) {
            throw new BusinessRuleException('Users cannot approve themselves');
        }

        if ($this->registrationStatus !== RegistrationStatus::PENDING) {
            throw new BusinessRuleException('Only pending registrations can be approved');
        }

        $this->registrationStatus = RegistrationStatus::APPROVED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function decline(UserId $declinerId): void
    {
        if ($this->userId->equals($declinerId)) {
            throw new BusinessRuleException('Users cannot decline themselves');
        }

        if ($this->registrationStatus !== RegistrationStatus::PENDING) {
            throw new BusinessRuleException('Only pending registrations can be declined');
        }

        $this->registrationStatus = RegistrationStatus::DECLINED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function validatePassword(string $password): void
    {
        // BR-IAM-003: Password requirements
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidArgumentException('Password must contain uppercase letter');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new InvalidArgumentException('Password must contain lowercase letter');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('Password must contain digit');
        }
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return array<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Getters
    public function id(): UserId
    {
        return $this->userId;
    }

    public function companyId(): ?CompanyId
    {
        return $this->companyId;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function registrationStatus(): RegistrationStatus
    {
        return $this->registrationStatus;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function lastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
