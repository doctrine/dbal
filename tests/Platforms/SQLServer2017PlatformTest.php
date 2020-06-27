<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2017Platform;

class SQLServer2017PlatformTest extends SQLServerPlatform2012Test
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLServer2017Platform();
    }

    public function testGeneratesSqlSnippetsForAggregateConcatExpression(): void
    {
        self::assertEquals('STRING_AGG(column1, \',\')', $this->platform->getAggregateConcatExpression('column1', '\',\''), 'Aggregate concatenation function is not correct');
        self::assertEquals('STRING_AGG(column1, \',\') WITHIN GROUP(ORDER BY column1 DESC)', $this->platform->getAggregateConcatExpression('column1', '\',\'', 'column1 DESC'), 'Aggregate concatenation function with order is not correct');
    }
}
