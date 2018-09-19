# Export Eloquent queries to flatfiles using simple configuration

[![](https://img.shields.io/github/issues-raw/real-media-technic-staudacher/laravel-flatfiles/shields.svg)]()
[![TravisCI](https://img.shields.io/travis/real-media-technic-staudacher/laravel-flatfiles.svg)](https://travis-ci.org/real-media-technic-staudacher/laravel-flatfiles)


## Installation

    composer require real-media-technic-staudacher/laravel-flatfiles:dev-master
    
To overwrite the default configuration

    php artisan vendor:publish

Then select the option `Provider: LaravelFlatfiles\FlatfileExportServiceProvider`. The default configuration looks like:

```php
return [
    'default' => env('FLATFILE_DRIVER', 'csv'),

    'drivers' => [
        'csv' => [
            'charset'       => 'UTF-8',
            'delimiter'     => ';',
            'enclosure'     => '"',
            'bom'           => true,
            'force_enclose' => false,
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
    public function handle(FlatfileExport $flatfile, $exportFilepath = '/var/www/html/storage/export.csv')
    {
        // Expose where to export the file. Based on file extension (ie. .csv) we select the proper exporter for you)
        $flatfile->toFile($exportFilepath, $replaceIfExisting = true);
    
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
    
    // In your field defintion to are supposed to "only" pick out loaded or prepared data instead of
    // doing complex calculations (See beforeEachRow())
    public function fields() {
        return []; // Your field defintion
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

### Local path

- You're a free where the file should be written to
- It will not generate a temporary file first

```php
    $flatfile->toFile('absolute/path/to/file-with-extension.csv');
```

If you want a temporary file first, use

!! TODO !!

### Using filesystem disk

- This enables you to export to all available filesystem drivers
- Exports are generated locally/temporary first and than streamed to disk

```php
    $flatfile->to('/relative/path/to/file-with-extension.csv', Storage::disk('name'));
    
    // Do export ...
    
    $flatfile->moveToDisk();
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
        $flatfile->toFile($this->csvFilepath);

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
            'column'   => 'relation.columnOfRelation' // Value of param $value in callback (optional)
            'callback' => function ($value, $model) { // Format cell values
                return $model->currencySign.' '.number_format($value);
            }
        ],
        'attribute' => [ // Column name can also still be the key of the array
            'label'    => 'Label with special characters',
            'callback' => function ($value, $model) {}
        ],
        'Column header' => function() { // For callbacks the header label can also be specified in the key! Crazy...
            return 'static cell content';
        }
    ]
```

