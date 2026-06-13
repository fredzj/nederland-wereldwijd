<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Api;

use Generator;

/**
 * Toegang tot de open data API van Nederland Wereldwijd.
 *
 * @see https://opendata.nederlandwereldwijd.nl/v2/sources/nederlandwereldwijd/openapi.yaml
 */
final class NederlandWereldwijdApi
{
    private readonly string $infotypesUrl;

    public function __construct(
        private readonly HttpClient $http,
        string $baseUrl,
        string $source = 'nederlandwereldwijd',
        private readonly string $lang = 'nl',
    ) {
        $this->infotypesUrl = sprintf('%s/sources/%s/infotypes', rtrim($baseUrl, '/'), $source);
    }

    /**
     * Loopt door alle landen heen (gepagineerd).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function countries(int $pageSize = 100): Generator
    {
        yield from $this->paginate(
            "{$this->infotypesUrl}/countries",
            ['output' => 'json', 'sort' => 'asc'],
            $pageSize,
        );
    }

    /**
     * Loopt door alle reisadvies-samenvattingen heen (gepagineerd).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function travelAdviceSummaries(int $pageSize = 200): Generator
    {
        yield from $this->paginate(
            "{$this->infotypesUrl}/traveladvice",
            ['output' => 'json'],
            $pageSize,
        );
    }

    /**
     * Haalt het volledige reisadvies voor één locatie op.
     *
     * @return array<string, mixed>
     */
    public function travelAdvice(string $location): array
    {
        return $this->http->getJson(
            "{$this->infotypesUrl}/countries/{$location}/traveladvice",
            ['output' => 'json'],
        );
    }

    /**
     * Loopt door alle nood-informatie-items heen (gepagineerd).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function emergencySummaries(int $pageSize = 100): Generator
    {
        yield from $this->paginate(
            "{$this->infotypesUrl}/hulp-bij-nood",
            ['output' => 'json'],
            $pageSize,
        );
    }

    /**
     * Haalt één nood-informatie-item op via id.
     *
     * @return array<string, mixed>
     */
    public function emergency(string $id): array
    {
        return $this->http->getJson(
            "{$this->infotypesUrl}/hulp-bij-nood/{$id}",
            ['output' => 'json'],
        );
    }

    /**
     * Generieke paginering met rows/offset totdat er geen resultaten meer zijn.
     *
     * @param array<string, scalar> $query
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function paginate(string $url, array $query, int $pageSize): Generator
    {
        $offset = 0;
        $query['lang'] = $this->lang;

        do {
            $page = $this->http->getJson($url, [
                ...$query,
                'rows'   => $pageSize,
                'offset' => $offset,
            ]);

            $count = 0;
            foreach ($page as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    yield $item;
                    $count++;
                }
            }

            $offset += $pageSize;
        } while ($count === $pageSize);
    }
}
