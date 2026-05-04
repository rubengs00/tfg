<?php

return [
    'paths' => ['api/*', 'up'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique([
        env('FRONTEND_URL', 'http://localhost:4200'),
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'http://localhost:4300',
        'http://127.0.0.1:4300',
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
