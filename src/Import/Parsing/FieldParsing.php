<?php

namespace App\Import\Parsing;

/** Outils communs aux convertisseurs de lignes : préfixe les erreurs du nom de la colonne. */
trait FieldParsing
{
    /**
     * @template T
     *
     * @param callable(): T $parse
     *
     * @return T
     */
    private function field(string $column, callable $parse): mixed
    {
        try {
            return $parse();
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Colonne "%s" : %s', $column, $e->getMessage()), 0, $e);
        }
    }

    /** Quantité entière : accepte "6" et "3.0", refuse "2.5" et les négatives. */
    private function parseQuantity(string $value): int
    {
        if (1 !== preg_match('/^(?<n>\d+)(?:\.0+)?$/', $value, $m) || 0 === (int) $m['n']) {
            throw new \InvalidArgumentException(sprintf('quantité invalide "%s" (entier positif attendu).', $value));
        }

        return (int) $m['n'];
    }
}
