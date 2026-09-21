<?php

return [
    'cache-prefix' => '',

    'enabled' => env('MODEL_CACHE_ENABLED', true),

    'use-database-keying' => env('MODEL_CACHE_USE_DATABASE_KEYING', true),

    'store' => env('MODEL_CACHE_STORE'),

    'fallback-to-database' => env('MODEL_CACHE_FALLBACK_TO_DB', false),

    'ttl' => env('MODEL_CACHE_TTL'),
];
