<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function deg2rad;

final class RadiansExpressionTest extends FunctionalTestCase
{
    #[DataProvider('degreesProvider')]
    public function testRadiansExpression(string $degrees, float $expectedRadians): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($platform->getRadiansExpression($degrees));

        self::assertEqualsWithDelta($expectedRadians, (float) $this->connection->fetchOne($query), 1e-9);
    }

    /** @return iterable<string, array{string, float}> */
    public static function degreesProvider(): iterable
    {
        yield 'integer' => ['180', deg2rad(180)];
        yield 'decimal' => ['22.5', deg2rad(22.5)];
        yield 'negative' => ['-90', deg2rad(-90)];
        yield 'arithmetic expression' => ['45 + 45', deg2rad(90)];
    }
}
