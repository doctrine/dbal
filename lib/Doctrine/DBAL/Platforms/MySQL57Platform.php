<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 5.7 (5.7.9 GA) database platform.
 *
 * @author İsmail BASKIN <ismailbaskin1@gmail.com>
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class MySQL57Platform extends MySqlPlatform
{
    /**
     * {@inheritdoc}
     */
    public function hasNativeJsonType()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $field)
    {
        return 'JSON';
    }

    /**
     * {@inheritdoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPostAlterTableRenameIndexForeignKeySQL(TableDiff $diff)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        return array(
            'ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\MySQL57Keywords';
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['json'] = 'json_array';
    }
}
