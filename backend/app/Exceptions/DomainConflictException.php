<?php

namespace App\Exceptions;

use RuntimeException;

class DomainConflictException extends RuntimeException
{
    public function __construct(string $message = 'Xung đột trạng thái dữ liệu.')
    {
        parent::__construct($message, 409);
    }
}
