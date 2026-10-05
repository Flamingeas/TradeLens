<?php

namespace App\Import\Source\Topstep;

use App\Import\Parsing\BrokerDateTimeParser;
use App\Import\Parsing\FieldParsing;
use App\Import\Parsing\MoneyParser;
use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Fill;
use App\Portfolio\Enum\FillSide;

/**
 * Convertit une ligne de l'ODS (une exécution) en Fill.
 * Colonnes : 0 date et heure, 1 jour de bourse (ignoré), 2 identifiant, 3 sens, 4 quantité, 5 symbole,
 * 6 prix, 7 commission, 8 P&L brut.
 */
class TopstepFillMapper
{
    use FieldParsing;

    private const COLUMNS = [
        0 => 'date et heure',
        2 => 'identifiant d\'exécution',
        3 => 'sens',
        4 => 'quantité',
        5 => 'symbole',
        6 => 'prix',
        7 => 'commission',
        8 => 'P&L',
    ];

    public function __construct(
        private readonly MoneyParser $money,
        private readonly BrokerDateTimeParser $dates,
    ) {
    }

    /**
     * @param list<string> $row
     *
     * @throws \InvalidArgumentException si la ligne est incomplète ou invalide
     */
    public function map(array $row, Account $account): Fill
    {
        foreach (self::COLUMNS as $index => $label) {
            if (!isset($row[$index]) || '' === $row[$index]) {
                throw new \InvalidArgumentException(sprintf('Colonne %d (%s) : valeur absente.', $index + 1, $label));
            }
        }

        return new Fill(
            $account,
            $row[2],
            $this->field('date et heure', fn () => $this->dates->parse($row[0])),
            $this->field('sens', fn () => $this->parseSide($row[3])),
            $this->field('quantité', fn () => $this->parseQuantity($row[4])),
            strtoupper($row[5]),
            $this->field('prix', fn () => $this->money->parse($row[6])),
            $this->field('commission', fn () => $this->money->parseCommission($row[7])),
            $this->field('P&L', fn () => $this->money->parse($row[8])),
        );
    }

    private function parseSide(string $value): FillSide
    {
        return match (strtolower($value)) {
            'buy' => FillSide::Buy,
            'sell' => FillSide::Sell,
            default => throw new \InvalidArgumentException(sprintf('sens inconnu "%s" (Buy ou Sell attendu).', $value)),
        };
    }
}
