<?php

namespace LaravelFlatfilesTest\Unit;

use Illuminate\Filesystem\FilesystemManager;
use LaravelFlatfiles\FlatfileExport;
use LaravelFlatfiles\FlatfileFields;
use LaravelFlatfilesTest\TestCase;

class WriteStreamToDiskTest extends TestCase implements FlatfileFields
{
    /** @var FlatfileExport $flatfile */
    protected $flatfile;

    public function fields()
    {
        return [
            'field' => 'Label',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->flatfile = app(FlatfileExport::class, ['fields' => $this]);
    }

    /** @test */
    public function it_simply_works()
    {
        $disk = app(FilesystemManager::class)->createLocalDriver([
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/flatfile-tests',
        ]);

        $this->flatfile->to('test.csv', $disk);

        // Optionally add a Header
        $this->flatfile->addHeader();

        // Proposed way to step through a large result set
        collect([1,2,3,4,5,6,7,8,9,10])->each(function ($chunk) {

            dd($this->flatfile);
            $this->flatfile->addRows($chunk);
        });
    }
}
