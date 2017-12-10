<?php

namespace LaravelFlatfilesTest;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return ['LaravelFlatfiles\Providers\LaravelFlatfilesServiceProvider'];
    }
}