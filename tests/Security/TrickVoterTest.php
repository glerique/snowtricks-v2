<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Trick;
use App\Entity\User;
use App\Factory\TrickFactory;
use App\Factory\UserFactory;
use App\Security\TrickVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Zenstruck\Foundry\Test\Factories;

final class TrickVoterTest extends KernelTestCase
{
    use Factories;

    /**
     * @return iterable<string, array{string}>
     */
    public static function attributes(): iterable
    {
        yield 'edit' => [TrickVoter::EDIT];
        yield 'delete' => [TrickVoter::DELETE];
    }

    #[Test]
    #[DataProvider('attributes')]
    public function theAuthorIsAllowed(string $attribute): void
    {
        $author = UserFactory::createOne();
        $trick = TrickFactory::createOne(['author' => $author]);

        self::assertTrue($this->isGrantedTo($author, $attribute, $trick));
    }

    #[Test]
    #[DataProvider('attributes')]
    public function anotherMemberIsDenied(string $attribute): void
    {
        $trick = TrickFactory::createOne();

        self::assertFalse($this->isGrantedTo(UserFactory::createOne(), $attribute, $trick));
    }

    #[Test]
    #[DataProvider('attributes')]
    public function anAdministratorIsAllowed(string $attribute): void
    {
        $admin = UserFactory::new()->asAdmin()->create();
        $trick = TrickFactory::createOne();

        self::assertTrue($this->isGrantedTo($admin, $attribute, $trick));
    }

    #[Test]
    #[DataProvider('attributes')]
    public function aVisitorIsDenied(string $attribute): void
    {
        $trick = TrickFactory::createOne();

        self::assertFalse($this->isGrantedTo(null, $attribute, $trick));
    }

    private function isGrantedTo(?User $user, string $attribute, Trick $trick): bool
    {
        $container = self::getContainer();
        $container->get(TokenStorageInterface::class)->setToken(
            null === $user ? null : new UsernamePasswordToken($user, 'main', $user->getRoles()),
        );

        return $container->get(AuthorizationCheckerInterface::class)->isGranted($attribute, $trick);
    }
}
