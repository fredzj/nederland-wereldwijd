<?php

declare(strict_types=1);

/**
 * ===========================================================================
 *  Nederland Wereldwijd – reisadvies widget (zelfstandig in te sluiten)
 * ===========================================================================
 *
 * Plak dit hele bestand op een ander domein (of include het in een PHP-pagina).
 * Het haalt de gegevens op via de private backend-API (widget-api.php) en toont:
 *
 *   1. Alleen de landen waarvan de ISO-code in $NW_WHITELIST staat;
 *   2. Per land: kaart (map) + landnaam + datum laatste wijziging;
 *   3. Na het klikken op een land: alle reisadvies-informatie.
 *
 * Aanroep:
 *   - Standaard overzicht:           widget.php
 *   - Eén land tonen:                widget.php?nw_iso=ESP
 *
 * LET OP: deze widget maakt GEEN directe databaseverbinding meer. Vul hieronder
 * de URL van de backend-API in (het bestand public/widget-api.php op de server
 * waar de database staat) plus het gedeelde token (WIDGET_API_TOKEN). De widget
 * praat uitsluitend via HTTPS met die API; de databasegegevens blijven privé op
 * de server staan.
 */

// ---------------------------------------------------------------------------
// 1) Configuratie – pas deze waarden aan voor jouw omgeving.
// ---------------------------------------------------------------------------

/**
 * Standaard-whitelist met ISO 3166-1 alpha-3 codes (hoofdletters). Wordt
 * gebruikt wanneer er geen (geldige) whitelist via de GET-parameter wordt
 * meegegeven. Laat leeg ([]) om alleen via de URL te filteren.
 *
 * @var list<string>
 */
$NW_WHITELIST_DEFAULT = ['ESP', 'FRA', 'DEU', 'ITA', 'USA'];

/**
 * Verbinding met de private backend-API.
 *
 * - 'base_url' is de volledige URL naar public/widget-api.php op de server waar
 *   de database draait (bij voorkeur via HTTPS).
 * - 'token' is het gedeelde geheim dat overeenkomt met WIDGET_API_TOKEN in de
 *   .env van die server.
 */
$NW_API = [
    'base_url' => 'https://travlr.nl/nederland-wereldwijd/widget-api.php',
    'token'    => 'a52500719b42e15a56cab343d262889ed516cd472ed9a4bbd20024d8a71c31c1PS',
    'timeout'  => 10,
];

/** Naam van de GET-parameter waarmee een land wordt geselecteerd. */
const NW_PARAM = 'nw_iso';

/**
 * Naam van de GET-parameter met de dynamische whitelist. Geef de ISO-codes
 * komma-gescheiden mee, bijv. widget.php?nw_landen=ESP,FRA,DEU
 */
const NW_WHITELIST_PARAM = 'nw_landen';

// ---------------------------------------------------------------------------
// 2) Backend-API-client.
// ---------------------------------------------------------------------------

/**
 * Roept de private backend-API aan en geeft de gedecodeerde JSON terug.
 *
 * @param array<string, string> $params
 *
 * @return array<string, mixed>
 *
 * @throws RuntimeException bij verbindings-, HTTP- of decodeerfouten.
 */
function nw_api_get(array $cfg, string $action, array $params): array
{
    $base = (string) ($cfg['base_url'] ?? '');
    if ($base === '') {
        throw new RuntimeException('De backend-API is niet geconfigureerd (base_url ontbreekt).');
    }

    $query = http_build_query(['action' => $action] + $params);
    $url   = $base . (str_contains($base, '?') ? '&' : '?') . $query;

    $token   = (string) ($cfg['token'] ?? '');
    $timeout = (int) ($cfg['timeout'] ?? 10);

    $body   = null;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno  = curl_errno($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw new RuntimeException('Kan de backend-API niet bereiken: ' . $error);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\n"
                    . 'Authorization: Bearer ' . $token . "\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Kan de backend-API niet bereiken.');
        }
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m) === 1) {
            $status = (int) $m[1];
        }
    }

    if ($status < 200 || $status >= 300) {
        $message = 'API-fout (HTTP ' . $status . ').';
        $decoded = json_decode((string) $body, true);
        if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            $message = $decoded['error'];
        }
        throw new RuntimeException($message);
    }

    try {
        $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Ongeldig antwoord van de backend-API.');
    }

    return is_array($data) ? $data : [];
}

// ---------------------------------------------------------------------------
// 3) Hulpfuncties (veilige weergave).
// ---------------------------------------------------------------------------

function nw_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Bootstrap-kleur voor de classificatie van een reisadvies. */
function nw_classification_color(?string $classification): string
{
    return match (strtolower(trim((string) $classification))) {
        'groen', 'green'   => 'success',
        'geel', 'yellow'   => 'warning',
        'oranje', 'orange' => 'warning',
        'rood', 'red'      => 'danger',
        default            => 'secondary',
    };
}

const NW_CONTENT_TITLE_KEYS = ['category', 'paragraphtitle', 'title', 'header', 'heading', 'name', 'label'];
const NW_CONTENT_HTML_KEYS  = ['paragraph', 'html'];
const NW_CONTENT_BODY_KEYS  = ['contentblocks', 'content', 'value', 'body', 'text', 'description', 'items', 'children', 'blocks'];

/** Rendert (geneste) inhoudsblokken die als JSON zijn opgeslagen. */
function nw_render_content(mixed $node): string
{
    if ($node === null) {
        return '';
    }
    if (is_string($node)) {
        $text = trim($node);
        return $text === '' ? '' : '<p>' . nl2br(nw_e($text)) . '</p>';
    }
    if (is_scalar($node)) {
        return '<p>' . nw_e((string) $node) . '</p>';
    }
    if (!is_array($node)) {
        return '';
    }

    if (array_is_list($node)) {
        $out = '';
        foreach ($node as $child) {
            $out .= nw_render_content($child);
        }
        return $out;
    }

    $out = '';

    foreach (NW_CONTENT_TITLE_KEYS as $key) {
        if (!empty($node[$key]) && is_string($node[$key])) {
            $out .= '<h4 class="h6 fw-semibold mt-3">' . nw_e($node[$key]) . '</h4>';
            break;
        }
    }

    $renderedBody = false;

    foreach (NW_CONTENT_HTML_KEYS as $key) {
        if (!empty($node[$key]) && is_string($node[$key])) {
            $out .= nw_safe_html($node[$key]);
            $renderedBody = true;
        }
    }

    foreach (NW_CONTENT_BODY_KEYS as $key) {
        if (array_key_exists($key, $node) && $node[$key] !== null && $node[$key] !== '') {
            $out .= nw_render_content($node[$key]);
            $renderedBody = true;
        }
    }

    if (!$renderedBody) {
        foreach ($node as $key => $value) {
            if (in_array($key, NW_CONTENT_TITLE_KEYS, true)) {
                continue;
            }
            $out .= nw_render_content($value);
        }
    }

    return $out;
}

/** Saneert HTML uit de API: behoudt alleen een veilige set tags. */
function nw_safe_html(?string $html): string
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

    nw_sanitize_node($body, $allowed);

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }

    return $out;
}

/** @param list<string> $allowed */
function nw_sanitize_node(DOMNode $node, array $allowed): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            nw_sanitize_node($child, $allowed);

            $tag = strtolower($child->tagName);
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);

                $isSafeHref = $tag === 'a'
                    && $name === 'href'
                    && preg_match('#^(https?:|mailto:|tel:)#i', trim($attr->value)) === 1;

                if (!$isSafeHref) {
                    $child->removeAttribute($attr->name);
                }
            }

            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('target', '_blank');
            }
        } elseif (!($child instanceof DOMText)) {
            $node->removeChild($child);
        }
    }
}

/** Rendert alle bestanden (kaarten/afbeeldingen/links) van een reisadvies. */
function nw_render_files(mixed $files): string
{
    if (!is_array($files) || $files === []) {
        return '';
    }

    $out = '';
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }

        // De "standard" kaart (app-versie) wordt niet getoond bij één land.
        $mapType = is_string($file['mapType'] ?? null) ? strtolower($file['mapType']) : '';
        if ($mapType === 'standard') {
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
            $out .= '<figure class="nw-figure">'
                . '<img src="' . nw_e($url) . '" alt="' . nw_e((string) $title) . '" loading="lazy">';
            if ($caption !== '') {
                $out .= '<figcaption>' . nw_e($caption) . '</figcaption>';
            }
            $out .= '</figure>';
        } else {
            $out .= '<p><a href="' . nw_e($url) . '" rel="noopener noreferrer" target="_blank">'
                . nw_e((string) $title) . '</a></p>';
        }
    }

    return $out;
}

/** Geeft een datum netjes weer (of leeg). */
function nw_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? nw_e($value) : date('d-m-Y', $ts);
}

// ---------------------------------------------------------------------------
// 4) Gegevens ophalen.
// ---------------------------------------------------------------------------

/**
 * Leest de whitelist uit de GET-parameter (komma-gescheiden) en valt terug op
 * de standaardwaarde. Alleen geldige ISO 3166-1 alpha-3 codes (2-3 letters)
 * blijven behouden, zodat de URL geen ongeldige invoer kan injecteren.
 */
$NW_WHITELIST = (static function (array $default): array {
    $raw = $_GET[NW_WHITELIST_PARAM] ?? null;

    if (is_string($raw) && trim($raw) !== '') {
        $codes = preg_split('/[\s,;]+/', $raw) ?: [];
    } else {
        $codes = $default;
    }

    $codes = array_filter(
        array_map(static fn ($c): string => strtoupper(trim((string) $c)), $codes),
        static fn (string $c): bool => preg_match('/^[A-Z]{2,3}$/', $c) === 1,
    );

    return array_values(array_unique($codes));
})($NW_WHITELIST_DEFAULT);

$nwError      = null;
$nwSelected   = isset($_GET[NW_PARAM]) ? strtoupper(trim((string) $_GET[NW_PARAM])) : '';
$nwCountries  = [];
$nwCountry    = null;
$nwAdvices    = [];

// Alleen een whitelisted land mag geselecteerd worden.
if ($nwSelected !== '' && !in_array($nwSelected, $NW_WHITELIST, true)) {
    $nwSelected = '';
}

try {
    if ($NW_WHITELIST !== []) {
        $overview    = nw_api_get($NW_API, 'countries', ['whitelist' => implode(',', $NW_WHITELIST)]);
        $nwCountries = is_array($overview['countries'] ?? null) ? $overview['countries'] : [];
    }

    if ($nwSelected !== '') {
        $detail    = nw_api_get($NW_API, 'country', ['iso' => $nwSelected]);
        $nwCountry = is_array($detail['country'] ?? null) ? $detail['country'] : null;
        $nwAdvices = is_array($detail['advices'] ?? null) ? $detail['advices'] : [];
    }
} catch (Throwable $e) {
    $nwError = $e->getMessage();
}

/** Bouwt een URL naar deze pagina met de gekozen ISO-code. */
function nw_url(string $iso): string
{
    $base   = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
    $params = $_GET;
    if ($iso === '') {
        unset($params[NW_PARAM]);
    } else {
        $params[NW_PARAM] = $iso;
    }
    $query = http_build_query($params);
    return nw_e($base . ($query !== '' ? '?' . $query : ''));
}

?>
<!-- ===================== Nederland Wereldwijd widget ===================== -->
<style>
.nw-widget { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1f2933; line-height: 1.5; }
.nw-widget * { box-sizing: border-box; }
.nw-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.nw-card { display: flex; flex-direction: column; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; text-decoration: none; color: inherit; background: #fff; transition: box-shadow .15s, transform .15s; }
.nw-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,.10); transform: translateY(-2px); }
.nw-card__map { aspect-ratio: 4 / 3; background: #f1f5f9 center/cover no-repeat; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 13px; }
.nw-card__map img { width: 100%; height: 100%; object-fit: cover; display: block; }
.nw-card__body { padding: 10px 12px; }
.nw-card__name { font-weight: 600; margin: 0; }
.nw-card__date { color: #64748b; font-size: 12px; margin: 4px 0 0; }
.nw-back { display: inline-block; margin-bottom: 12px; color: #2563eb; text-decoration: none; }
.nw-back:hover { text-decoration: underline; }
.nw-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #fff; }
.nw-badge--success { background: #16a34a; } .nw-badge--warning { background: #f59e0b; }
.nw-badge--danger { background: #dc2626; } .nw-badge--secondary { background: #64748b; }
.nw-advice { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; background: #fff; }
.nw-advice h3 { margin: 0 0 8px; font-size: 18px; }
.nw-figure { margin: 12px 0; } .nw-figure img { max-width: 100%; height: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
.nw-figure figcaption { font-size: 12px; color: #64748b; margin-top: 4px; }
.nw-muted { color: #64748b; font-size: 13px; }
.nw-alert { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.nw-alert--info { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
</style>

<div class="nw-widget">
<?php if ($nwError !== null): ?>
    <div class="nw-alert">Kan de gegevens niet laden: <?= nw_e($nwError) ?></div>

<?php elseif ($nwCountry !== null): ?>
    <a class="nw-back" href="<?= nw_url('') ?>">&larr; Terug naar alle landen</a>
    <h2><?= nw_e($nwCountry['location']) ?></h2>

    <?php if ($nwAdvices === []): ?>
        <div class="nw-alert nw-alert--info">Voor dit land is geen reisadvies beschikbaar.</div>
    <?php else: ?>
        <?php foreach ($nwAdvices as $advice): ?>
            <?php
            $content = nw_render_content($advice['content'] ?? null);
            $files   = nw_render_files($advice['files'] ?? null);
            ?>
            <article class="nw-advice">
                <h3><?= nw_e($advice['title'] ?? 'Reisadvies') ?></h3>
                <?php if (!empty($advice['introduction'])): ?>
                    <div class="nw-content"><?= nw_safe_html($advice['introduction']) ?></div>
                <?php endif; ?>
                <?php if ($files !== ''): ?>
                    <div class="nw-content"><?= $files ?></div>
                <?php endif; ?>
                <?php if ($content !== ''): ?>
                    <div class="nw-content"><?= $content ?></div>
                <?php endif; ?>
                <?php if (!empty($advice['last_modified_at'])): ?>
                    <p class="nw-muted">Laatst gewijzigd: <?= nw_date($advice['last_modified_at']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

<?php elseif ($nwCountries === []): ?>
    <div class="nw-alert nw-alert--info">Geen landen gevonden voor de opgegeven whitelist.</div>

<?php else: ?>
    <div class="nw-grid">
        <?php foreach ($nwCountries as $c): ?>
            <a class="nw-card" href="<?= nw_url($c['iso_code']) ?>">
                <div class="nw-card__map">
                    <?php if ($c['map'] !== null): ?>
                        <img src="<?= nw_e($c['map']['url']) ?>" alt="<?= nw_e($c['map']['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <span>Geen kaart</span>
                    <?php endif; ?>
                </div>
                <div class="nw-card__body">
                    <p class="nw-card__name"><?= nw_e($c['location']) ?></p>
                    <?php if (!empty($c['last_update'])): ?>
                        <p class="nw-card__date">Bijgewerkt: <?= nw_date($c['last_update']) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
<!-- =================== /Nederland Wereldwijd widget ===================== -->
