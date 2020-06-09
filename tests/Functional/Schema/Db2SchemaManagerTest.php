<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

class Db2SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function testListTableWithBinary(): void
    {
        self::markTestSkipped('Binary data type is currently not supported on DB2 LUW');
    }
}
