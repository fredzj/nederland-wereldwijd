<?php

declare(strict_types=1);

/**
 * Gedeelde bootstrap voor de webpagina's:
 * - registreert de PSR-4 autoloader (Composer of fallback);
 * - leest de configuratie;
 * - maakt een PDO-verbinding met de database;
 * - biedt kleine hulpfuncties voor het veilig weergeven van gegevens.
 */

use NederlandWereldwijd\Database\Database;

/**
 * Bepaalt de hoofdmap met de niet-publieke bestanden (config, src, .env).
 *
 * - Lokaal staat `public/` ín de repository, dus de hoofdmap is één niveau hoger.
 * - In productie staan de publieke bestanden in de webmap en de niet-publieke
 *   bestanden los daarvan (bijv. ../../cron/nederland-wereldwijd).
 *
 * De juiste locatie wordt herkend doordat daar `config/config.php` aanwezig is.
 * Zet desgewenst de omgevingsvariabele NW_APP_ROOT om dit te overschrijven.
 */
$root = (static function (): string {
    $candidates = [];

    $envRoot = getenv('NW_APP_ROOT');
    if (is_string($envRoot) && $envRoot !== '') {
        $candidates[] = $envRoot;
    }

    $candidates[] = dirname(__DIR__);                         // public/ binnen de repo
    $candidates[] = __DIR__ . '/../../cron/nederland-wereldwijd'; // productie-indeling

    foreach ($candidates as $candidate) {
        if (is_file($candidate . '/config/config.php')) {
            return rtrim($candidate, '/\\');
        }
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Configuratie niet gevonden: kon config/config.php niet vinden.\n";
    echo "Zoekpaden:\n - " . implode("\n - ", $candidates) . "\n";
    echo "Stel eventueel de omgevingsvariabele NW_APP_ROOT in.\n";
    exit;
})();

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

/** @var array{db: array<string, mixed>} $config */
$config = require $root . '/config/config.php';

/**
 * Geeft een gedeelde PDO-verbinding terug, of een nette foutmelding wanneer de
 * database niet bereikbaar is.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    /** @var array{db: array{host:string,port:int,name:string,user:string,password:string,charset:string}} $config */
    global $config;

    try {
        $pdo = (new Database($config['db']))->pdo();
    } catch (Throwable $e) {
        http_response_code(503);
        page_header('Database niet bereikbaar');
        echo '<div class="alert alert-danger" role="alert">'
            . '<h2 class="h5 alert-heading">Geen verbinding met de database</h2>'
            . '<p class="mb-0">' . e($e->getMessage()) . '</p>'
            . '</div>';
        page_footer();
        exit;
    }

    return $pdo;
}

/**
 * Veilige HTML-escaping.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Geeft een Bootstrap-badgekleur terug voor de reisadvies-classificatie.
 */
function classification_color(?string $classification): string
{
    return match (strtolower(trim((string) $classification))) {
        'groen', 'green'   => 'success',
        'geel', 'yellow'   => 'warning',
        'oranje', 'orange' => 'orange',
        'rood', 'red'      => 'danger',
        default            => 'secondary',
    };
}

/**
 * Sleutels die platte tekst bevatten en als titel worden getoond.
 *
 * @var list<string>
 */
const CONTENT_TITLE_KEYS = ['category', 'paragraphtitle', 'title', 'header', 'heading', 'name', 'label'];

/**
 * Sleutels waarvan de waarde HTML-opmaak bevat (afkomstig uit de API). Deze HTML
 * wordt ge-sanitized voordat ze worden getoond.
 *
 * @var list<string>
 */
const CONTENT_HTML_KEYS = ['paragraph', 'html'];

/**
 * Sleutels met (geneste) inhoud die opnieuw via render_content() wordt verwerkt.
 *
 * @var list<string>
 */
const CONTENT_BODY_KEYS = ['contentblocks', 'content', 'value', 'body', 'text', 'description', 'items', 'children', 'blocks'];

/**
 * Rendert (geneste) inhoudsblokken die als JSON zijn opgeslagen.
 *
 * Platte tekst wordt ge-escaped; HTML-velden (zoals `paragraph`) worden via
 * safe_html() gesaneerd, zodat alleen een veilige set tags overblijft.
 *
 * @param mixed $node
 */
function render_content(mixed $node): string
{
    if ($node === null) {
        return '';
    }

    if (is_string($node)) {
        $text = trim($node);
        return $text === '' ? '' : '<p>' . nl2br(e($text)) . '</p>';
    }

    if (is_scalar($node)) {
        return '<p>' . e((string) $node) . '</p>';
    }

    if (!is_array($node)) {
        return '';
    }

    // Lijst met blokken: render elk blok.
    if (array_is_list($node)) {
        $out = '';
        foreach ($node as $child) {
            $out .= render_content($child);
        }
        return $out;
    }

    $out = '';

    // Titel.
    foreach (CONTENT_TITLE_KEYS as $key) {
        if (!empty($node[$key]) && is_string($node[$key])) {
            // Een `category` is een sectiekop; overige titels zijn subkoppen.
            $class = $key === 'category'
                ? 'content-category h4 fw-semibold mt-4 pt-2 border-top'
                : 'content-title h5 fw-semibold mt-4';
            $out .= '<h3 class="' . $class . '">' . e($node[$key]) . '</h3>';
            break;
        }
    }

    $renderedBody = false;

    // HTML-velden: saneren en tonen.
    foreach (CONTENT_HTML_KEYS as $key) {
        if (!empty($node[$key]) && is_string($node[$key])) {
            $out .= safe_html($node[$key]);
            $renderedBody = true;
        }
    }

    // Geneste inhoud opnieuw verwerken.
    foreach (CONTENT_BODY_KEYS as $key) {
        if (array_key_exists($key, $node) && $node[$key] !== null && $node[$key] !== '') {
            $out .= render_content($node[$key]);
            $renderedBody = true;
        }
    }

    if (!$renderedBody) {
        foreach ($node as $key => $value) {
            if (in_array($key, CONTENT_TITLE_KEYS, true)) {
                continue;
            }
            $out .= render_content($value);
        }
    }

    return $out;
}

/**
 * Saneert HTML uit de API: behoudt alleen een veilige set tags en attributen.
 * Voorkomt XSS door scripts, event-handlers en onveilige links te verwijderen.
 */
function safe_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    $allowed = [
        'p', 'br', 'strong', 'em', 'b', 'i', 'u', 'small',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'h3', 'h4', 'h5', 'h6', 'blockquote', 'a', 'span', 'div',
    ];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8"?><body>' . $html . '</body>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();

    if ($loaded === false) {
        return '';
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body === null) {
        return '';
    }

    sanitize_node($body, $allowed);

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }

    return $out;
}

/**
 * Saneert een DOM-knooppunt en zijn kinderen op basis van een lijst toegestane
 * tags. Niet-toegestane elementen worden "uitgepakt" (hun tekst blijft behouden).
 *
 * @param list<string> $allowed
 */
function sanitize_node(DOMNode $node, array $allowed): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            // Eerst de subboom saneren (bottom-up).
            sanitize_node($child, $allowed);

            $tag = strtolower($child->tagName);
            if (!in_array($tag, $allowed, true)) {
                // Uitpakken: kinderen vóór het element plaatsen, element verwijderen.
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Attributen opschonen: alleen veilige href op anchors en een
            // beperkte set class-namen behouden.
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);

                $isSafeHref = $tag === 'a'
                    && $name === 'href'
                    && preg_match('#^(https?:|mailto:|tel:)#i', trim($attr->value)) === 1;

                $isSafeClass = $name === 'class'
                    && sanitize_class($attr->value) !== '';

                if (!$isSafeHref && !$isSafeClass) {
                    $child->removeAttribute($attr->name);
                }
            }

            if ($child->hasAttribute('class')) {
                $safeClass = sanitize_class($child->getAttribute('class'));
                if ($safeClass === '') {
                    $child->removeAttribute('class');
                } else {
                    $child->setAttribute('class', $safeClass);
                }
            }

            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('target', '_blank');
            }
        } elseif (!($child instanceof DOMText)) {
            // Commentaar, processing-instructies, e.d. verwijderen.
            $node->removeChild($child);
        }
    }
}

/**
 * Houdt alleen een beperkte set veilige class-namen over (voor callouts).
 */
function sanitize_class(string $class): string
{
    $allowed = ['notification', 'attention', 'warning', 'info'];
    $kept = array_values(array_intersect(
        preg_split('/\s+/', trim($class)) ?: [],
        $allowed,
    ));

    return implode(' ', $kept);
}

/**
 * Rendert de `files`-array van een reisadvies (kaarten/afbeeldingen). Alleen
 * veilige http(s)-URL's worden getoond.
 *
 * @param mixed $files
 */
function render_files(mixed $files): string
{
    if (!is_array($files) || $files === []) {
        return '';
    }

    $out = '';
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }

        $url = isset($file['fileurl']) && is_string($file['fileurl']) ? trim($file['fileurl']) : '';
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            continue;
        }

        $mime    = is_string($file['mimetype'] ?? null) ? strtolower($file['mimetype']) : '';
        $title   = is_string($file['filetitle'] ?? null) ? $file['filetitle'] : ($file['filename'] ?? 'Bestand');
        $caption = is_string($file['fileDescription'] ?? null) ? $file['fileDescription'] : '';

        if (str_starts_with($mime, 'image/')) {
            $out .= '<figure class="figure d-block">'
                . '<img src="' . e($url) . '" class="figure-img img-fluid rounded border" '
                . 'alt="' . e((string) $title) . '" loading="lazy">';
            if ($caption !== '') {
                $out .= '<figcaption class="figure-caption">' . e($caption) . '</figcaption>';
            }
            $out .= '</figure>';
        } else {
            $out .= '<p class="mb-1">'
                . '<a href="' . e($url) . '" rel="noopener noreferrer" target="_blank">'
                . e((string) $title) . '</a></p>';
        }
    }

    return $out;
}

/**
 * Decodeert een JSON-tekstkolom naar een PHP-waarde.
 *
 * @return mixed
 */
function decode_json(?string $json): mixed
{
    if ($json === null || $json === '') {
        return null;
    }

    try {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return $json;
    }
}

/**
 * Toont de bovenkant van de pagina (Bootstrap 5.3 + navigatie).
 */
function page_header(string $title, string $active = ''): void
{
    $nav = [
        'index.php'     => 'Home',
        'emergency.php' => 'Nood-informatie',
        'country.php'   => 'Landen & reisadvies',
    ];
    ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> – Nederland Wereldwijd</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body { background-color: #f8f9fa; }
        .text-bg-orange { background-color: #fd7e14 !important; color: #fff; }
        .content-blocks { line-height: 1.6; }
        .content-blocks .content-title:first-child { margin-top: 0 !important; }
        .content-blocks .content-category:first-child { margin-top: 0 !important; border-top: 0 !important; padding-top: 0 !important; }
        .content-blocks h3 { font-size: 1.05rem; font-weight: 600; margin-top: 1rem; }
        .content-blocks h4 { font-size: .95rem; font-weight: 600; margin-top: .75rem; }
        .content-blocks .content-title { font-size: 1.25rem; }
        .content-blocks .content-category { font-size: 1.4rem; }
        .content-blocks figure { margin: 1rem 0; }
        .content-blocks .notification { padding: .75rem 1rem; border-radius: .375rem; background: #e7f1ff; margin: 1rem 0; }
        .content-blocks .notification.attention { background: #fff3cd; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Nederland Wereldwijd</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
                aria-controls="nav" aria-expanded="false" aria-label="Navigatie">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto">
                <?php foreach ($nav as $href => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active === $href ? ' active' : '' ?>"
                           <?= $active === $href ? 'aria-current="page" ' : '' ?>href="<?= e($href) ?>"><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">
    <?php
}

/**
 * Toont de onderkant van de pagina.
 */
function page_footer(): void
{
    ?>
</main>
<footer class="container text-muted small py-4 border-top mt-4">
    Gegevens: open data van
    <a href="https://www.nederlandwereldwijd.nl/open-data" class="link-secondary" rel="noopener" target="_blank">Nederland Wereldwijd</a>.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
    <?php
}
