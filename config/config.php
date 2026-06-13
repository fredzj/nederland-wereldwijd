<?php

declare(strict_types=1);

use NederlandWereldwijd\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

return [
    'db' => [
        'host'     => Env::get('DB_HOST', '127.0.0.1'),
        'port'     => Env::getInt('DB_PORT', 3306),
        'name'     => Env::get('DB_NAME', 'nederland_wereldwijd'),
        'user'     => Env::get('DB_USER', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset'  => Env::get('DB_CHARSET', 'utf8mb4'),
    ],
    'api' => [
        'base_url' => rtrim((string) Env::get('API_BASE_URL', 'https://opendata.nederlandwereldwijd.nl/v2'), '/'),
        'source'   => Env::get('API_SOURCE', 'nederlandwereldwijd'),
        'lang'     => Env::get('API_LANG', 'nl'),
    ],
    'http' => [
        'timeout'     => Env::getInt('HTTP_TIMEOUT', 30),
        'retries'     => Env::getInt('HTTP_RETRIES', 3),
        'throttle_ms' => Env::getInt('HTTP_THROTTLE_MS', 150),
    ],
];
