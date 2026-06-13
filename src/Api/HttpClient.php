<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Api;

/**
 * Eenvoudige HTTP-client op basis van cURL die JSON ophaalt.
 */
final class HttpClient
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        private readonly int $throttleMs = 150,
    ) {
    }

    /**
     * Voert een GET-verzoek uit en geeft de gedecodeerde JSON terug.
     *
     * @param array<string, scalar> $query
     *
     * @return array<int|string, mixed>
     *
     * @throws ApiException
     */
    public function getJson(string $url, array $query = []): array
    {
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $body = $this->request($url);

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new ApiException("Ongeldige JSON-respons ontvangen van: {$url}");
        }

        return $decoded;
    }

    /**
     * @throws ApiException
     */
    private function request(string $url): string
    {
        $attempt = 0;
        $lastError = '';

        while ($attempt < $this->retries) {
            $attempt++;
            $this->throttle();

            $handle = curl_init($url);
            if ($handle === false) {
                throw new ApiException('Kan cURL niet initialiseren.');
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'User-Agent: nederland-wereldwijd-importer/1.0',
                ],
            ]);

            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if ($body === false) {
                $lastError = "Netwerkfout: {$error}";
                continue;
            }

            if ($status === 404) {
                throw new ApiException("Niet gevonden (404): {$url}");
            }

            if ($status >= 200 && $status < 300) {
                return (string) $body;
            }

            $lastError = "HTTP-status {$status} voor: {$url}";

            // 5xx-fouten: opnieuw proberen; 4xx (behalve 404): direct stoppen.
            if ($status < 500) {
                break;
            }
        }

        throw new ApiException("Verzoek mislukt na {$attempt} poging(en). {$lastError}");
    }

    private function throttle(): void
    {
        if ($this->throttleMs > 0) {
            usleep($this->throttleMs * 1000);
        }
    }
}
