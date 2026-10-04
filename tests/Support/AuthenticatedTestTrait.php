<?php

namespace App\Tests\Support;

use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Fill;
use App\Portfolio\Entity\Trade;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Outils pour les tests qui ont besoin d'un utilisateur connecté.
 * À utiliser dans une classe qui définit $this->em (EntityManagerInterface) ; $this->client pour loginAs().
 */
trait AuthenticatedTestTrait
{
    public const TEST_PASSWORD = 'mot-de-passe-de-test';

    /** Vide toutes les tables concernées, dans l'ordre des dépendances. */
    private function resetDatabase(EntityManagerInterface $em): void
    {
        foreach ([Fill::class, Trade::class, Account::class, User::class] as $class) {
            $em->createQuery('DELETE FROM '.$class)->execute();
        }
        $em->clear();
    }

    private function createUser(EntityManagerInterface $em, string $email = 'trader@example.com', string $password = self::TEST_PASSWORD): User
    {
        $user = new User($email);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
