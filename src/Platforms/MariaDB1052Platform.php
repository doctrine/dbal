<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.5 (10.5.2 GA) database platform.
 *
 * Note: Should not be used with versions prior to 10.5.2.
 */
class MariaDB1052Platform extends MariaDBPlatform
{
    /**
     * {@inheritdoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        return AbstractMySQLPlatform::getPreAlterTableRenameIndexForeignKeySQL($diff);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        return AbstractMySQLPlatform::getPostAlterTableIndexForeignKeySQL($diff);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, $tableName): array
    {
        return ['ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)];
    }
}
