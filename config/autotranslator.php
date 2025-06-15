<?php

return [
    'driver' => env('AUTOTRANSLATOR_DRIVER', 'mymemory'),
    'source' => env('AUTOTRANSLATOR_SOURCE', 'en'),
    'target' => env('AUTOTRANSLATOR_TARGET', 'ar'),
    'output_format' => env('AUTOTRANSLATOR_OUTPUT_FORMAT', 'json'),
    'directories' => [
        resource_path('views'),
        app_path(),
        base_path('Modules'),
    ],
    'patterns' => [
        '/@lang\(\'(.*?)\'\)/',
        '/@lang\("(.*?)"\)/',
        '/__\(\'(.*?)\'\)/',
        '/__\("(.*?)"\)/',
    ],
    'mymemory' => [
        'endpoint' => env('MYMEMORY_ENDPOINT', 'https://api.mymemory.translated.net/get'),
        'api_key' => env('MYMEMORY_API_KEY'),
    ],
    'deepl' => [
        'endpoint' => env('DEEPL_ENDPOINT', 'https://api-free.deepl.com/v2/translate'),
        'api_key' => env('DEEPL_API_KEY'),
    ],
    'fallback_driver' => env('AUTOTRANSLATOR_FALLBACK_DRIVER', 'deepl'),
    'lang_directories' => [
        lang_path(), // Default directory for Laravel's translations
        
        // You can add additional paths here (absolute or full path), for example:
        // 'D:\your-project\custom\lang'
    ],
    
];