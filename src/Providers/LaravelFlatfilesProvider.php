<?php
/**
 * Copyright (c) 2015-2017  real media technic staudacher
 */

namespace LaravelFlatfiles;

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

}
