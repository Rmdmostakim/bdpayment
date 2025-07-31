<?php

namespace RmdMostakim\BdPayment;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bdpayment.php', 'bdpayment');

        $this->app->singleton('bdpayment', function ($app) {
            return new PaymentManager($app);
        });
    }

    public function boot()
    {
        // Load routes ALWAYS (for web & api requests)
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Load views ALWAYS
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'bdpayment');

        // Load migrations ALWAYS
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        if ($this->app->runningInConsole()) {
            // Publish config only when running console commands
            $this->publishes([
                __DIR__ . '/../config/bdpayment.php' => config_path('bdpayment.php'),
            ], 'config');

            // You can add more publishes here (views, assets) if needed
        }
    }
}
