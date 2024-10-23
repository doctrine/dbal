<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\Attributes\DataProvider;

class DBAL6146Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_mysql', 'mysqli')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_mysql or the mysqli driver.');
    }

    /** @return iterable<array{array<string,string>,array<string,string>}> */
    public static function equivalentCharsetAndCollationProvider(): iterable
    {
        yield [[], []];
        yield [['charset' => 'utf8'], ['charset' => 'utf8']];
        yield [['charset' => 'utf8'], ['charset' => 'utf8mb3']];
        yield [['charset' => 'utf8mb3'], ['charset' => 'utf8']];
        yield [['charset' => 'utf8mb3'], ['charset' => 'utf8mb3']];
        yield [['collation' => 'utf8_unicode_ci'], ['collation' => 'utf8_unicode_ci']];
        yield [['collation' => 'utf8_unicode_ci'], ['collation' => 'utf8mb3_unicode_ci']];
        yield [['collation' => 'utf8mb3_unicode_ci'], ['collation' => 'utf8mb3_unicode_ci']];
        yield [['collation' => 'utf8mb3_unicode_ci'], ['collation' => 'utf8_unicode_ci']];
        yield [
            ['charset' => 'utf8', 'collation' => 'utf8_unicode_ci'],
            ['charset' => 'utf8', 'collation' => 'utf8_unicode_ci'],
        ];

        yield [
            ['charset' => 'utf8', 'collation' => 'utf8_unicode_ci'],
            ['charset' => 'utf8mb3', 'collation' => 'utf8mb3_unicode_ci'],
        ];

        yield [
            ['charset' => 'utf8mb3', 'collation' => 'utf8mb3_unicode_ci'],
            ['charset' => 'utf8', 'collation' => 'utf8_unicode_ci'],
        ];

        yield [
            ['charset' => 'utf8mb3', 'collation' => 'utf8mb3_unicode_ci'],
            ['charset' => 'utf8mb3', 'collation' => 'utf8mb3_unicode_ci'],
        ];
    }

    /**
     * @param array<string,string> $options1
     * @param array<string,string> $options2
     */
    #[DataProvider('equivalentCharsetAndCollationProvider')]
    public function testThereAreNoRedundantAlterTableStatements(array $options1, array $options2): void
    {
        $column1 = new Column('bar', new StringType(), ['length' => 32, 'platformOptions' => $options1]);
        $table1  = new Table(name: 'foo6146', columns: [$column1]);

        $column2 = new Column('bar', new StringType(), ['length' => 32, 'platformOptions' => $options2]);
        $table2  = new Table(name: 'foo6146', columns: [$column2]);

        $this->dropAndCreateTable($table1);

        $schemaManager = $this->connection->createSchemaManager();
        $oldSchema     = $schemaManager->introspectSchema();
        $newSchema     = new Schema([$table2]);
        $comparator    = $schemaManager->createComparator();
        $schemaDiff    = $comparator->compareSchemas($oldSchema, $newSchema);
        $alteredTables = $schemaDiff->getAlteredTables();

        self::assertEmpty($alteredTables);
    }
}
