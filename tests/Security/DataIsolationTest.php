<?php

namespace App\Tests\Security;

use App\Dashboard\AccountOverviewProvider;
use App\Portfolio\AccountLocator;
use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Trade;
use App\Portfolio\Enum\TradeDirection;
use App\Portfolio\Repository\AccountRepository;
use App\Portfolio\Repository\TradeRepository;
use App\Security\Entity\User;
use App\Tests\Support\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Chacun ne voit, ne modifie et ne supprime que ses propres données.
 *
 * Deux utilisateurs, avec des noms de compte en commun ("MFFU") pour que rien ne tienne au hasard :
 *   A : MFFU (2 trades, net 97,20 chacun = 194,40) et Apex (1 trade, net -52,80)       -> 3 trades, net +141,60
 *   B : MFFU (1 trade, net 997,20) et Lucid (1 trade, net 97,20)                        -> 2 trades, net +1 094,40
 * Un compte sans propriétaire (données d'avant l'authentification) n'est visible de personne.
 */
class DataIsolationTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $alice;
    private User $bob;
    private Account $aliceMffu;
    private Account $bobMffu;
    private Account $bobLucid;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($this->em);

        $this->alice = $this->createUser($this->em, 'alice@example.com');
        $this->bob = $this->createUser($this->em, 'bob@example.com');

        $this->aliceMffu = $this->account('MFFU', $this->alice, ['100.00', '100.00']);
        $this->account('Apex', $this->alice, ['-50.00']);
        $this->bobMffu = $this->account('MFFU', $this->bob, ['1000.00']);
        $this->bobLucid = $this->account('Lucid', $this->bob, ['100.00']);
        $this->account('Orphelin', null, ['5000.00']);
    }

    /**
     * @param list<string> $grossPnls un trade par valeur (commission 2,80 chacun)
     */
    private function account(string $name, ?User $owner, array $grossPnls): Account
    {
        $account = new Account($name, $owner);
        $this->em->persist($account);

        foreach ($grossPnls as $i => $gross) {
            $closed = new \DateTimeImmutable(sprintf('-%d days', $i + 1), new \DateTimeZone('UTC'));
            $this->em->persist(new Trade($account, 'ESH6', TradeDirection::Long, 1, $closed->modify('-5 minutes'), $closed, '5000.00', '5001.00', $gross, '2.80'));
        }
        $this->em->flush();

        return $account;
    }

    private function dashboardAs(User $user, string $query = ''): Crawler
    {
        $this->client->loginUser($user);

        return $this->client->request('GET', '/dashboard'.$query);
    }

    private function accountCards(Crawler $page): array
    {
        return $page->filter('.tl-accounts .tl-account .tl-account-name')->each(static fn (Crawler $n) => trim($n->text()));
    }

    private function stat(Crawler $page, string $key): string
    {
        return trim($page->filter('[data-stat="'.$key.'"] .tl-stat-value')->text());
    }

    // Ce que chacun voit

    public function testEachUserSeesOnlyHisOwnAccountsAndNumbers(): void
    {
        $page = $this->dashboardAs($this->alice);

        $this->assertSame(['Tous les comptes', 'Apex', 'MFFU'], $this->accountCards($page));
        $this->assertSame('3', $this->stat($page, 'trade-count'));
        $this->assertStringContainsString('+141,60', $this->stat($page, 'net'));

        $page = $this->dashboardAs($this->bob);

        $this->assertSame(['Tous les comptes', 'Lucid', 'MFFU'], $this->accountCards($page));
        $this->assertSame('2', $this->stat($page, 'trade-count'));
        $this->assertStringContainsString('+1', $this->stat($page, 'net'));
        $this->assertStringContainsString('094,40', $this->stat($page, 'net'));
    }

    public function testNothingOfAnotherUserLeaksIntoThePage(): void
    {
        $this->dashboardAs($this->alice);
        $html = (string) $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString('Lucid', $html, 'Le compte de Bob ne doit pas apparaître.');
        $this->assertStringNotContainsString('bob@example.com', $html);
        $this->assertStringNotContainsString('997,20', $html, 'Le trade de Bob ne doit pas être compté.');
        $this->assertStringNotContainsString('Orphelin', $html, 'Un compte sans propriétaire est invisible.');
        $this->assertStringNotContainsString('5000', str_replace(['5000.00', '5 000'], '', $html), "Le P&L du compte orphelin ne doit pas être compté.");
    }

    public function testTheTopBarShowsTheLoggedInUserAndNobodyElse(): void
    {
        $page = $this->dashboardAs($this->bob);

        $this->assertSame('bob@example.com', trim($page->filter('.tl-user')->text()));
    }

    // Deviner l'adresse d'un compte d'autrui

    public function testAnotherUsersAccountNameInTheAddressIsTreatedAsUnknown(): void
    {
        // "lucid" est un compte de Bob : pour Alice, c'est un nom inconnu, donc on affiche « Tous les comptes ».
        $page = $this->dashboardAs($this->alice, '?account=lucid');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Tous les comptes', trim($page->filter('.tl-account[aria-current=page] .tl-account-name')->text()));
        $this->assertSame('3', $this->stat($page, 'trade-count'), 'Les chiffres sont ceux d\'Alice, pas ceux de Bob.');
    }

    public function testAnotherUsersAccountIdInTheAddressIsTreatedAsUnknown(): void
    {
        // L'ancien format numérique ne permet pas non plus de lire le compte de quelqu'un d'autre.
        $page = $this->dashboardAs($this->alice, '?account='.$this->bobLucid->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Tous les comptes', trim($page->filter('.tl-account[aria-current=page] .tl-account-name')->text()));
        $this->assertSame('3', $this->stat($page, 'trade-count'));

        $page = $this->dashboardAs($this->alice, '?account='.$this->bobMffu->getId());
        $this->assertSame('3', $this->stat($page, 'trade-count'));
        $this->assertStringNotContainsString('997,20', (string) $this->client->getResponse()->getContent());
    }

    public function testSameAccountNameGivesEachUserHisOwnNumbers(): void
    {
        $alice = $this->dashboardAs($this->alice, '?account=mffu');
        $this->assertSame('2', $this->stat($alice, 'trade-count'));
        $this->assertStringContainsString('+194,40', $this->stat($alice, 'net'));

        $bob = $this->dashboardAs($this->bob, '?account=mffu');
        $this->assertSame('1', $this->stat($bob, 'trade-count'));
        $this->assertStringContainsString('+997,20', $this->stat($bob, 'net'));
    }

    public function testPeriodAndMonthNavigationStayInsideTheUsersData(): void
    {
        $page = $this->dashboardAs($this->alice, '?period=all&month=2000-01');

        $this->assertResponseIsSuccessful();
        $this->assertSame('3', $this->stat($page, 'trade-count'));
        $this->assertStringNotContainsString('Lucid', (string) $this->client->getResponse()->getContent());
    }

    // Suppression

    public function testDeletingMyAccountNeverTouchesTheSameNamedAccountOfAnotherUser(): void
    {
        $page = $this->dashboardAs($this->alice);
        $cross = $page->filter('button.tl-account-delete[aria-label="Supprimer le compte MFFU"]');
        $this->assertCount(1, $cross);

        $this->client->request('POST', $cross->attr('data-account-delete-url-param'), ['_token' => $cross->attr('data-account-delete-token-param')]);
        $this->client->followRedirect();

        $this->em->clear();
        $names = array_map(static fn (Account $a) => $a->getOwner()?->getEmail().':'.$a->getName(), $this->em->getRepository(Account::class)->findAll());
        sort($names);
        $this->assertSame([':Orphelin', 'alice@example.com:Apex', 'bob@example.com:Lucid', 'bob@example.com:MFFU'], $names);
        $this->assertSame(2, (int) $this->em->createQuery('SELECT COUNT(t) FROM '.Trade::class.' t JOIN t.account a WHERE a.owner = :o')->setParameter('o', $this->bob)->getSingleScalarResult(), 'Les trades de Bob sont intacts.');
    }

    public function testADeleteRequestForSomeoneElsesAccountChangesNothing(): void
    {
        $page = $this->dashboardAs($this->alice);
        // Alice utilise son propre jeton, mais vise le compte de Bob : refusé (jeton propre à chaque compte).
        $token = $page->filter('button.tl-account-delete')->first()->attr('data-account-delete-token-param');

        $this->client->request('POST', '/accounts/'.$this->bobMffu->getId().'/delete', ['_token' => $token]);
        $this->client->followRedirect();

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(Account::class)->find($this->bobMffu->getId()));
        $this->assertSame(5, $this->em->getRepository(Account::class)->count([]));
    }

    // Niveau des requêtes

    public function testRepositoriesAreScopedToTheOwner(): void
    {
        $trades = static::getContainer()->get(TradeRepository::class);
        $accounts = static::getContainer()->get(AccountRepository::class);

        $this->assertCount(3, $trades->findClosed($this->alice));
        $this->assertCount(2, $trades->findClosed($this->bob));
        $this->assertCount(2, $trades->findClosed($this->alice, null, $this->aliceMffu));

        // Même en passant le compte d'un autre : rien ne sort.
        $this->assertSame([], $trades->findClosed($this->alice, null, $this->bobMffu));

        $this->assertNull($accounts->findOneOfOwner($this->alice, (int) $this->bobMffu->getId()));
        $this->assertNotNull($accounts->findOneOfOwner($this->bob, (int) $this->bobMffu->getId()));
        $this->assertNull($accounts->findOneByOwnerAndName($this->alice, 'Lucid'));
        $this->assertSame(['MFFU', 'Apex'], array_map(static fn (Account $a) => $a->getName(), $accounts->findByOwner($this->alice)), 'Par identifiant croissant (ordre de création).');
    }

    public function testOverviewsAndLocatorAreScopedToTheOwner(): void
    {
        $overviews = static::getContainer()->get(AccountOverviewProvider::class);
        $locator = static::getContainer()->get(AccountLocator::class);

        $this->assertSame(['Apex', 'MFFU'], array_map(static fn ($o) => $o->name, $overviews->forUser($this->alice)));
        $this->assertSame(['Lucid', 'MFFU'], array_map(static fn ($o) => $o->name, $overviews->forUser($this->bob)));

        $this->assertNull($locator->find($this->alice, 'lucid'));
        $this->assertSame('Lucid', $locator->find($this->bob, 'lucid')->getName());
        // "mffu" désigne, pour chacun, son compte
        $this->assertSame($this->aliceMffu->getId(), $locator->find($this->alice, 'mffu')->getId());
        $this->assertSame($this->bobMffu->getId(), $locator->find($this->bob, 'mffu')->getId());
    }

    public function testAdoptedOrphansBecomeVisibleToTheirNewOwnerOnly(): void
    {
        $accounts = static::getContainer()->get(AccountRepository::class);

        $this->assertSame(1, $accounts->adoptOrphans($this->bob));

        $this->em->clear();
        $page = $this->dashboardAs($this->alice);
        $this->assertNotContains('Orphelin', $this->accountCards($page));
        $page = $this->dashboardAs($this->bob);
        $this->assertContains('Orphelin', $this->accountCards($page));
    }
}
