<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Index;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index\BoundingBox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use const INF;
use const NAN;

class BoundingBoxTest extends TestCase
{
    public function testGetters(): void
    {
        $boundingBox = new BoundingBox(-180, -90.5, 180, 90);

        self::assertSame(-180.0, $boundingBox->getMinX());
        self::assertSame(-90.5, $boundingBox->getMinY());
        self::assertSame(180.0, $boundingBox->getMaxX());
        self::assertSame(90.0, $boundingBox->getMaxY());
    }

    #[TestWith([10, 0, 10, 10])]
    #[TestWith([0, 10, 10, 5])]
    #[TestWith([NAN, 0, 10, 10])]
    #[TestWith([0, -INF, 10, 10])]
    #[TestWith([0, 0, 10, INF])]
    public function testInvalidBoundingBox(float $minX, float $minY, float $maxX, float $maxY): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        new BoundingBox($minX, $minY, $maxX, $maxY);
    }
}
