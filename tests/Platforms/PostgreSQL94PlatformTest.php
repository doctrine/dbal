<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class PostgreSQL94PlatformTest extends AbstractPostgreSQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new PostgreSQL94Platform();
    }

    public function testSupportsPartialIndexes(): void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    public function testGetCreateTableSQLWithColumnCollation(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', 'string');
        $table->addOption('comment', 'foo');
        self::assertSame(
            [
                'CREATE TABLE foo (id VARCHAR(255) NOT NULL)',
                "COMMENT ON TABLE foo IS 'foo'",
            ],
            $this->platform->getCreateTableSQL($table),
            'Comments are added to table.'
        );
    }

    public function testColumnCollationDeclarationSQL(): void
    {
        self::assertEquals(
            'COLLATE "en_US.UTF-8"',
            $this->platform->getColumnCollationDeclarationSQL('en_US.UTF-8')
        );
    }

    public function testHasNativeJsonType(): void
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => false]));
        self::assertSame('JSONB', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => true]));
    }

    public function testReturnsSmallIntTypeDeclarationSQL(): void
    {
        self::assertSame(
            'SMALLSERIAL',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => true])
        );

        self::assertSame(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => false])
        );

        self::assertSame(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL([])
        );
    }

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('jsonb'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('jsonb'));
    }

    /**
     * @dataProvider getDefaultValueDeclarationSQLDateTimeWithExpressions
     */
    public function testGetDefaultValueDeclarationSQLDateTimeWithExpressions(
        string $expression,
        bool $shouldBeQuoted
    ): void {
        foreach (['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable'] as $type) {
            $value          = $shouldBeQuoted ? $this->platform->quoteStringLiteral($expression) : $expression;
            $expectedOutput = ' DEFAULT ' . $value;

            self::assertSame(
                $expectedOutput,
                $this->platform->getDefaultValueDeclarationSQL([
                    'type'    => Type::getType($type),
                    'default' => $expression,
                ])
            );
        }
    }

    /**
     * @return mixed[][]
     */
    public function getDefaultValueDeclarationSQLDateTimeWithExpressions(): array
    {
        return [
            ['clock_timestamp()', false],
            ['current_timestamp', false],
            ['current_timestamp()', false],
            ['localtimestamp', false],
            ['localtimestamp()', false],
            ['now()', false],
            ['statement_timestamp()', false],
            ['transaction_timestamp()', false],
            ['CURRENT_TIMESTAMP', false],
            ['CURRENT_TIMESTAMP()', false],
            ['UNSUPPORTED_FUNCTION', true],
            ['UNSUPPORTED_FUNCTION()', true],
        ];
    }
}
