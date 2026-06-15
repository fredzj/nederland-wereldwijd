<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Backend;

use PDO;

/**
 * Lees-service achter de private widget-API.
 *
 * Bevat uitsluitend de (read-only) query's die de insluitbare widget nodig
 * heeft. De service vormt de gegevens om naar nette PHP-structuren, zodat de
 * widget zelf geen databasekennis of JSON-decodering meer hoeft te bevatten.
 */
final class WidgetService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Geeft het landoverzicht voor de opgegeven whitelist terug.
     *
     * Elk land bevat de naam, de datum van de laatste wijziging en (indien
     * beschikbaar) één kaart voor de tegel.
     *
     * @param list<string> $whitelist ISO 3166-1 alpha-3 codes (hoofdletters)
     *
     * @return list<array{iso_code:string,location:string,last_update:?string,map:array{url:string,title:string}|null}>
     */
    public function overview(array $whitelist): array
    {
        if ($whitelist === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($whitelist), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT c.iso_code, c.location,
                    COALESCE(MAX(t.last_modified_at), c.updated_at) AS last_update
             FROM countries c
             LEFT JOIN travel_advice t ON t.country_iso_code = c.iso_code
             WHERE c.iso_code IN ($placeholders)
             GROUP BY c.iso_code, c.location, c.updated_at
             ORDER BY c.location"
        );
        $stmt->execute(array_values($whitelist));

        $countries = [];

        foreach ($stmt->fetchAll() as $row) {
            $countries[] = [
                'iso_code'    => (string) $row['iso_code'],
                'location'    => (string) $row['location'],
                'last_update' => $row['last_update'] !== null ? (string) $row['last_update'] : null,
                'map'         => $this->mapForCountry((string) $row['iso_code']),
            ];
        }

        return $countries;
    }

    /**
     * Geeft één land met de bijbehorende reisadviezen terug.
     *
     * De velden `content` en `files` worden al gedecodeerd teruggegeven, zodat
     * de widget ze direct kan renderen.
     *
     * @return array{country: array<string, mixed>|null, advices: list<array<string, mixed>>}
     */
    public function country(string $iso): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM countries WHERE iso_code = ?');
        $stmt->execute([$iso]);
        $country = $stmt->fetch() ?: null;

        if ($country === null) {
            return ['country' => null, 'advices' => []];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, title, introduction, classification, content, files,
                    location, last_modified_at
             FROM travel_advice
             WHERE country_iso_code = ?
             ORDER BY title'
        );
        $stmt->execute([$iso]);

        $advices = [];

        foreach ($stmt->fetchAll() as $advice) {
            $advice['content'] = $this->decodeJson($advice['content'] ?? null);
            $advice['files']   = $this->decodeJson($advice['files'] ?? null);
            $advices[]         = $advice;
        }

        return ['country' => $country, 'advices' => $advices];
    }

    /**
     * Bepaalt één kaart (uit de `files` van de reisadviezen) voor een land.
     *
     * @return array{url:string,title:string}|null
     */
    private function mapForCountry(string $iso): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT files FROM travel_advice
             WHERE country_iso_code = ? AND files IS NOT NULL AND files <> ""
             ORDER BY last_modified_at DESC'
        );
        $stmt->execute([$iso]);

        foreach ($stmt->fetchAll() as $row) {
            $map = $this->firstMap($this->decodeJson($row['files'] ?? null));
            if ($map !== null) {
                return $map;
            }
        }

        return null;
    }

    /**
     * Haalt de voorkeurs-kaart uit een `files`-lijst. De "standard" kaart
     * (app-versie) heeft de voorkeur; anders de eerste geschikte afbeelding.
     *
     * @return array{url:string,title:string}|null
     */
    private function firstMap(mixed $files): ?array
    {
        if (!is_array($files)) {
            return null;
        }

        $fallback = null;

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $url = isset($file['fileurl']) && is_string($file['fileurl']) ? trim($file['fileurl']) : '';
            if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }

            $mime = is_string($file['mimetype'] ?? null) ? strtolower($file['mimetype']) : '';
            if (!str_starts_with($mime, 'image/')) {
                continue;
            }

            $title   = is_string($file['filetitle'] ?? null) ? $file['filetitle'] : 'Kaart';
            $mapType = is_string($file['mapType'] ?? null) ? strtolower($file['mapType']) : '';

            if ($mapType === 'standard') {
                return ['url' => $url, 'title' => $title];
            }

            $fallback ??= ['url' => $url, 'title' => $title];
        }

        return $fallback;
    }

    private function decodeJson(mixed $json): mixed
    {
        if (!is_string($json) || $json === '') {
            return null;
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
