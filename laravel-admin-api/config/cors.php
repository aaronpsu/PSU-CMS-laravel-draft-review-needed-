<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => ['http://localhost:8000', 'http://127.0.0.1:8000'],
    'allowed_origins_patterns' => ['^https?://(localhost|127\\.0\\.0\\.1)(:\\d+)?$'],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
