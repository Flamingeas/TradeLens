<?php

namespace App\Import;

use App\Portfolio\Entity\Trade;

/**
 * Écarte les trades déjà en base pour qu'importer deux fois le même fichier ne change rien.
 * Comparaison sur compte, symbole, sens, quantité, dates et prix, en comptant les exemplaires identiques.
 */
class TradeDeduplicator
{
    /**
     * @param iterable<Trade> $candidates
     * @param iterable<Trade> $existing   trades déjà en base
     *
     * @return array{new: list<Trade>, duplicates: int}
     */
    public function unseen(iterable $candidates, iterable $existing): array
    {
        $available = [];
        foreach ($existing as $trade) {
            $key = $this->key($trade);
            $available[$key] = ($available[$key] ?? 0) + 1;
        }

        $new = [];
        $duplicates = 0;
        foreach ($candidates as $trade) {
            $key = $this->key($trade);
            if (($available[$key] ?? 0) > 0) {
                --$available[$key];
                ++$duplicates;
                continue;
            }
            $new[] = $trade;
        }

        return ['new' => $new, 'duplicates' => $duplicates];
    }

    private function key(Trade $trade): string
    {
        $utc = new \DateTimeZone('UTC');

        return implode('|', [
            $trade->getAccount()->getName(),
            $trade->getSymbol(),
            $trade->getDirection()->value,
            $trade->getQuantity(),
            $trade->getOpenedAt()->setTimezone($utc)->format('Y-m-d H:i:s'),
            $trade->getClosedAt()->setTimezone($utc)->format('Y-m-d H:i:s'),
            bcadd($trade->getEntryPrice(), '0', 4),
            bcadd($trade->getExitPrice(), '0', 4),
        ]);
    }
}
