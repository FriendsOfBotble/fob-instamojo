<?php

namespace FriendsOfBotble\Instamojo\Exceptions;

use Exception;

class ClientIdOrSecretNotProvidedException extends Exception
{
    public function __construct(string $message = 'Client ID or Client Secret not provided', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
