<?php

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;
use Doctrine\DBAL\Types\Types;

use function strpos;
use function substr;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(
            new MySQLPlatform(),
            new class implements CollationMetadataProvider {
                public function getCollationCharset(string $collation): ?string
                {
                    return null;
                }
            },
        );
    }

    public function testImplicitTableOptionsMatchingActualTableOptions(): void
    {
        $comparator = $this->createComparatorWithImplicitTableOptions('utf8mb4', 'utf8mb4_unicode_ci');

        $actualTable  = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');
        $desiredTable = $this->createDesiredTable();

        self::assertFalse($comparator->diffTable($actualTable, $desiredTable));
        self::assertFalse($comparator->diffTable($desiredTable, $actualTable));
        self::assertTrue($comparator->compareTables($actualTable, $desiredTable)->isEmpty());
        self::assertTrue($comparator->compareTables($desiredTable, $actualTable)->isEmpty());
    }

    public function testImplicitTableOptionsResolvedToDifferentCharset(): void
    {
        $comparator = $this->createComparatorWithImplicitTableOptions('utf8mb3', 'utf8mb3_unicode_ci');

        $actualTable  = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');
        $desiredTable = $this->createDesiredTable();

        self::assertNotFalse($comparator->diffTable($actualTable, $desiredTable));
        self::assertNotFalse($comparator->diffTable($desiredTable, $actualTable));
    }

    /** @dataProvider differentTableOptionsProvider */
    public function testImplicitTableOptionsDifferentFromActualTableOptions(string $charset, string $collation): void
    {
        $comparator = $this->createComparatorWithImplicitTableOptions('utf8mb4', 'utf8mb4_unicode_ci');

        $actualTable = new Table('comparator_test', [], [], [], [], [
            'charset' => $charset,
            'collation' => $collation,
        ]);
        $actualTable->addColumn('name', Types::STRING, ['length' => 32]);

        $desiredTable = $this->createDesiredTable();

        self::assertNotFalse($comparator->diffTable($actualTable, $desiredTable));
        self::assertNotFalse($comparator->diffTable($desiredTable, $actualTable));
    }

    /** @return iterable<string,array{string,string}> */
    public static function differentTableOptionsProvider(): iterable
    {
        yield 'charset' => ['latin1', 'latin1_swedish_ci'];
        yield 'collation' => ['utf8mb4', 'utf8mb4_general_ci'];
    }

    public function testImplicitTableOptionsColumnCollationChange(): void
    {
        $comparator = $this->createComparatorWithImplicitTableOptions('utf8mb4', 'utf8mb4_unicode_ci');

        $actualTable = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');
        $actualTable->getColumn('name')->setPlatformOptions([
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_bin',
        ]);

        $desiredTable = $this->createDesiredTable();

        self::assertNotFalse($comparator->diffTable($actualTable, $desiredTable));
        self::assertNotFalse($comparator->diffTable($desiredTable, $actualTable));
    }

    public function testImplicitTableOptionsPreserveCharsetOnlyColumn(): void
    {
        $comparator = $this->createComparatorWithImplicitTableOptions('utf8mb4', 'utf8mb4_unicode_ci');

        $actualTable = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');

        $desiredTable = new Table('comparator_test');
        $desiredTable->addColumn('name', Types::STRING, [
            'length' => 32,
            'platformOptions' => ['charset' => 'utf8mb4'],
        ]);

        self::assertNotFalse($comparator->diffTable($actualTable, $desiredTable));
        self::assertNotFalse($comparator->diffTable($desiredTable, $actualTable));
    }

    /** @dataProvider explicitTableOptionsProvider */
    public function testExplicitTableOptionsAreNotOverridden(string $name, string $value): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())
            ->method('fetchNumeric');

        $comparator = new Comparator(new MySQLPlatform(), $this->createCollationMetadataProvider(), $connection);

        $actualTable  = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');
        $desiredTable = $this->createDesiredTable();
        $desiredTable->addOption($name, $value);

        self::assertNotFalse($comparator->diffTable($actualTable, $desiredTable));
    }

    /** @return iterable<string,array{string,string}> */
    public static function explicitTableOptionsProvider(): iterable
    {
        yield 'collate' => ['collate', 'utf8mb4_general_ci'];
        yield 'table_options' => ['table_options', 'ENGINE = InnoDB'];
    }

    public function testImplicitTableOptionsWithoutConnection(): void
    {
        $comparator = new Comparator(new MySQLPlatform(), $this->createCollationMetadataProvider());

        $actualTable  = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');
        $desiredTable = $this->createDesiredTable();

        self::assertNotFalse($comparator->diffTable($actualTable, $desiredTable));
    }

    public function testImplicitTableOptionsAreResolvedOnce(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchNumeric')
            ->willReturn(['utf8mb4', 'utf8mb4_unicode_ci']);

        $comparator = new Comparator(new MySQLPlatform(), $this->createCollationMetadataProvider(), $connection);

        $actualTable  = $this->createActualTable('utf8mb4', 'utf8mb4_unicode_ci');
        $desiredTable = $this->createDesiredTable();

        self::assertFalse($comparator->diffTable($actualTable, $desiredTable));
        self::assertFalse($comparator->diffTable($desiredTable, $actualTable));
    }

    private function createComparatorWithImplicitTableOptions(string $charset, string $collation): Comparator
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchNumeric')
            ->willReturn([$charset, $collation]);

        return new Comparator(new MySQLPlatform(), $this->createCollationMetadataProvider(), $connection);
    }

    private function createCollationMetadataProvider(): CollationMetadataProvider
    {
        return new class implements CollationMetadataProvider {
            public function getCollationCharset(string $collation): ?string
            {
                $position = strpos($collation, '_');

                if ($position === false) {
                    return null;
                }

                return substr($collation, 0, $position);
            }
        };
    }

    /**
     * Creates a table as it would be introspected from a database.
     */
    private function createActualTable(string $charset, string $collation): Table
    {
        $table = new Table('comparator_test', [], [], [], [], [
            'charset' => $charset,
            'collation' => $collation,
        ]);

        $table->addColumn('name', Types::STRING, [
            'length' => 32,
            'platformOptions' => [
                'charset' => $charset,
                'collation' => $collation,
            ],
        ]);

        return $table;
    }

    /**
     * Creates a table without table options with a column that has explicit collation.
     */
    private function createDesiredTable(): Table
    {
        $table = new Table('comparator_test');
        $table->addColumn('name', Types::STRING, [
            'length' => 32,
            'platformOptions' => ['collation' => 'utf8mb4_unicode_ci'],
        ]);

        return $table;
    }
}
