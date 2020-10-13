<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Platform to ensure compatibility of Doctrine with Microsoft SQL Server 2008 version.
 *
 * Differences to SQL Server 2005 and before are that a new DATETIME2 type was
 * introduced that has a higher precision.
 *
 * @deprecated Use SQL Server 2012 or newer
 */
class SQLServer2008Platform extends SQLServer2005Platform
{
    /**
     * {@inheritdoc}
     */
    public function supportsPartialIndexes()
    {
        // Partial indexes are supported as "filtered indexes" since version 2008.
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL()
    {
        // "sysdiagrams" table must be ignored as it's internal SQL Server table for Database Diagrams
        // Category 2 must be ignored as it is "MS SQL Server 'pseudo-system' object[s]" for replication
        return 'SELECT name, SCHEMA_NAME (uid) AS schema_name FROM sysobjects'
            . " WHERE type = 'U' AND name != 'sysdiagrams' AND category != 2 ORDER BY name";
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column)
    {
        // 3 - microseconds precision length
        // http://msdn.microsoft.com/en-us/library/ms187819.aspx
        return 'DATETIME2(6)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $column)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $column)
    {
        return 'TIME(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column)
    {
        return 'DATETIMEOFFSET(6)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:s.u P';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateFormatString()
    {
        return 'Y-m-d';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString()
    {
        return 'H:i:s';
    }

    /**
     * {@inheritDoc}
     *
     * Adding Datetime2 Type
     */
    protected function initializeDoctrineTypeMappings()
    {
        parent::initializeDoctrineTypeMappings();
        $this->doctrineTypeMapping['datetime2']      = 'datetime';
        $this->doctrineTypeMapping['date']           = 'date';
        $this->doctrineTypeMapping['time']           = 'time';
        $this->doctrineTypeMapping['datetimeoffset'] = 'datetimetz';
    }

    /**
     * {@inheritdoc}
     *
     * Returns Microsoft SQL Server 2008 specific keywords class
     */
    protected function getReservedKeywordsClass()
    {
        return Keywords\SQLServer2008Keywords::class;
    }

    protected function getLikeWildcardCharacters(): string
    {
        return parent::getLikeWildcardCharacters() . '[]^';
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableIndexesSQL($table, $database = null)
    {
        return "SELECT idx.name AS key_name,
                       col.name AS column_name,
                       ~idx.is_unique AS non_unique,
                       idx.is_primary_key AS [primary],
                       CASE idx.type
                           WHEN '1' THEN 'clustered'
                           WHEN '2' THEN 'nonclustered'
                           ELSE NULL
                       END AS flags,
                       idx.filter_definition AS [where]
                FROM sys.tables AS tbl
                JOIN sys.schemas AS scm ON tbl.schema_id = scm.schema_id
                JOIN sys.indexes AS idx ON tbl.object_id = idx.object_id
                JOIN sys.index_columns AS idxcol ON idx.object_id = idxcol.object_id AND idx.index_id = idxcol.index_id
                JOIN sys.columns AS col ON idxcol.object_id = col.object_id AND idxcol.column_id = col.column_id
                WHERE " . $this->getTableWhereClause($table, 'scm.name', 'tbl.name') . '
                ORDER BY idx.index_id ASC, idxcol.key_ordinal ASC';
    }
}
