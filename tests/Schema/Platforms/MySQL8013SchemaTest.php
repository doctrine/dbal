<?php

namespace Doctrine\DBAL\Tests\Schema\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQL8013Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class MySQL8013SchemaTest extends TestCase
{
    private MySQL80Platform $platformMysql;
    private MySQL8013Platform $platformMysql8013;

    protected function setUp(): void
    {
        $this->platformMysql8013 = new MySQL8013Platform();
        $this->platformMysql     = new MySQL80Platform();
    }

    public function testGenerateFunctionalIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('foo_id', 'integer');
        $table->addIndex(['foo_id', '(CAST(bar AS CHAR(10)))'], 'idx_foo_id');

        $sqls = [];
        foreach ($table->getIndexes() as $index) {
            $sqls[] = $this->platformMysql8013->getCreateIndexSQL(
                $index,
                $table->getQuotedName($this->platformMysql8013),
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

        foreach ($table->getIndexes() as $index) {
            $this->expectException(Exception::class);

            $this->platformMysql->getCreateIndexSQL(
                $index,
                $table->getQuotedName($this->platformMysql),
            );
        }
    }
}
