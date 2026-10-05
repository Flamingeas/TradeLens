<?php

namespace App\Import\Source\Mffu;

use App\Import\Parsing\BrokerDateTimeParser;
use App\Import\Parsing\FieldParsing;
use App\Import\Parsing\MoneyParser;
use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Trade;
use App\Portfolio\Enum\TradeDirection;

/** Convertit une ligne du CSV MFFU (un trade complet) en Trade ; "Net Profit" est recalculé par Trade. */
class MffuTradeMapper
{
    use FieldParsing;

    public const ACCOUNT_NAME_COLUMN = 'Account Name';

    /** Colonnes requises pour reconnaître un CSV MFFU. */
    public const REQUIRED_COLUMNS = [
        'Symbol', 'Bias', 'Volume', 'Open Time', 'Avg. Entry', 'Close Time', 'Avg. Close', 'Gross Profit', 'Commissions',
    ];

    public function __construct(
        private readonly MoneyParser $money,
        private readonly BrokerDateTimeParser $dates,
    ) {
    }

    /**
     * @param array<string, string> $row
     *
     * @throws \InvalidArgumentException si une colonne est absente ou invalide
     */
    public function accountName(array $row): string
    {
        $name = $this->column($row, self::ACCOUNT_NAME_COLUMN);
        if ('' === $name) {
            throw new \InvalidArgumentException(sprintf('Colonne "%s" : valeur vide.', self::ACCOUNT_NAME_COLUMN));
        }

        return $name;
    }

    /**
     * @param array<string, string> $row
     *
     * @throws \InvalidArgumentException si une colonne est absente ou invalide
     */
    public function map(array $row, Account $account): Trade
    {
        return new Trade(
            $account,
            strtoupper($this->column($row, 'Symbol')),
            $this->field('Bias', fn () => $this->parseDirection($this->column($row, 'Bias'))),
            $this->field('Volume', fn () => $this->parseQuantity($this->column($row, 'Volume'))),
            $this->field('Open Time', fn () => $this->dates->parse($this->column($row, 'Open Time'))),
            $this->field('Close Time', fn () => $this->dates->parse($this->column($row, 'Close Time'))),
            $this->field('Avg. Entry', fn () => $this->money->parse($this->column($row, 'Avg. Entry'))),
            $this->field('Avg. Close', fn () => $this->money->parse($this->column($row, 'Avg. Close'))),
            $this->field('Gross Profit', fn () => $this->money->parse($this->column($row, 'Gross Profit'))),
            $this->field('Commissions', fn () => $this->money->parseCommission($this->column($row, 'Commissions'))),
        );
    }

    /**
     * @param array<string, string> $row
     */
    private function column(array $row, string $name): string
    {
        if (!array_key_exists($name, $row)) {
            throw new \InvalidArgumentException(sprintf('Colonne "%s" absente du fichier.', $name));
        }

        return $row[$name];
    }

    private function parseDirection(string $value): TradeDirection
    {
        return match (strtoupper($value)) {
            'LONG' => TradeDirection::Long,
            'SHORT' => TradeDirection::Short,
            default => throw new \InvalidArgumentException(sprintf('sens inconnu "%s" (LONG ou SHORT attendu).', $value)),
        };
    }
}
