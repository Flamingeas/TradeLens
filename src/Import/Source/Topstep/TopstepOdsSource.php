<?php

namespace App\Import\Source\Topstep;

use App\Import\AccountResolver;
use App\Import\Reader\OdsFileReader;
use App\Import\Source\ImportRow;
use App\Import\Source\TradeSource;

/**
 * TopStep : ODS d'exécutions (une ligne par achat ou vente, sans compte) ;
 * les trades sont déduits par appariement FIFO.
 */
class TopstepOdsSource implements TradeSource
{
    public function __construct(
        private readonly OdsFileReader $reader,
        private readonly TopstepFillMapper $mapper,
    ) {
    }

    public function key(): string
    {
        return 'topstep';
    }

    public function label(): string
    {
        return 'TopStep, ODS d\'exécutions (toutes les feuilles)';
    }

    public function providesAccounts(): bool
    {
        return false;
    }

    public function supports(string $path): bool
    {
        return str_ends_with(strtolower($path), '.ods') && is_file($path);
    }

    public function read(string $path, AccountResolver $accounts): \Generator
    {
        foreach ($this->reader->read($path) as $position => $row) {
            try {
                yield ImportRow::record($position, $this->mapper->map($row, $accounts->resolve()));
            } catch (\InvalidArgumentException $e) {
                yield ImportRow::error($position, $e->getMessage());
            }
        }
    }
}
