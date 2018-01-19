# Export Eloquent queries to flatfiles using simple configuration

[![](https://img.shields.io/github/issues-raw/real-media-technic-staudacher/laravel-flatfiles/shields.svg)]()
[![TravisCI](https://img.shields.io/travis/real-media-technic-staudacher/laravel-flatfiles.svg)](https://travis-ci.org/real-media-technic-staudacher/laravel-flatfiles)


## Installation

    composer require real-media-technic-staudacher/laravel-flatfiles:dev-master
    
## Usage

```php
public function handle()
{
    // In this case we do not use Laravel disks because we deal with local files
    $exportFilepath = '/var/www/html/export/myfile.csv';
    
    // Initialize Exporter with current class holding the field definitions in field()-method. See later.
    $flatfile = app(FlatfileExport::class, [$this]);

    // An exception is thrown if the file you want to export already exists. You can handle this better than the package!
    if (file_exists($exportFilepath)) {
        \Log::info('Deleting existing export file: '.$exportFilepath);
        unlink($exportFilepath);
    }

    // Expose where to export the file. Based on file extension (ie. .csv) we select the proper exporter for you)
    $flatfile->toFile($exportFilepath);

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

    // Here we use a query builder (if you want to).
    $this->queryToSelectEachRow()->chunk(500, function ($chunk) use ($flatfile) {
        $flatfile->addRows($chunk);
    });

    // Dont forget to properly "close" the operation by this command
    $flatfile->moveToTarget();
}
```

## Configuration

### Define fields

You need to define an array of fields. See Field definitions later on. This will generate a flatfile with one column. Headline will be `Column headline` and the value correspond to your model's column `column_name`
```php
    $fields = [
        'column_name' => 'Column headline'
    ];
```
    
#### Advanced field configurations: Relations, Custom labels, Cell callbacks
```php
    $fields = [
        'column_name' => 'Column headline'
        [
            'column'   => 'relation.relation_name', // Access relations
            'label'    => 'Label with special characters',
            'callback' => function ($value, $model) { // Format cell values
                return $model->currencySign.' '.number_format($value);
            }
        ]
    ]
```
    
This works as well
```php
    $fields = [
        'column_name' => [ // Column name can also still be the key of the array
            'label'    => 'Label with special characters',
            'callback' => function ($value, $model) {}
        ]
    ]
```

## Usage

### Ingredients

#### Field defintions

By doing so, you get
- The possiblity to add callbacks in your field definitions
- Decide by yourself whether you want a dedicated class or just use a method in get the field defintions from anywhere else 

Two examples how to use it:
```php
    class MyCsvExport implements ShouldQueue, FlatfileFields
    {
        public function fields()
        {
            // Your field definitions in code. You can use callbacks here
            return [...]
        }
    
        public function handle() {}
    }
```
    
or 
```php
    // Dedicated class and definition in your code base 
    class MyCsvExportFields FlatfileFields
    {
        public function fields()
        {
            // Callbacks or any other interpolations are not possible
            return config('exports.mycsv.fields');
        }
    }
```

#### A Flatfile class

Resolved from container and connected to your field definition
```php
    public function handle(FlatfileExport $flatfile) {
        $flatfile->withFields($this); // If your class directly implements the FlatfileFields-interface
    }
```
    
or

```php
    // Resolve by yourself
    $flatfile = app(FlatfileExport::class, ['fields' => $this]);
    
    // There is a fallback for lazy people as well 
    $flatfile = app(FlatfileExport::class, [$this]);
```
    
### Export

With this ingredients you can easily do a flatfile export.

**If the export file already exists, an RuntimeException is thrown. You have to take care of deleting/moving old exports by yourself.**

#### Using a storage disk

- This enables you to export to all available filesystem drivers
- Exports are generated locally/temporary first and than copied to disk (you have to trigger this explicitely)

```php
    $flatfile->to(Storage::disk('name'), '/relative/path/to/file-with-extension.csv');
    
    // Do your export ...
    
    $flatfile->moveToDisk();
```

#### Using a local filepath

- You're a free where the file should be written at
- It will not generated in a temporary file first

```php
    $flatfile->toFile('absolute/path/to/file-with-extension.csv');
```

#### Add rows to export file

- Preselect the models that will represent a single row in your flat file
- Chunk through this result set to limit resources

```php
    public function handle()
    {
        $flatfile = app(FlatfileExport::class, [$this]);
        $flatfile->toFile($this->csvFilepath);

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

#### Add headers

Add before your first `$flatfile->addRows()`

```php
    $flatfile->addHeader();
```