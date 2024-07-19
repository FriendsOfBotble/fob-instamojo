<?php

namespace FriendsOfBotble\Instamojo\Exceptions;

use Exception;

class NonFieldErrorsException extends Exception
{
    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
