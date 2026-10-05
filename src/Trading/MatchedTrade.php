<?php

namespace App\Trading;

use App\Portfolio\Entity\Fill;
use App\Portfolio\Entity\Trade;

/** Un trade issu de l'appariement, avec l'exécution de clôture dont il provient. */
final readonly class MatchedTrade
{
    public function __construct(
        public Trade $trade,
        public Fill $closingFill,
    ) {
    }
}
