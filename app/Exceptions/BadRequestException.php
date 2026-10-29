<?php

namespace App\Exceptions;

class BadRequestException extends AppException
{
    protected string $defaultMessage = 'Requisição inválida.';

    protected int $statusCode = 400;

    public function __construct(?string $message = null, mixed $data = null)
    {
        parent::__construct($message ?? $this->defaultMessage, 400, $data);
    }
}

