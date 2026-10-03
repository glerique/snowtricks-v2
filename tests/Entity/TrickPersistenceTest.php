<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\Trick;
use App\Entity\User;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrickPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function slugAndTimestampsAreGeneratedOnPersist(): void
    {
        $trick = $this->persistTrick('Mute Grab');

        self::assertSame('mute-grab', $trick->getSlug());
        self::assertNotNull($trick->getCreatedAt());
        self::assertNotNull($trick->getUpdatedAt());
    }

    #[Test]
    public function removingATrickRemovesItsMediasAndComments(): void
    {
        $trick = $this->persistTrick('Backside 360');
        $trick->addMedia(
            (new Media())->setType(MediaType::Video)->setSource('https://www.youtube.com/watch?v=abc')
        );
        $this->em->persist(
            (new Comment())->setContent('Nice!')->setAuthor($trick->getAuthor())->setTrick($trick)
        );
        $this->em->flush();
        $this->em->clear();

        $trick = $this->em->getRepository(Trick::class)->findOneBy(['slug' => 'backside-360']);
        self::assertNotNull($trick);
        self::assertCount(1, $trick->getMedias());
        self::assertCount(1, $trick->getComments());

        $this->em->remove($trick);
        $this->em->flush();

        self::assertSame(0, $this->em->getRepository(Media::class)->count([]));
        self::assertSame(0, $this->em->getRepository(Comment::class)->count([]));
    }

    #[Test]
    public function eachTestStartsWithAnEmptyDatabase(): void
    {
        self::assertSame(0, $this->em->getRepository(Trick::class)->count([]));
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
    }

    private function persistTrick(string $name): Trick
    {
        $author = (new User())
            ->setEmail(strtolower(str_replace(' ', '', $name)).'@example.com')
            ->setUsername(strtolower(str_replace(' ', '', $name)))
            ->setPassword('hashed');
        $category = (new Category())->setName('Grabs');
        $trick = (new Trick())
            ->setName($name)
            ->setDescription('A trick.')
            ->setCategory($category)
            ->setAuthor($author);

        $this->em->persist($author);
        $this->em->persist($category);
        $this->em->persist($trick);
        $this->em->flush();

        return $trick;
    }
}
