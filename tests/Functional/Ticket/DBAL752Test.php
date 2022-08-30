<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;

class DBAL752Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        self::markTestSkipped('Related to SQLite only');
    }

    public function testUnsignedIntegerDetection(): void
    {
        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE dbal752_unsigneds (
    small SMALLINT,
    small_unsigned SMALLINT UNSIGNED,
    medium MEDIUMINT,
    medium_unsigned MEDIUMINT UNSIGNED,
    "integer" INTEGER,
    integer_unsigned INTEGER UNSIGNED,
    big BIGINT,
    big_unsigned BIGINT UNSIGNED
);
SQL);

        $schemaManager = $this->connection->createSchemaManager();

        $fetchedTable = $schemaManager->introspectTable('dbal752_unsigneds');

        self::assertInstanceOf(SmallIntType::class, $fetchedTable->getColumn('small')->getType());
        self::assertInstanceOf(SmallIntType::class, $fetchedTable->getColumn('small_unsigned')->getType());
        self::assertInstanceOf(IntegerType::class, $fetchedTable->getColumn('medium')->getType());
        self::assertInstanceOf(IntegerType::class, $fetchedTable->getColumn('medium_unsigned')->getType());
        self::assertInstanceOf(IntegerType::class, $fetchedTable->getColumn('integer')->getType());
        self::assertInstanceOf(IntegerType::class, $fetchedTable->getColumn('integer_unsigned')->getType());
        self::assertInstanceOf(BigIntType::class, $fetchedTable->getColumn('big')->getType());
        self::assertInstanceOf(BigIntType::class, $fetchedTable->getColumn('big_unsigned')->getType());

        self::assertTrue($fetchedTable->getColumn('small_unsigned')->getUnsigned());
        self::assertTrue($fetchedTable->getColumn('medium_unsigned')->getUnsigned());
        self::assertTrue($fetchedTable->getColumn('integer_unsigned')->getUnsigned());
        self::assertTrue($fetchedTable->getColumn('big_unsigned')->getUnsigned());

        self::assertFalse($fetchedTable->getColumn('small')->getUnsigned());
        self::assertFalse($fetchedTable->getColumn('medium')->getUnsigned());
        self::assertFalse($fetchedTable->getColumn('integer')->getUnsigned());
        self::assertFalse($fetchedTable->getColumn('big')->getUnsigned());
    }
}
