<?php

namespace App\Utility\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(string $message, int $code = 412)
    {
        parent::__construct($message, $code);
    }
}
