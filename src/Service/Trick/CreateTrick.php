<?php

declare(strict_types=1);

namespace App\Service\Trick;

use App\Entity\Trick;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CreateTrick
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(Trick $trick, User $author): void
    {
        $trick->setAuthor($author);

        $this->entityManager->persist($trick);
        $this->entityManager->flush();
    }
}
