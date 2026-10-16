<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class UserRepositoryTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function upgradePasswordStoresTheNewHash(): void
    {
        $user = UserFactory::createOne();
        $repository = self::getContainer()->get(UserRepository::class);

        $repository->upgradePassword($user, 'new-hash');

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = $repository->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('new-hash', $reloaded->getPassword());
    }
}
