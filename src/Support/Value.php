<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Support;

/**
 * Hulpfuncties voor het normaliseren van waarden uit de API-respons.
 */
final class Value
{
    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Zet een ISO 8601 / date-time string om naar het MySQL DATETIME-formaat.
     *
     * @param array<string, mixed> $data
     */
    public static function dateTime(array $data, string $key): ?string
    {
        $value = self::string($data, $key);

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Codeert een (geneste) waarde naar JSON voor opslag in een tekstkolom.
     *
     * @param array<string, mixed> $data
     */
    public static function json(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $encoded = json_encode($data[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }
}
