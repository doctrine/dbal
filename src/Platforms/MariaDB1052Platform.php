<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.5 database platform.
 */
class MariaDB1052Platform extends MariaDBPlatform
{
    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        return AbstractMySQLPlatform::getPreAlterTableRenameIndexForeignKeySQL($diff);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        return AbstractMySQLPlatform::getPostAlterTableIndexForeignKeySQL($diff);
    }

    /**
     * {@inheritDoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, $tableName): array
    {
        return ['ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)];
    }
}
