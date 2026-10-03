<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Media;
use App\Enum\MediaType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Media>
 */
final class MediaFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Media::class;
    }

    public function video(): static
    {
        return $this->with([
            'type' => MediaType::Video,
            'source' => 'https://www.youtube.com/watch?v='.self::faker()->regexify('[A-Za-z0-9_-]{11}'),
        ]);
    }

    public function main(): static
    {
        return $this->with(['isMain' => true]);
    }

    protected function defaults(): array
    {
        return [
            'type' => MediaType::Image,
            'source' => 'https://picsum.photos/seed/'.self::faker()->uuid().'/800/600',
            'isMain' => false,
            'trick' => TrickFactory::new(),
        ];
    }
}
