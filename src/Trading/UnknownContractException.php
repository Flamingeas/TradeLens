<?php

namespace App\Trading;

class UnknownContractException extends \DomainException
{
    public static function forSymbol(string $symbol, ?string $root, array $knownRoots): self
    {
        $reason = null === $root
            ? 'racine introuvable (format attendu : racine + code de mois + année, ex. ESU5)'
            : sprintf('racine "%s" absente de la configuration', $root);

        return new self(sprintf(
            'Contrat inconnu "%s" : %s. Racines connues : %s. Ajoutez-la dans "app.contract_multipliers" (config/services.yaml).',
            $symbol,
            $reason,
            implode(', ', $knownRoots),
        ));
    }
}
