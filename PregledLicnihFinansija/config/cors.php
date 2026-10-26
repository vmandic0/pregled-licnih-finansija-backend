<?php

$frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');
$allowedOrigins = $frontendUrl === '' ? [] : [$frontendUrl];

if (env('APP_ENV', 'production') === 'local') {
    $allowedOrigins[] = 'http://localhost:5173';
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique($allowedOrigins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
