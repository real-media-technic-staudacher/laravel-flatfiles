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

    /** @var String $relativePathToFileOnDisk */
    protected $relativePathToFileOnDisk;

    /** @var callable|null */
    protected $beforeEachRowCallback;

    public function __construct(FlatfileConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function withFields($exportFields)
    {
        $this->configuration->fields($exportFields);

        return $this;
    }

    public function exportToFile(FilesystemAdapter $disk, String $targetFilename)
    {
        if ($disk->exists($targetFilename)) {
            throw new \RuntimeException('Target export file exists already');
        }

        $this->disk($disk)->pathToFileOnDisk($targetFilename);

        $this->writer = $this->detectDefaultWriter($targetFilename);

        return $this;
    }

    public function beforeEachRow(callable $callback)
    {
        $this->beforeEachRowCallback = $callback;

        return $this;
    }

    /**
     * @param Collection|Model[] $models
     */
    public function push(Collection $models)
    {
        $columns = $this->configuration->columns();

        $this->makeModelAttributesVisible($models, $columns);

        foreach ($models as $model) {
            if (false === $this->applyRowCallback($model)) {
                continue;
            }

            $data = $model->toArray();

            // Grap values for eacho column from arrayed model (including relations)
            $this->writer->insertOne(collect($columns)->map(function ($column) use ($data) {
                return Arr::get($data, $column);
            })->toArray());
        }
    }

    public function addHeader()
    {
        $this->writer->insertOne($this->configuration->fieldLabels());
    }

    public function moveToDisk()
    {
        // TODO: Write as stream?
        $this->disk()->put($this->pathToFileOnDisk(), (string)$this->writer);
    }

    private function detectDefaultWriter($targetFilename)
    {
        switch ($extenstion = Str::lower(pathinfo($targetFilename, PATHINFO_EXTENSION))) {
            case 'csv':
                $writer = Writer::createFromFileObject(new SplTempFileObject);
//                $writer->setInputEncoding($this->configuration->get('csv', 'charset'));
                $writer->setDelimiter($this->configuration->get('csv', 'delimiter'));
                $writer->setEnclosure($this->configuration->get('csv', 'enclosure'));
                $writer->setOutputBOM($this->configuration->get('csv', 'bom') ? Reader::BOM_UTF8 : '');

                return $writer;
            default:
                throw new \RuntimeException('Unsupported file type: .'.$extenstion);
        }
    }

    public function pathToFileOnDisk(String $relativePathToFileOnDisk = null)
    {
        if (is_null($relativePathToFileOnDisk)) {
            return $this->relativePathToFileOnDisk;
        }

        $this->relativePathToFileOnDisk = $relativePathToFileOnDisk;

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

    private function makeModelAttributesVisible(Collection $models, array $fields)
    {
        $models->each(function ($model) use ($fields) {
            if ($model instanceof Model) {
                $model->makeVisible($fields);
            }
        });
    }

    private function applyRowCallback(&$model)
    {
        $callback = $this->beforeEachRowCallback;

        if (is_callable($callback)) {
            return $callback($model);
        }

        return true;
    }
}