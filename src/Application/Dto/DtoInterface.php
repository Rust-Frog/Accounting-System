<?php

declare(strict_types=1);

namespace Application\Dto;

/**
 * Base interface for Data Transfer Objects.
 * DTOs are used to transfer data between application layers.
 */
interface DtoInterface
{
    /**
     * Convert DTO to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
