<?php

namespace App\Shared\Twig;

use App\Shared\Decimal;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/** Filtres d'affichage des décimaux au format français : "—" pour null, signe toujours écrit (+ / −). */
class MoneyExtension extends AbstractExtension
{
    private const EMPTY = '—';
    private const MINUS = '−';
    private const THOUSANDS = "\u{202F}";
    private const NBSP = "\u{00A0}";

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
            new TwigFilter('money_class', $this->moneyClass(...)),
            new TwigFilter('compact', $this->compact(...)),
            new TwigFilter('points', $this->points(...)),
            new TwigFilter('ratio', $this->ratio(...)),
            new TwigFilter('percent', $this->percent(...)),
        ];
    }

    /** Montant signé avec devise : "+1 234,50 $". */
    public function money(?string $amount): string
    {
        return null === $amount ? self::EMPTY : $this->format($amount, 2, true).self::NBSP.'$';
    }

    /** Classe CSS selon le signe : is-positive, is-negative ou is-neutral. */
    public function moneyClass(?string $amount): string
    {
        if (null === $amount) {
            return 'is-neutral';
        }

        return match (bccomp(Decimal::round($amount, 2), '0', 2)) {
            1 => 'is-positive',
            -1 => 'is-negative',
            default => 'is-neutral',
        };
    }

    /** Montant court sans symbole pour les petites cases : "+241", "+1,2k". */
    public function compact(?string $amount): string
    {
        if (null === $amount) {
            return self::EMPTY;
        }

        $rounded = Decimal::round($amount, 2);
        if (0 === bccomp($rounded, '0', 2)) {
            return '0';
        }

        $absolute = Decimal::abs($rounded);
        $units = Decimal::round($absolute, 0);

        if (bccomp($units, '1000', 0) < 0) {
            $body = $units;
        } else {
            $thousands = Decimal::divide($absolute, '1000', 1);
            $body = str_replace('.', ',', str_ends_with($thousands, '.0') ? substr($thousands, 0, -2) : $thousands).'k';
        }

        return (str_starts_with($rounded, '-') ? self::MINUS : '+').$body;
    }

    /** Points signés : "+4,25". */
    public function points(?string $value): string
    {
        return null === $value ? self::EMPTY : $this->format($value, 2, true);
    }

    /** Nombre à 2 décimales, sans signe explicite : "1,85". */
    public function ratio(?string $value): string
    {
        return null === $value ? self::EMPTY : $this->format($value, 2, false);
    }

    /** Pourcentage à 1 décimale : "62,5 %". */
    public function percent(?string $value): string
    {
        return null === $value ? self::EMPTY : $this->format($value, 1, false).self::NBSP.'%';
    }

    private function format(string $value, int $scale, bool $explicitPlus): string
    {
        $rounded = Decimal::round($value, $scale);
        $isZero = 0 === bccomp($rounded, '0', $scale);
        $digits = ltrim($rounded, '-');

        [$integer, $decimals] = array_pad(explode('.', $digits, 2), 2, '');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', self::THOUSANDS, $integer);

        $sign = match (true) {
            $isZero => '',
            str_starts_with($rounded, '-') => self::MINUS,
            $explicitPlus => '+',
            default => '',
        };

        return $sign.$integer.('' === $decimals ? '' : ','.$decimals);
    }
}
