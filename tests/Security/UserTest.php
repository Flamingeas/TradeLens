<?php

namespace App\Tests\Security;

use App\Security\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testEmailIsNormalizedAndUsedAsTheIdentifier(): void
    {
        $user = new User('  Jean.Dupont@Example.COM ');

        $this->assertSame('jean.dupont@example.com', $user->getEmail());
        $this->assertSame('jean.dupont@example.com', $user->getUserIdentifier());

        $user->setEmail('AUTRE@Example.com');
        $this->assertSame('autre@example.com', $user->getEmail());
    }

    public function testEveryUserHasTheUserRoleExactlyOnce(): void
    {
        $user = new User('a@example.com');
        $this->assertSame(['ROLE_USER'], $user->getRoles());

        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER', 'ROLE_ADMIN']);
        $this->assertEqualsCanonicalizing(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testCreationDateIsSet(): void
    {
        $before = new \DateTimeImmutable('-2 seconds');

        $user = new User('a@example.com');

        $this->assertGreaterThan($before, $user->getCreatedAt());
    }

    /** La session ne garde que l'empreinte du hachage. */
    public function testTheSessionNeverContainsThePasswordHash(): void
    {
        $user = new User('a@example.com');
        $hash = '$2y$13$abcdefghijklmnopqrstuuMbRkD3Mhs8b3g5m4Z5pX8mE2VYb9a1e';
        $user->setPassword($hash);

        $serialized = serialize($user);

        $this->assertStringNotContainsString($hash, $serialized);
        $this->assertStringContainsString(hash('crc32c', $hash), $serialized, "L'empreinte sert à invalider les sessions si le mot de passe change.");
    }
}
