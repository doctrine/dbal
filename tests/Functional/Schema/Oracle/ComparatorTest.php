<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\Oracle;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class ComparatorTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestSkipped('This test covers Oracle-specific schema comparison scenarios.');
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    /**
     * Oracle does not support fixed length binary columns. The DBAL will map the {@see Types::BINARY} type
     * to the variable-length RAW column type regardless of the "fixed" attribute.
     *
     * There should not be a diff when comparing a variable-length and a fixed-length column
     * that are otherwise the same.
     *
     * @see OraclePlatform::getBinaryTypeDeclarationSQLSnippet()
     */
    public function testChangeBinaryColumnFixed(): void
    {
        $table  = new Table('comparator_test');
        $column = $table->addColumn('id', Types::BINARY, [
            'length' => 32,
            'fixed' => true,
        ]);
        $this->dropAndCreateTable($table);

        $column->setFixed(false);

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());
    }

    public function testEnumDiffDetected(): void
    {
        $table = new Table('enum_test_table');

        $table->addColumn('enum_col', Types::ENUM, ['members' => ['a', 'b']]);
        $this->dropAndCreateTable($table);

        ComparatorTestUtils::assertNoDiffDetected($this->connection, $this->comparator, $table);

        // Alter column to previous state and check diff
        $sql = 'ALTER TABLE enum_test_table CHANGE enum_col enum_col ENUM(\'NOT_A_MEMBER_ANYMORE\') NOT NULL';
        $this->connection->executeStatement($sql);

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }
}
