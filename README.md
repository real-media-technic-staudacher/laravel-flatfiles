# Laravel Flatfiles

Export and import flatfiles with Laravel like charm

## Installation

tbd

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
            'callback' => function ($value) { // Format cell values
                return '$ '.number_format($value);
            }
        ]
    ]
```
    
This works as well
```php
    $fields = [
        'column_name' => [ // Column name can also still be the key of the array
            'label'    => 'Label with special characters',
            'callback' => function ($value) {}
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
    public function handle(Flatfile $flatfile) {
        $flatfile->withFields($this); // If your class directly implements the FlatfileFields-interface
    }
```
    
or

```php
    // Resolve by yourself
    $flatfile = app(Flatfile::class, ['fields' => $this]);
    
    // There is a fallback for lazy people as well 
    $flatfile = app(Flatfile::class, [$this]);
```
    
### Export

With this ingredients you can easily do a flatfile export.

**If the export file already exists, an RuntimeException is thrown. You have to take care of deleting/moving old exports by yourself.**

#### Using a storage disk

- This enables you to export to all available filesystem drivers
- Exports are generated locally/temporary first and than copied to disk (you have to trigger this explicitely)

```php
    $flatfile->exportToFileOnDisk(Storage::disk('name'), '/relative/path/to/file-with-extension.csv');
    
    // Do your export ...
    
    $flatfile->moveToDisk();
```

#### Using a local filepath

- You're a free where the file should be written at
- It will not generated in a temporary file first

```php
    $flatfile->exportToFileAtPath('absolute/path/to/file-with-extension.csv');
```

#### Add rows to export file

- Preselect the models that will represent a single row in your flat file
- Chunk through this result set to limit resources

```php
    public function handle()
    {
        $flatfile = app(Flatfile::class, [$this]);
        $flatfile->exportToFileAtPath($this->csvFilepath);

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