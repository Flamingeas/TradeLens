<?php

namespace App\Trading;

use App\Shared\Decimal;

/**
 * Commission d'une exécution, répartie au prorata des contrats consommés ; le dernier retrait prend le reste.
 *
 * @internal
 */
final class CommissionPool
{
    private int $remainingQuantity;
    private string $remainingCommission;

    public function __construct(int $quantity, string $commission)
    {
        $this->remainingQuantity = $quantity;
        $this->remainingCommission = bcadd($commission, '0', 2);
    }

    /**
     * @throws \LogicException si on retire plus de contrats qu'il n'en reste
     */
    public function take(int $quantity): string
    {
        if ($quantity <= 0 || $quantity > $this->remainingQuantity) {
            throw new \LogicException(sprintf('Retrait de %d contrats impossible : %d restants.', $quantity, $this->remainingQuantity));
        }

        $share = $quantity === $this->remainingQuantity
            ? $this->remainingCommission
            : Decimal::divide(bcmul($this->remainingCommission, (string) $quantity, 6), (string) $this->remainingQuantity, 2);

        $this->remainingQuantity -= $quantity;
        $this->remainingCommission = bcsub($this->remainingCommission, $share, 2);

        return $share;
    }
}
