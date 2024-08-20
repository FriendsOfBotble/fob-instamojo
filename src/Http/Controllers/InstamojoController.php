<?php

namespace FriendsOfBotble\Instamojo\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Hotel\Models\Booking;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Botble\Payment\Supports\PaymentHelper;
use Illuminate\Http\Request;

class InstamojoController extends BaseController
{
    public function callback(Request $request, BaseHttpResponse $response): BaseHttpResponse
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

        $status = match ($validated['payment_status']) {
            'Credit' => PaymentStatusEnum::COMPLETED,
            'Failed' => PaymentStatusEnum::FAILED,
            default => PaymentStatusEnum::PENDING,
        };

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
}
