<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;

final class RegistrationControllerTest extends WebTestCase
{
    use Factories;

    #[Test]
    public function visitorCanRegister(): void
    {
        $client = static::createClient();

        $this->submitRegistration($client, 'rider@example.com', 'rider', 'long-enough-password');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Votre compte a été créé');

        $user = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['email' => 'rider@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
    }

    #[Test]
    public function userCanConfirmTheirEmailAndLogIn(): void
    {
        $client = static::createClient();

        $this->submitRegistration($client, 'rider@example.com', 'rider', 'long-enough-password');
        $link = $this->verificationLinkFromSentEmail();
        $client->followRedirect(); // consumes the "account created" flash message

        $client->request('GET', $link);
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Votre adresse e-mail est confirmée');

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form([
            '_username' => 'rider@example.com',
            '_password' => 'long-enough-password',
        ]));
        self::assertResponseRedirects('/');
    }

    #[Test]
    public function unverifiedUserCannotLogIn(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com', 'isVerified' => false]);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form([
            '_username' => 'rider@example.com',
            '_password' => 'password',
        ]));

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'confirmer votre adresse e-mail');
    }

    #[Test]
    public function wrongPasswordOnUnverifiedAccountDoesNotRevealItsStatus(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com', 'isVerified' => false]);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form([
            '_username' => 'rider@example.com',
            '_password' => 'wrong-password',
        ]));

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextNotContains('.alert-danger', 'confirmer votre adresse e-mail');
    }

    #[Test]
    public function tamperedConfirmationLinkIsRejected(): void
    {
        $client = static::createClient();

        $this->submitRegistration($client, 'rider@example.com', 'rider', 'long-enough-password');
        $link = $this->verificationLinkFromSentEmail().'x';
        $client->followRedirect(); // consumes the "account created" flash message

        $client->request('GET', $link);
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'invalide ou a expiré');

        $user = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['email' => 'rider@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
    }

    #[Test]
    public function emailAlreadyUsedIsRejected(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com']);

        $this->submitRegistration($client, 'rider@example.com', 'someone-else', 'long-enough-password');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Cette adresse e-mail est déjà utilisée.');
    }

    #[Test]
    public function shortPasswordIsRejected(): void
    {
        $client = static::createClient();

        $this->submitRegistration($client, 'rider@example.com', 'rider', 'short');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'au moins 8 caractères');
    }

    #[Test]
    public function loggedInUserIsRedirectedAwayFromRegistration(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/register');

        self::assertResponseRedirects('/');
    }

    /**
     * Must run right after the registration request: the mail log is reset when the kernel reboots.
     */
    private function verificationLinkFromSentEmail(): string
    {
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        if (1 !== preg_match('/href="([^"]*\/verify\/email[^"]*)"/', (string) $message->getHtmlBody(), $matches)) {
            self::fail('The verification link was not found in the email.');
        }

        return html_entity_decode($matches[1]);
    }

    private function submitRegistration(KernelBrowser $client, string $email, string $username, string $password): void
    {
        $crawler = $client->request('GET', '/register');
        $client->submit($crawler->filter('form')->form([
            'registration[email]' => $email,
            'registration[username]' => $username,
            'registration[plainPassword][first]' => $password,
            'registration[plainPassword][second]' => $password,
        ]));
    }
}
