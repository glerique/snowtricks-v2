<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Service\User\RegisterUser;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUserTest extends KernelTestCase
{
    #[Test]
    public function itHashesThePasswordAndSavesAnUnverifiedUser(): void
    {
        $user = (new User())->setEmail('rider@example.com')->setUsername('rider');

        self::getContainer()->get(RegisterUser::class)($user, 'my-secret-password');

        self::assertNotNull($user->getId());
        self::assertFalse($user->isVerified());
        self::assertNotSame('my-secret-password', $user->getPassword());
        self::assertTrue(
            self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'my-secret-password'),
        );
    }
}
