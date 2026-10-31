<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\MediaType;
use App\Factory\CategoryFactory;
use App\Factory\MediaFactory;
use App\Factory\TrickFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Zenstruck\Foundry\Test\Factories;

final class HomeControllerTest extends WebTestCase
{
    use Factories;

    #[Test]
    public function homePageIsAccessibleToVisitors(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav a[href="/login"]');
    }

    #[Test]
    public function itExplainsThereIsNothingToShowWithoutTricks(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertSelectorTextContains('main', 'Aucune figure pour le moment.');
        self::assertSelectorNotExists('.card');
    }

    #[Test]
    public function itListsTricksNewestFirst(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Older trick']);
        TrickFactory::createOne(['name' => 'Newer trick']);

        $crawler = $client->request('GET', '/');

        self::assertCount(2, $crawler->filter('.card'));
        self::assertSame('Newer trick', trim($crawler->filter('.card-title')->first()->text()));
    }

    #[Test]
    public function eachCardLinksToItsTrickPage(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);

        $client->request('GET', '/');

        self::assertSelectorTextContains('.card a.stretched-link[href="/tricks/mute-grab"]', 'Mute Grab');
    }

    #[Test]
    public function cardLinksLeaveTheTurboFrameWhileTheViewMoreButtonStaysInIt(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $crawler = $client->request('GET', '/');

        self::assertCount(12, $crawler->filter('.card a.stretched-link[data-turbo-frame="_top"]'));
        self::assertSelectorNotExists('a.btn[data-turbo-frame]');
    }

    #[Test]
    public function itShowsTheCategoryAndTheAuthorOnEachCard(): void
    {
        $client = static::createClient();
        TrickFactory::createOne([
            'category' => CategoryFactory::createOne(['name' => 'Grabs']),
            'author' => UserFactory::createOne(['username' => 'rider42']),
        ]);

        $client->request('GET', '/');

        self::assertSelectorTextContains('.card-text', 'Grabs');
        self::assertSelectorTextContains('.card-text', 'rider42');
    }

    #[Test]
    public function itShowsTwelveTricksFirstWithAViewMoreButton(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $crawler = $client->request('GET', '/');

        self::assertCount(12, $crawler->filter('.card'));
        self::assertSelectorTextContains('turbo-frame#tricks-page-2 a.btn[href="/?page=2"]', 'Voir plus');
    }

    #[Test]
    public function everyCardIsADirectChildOfItsGridRow(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        foreach (['/', '/?page=2'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertCount(
                $crawler->filter('.card')->count(),
                $crawler->filter('.row > .col > .card'),
                'Every card must sit in a .col that is a direct child of a .row.',
            );
        }
    }

    #[Test]
    public function theNextPageHoldsTheRemainingTricksInTheFrameTurboWillSwap(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $crawler = $client->request('GET', '/?page=2', server: ['HTTP_TURBO_FRAME' => 'tricks-page-2']);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('turbo-frame#tricks-page-2 .card'));
    }

    #[Test]
    public function laterPagesLinkBackToTheStart(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $client->request('GET', '/?page=2');

        self::assertSelectorTextContains('main a[href="/"]', 'Revenir au début');
    }

    #[Test]
    public function theFirstPageHasNoBackToStartLink(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $client->request('GET', '/');

        self::assertSelectorNotExists('main a[href="/"]');
    }

    #[Test]
    public function theBackToStartLinkStaysOutsideTheFramesTurboCopies(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $crawler = $client->request('GET', '/?page=2', server: ['HTTP_TURBO_FRAME' => 'tricks-page-2']);

        self::assertCount(0, $crawler->filter('turbo-frame a[href="/"]'));
    }

    #[Test]
    public function theLastPageHasNoViewMoreButton(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(13);

        $client->request('GET', '/?page=2');

        self::assertSelectorNotExists('a.btn');
    }

    #[Test]
    public function aShortListHasNoViewMoreButton(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(3);

        $crawler = $client->request('GET', '/');

        self::assertCount(3, $crawler->filter('.card'));
        self::assertSelectorNotExists('a.btn');
    }

    #[Test]
    public function aNonsensePageNumberFallsBackToTheFirstPage(): void
    {
        $client = static::createClient();
        TrickFactory::createMany(2);

        foreach (['0', '-3', 'abc'] as $page) {
            $crawler = $client->request('GET', '/?page='.$page);

            self::assertResponseIsSuccessful();
            self::assertCount(2, $crawler->filter('.card'));
        }
    }

    #[Test]
    public function aCardShowsTheCoverImageOrAPlaceholder(): void
    {
        $client = static::createClient();
        $withImage = TrickFactory::createOne(['name' => 'With image']);
        MediaFactory::createOne(['trick' => $withImage, 'type' => MediaType::Image, 'source' => 'https://example.com/cover.jpg', 'isMain' => true]);
        TrickFactory::createOne(['name' => 'Without image']);

        $client->request('GET', '/');

        self::assertSelectorExists('img[src="https://example.com/cover.jpg"]');
        self::assertSelectorTextContains('main', "Pas d'image");
    }

    #[Test]
    public function theNumberOfQueriesDoesNotGrowWithTheNumberOfTricks(): void
    {
        $client = static::createClient();

        TrickFactory::createMany(2);
        $few = $this->queryCountOfHomePage($client);

        TrickFactory::createMany(8);
        $many = $this->queryCountOfHomePage($client);

        self::assertSame($few, $many);
    }

    private function queryCountOfHomePage(KernelBrowser $client): int
    {
        $client->request('GET', '/');

        $client->enableProfiler();
        $client->request('GET', '/');

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
