<?php

namespace App\Tests\Shared;

use App\Shared\Twig\MoneyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyExtensionTest extends TestCase
{
    private const NBSP = "\u{00A0}";
    private const THIN = "\u{202F}";

    #[DataProvider('amounts')]
    public function testMoney(?string $input, string $expected): void
    {
        $this->assertSame($expected, (new MoneyExtension())->money($input));
    }

    public static function amounts(): iterable
    {
        yield 'gain' => ['394.40', '+394,40'.self::NBSP.'$'];
        yield 'perte' => ['-152.80', '−152,80'.self::NBSP.'$'];
        yield 'milliers' => ['1234567.891', '+1'.self::THIN.'234'.self::THIN.'567,89'.self::NBSP.'$'];
        yield 'perte avec milliers' => ['-1500', '−1'.self::THIN.'500,00'.self::NBSP.'$'];
        yield 'zéro sans signe' => ['0.00', '0,00'.self::NBSP.'$'];
        yield 'zéro négatif' => ['-0.004', '0,00'.self::NBSP.'$'];
        yield 'arrondi de la demi-unité' => ['2.345', '+2,35'.self::NBSP.'$'];
        yield 'arrondi négatif' => ['-2.345', '−2,35'.self::NBSP.'$'];
        yield 'non calculable' => [null, '—'];
    }

    #[DataProvider('classes')]
    public function testMoneyClass(?string $input, string $expected): void
    {
        $this->assertSame($expected, (new MoneyExtension())->moneyClass($input));
    }

    public static function classes(): iterable
    {
        yield ['12.00', 'is-positive'];
        yield ['-0.01', 'is-negative'];
        yield ['0.00', 'is-neutral'];
        yield ['0.004', 'is-neutral'];
        yield [null, 'is-neutral'];
    }

    #[DataProvider('compactAmounts')]
    public function testCompact(?string $input, string $expected): void
    {
        $this->assertSame($expected, (new MoneyExtension())->compact($input));
    }

    public static function compactAmounts(): iterable
    {
        yield 'petit gain' => ['241.60', '+242'];
        yield 'petite perte' => ['-408.40', '−408'];
        yield 'quelques centimes' => ['0.40', '+0'];
        yield 'zéro' => ['0.00', '0'];
        yield 'zéro négatif' => ['-0.004', '0'];
        yield 'juste sous le millier' => ['999.40', '+999'];
        yield 'arrondi au millier' => ['999.60', '+1k'];
        yield 'un millier exact' => ['1000.00', '+1k'];
        yield 'milliers décimaux' => ['1234.56', '+1,2k'];
        yield 'perte en milliers' => ['-12345.00', '−12,3k'];
        yield 'milliers ronds' => ['-12000.00', '−12k'];
        yield 'non calculable' => [null, '—'];
    }

    public function testPointsRatioAndPercent(): void
    {
        $ext = new MoneyExtension();

        $this->assertSame('+4,25', $ext->points('4.25'));
        $this->assertSame('−3,00', $ext->points('-3'));
        $this->assertSame('—', $ext->points(null));
        $this->assertSame('0,78', $ext->ratio('0.78'));
        $this->assertSame('—', $ext->ratio(null));
        $this->assertSame('62,5'.self::NBSP.'%', $ext->percent('62.5'));
        $this->assertSame('100,0'.self::NBSP.'%', $ext->percent('100.0'));
    }
}
