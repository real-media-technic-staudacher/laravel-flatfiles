<?php

namespace LaravelFlatfiles;


class Flatfile
{
    /** @var FlatfileConfiguration $configuration */
    protected $configuration;

    public function __construct(FlatfileConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function withFields($exportFields)
    {
        $this->configuration->fields($exportFields);

        return $this;
    }
}