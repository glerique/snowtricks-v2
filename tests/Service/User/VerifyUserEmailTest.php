<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Service\User\VerifyUserEmail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Zenstruck\Foundry\Test\Factories;

final class VerifyUserEmailTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function validLinkMarksTheUserAsVerified(): void
    {
        $user = UserFactory::createOne(['isVerified' => false]);

        self::getContainer()->get(VerifyUserEmail::class)($user->getId(), $this->signedUrlFor($user));

        self::assertTrue($user->isVerified());
    }

    #[Test]
    public function tamperedLinkIsRejected(): void
    {
        $user = UserFactory::createOne(['isVerified' => false]);

        $this->expectException(InvalidSignatureException::class);

        try {
            self::getContainer()->get(VerifyUserEmail::class)($user->getId(), $this->signedUrlFor($user).'x');
        } finally {
            self::assertFalse($user->isVerified());
        }
    }

    #[Test]
    public function unknownUserIsRejected(): void
    {
        $this->expectException(InvalidSignatureException::class);

        self::getContainer()->get(VerifyUserEmail::class)(999_999, 'http://localhost/verify/email');
    }

    private function signedUrlFor(User $user): string
    {
        return self::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature('app_verify_email', (string) $user->getId(), $user->getEmail(), ['id' => $user->getId()])
            ->getSignedUrl();
    }
}
