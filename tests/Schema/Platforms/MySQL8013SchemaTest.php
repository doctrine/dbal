<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQL8013Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class MySQL8013SchemaTest extends TestCase
{
    public function testGenerateFunctionalIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('foo_id', 'integer');
        $table->addIndex(['foo_id', '(CAST(bar AS CHAR(10)))'], 'idx_foo_id');

        $platform = new MySQL8013Platform();

        $sqls = [];
        foreach ($table->getIndexes() as $index) {
            $sqls[] = $platform->getCreateIndexSQL(
                $index,
                $table->getQuotedName($platform),
            );
        }

        self::assertEquals(
            ['CREATE INDEX idx_foo_id ON test (foo_id, (CAST(bar AS CHAR(10))))'],
            $sqls,
        );
    }

    public function testGenerateFunctionalIndexWithError(): void
    {
        $table = new Table('test');
        $table->addColumn('foo_id', 'integer');
        $table->addIndex(['foo_id', '(CAST(bar AS CHAR(10)))'], 'idx_foo_id');

        $platform = new MySQL80Platform();

        foreach ($table->getIndexes() as $index) {
            $this->expectException(Exception::class);

            $platform->getCreateIndexSQL(
                $index,
                $table->getQuotedName($platform),
            );
        }
    }
}
