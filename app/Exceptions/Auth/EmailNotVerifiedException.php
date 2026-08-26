<?php

namespace App\Exceptions\Auth;

use Exception;

class EmailNotVerifiedException extends Exception
{
    protected $message = 'Please verify your email before logging in.';
}
