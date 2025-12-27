<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Hydrator;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Entity\User;
use Domain\Identity\ValueObject\RegistrationStatus;
use Domain\Identity\ValueObject\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Email;
use ReflectionClass;

/**
 * Hydrates User entities from database rows and extracts data for persistence.
 */
final class UserHydrator
{
    /**
     * Hydrate a User entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): User
    {
        // User has a private constructor, so we use Reflection
        $reflection = new ReflectionClass(User::class);
        $user = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $user, 'userId', UserId::fromString($row['id']));
        $this->setProperty(
            $reflection,
            $user,
            'companyId',
            $row['company_id'] !== null ? CompanyId::fromString($row['company_id']) : null
        );
        $this->setProperty($reflection, $user, 'username', $row['username']);
        $this->setProperty($reflection, $user, 'email', Email::fromString($row['email']));
        $this->setProperty($reflection, $user, 'passwordHash', $row['password_hash']);
        $this->setProperty($reflection, $user, 'role', Role::from($row['role']));
        $this->setProperty(
            $reflection,
            $user,
            'registrationStatus',
            RegistrationStatus::from($row['registration_status'])
        );
        $this->setProperty($reflection, $user, 'isActive', (bool) $row['is_active']);
        $this->setProperty(
            $reflection,
            $user,
            'lastLoginAt',
            $row['last_login_at'] !== null ? new DateTimeImmutable($row['last_login_at']) : null
        );
        $this->setProperty($reflection, $user, 'lastLoginIp', $row['last_login_ip'] ?? null);
        $this->setProperty($reflection, $user, 'otpSecret', $row['otp_secret'] ?? null);
        $this->setProperty($reflection, $user, 'createdAt', new DateTimeImmutable($row['created_at']));
        $this->setProperty($reflection, $user, 'updatedAt', new DateTimeImmutable($row['updated_at']));
        $this->setProperty($reflection, $user, 'domainEvents', []);

        return $user;
    }

    /**
     * Extract data from User entity for persistence.
     *
     * @return array<string, mixed>
     */
    public function extract(User $user): array
    {
        return [
            'id' => $user->id()->toString(),
            'company_id' => $user->companyId()?->toString(),
            'username' => $user->username(),
            'email' => $user->email()->toString(),
            'password_hash' => $user->passwordHash(),
            'role' => $user->role()->value,
            'registration_status' => $user->registrationStatus()->value,
            'is_active' => $user->isActive() ? 1 : 0,
            'last_login_at' => $user->lastLoginAt()?->format('Y-m-d H:i:s'),
            'last_login_ip' => $user->lastLoginIp(),
            'otp_secret' => $user->otpSecret(),
            'created_at' => $user->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Set a property value using reflection.
     */
    private function setProperty(ReflectionClass $reflection, object $object, string $property, mixed $value): void
    {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
