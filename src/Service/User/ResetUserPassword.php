<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final readonly class ResetUserPassword
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function checkToken(string $token): void
    {
        $this->resetPasswordHelper->validateTokenAndFetchUser($token);
    }

    public function __invoke(string $token, string $plainPassword): void
    {
        $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        \assert($user instanceof User);

        $this->resetPasswordHelper->removeResetRequest($token);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }
}
