<?php

declare(strict_types=1);

namespace Api\Request;

use Api\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Exception thrown when request validation fails.
 */
class ValidationException extends \Exception
{
    /** @var array<string, string[]> */
    private array $errors;

    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Validation failed');
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Convert to JSON response.
     */
    public function toResponse(): ResponseInterface
    {
        return JsonResponse::validationError($this->errors);
    }
}
