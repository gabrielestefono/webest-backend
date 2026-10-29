<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class AppException extends Exception
{
    /**
     * Default user-friendly error message.
     */
    protected string $defaultMessage = 'Ocorreu um erro na requisição.';

    /**
     * HTTP status code.
     */
    protected int $statusCode = 400;

    /**
     * Additional contextual data.
     */
    protected mixed $data = null;

    public function __construct(?string $message = null, ?int $statusCode = null, mixed $data = null)
    {
        $this->data = $data;
        $resolvedMessage = $message ?? $this->defaultMessage;
        $resolvedCode = $statusCode ?? $this->statusCode;

        parent::__construct($resolvedMessage, $resolvedCode);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => $this->data,
            'message' => $this->getMessage(),
        ], $this->getCode() ?: 400);
    }
}
