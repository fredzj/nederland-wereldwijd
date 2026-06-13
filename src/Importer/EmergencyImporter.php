<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Importer;

use NederlandWereldwijd\Api\ApiException;
use NederlandWereldwijd\Api\NederlandWereldwijdApi;
use NederlandWereldwijd\Repository\EmergencyRepository;
use NederlandWereldwijd\Support\Logger;
use NederlandWereldwijd\Support\Value;
use Throwable;

/**
 * Importeert nood-informatie (hulp bij nood / noodhulp).
 */
final class EmergencyImporter
{
    public function __construct(
        private readonly NederlandWereldwijdApi $api,
        private readonly EmergencyRepository $repository,
        private readonly Logger $logger,
    ) {
    }

    public function import(): int
    {
        $this->logger->info('Nood-informatie importeren...');
        $count = 0;

        foreach ($this->api->emergencySummaries() as $summary) {
            $id = Value::string($summary, 'id');
            if ($id === null) {
                continue;
            }

            try {
                $detail = $this->api->emergency($id);
                $this->repository->upsert($detail);
                $count++;
            } catch (ApiException $e) {
                // Geen volledig detail beschikbaar: bewaar in elk geval de samenvatting.
                $this->logger->error("Detail voor '{$id}' niet opgehaald: {$e->getMessage()}");
                try {
                    $this->repository->upsert($summary);
                    $count++;
                } catch (Throwable $inner) {
                    $this->logger->error("Nood-informatie '{$id}' overgeslagen: {$inner->getMessage()}");
                }
            } catch (Throwable $e) {
                $this->logger->error("Nood-informatie '{$id}' overgeslagen: {$e->getMessage()}");
            }
        }

        $this->logger->info("Klaar: {$count} nood-informatie-items opgeslagen.");

        return $count;
    }
}
