<?php

namespace App\Exceptions;

class ForbiddenException extends AppException
{
    protected string $defaultMessage = 'Acesso não permitido.';

    protected int $statusCode = 403;

    public function __construct(?string $message = null, mixed $data = null)
    {
        parent::__construct($message ?? $this->defaultMessage, 403, $data);
    }
}

