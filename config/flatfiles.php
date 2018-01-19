<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Flatfiles
    |--------------------------------------------------------------------------
    |
    | Control the basic handling with flatfiles. You of course can overwrite
    | the defaults in your concrete flatfile implementation
    |
    */

    'default' => env('FLATFILE_DRIVER', 'csv'),

    'drivers' => [
        'csv' => [
            'charset'   => 'UTF-8',
            'delimiter' => ';',
            'enclosure' => '"',
            'bom'       => true,
        ],
    ],
];
