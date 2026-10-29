<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Form\ResetPasswordRequestType;
use App\Service\User\ResetUserPassword;
use App\Service\User\SendPasswordResetEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    #[Route('/reset-password', name: 'app_forgot_password_request')]
    public function request(Request $request, SendPasswordResetEmail $sendPasswordResetEmail): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sendPasswordResetEmail((string) $form->get('email')->getData());

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/reset-password/reset/{token}', name: SendPasswordResetEmail::ROUTE, defaults: ['token' => null])]
    public function reset(Request $request, ResetUserPassword $resetUserPassword, ?string $token): Response
    {
        if (null !== $token) {
            // Move the token from the URL to the session, so it cannot leak through the Referer header.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute(SendPasswordResetEmail::ROUTE);
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            return $this->invalidLink();
        }

        try {
            $resetUserPassword->checkToken($token);
        } catch (ResetPasswordExceptionInterface) {
            return $this->invalidLink();
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $resetUserPassword($token, (string) $form->get('plainPassword')->getData());
            } catch (ResetPasswordExceptionInterface) {
                return $this->invalidLink();
            }

            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form]);
    }

    private function invalidLink(): Response
    {
        $this->cleanSessionAfterReset();
        $this->addFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré. Vous pouvez en demander un nouveau.');

        return $this->redirectToRoute('app_forgot_password_request');
    }
}
