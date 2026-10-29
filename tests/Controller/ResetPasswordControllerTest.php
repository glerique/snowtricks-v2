<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;

final class ResetPasswordControllerTest extends WebTestCase
{
    use Factories;

    #[Test]
    public function userCanResetThePasswordFromTheEmailedLink(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com']);

        $this->requestReset($client, 'rider@example.com');
        self::assertResponseRedirects('/reset-password/check-email');
        $link = $this->resetLinkFromSentEmail();

        // The token is moved from the URL to the session, then the form is shown.
        $client->request('GET', $link);
        self::assertResponseRedirects('/reset-password/reset');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->submit($crawler->filter('form')->form([
            'change_password[plainPassword][first]' => 'my-new-password',
            'change_password[plainPassword][second]' => 'my-new-password',
        ]));
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Votre mot de passe a été modifié');

        $this->logIn($client, 'rider@example.com', 'my-new-password');
        self::assertResponseRedirects('/');
    }

    #[Test]
    public function oldPasswordNoLongerWorksAfterTheReset(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com']);
        $this->requestReset($client, 'rider@example.com');
        $link = $this->resetLinkFromSentEmail();
        $client->request('GET', $link);
        $crawler = $client->followRedirect();
        $client->submit($crawler->filter('form')->form([
            'change_password[plainPassword][first]' => 'my-new-password',
            'change_password[plainPassword][second]' => 'my-new-password',
        ]));

        $this->logIn($client, 'rider@example.com', 'password');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    public function unknownAddressGetsTheSameAnswerAndNoEmail(): void
    {
        $client = static::createClient();

        $this->requestReset($client, 'nobody@example.com');

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
    }

    #[Test]
    public function invalidEmailFormatIsRejected(): void
    {
        $client = static::createClient();

        $this->requestReset($client, 'not-an-email');

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    #[Test]
    public function invalidTokenRedirectsToANewRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', '/reset-password/reset/'.str_repeat('x', 40));
        $client->followRedirect();
        self::assertResponseRedirects('/reset-password');
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'invalide ou a expiré');
    }

    #[Test]
    public function resetPageWithoutATokenRedirectsToANewRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', '/reset-password/reset');

        self::assertResponseRedirects('/reset-password');
    }

    #[Test]
    public function shortNewPasswordIsRejected(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'rider@example.com']);
        $this->requestReset($client, 'rider@example.com');
        $link = $this->resetLinkFromSentEmail();
        $client->request('GET', $link);
        $crawler = $client->followRedirect();

        $client->submit($crawler->filter('form')->form([
            'change_password[plainPassword][first]' => 'short',
            'change_password[plainPassword][second]' => 'short',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'au moins 8 caractères');
    }

    #[Test]
    public function loginPageLinksToThePasswordResetRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertSelectorExists('a[href="/reset-password"]');
    }

    private function requestReset(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->filter('form')->form(['reset_password_request[email]' => $email]));
    }

    private function logIn(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('form')->form(['_username' => $email, '_password' => $password]));
    }

    /**
     * Must run right after the request: the mail log is reset when the kernel reboots.
     */
    private function resetLinkFromSentEmail(): string
    {
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        if (1 !== preg_match('/href="([^"]*\/reset-password\/reset\/[^"]*)"/', (string) $message->getHtmlBody(), $matches)) {
            self::fail('The reset link was not found in the email.');
        }

        return html_entity_decode($matches[1]);
    }
}
