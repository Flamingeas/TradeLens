<?php

namespace App\Trading;

use App\Portfolio\Entity\Fill;
use App\Portfolio\Entity\Trade;
use App\Portfolio\Enum\FillSide;
use App\Portfolio\Enum\TradeDirection;
use App\Shared\Decimal;

/**
 * Transforme des exécutions en trades par appariement FIFO : un trade par exécution de clôture, avec P&L brut
 * et commissions réparties au prorata. Ce qui reste ouvert est signalé dans MatchResult::$openPositions.
 */
class FifoTradeMatcher
{
    public function __construct(private readonly ContractMultiplierProvider $multipliers)
    {
    }

    /**
     * Apparie les exécutions et renvoie les trades de clôture.
     *
     * @param iterable<Fill> $fills
     *
     * @throws UnknownContractException si un symbole n'a pas de multiplicateur
     */
    public function match(iterable $fills): MatchResult
    {
        $groups = [];
        foreach ($fills as $fill) {
            $groups[$this->groupKey($fill)][] = $fill;
        }

        $matched = [];
        $openPositions = [];

        foreach ($groups as $group) {
            usort($group, static fn (Fill $a, Fill $b): int => $a->getExecutedAt() <=> $b->getExecutedAt()
                ?: strnatcmp($a->getExecutionId(), $b->getExecutionId()));

            /** @var list<OpenLot> $lots */
            $lots = [];

            foreach ($group as $fill) {
                $trade = $this->apply($fill, $lots);
                if (null !== $trade) {
                    $matched[] = $trade;
                }
            }

            $net = array_sum(array_map(static fn (OpenLot $lot): int => $lot->direction * $lot->remaining, $lots));
            if (0 !== $net) {
                $openPositions[sprintf('%s / %s', $group[0]->getAccount()->getName(), $group[0]->getSymbol())] = $net;
            }
        }

        usort($matched, static fn (MatchedTrade $a, MatchedTrade $b): int => $a->trade->getClosedAt() <=> $b->trade->getClosedAt()
            ?: strnatcmp($a->closingFill->getExecutionId(), $b->closingFill->getExecutionId()));

        return new MatchResult($matched, $openPositions);
    }

    /**
     * Applique une exécution aux lots ouverts et renvoie le trade de clôture éventuel.
     *
     * @param list<OpenLot> $lots
     */
    private function apply(Fill $fill, array &$lots): ?MatchedTrade
    {
        $side = FillSide::Buy === $fill->getSide() ? 1 : -1;
        $commission = new CommissionPool($fill->getQuantity(), $fill->getCommission());
        $remaining = $fill->getQuantity();
        $multiplier = null;

        $matchedQuantity = 0;
        $grossPoints = '0.0000';
        $entryWeighted = '0.0000';
        $tradeCommission = '0.00';
        $openedAt = null;

        while ($remaining > 0 && [] !== $lots && $lots[0]->direction === -$side) {
            $multiplier ??= $this->multipliers->multiplierFor($fill->getSymbol());
            $lot = $lots[0];
            $slice = min($remaining, $lot->remaining);

            $move = 1 === $lot->direction
                ? bcsub($fill->getPrice(), $lot->fill->getPrice(), 4)
                : bcsub($lot->fill->getPrice(), $fill->getPrice(), 4);
            $grossPoints = bcadd($grossPoints, bcmul(bcmul($move, (string) $slice, 4), $multiplier, 4), 4);
            $entryWeighted = bcadd($entryWeighted, bcmul($lot->fill->getPrice(), (string) $slice, 4), 4);
            $tradeCommission = bcadd($tradeCommission, bcadd($commission->take($slice), $lot->commission->take($slice), 2), 2);
            $openedAt ??= $lot->fill->getExecutedAt();
            $matchedQuantity += $slice;

            $lot->remaining -= $slice;
            $remaining -= $slice;
            if (0 === $lot->remaining) {
                array_shift($lots);
            }
        }

        if ($remaining > 0) {
            // Le reste ouvre ou renforce une position.
            $lots[] = new OpenLot($fill, $side, $remaining, $commission);
        }

        if (0 === $matchedQuantity) {
            return null;
        }

        $trade = new Trade(
            $fill->getAccount(),
            $fill->getSymbol(),
            1 === $side ? TradeDirection::Short : TradeDirection::Long,
            $matchedQuantity,
            $openedAt,
            $fill->getExecutedAt(),
            Decimal::divide($entryWeighted, (string) $matchedQuantity, 4),
            bcadd($fill->getPrice(), '0', 4),
            Decimal::round($grossPoints, 2),
            $tradeCommission,
        );

        return new MatchedTrade($trade, $fill);
    }

    private function groupKey(Fill $fill): string
    {
        $account = $fill->getAccount();

        return sprintf('%s|%s', $account->getId() ?? 'obj'.spl_object_id($account), $fill->getSymbol());
    }
}
