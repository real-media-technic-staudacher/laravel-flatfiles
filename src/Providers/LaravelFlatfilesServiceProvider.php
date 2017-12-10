<?php
/**
 * Copyright (c) 2015-2017  real media technic staudacher.
 */

namespace LaravelFlatfiles;

use Illuminate\Support\Arr;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Class LaravelFlatfilesServiceProvider.
 */
class LaravelFlatfilesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfiguration();

        $this->app->bind(Flatfile::class, function (Application $app, $parameters) {
            $config = new FlatfileConfiguration(config('flatfiles') ?: []);
            $fields = Arr::get($parameters, 'fields', Arr::first($parameters));

            return new Flatfile($config, $fields);
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
            __DIR__.'/../../config/flatfiles.php' => config_path('flatfiles.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/flatfiles.php', 'flatfiles');
    }
}
