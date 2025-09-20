<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Providers;

use Illuminate\Support\ServiceProvider;
use RomegaSoftware\Availability\Support\AvailabilityEngine;
use RomegaSoftware\Availability\Support\RuleEvaluatorRegistry;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/availability.php', 'availability');

        $this->app->singleton(RuleEvaluatorRegistry::class, function ($app): RuleEvaluatorRegistry {
            $definitions = $app['config']->get('availability.rule_types', []);

            return new RuleEvaluatorRegistry($app, $definitions);
        });

        $this->app->singleton(AvailabilityEngine::class, function ($app): AvailabilityEngine {
            return new AvailabilityEngine($app->make(RuleEvaluatorRegistry::class));
        });

        $this->app->alias(AvailabilityEngine::class, 'availability.engine');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/availability.php' => config_path('availability.php'),
            ], 'availability-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/' => database_path('migrations'),
            ], 'availability-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
