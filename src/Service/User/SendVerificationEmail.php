<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final readonly class SendVerificationEmail
{
    public const ROUTE = 'app_verify_email';

    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            self::ROUTE,
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()],
        );

        $this->mailer->send(
            (new TemplatedEmail())
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject('Confirmez votre adresse e-mail')
                ->htmlTemplate('email/verification.html.twig')
                ->context([
                    'username' => $user->getUsername(),
                    'signedUrl' => $signature->getSignedUrl(),
                ]),
        );
    }
}
