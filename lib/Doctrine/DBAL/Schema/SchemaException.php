<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\DBALException;
use function implode;
use function sprintf;

class SchemaException extends DBALException
{
    public const TABLE_DOESNT_EXIST       = 10;
    public const TABLE_ALREADY_EXISTS     = 20;
    public const COLUMN_DOESNT_EXIST      = 30;
    public const COLUMN_ALREADY_EXISTS    = 40;
    public const INDEX_DOESNT_EXIST       = 50;
    public const INDEX_ALREADY_EXISTS     = 60;
    public const SEQUENCE_DOENST_EXIST    = 70;
    public const SEQUENCE_ALREADY_EXISTS  = 80;
    public const INDEX_INVALID_NAME       = 90;
    public const FOREIGNKEY_DOESNT_EXIST  = 100;
    public const NAMESPACE_ALREADY_EXISTS = 110;

    /**
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function tableDoesNotExist($tableName)
    {
        return new self("There is no table with name '" . $tableName . "' in the schema.", self::TABLE_DOESNT_EXIST);
    }

    /**
     * @param string $indexName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function indexNameInvalid($indexName)
    {
        return new self(
            sprintf('Invalid index-name %s given, has to be [a-zA-Z0-9_]', $indexName),
            self::INDEX_INVALID_NAME
        );
    }

    /**
     * @param string $indexName
     * @param string $table
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function indexDoesNotExist($indexName, $table)
    {
        return new self(
            sprintf("Index '%s' does not exist on table '%s'.", $indexName, $table),
            self::INDEX_DOESNT_EXIST
        );
    }

    /**
     * @param string $indexName
     * @param string $table
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function indexAlreadyExists($indexName, $table)
    {
        return new self(
            sprintf("An index with name '%s' was already defined on table '%s'.", $indexName, $table),
            self::INDEX_ALREADY_EXISTS
        );
    }

    /**
     * @param string $columnName
     * @param string $table
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function columnDoesNotExist($columnName, $table)
    {
        return new self(
            sprintf("There is no column with name '%s' on table '%s'.", $columnName, $table),
            self::COLUMN_DOESNT_EXIST
        );
    }

    /**
     * @param string $namespaceName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function namespaceAlreadyExists($namespaceName)
    {
        return new self(
            sprintf("The namespace with name '%s' already exists.", $namespaceName),
            self::NAMESPACE_ALREADY_EXISTS
        );
    }

    /**
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function tableAlreadyExists($tableName)
    {
        return new self("The table with name '" . $tableName . "' already exists.", self::TABLE_ALREADY_EXISTS);
    }

    /**
     * @param string $tableName
     * @param string $columnName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function columnAlreadyExists($tableName, $columnName)
    {
        return new self(
            "The column '" . $columnName . "' on table '" . $tableName . "' already exists.",
            self::COLUMN_ALREADY_EXISTS
        );
    }

    /**
     * @param string $sequenceName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function sequenceAlreadyExists($sequenceName)
    {
        return new self("The sequence '" . $sequenceName . "' already exists.", self::SEQUENCE_ALREADY_EXISTS);
    }

    /**
     * @param string $sequenceName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function sequenceDoesNotExist($sequenceName)
    {
        return new self("There exists no sequence with the name '" . $sequenceName . "'.", self::SEQUENCE_DOENST_EXIST);
    }

    /**
     * @param string $fkName
     * @param string $table
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function foreignKeyDoesNotExist($fkName, $table)
    {
        return new self(
            sprintf("There exists no foreign key with the name '%s' on table '%s'.", $fkName, $table),
            self::FOREIGNKEY_DOESNT_EXIST
        );
    }

    /**
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function namedForeignKeyRequired(Table $localTable, ForeignKeyConstraint $foreignKey)
    {
        return new self(
            'The performed schema operation on ' . $localTable->getName() . ' requires a named foreign key, ' .
            'but the given foreign key from (' . implode(', ', $foreignKey->getColumns()) . ') onto foreign table ' .
            "'" . $foreignKey->getForeignTableName() . "' (" . implode(', ', $foreignKey->getForeignColumns()) . ') is currently ' .
            'unnamed.'
        );
    }

    /**
     * @param string $changeName
     *
     * @return \Doctrine\DBAL\Schema\SchemaException
     */
    public static function alterTableChangeNotSupported($changeName)
    {
        return new self(
            sprintf("Alter table change not supported, given '%s'", $changeName)
        );
    }
}
