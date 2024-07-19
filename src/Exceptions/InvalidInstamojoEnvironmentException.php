<?php

namespace FriendsOfBotble\Instamojo\Exceptions;

use Exception;

class InvalidInstamojoEnvironmentException extends Exception
{
    public function __construct(string $message = 'Invalid Instamojo environment. Supported: sandbox, production.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
