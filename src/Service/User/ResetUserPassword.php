<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final readonly class ResetUserPassword
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ResetPasswordExceptionInterface when the token is invalid or expired
     */
    public function checkToken(string $token): void
    {
        $this->resetPasswordHelper->validateTokenAndFetchUser($token);
    }

    /**
     * @throws ResetPasswordExceptionInterface when the token is invalid or expired
     */
    public function __invoke(string $token, string $plainPassword): void
    {
        $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        \assert($user instanceof User);

        // A token is single-use: drop the request before anything else.
        $this->resetPasswordHelper->removeResetRequest($token);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }
}
