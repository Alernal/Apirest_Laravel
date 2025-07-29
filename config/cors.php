<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods'   => ['*'],

    'allowed_origins'   => [
        'https://nurae.com.co',
        'http://localhost:5173',
    ],

    // Puedes dejar esto vacío o eliminarlo si no usas regex
    'allowed_origins_patterns' => [],

    'allowed_headers'   => ['*'],
    'exposed_headers'   => [],
    'max_age'           => 3600,
    'supports_credentials' => false,
];
