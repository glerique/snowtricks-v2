<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $client->followRedirect();

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
        $client->followRedirect();

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
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function incompleteRegistrations(): iterable
    {
        yield 'everything empty' => ['', '', '', 'ne doit pas être vide'];
        yield 'no e-mail' => ['', 'rider', 'long-enough-password', 'ne doit pas être vide'];
        yield 'no username' => ['rider@example.com', '', 'long-enough-password', 'ne doit pas être vide'];
        yield 'no password' => ['rider@example.com', 'rider', '', 'Veuillez saisir un mot de passe.'];
    }

    #[Test]
    #[DataProvider('incompleteRegistrations')]
    public function emptyFieldsAreRejectedWithValidationMessages(string $email, string $username, string $password, string $message): void
    {
        $client = static::createClient();

        $this->submitRegistration($client, $email, $username, $password);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', $message);
        self::assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->count([]));
        self::assertEmailCount(0);
    }

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
