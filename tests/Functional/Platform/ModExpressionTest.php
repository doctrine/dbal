<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Tests\FunctionalTestCase;

final class ModExpressionTest extends FunctionalTestCase
{
    public function testModExpression(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($platform->getModExpression('5', '2'));

        self::assertEquals('1', $this->connection->fetchOne($query));
    }
}
