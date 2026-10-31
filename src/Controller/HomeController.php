<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TrickRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const TRICKS_PER_PAGE = 12;

    #[Route('/', name: 'app_home')]
    public function index(Request $request, TrickRepository $tricks, PaginatorInterface $paginator): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));

        return $this->render('home/index.html.twig', [
            'tricks' => $paginator->paginate($tricks->createListingQueryBuilder(), $page, self::TRICKS_PER_PAGE),
        ]);
    }
}
