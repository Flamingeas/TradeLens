<?php

namespace App\Trading;

/**
 * Jour de trading d'un instant : la séance des futures CME change à 17 h (Chicago),
 * un trade clôturé à partir de 17 h appartient donc au jour suivant.
 */
class TradingDay
{
    private const TIMEZONE = 'America/Chicago';
    private const ROLLOVER_HOUR = 17;

    /** Renvoie le jour de trading au format Y-m-d. */
    public function of(\DateTimeImmutable $instant): string
    { $local = $instant->setTimezone(new \DateTimeZone(self::TIMEZONE));
        // Calcul en heure locale : insensible aux changements d'heure.
        if ((int) $local->format('G') >= self::ROLLOVER_HOUR) {
            $local = $local->modify('+1 day');
        }
        return $local->format('Y-m-d');
    }
}
