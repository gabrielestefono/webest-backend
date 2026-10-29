<?php

namespace App\Exceptions;

class InternalServerErrorException extends AppException
{
    protected string $defaultMessage = 'Ocorreu um erro interno no servidor.';

    protected int $statusCode = 500;

    public function __construct(?string $message = null, mixed $data = null)
    {
        parent::__construct($message ?? $this->defaultMessage, 500, $data);
    }
}

