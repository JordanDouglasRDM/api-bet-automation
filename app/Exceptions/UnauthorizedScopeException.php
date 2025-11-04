<?php

declare(strict_types = 1);

namespace App\Exceptions;

use Exception;

class UnauthorizedScopeException extends Exception
{
    public function __construct(string $message = 'Ação não autorizada.', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
