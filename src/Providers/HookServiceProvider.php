<?php

namespace FriendsOfBotble\Instamojo\Providers;

use Botble\Base\Facades\Html;
use Botble\JobBoard\Models\Currency as CurrencyJobBoard;
use Botble\Payment\Facades\PaymentMethods;
use FriendsOfBotble\Instamojo\Contracts\Instamojo;
use FriendsOfBotble\Instamojo\Services\InstamojoPaymentService;
use Botble\Ecommerce\Models\Currency as CurrencyEcommerce;
use Botble\RealEstate\Models\Currency as CurrencyRealEstate;
use Botble\Hotel\Models\Currency as CurrencyHotel;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, function (string|null $settings) {
            return $settings . view('plugins/instamojo::settings')->render();
        }, 999);

        add_filter(BASE_FILTER_ENUM_ARRAY, function (array $values, string $class): array {
            if ($class === PaymentMethodEnum::class) {
                $values['INSTAMOJO'] = InstamojoServiceProvider::MODULE_NAME;
            }

            return $values;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class): string {
            if ($class === PaymentMethodEnum::class && $value === InstamojoServiceProvider::MODULE_NAME) {
                $value = 'Instamojo';
            }

            return $value;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function (string $value, string $class): string {
            if ($class === PaymentMethodEnum::class && $value === InstamojoServiceProvider::MODULE_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )->toHtml();
            }

            return $value;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, function (string|null $html, array $data): string|null {
            if (get_payment_setting('status', InstamojoServiceProvider::MODULE_NAME)) {
                $supportedCurrencies = $this->app->make(InstamojoPaymentService::class)->getSupportedCurrencies();
                $currencies = get_all_currencies()
                    ->filter(fn ($currency) => in_array($currency->title, $supportedCurrencies));

                PaymentMethods::method(InstamojoServiceProvider::MODULE_NAME, [
                    'html' => view('plugins/instamojo::method', array_merge($data, [
                        'moduleName' => InstamojoServiceProvider::MODULE_NAME,
                        'currencies' => $currencies,
                        'supportedCurrencies' => $supportedCurrencies,
                    ]))->render(),
                ]);
            }

            return $html;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function (string|null $data, string $value): string|null {
            if ($value === InstamojoServiceProvider::MODULE_NAME) {
                $data = InstamojoPaymentService::class;
            }

            return $data;
        }, 20, 2);

        add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, function (array $data, Request $request): array {
            if ($data['type'] !== InstamojoServiceProvider::MODULE_NAME) {
                return $data;
            }

            $currentCurrency = get_application_currency();

            $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

            if (strtoupper($currentCurrency->title) !== 'INR') {
                $currency = match (true) {
                    is_plugin_active('ecommerce') => CurrencyEcommerce::class,
                    is_plugin_active('job-board') => CurrencyJobBoard::class,
                    is_plugin_active('real-estate') => CurrencyRealEstate::class,
                    is_plugin_active('hotel') => CurrencyHotel::class,
                    default => null,
                };

                $supportedCurrency = $currency::query()->where('title', 'INR')->first();

                if ($supportedCurrency) {
                    $paymentData['currency'] = strtoupper($supportedCurrency->title);
                    if ($currentCurrency->is_default) {
                        $paymentData['amount'] = $paymentData['amount'] * $supportedCurrency->exchange_rate;
                    } else {
                        $paymentData['amount'] = format_price(
                            $paymentData['amount'] / $currentCurrency->exchange_rate,
                            $currentCurrency,
                            true
                        );
                    }
                }
            }

            $supportedCurrencies = $this->app->make(InstamojoPaymentService::class)->getSupportedCurrencies();

            if (! in_array($paymentData['currency'], $supportedCurrencies)) {
                $data['error'] = true;
                $data['message'] = __(
                    ":name doesn't support :currency. List of currencies supported by :name: :currencies.",
                    ['name' => InstamojoServiceProvider::MODULE_NAME, 'currency' => $data['currency'], 'currencies' => implode(', ', $supportedCurrencies)]
                );

                return $data;
            }

            $instamojo = $this->app->make(Instamojo::class);


            try {
                $response = $instamojo->createPaymentRequest([
                    'amount' => number_format($paymentData['amount'], 2, '.', ''),
                    'purpose' => $paymentData['description'],
                    'buyer_name' => $paymentData['address']['name'],
                    'email' => $paymentData['address']['email'],
                    'phone' => $paymentData['address']['phone'],
                    'redirect_url' => route('payment.instamojo.callback', [
                        'checkout_token' => $paymentData['checkout_token'],
                    ]),
                ]);

                if (! isset($response['status'])) {
                    $data['error'] = true;
                    $data['message'] = implode(', ', Arr::first($response));

                    return $data;
                }

                $status = match ($response['status']) {
                    'Failed' => PaymentStatusEnum::FAILED,
                    'Completed' => PaymentStatusEnum::COMPLETED,
                    default => PaymentStatusEnum::PENDING,
                };

                if ($response['status'] === 'Pending' && isset($response['longurl'])) {
                    do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                        'order_id' => $paymentData['order_id'],
                        'amount' => $paymentData['amount'],
                        'charge_id' => $response['id'],
                        'payment_channel' => InstamojoServiceProvider::MODULE_NAME,
                        'status' => $status,
                        'customer_id' => $paymentData['customer_id'],
                        'customer_type' => $paymentData['customer_type'],
                        'payment_type' => 'direct',
                        'currency' => $paymentData['currency'],
                    ], $request);

                    header('Location: ' . $response['longurl']);
                    exit();
                } else {
                    $data['error'] = true;
                    $data['message'] = __('Something went wrong');
                }
            } catch (Exception $exception) {
                $data['error'] = true;
                $data['message'] = $exception->getMessage();
            }

            return $data;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_PAYMENT_INFO_DETAIL, function (string $data, Payment $payment) {
            if ($payment->payment_channel->getValue() !== InstamojoServiceProvider::MODULE_NAME) {
                return $data;
            }

            $detail = $this->app->make(Instamojo::class)
                ->getPaymentDetail($payment->charge_id);

            return view('plugins/instamojo::detail', compact('detail', 'payment')) . $data;
        }, 999, 2);
    }
}
