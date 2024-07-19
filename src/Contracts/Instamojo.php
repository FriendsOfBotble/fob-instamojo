<?php

namespace FriendsOfBotble\Instamojo\Contracts;

interface Instamojo
{
    public function transactionId(): string;

    public function createPaymentRequest(array $data): array;

    public function getPaymentDetail(string $id): array;

    public function createRefund(string $paymentId, array $data): array|null;
}
