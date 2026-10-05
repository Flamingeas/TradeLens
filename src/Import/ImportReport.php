<?php

namespace App\Import;

/** Bilan d'un import ; en simulation (dryRun), les compteurs disent ce qui serait enregistré. */
final readonly class ImportReport
{
    /**
     * @param list<string>       $accountsCreated comptes qui n'existaient pas encore
     * @param array<string, int> $openPositions   positions restées ouvertes après appariement (ODS)
     */
    public function __construct(
        public int $rowsRead,
        public int $fillsInserted = 0,
        public int $fillsSkipped = 0,
        public int $tradesInserted = 0,
        public int $tradesSkipped = 0,
        public array $accountsCreated = [],
        public array $openPositions = [],
        public bool $dryRun = false,
    ) {
    }
}
