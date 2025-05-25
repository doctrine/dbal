<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

class MariaDBJsonComparatorTest extends TestCase
{
    protected Comparator $comparator;

    /** @var Table[] */
    private array $tables = [];

    protected function setUp(): void
    {
        $this->comparator = new Comparator(
            new MariaDBPlatform(),
            new class implements CharsetMetadataProvider {
                public function getDefaultCharsetCollation(string $charset): ?string
                {
                    return null;
                }
            },
            new class implements CollationMetadataProvider {
                public function getCollationCharset(string $collation): ?string
                {
                    return null;
                }
            },
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
            new ComparatorConfig(),
        );

        // TableA has collation set at table level and various column collations
        $this->tables['A'] = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('json_1')
                    ->setTypeName(Types::JSON)
                    ->setCollation('latin1_swedish_ci')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_2')
                    ->setTypeName(Types::JSON)
                    ->setCollation('utf8_general_ci')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_3')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->setOptions([
                'charset' => 'latin1',
                'collation' => 'latin1_swedish_ci',
            ])
            ->create();

        // TableB has no table-level collation and various column collations
        $this->tables['B'] = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('json_1')
                    ->setTypeName(Types::JSON)
                    ->setCollation('latin1_swedish_ci')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_2')
                    ->setTypeName(Types::JSON)
                    ->setCollation('utf8_general_ci')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_3')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->create();

        // Table C has no table-level collation and column collations as MariaDb would return for columns declared
        // as JSON
        $this->tables['C'] = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('json_1')
                    ->setTypeName(Types::JSON)
                    ->setCollation('utf8mb4_bin')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_2')
                    ->setTypeName(Types::JSON)
                    ->setCollation('utf8mb4_bin')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_3')
                    ->setTypeName(Types::JSON)
                    ->setCollation('utf8mb4_bin')
                    ->create(),
            )
            ->create();

        // Table D has no table or column collations set
        $this->tables['D'] = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('json_1')
                    ->setTypeName(Types::JSON)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_2')
                    ->setTypeName(Types::JSON)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('json_3')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->create();
    }

    /** @return array{string, string}[] */
    public static function providerTableComparisons(): iterable
    {
        return [
            ['A', 'B'],
            ['A', 'C'],
            ['A', 'D'],
            ['B', 'C'],
            ['B', 'D'],
            ['C', 'D'],
        ];
    }

    #[DataProvider('providerTableComparisons')]
    public function testJsonColumnComparison(string $table1, string $table2): void
    {
        self::assertTrue(
            $this->comparator->compareTables($this->tables[$table1], $this->tables[$table2])->isEmpty(),
            sprintf('Tables %s and %s should be identical', $table1, $table2),
        );
    }
}
