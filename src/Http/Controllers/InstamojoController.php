<?php

namespace FriendsOfBotble\Instamojo\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Hotel\Models\Booking;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Botble\Payment\Supports\PaymentHelper;
use FriendsOfBotble\Instamojo\Contracts\Instamojo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class InstamojoController extends BaseController
{
    public function callback(Request $request, BaseHttpResponse $response, Instamojo $instamojo): BaseHttpResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'string'],
            'payment_status' => ['required', 'string'],
            'payment_request_id' => ['required', 'string'],
            'checkout_token' => ['required', 'string'],
        ]);

        $payment = Payment::query()
            ->where('charge_id', $validated['payment_request_id'])
            ->firstOrFail();

        // The query string is controlled by the buyer, so the payment status is read from the Instamojo API.
        $status = $this->verifiedStatus($instamojo, $payment, $validated['payment_id'], $validated['payment_request_id']);

        if ($status === null) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL($validated['checkout_token']))
                ->setMessage(__('Checkout failed!'));
        }

        if (! in_array($payment->status, [PaymentStatusEnum::COMPLETED, PaymentStatusEnum::FAILED])) {
            $payment->forceFill([
                'charge_id' => $validated['payment_id'],
                'status' => $status,
            ]);
            $payment->save();
        }

        if (is_plugin_active('hotel')) {
            $booking = Booking::query()
                ->select('transaction_id')
                ->find($payment->order_id);

            if (! $booking) {
                return $response
                    ->setNextUrl(PaymentHelper::getCancelURL($validated['checkout_token']))
                    ->setMessage(__('Checkout failed!'));
            }

            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL($booking->transaction_id))
                ->setMessage(__('Checkout successfully!'));
        }

        $nextUrl = PaymentHelper::getRedirectURL($validated['checkout_token']);

        if (is_plugin_active('job-board') || is_plugin_active('real-estate')) {
            $nextUrl = $nextUrl . '?charge_id=' . $validated['payment_id'];
        }

        return $response
            ->setNextUrl($nextUrl)
            ->setMessage(__('Checkout successfully!'));
    }

    protected function verifiedStatus(Instamojo $instamojo, Payment $payment, string $paymentId, string $paymentRequestId): ?string
    {
        try {
            $detail = $instamojo->getPaymentDetail($paymentId);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        // The payment must belong to the payment request created for this order.
        $paymentRequest = (string) data_get($detail, 'payment_request', '');

        if (
            data_get($detail, 'id') !== $paymentId
            || ! Str::of($paymentRequest)->rtrim('/')->afterLast('/')->exactly($paymentRequestId)
        ) {
            return null;
        }

        $currency = data_get($detail, 'currency');

        if ($currency && strtoupper($currency) !== strtoupper((string) $payment->currency)) {
            return null;
        }

        $apiStatus = data_get($detail, 'status');

        if ($apiStatus === true || $apiStatus === 'Credit') {
            if ((float) data_get($detail, 'amount') + 0.01 < (float) $payment->amount) {
                return null;
            }

            return PaymentStatusEnum::COMPLETED;
        }

        if ($apiStatus === false || $apiStatus === 'Failed') {
            return PaymentStatusEnum::FAILED;
        }

        return PaymentStatusEnum::PENDING;
    }
}
