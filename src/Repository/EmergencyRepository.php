<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Repository;

use NederlandWereldwijd\Support\Value;
use PDO;

/**
 * Slaat nood-informatie op in de tabel `emergency_infos`.
 */
final class EmergencyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Voegt een nood-informatie-item toe of werkt het bij (op basis van id).
     *
     * @param array<string, mixed> $emergency
     */
    public function upsert(array $emergency): void
    {
        $id = Value::string($emergency, 'id');
        if ($id === null) {
            return;
        }

        $sql = <<<'SQL'
            INSERT INTO emergency_infos (
                id, type, canonical, data_url, title, introduction,
                content, parent, order_no, locations, languages, license,
                issued_at, available_at, last_modified_at
            ) VALUES (
                :id, :type, :canonical, :data_url, :title, :introduction,
                :content, :parent, :order_no, :locations, :languages, :license,
                :issued_at, :available_at, :last_modified_at
            )
            ON DUPLICATE KEY UPDATE
                type             = VALUES(type),
                canonical        = VALUES(canonical),
                data_url         = VALUES(data_url),
                title            = VALUES(title),
                introduction     = VALUES(introduction),
                content          = VALUES(content),
                parent           = VALUES(parent),
                order_no         = VALUES(order_no),
                locations        = VALUES(locations),
                languages        = VALUES(languages),
                license          = VALUES(license),
                issued_at        = VALUES(issued_at),
                available_at     = VALUES(available_at),
                last_modified_at = VALUES(last_modified_at)
            SQL;

        $this->pdo->prepare($sql)->execute([
            'id'               => $id,
            'type'             => Value::string($emergency, 'type'),
            'canonical'        => Value::string($emergency, 'canonical'),
            'data_url'         => Value::string($emergency, 'dataurl'),
            'title'            => Value::string($emergency, 'title'),
            'introduction'     => Value::string($emergency, 'introduction'),
            'content'          => Value::json($emergency, 'content'),
            'parent'           => Value::string($emergency, 'parent'),
            'order_no'         => Value::string($emergency, 'order'),
            'locations'        => Value::json($emergency, 'locations'),
            'languages'        => Value::json($emergency, 'languages'),
            'license'          => Value::string($emergency, 'license'),
            'issued_at'        => Value::dateTime($emergency, 'issued'),
            'available_at'     => Value::dateTime($emergency, 'available'),
            'last_modified_at' => Value::dateTime($emergency, 'lastmodified'),
        ]);
    }
}
