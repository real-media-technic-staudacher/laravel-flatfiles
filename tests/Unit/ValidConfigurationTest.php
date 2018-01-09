<?php

namespace LaravelFlatfilesTest\Unit;

use LaravelFlatfiles\FlatfileExport;
use LaravelFlatfilesTest\TestCase;
use LaravelFlatfiles\FlatfileFields;

class ValidConfigurationTest extends TestCase implements FlatfileFields
{
    /** @var FlatfileExport $flatfile */
    protected $flatfile;

    public function fields()
    {
        return [
            'field' => 'Label',
            'relation.field' => [
                'label' => 'Custom label',
                'callback' => function ($field) {
                    return $field;
                },
            ],
            [
                'column' => 'special.field',
                'label' => 'Special Label',
            ],
        ];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->flatfile = app(FlatfileExport::class, ['fields' => $this]);
    }

    /** @test */
    public function it_interpretes_valid_field_configuration()
    {
        $this->assertEquals([
            [
                'column' => 'field',
                'label' => 'Label',
            ],
            [
                'column' => 'relation.field',
                'label' => 'Custom label',
                'callback' => function ($field) {
                    return $field;
                },
            ],
            [
                'column' => 'special.field',
                'label' => 'Special Label',
            ],
        ], $this->flatfile->configuration()->fields()->values()->toArray());
    }

    /** @test */
    public function it_knows_all_columns()
    {
        $this->assertEquals(['field', 'relation.field', 'special.field'], $this->flatfile->configuration()->columns());
    }

    /** @test */
    public function it_knows_all_labels()
    {
        $this->assertEquals(['Label', 'Custom label', 'Special Label'], $this->flatfile->configuration()->fieldLabels());
    }
}
