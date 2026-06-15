<?php

declare(strict_types=1);

/**
 * ===========================================================================
 *  Private backend-API voor de insluitbare widget
 * ===========================================================================
 *
 * Deze dunne API staat vóór de database en geeft uitsluitend JSON terug. De
 * insluitbare widget (embed/nederland-wereldwijd-widget.php) gebruikt deze
 * API in plaats van een directe databaseverbinding, zodat:
 *
 *   - de databasegegevens niet langer op een extern domein hoeven te staan;
 *   - de toegang met een gedeeld geheim (bearer-token) wordt afgeschermd;
 *   - alleen de twee benodigde, alleen-lezen acties worden blootgesteld.
 *
 * Endpoints (GET):
 *   ?action=countries&whitelist=ESP,FRA,DEU   → landoverzicht
 *   ?action=country&iso=ESP                    → land + reisadviezen
 *
 * Authenticatie:
 *   Stuur het token mee via de Authorization-header ("Bearer <token>") of als
 *   GET-parameter ?token=<token>. Het token wordt ingesteld via WIDGET_API_TOKEN.
 */

use NederlandWereldwijd\Backend\WidgetService;
use NederlandWereldwijd\Database\Database;

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/**
 * Stuurt een JSON-respons en beëindigt het script.
 *
 * @param array<string, mixed> $payload
 */
$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

// ---------------------------------------------------------------------------
// Bootstrap: autoloader + configuratie laden.
// ---------------------------------------------------------------------------
$root = (static function (): string {
    $candidates = [];

    $envRoot = getenv('NW_APP_ROOT');
    if (is_string($envRoot) && $envRoot !== '') {
        $candidates[] = $envRoot;
    }

    $candidates[] = dirname(__DIR__);                            // public/ binnen de repo
    $candidates[] = __DIR__ . '/../../cron/nederland-wereldwijd'; // productie-indeling

    foreach ($candidates as $candidate) {
        if (is_file($candidate . '/config/config.php')) {
            return rtrim($candidate, '/\\');
        }
    }

    return '';
})();

if ($root === '') {
    $respond(500, ['error' => 'Configuratie niet gevonden.']);
}

$composerAutoload = $root . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'NederlandWereldwijd\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

/** @var array{db: array<string, mixed>, widget_api: array{token: string}} $config */
$config = require $root . '/config/config.php';

// ---------------------------------------------------------------------------
// Authenticatie (private API): gedeeld bearer-token vereist.
// ---------------------------------------------------------------------------
$expectedToken = (string) ($config['widget_api']['token'] ?? '');

if ($expectedToken === '') {
    $respond(503, ['error' => 'De API is niet geconfigureerd (ontbrekend token).']);
}

$providedToken = (static function (): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) === 1) {
        return trim($m[1]);
    }

    $param = $_GET['token'] ?? '';

    return is_string($param) ? trim($param) : '';
})();

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    $respond(401, ['error' => 'Ongeldig of ontbrekend token.']);
}

// ---------------------------------------------------------------------------
// Verzoek afhandelen.
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $respond(405, ['error' => 'Alleen GET wordt ondersteund.']);
}

try {
    $service = new WidgetService((new Database($config['db']))->pdo());
} catch (Throwable $e) {
    $respond(503, ['error' => 'Database niet bereikbaar.']);
}

$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

switch ($action) {
    case 'countries':
        $raw   = is_string($_GET['whitelist'] ?? null) ? $_GET['whitelist'] : '';
        $codes = array_filter(
            array_map(
                static fn (string $c): string => strtoupper(trim($c)),
                preg_split('/[\s,;]+/', $raw) ?: [],
            ),
            static fn (string $c): bool => preg_match('/^[A-Z]{2,3}$/', $c) === 1,
        );
        $whitelist = array_values(array_unique($codes));

        $respond(200, ['countries' => $service->overview($whitelist)]);

        // no break (respond exits)

    case 'country':
        $iso = is_string($_GET['iso'] ?? null) ? strtoupper(trim($_GET['iso'])) : '';
        if (preg_match('/^[A-Z]{2,3}$/', $iso) !== 1) {
            $respond(400, ['error' => 'Ongeldige ISO-code.']);
        }

        $respond(200, $service->country($iso));

        // no break (respond exits)

    default:
        $respond(404, ['error' => 'Onbekende actie.']);
}
