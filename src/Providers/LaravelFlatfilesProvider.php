<?php
/**
 * Copyright (c) 2015-2017  real media technic staudacher
 */

namespace LaravelFlatfiles;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Class LaravelFlatfilesProvider
 *
 * @package LaravelFlatfiles
 */
class LaravelFlatfilesProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfiguration();

        $this->app->bind(FlatFile::class, function (Application $app) {
            return new FlatFile(new FlatFileConfiguration(config('flatfiles')));
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
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $this->commands([//            \LaravelFlatfiles\Command::class,
        ]);
    }

    private function publishConfiguration()
    {
        $this->publishes([
            __DIR__.'/../../config/flatfiles.php' => config_path('flatfiles.php'),
        ], 'config');
    }
}
