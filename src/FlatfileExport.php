<?php

namespace LaravelFlatfiles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LaravelFlatfiles\StreamFilters\RemoveSequence;
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;

class FlatfileExport
{
    /** @var FlatfileExportConfiguration $configuration */
    public $configuration;

    /** @var FilesystemAdapter $disk */
    protected $disk;

    /** @var Writer $writer */
    protected $writer;

    /** @var string $pathToFile */
    protected $pathToFile;

    /** @var callable|null */
    protected $beforeEachRowCallback;

    protected $bomNeedsToBeAdded = false;

    public function __construct(FlatfileExportConfiguration $configuration, FlatfileFields $fields = null)
    {
        $this->configuration = $configuration;

        if ($fields !== null) {
            $this->withFields($fields);
        }

        if ($this->configuration->get('csv', 'bom')) {
            $this->bomNeedsToBeAdded = true;
        }
    }

    public function withFields(FlatfileFields $flatfileFields)
    {
        $this->configuration->fields($flatfileFields->fields());

        return $this;
    }

    /**
     * @param  string  $targetFilepath
     * @param  FilesystemAdapter|string  $disk  The disk object or the name of it
     *
     * @return FlatfileExport
     * @throws \League\Csv\Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function to(String $targetFilepath, $disk)
    {
        $this->pathToFile = $targetFilepath;

        $this->disk = is_string($disk) ? Storage::disk($disk) : $disk;

        $this->determineDefaultWriter();
        $this->addBomIfNeeded();

        return $this;
    }

    public function stream()
    {
        if (!$this->disk->exists($this->pathToFile)) {
            $this->disk->put($this->pathToFile, '');
        }
        
        return $this->disk->readStream($this->pathToFile);
    }

    public function beforeEachRow(callable $callback)
    {
        $this->beforeEachRowCallback = $callback;

        return $this;
    }

    /**
     * @param  Collection|Model[]  $models
     *
     * @throws CannotInsertRecord
     */
    public function addRows(Collection $models)
    {
        foreach ($models as $model) {
            $this->addRow($model);
        }
    }

    /**
     * @param  Model  $model
     * @param  string|array  $relations  Name of child relation in model
     * @param  string  $alias  Name of attribute set with each model
     *
     * @return void
     * @throws CannotInsertRecord
     */
    public function addRowForEachRelation(Model $model, $relations, string $alias)
    {
        $relations = !is_array($relations) ? [$relations] : $relations;
        $hasRelation = false;

        foreach ($relations as $relation) {
            $relation = data_get($model, $relation);

            foreach ($relation as $relationModel) {
                $hasRelation = true;
                $model->$alias = $relationModel;
                $this->addRow($model);
                unset($model->$alias);
            }
        }

        // has no relations, insert only one row
        if (!$hasRelation) {
            $this->addRow($model);
        }
    }

    /**
     * @param  Model|Collection  $model
     *
     * @throws CannotInsertRecord
     */
    public function addRow($model)
    {
        if (false === $this->applyRowCallback($model)) {
            return;
        }

        $fields = $this->configuration->fields();
        $dataAsArray = $this->toArrayWithoutSnakeCasedKeys($this->makeModelAttributesVisible($model));

        // Grap values for each column from arrayed model (including relations)
        $this->writer->insertOne($fields->map(function (array $fieldConfigData) use ($dataAsArray, $model) {
            // Get value from arrayed model by column defintion
            $value = Arr::get($dataAsArray, Arr::get($fieldConfigData, 'column'));

            if ($callback = Arr::get($fieldConfigData, 'callback')) {
                $value = $callback($value, $model) ?? $value;
            }

            return $value;
        })->toArray());
    }

    /**
     * @throws CannotInsertRecord
     */
    public function addHeader()
    {
        $this->writer->insertOne($this->configuration->fieldLabels());
    }

    /**
     * @return static
     * @throws \League\Csv\Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function determineDefaultWriter()
    {
        $writer = null;

        switch ($extension = $this->targetfileExtension()) {
            case 'csv':
                $this->writer = Writer::createFromStream($this->stream());
                $this->writer->setDelimiter($this->configuration->get('csv', 'delimiter'));
                $this->writer->setEnclosure($this->configuration->get('csv', 'enclosure'));

                if ($this->configuration->get('csv', 'force_enclosure')) {
                    $this->addForceEnclosure();
                }

//                $this->writer->setOutputBOM($this->configuration->get('csv', 'bom') ? Writer::BOM_UTF8 : '');
                break;
            default:
                throw new \RuntimeException('Unsupported file type: .'.$extension);
        }

        return $this;
    }

    protected function targetfileExtension()
    {
        return Str::lower(pathinfo($this->pathToFile, PATHINFO_EXTENSION));
    }

    private function applyRowCallback(&$model)
    {
        $callback = $this->beforeEachRowCallback;

        if (is_callable($callback)) {
            return $callback($model);
        }

        return true;
    }

    /**
     * @param  Model|Collection  $model
     *
     * @return Model|Collection
     */
    private function makeModelAttributesVisible($model)
    {
        if (!($model instanceof Model)) {
            return $model;
        }

        return $model->makeVisible($this->configuration->columns());
    }

    private function addBomIfNeeded()
    {
        fseek($this->stream(), 0);
        fwrite($this->stream(), Writer::BOM_UTF8);
    }

    private function toArrayWithoutSnakeCasedKeys($model)
    {
        if (!($model instanceof Model)) {
            return $model->toArray();
        }

        $snake = $model::$snakeAttributes;

        $model::$snakeAttributes = false;
        $dataAsArray = $model->toArray();
        $model::$snakeAttributes = $snake;

        return $dataAsArray;
    }

    /**
     * adding an StreamFilter to force the enclosure of each cell.
     *
     * @throws \League\Csv\Exception
     */
    private function addForceEnclosure()
    {
        $sequence = "\t\x1f";
        $addSequence = function (array $row) use ($sequence) {
            $res = [];
            foreach ($row as $value) {
                $res[] = $sequence.$value;
            }

            return $res;
        };

        $this->writer->addFormatter($addSequence);
        RemoveSequence::registerStreamFilter();
        $this->writer->addStreamFilter(RemoveSequence::createFilterName($this->writer, $sequence));
    }

    /**
     * @param  string|null  $filename
     * @param  array  $headers
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadResponse(string $filename = null, array $headers = [])
    {
        return $this->disk->download($this->pathToFile, $filename, $headers);
    }
}
