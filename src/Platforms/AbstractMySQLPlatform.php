<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQLKeywords;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;

use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_numeric;
use function sprintf;
use function str_replace;
use function strcasecmp;
use function strtoupper;
use function trim;

/**
 * Provides the base implementation for the lowest versions of supported MySQL-like database platforms.
 */
abstract class AbstractMySQLPlatform extends AbstractPlatform
{
    public const LENGTH_LIMIT_TINYTEXT   = 255;
    public const LENGTH_LIMIT_TEXT       = 65535;
    public const LENGTH_LIMIT_MEDIUMTEXT = 16777215;

    public const LENGTH_LIMIT_TINYBLOB   = 255;
    public const LENGTH_LIMIT_BLOB       = 65535;
    public const LENGTH_LIMIT_MEDIUMBLOB = 16777215;

    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        if ($limit !== null) {
            $query .= sprintf(' LIMIT %d', $limit);

            if ($offset > 0) {
                $query .= sprintf(' OFFSET %d', $offset);
            }
        } elseif ($offset > 0) {
            // 2^64-1 is the maximum of unsigned BIGINT, the biggest limit possible
            $query .= sprintf(' LIMIT 18446744073709551615 OFFSET %d', $offset);
        }

        return $query;
    }

    public function getIdentifierQuoteCharacter(): string
    {
        return '`';
    }

    public function getRegexpExpression(): string
    {
        return 'RLIKE';
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('LOCATE(%s, %s)', $substring, $string);
        }

        return sprintf('LOCATE(%s, %s, %s)', $substring, $string, $start);
    }

    public function getConcatExpression(string ...$string): string
    {
        return sprintf('CONCAT(%s)', implode(', ', $string));
    }

    protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        string $unit
    ): string {
        $function = $operator === '+' ? 'DATE_ADD' : 'DATE_SUB';

        return $function . '(' . $date . ', INTERVAL ' . $interval . ' ' . $unit . ')';
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return 'DATEDIFF(' . $date1 . ', ' . $date2 . ')';
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'DATABASE()';
    }

    public function getLengthExpression(string $string): string
    {
        return 'CHAR_LENGTH(' . $string . ')';
    }

    public function getListDatabasesSQL(): string
    {
        return 'SHOW DATABASES';
    }

    public function getListViewsSQL(string $database): string
    {
        return 'SELECT * FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ' . $this->quoteStringLiteral($database);
    }

    /**
     * Gets the SQL snippet used to declare a CLOB column type.
     *     TINYTEXT   : 2 ^  8 - 1 = 255
     *     TEXT       : 2 ^ 16 - 1 = 65535
     *     MEDIUMTEXT : 2 ^ 24 - 1 = 16777215
     *     LONGTEXT   : 2 ^ 32 - 1 = 4294967295
     *
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['length']) && is_numeric($column['length'])) {
            $length = $column['length'];

            if ($length <= static::LENGTH_LIMIT_TINYTEXT) {
                return 'TINYTEXT';
            }

            if ($length <= static::LENGTH_LIMIT_TEXT) {
                return 'TEXT';
            }

            if ($length <= static::LENGTH_LIMIT_MEDIUMTEXT) {
                return 'MEDIUMTEXT';
            }
        }

        return 'LONGTEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        if (isset($column['version']) && $column['version'] === true) {
            return 'TIMESTAMP';
        }

        return 'DATETIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIME';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'TINYINT(1)';
    }

    /**
     * {@inheritDoc}
     *
     * MySQL supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns(): bool
    {
        return true;
    }

    public function supportsInlineColumnComments(): bool
    {
        return true;
    }

    public function supportsColumnCollation(): bool
    {
        return true;
    }

    /**
     * @deprecated The SQL used for schema introspection is an implementation detail and should not be relied upon.
     */
    public function getListTablesSQL(): string
    {
        return "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'";
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($definition);
            }
        }

        // add all indexes
        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $definition) {
                $queryFields .= ', ' . $this->getIndexDeclarationSQL($definition);
            }
        }

        // attach all primary keys
        if (isset($options['primary']) && ! empty($options['primary'])) {
            $keyColumns   = array_unique(array_values($options['primary']));
            $queryFields .= ', PRIMARY KEY(' . implode(', ', $keyColumns) . ')';
        }

        $sql = ['CREATE'];

        if (! empty($options['temporary'])) {
            $sql[] = 'TEMPORARY';
        }

        $sql[] = 'TABLE ' . $name . ' (' . $queryFields . ')';

        $tableOptions = $this->buildTableOptions($options);

        if ($tableOptions !== '') {
            $sql[] = $tableOptions;
        }

        if (isset($options['partition_options'])) {
            $sql[] = $options['partition_options'];
        }

        $sql = [implode(' ', $sql)];

        // Propagate foreign key constraints only for InnoDB.
        if (
            isset($options['foreignKeys'])
            && (! isset($options['engine']) || strcasecmp($options['engine'], 'InnoDB') === 0)
        ) {
            foreach ($options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $name);
            }
        }

        return $sql;
    }

    /**
     * Build SQL for table options
     *
     * @param mixed[] $options
     */
    private function buildTableOptions(array $options): string
    {
        if (isset($options['table_options'])) {
            return $options['table_options'];
        }

        $tableOptions = [];

        if (isset($options['charset'])) {
            $tableOptions[] = sprintf('DEFAULT CHARACTER SET %s', $options['charset']);
        }

        if (isset($options['collation'])) {
            $tableOptions[] = $this->getColumnCollationDeclarationSQL($options['collation']);
        }

        if (isset($options['engine'])) {
            $tableOptions[] = sprintf('ENGINE = %s', $options['engine']);
        }

        // Auto increment
        if (isset($options['auto_increment'])) {
            $tableOptions[] = sprintf('AUTO_INCREMENT = %s', $options['auto_increment']);
        }

        // Comment
        if (isset($options['comment'])) {
            $tableOptions[] = sprintf('COMMENT = %s ', $this->quoteStringLiteral($options['comment']));
        }

        // Row format
        if (isset($options['row_format'])) {
            $tableOptions[] = sprintf('ROW_FORMAT = %s', $options['row_format']);
        }

        return implode(' ', $tableOptions);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $columnSql  = [];
        $queryParts = [];
        $newName    = $diff->getNewName();

        if ($newName !== null) {
            $queryParts[] = 'RENAME TO ' . $newName->getQuotedName($this);
        }

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnArray = array_merge($column->toArray(), [
                'comment' => $column->getComment(),
            ]);

            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] =  'DROP ' . $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $column      = $columnDiff->column;
            $columnArray = $column->toArray();

            $columnArray['comment'] = $column->getComment();
            $queryParts[]           =  'CHANGE ' . ($columnDiff->getOldColumnName()->getQuotedName($this)) . ' '
                . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName          = new Identifier($oldColumnName);
            $columnArray            = $column->toArray();
            $columnArray['comment'] = $column->getComment();
            $queryParts[]           =  'CHANGE ' . $oldColumnName->getQuotedName($this) . ' '
                . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        if (isset($diff->addedIndexes['primary'])) {
            $keyColumns   = array_unique(array_values($diff->addedIndexes['primary']->getColumns()));
            $queryParts[] = 'ADD PRIMARY KEY (' . implode(', ', $keyColumns) . ')';
            unset($diff->addedIndexes['primary']);
        } elseif (isset($diff->changedIndexes['primary'])) {
            // Necessary in case the new primary key includes a new auto_increment column
            foreach ($diff->changedIndexes['primary']->getColumns() as $columnName) {
                if (isset($diff->addedColumns[$columnName]) && $diff->addedColumns[$columnName]->getAutoincrement()) {
                    $keyColumns   = array_unique(array_values($diff->changedIndexes['primary']->getColumns()));
                    $queryParts[] = 'DROP PRIMARY KEY';
                    $queryParts[] = 'ADD PRIMARY KEY (' . implode(', ', $keyColumns) . ')';
                    unset($diff->changedIndexes['primary']);
                    break;
                }
            }
        }

        $sql      = [];
        $tableSql = [];

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            if (count($queryParts) > 0) {
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' '
                    . implode(', ', $queryParts);
            }

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql   = [];
        $table = $diff->getName($this)->getQuotedName($this);

        foreach ($diff->changedIndexes as $changedIndex) {
            $sql = array_merge($sql, $this->getPreAlterTableAlterPrimaryKeySQL($diff, $changedIndex));
        }

        foreach ($diff->removedIndexes as $remKey => $remIndex) {
            $sql = array_merge($sql, $this->getPreAlterTableAlterPrimaryKeySQL($diff, $remIndex));

            foreach ($diff->addedIndexes as $addKey => $addIndex) {
                if ($remIndex->getColumns() !== $addIndex->getColumns()) {
                    continue;
                }

                $indexClause = 'INDEX ' . $addIndex->getName();

                if ($addIndex->isPrimary()) {
                    $indexClause = 'PRIMARY KEY';
                } elseif ($addIndex->isUnique()) {
                    $indexClause = 'UNIQUE INDEX ' . $addIndex->getName();
                }

                $query  = 'ALTER TABLE ' . $table . ' DROP INDEX ' . $remIndex->getName() . ', ';
                $query .= 'ADD ' . $indexClause;
                $query .= ' (' . $this->getIndexFieldDeclarationListSQL($addIndex) . ')';

                $sql[] = $query;

                unset($diff->removedIndexes[$remKey], $diff->addedIndexes[$addKey]);

                break;
            }
        }

        $engine = 'INNODB';

        if ($diff->fromTable instanceof Table && $diff->fromTable->hasOption('engine')) {
            $engine = strtoupper(trim($diff->fromTable->getOption('engine')));
        }

        // Suppress foreign key constraint propagation on non-supporting engines.
        if ($engine !== 'INNODB') {
            $diff->addedForeignKeys   = [];
            $diff->changedForeignKeys = [];
            $diff->removedForeignKeys = [];
        }

        return array_merge(
            $sql,
            $this->getPreAlterTableAlterIndexForeignKeySQL($diff),
            parent::getPreAlterTableIndexForeignKeySQL($diff),
            $this->getPreAlterTableRenameIndexForeignKeySQL($diff)
        );
    }

    /**
     * @return string[]
     *
     * @throws Exception
     */
    private function getPreAlterTableAlterPrimaryKeySQL(TableDiff $diff, Index $index): array
    {
        $sql = [];

        if (! $index->isPrimary() || ! $diff->fromTable instanceof Table) {
            return $sql;
        }

        $tableName = $diff->getName($this)->getQuotedName($this);

        // Dropping primary keys requires to unset autoincrement attribute on the particular column first.
        foreach ($index->getColumns() as $columnName) {
            if (! $diff->fromTable->hasColumn($columnName)) {
                continue;
            }

            $column = $diff->fromTable->getColumn($columnName);

            if (! $column->getAutoincrement()) {
                continue;
            }

            $column->setAutoincrement(false);

            $sql[] = 'ALTER TABLE ' . $tableName . ' MODIFY ' .
                $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());

            // original autoincrement information might be needed later on by other parts of the table alteration
            $column->setAutoincrement(true);
        }

        return $sql;
    }

    /**
     * @param TableDiff $diff The table diff to gather the SQL for.
     *
     * @return string[]
     *
     * @throws Exception
     */
    private function getPreAlterTableAlterIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql   = [];
        $table = $diff->getName($this)->getQuotedName($this);

        foreach ($diff->changedIndexes as $changedIndex) {
            // Changed primary key
            if (! $changedIndex->isPrimary() || ! ($diff->fromTable instanceof Table)) {
                continue;
            }

            foreach ($diff->fromTable->getPrimaryKeyColumns() as $columnName => $column) {
                $column = $diff->fromTable->getColumn($columnName);

                // Check if an autoincrement column was dropped from the primary key.
                if (! $column->getAutoincrement() || in_array($columnName, $changedIndex->getColumns(), true)) {
                    continue;
                }

                // The autoincrement attribute needs to be removed from the dropped column
                // before we can drop and recreate the primary key.
                $column->setAutoincrement(false);

                $sql[] = 'ALTER TABLE ' . $table . ' MODIFY ' .
                    $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());

                // Restore the autoincrement attribute as it might be needed later on
                // by other parts of the table alteration.
                $column->setAutoincrement(true);
            }
        }

        return $sql;
    }

    /**
     * @param TableDiff $diff The table diff to gather the SQL for.
     *
     * @return string[]
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        return [];
    }

    protected function getCreateIndexSQLFlags(Index $index): string
    {
        $type = '';
        if ($index->isUnique()) {
            $type .= 'UNIQUE ';
        } elseif ($index->hasFlag('fulltext')) {
            $type .= 'FULLTEXT ';
        } elseif ($index->hasFlag('spatial')) {
            $type .= 'SPATIAL ';
        }

        return $type;
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return 'SMALLINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function getFloatDeclarationSQL(array $column): string
    {
        return 'DOUBLE PRECISION' . $this->getUnsignedDeclaration($column);
    }

    /**
     * {@inheritdoc}
     */
    public function getDecimalTypeDeclarationSQL(array $column): string
    {
        return parent::getDecimalTypeDeclarationSQL($column) . $this->getUnsignedDeclaration($column);
    }

    /**
     * Get unsigned declaration for a column.
     *
     * @param mixed[] $columnDef
     */
    private function getUnsignedDeclaration(array $columnDef): string
    {
        return ! empty($columnDef['unsigned']) ? ' UNSIGNED' : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        $autoinc = '';
        if (! empty($column['autoincrement'])) {
            $autoinc = ' AUTO_INCREMENT';
        }

        return $this->getUnsignedDeclaration($column) . $autoinc;
    }

    public function getColumnCharsetDeclarationSQL(string $charset): string
    {
        return 'CHARACTER SET ' . $charset;
    }

    public function getColumnCollationDeclarationSQL(string $collation): string
    {
        return 'COLLATE ' . $this->quoteSingleIdentifier($collation);
    }

    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';
        if ($foreignKey->hasOption('match')) {
            $query .= ' MATCH ' . $foreignKey->getOption('match');
        }

        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        return $query;
    }

    public function getDropIndexSQL(string $name, string $table): string
    {
        return 'DROP INDEX ' . $name . ' ON ' . $table;
    }

    /**
     * The `ALTER TABLE ... DROP CONSTRAINT` syntax is only available as of MySQL 8.0.19.
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
     */
    public function getDropUniqueConstraintSQL(string $name, string $tableName): string
    {
        return $this->getDropIndexSQL($name, $tableName);
    }

    public function getSetTransactionIsolationSQL(int $level): string
    {
        return 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    public function getReadLockSQL(): string
    {
        return 'LOCK IN SHARE MODE';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'bigint'     => 'bigint',
            'binary'     => 'binary',
            'blob'       => 'blob',
            'char'       => 'string',
            'date'       => 'date',
            'datetime'   => 'datetime',
            'decimal'    => 'decimal',
            'double'     => 'float',
            'float'      => 'float',
            'int'        => 'integer',
            'integer'    => 'integer',
            'json'       => 'json',
            'longblob'   => 'blob',
            'longtext'   => 'text',
            'mediumblob' => 'blob',
            'mediumint'  => 'integer',
            'mediumtext' => 'text',
            'numeric'    => 'decimal',
            'real'       => 'float',
            'set'        => 'simple_array',
            'smallint'   => 'smallint',
            'string'     => 'string',
            'text'       => 'text',
            'time'       => 'time',
            'timestamp'  => 'datetime',
            'tinyblob'   => 'blob',
            'tinyint'    => 'boolean',
            'tinytext'   => 'text',
            'varbinary'  => 'binary',
            'varchar'    => 'string',
            'year'       => 'date',
        ];
    }

    protected function createReservedKeywordsList(): KeywordList
    {
        return new MySQLKeywords();
    }

    /**
     * {@inheritDoc}
     *
     * MySQL commits a transaction implicitly when DROP TABLE is executed, however not
     * if DROP TEMPORARY TABLE is executed.
     */
    public function getDropTemporaryTableSQL(string $table): string
    {
        return 'DROP TEMPORARY TABLE ' . $table;
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     *     TINYBLOB   : 2 ^  8 - 1 = 255
     *     BLOB       : 2 ^ 16 - 1 = 65535
     *     MEDIUMBLOB : 2 ^ 24 - 1 = 16777215
     *     LONGBLOB   : 2 ^ 32 - 1 = 4294967295
     *
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['length']) && is_numeric($column['length'])) {
            $length = $column['length'];

            if ($length <= static::LENGTH_LIMIT_TINYBLOB) {
                return 'TINYBLOB';
            }

            if ($length <= static::LENGTH_LIMIT_BLOB) {
                return 'BLOB';
            }

            if ($length <= static::LENGTH_LIMIT_MEDIUMBLOB) {
                return 'MEDIUMBLOB';
            }
        }

        return 'LONGBLOB';
    }

    public function quoteStringLiteral(string $str): string
    {
        $str = str_replace('\\', '\\\\', $str); // MySQL requires backslashes to be escaped aswell.

        return parent::quoteStringLiteral($str);
    }

    public function getDefaultTransactionIsolationLevel(): int
    {
        return TransactionIsolationLevel::REPEATABLE_READ;
    }

    public function supportsColumnLengthIndexes(): bool
    {
        return true;
    }
}
