<?php

namespace LaravelFlatfiles;


use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FlatfileConfiguration
{
    public $configuration;

    /** @var Collection $fields */
    protected $fields;

    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration;
    }

    /**
     * @param array|null $fields
     *
     * @return $this|Collection
     */
    public function fields(array $fields = null)
    {
        if (is_null($fields)) {
            return $this->fields;
        }

        $this->fields = collect($fields);

        return $this;
    }

    public function fieldLabels(): array
    {
        return $this->fields()->values()->map(function ($fieldValue) {
            if (is_array($fieldValue)) {
                return $fieldValue['label'];
            }

            return $fieldValue;
        })->toArray();
    }

    public function columns(): array
    {
        return $this->fields()->map(function ($fieldValue, $fieldColumn) {
            if (is_array($fieldValue)) {
                return $fieldValue['column'];
            }

            return $fieldColumn;
        })->values()->toArray();
    }

    public function get($driver, $key, $default = null)
    {
        return Arr::get($this->configuration, "drivers.{$driver}.{$key}", $default);
    }
}