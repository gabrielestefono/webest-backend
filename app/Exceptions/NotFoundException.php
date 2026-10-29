<?php

namespace App\Exceptions;

class NotFoundException extends AppException
{
    protected string $defaultMessage = 'Recurso não encontrado.';

    protected int $statusCode = 404;

    public function __construct(?string $message = null, mixed $data = null)
    {
        parent::__construct($message ?? $this->defaultMessage, 404, $data);
    }
}

