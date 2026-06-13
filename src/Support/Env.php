<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Support;

/**
 * Minimale .env-loader zonder externe afhankelijkheden.
 */
final class Env
{
    private static bool $loaded = false;

    /**
     * Laadt een .env-bestand in de omgevingsvariabelen (eenmalig).
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = self::cleanValue($value);

            if ($name === '' || array_key_exists($name, $_ENV)) {
                continue;
            }

            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    /**
     * Haalt een omgevingsvariabele op met optionele standaardwaarde.
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function getInt(string $name, int $default): int
    {
        $value = self::get($name);

        return $value === null ? $default : (int) $value;
    }

    private static function cleanValue(string $value): string
    {
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
