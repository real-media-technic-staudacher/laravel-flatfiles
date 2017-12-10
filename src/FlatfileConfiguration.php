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

        // Normalize the different ways to make field specifications
        $this->fields = collect($fields)->map(function ($value, $key) {
            if (is_array($value)) {
                if (! Arr::exists($value, 'column')) {
                    return Arr::add($value, 'column', $key);
                }

                return $value;
            }

            return [
                'column'   => $key,
                'label'    => $value,
            ];
        });

        return $this;
    }

    public function fieldLabels(): array
    {
        return $this->fields()->pluck('label')->toArray();
    }

    public function columns(): array
    {
        return $this->fields()->pluck('column')->toArray();
    }

    public function get($driver, $key, $default = null)
    {
        return Arr::get($this->configuration, "drivers.{$driver}.{$key}", $default);
    }
}
