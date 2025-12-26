<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Identity\Entity\User;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Email;
use Infrastructure\Persistence\Mysql\Hydrator\UserHydrator;
use PDO;

/**
 * MySQL implementation of UserRepositoryInterface.
 */
final class MysqlUserRepository extends AbstractMysqlRepository implements UserRepositoryInterface
{
    private UserHydrator $hydrator;

    public function __construct(?PDO $connection = null)
    {
        parent::__construct($connection);
        $this->hydrator = new UserHydrator();
    }

    public function save(User $user): void
    {
        $data = $this->hydrator->extract($user);

        // Check if user exists
        $exists = $this->exists(
            'SELECT 1 FROM users WHERE id = :id',
            ['id' => $data['id']]
        );

        if ($exists) {
            $this->update($data);
        } else {
            $this->insert($data);
        }
    }

    public function findById(UserId $userId): ?User
    {
        $row = $this->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            ['id' => $userId->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $row = $this->fetchOne(
            'SELECT * FROM users WHERE username = :username',
            ['username' => $username]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $row = $this->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            ['email' => $email->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function existsByUsername(string $username): bool
    {
        return $this->exists(
            'SELECT 1 FROM users WHERE username = :username',
            ['username' => $username]
        );
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->exists(
            'SELECT 1 FROM users WHERE email = :email',
            ['email' => $email->toString()]
        );
    }

    /**
     * @return array<User>
     */
    public function findPendingUsers(): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM users WHERE registration_status = 'pending' ORDER BY created_at ASC"
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    public function hasAnyAdmin(): bool
    {
        return $this->exists(
            "SELECT 1 FROM users WHERE role = 'admin'"
        );
    }

    /**
     * @return array<User>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->fetchPaged(
            'SELECT * FROM users ORDER BY created_at DESC',
            [],
            new \Domain\Shared\ValueObject\Pagination($limit, $offset)
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @return array<User>
     */
    public function findByRole(string $role): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM users WHERE role = :role ORDER BY created_at DESC',
            ['role' => $role]
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * Insert a new user.
     *
     * @param array<string, mixed> $data
     */
    private function insert(array $data): void
    {
        $sql = <<<SQL
            INSERT INTO users (
                id, company_id, username, email, password_hash, role, 
                registration_status, is_active, last_login_at, last_login_ip, 
                otp_secret,
                created_at, updated_at
            ) VALUES (
                :id, :company_id, :username, :email, :password_hash, :role,
                :registration_status, :is_active, :last_login_at, :last_login_ip,
                :otp_secret,
                :created_at, :updated_at
            )
        SQL;

        $this->execute($sql, $data);
    }

    /**
     * Update an existing user.
     *
     * @param array<string, mixed> $data
     */
    private function update(array $data): void
    {
        $sql = <<<SQL
            UPDATE users SET
                company_id = :company_id,
                username = :username,
                email = :email,
                password_hash = :password_hash,
                role = :role,
                registration_status = :registration_status,
                is_active = :is_active,
                last_login_at = :last_login_at,
                last_login_ip = :last_login_ip,
                otp_secret = :otp_secret,
                updated_at = :updated_at
            WHERE id = :id
        SQL;

        $this->execute($sql, $data);
    }
}
