<?php

namespace FriendsOfBotble\Instamojo\Providers;

use FriendsOfBotble\Instamojo\Contracts\Instamojo as InstamojoContract;
use FriendsOfBotble\Instamojo\Instamojo;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Support\ServiceProvider;

class InstamojoServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public const MODULE_NAME = 'instamojo';

    public function register(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        $this->app->singleton(InstamojoContract::class, function () {
            return new Instamojo();
        });
    }

    public function boot(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        $this->setNamespace('plugins/instamojo')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadRoutes();

        $this->app->booted(function () {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
