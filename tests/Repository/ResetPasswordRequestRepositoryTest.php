<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ResetPasswordRequest;
use App\Factory\UserFactory;
use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class ResetPasswordRequestRepositoryTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function itPersistsAndFindsARequestBySelector(): void
    {
        $user = UserFactory::createOne();
        $repository = $this->repository();

        $repository->persistResetPasswordRequest(
            $repository->createResetPasswordRequest($user, new \DateTimeImmutable('+1 hour'), 'selector-one-12345', 'hashed-token'),
        );

        $found = $repository->findResetPasswordRequest('selector-one-12345');
        self::assertInstanceOf(ResetPasswordRequest::class, $found);
        self::assertSame($user->getId(), $found->getUser()->getId());
        self::assertFalse($found->isExpired());
        self::assertNotNull($repository->getMostRecentNonExpiredRequestDate($user));
    }

    #[Test]
    public function itRemovesRequestsExpiredForMoreThanAWeek(): void
    {
        $user = UserFactory::createOne();
        $repository = $this->repository();
        foreach ([
            'selector-old-123456' => '-2 weeks',
            'selector-recent-123' => '-1 hour',
            'selector-valid-1234' => '+1 hour',
        ] as $selector => $expiresAt) {
            $repository->persistResetPasswordRequest(
                $repository->createResetPasswordRequest($user, new \DateTimeImmutable($expiresAt), $selector, 'hash'),
            );
        }

        self::assertSame(1, $repository->removeExpiredResetPasswordRequests());

        self::assertNull($repository->findResetPasswordRequest('selector-old-123456'));
        self::assertNotNull($repository->findResetPasswordRequest('selector-recent-123'));
        self::assertNotNull($repository->findResetPasswordRequest('selector-valid-1234'));
    }

    #[Test]
    public function deletingAUserDeletesItsRequests(): void
    {
        $user = UserFactory::createOne();
        $repository = $this->repository();
        $repository->persistResetPasswordRequest(
            $repository->createResetPasswordRequest($user, new \DateTimeImmutable('+1 hour'), 'selector-gone-12345', 'hash'),
        );

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->remove($user);
        $em->flush();
        $em->clear();

        self::assertSame(0, $repository->count([]));
    }

    private function repository(): ResetPasswordRequestRepository
    {
        return self::getContainer()->get(ResetPasswordRequestRepository::class);
    }
}
