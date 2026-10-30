<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TrickRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tricks', name: 'app_trick_')]
final class TrickController extends AbstractController
{
    #[Route('/{slug}', name: 'show')]
    public function show(string $slug, TrickRepository $tricks): Response
    {
        $trick = $tricks->findOneBy(['slug' => $slug])
            ?? throw $this->createNotFoundException('Cette figure n\'existe pas.');

        return $this->render('trick/show.html.twig', ['trick' => $trick]);
    }
}
