<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Maakt en bewaart een PDO-verbinding met de MariaDB-database.
 */
final class Database
{
    private ?PDO $pdo = null;

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string,charset:string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['name'],
            $this->config['charset'],
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ],
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Kan geen verbinding maken met de database: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        return $this->pdo;
    }
}
