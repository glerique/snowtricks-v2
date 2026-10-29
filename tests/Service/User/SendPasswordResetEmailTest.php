<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Factory\UserFactory;
use App\Service\User\SendPasswordResetEmail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class SendPasswordResetEmailTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function itSendsAnEmailWithAResetLinkToAKnownUser(): void
    {
        UserFactory::createOne(['email' => 'rider@example.com']);

        $this->service()('rider@example.com');

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'rider@example.com');
        self::assertEmailHtmlBodyContains($email, '/reset-password/reset/');
    }

    #[Test]
    public function itSendsNothingForAnUnknownAddress(): void
    {
        $this->service()('nobody@example.com');

        self::assertEmailCount(0);
    }

    #[Test]
    public function aSecondRequestWithinTheThrottleDelaySendsNothingMore(): void
    {
        UserFactory::createOne(['email' => 'rider@example.com']);

        $this->service()('rider@example.com');
        $this->service()('rider@example.com');

        self::assertEmailCount(1);
    }

    private function service(): SendPasswordResetEmail
    {
        return self::getContainer()->get(SendPasswordResetEmail::class);
    }
}
