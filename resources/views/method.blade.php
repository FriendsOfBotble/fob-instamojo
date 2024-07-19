<li class="list-group-item">
    <input class="magic-radio js_payment_method" type="radio" name="payment_method" id="payment_{{ $moduleName }}" value="{{ $moduleName }}" @checked($selecting === $moduleName)>
    <label for="payment_{{ $moduleName }}">{{ get_payment_setting('name', $moduleName) }}</label>
    <div @class(['payment_' . $moduleName . '_wrap payment_collapse_wrap collapse', 'show' => $selecting === $moduleName])>
        <p>{!! get_payment_setting('description', $moduleName) !!}</p>

        @if (! in_array(get_application_currency()->title, $supportedCurrencies) && ! get_application_currency()->replicate()->newQuery()->where('title', 'INR')->exists())
            <div class="alert alert-warning" style="margin-top: 15px;">
                {!! BaseHelper::clean(__(":name doesn't support :currency. List of currencies supported by :name: :currencies.", ['name' => ucfirst($moduleName), 'currency' => get_application_currency()->title, 'currencies' => '<strong>' . implode(', ', $supportedCurrencies) . '</strong>'])) !!}
                @if (count($currencies))
                    <div style="margin-top: 10px;">{{ __('Please switch currency to any supported currency') }}:&nbsp;&nbsp;
                        @foreach ($currencies as $currency)
                            <a href="{{ route('public.change-currency', $currency->title) }}" @class(['active' => get_application_currency_id() === $currency->id])>
                                <span>{{ $currency->title }}</span>
                            </a>
                            @if (!$loop->last)
                                &nbsp; | &nbsp;
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</li>
