<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class ComparatorTest extends FunctionalTestCase
{
    private AbstractPlatform $platform;

    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->connection->getDatabasePlatform();

        if (! $this->platform instanceof MySQLPlatform) {
            self::markTestSkipped();
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    /**
     * @dataProvider lobColumnProvider
     */
    public function testLobLengthIncrementWithinLimit(string $type, int $length): void
    {
        $table = $this->createLobTable($type, $length - 1);
        $this->increaseLobLength($table);

        self::assertNull(ComparatorTestUtils::diffOnlineAndOfflineTable(
            $this->schemaManager,
            $this->comparator,
            $table
        ));
    }

    /**
     * @dataProvider lobColumnProvider
     */
    public function testLobLengthIncrementOverLimit(string $type, int $length): void
    {
        $table = $this->createLobTable($type, $length);
        $this->increaseLobLength($table);
        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    /**
     * @return iterable<array{string,int}>
     */
    public static function lobColumnProvider(): iterable
    {
        yield [Types::BLOB, MySQLPlatform::LENGTH_LIMIT_TINYBLOB];
        yield [Types::BLOB, MySQLPlatform::LENGTH_LIMIT_BLOB];
        yield [Types::BLOB, MySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB];

        yield [Types::TEXT, MySQLPlatform::LENGTH_LIMIT_TINYTEXT];
        yield [Types::TEXT, MySQLPlatform::LENGTH_LIMIT_TEXT];
        yield [Types::TEXT, MySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT];
    }

    /**
     * @throws Exception
     */
    private function createLobTable(string $type, int $length): Table
    {
        $table = new Table('comparator_test');
        $table->addColumn('lob', $type)->setLength($length);

        $this->schemaManager->dropAndCreateTable($table);

        return $table;
    }

    /**
     * @throws Exception
     */
    private function increaseLobLength(Table $table): void
    {
        $column = $table->getColumn('lob');
        $length = $column->getLength();
        self::assertNotNull($length);
        $column->setLength($length + 1);
    }

    public function testExplicitDefaultCollation(): void
    {
        [$table, $column] = $this->createCollationTable();
        $column->setPlatformOption('collation', 'utf8mb4_general_ci');

        self::assertNull(ComparatorTestUtils::diffOnlineAndOfflineTable(
            $this->schemaManager,
            $this->comparator,
            $table
        ));
    }

    public function testChangeColumnCharsetAndCollation(): void
    {
        [$table, $column] = $this->createCollationTable();
        $column->setPlatformOption('charset', 'utf8');
        $column->setPlatformOption('collation', 'utf8_bin');

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    public function testChangeColumnCollation(): void
    {
        [$table, $column] = $this->createCollationTable();
        $column->setPlatformOption('collation', 'utf8mb4_bin');

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    /**
     * @return array{Table,Column}
     *
     * @throws Exception
     */
    private function createCollationTable(): array
    {
        $table = new Table('comparator_test');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_general_ci');
        $column = $table->addColumn('id', Types::STRING, ['length' => 32]);
        $this->schemaManager->dropAndCreateTable($table);

        return [$table, $column];
    }
}
