<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\MediaType;
use App\Factory\CategoryFactory;
use App\Factory\MediaFactory;
use App\Factory\TrickFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Zenstruck\Foundry\Test\Factories;

final class TrickControllerTest extends WebTestCase
{
    use Factories;

    #[Test]
    public function itShowsTheTrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne([
            'name' => 'Mute Grab',
            'description' => 'Grab the toe edge with the front hand.',
            'category' => CategoryFactory::createOne(['name' => 'Grabs']),
            'author' => UserFactory::createOne(['username' => 'rider42']),
        ]);

        $client->request('GET', '/tricks/mute-grab');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mute Grab');
        self::assertSelectorTextContains('article', 'Grabs');
        self::assertSelectorTextContains('article', 'rider42');
        self::assertSelectorTextContains('article', 'Grab the toe edge with the front hand.');
        self::assertPageTitleContains('Mute Grab');
    }

    #[Test]
    public function anUnknownTrickIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tricks/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function imagesAreShownWithTheMainImageFirstThenInOrder(): void
    {
        $client = static::createClient();
        $trick = TrickFactory::createOne(['name' => 'Mute Grab']);
        MediaFactory::createOne(['trick' => $trick, 'source' => 'https://example.com/first.jpg', 'isMain' => false]);
        MediaFactory::createOne(['trick' => $trick, 'source' => 'https://example.com/second.jpg', 'isMain' => false]);
        MediaFactory::createOne(['trick' => $trick, 'source' => 'https://example.com/main.jpg', 'isMain' => true]);
        // A real request starts with an empty identity map; otherwise Doctrine would not reload the medias.
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $crawler = $client->request('GET', '/tricks/mute-grab');

        self::assertSame(
            ['https://example.com/main.jpg', 'https://example.com/first.jpg', 'https://example.com/second.jpg'],
            $crawler->filter('article img')->each(static fn ($img): ?string => $img->attr('src')),
        );
    }

    #[Test]
    public function videosAreLinksOpeningInANewTab(): void
    {
        $client = static::createClient();
        $trick = TrickFactory::createOne(['name' => 'Mute Grab']);
        MediaFactory::new(['trick' => $trick, 'type' => MediaType::Video, 'source' => 'https://www.youtube.com/watch?v=abc'])->create();

        $crawler = $client->request('GET', '/tricks/mute-grab');

        $link = $crawler->filter('article a[href="https://www.youtube.com/watch?v=abc"]');
        self::assertCount(1, $link);
        self::assertSame('_blank', $link->attr('target'));
        self::assertSame('noopener noreferrer', $link->attr('rel'));
        self::assertCount(0, $crawler->filter('article img'));
    }

    #[Test]
    public function theDescriptionIsEscaped(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab', 'description' => '<script>alert(1)</script>']);

        $client->request('GET', '/tricks/mute-grab');

        self::assertSelectorNotExists('article script');
        self::assertSelectorTextContains('article', '<script>alert(1)</script>');
    }

    #[Test]
    public function lineBreaksInTheDescriptionAreKept(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab', 'description' => "First line\nSecond line"]);

        $client->request('GET', '/tricks/mute-grab');

        self::assertSelectorExists('article p br');
    }

    #[Test]
    public function aRenamedTrickMovesToItsNewAddress(): void
    {
        $client = static::createClient();
        $trick = TrickFactory::createOne(['name' => 'Mute Grab']);

        $trick->setName('Indy Grab');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/tricks/indy-grab');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/tricks/mute-grab');
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function theNumberOfQueriesDoesNotGrowWithTheNumberOfMedias(): void
    {
        $client = static::createClient();
        $few = TrickFactory::createOne(['name' => 'Few medias']);
        MediaFactory::createOne(['trick' => $few]);
        $many = TrickFactory::createOne(['name' => 'Many medias']);
        MediaFactory::createMany(6, ['trick' => $many]);

        $queriesForFew = $this->queryCount($client, '/tricks/few-medias');
        $queriesForMany = $this->queryCount($client, '/tricks/many-medias');

        self::assertSame($queriesForFew, $queriesForMany);
    }

    /**
     * The profiler counts every query since the container booted, fixtures included.
     * A first request reboots the kernel, so the measured one only holds its own queries.
     */
    private function queryCount(KernelBrowser $client, string $url): int
    {
        $client->request('GET', $url);

        $client->enableProfiler(); // only applies to the next request
        $client->request('GET', $url);

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
