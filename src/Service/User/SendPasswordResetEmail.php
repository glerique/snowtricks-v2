<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final readonly class SendPasswordResetEmail
{
    public const ROUTE = 'app_reset_password';

    public function __construct(
        private UserRepository $userRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Never tells the caller whether the address exists or whether the request was throttled:
     * the response to the visitor must be the same in every case.
     */
    public function __invoke(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (null === $user) {
            return;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return;
        }

        $this->mailer->send(
            (new TemplatedEmail())
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('email/reset_password.html.twig')
                ->context([
                    'username' => $user->getUsername(),
                    'resetUrl' => $this->urlGenerator->generate(
                        self::ROUTE,
                        ['token' => $resetToken->getToken()],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                ]),
        );
    }
}
