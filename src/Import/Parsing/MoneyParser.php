<?php

namespace App\Import\Parsing;

/** Convertit les montants des fichiers de courtier en décimaux (chaînes) : "$3,700.00", "$-651.54", "@ $6,247.75". */
class MoneyParser
{
    // Espaces, dont l'espace insécable des cellules ODS.
    private const NOISE = '/[\s\x{00A0}]+/u';
    private const AMOUNT = '/^(?<sign>-?)(?<int>\d{1,3}(?:,\d{3})+|\d+)(?<dec>\.\d+)?$/';

    /**
     * Montant signé ; un signe mal formé (ex. "$--4.20") est refusé.
     *
     * @throws \InvalidArgumentException si la valeur n'est pas un montant reconnu
     */
    public function parse(string $value): string
    {
        return $this->normalize($value, tolerateRepeatedMinus: false);
    }

    /**
     * Commission en valeur absolue (positive dans le CSV, négative dans l'ODS) ; le signe est toléré.
     *
     * @throws \InvalidArgumentException si la valeur n'est pas un montant reconnu
     */
    public function parseCommission(string $value): string
    {
        return ltrim($this->normalize($value, tolerateRepeatedMinus: true), '-');
    }

    private function normalize(string $value, bool $tolerateRepeatedMinus): string
    {
        $clean = preg_replace(self::NOISE, '', $value) ?? '';
        $clean = ltrim($clean, '@');

        if (!str_starts_with($clean, '$')) {
            throw new \InvalidArgumentException(sprintf('Montant invalide "%s" : le symbole $ est attendu.', $value));
        }
        $clean = substr($clean, 1);

        if ($tolerateRepeatedMinus) {
            $clean = preg_replace('/^-+/', '-', $clean);
        }

        if (1 !== preg_match(self::AMOUNT, $clean, $m)) {
            throw new \InvalidArgumentException(sprintf('Montant invalide "%s".', $value));
        }

        $number = str_replace(',', '', $m['int']).($m['dec'] ?? '');

        // Évite "-0.00".
        return '-' === $m['sign'] && 0 !== bccomp($number, '0', 10) ? '-'.$number : $number;
    }
}
