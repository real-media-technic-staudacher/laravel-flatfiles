<?php

namespace LaravelFlatfilesTest\Unit;

use RealMediaTechnicStaudacher\LaravelFlatfiles\FlatfileExport;
use RealMediaTechnicStaudacher\LaravelFlatfiles\FlatfileFields;
use RealMediaTechnicStaudacher\LaravelFlatfilesTest\TestCase;

class ValidConfigurationTest extends TestCase implements FlatfileFields
{
    /** @var FlatfileExport $flatfile */
    protected $flatfile;

    public function fields(): array
    {
        return [
            'field' => 'Label',
            'relation.field' => [
                'label' => 'Custom label',
                'callback' => function ($field, $value) {
                    return $field;
                },
            ],
            [
                'column' => 'special.field',
                'label' => 'Special Label',
            ],
            'field_where_label_equals_field',
            'field_where_label_equals_field_with_callback' => function () {
                return 'value';
            },
            'Label for empty field' => '',
        ];
    }

    protected function setUp(): void
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
            [
                'column' => 'field_where_label_equals_field',
                'label' => 'field_where_label_equals_field',
            ],
            [
                'column' => 'field_where_label_equals_field_with_callback',
                'label' => 'field_where_label_equals_field_with_callback',
                'callback' => function () {
                    return 'value';
                },
            ],
            [
                'label' => 'Label for empty field',
                'callback' => function ($field) {
                    return '';
                },
            ],
        ], $this->flatfile->configuration->fields()->values()->toArray());
    }

    /** @test */
    public function it_knows_all_columns()
    {
        $this->assertEquals([
            'field',
            'relation.field',
            'special.field',
            'field_where_label_equals_field',
            'field_where_label_equals_field_with_callback',
        ], $this->flatfile->configuration->columns());
    }

    /** @test */
    public function it_knows_all_labels()
    {
        $this->assertEquals([
            'Label',
            'Custom label',
            'Special Label',
            'field_where_label_equals_field',
            'field_where_label_equals_field_with_callback',
        ], $this->flatfile->configuration->fieldLabels());
    }
}
