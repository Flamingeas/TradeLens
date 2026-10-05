<?php

namespace App\Import;

use App\Import\Source\TradeSource;

/**
 * Résultat de l'import d'un fichier envoyé depuis le navigateur.
 */
final readonly class UploadResult
{
    public function __construct(
        public ImportReport $report,
        public TradeSource $source,
        /** Compte qui a reçu les données, null si un compte par ligne */
        public ?string $accountName,
    ) {
    }
}
