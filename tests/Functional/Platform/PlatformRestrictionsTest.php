<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function str_repeat;

/**
 * This class holds tests that make sure generated SQL statements respect to platform restrictions
 * like maximum element name length
 */
class PlatformRestrictionsTest extends FunctionalTestCase
{
    /**
     * Tests element names that are at the boundary of the identifier length limit.
     * Ensures generated auto-increment identifier name respects to platform restrictions.
     */
    public function testMaxIdentifierLengthLimitWithAutoIncrement(): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $tableName  = str_repeat('x', $platform->getMaxIdentifierLength());
        $columnName = str_repeat('y', $platform->getMaxIdentifierLength());

        $primaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames($columnName)
            ->create();

        $table = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName($columnName)
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint($primaryKeyConstraint)
            ->create();

        $this->dropAndCreateTable($table);
        $createdTable = $this->connection->createSchemaManager()->introspectTableByUnquotedName($tableName);

        self::assertTrue($createdTable->hasColumn($columnName));
        self::assertPrimaryKeyConstraintEquals($primaryKeyConstraint, $createdTable->getPrimaryKeyConstraint());
    }
}
