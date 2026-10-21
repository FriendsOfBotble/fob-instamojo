<?php

namespace FriendsOfBotble\Instamojo;

use FriendsOfBotble\Instamojo\Contracts\Instamojo as InstamojoContract;
use FriendsOfBotble\Instamojo\Exceptions\ClientIdOrSecretNotProvidedException;
use FriendsOfBotble\Instamojo\Exceptions\CouldNotGenerateAccessTokenException;
use FriendsOfBotble\Instamojo\Exceptions\InvalidInstamojoEnvironmentException;
use FriendsOfBotble\Instamojo\ObjectValues\InstamojoToken;
use FriendsOfBotble\Instamojo\Providers\InstamojoServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Instamojo implements InstamojoContract
{
    protected array $endpoint = [
        'sandbox' => 'https://test.instamojo.com',
        'production' => 'https://api.instamojo.com',
    ];

    public function transactionId(): string
    {
        return Str::random(10);
    }

    public function createPaymentRequest(array $data): array
    {
        return $this->request('POST', '/v2/payment_requests/', $data);
    }

    public function getPaymentDetail(string $id): array
    {
        return $this->request('GET', "/v2/payments/$id/");
    }

    public function createRefund(string $paymentId, array $data): array|null
    {
        return $this->request('POST', "/v2/payments/$paymentId/refund/", $data);
    }

    protected function request(string $method, string $uri, array $data = [], bool $retried = false): array
    {
        $pendingRequest = Http::baseUrl($this->getEndpointUrl());

        if ($accessToken = $this->getAccessToken()) {
            $pendingRequest->withToken($accessToken);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response =  $pendingRequest->{$method}($uri, $data);

        // Refresh an expired token once; retrying forever would loop when the credentials are wrong.
        if ($response->unauthorized() && ! $retried) {
            $this->generateAccessToken();

            return $this->request($method, $uri, $data, true);
        }

        return (array) $response->json();
    }

    protected function generateAccessToken(): InstamojoToken
    {
        $clientId = get_payment_setting('client_id', InstamojoServiceProvider::MODULE_NAME);
        $clientSecret = get_payment_setting('client_secret', InstamojoServiceProvider::MODULE_NAME);

        if (empty($clientId) || empty($clientSecret)) {
            throw new ClientIdOrSecretNotProvidedException();
        }

        // The token endpoint takes a form-encoded body and no bearer token, so call it directly.
        $response = Http::asForm()->post($this->getEndpointUrl('/oauth2/token/'), [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new CouldNotGenerateAccessTokenException('Could not get an Instamojo access token, please check the client ID and secret.');
        }

        return new InstamojoToken((string) $response->json('access_token'), (int) $response->json('expires_in'));
    }

    protected function getEndpointUrl(string $uri = null): string
    {
        $environment = get_payment_setting('environment', InstamojoServiceProvider::MODULE_NAME);

        if (! in_array($environment, array_keys($this->endpoint))) {
            throw new InvalidInstamojoEnvironmentException();
        }

        return $this->endpoint[$environment] . $uri;
    }

    protected function getAccessToken(): string|null
    {
        return get_payment_setting('access_token', InstamojoServiceProvider::MODULE_NAME);
    }
}
