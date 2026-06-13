<?php

declare(strict_types=1);

namespace NederlandWereldwijd\Support;

/**
 * Eenvoudige logger die naar de console (STDOUT/STDERR) schrijft.
 */
final class Logger
{
    public function info(string $message): void
    {
        fwrite(STDOUT, '[' . date('H:i:s') . '] ' . $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, '[' . date('H:i:s') . '] FOUT: ' . $message . PHP_EOL);
    }
}
