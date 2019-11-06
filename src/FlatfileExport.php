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
    protected $configuration;

    /** @var FilesystemAdapter $disk */
    protected $disk;

    /** @var Writer $writer */
    protected $writer;

    /** @var string $pathToFileOnDisk */
    protected $pathToFileOnDisk;

    /** @var string $pathToFile */
    public $pathToLocalTmpFile;

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
     * @param string                   $targetFilename
     * @param FilesystemAdapter|string $disk The disk object or the name of it
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
     * @param bool   $replace
     *
     * @return $this
     * @throws \League\Csv\Exception
     */
    public function toFile(String $absoluteFilepath, $replace = false)
    {
        $fileExists = file_exists($absoluteFilepath);

        if ($replace && $fileExists) {
            \Log::debug('Delete existing export file: '.$absoluteFilepath);
            $fileExists = !unlink($absoluteFilepath);
        }

        if ($fileExists) {
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
     * @param string $tempFilename Absolut path to local disk to store a local temp file (before moving to final location)
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
     * @throws CannotInsertRecord
     */
    public function addRows(Collection $models)
    {
        foreach ($models as $model) {
            $this->addRow($model);
        }
    }

    /**
     * @param Model  $model
     * @param string $relation Name of child relation in model
     * @param string $alias    Name of attribute set with each model
     *
     * @return void
     * @throws CannotInsertRecord
     */
    public function addRowForEachRelation(Model $model, string $relation, string $alias)
    {
        if (data_get($model, $relation) === null) {
            $this->addRow($model);

            return;
        }

        foreach (data_get($model, $relation) as $relationModel) {
            $model->$alias = $relationModel;
            $this->addRow($model);
            unset($model->$alias);
        }
    }

    /**
     * @param Model|Collection $model
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

    public function moveToTarget()
    {
        $this->addBomIfNeeded();

        if (!$this->usesDisk()) {
            if ($this->pathToLocalTmpFile == $this->pathToFile()) {
                return true;
            }

            return rename($this->pathToLocalTmpFile, $this->pathToFile());
        }

        $this->disk()->putStream($this->pathToFile(), fopen($this->pathToLocalTmpFile, 'r'));

        return unlink($this->pathToLocalTmpFile);
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

    /**
     * @param Model|Collection $model
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

    private function usesDisk()
    {
        return $this->disk() !== null;
    }

    private function addBomIfNeeded()
    {
        if ($this->bomNeedsToBeAdded && !$this->checkbom()) {
            file_put_contents($this->pathToLocalTmpFile, Writer::BOM_UTF8.file_get_contents($this->pathToLocalTmpFile));
            $this->bomNeedsToBeAdded = false;
        }
    }

    public function checkbom()
    {
        $str = file_get_contents($this->pathToLocalTmpFile);
        $bom = pack('CCC', 0xef, 0xbb, 0xbf);

        return 0 === strncmp($str, $bom, 3);
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
     * @param string|null $filename
     * @param array       $headers
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadResponse(string $filename = null, array $headers = [])
    {
        if ($this->usesDisk()) {
            return $this->disk()->download($this->pathToFile(), $filename, $headers);
        }

        return response()->download($this->pathToFile(), $filename, $headers);
    }
}
