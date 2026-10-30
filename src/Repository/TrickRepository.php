<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Trick;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trick>
 */
final class TrickRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trick::class);
    }

    public function createListingQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->addSelect('c', 'a', 'm')
            ->join('t.category', 'c')
            ->join('t.author', 'a')
            ->leftJoin('t.medias', 'm')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC');
    }
}
