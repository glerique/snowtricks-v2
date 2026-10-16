<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Service\User\RegisterUser;
use App\Service\User\SendVerificationEmail;
use App\Service\User\VerifyUserEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, RegisterUser $registerUser): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $registerUser($user, (string) $form->get('plainPassword')->getData());
            $this->addFlash('success', 'Votre compte a été créé. Un e-mail de confirmation vient de vous être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', ['form' => $form]);
    }

    #[Route('/verify/email', name: SendVerificationEmail::ROUTE)]
    public function verifyEmail(Request $request, VerifyUserEmail $verifyUserEmail): Response
    {
        try {
            $verifyUserEmail($request->query->getInt('id') ?: null, $request->getUri());
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('danger', 'Ce lien de confirmation est invalide ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Votre adresse e-mail est confirmée. Vous pouvez vous connecter.');

        return $this->redirectToRoute('app_login');
    }
}
