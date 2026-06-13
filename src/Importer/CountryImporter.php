<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Importer;

use NederlandWereldwijd\Api\NederlandWereldwijdApi;
use NederlandWereldwijd\Repository\CountryRepository;
use NederlandWereldwijd\Support\Logger;
use Throwable;

/**
 * Importeert de lijst met landen.
 */
final class CountryImporter
{
    public function __construct(
        private readonly NederlandWereldwijdApi $api,
        private readonly CountryRepository $repository,
        private readonly Logger $logger,
    ) {
    }

    public function import(): int
    {
        $this->logger->info('Landen importeren...');
        $count = 0;

        foreach ($this->api->countries() as $country) {
            try {
                $this->repository->upsert($country);
                $count++;
            } catch (Throwable $e) {
                $this->logger->error("Land overgeslagen: {$e->getMessage()}");
            }
        }

        $this->logger->info("Klaar: {$count} landen opgeslagen.");

        return $count;
    }
}
