<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

final class SecurityControllerTest extends WebTestCase
{
    use Factories;

    #[Test]
    public function userCanLogInWithValidCredentials(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com', 'username' => 'rider']);

        $this->submitLogin($client, 'rider@example.com', 'password');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('nav', 'rider');
    }

    #[Test]
    public function loginFormLoadsTheCsrfScript(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertSelectorExists('input[name="_csrf_token"][data-controller="csrf-protection"]');
    }

    #[Test]
    public function wrongPasswordShowsAnError(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com']);

        $this->submitLogin($client, 'rider@example.com', 'wrong-password');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    #[Test]
    public function userCanLogOut(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com', 'username' => 'rider']);
        $this->submitLogin($client, 'rider@example.com', 'password');

        $client->request('GET', '/logout');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorNotExists('nav .navbar-text');
        self::assertSelectorExists('nav a[href="/login"]');
    }

    #[Test]
    public function loggedInUserIsRedirectedAwayFromLoginPage(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    private function submitLogin(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form([
            '_username' => $email,
            '_password' => $password,
        ]));
    }
}
