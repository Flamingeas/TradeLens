<?php

namespace App\Import;

use App\Portfolio\Entity\Account;
use App\Portfolio\Repository\AccountRepository;
use App\Security\Entity\User;

/**
 * Donne à chaque ligne importée son compte, unique pour le fichier ou indiqué par la ligne : retrouve celui de
 * l'utilisateur ou en prépare un nouveau. Un exemplaire par import.
 */
final class AccountResolver
{
    /** @var array<string, Account> */
    private array $accounts = [];
    /** @var list<string> */
    private array $created = [];

    /**
     * @param string|null $fixedName compte unique pour tout le fichier ; null : celui de chaque ligne
     */
    public function __construct(
        private readonly AccountRepository $repository,
        private readonly User $owner,
        private readonly ?string $fixedName = null,
    ) {
    }

    /** Vrai si un compte unique est imposé. */
    public function isFixed(): bool
    {
        return null !== $this->fixedName;
    }

    /**
     * @param string|null $rowName compte indiqué par la ligne, ignoré si un compte unique est imposé
     *
     * @throws \InvalidArgumentException si aucun nom de compte n'est disponible
     */
    public function resolve(?string $rowName = null): Account
    {
        $name = $this->fixedName ?? ('' === trim((string) $rowName) ? null : trim((string) $rowName));
        if (null === $name) {
            throw new \InvalidArgumentException('Aucun compte : le fichier n\'en indique pas, précisez-en un.');
        }

        if (!isset($this->accounts[$name])) {
            $existing = $this->repository->findOneByOwnerAndName($this->owner, $name);
            $this->accounts[$name] = $existing ?? new Account($name, $this->owner);
            if (null === $existing) {
                $this->created[] = $name;
            }
        }

        return $this->accounts[$name];
    }

    /**
     * @return list<Account> comptes utilisés jusqu'ici
     */
    public function all(): array
    {
        return array_values($this->accounts);
    }

    /**
     * @return list<string> noms des comptes qui n'existaient pas encore
     */
    public function createdNames(): array
    {
        return $this->created;
    }
}
