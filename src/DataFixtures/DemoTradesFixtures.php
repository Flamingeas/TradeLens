<?php

namespace App\DataFixtures;

use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Trade;
use App\Portfolio\Enum\TradeDirection;
use App\Security\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données fictives de démonstration : 1 utilisateur (demo@tradelens.test / demo-tradelens), 1 compte,
 * 30 trades sur environ 6 semaines. Groupe : demo, développement uniquement.
 */
class DemoTradesFixtures extends Fixture implements FixtureGroupInterface
{
    public const ACCOUNT_NAME = 'DEMO (données fictives)';
    public const USER_EMAIL = 'demo@tradelens.test';
    public const USER_PASSWORD = 'demo-tradelens';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    /** Points gagnés (+) ou perdus (−), dans l'ordre chronologique ; 0 = équilibre avant commissions. */
    private const POINTS = [
        4.5, -3.0, 2.25, -6.0, 0.0, 7.75, -2.5, 3.0, -4.25, 1.5,
        -1.75, 5.0, 0.0, -3.5, 9.25, -2.0, 2.75, -5.5, 4.0, 1.25,
        -3.25, 6.5, -1.0, 0.0, 3.75, -4.5, 8.0, -2.75, 2.0, 5.25,
    ];

    private const MULTIPLIERS = ['ES' => '50', 'NQ' => '20'];
    private const COMMISSION_PER_CONTRACT = '2.80';

    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User(self::USER_EMAIL);
        $user->setPassword($this->hasher->hashPassword($user, self::USER_PASSWORD));
        $manager->persist($user);

        $account = new Account(self::ACCOUNT_NAME, $user);
        $manager->persist($account);

        $utc = new \DateTimeZone('UTC');
        $day = new \DateTimeImmutable('today', $utc);
        $trades = [];

        // Un trade par jour ouvré, en remontant depuis aujourd'hui.
        for ($i = count(self::POINTS) - 1; $i >= 0; --$i) {
            while ($day->format('N') >= 6) {
                $day = $day->modify('-1 day');
            }
            $trades[$i] = $this->createTrade($account, $i, $day);
            $day = $day->modify('-1 day');
        }

        ksort($trades);
        foreach ($trades as $trade) {
            $manager->persist($trade);
        }
        $manager->flush();
    }

    private function createTrade(Account $account, int $index, \DateTimeImmutable $day): Trade
    {
        $root = 0 === $index % 3 ? 'NQ' : 'ES';
        $direction = 0 === $index % 2 ? TradeDirection::Long : TradeDirection::Short;
        $quantity = 1 + $index % 3;
        $points = number_format(self::POINTS[$index], 2, '.', '');

        $entry = 'ES' === $root
            ? bcadd('5800.00', (string) ($index * 3), 4)
            : bcadd('20500.00', (string) ($index * 11), 4);
        // Prix de sortie selon le sens et les points.
        $exit = TradeDirection::Long === $direction ? bcadd($entry, $points, 4) : bcsub($entry, $points, 4);

        $gross = bcmul(bcmul($points, (string) $quantity, 4), self::MULTIPLIERS[$root], 2);
        $commission = bcmul(self::COMMISSION_PER_CONTRACT, (string) $quantity, 2);

        $closedAt = $day->setTime(14 + $index % 6, 5 * ($index % 12));
        $openedAt = $closedAt->modify(sprintf('-%d minutes', 4 + ($index * 7) % 55));

        return new Trade(
            $account,
            $root.'Z5',
            $direction,
            $quantity,
            $openedAt,
            $closedAt,
            $entry,
            $exit,
            $gross,
            $commission,
        );
    }
}
