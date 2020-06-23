<?php

namespace RealMediaTechnicStaudacher\LaravelFlatfiles;

use Closure;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FlatfileExportConfiguration
{
    public $configuration;

    /** @var Collection $fields */
    protected $fields;

    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration;
    }

    /**
     * @param  array|null  $fields
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
            $callback = null;

            if (is_array($value)) {
                if (!Arr::exists($value, 'column')) {
                    return Arr::add($value, 'column', $key);
                }

                return $value;
            }

            if ($value instanceof Closure) {
                $callback = $value;
                $value = $key;
            }

            if (is_numeric($key)) {
                $key = $value;
            }

            if ($value === '') {
                return [
                    'label' => $key,
                    'callback' => function() {
                        return '';
                    },
                ];
            }

            if ($callback) {
                return [
                    'column' => $key,
                    'label' => $value,
                    'callback' => $callback,
                ];
            }

            return [
                'column' => $key,
                'label' => $value,
            ];
        })->values();

        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function fieldLabels(): array
    {
        $labels = $this->fields()->pluck('label');

        if (!$this->get('csv', 'ignore_sylk_exception') && strcmp($labels->first(), 'ID') === 0) {
            throw new Exception("SYLK file format error: Don't name your first column 'ID'");
        }

        return $labels->toArray();
    }

    public function columns(): array
    {
        return $this->fields()->pluck('column')->toArray();
    }

    public function get($driver, $key, $default = null)
    {
        return Arr::get($this->configuration, "drivers.{$driver}.{$key}", $default);
    }

    public function set($driver, $key, $value): array
    {
        return Arr::set($this->configuration, "drivers.{$driver}.{$key}", $value);
    }
}
