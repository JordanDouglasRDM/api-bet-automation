<?php

declare(strict_types = 1);

namespace App\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    public function __construct(string $message = 'Usuário não autenticado.', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
