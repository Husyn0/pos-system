<?php

return [
    'name' => env('APP_NAME', 'Coffee POS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'central_domain' => env('CENTRAL_DOMAIN', 'posapp.io'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
