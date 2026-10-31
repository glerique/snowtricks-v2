<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final readonly class VerifyUserEmail
{
    public function __construct(
        private UserRepository $userRepository,
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(?int $userId, string $signedUrl): void
    {
        $user = null === $userId ? null : $this->userRepository->find($userId);
        if (null === $user) {
            throw new InvalidSignatureException();
        }

        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            Request::create($signedUrl),
            (string) $user->getId(),
            $user->getEmail(),
        );

        $user->setIsVerified(true);
        $this->entityManager->flush();
    }
}
