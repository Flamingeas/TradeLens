<?php

namespace App\Security;

use App\Portfolio\Repository\AccountRepository;
use App\Security\Entity\User;
use App\Security\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un utilisateur : hache le mot de passe et l'enregistre. Le premier inscrit reprend les comptes
 * sans propriétaire créés avant l'authentification.
 */
class UserRegistration
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users,
        private readonly AccountRepository $accounts,
    ) {
    }

    /**
     * @return int comptes repris par le premier utilisateur, 0 ensuite
     */
    public function register(User $user, #[\SensitiveParameter] string $plainPassword): int
    {
        $isFirstUser = 0 === $this->users->count([]);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        return $this->em->wrapInTransaction(function () use ($user, $isFirstUser): int {
            $this->em->persist($user);
            $this->em->flush();

            return $isFirstUser ? $this->accounts->adoptOrphans($user) : 0;
        });
    }
}
