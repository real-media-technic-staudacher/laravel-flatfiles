<?php
/**
 * Copyright (c) 2015-2018  real media technic staudacher.
 */

namespace LaravelFlatfiles;

use Illuminate\Support\Arr;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Class FlatfileExportServiceProvider.
 */
class FlatfileExportServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfiguration();

        $this->app->bind(FlatfileExport::class, function (Application $app, $parameters) {
            $config = new FlatfileExportConfiguration(config('flatfiles') ?: []);
            $fields = Arr::get($parameters, 'fields', Arr::first($parameters));

            return new FlatfileExport($config, $fields);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
    }

    private function registerCommands()
    {
        /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $this->commands([//            \LaravelFlatfiles\Command::class,
        ]);
    }

    private function publishConfiguration()
    {
        $this->publishes([
            __DIR__.'/../config/flatfiles.php' => config_path('flatfiles.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../config/flatfiles.php', 'flatfiles');
    }
}
