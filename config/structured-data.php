<?php

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
];
