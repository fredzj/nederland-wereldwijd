<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Repository;

use NederlandWereldwijd\Support\Value;
use PDO;

/**
 * Slaat landen op in de tabel `countries`.
 */
final class CountryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Voegt een land toe of werkt het bij (op basis van iso_code).
     *
     * @param array<string, mixed> $country
     */
    public function upsert(array $country): void
    {
        $isoCode = Value::string($country, 'isoCode') ?? Value::string($country, 'isocode');
        if ($isoCode === null) {
            return;
        }

        $sql = <<<'SQL'
            INSERT INTO countries (
                iso_code, type, location, location_key,
                data_url, travel_advice_url, nl_representation_url
            ) VALUES (
                :iso_code, :type, :location, :location_key,
                :data_url, :travel_advice_url, :nl_representation_url
            )
            ON DUPLICATE KEY UPDATE
                type                  = VALUES(type),
                location              = VALUES(location),
                location_key          = VALUES(location_key),
                data_url              = VALUES(data_url),
                travel_advice_url     = VALUES(travel_advice_url),
                nl_representation_url = VALUES(nl_representation_url)
            SQL;

        $this->pdo->prepare($sql)->execute([
            'iso_code'             => $isoCode,
            'type'                 => Value::string($country, 'type'),
            'location'             => Value::string($country, 'location') ?? $isoCode,
            'location_key'         => Value::string($country, 'locationKey')
                ?? Value::string($country, 'locationkey')
                ?? strtolower($isoCode),
            'data_url'             => Value::string($country, 'dataUrl') ?? Value::string($country, 'dataurl'),
            'travel_advice_url'    => Value::string($country, 'travelAdvice'),
            'nl_representation_url' => Value::string($country, 'nlRepresentation'),
        ]);
    }
}
