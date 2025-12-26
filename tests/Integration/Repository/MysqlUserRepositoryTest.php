<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Entity\User;
use Domain\Identity\ValueObject\Password;
use Domain\Identity\ValueObject\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\Username;
use Domain\Shared\ValueObject\Email;
use Infrastructure\Persistence\Mysql\Repository\MysqlUserRepository;
use Tests\Integration\BaseIntegrationTestCase;
use Tests\Integration\DatabaseTestHelper;

class MysqlUserRepositoryTest extends BaseIntegrationTestCase
{
    use DatabaseTestHelper;

    private MysqlUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MysqlUserRepository($this->pdo);
    }

    public function testSaveAndFindById(): void
    {
        $companyId = CompanyId::generate();
        $this->createCompany($this->pdo, $companyId->toString());

        $user = User::register(
            Username::fromString('testuser'),
            Email::fromString('test@example.com'),
            Password::fromString('Password123!'),
            Role::TENANT,
            $companyId
        );

        $this->repository->save($user);

        $retrieved = $this->repository->findById($user->id());

        $this->assertNotNull($retrieved);
        $this->assertEquals('testuser', $retrieved->username());
        $this->assertEquals('test@example.com', $retrieved->email()->toString());
    }

    public function testFindByEmail(): void
    {
        $companyId = CompanyId::generate();
        $this->createCompany($this->pdo, $companyId->toString());

        $email = Email::fromString('findme@example.com');
        
        $user = User::register(
            Username::fromString('findmeuser'),
            $email,
            Password::fromString('Password123!'),
            Role::TENANT,
            $companyId
        );

        $this->repository->save($user);

        $retrieved = $this->repository->findByEmail($email);

        $this->assertNotNull($retrieved);
        $this->assertEquals($user->id()->toString(), $retrieved->id()->toString());
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findById(UserId::generate());
        $this->assertNull($result);
    }
}
