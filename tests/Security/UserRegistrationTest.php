<?php

namespace App\Tests\Security;

use App\Portfolio\Entity\Account;
use App\Security\Entity\User;
use App\Security\UserRegistration;
use App\Tests\Support\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationTest extends KernelTestCase
{
    use AuthenticatedTestTrait;

    private EntityManagerInterface $em;
    private UserRegistration $registration;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->registration = self::getContainer()->get(UserRegistration::class);
        $this->resetDatabase($this->em);
    }

    private function orphan(string $name): Account
    {
        $account = new Account($name);
        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    private function owners(): array
    {
        $this->em->clear();

        $owners = [];
        foreach ($this->em->getRepository(Account::class)->findBy([], ['name' => 'ASC']) as $account) {
            $owners[$account->getName()] = $account->getOwner()?->getEmail();
        }

        return $owners;
    }

    public function testFirstUserTakesOverTheExistingAccountsOfTheDatabase(): void
    {
        $this->orphan('MFFU');
        $this->orphan('TopStep');

        $adopted = $this->registration->register(new User('premier@example.com'), 'un-bon-mot-de-passe');

        $this->assertSame(2, $adopted);
        $this->assertSame(['MFFU' => 'premier@example.com', 'TopStep' => 'premier@example.com'], $this->owners());
    }

    public function testFollowingUsersStartWithAnEmptyWorkspace(): void
    {
        $this->registration->register(new User('premier@example.com'), 'un-bon-mot-de-passe');
        $this->orphan('Apparu-plus-tard');

        $adopted = $this->registration->register(new User('second@example.com'), 'un-bon-mot-de-passe');

        $this->assertSame(0, $adopted);
        $this->assertSame(['Apparu-plus-tard' => null], $this->owners(), 'Le deuxième utilisateur ne reprend rien.');
    }

    public function testFirstUserWithNoExistingDataAdoptsNothing(): void
    {
        $this->assertSame(0, $this->registration->register(new User('premier@example.com'), 'un-bon-mot-de-passe'));
    }

    public function testPasswordIsHashedAndUserIsPersisted(): void
    {
        $user = new User('premier@example.com');

        $this->registration->register($user, 'un-bon-mot-de-passe');

        $this->assertNotNull($user->getId());
        $this->assertNotSame('un-bon-mot-de-passe', $user->getPassword());
        $this->assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'un-bon-mot-de-passe'));
    }
}
