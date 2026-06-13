<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Repository;

use NederlandWereldwijd\Support\Value;
use PDO;

/**
 * Slaat reisadviezen op in de tabel `travel_advices`.
 */
final class TravelAdviceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Voegt een reisadvies toe of werkt het bij (op basis van id).
     *
     * @param array<string, mixed> $advice
     * @param string|null          $classification Kleurcodering uit de samenvatting
     */
    public function upsert(array $advice, ?string $classification = null): void
    {
        $id = Value::string($advice, 'id');
        if ($id === null) {
            return;
        }

        $isoCode = Value::string($advice, 'isocode') ?? Value::string($advice, 'isoCode');

        $sql = <<<'SQL'
            INSERT INTO travel_advices (
                id, country_iso_code, type, canonical, data_url, title,
                introduction, location, location_key, classification,
                modification_date, modifications, content, files,
                additional_information, language, license,
                issued_at, available_at, last_modified_at
            ) VALUES (
                :id, :country_iso_code, :type, :canonical, :data_url, :title,
                :introduction, :location, :location_key, :classification,
                :modification_date, :modifications, :content, :files,
                :additional_information, :language, :license,
                :issued_at, :available_at, :last_modified_at
            )
            ON DUPLICATE KEY UPDATE
                country_iso_code       = VALUES(country_iso_code),
                type                   = VALUES(type),
                canonical              = VALUES(canonical),
                data_url               = VALUES(data_url),
                title                  = VALUES(title),
                introduction           = VALUES(introduction),
                location               = VALUES(location),
                location_key           = VALUES(location_key),
                classification         = VALUES(classification),
                modification_date      = VALUES(modification_date),
                modifications          = VALUES(modifications),
                content                = VALUES(content),
                files                  = VALUES(files),
                additional_information = VALUES(additional_information),
                language               = VALUES(language),
                license                = VALUES(license),
                issued_at              = VALUES(issued_at),
                available_at           = VALUES(available_at),
                last_modified_at       = VALUES(last_modified_at)
            SQL;

        $this->pdo->prepare($sql)->execute([
            'id'                     => $id,
            'country_iso_code'       => $isoCode !== null ? strtoupper($isoCode) : null,
            'type'                   => Value::string($advice, 'type'),
            'canonical'              => Value::string($advice, 'canonical'),
            'data_url'               => Value::string($advice, 'dataurl'),
            'title'                  => Value::string($advice, 'title'),
            'introduction'           => Value::string($advice, 'introduction'),
            'location'               => Value::string($advice, 'location'),
            'location_key'           => Value::string($advice, 'locationkey'),
            'classification'         => $classification ?? Value::string($advice, 'classification'),
            'modification_date'      => Value::string($advice, 'modificationdate'),
            'modifications'          => Value::string($advice, 'modifications'),
            'content'                => Value::json($advice, 'content'),
            'files'                  => Value::json($advice, 'files'),
            'additional_information' => Value::string($advice, 'additionalinformation'),
            'language'               => Value::string($advice, 'language'),
            'license'                => Value::string($advice, 'license'),
            'issued_at'              => Value::dateTime($advice, 'issued'),
            'available_at'           => Value::dateTime($advice, 'available'),
            'last_modified_at'       => Value::dateTime($advice, 'lastmodified'),
        ]);
    }
}
