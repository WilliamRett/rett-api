<?php

return [

    'paths' => [
        'api/*',
        'docs',
        'api/docs',
        'api/oauth2-callback',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    // libera 8000 E 8080
    'allowed_origins' => [
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // pode deixar false
    'supports_credentials' => false,
];
