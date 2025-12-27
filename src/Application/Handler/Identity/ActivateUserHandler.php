<?php

declare(strict_types=1);

namespace Application\Handler\Identity;

use Application\Command\CommandInterface;
use Application\Command\Identity\ActivateUserCommand;
use Application\Dto\Identity\UserDto;
use Application\Handler\HandlerInterface;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\EventDispatcherInterface;
use Domain\Shared\Exception\EntityNotFoundException;

/**
 * Handler for activating a deactivated user.
 *
 * @implements HandlerInterface<ActivateUserCommand>
 */
final readonly class ActivateUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CommandInterface $command): UserDto
    {
        assert($command instanceof ActivateUserCommand);

        $userId = UserId::fromString($command->userId);

        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new EntityNotFoundException("User not found: {$command->userId}");
        }

        $user->activate();

        $this->userRepository->save($user);

        foreach ($user->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return new UserDto(
            id: $user->id()->toString(),
            username: $user->username(),
            email: $user->email()->toString(),
            firstName: '',
            lastName: '',
            role: $user->role()->value,
            status: $user->registrationStatus()->value,
            companyId: $user->companyId()?->toString(),
            createdAt: $user->createdAt()->format('Y-m-d H:i:s'),
        );
    }
}
