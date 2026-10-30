<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Media;
use App\Entity\Trick;
use App\Enum\MediaType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrickTest extends TestCase
{
    #[Test]
    public function coverImageIsNullWithoutMedia(): void
    {
        self::assertNull((new Trick())->getCoverImage());
    }

    #[Test]
    public function coverImageIgnoresVideos(): void
    {
        $trick = (new Trick())->addMedia($this->media(MediaType::Video, 'https://youtu.be/abc', main: true));

        self::assertNull($trick->getCoverImage());
    }

    #[Test]
    public function coverImageIsTheMainImage(): void
    {
        $main = $this->media(MediaType::Image, 'main.jpg', main: true);
        $trick = (new Trick())
            ->addMedia($this->media(MediaType::Image, 'first.jpg'))
            ->addMedia($main);

        self::assertSame($main, $trick->getCoverImage());
    }

    #[Test]
    public function coverImageFallsBackToTheFirstImage(): void
    {
        $first = $this->media(MediaType::Image, 'first.jpg');
        $trick = (new Trick())
            ->addMedia($this->media(MediaType::Video, 'https://youtu.be/abc'))
            ->addMedia($first)
            ->addMedia($this->media(MediaType::Image, 'second.jpg'));

        self::assertSame($first, $trick->getCoverImage());
    }

    private function media(MediaType $type, string $source, bool $main = false): Media
    {
        return (new Media())->setType($type)->setSource($source)->setIsMain($main);
    }
}
