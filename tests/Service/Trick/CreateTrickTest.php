<?php

declare(strict_types=1);

namespace App\Tests\Service\Trick;

use App\Entity\Trick;
use App\Factory\CategoryFactory;
use App\Factory\UserFactory;
use App\Service\Trick\CreateTrick;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class CreateTrickTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function itSavesTheTrickWithItsAuthor(): void
    {
        $author = UserFactory::createOne();
        $trick = (new Trick())
            ->setName('Mute Grab')
            ->setDescription('Grab the toe edge with the front hand.')
            ->setCategory(CategoryFactory::createOne());

        self::getContainer()->get(CreateTrick::class)($trick, $author);

        self::assertNotNull($trick->getId());
        self::assertSame($author, $trick->getAuthor());
        self::assertSame('mute-grab', $trick->getSlug());
        self::assertNotNull($trick->getCreatedAt());
    }
}
