<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Service\User\ResetUserPassword;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\InvalidResetPasswordTokenException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Zenstruck\Foundry\Test\Factories;

final class ResetUserPasswordTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function itReplacesThePasswordWithTheNewHash(): void
    {
        $user = UserFactory::createOne();
        $token = $this->tokenFor($user);

        $this->service()($token, 'a-brand-new-password');

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'a-brand-new-password'));
        self::assertFalse($hasher->isPasswordValid($user, 'password'));
    }

    #[Test]
    public function aTokenCanOnlyBeUsedOnce(): void
    {
        $user = UserFactory::createOne();
        $token = $this->tokenFor($user);
        $this->service()($token, 'a-brand-new-password');

        $this->expectException(InvalidResetPasswordTokenException::class);
        $this->service()($token, 'another-password-attempt');
    }

    #[Test]
    public function anInvalidTokenIsRejectedAndChangesNothing(): void
    {
        $user = UserFactory::createOne();
        $originalHash = $user->getPassword();

        try {
            $this->service()(str_repeat('x', 40), 'a-brand-new-password');
            self::fail('An invalid token must be rejected.');
        } catch (InvalidResetPasswordTokenException) {
            self::assertSame($originalHash, $user->getPassword());
        }
    }

    #[Test]
    public function checkTokenAcceptsAValidTokenAndRejectsAnInvalidOne(): void
    {
        $token = $this->tokenFor(UserFactory::createOne());

        $this->service()->checkToken($token);
        $this->expectException(InvalidResetPasswordTokenException::class);
        $this->service()->checkToken('too-short');
    }

    private function tokenFor(User $user): string
    {
        return self::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($user)->getToken();
    }

    private function service(): ResetUserPassword
    {
        return self::getContainer()->get(ResetUserPassword::class);
    }
}
