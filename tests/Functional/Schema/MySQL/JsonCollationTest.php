<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_filter;

/**
 * Tests that character set and collation are ignored for columns declared as native JSON in MySQL and
 * MariaDb and cannot be changed.
 */
final class JsonCollationTest extends FunctionalTestCase
{
    private AbstractPlatform $platform;

    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    protected function setUp(): void
    {
        $this->platform = $this->connection->getDatabasePlatform();

        if (! $this->platform instanceof MariaDBPlatform) {
            self::markTestSkipped();
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    /**
     * Generates a number of tables comprising only json columns. The tables are identical but for character
     * set and collation.
     *
     * @return Iterator<array{Table}>
     */
    public static function tableProvider(): iterable
    {
        $tables = [
            [
                'name' => 'mariadb_json_column_comparator_test',
                'columns' => [
                    ['name' => 'json_1', 'charset' => 'latin1', 'collation' => 'latin1_swedish_ci'],
                    ['name' => 'json_2', 'charset' => 'utf8', 'collation' => 'utf8_general_ci'],
                    ['name' => 'json_3'],
                ],
                'charset' => 'latin1',
                'collation' => 'latin1_swedish_ci',
            ],
            [
                'name' => 'mariadb_json_column_comparator_test',
                'columns' => [
                    ['name' => 'json_1', 'charset' => 'latin1', 'collation' => 'latin1_swedish_ci'],
                    ['name' => 'json_2', 'charset' => 'utf8', 'collation' => 'utf8_general_ci'],
                    ['name' => 'json_3'],
                ],
            ],
            [
                'name' => 'mariadb_json_column_comparator_test',
                'columns' => [
                    ['name' => 'json_1', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'],
                    ['name' => 'json_2', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'],
                    ['name' => 'json_3', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_general_ci'],
                ],
            ],
            [
                'name' => 'mariadb_json_column_comparator_test',
                'columns' => [
                    ['name' => 'json_1'],
                    ['name' => 'json_2'],
                    ['name' => 'json_3'],
                ],
            ],
        ];

        foreach ($tables as $table) {
            yield [self::setUpTable(
                $table['name'],
                $table['columns'],
                $table['charset'] ?? null,
                $table['collation'] ?? null,
            ),
            ];
        }
    }

    /** @param array{name: string, type?: string, charset?: string, collation?: string}[] $columns */
    private static function setUpTable(
        string $name,
        array $columns,
        ?string $charset = null,
        ?string $collation = null,
    ): Table {
        $tableOptions = array_filter(['charset' => $charset, 'collation' => $collation]);

        $table = new Table($name, [], [], [], [], $tableOptions);

        foreach ($columns as $column) {
            if (! isset($column['charset']) || ! isset($column['collation'])) {
                $table->addColumn($column['name'], $column['type'] ?? Types::JSON);
            } else {
                $table->addColumn($column['name'], $column['type'] ?? Types::JSON)
                      ->setPlatformOption('charset', $column['charset'])
                      ->setPlatformOption('collation', $column['collation']);
            }
        }

        return $table;
    }

    #[DataProvider('tableProvider')]
    public function testJsonColumnComparison(Table $table): void
    {
        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('mariadb_json_column_comparator_test');
        $diff        = $this->comparator->compareTables($table, $onlineTable);

        self::assertTrue($diff->isEmpty(), 'Tables should be identical.');

        $originalTable = clone $table;

        $table->getColumn('json_1')
              ->setPlatformOption('charset', 'utf8')
              ->setPlatformOption('collation', 'utf8_general_ci');

        $diff = $this->comparator->compareTables($table, $onlineTable);
        self::assertTrue($diff->isEmpty(), 'Tables should be unchanged after attempted collation change.');

        $diff = $this->comparator->compareTables($table, $originalTable);
        self::assertTrue($diff->isEmpty(), 'Tables should be unchanged after attempted collation change.');
    }
}
