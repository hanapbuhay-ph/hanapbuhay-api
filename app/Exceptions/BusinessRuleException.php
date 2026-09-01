<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BusinessRuleException extends Exception
{
    public function __construct(string $message, private readonly int $statusCode = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
