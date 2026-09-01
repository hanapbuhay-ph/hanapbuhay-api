<?php

namespace App\Exceptions\Auth;

use Exception;

class InvalidCredentialsException extends Exception
{
    protected $message = 'Invalid email or password.';
}
