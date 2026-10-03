<?php

declare(strict_types=1);

namespace App\Tests\Story;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Trick;
use App\Entity\User;
use App\Story\AppStory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Foundry\Test\Factories;

final class AppStoryTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function storyCreatesValidDemoData(): void
    {
        AppStory::load();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $validator = self::getContainer()->get(ValidatorInterface::class);

        self::assertSame(6, $em->getRepository(User::class)->count([]));
        self::assertSame(6, $em->getRepository(Category::class)->count([]));
        self::assertSame(15, $em->getRepository(Trick::class)->count([]));
        self::assertNotNull($em->getRepository(User::class)->findOneBy(['email' => 'demo@snowtricks.dev']));

        foreach ($em->getRepository(Trick::class)->findAll() as $trick) {
            self::assertCount(0, $validator->validate($trick), $trick->getName());
            self::assertNotNull($trick->getSlug());
            self::assertSame(
                1,
                $em->getRepository(Media::class)->count(['trick' => $trick, 'isMain' => true]),
            );
        }
    }
}
