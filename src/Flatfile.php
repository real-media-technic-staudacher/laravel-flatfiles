<?php

namespace LaravelFlatfiles;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;

class Flatfile
{
    /** @var FlatfileConfiguration $configuration */
    protected $configuration;

    /** @var FilesystemAdapter $disk */
    protected $disk;

    /** @var Writer $writer */
    protected $writer;

    /** @var String $pathToFileOnDisk */
    protected $pathToFileOnDisk;

    /** @var callable|null */
    protected $beforeEachRowCallback;

    public function __construct(FlatfileConfiguration $configuration, FlatfileFields $fields = null)
    {
        $this->configuration = $configuration;

        if ($fields !== null) {
            $this->withFields($fields);
        }
    }

    public function withFields(FlatfileFields $flatfileFields)
    {
        $this->configuration->fields($flatfileFields->fields());

        return $this;
    }

    public function exportToFileAtPath(String $absoluteFilepath)
    {
        if (file_exists($absoluteFilepath)) {
            throw new \RuntimeException('Target export file already exists at: '.$absoluteFilepath);
        }

        if (!file_exists(dirname($absoluteFilepath))) {
            mkdir($absoluteFilepath, 0777, true);
        }

        return $this->pathToFile($absoluteFilepath)->determineDefaultWriter();
    }

    public function exportToFileOnDisk(FilesystemAdapter $disk, String $targetFilename)
    {
        if ($disk->exists($targetFilename)) {
            throw new \RuntimeException('Target export file already exists at: '.$targetFilename);
        }

        return $this->disk($disk)->pathToFile($targetFilename)->determineDefaultWriter();
    }

    public function beforeEachRow(callable $callback)
    {
        $this->beforeEachRowCallback = $callback;

        return $this;
    }

    /**
     * @param Collection|Model[] $models
     */
    public function addRows(Collection $models)
    {
        foreach ($models as $model) {
            $this->addRow($model);
        }
    }

    public function addRow(Model $model)
    {
        if (false === $this->applyRowCallback($model)) {
            return;
        }

        $dataAsArray = $this->makeModelAttributesVisible($model)->toArray();

        // Grap values for eacho column from arrayed model (including relations)
        $this->writer->insertOne($this->configuration->fields()->map(function (array $fieldData) use ($dataAsArray) {
            // Get value from arrayed model by column defintion
            $value = Arr::get($dataAsArray, Arr::get($fieldData, 'column'));

            if ($callback = Arr::get($fieldData, 'callback')) {
                $value = $callback($value) ?? $value;
            }

            return $value;
        })->toArray());
    }

    public function addHeader()
    {
        $this->writer->insertOne($this->configuration->fieldLabels());
    }

    public function moveToDisk()
    {
        // TODO: Write as stream?
        $this->disk()->put($this->pathToFile(), (string)$this->writer);
    }

    private function determineDefaultWriter()
    {
        $writer = null;

        switch ($extenstion = Str::lower(pathinfo($this->pathToFile(), PATHINFO_EXTENSION))) {
            case 'csv':
                if ($this->usesDisk()) {
                    $writer = Writer::createFromFileObject(new SplTempFileObject);
                } else {
                    $writer = Writer::createFromPath($this->pathToFile(), 'w+');
                }
                $writer->setDelimiter($this->configuration->get('csv', 'delimiter'));
                $writer->setEnclosure($this->configuration->get('csv', 'enclosure'));
                $writer->setOutputBOM($this->configuration->get('csv', 'bom') ? Reader::BOM_UTF8 : '');

                break;
            default:
                throw new \RuntimeException('Unsupported file type: .'.$extenstion);
        }

        $this->writer = $writer;

        return $this;
    }

    public function pathToFile(String $relativePathToFileOnDisk = null)
    {
        if (is_null($relativePathToFileOnDisk)) {
            return $this->pathToFileOnDisk;
        }

        $this->pathToFileOnDisk = $relativePathToFileOnDisk;

        return $this;
    }

    public function disk(FilesystemAdapter $disk = null)
    {
        if (is_null($disk)) {
            return $this->disk;
        }

        $this->disk = $disk;

        return $this;
    }

    public function configuration()
    {
        return $this->configuration;
    }

    private function applyRowCallback(&$model)
    {
        $callback = $this->beforeEachRowCallback;

        if (is_callable($callback)) {
            return $callback($model);
        }

        return true;
    }

    private function makeModelAttributesVisible(Model $model): Model
    {
        return $model->makeVisible($this->configuration->columns());
    }

    private function usesDisk()
    {
        return $this->disk() !== null;
    }
}