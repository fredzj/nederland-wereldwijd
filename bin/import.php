<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Cli;

use NederlandWereldwijd\Api\HttpClient;
use NederlandWereldwijd\Api\NederlandWereldwijdApi;
use NederlandWereldwijd\Database\Database;
use NederlandWereldwijd\Importer\CountryImporter;
use NederlandWereldwijd\Importer\EmergencyImporter;
use NederlandWereldwijd\Importer\TravelAdviceImporter;
use NederlandWereldwijd\Repository\CountryRepository;
use NederlandWereldwijd\Repository\EmergencyRepository;
use NederlandWereldwijd\Repository\TravelAdviceRepository;
use NederlandWereldwijd\Support\Logger;
use Throwable;

$root = dirname(__DIR__);

// Autoloader: gebruik Composer indien beschikbaar, anders een eenvoudige PSR-4 fallback.
$composerAutoload = $root . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'NederlandWereldwijd\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Dit script kan alleen vanaf de commandoregel worden uitgevoerd.' . PHP_EOL);
    exit(1);
}

/** @var array{db: array<string, mixed>, api: array<string, mixed>, http: array<string, mixed>} $config */
$config = require $root . '/config/config.php';

$target = strtolower($argv[1] ?? 'all');
$allowed = ['all', 'countries', 'traveladvice', 'emergency'];

if (!in_array($target, $allowed, true)) {
    fwrite(STDERR, 'Gebruik: php bin/import.php [all|countries|traveladvice|emergency]' . PHP_EOL);
    exit(1);
}

$logger = new Logger();

try {
    /** @var array{host:string,port:int,name:string,user:string,password:string,charset:string} $dbConfig */
    $dbConfig = $config['db'];
    $pdo = (new Database($dbConfig))->pdo();

    $http = new HttpClient(
        timeout: (int) $config['http']['timeout'],
        retries: (int) $config['http']['retries'],
        throttleMs: (int) $config['http']['throttle_ms'],
    );

    $api = new NederlandWereldwijdApi(
        http: $http,
        baseUrl: (string) $config['api']['base_url'],
        source: (string) $config['api']['source'],
        lang: (string) $config['api']['lang'],
    );

    $start = microtime(true);

    if ($target === 'all' || $target === 'countries') {
        (new CountryImporter($api, new CountryRepository($pdo), $logger))->import();
    }

    if ($target === 'all' || $target === 'traveladvice') {
        (new TravelAdviceImporter($api, new TravelAdviceRepository($pdo), $logger))->import();
    }

    if ($target === 'all' || $target === 'emergency') {
        (new EmergencyImporter($api, new EmergencyRepository($pdo), $logger))->import();
    }

    $elapsed = round(microtime(true) - $start, 1);
    $logger->info("Import voltooid in {$elapsed}s.");
} catch (Throwable $e) {
    $logger->error($e->getMessage());
    exit(1);
}
