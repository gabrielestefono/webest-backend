<?php

namespace App\Exceptions;

class UnauthorizedException extends AppException
{
    protected string $defaultMessage = 'Não autorizado.';

    protected int $statusCode = 401;

    public function __construct(?string $message = null, mixed $data = null)
    {
        parent::__construct($message ?? $this->defaultMessage, 401, $data);
    }
}

