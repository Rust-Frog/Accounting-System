<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

/**
 * Marker interface for exceptions that are safe to expose to the client.
 * 
 * Exceptions implementing this interface will have their messages returned
 * in the API response instead of a generic error message.
 */
interface SafeToExposeInterface
{
}
