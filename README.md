# Export Eloquent queries to flatfiles using simple configuration

[![](https://img.shields.io/github/issues-raw/real-media-technic-staudacher/laravel-flatfiles/shields.svg)]()
[![TravisCI](https://img.shields.io/travis/real-media-technic-staudacher/laravel-flatfiles.svg)](https://travis-ci.org/real-media-technic-staudacher/laravel-flatfiles)


## Installation

    composer require real-media-technic-staudacher/laravel-flatfiles:dev-master
    
To overwrite the default configuration

    php artisan vendor:publish

Then select the option `Provider: RealMediaTechnicStaudacher\LaravelFlatfiles\FlatfileExportServiceProvider`. The default configuration looks like:

```php
return [
    'default' => env('FLATFILE_DRIVER', 'csv'),

    'drivers' => [
        'csv' => [
            'charset'               => 'UTF-8',
            'delimiter'             => ';',
            'enclosure'             => '"',
            'bom'                   => true,
            'force_enclosure'       => false,
            'ignore_sylk_exception' => false,
        ],
    ],
];
```

As you see, right now, only CSV exports are supported ;-)
    
## Usage example / Basic workflow

```php
// Implement FlatfileFields to define your export fields (See later sections for details)
class ExportJob implements ShouldQueue, FlatfileFields
{
    // FlatfileExporter magically find out, whether your auto-injecting method's class implement the FlatfileFields-interface!
    // If so, it use this field definition by default
    public function handle(FlatfileExport $flatfile, $exportFilepath = '/subfolderOnDisk/export.csv')
    {
        // Expose where to export the file. Based on file extension (ie. .csv) we select the proper exporter for you)
        $flatfile->to($exportFilepath, 'diskNameOrInstance');
    
        $flatfile->addHeader();
    
        // You may want to load any data globally to prevent database queries for each row or even cell
        $imagePaths = $this->imagepaths();
    
        // Only needed for very custom contents in your flatfile!
        $flatfile->beforeEachRow(function (Model $model) use ($imagePaths) {
            // Do some very special magic to make custom image paths available for your "cells" for
            // each row.
            // Typically here you merge the globally loaded objects with the data you need for you cell
            // $model here is an eloquent model selected by queryToSelectEachRow()
        });
    
        // Here we use a query builder (if you want to) and ensure to restrict memory usage by chunking
        $this->queryToSelectEachRow()->chunk(500, function ($chunk) use ($flatfile) {
            $flatfile->addRows($chunk);
        });
    
        // Dont forget to properly "close" the operation by this command
        $flatfile->moveToTarget();
    }
    
    // In your field definition to are supposed to "only" pick out loaded or prepared data instead of
    // doing complex calculations (See beforeEachRow())
    public function fields(): array {
        return []; // Your field definition
    }
    
    // Return an elequent query builder and carefully eager load relations you will gonna use in your cells!
    protected function queryToSelectEachRow() {
        return Products::whereCategory(15)->with('images');
    }
}
```

## Load export

Easiest way is to auto-inject the `FlatfileExport` while implementing the `FlatfileFields` interface:

```php
// This will lookup for your field definition in the current class
class ExportJob implements ShouldQueue, FlatfileFields
{
    public function handle(FlatfileExport $flatfile) {}
}
```

If you want to use a dedicated class for field definitions are get the array from somewhere else use `withFields()`

```php
    public function handle(FlatfileExport $flatfile) {
        $flatfile->withFields($objImplementingFlatfileFields);
        
        // Alternatively you can resolve and assign fields in one step
        // app(FlatfileExport::class, [$objImplementingFlatfileFields]);
        // app(FlatfileExport::class, ['fields' => $objImplementingFlatfileFields]);
    }
```

## Specify target file / location

### Using filesystem disk

- This enables you to export to all available filesystem drivers
- Exports are generated locally/temporary first and than streamed to disk

```php
    $flatfile->to('relative/path/to/file-with-extension.csv', Storage::disk('name'));
    
    // Do export ...
    
    $flatfile->moveToTarget();
```

## Prepare global export resources
...
## Loop through data and write to export file

- Preselect the models that will represent a single row in your flat file
- Chunk through this result set to limit resources

```php
    public function handle()
    {
        $flatfile = app(FlatfileExport::class, [$this]);
        $flatfile->to($this->csvFilepath, $this->disk);

        // Optionally add a Header
        $flatfile->addHeader();
        
        // Proposed way to step through a large result set
        $this->queryToSelectEachRow()->chunk(500, function ($chunk) use ($flatfile) {
            $flatfile->addRows($chunk);
        });
    }

    protected function queryToSelectEachRow(): Builder
    {
        return CampaignModels::whereCampaignId($this->campaignId)->with(['model.product', 'campaign']);
    }
```
## Finish export

    $flatfile->moveToTarget();

## Define fields

Fields are defined within an object/class implementing the `FlatfileFields` interface, thus a `public function field()`.
Implement this function directly in your export-handling class, or in a dedicated sort like a DTO class.

Why?
You get the possiblity to add callbacks in your field definitions, so that you can define dynamic cells easily

In your `field()` method you need to define an array of fields.
This will generate a flatfile with one column each field array element.

Assume we load a collection of products having attributes named `product_name`. The definition looks like:

```php
    $fields = [
        'product_name' => 'Product Name'
    ];
```

The field defintions are pretty flexible. Better learn by examples by yourself

```php
    $fields = [
        'relation.columnOfRelation' => 'Column Header Label', // Relations should be eager loaded
        [
            'label'    => 'Label with special characters',
            'column'   => 'relation.columnOfRelation', // Value of param $value in callback (optional)
            'callback' => function ($model, $value) { // Format cell values
                return $model->currencySign.' '.number_format($value);
            }
        ],
        'attribute' => [ // Column name can also still be the key of the array
            'label'    => 'Label with special characters',
            'callback' => function ($model, $value) {}
        ],
        'Column header' => function() { // For callbacks the header label can also be specified in the key! Crazy...
            return 'static cell content';
        }
    ]
```

## One row for each relation

If you have a relation you want to put into one row and preserve it's parent as one row if it hasn't a relation, you can
use the following:

```php
    public function handle()
    {
        // ...

        // relation has to be loaded in items of course
        $items->each(function (Item $item) use ($export) {
          $export->addRowForEachRelation($item, ['relation', 'more.*.relations'], 'fieldAccessorAlias', true);
        });

        // ...
    }

    public function fields(): array
    {
        return [
            'fieldAccessorAlias.fieldName' => 'Output of relation fieldName if it is existing',
            'fieldAccessorAlias.fieldNameMoreRelations' => 'Output of more.*.relations fieldName if it is existing',
        ];
    }
```

## SYLK file format error

By default, an exception getting thrown if the first column in the header row is named `ID`.
Background for this is the SYLK formatting, which does not allow an flawless opening with Microsoft Excel in some Versions.
You are free to disable the exception via the config `drivers.csv.ignore_sylk_exception` again.

## Upgrade guide

### To v3 from v2

- Added return types. Also in interfaces. So mainly interfaces have to be checked: ie. `public function fields(): array;`. Tipp: Search for `public function fields(` across the whole project.
- New Namespace. Change imports from `LaravelFlatfiles\*` to `RealMediaTechnicStaudacher\LaravelFlatfiles\*`
- Changed order of callback paramters of field callback method to prevent `$null` in most of the calls `function ($null, Asset $asset)`. Now: `function (Asset $asset)`. Tipp: Search for `function ($null` and all `function fields` in your editor. 