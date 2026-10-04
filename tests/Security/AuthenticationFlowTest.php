<?php

namespace App\Tests\Security;

use App\Portfolio\Entity\Account;
use App\Portfolio\Entity\Trade;
use App\Portfolio\Enum\TradeDirection;
use App\Security\Entity\User;
use App\Tests\Support\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Parcours complet : accueil publique, inscription ou connexion, tableau de bord protégé, déconnexion (base de TEST). */
class AuthenticationFlowTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($this->em);
        // Remet à zéro le compteur de tentatives de connexion.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    private function register(string $email, string $password, ?string $confirmation = null): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => $password,
            'registration_form[plainPassword][second]' => $confirmation ?? $password,
        ]);
    }

    private function logIn(string $email, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['email' => $email, 'password' => $password]);
    }

    private function findUser(string $email): ?User
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    // Page d'accueil publique

    public function testLandingPageIsPublicAndLeadsToSignUpAndSignIn(): void
    {
        $page = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSame('TradeLens', trim($page->filter('main.tl-hero h1')->text()));
        $this->assertSame('/register', $page->selectLink('Créer un compte')->attr('href'));
        $this->assertSame('/login', $page->selectLink('Se connecter')->attr('href'));
        $this->assertSelectorNotExists('a[href="/dashboard"]', 'Pas de lien vers le tableau de bord pour un visiteur.');
        $this->assertSelectorNotExists('dialog', "L'import vit dans le tableau de bord, pas sur la vitrine.");
    }

    public function testLandingPageOffersTheDashboardToALoggedInUser(): void
    {
        $this->client->loginUser($this->createUser($this->em));

        $page = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSame('/dashboard', $page->selectLink('Ouvrir le tableau de bord')->attr('href'));
        $this->assertSelectorNotExists('a[href="/register"]');
        $this->assertSelectorNotExists('a[href="/login"]');
    }

    // Accès protégé

    public function testDashboardRedirectsVisitorsToTheLoginPage(): void
    {
        $this->client->request('GET', '/dashboard');

        $this->assertResponseRedirects('http://localhost/login');
    }

    public function testImportAndDeleteRoutesAreProtectedToo(): void
    {
        // (un GET sur /import répond 405 avant même le contrôle d'accès : il n'expose rien)
        foreach ([['POST', '/import'], ['POST', '/accounts/1/delete'], ['GET', '/dashboard?account=mffu']] as [$method, $uri]) {
            $this->client->request($method, $uri);

            $this->assertResponseRedirects('http://localhost/login', null, "$method $uri doit exiger une connexion");
        }
    }

    public function testLandingLoginAndRegisterStayPublic(): void
    {
        foreach (['/', '/login', '/register'] as $uri) {
            $this->client->request('GET', $uri);

            $this->assertResponseIsSuccessful($uri);
        }
    }

    public function testAfterLoginTheUserGoesBackToTheRequestedPage(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');

        $this->client->request('GET', '/dashboard?period=7d');
        $this->client->followRedirect();
        $this->client->submitForm('Se connecter', ['email' => 'trader@example.com', 'password' => 'un-bon-mot-de-passe']);

        $this->assertResponseRedirects('http://localhost/dashboard?period=7d');
    }

    // Inscription

    public function testRegistrationPageShowsTheForm(): void
    {
        $page = $this->client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form input[type=email][name="registration_form[email]"][autocomplete=email]');
        $this->assertSelectorExists('form input[type=password][name="registration_form[plainPassword][first]"][autocomplete=new-password]');
        $this->assertSelectorExists('form input[type=password][name="registration_form[plainPassword][second]"]');
        $this->assertSame('page', $page->filter('.tl-auth-tabs .nav-link.active')->attr('aria-current'));
        $this->assertSame('Créer un compte', trim($page->filter('.tl-auth-tabs .nav-link.active')->text()));
    }

    public function testSuccessfulRegistrationLogsTheUserInAndShowsTheDashboard(): void
    {
        $this->register('nouveau@example.com', 'un-bon-mot-de-passe');

        $this->assertResponseRedirects('/dashboard');
        $page = $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Bienvenue sur TradeLens', $page->filter('.alert-success')->text());
        $this->assertSame('nouveau@example.com', trim($page->filter('.tl-user')->text()), 'Connecté immédiatement, sans repasser par la connexion.');
        $this->assertSelectorExists('#empty-title', 'Un nouvel utilisateur démarre sans aucune donnée.');
    }

    public function testPasswordIsStoredHashedNeverInPlainText(): void
    {
        $this->register('nouveau@example.com', 'un-bon-mot-de-passe');

        $user = $this->findUser('nouveau@example.com');
        $this->assertNotNull($user);
        $this->assertNotSame('un-bon-mot-de-passe', $user->getPassword());
        $this->assertStringNotContainsString('un-bon-mot-de-passe', $user->getPassword());
        $this->assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'un-bon-mot-de-passe'));
        $this->assertFalse(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'un-autre-mot-de-passe'));
    }

    public function testEmailIsNormalizedToLowerCase(): void
    {
        $this->register('  Jean.Dupont@Example.COM ', 'un-bon-mot-de-passe');

        $this->assertNotNull($this->findUser('jean.dupont@example.com'));
    }

    public function testEmailAlreadyUsedIsRefusedWhateverItsCase(): void
    {
        $this->createUser($this->em, 'trader@example.com');

        $this->register('TRADER@example.com', 'un-bon-mot-de-passe');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('form', 'Un compte existe déjà avec cette adresse e-mail.');
        $this->assertSame(1, $this->em->getRepository(User::class)->count([]));
    }

    public function testInvalidRegistrationsAreRefusedWithClearMessages(): void
    {
        $cases = [
            'mot de passe trop court' => ['a@example.com', 'court', null, 'au moins 8 caractères'],
            'confirmation différente' => ['a@example.com', 'un-bon-mot-de-passe', 'une-autre-chose', 'ne correspondent pas'],
            'adresse invalide' => ['pas-une-adresse', 'un-bon-mot-de-passe', null, "n'est pas valide"],
            'adresse vide' => ['', 'un-bon-mot-de-passe', null, 'Saisissez votre adresse e-mail'],
            'mot de passe vide' => ['a@example.com', '', null, 'Choisissez un mot de passe'],
        ];

        foreach ($cases as $label => [$email, $password, $confirmation, $message]) {
            $this->register($email, $password, $confirmation);

            $this->assertResponseStatusCodeSame(422, $label);
            $this->assertSelectorTextContains('form', $message, $label);
            $this->assertSame(0, $this->em->getRepository(User::class)->count([]), $label.' : aucun utilisateur créé');
        }
    }

    public function testLoggedInUserIsSentBackToTheDashboardFromLoginAndRegister(): void
    {
        $this->client->loginUser($this->createUser($this->em));

        foreach (['/login', '/register'] as $uri) {
            $this->client->request('GET', $uri);

            $this->assertResponseRedirects('/dashboard', null, $uri);
        }
    }

    // Connexion

    public function testLoginPageShowsTheForm(): void
    {
        $page = $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/login"] input[type=email][name=email][autocomplete=email][required]');
        $this->assertSelectorExists('form[action="/login"] input[type=password][name=password][autocomplete=current-password][required]');
        $this->assertSelectorExists('form[action="/login"] input[type=checkbox][name=_remember_me]');
        $this->assertSelectorExists('form[action="/login"] input[type=hidden][name=_csrf_token]');
        $this->assertSame('Se connecter', trim($page->filter('.tl-auth-tabs .nav-link.active')->text()));
        $this->assertSame('/register', $page->filter('.tl-auth-tabs .nav-link:not(.active)')->attr('href'));
    }

    public function testSuccessfulLoginLeadsToTheDashboard(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');

        $this->logIn('trader@example.com', 'un-bon-mot-de-passe');

        $this->assertResponseRedirects('/dashboard');
        $page = $this->client->followRedirect();
        $this->assertSame('trader@example.com', trim($page->filter('.tl-user')->text()));
    }

    public function testLoginIgnoresEmailCase(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');

        $this->logIn('Trader@Example.com', 'un-bon-mot-de-passe');

        $this->assertResponseRedirects('/dashboard');
    }

    public function testWrongPasswordAndUnknownEmailGiveTheSameGenericMessage(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');

        $this->logIn('trader@example.com', 'mauvais-mot-de-passe');
        $this->assertResponseRedirects('/login');
        $wrongPassword = $this->client->followRedirect()->filter('.alert-danger')->text();

        $this->logIn('inconnu@example.com', 'mauvais-mot-de-passe');
        $this->assertResponseRedirects('/login');
        $unknownEmail = $this->client->followRedirect()->filter('.alert-danger')->text();

        $this->assertNotEmpty($wrongPassword);
        $this->assertSame($wrongPassword, $unknownEmail, "On ne révèle pas si l'adresse existe.");
    }

    public function testFailedLoginKeepsTheEmailButNeverThePassword(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');

        $this->logIn('trader@example.com', 'mauvais-mot-de-passe');
        $page = $this->client->followRedirect();

        $this->assertSame('trader@example.com', $page->filter('input[name=email]')->attr('value'));
        $this->assertSame('', (string) $page->filter('input[name=password]')->attr('value'));
        $this->assertStringNotContainsString('mauvais-mot-de-passe', (string) $this->client->getResponse()->getContent());
    }

    public function testLoginWithoutTheCsrfTokenIsRefused(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');

        $this->client->request('POST', '/login', ['email' => 'trader@example.com', 'password' => 'un-bon-mot-de-passe']);
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');

        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('http://localhost/login', null, "Pas connecté malgré les bons identifiants : le jeton manquait.");
    }

    public function testTooManyFailedAttemptsAreThrottled(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');
        for ($i = 0; $i < 5; ++$i) {
            $this->logIn('trader@example.com', 'mauvais-'.$i);
            $this->client->followRedirect();
        }

        // La 6e tentative est bloquée, même avec le BON mot de passe.
        $this->logIn('trader@example.com', 'un-bon-mot-de-passe');
        $this->assertResponseRedirects('/login');
        $page = $this->client->followRedirect();
        $this->assertStringContainsString('Trop de tentatives', $page->filter('.alert-danger')->text());

        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('http://localhost/login');
    }

    // Déconnexion

    public function testLogoutReturnsToTheLandingPageAndClosesTheSession(): void
    {
        $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');
        $this->logIn('trader@example.com', 'un-bon-mot-de-passe');
        $this->client->followRedirect();

        $this->client->clickLink('Se déconnecter');

        $this->assertResponseRedirects('/');
        $page = $this->client->followRedirect();
        $this->assertSame('/register', $page->selectLink('Créer un compte')->attr('href'), 'De retour en visiteur.');

        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('http://localhost/login');
    }

    public function testLogoutLinkCarriesACsrfToken(): void
    {
        $this->client->loginUser($this->createUser($this->em));

        $page = $this->client->request('GET', '/dashboard');

        $this->assertStringContainsString('_csrf_token=', $page->selectLink('Se déconnecter')->attr('href'));
    }

    // Mot de passe : jamais exposé

    public function testThePasswordHashIsNeverRenderedInAPage(): void
    {
        $user = $this->createUser($this->em, 'trader@example.com', 'un-bon-mot-de-passe');
        $this->client->loginUser($user);
        $account = new Account('Alpha', $user);
        $this->em->persist($account);
        $closed = new \DateTimeImmutable('1 day ago');
        $this->em->persist(new Trade($account, 'ESH6', TradeDirection::Long, 1, $closed, $closed, '5000.00', '5001.00', '50.00', '2.80'));
        $this->em->flush();

        foreach (['/dashboard', '/'] as $uri) {
            $this->client->request('GET', $uri);

            $this->assertStringNotContainsString($user->getPassword(), (string) $this->client->getResponse()->getContent(), $uri);
        }
    }
}
