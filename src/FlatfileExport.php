<?php

namespace LaravelFlatfiles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Writer;

class FlatfileExport
{
    /** @var FlatfileExportConfiguration $configuration */
    protected $configuration;

    /** @var FilesystemAdapter $disk */
    protected $disk;

    /** @var Writer $writer */
    protected $writer;

    /** @var string $pathToFileOnDisk */
    protected $pathToFileOnDisk;

    /** @var string $pathToFile */
    protected $pathToLocalTmpFile;

    /** @var callable|null */
    protected $beforeEachRowCallback;

    public function __construct(FlatfileExportConfiguration $configuration, FlatfileFields $fields = null)
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

    /**
     * @param String                   $targetFilename
     * @param FilesystemAdapter|String $disk The disk object or the name of it
     *
     * @return FlatfileExport
     * @throws \League\Csv\Exception
     */
    public function to(String $targetFilename, $disk)
    {
        $this->pathToFile($targetFilename);

        if (is_string($disk)) {
            $disk = Storage::disk($disk);
        }

        $this->disk = $disk;

        $this->determineDefaultWriter();

        return $this;
    }

    /**
     * @param string $absoluteFilepath
     *
     * @return $this
     * @throws \League\Csv\Exception
     */
    public function toFile(String $absoluteFilepath)
    {
        if (file_exists($absoluteFilepath)) {
            throw new \RuntimeException('Target export file already exists at: '.$absoluteFilepath);
        }

        if (!file_exists(dirname($absoluteFilepath))) {
            mkdir($absoluteFilepath, 0777, true);
        }

        $this->pathToFile($absoluteFilepath);
        $this->determineDefaultWriter();

        return $this;
    }

    /**
     * You can set a file location for the temporary file used to generate the export file. It's only locally, because
     * we're using a streaming API.
     *
     * @param String $tempFilename Absolut path to local disk to store a local temp file (before moving to final location)
     *
     * @return $this
     */
    public function usingLocalTmpFile(String $tempFilename)
    {
        $this->pathToLocalTmpFile = $tempFilename;

        return $this;
    }

    public function beforeEachRow(callable $callback)
    {
        $this->beforeEachRowCallback = $callback;

        return $this;
    }

    /**
     * @param Collection|Model[] $models
     *
     * @throws \League\Csv\CannotInsertRecord
     */
    public function addRows(Collection $models)
    {
        foreach ($models as $model) {
            $this->addRow($model);
        }
    }

    /**
     * @param Model $model
     *
     * @throws \League\Csv\CannotInsertRecord
     */
    public function addRow(Model $model)
    {
        if (false === $this->applyRowCallback($model)) {
            return;
        }

        $fields = $this->configuration->fields();
        $dataAsArray = $this->makeModelAttributesVisible($model)->toArray();

        // Grap values for eacho column from arrayed model (including relations)
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
     * @throws \League\Csv\CannotInsertRecord
     */
    public function addHeader()
    {
        $this->writer->insertOne($this->configuration->fieldLabels());
    }

    /**
     * Skips the generation of a temporary file. Dont call moveToTarget() in this case because its not needed
     *
     * @return FlatfileExport
     */
    public function withoutTempFile()
    {
        return $this->usingLocalTmpFile($this->pathToFile());
    }

    public function moveToTarget()
    {
        $this->disk()->putStream($this->pathToFile(), fopen($this->pathToLocalTmpFile, 'r'));

        unlink($this->pathToLocalTmpFile);
    }

    /**
     * @return $this
     * @throws \League\Csv\Exception
     */
    private function determineDefaultWriter()
    {
        $writer = null;

        switch ($extension = $this->targetfileExtension()) {
            case 'csv':
                if (!$this->pathToLocalTmpFile) {
                    if ($this->usesDisk()) {
                        $this->pathToLocalTmpFile = tempnam(sys_get_temp_dir(), 'ffe');
                    } else {
                        $this->pathToLocalTmpFile = $this->pathToFile();
                    }
                }

                $this->writer = Writer::createFromPath($this->pathToLocalTmpFile, 'w+');

                $this->writer->setDelimiter($this->configuration->get('csv', 'delimiter'));
                $this->writer->setEnclosure($this->configuration->get('csv', 'enclosure'));
                $this->writer->setOutputBOM($this->configuration->get('csv', 'bom') ? Writer::BOM_UTF8 : '');
                break;
            default:
                throw new \RuntimeException('Unsupported file type: .'.$extension);
        }

        return $this;
    }

    protected function targetfileExtension()
    {
        return Str::lower(pathinfo($this->pathToFile(), PATHINFO_EXTENSION));
    }

    public function pathToFile(String $relativePathToFileOnDisk = null)
    {
        if (is_null($relativePathToFileOnDisk)) {
            return $this->pathToFileOnDisk;
        }

        $this->pathToFileOnDisk = $relativePathToFileOnDisk;

        return $this;
    }

    /**
     * @return FilesystemAdapter
     */
    public function disk()
    {
        return $this->disk;
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
