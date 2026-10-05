<?php

namespace App\Import\Source\Mffu;

use App\Import\AccountResolver;
use App\Import\Reader\CsvFileReader;
use App\Import\Source\ImportRow;
use App\Import\Source\TradeSource;

/**
 * MFFU : CSV de trades complets (un aller-retour par ligne, avec compte, prix moyens et P&L).
 */
class MffuCsvSource implements TradeSource
{
    public function __construct(
        private readonly CsvFileReader $reader,
        private readonly MffuTradeMapper $mapper,
    ) {
    }

    public function key(): string
    {
        return 'mffu';
    }

    public function label(): string
    {
        return 'MFFU, CSV de trades complets';
    }

    public function providesAccounts(): bool
    {
        return true;
    }

    public function supports(string $path): bool
    {
        if (!str_ends_with(strtolower($path), '.csv') || !is_file($path)) {
            return false;
        }

        $handle = @fopen($path, 'r');
        if (false === $handle) {
            return false;
        }
        $header = fgets($handle);
        fclose($handle);

        if (false === $header) {
            return false;
        }

        $columns = array_map('trim', str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header, escape: ''));

        return [] === array_diff(MffuTradeMapper::REQUIRED_COLUMNS, $columns);
    }

    public function read(string $path, AccountResolver $accounts): \Generator
    {
        foreach ($this->reader->read($path) as $line => $row) {
            $position = sprintf('Ligne %d', $line);

            try {
                // Avec un compte unique imposé, la colonne "Account Name" n'est pas lue.
                $name = $accounts->isFixed() ? null : $this->mapper->accountName($row);

                yield ImportRow::record($position, $this->mapper->map($row, $accounts->resolve($name)));
            } catch (\InvalidArgumentException $e) {
                yield ImportRow::error($position, $e->getMessage());
            }
        }
    }
}
