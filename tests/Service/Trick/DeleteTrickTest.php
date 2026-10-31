<?php

declare(strict_types=1);

namespace App\Tests\Service\Trick;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\Trick;
use App\Entity\User;
use App\Factory\CommentFactory;
use App\Factory\MediaFactory;
use App\Factory\TrickFactory;
use App\Service\Trick\DeleteTrick;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DeleteTrickTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function itDeletesTheTrickWithItsMediasAndComments(): void
    {
        $trick = TrickFactory::createOne(['name' => 'Mute Grab']);
        MediaFactory::createMany(2, ['trick' => $trick]);
        CommentFactory::createMany(3, ['trick' => $trick]);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $reloaded = $em->getRepository(Trick::class)->findOneBy(['slug' => 'mute-grab']);
        self::assertInstanceOf(Trick::class, $reloaded);
        self::getContainer()->get(DeleteTrick::class)($reloaded);

        self::assertSame(0, $em->getRepository(Trick::class)->count([]));
        self::assertSame(0, $em->getRepository(Media::class)->count([]));
        self::assertSame(0, $em->getRepository(Comment::class)->count([]));
    }

    #[Test]
    public function itLeavesOtherTricksAndTheAuthorsAlone(): void
    {
        $doomed = TrickFactory::createOne(['name' => 'Mute Grab']);
        $kept = TrickFactory::createOne(['name' => 'Indy Grab']);
        MediaFactory::createOne(['trick' => $kept]);
        CommentFactory::createOne(['trick' => $kept]);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $reloaded = $em->getRepository(Trick::class)->findOneBy(['slug' => 'mute-grab']);
        self::assertInstanceOf(Trick::class, $reloaded);
        self::getContainer()->get(DeleteTrick::class)($reloaded);

        self::assertNotNull($em->getRepository(Trick::class)->findOneBy(['slug' => 'indy-grab']));
        self::assertSame(1, $em->getRepository(Media::class)->count([]));
        self::assertSame(1, $em->getRepository(Comment::class)->count([]));
        self::assertNotNull($em->getRepository(User::class)->find($doomed->getAuthor()?->getId()));
    }
}
