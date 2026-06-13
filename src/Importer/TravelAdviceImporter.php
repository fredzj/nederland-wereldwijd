<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Importer;

use NederlandWereldwijd\Api\ApiException;
use NederlandWereldwijd\Api\NederlandWereldwijdApi;
use NederlandWereldwijd\Repository\TravelAdviceRepository;
use NederlandWereldwijd\Support\Logger;
use NederlandWereldwijd\Support\Value;
use Throwable;

/**
 * Importeert reisadviezen: per land wordt eerst de samenvatting opgehaald en
 * vervolgens het volledige reisadvies.
 */
final class TravelAdviceImporter
{
    public function __construct(
        private readonly NederlandWereldwijdApi $api,
        private readonly TravelAdviceRepository $repository,
        private readonly Logger $logger,
    ) {
    }

    public function import(): int
    {
        $this->logger->info('Reisadviezen importeren...');
        $count = 0;

        foreach ($this->api->travelAdviceSummaries() as $summary) {
            $isoCode = Value::string($summary, 'isocode');
            $location = $isoCode !== null
                ? strtolower($isoCode)
                : Value::string($summary, 'locationkey');

            if ($location === null) {
                continue;
            }

            $classification = Value::string($summary, 'classification');

            try {
                $detail = $this->api->travelAdvice($location);
                $this->repository->upsert($detail, $classification);
                $count++;
            } catch (ApiException $e) {
                // Geen volledig detail beschikbaar: bewaar in elk geval de samenvatting.
                $this->logger->error("Detail voor '{$location}' niet opgehaald: {$e->getMessage()}");
                try {
                    $this->repository->upsert($summary, $classification);
                    $count++;
                } catch (Throwable $inner) {
                    $this->logger->error("Reisadvies '{$location}' overgeslagen: {$inner->getMessage()}");
                }
            } catch (Throwable $e) {
                $this->logger->error("Reisadvies '{$location}' overgeslagen: {$e->getMessage()}");
            }
        }

        $this->logger->info("Klaar: {$count} reisadviezen opgeslagen.");

        return $count;
    }
}
