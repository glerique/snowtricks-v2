<?php

declare(strict_types=1);

namespace App\Tests\Service\Trick;

use App\Entity\Trick;
use App\Factory\TrickFactory;
use App\Service\Trick\UpdateTrick;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class UpdateTrickTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function itSavesTheChangesAndFollowsTheNewNameWithTheSlug(): void
    {
        $trick = TrickFactory::createOne(['name' => 'Mute Grab']);
        $trick->setName('Indy Grab');

        self::getContainer()->get(UpdateTrick::class)($trick);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(Trick::class)->find($trick->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Indy Grab', $reloaded->getName());
        self::assertSame('indy-grab', $reloaded->getSlug());
    }
}
