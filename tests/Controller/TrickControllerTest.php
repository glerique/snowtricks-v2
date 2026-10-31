<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Trick;
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

    #[Test]
    public function visitorsAreSentToTheLoginPageToAddATrick(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tricks/new');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    public function theAddLinkIsOnlyShownToSignedInUsers(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');
        self::assertSelectorNotExists('nav a[href="/tricks/new"]');

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/');
        self::assertSelectorExists('nav a[href="/tricks/new"]');
    }

    #[Test]
    public function aSignedInUserCanCreateATrick(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne(['name' => 'Grabs']);
        $user = UserFactory::createOne(['username' => 'rider42']);
        $client->loginUser($user);

        $this->submitNewTrick($client, [
            'trick[name]' => 'Mute Grab',
            'trick[description]' => 'Grab the toe edge with the front hand.',
            'trick[category]' => (string) $category->getId(),
        ]);

        self::assertResponseRedirects('/tricks/mute-grab');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'La figure a été créée.');
        self::assertSelectorTextContains('h1', 'Mute Grab');
        self::assertSelectorTextContains('article', 'rider42');

        $trick = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Trick::class)->findOneBy(['slug' => 'mute-grab']);
        self::assertInstanceOf(Trick::class, $trick);
        self::assertSame($user->getId(), $trick->getAuthor()?->getId());
    }

    #[Test]
    public function aTrickNeedsANameADescriptionAndACategory(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $this->submitNewTrick($client, ['trick[name]' => '', 'trick[description]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
        self::assertSelectorTextContains('form', 'Cette valeur ne doit pas être vide.');
        self::assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Trick::class)->count([]));
    }

    #[Test]
    public function aTrickNameMustBeUnique(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);
        $category = CategoryFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $this->submitNewTrick($client, [
            'trick[name]' => 'Mute Grab',
            'trick[description]' => 'Another description.',
            'trick[category]' => (string) $category->getId(),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Une figure porte déjà ce nom.');
    }

    #[Test]
    public function theNameNewIsReservedBecauseItWouldHideTheCreationPage(): void
    {
        $client = static::createClient();
        $category = CategoryFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        foreach (['new', 'NEW', 'New'] as $name) {
            $this->submitNewTrick($client, [
                'trick[name]' => $name,
                'trick[description]' => 'A description.',
                'trick[category]' => (string) $category->getId(),
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('form', 'Ce nom est réservé.');
        }
    }

    #[Test]
    public function visitorsAreSentToTheLoginPageToEditATrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);

        $client->request('GET', '/tricks/mute-grab/edit');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    public function aMemberCannotEditSomeoneElsesTrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/tricks/mute-grab/edit');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function theAuthorSeesTheFormFilledWithTheTrick(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'description' => 'Original description.', 'author' => $author]);
        $client->loginUser($author);

        $client->request('GET', '/tricks/mute-grab/edit');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('trick[name]', 'Mute Grab');
        self::assertSelectorTextContains('textarea[name="trick[description]"]', 'Original description.');
    }

    #[Test]
    public function theAuthorCanEditTheirTrickAndTheAddressFollowsTheNewName(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $client->loginUser($author);

        $this->submitEditForm($client, 'mute-grab', ['trick[name]' => 'Indy Grab']);

        self::assertResponseRedirects('/tricks/indy-grab');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'La figure a été modifiée.');
        self::assertSelectorTextContains('h1', 'Indy Grab');
    }

    #[Test]
    public function aTrickCanBeSavedWithoutRenamingIt(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $client->loginUser($author);

        $this->submitEditForm($client, 'mute-grab', ['trick[description]' => 'A better description.']);

        self::assertResponseRedirects('/tricks/mute-grab');
        $client->followRedirect();
        self::assertSelectorTextContains('article', 'A better description.');
    }

    #[Test]
    public function anAdministratorCanEditAnyTrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);
        $client->loginUser(UserFactory::new()->asAdmin()->create());

        $this->submitEditForm($client, 'mute-grab', ['trick[description]' => 'Moderated description.']);

        self::assertResponseRedirects('/tricks/mute-grab');
    }

    #[Test]
    public function invalidChangesAreRejectedAndNothingIsSaved(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $client->loginUser($author);

        $this->submitEditForm($client, 'mute-grab', ['trick[name]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Cette valeur ne doit pas être vide.');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Trick::class)->findOneBy(['slug' => 'mute-grab']);
        self::assertSame('Mute Grab', $stored?->getName());
    }

    #[Test]
    public function editingAnUnknownTrickIsNotFound(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/tricks/does-not-exist/edit');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function theEditButtonIsOnlyShownToThoseWhoMayEdit(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);

        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorNotExists('a[href="/tricks/mute-grab/edit"]');

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorNotExists('a[href="/tricks/mute-grab/edit"]');

        $client->loginUser($author);
        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorExists('a[href="/tricks/mute-grab/edit"]');

        $client->loginUser(UserFactory::new()->asAdmin()->create());
        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorExists('a[href="/tricks/mute-grab/edit"]');
    }

    /**
     * @param array<string, string> $values
     */
    private function submitEditForm(KernelBrowser $client, string $slug, array $values): void
    {
        $crawler = $client->request('GET', '/tricks/'.$slug.'/edit');
        $client->submit($crawler->filter('form')->form($values));
    }

    #[Test]
    public function theAuthorCanDeleteTheirTrick(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $client->loginUser($author);

        $this->submitDeleteForm($client, 'mute-grab');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'La figure a été supprimée.');
        $client->request('GET', '/tricks/mute-grab');
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function anAdministratorCanDeleteAnyTrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);
        $client->loginUser(UserFactory::new()->asAdmin()->create());

        $this->submitDeleteForm($client, 'mute-grab');

        self::assertResponseRedirects('/');
        self::assertSame(0, $this->trickCount());
    }

    #[Test]
    public function aMemberCannotDeleteSomeoneElsesTrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);
        $client->loginUser(UserFactory::createOne());

        $client->request('POST', '/tricks/mute-grab/delete', ['_token' => 'whatever']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->trickCount());
    }

    #[Test]
    public function visitorsCannotDeleteATrick(): void
    {
        $client = static::createClient();
        TrickFactory::createOne(['name' => 'Mute Grab']);

        $client->request('POST', '/tricks/mute-grab/delete', ['_token' => 'whatever']);

        self::assertResponseRedirects('/login');
        self::assertSame(1, $this->trickCount());
    }

    #[Test]
    public function aWrongCsrfTokenIsRefused(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $client->loginUser($author);

        $client->request('POST', '/tricks/mute-grab/delete', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->trickCount());
    }

    #[Test]
    public function aTrickCannotBeDeletedWithAGetRequest(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $client->loginUser($author);

        $client->request('GET', '/tricks/mute-grab/delete');

        self::assertResponseStatusCodeSame(405);
        self::assertSame(1, $this->trickCount());
    }

    #[Test]
    public function theDeleteButtonIsOnlyShownToThoseWhoMayDelete(): void
    {
        $client = static::createClient();
        $author = UserFactory::createOne();
        TrickFactory::createOne(['name' => 'Mute Grab', 'author' => $author]);
        $form = 'form[action="/tricks/mute-grab/delete"]';

        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorNotExists($form);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorNotExists($form);

        $client->loginUser($author);
        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorExists($form);

        $client->loginUser(UserFactory::new()->asAdmin()->create());
        $client->request('GET', '/tricks/mute-grab');
        self::assertSelectorExists($form);
    }

    private function submitDeleteForm(KernelBrowser $client, string $slug): void
    {
        $crawler = $client->request('GET', '/tricks/'.$slug);
        $client->submit($crawler->selectButton('Supprimer')->form());
    }

    private function trickCount(): int
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getRepository(Trick::class)->count([]);
    }

    /**
     * @param array<string, string> $values
     */
    private function submitNewTrick(KernelBrowser $client, array $values): void
    {
        $crawler = $client->request('GET', '/tricks/new');
        $client->submit($crawler->filter('form')->form($values));
    }

    private function queryCount(KernelBrowser $client, string $url): int
    {
        $client->request('GET', $url);

        $client->enableProfiler();
        $client->request('GET', $url);

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
