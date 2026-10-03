<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Trick;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Trick>
 */
final class TrickFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Trick::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => rtrim(self::faker()->unique()->sentence(2), '.'),
            'description' => self::faker()->paragraphs(3, true),
            'category' => CategoryFactory::new(),
            'author' => UserFactory::new(),
        ];
    }
}
