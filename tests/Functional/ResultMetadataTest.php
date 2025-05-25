<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\TestWith;

use function strtolower;

class ResultMetadataTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = Table::editor()
            ->setUnquotedName('result_metadata_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test_int')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('test_int')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert('result_metadata_table', ['test_int' => 1]);
    }

    public function testColumnNameWithResults(): void
    {
        $sql = 'SELECT test_int, test_int AS alternate_name FROM result_metadata_table';

        $result = $this->connection->executeQuery($sql);

        self::assertEquals(2, $result->columnCount());
        // Depending on the platform, field names might have different case than in the SQL
        // query (for instance, Oracle turns unquoted identifiers into upper case).
        self::assertEquals('test_int', strtolower($result->getColumnName(0)));
        self::assertEquals('alternate_name', strtolower($result->getColumnName(1)));
    }

    #[TestWith([2])]
    #[TestWith([-1])]
    public function testColumnNameWithInvalidIndex(int $index): void
    {
        $sql = 'SELECT test_int, test_int AS alternate_name FROM result_metadata_table';

        $result = $this->connection->executeQuery($sql);

        // Consume the result set to avoid issues with unprocessed buffer between tests
        $result->fetchAllAssociative();

        $this->expectException(InvalidColumnIndex::class);

        $result->getColumnName($index);
    }

    public function testColumnNameWithoutResults(): void
    {
        $sql = 'SELECT test_int, test_int AS alternate_name FROM result_metadata_table WHERE 1 = 0';

        $result = $this->connection->executeQuery($sql);

        self::assertEquals(2, $result->columnCount());
        self::assertEquals('test_int', strtolower($result->getColumnName(0)));
        self::assertEquals('alternate_name', strtolower($result->getColumnName(1)));
    }
}
