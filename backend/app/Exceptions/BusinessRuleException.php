<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation violates a business rule (double booking, checking
 * out a stay that is not active, removing the last administrator, ...).
 *
 * Controllers translate this into a validation error / friendly message with
 * HTTP 422 instead of a 500.
 */
class BusinessRuleException extends RuntimeException
{
}
