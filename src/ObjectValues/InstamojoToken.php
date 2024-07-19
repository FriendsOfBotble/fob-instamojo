<?php

namespace FriendsOfBotble\Instamojo\ObjectValues;

use Botble\Setting\Facades\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class InstamojoToken
{
    protected string $accessToken;

    protected CarbonInterface $expiresAt;

    public function __construct(string $accessToken, int $expiresIn)
    {
        $this->accessToken = $accessToken;
        $this->expiresAt = Carbon::now()->addSeconds($expiresIn);

        Setting::set([
            'payment_instamojo_access_token' => $this->accessToken,
            'payment_instamojo_expires_at' => $this->expiresAt->toDateTimeString(),
        ]);

        Setting::save();
    }
}
