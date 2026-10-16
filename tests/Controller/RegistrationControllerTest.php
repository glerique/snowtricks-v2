<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
