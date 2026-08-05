<?php

use Justbetter\StatamicStructuredData\Repositories\EloquentReportRepository;
use Justbetter\StatamicStructuredData\Repositories\FileReportRepository;

return [
    'collections' => [
        // Add collection handles here that should have structured data templates
        // Example: 'pages', 'blog', 'products'
    ],
    'taxonomies' => [
        // Add taxonomy handles here that should have structured data objects
        // Example: 'categories', 'tags'
    ],
    'runway' => [
        // Add Runway resource handles here that should use structured data templates
        // Example: 'product', 'category'
        // Templates targeting a Runway resource apply to all models of that resource.
    ],
    'presets' => [
        'enabled' => true,
        'default_presets' => ['website', 'organization', 'article', 'webpage', 'localbusiness'],
        'custom_preset_paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report storage
    |--------------------------------------------------------------------------
    |
    | Reports can be stored on the filesystem (default)
    | or in the database via Eloquent.
    |
    */
    'reports' => [
        'driver' => env('STRUCTURED_DATA_REPORT_DRIVER', 'file'),

        'drivers' => [
            'file' => FileReportRepository::class,
            'eloquent' => EloquentReportRepository::class,
        ],

        'path' => base_path('content/structured-data-reports'),

        'retention_days' => (int) env('STRUCTURED_DATA_REPORT_RETENTION_DAYS', 90),

        'queue' => env('STRUCTURED_DATA_REPORT_QUEUE', 'default'),

        'permissions' => [
            'view' => 'view structured data reports',
        ],
    ],
];
