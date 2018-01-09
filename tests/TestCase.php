<?php

namespace LaravelFlatfilesTest;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return ['LaravelFlatfiles\FlatfileExportServiceProvider'];
    }
}
