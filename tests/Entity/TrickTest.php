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

    #[Test]
    public function itSplitsImagesFromVideosKeepingTheirOrder(): void
    {
        $firstImage = $this->media(MediaType::Image, 'a.jpg');
        $video = $this->media(MediaType::Video, 'https://youtu.be/abc');
        $secondImage = $this->media(MediaType::Image, 'b.jpg');
        $trick = (new Trick())->addMedia($firstImage)->addMedia($video)->addMedia($secondImage);

        self::assertSame([$firstImage, $secondImage], $trick->getImages());
        self::assertSame([$video], $trick->getVideos());
    }

    #[Test]
    public function theMainImageComesFirstAndTheOthersKeepTheirOrder(): void
    {
        $first = $this->media(MediaType::Image, 'a.jpg');
        $main = $this->media(MediaType::Image, 'b.jpg', main: true);
        $last = $this->media(MediaType::Image, 'c.jpg');
        $trick = (new Trick())->addMedia($first)->addMedia($main)->addMedia($last);

        self::assertSame([$main, $first, $last], $trick->getImages());
    }

    private function media(MediaType $type, string $source, bool $main = false): Media
    {
        return (new Media())->setType($type)->setSource($source)->setIsMain($main);
    }
}
