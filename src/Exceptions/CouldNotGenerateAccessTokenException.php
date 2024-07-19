<?php

namespace FriendsOfBotble\Instamojo\Exceptions;

use Exception;

class CouldNotGenerateAccessTokenException extends Exception
{
    public function __construct(string $message = 'Could not generate access token.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
