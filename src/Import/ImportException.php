<?php

namespace App\Import;

/** Import refusé : au moins une ligne est invalide, rien n'est écrit en base. */
class ImportException extends \RuntimeException
{
    /**
     * @param list<string> $errors une entrée par ligne fautive, avec sa position
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(sprintf('Import annulé : %d erreur(s). Aucune donnée n\'a été enregistrée.', count($errors)));
    }
}
