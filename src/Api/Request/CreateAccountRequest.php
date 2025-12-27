<?php

declare(strict_types=1);

namespace Api\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Validated request for account creation.
 */
final class CreateAccountRequest extends ValidatedRequest
{
    protected function validate(): void
    {
        $this->requireField('code', 'Account code');
        $this->requireInteger('code', 'Account code');
        $this->requirePositive('code', 'Account code');

        $this->requireField('name', 'Account name');
        $this->requireMinLength('name', 1, 'Account name');
        $this->requireMaxLength('name', 100, 'Account name');

        // Description is optional, but validate max length if provided
        if ($this->has('description')) {
            $this->requireMaxLength('description', 500, 'Description');
        }
    }

    public function code(): int
    {
        return (int) $this->get('code');
    }

    public function name(): string
    {
        return (string) $this->get('name');
    }

    public function description(): ?string
    {
        return $this->get('description');
    }

    public function parentId(): ?string
    {
        return $this->get('parent_id');
    }
}
