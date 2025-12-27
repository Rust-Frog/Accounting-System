<?php

declare(strict_types=1);

namespace Api\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Validated request for user registration.
 */
final class RegisterRequest extends ValidatedRequest
{
    protected function validate(): void
    {
        $this->requireField('username', 'Username');
        $this->requireMinLength('username', 3, 'Username');
        $this->requireMaxLength('username', 50, 'Username');

        $this->requireField('email', 'Email');
        $this->requireEmail('email');

        $this->requireField('password', 'Password');
        $this->requireMinLength('password', 8, 'Password');
    }

    public function username(): string
    {
        return (string) $this->get('username');
    }

    public function email(): string
    {
        return (string) $this->get('email');
    }

    public function password(): string
    {
        return (string) $this->get('password');
    }

    public function companyId(): ?string
    {
        return $this->get('company_id');
    }

    public function role(): ?string
    {
        return $this->get('role');
    }
}
