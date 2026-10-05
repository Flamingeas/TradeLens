<?php

namespace App\Trading;

final readonly class MatchResult
{
    /**
     * @param list<MatchedTrade> $trades
     * @param array<string, int> $openPositions position ouverte par "compte / symbole" (> 0 longue, < 0 courte)
     */
    public function __construct(
        public array $trades,
        public array $openPositions,
    ) {
    }
}
