<?php

namespace FriendsOfBotble\Instamojo\Services;

use FriendsOfBotble\Instamojo\Contracts\Instamojo;
use Botble\Payment\Models\Payment;

class InstamojoPaymentService extends PaymentServiceAbstract
{
    public function isSupportRefundOnline(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return [
            'INR',
        ];
    }

    public function refund(string $chargeId, float $amount): array
    {
        $instamojo = app(Instamojo::class);

        $orderId = Payment::query()
            ->where('charge_id', $chargeId)
            ->value('order_id');

        $response = $instamojo->createRefund($chargeId, [
            'type' => 'PTH',
            'refund_amount' => $amount,
            'body' => request()->input('refund_note') ?: __('Refund for order :order', ['order' => $orderId]),
            'transaction_id' => $instamojo->transactionId(),
        ]);

        if ($response === null) {
            return [
                'error' => true,
                'message' => __('Already refunded!'),
            ];
        }

        if ($response['success'] === false) {
            return [
                'error' => true,
                'message' => $response['message'],
            ];
        }

        return [
            'error' => false,
            'data' => $response['refund'],
        ];
    }
}
