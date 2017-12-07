<?php

namespace LaravelFlatfiles;


class FlatFileConfiguration
{
    protected $configuration;

    protected $fields;

    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration;
    }

    public function fields(array $fields = null)
    {
        if (is_null($fields)) {
            return $this->fields;
        }

        $this->fields = $fields;

        return $this;
    }
}