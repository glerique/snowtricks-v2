<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Trick;
use App\Entity\User;
use App\Form\TrickType;
use App\Repository\TrickRepository;
use App\Service\Trick\CreateTrick;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tricks', name: 'app_trick_')]
final class TrickController extends AbstractController
{
    // Declared before "show": routes are matched in order, and "new" would otherwise be read as a slug.
    #[Route('/new', name: 'new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, CreateTrick $createTrick, #[CurrentUser] User $user): Response
    {
        $trick = new Trick();
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $createTrick($trick, $user);
            $this->addFlash('success', 'La figure a été créée.');

            return $this->redirectToRoute('app_trick_show', ['slug' => $trick->getSlug()]);
        }

        return $this->render('trick/new.html.twig', ['form' => $form]);
    }

    #[Route('/{slug}', name: 'show')]
    public function show(string $slug, TrickRepository $tricks): Response
    {
        $trick = $tricks->findOneBy(['slug' => $slug])
            ?? throw $this->createNotFoundException('Cette figure n\'existe pas.');

        return $this->render('trick/show.html.twig', ['trick' => $trick]);
    }
}
