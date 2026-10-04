<?php

namespace App\Shared;

/** Arrondis et divisions décimales en bcmath, arrondi au plus loin de zéro pour une demi-unité. */
final class Decimal
{
    public static function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($value, bccomp($value, '0', 20) < 0 ? '-'.$half : $half, $scale);

        // Évite "-0.00".
        return 0 === bccomp($rounded, '0', $scale) ? bcadd('0', '0', $scale) : $rounded;
    }

    /**
     * @throws \DivisionByZeroError si le diviseur est nul
     */
    public static function divide(string $dividend, string $divisor, int $scale): string
    {
        return self::round(bcdiv($dividend, $divisor, $scale + 6), $scale);
    }

    public static function abs(string $value): string
    {
        return ltrim($value, '-');
    }
}
