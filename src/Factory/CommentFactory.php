<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Comment;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Comment>
 */
final class CommentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Comment::class;
    }

    protected function defaults(): array
    {
        return [
            'content' => self::faker()->sentences(2, true),
            'author' => UserFactory::new(),
            'trick' => TrickFactory::new(),
        ];
    }
}
