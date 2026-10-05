<?php

namespace App\Trading;

use App\Portfolio\Entity\Fill;

/**
 * Contrats d'une exécution d'ouverture encore en position.
 *
 * @internal
 */
final class OpenLot
{
    public function __construct(
        public readonly Fill $fill,
        /** +1 longue, -1 courte */
        public readonly int $direction,
        public int $remaining,
        public readonly CommissionPool $commission,
    ) {
    }
}
