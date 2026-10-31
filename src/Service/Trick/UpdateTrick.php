<?php

declare(strict_types=1);

namespace App\Service\Trick;

use App\Entity\Trick;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UpdateTrick
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(Trick $trick): void
    {
        $this->entityManager->flush();
    }
}
