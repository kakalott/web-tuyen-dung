<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AIServiceException extends RuntimeException
{
    public function __construct(string $message = 'Dịch vụ AI tạm thời không khả dụng.', ?Throwable $previous = null)
    {
        parent::__construct($message, 502, $previous);
    }
}
