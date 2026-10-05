<?php

namespace App\Trading;

/** Donne la valeur d'un point (en dollars) d'un contrat à terme à partir de son symbole. */
class ContractMultiplierProvider
{
    // Symbole : racine, code de mois (FGHJKMNQUVXZ), année sur 1 ou 2 chiffres.
    private const SYMBOL_PATTERN = '/^(?<root>.+)[FGHJKMNQUVXZ]\d{1,2}$/';

    /**
     * @param array<string, int|string> $contractMultipliers multiplicateur par racine de contrat
     */
    public function __construct(private readonly array $contractMultipliers)
    {
    }

    /**
     * @throws UnknownContractException si le symbole n'a pas de multiplicateur configuré
     */
    public function multiplierFor(string $symbol): string
    {
        $normalized = strtoupper(trim($symbol));
        $root = $this->extractRoot($normalized);

        if (null === $root || !isset($this->contractMultipliers[$root])) {
            throw UnknownContractException::forSymbol($symbol, $root, array_keys($this->contractMultipliers));
        }
        return (string) $this->contractMultipliers[$root];
    }

    private function extractRoot(string $symbol): ?string
    {
        return 1 === preg_match(self::SYMBOL_PATTERN, $symbol, $matches) ? $matches['root'] : null;
    }
}
