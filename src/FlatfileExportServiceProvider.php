<?php
/**
 * Copyright (c) 2015-2018  real media technic staudacher.
 */

namespace RealMediaTechnicStaudacher\LaravelFlatfiles;

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

            if (! $fields) {
                $fields = $this->fieldsFromAutoInjectingClass(debug_backtrace());
            }

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
        $this->commands([
        ]);
    }

    private function publishConfiguration()
    {
        $this->publishes([
            __DIR__.'/../config/flatfiles.php' => config_path('flatfiles.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../config/flatfiles.php', 'flatfiles');
    }

    /**
     * @param $debugBacktrace
     *
     * @return FlatfileFields|null
     */
    private function fieldsFromAutoInjectingClass($debugBacktrace)
    {
        $firstTry = collect($debugBacktrace)->transform(function ($item) {
            // Auto injection detected?
            if ('getMethodDependencies' != Arr::get($item, 'function')) {
                return;
            }

            $classRequesting = Arr::get(Arr::get($item, 'args', []), '1.0');

            if (! $classRequesting) {
                return;
            }

            if ($classRequesting instanceof FlatfileFields) {
                return $classRequesting;
            }
        })->filter()->first();

        if ($firstTry) {
            return $firstTry;
        }

        return collect($debugBacktrace)->transform(function ($item) {
            // Auto injection detected?
            if ('resolveMethodDependencies' != Arr::get($item, 'function')) {
                return;
            }

            $classRequesting = Arr::get(Arr::get($item, 'args', []), '1');

            if (! $classRequesting) {
                return;
            }

            if ($classRequesting instanceof \ReflectionMethod) {
                $objectOfClass = app($classRequesting->class);
                if ($objectOfClass instanceof FlatfileFields) {
                    return $objectOfClass;
                }
            }
        })->filter()->first();
    }
}
