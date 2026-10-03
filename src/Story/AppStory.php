<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\CategoryFactory;
use App\Factory\CommentFactory;
use App\Factory\MediaFactory;
use App\Factory\TrickFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\faker;

/**
 * Demo data for local development. Log in with demo@snowtricks.dev / password.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    private const CATEGORIES = ['Grabs', 'Rotations', 'Flips', 'Slides', 'Jumps', 'Old school'];

    public function build(): void
    {
        $users = [
            UserFactory::createOne(['email' => 'demo@snowtricks.dev', 'username' => 'demo']),
            ...UserFactory::createMany(5),
        ];
        $categories = CategoryFactory::createSequence(
            array_map(static fn (string $name): array => ['name' => $name], self::CATEGORIES)
        );

        foreach (range(1, 15) as $_) {
            $trick = TrickFactory::createOne([
                'author' => faker()->randomElement($users),
                'category' => faker()->randomElement($categories),
            ]);

            MediaFactory::createOne(['trick' => $trick, 'isMain' => true]);
            MediaFactory::createMany(faker()->numberBetween(0, 2), ['trick' => $trick]);
            MediaFactory::new(['trick' => $trick])->video()->many(faker()->numberBetween(0, 1))->create();

            CommentFactory::createMany(
                faker()->numberBetween(0, 8),
                static fn (): array => ['trick' => $trick, 'author' => faker()->randomElement($users)],
            );
        }
    }
}
