<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Override;

/**
 * SQLite SchemaManager.
 *
 * @extends AbstractSchemaManager<SQLitePlatform>
 */
class SQLiteSchemaManager extends AbstractSchemaManager
{
    #[Override]
    public function createForeignKey(ForeignKeyConstraint $foreignKey, string $tableName): void
    {
        $tableName = $this->introspectTableByStringName($tableName);

        $this->alterTable(new TableDiff($tableName, addedForeignKeys: [$foreignKey]));
    }

    #[Override]
    public function dropForeignKey(string $constraintName, string $tableName): void
    {
        $parsedConstraintName = $this->parseUnqualifiedName($constraintName);

        $tableName = $this->introspectTableByStringName($tableName);

        $this->alterTable(new TableDiff($tableName, droppedForeignKeyConstraintNames: [$parsedConstraintName]));
    }

    /** @throws Exception */
    private function introspectTableByStringName(string $tableName): Table
    {
        $parsedName = $this->parseOptionallyQualifiedName($tableName);

        return $this->introspectTable($parsedName);
    }

    #[Override]
    public function createComparator(ComparatorConfig $config = new ComparatorConfig()): Comparator
    {
        return new SQLite\Comparator($this->platform, $config);
    }
}
