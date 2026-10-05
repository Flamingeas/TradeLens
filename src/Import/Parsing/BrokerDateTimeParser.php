<?php

namespace App\Import\Parsing;

/**
 * Convertit les dates des fichiers de courtier en UTC.
 * Formats : CSV "May 06 2025, 11:18:02 AM EDT", ODS "2025-07-01 14:10:10 CT" (espace insécable avant le fuseau).
 */
class BrokerDateTimeParser
{
    private const FORMATS = [
        'M d Y, h:i:s A',
        'Y-m-d H:i:s',
    ];

    // Abréviations à décalage fixe.
    private const FIXED_OFFSETS = [
        'EDT' => '-04:00',
        'EST' => '-05:00',
        'CDT' => '-05:00',
        'CST' => '-06:00',
        'UTC' => '+00:00',
    ];

    // "CT" suit le calendrier de Chicago.
    private const NAMED_ZONES = [
        'CT' => 'America/Chicago',
        'ET' => 'America/New_York',
    ];

    /**
     * @throws \InvalidArgumentException si la date ou le fuseau n'est pas reconnu
     */
    public function parse(string $value): \DateTimeImmutable
    {
        $clean = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '');

        if (1 !== preg_match('/^(?<local>.+) (?<tz>[A-Z]{2,3})$/', $clean, $m)) {
            throw new \InvalidArgumentException(sprintf('Date invalide "%s" : fuseau horaire attendu en fin de valeur.', $value));
        }

        $zone = $this->zoneFor($m['tz'], $value);

        foreach (self::FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $m['local'], $zone);
            $errors = \DateTimeImmutable::getLastErrors();

            // Rejette les dates corrigées en silence (30 février, 25:00...).
            if (false !== $date && (false === $errors || 0 === $errors['warning_count'] + $errors['error_count'])) {
                return $date->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        throw new \InvalidArgumentException(sprintf('Date invalide "%s" : format non reconnu.', $value));
    }

    private function zoneFor(string $abbreviation, string $original): \DateTimeZone
    {
        if (isset(self::FIXED_OFFSETS[$abbreviation])) {
            return new \DateTimeZone(self::FIXED_OFFSETS[$abbreviation]);
        }

        if (isset(self::NAMED_ZONES[$abbreviation])) {
            return new \DateTimeZone(self::NAMED_ZONES[$abbreviation]);
        }

        throw new \InvalidArgumentException(sprintf(
            'Date invalide "%s" : fuseau "%s" inconnu (connus : %s).',
            $original,
            $abbreviation,
            implode(', ', array_keys(self::FIXED_OFFSETS + self::NAMED_ZONES)),
        ));
    }
}
