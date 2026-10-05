<?php

namespace App\Import\Source;

use App\Portfolio\Entity\Fill;
use App\Portfolio\Entity\Trade;

/** Une ligne lue par une source : un enregistrement converti (Trade ou Fill) ou une erreur, collectée sans arrêter la lecture. */
final readonly class ImportRow
{
    private function __construct(
        /** Position dans le fichier, ex. "Ligne 3" ou "Feuil2!7" */
        public string $position,
        public Trade|Fill|null $record,
        public ?string $error,
    ) {
    }

    public static function record(string $position, Trade|Fill $record): self
    {
        return new self($position, $record, null);
    }

    public static function error(string $position, string $message): self
    {
        return new self($position, null, $message);
    }
}
