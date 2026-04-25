<?php

namespace App\Exceptions;

use Exception;

class PayrollLockedException extends Exception
{
    public function __construct(string $message = 'Payroll for the target month is locked')
    {
        parent::__construct($message);
    }
}
