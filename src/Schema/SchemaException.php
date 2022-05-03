<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;

use function implode;
use function sprintf;

/**
 * @psalm-immutable
 */
class SchemaException extends Exception
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
    public const CONSTRAINT_DOESNT_EXIST  = 110;
    public const NAMESPACE_ALREADY_EXISTS = 120;

    /**
     * @param string $tableName
     */
    public static function tableDoesNotExist($tableName): SchemaException
    {
        return new self("There is no table with name '" . $tableName . "' in the schema.", self::TABLE_DOESNT_EXIST);
    }

    /**
     * @param string $indexName
     */
    public static function indexNameInvalid($indexName): SchemaException
    {
        return new self(
            sprintf('Invalid index-name %s given, has to be [a-zA-Z0-9_]', $indexName),
            self::INDEX_INVALID_NAME
        );
    }

    /**
     * @param string $indexName
     * @param string $table
     */
    public static function indexDoesNotExist($indexName, $table): SchemaException
    {
        return new self(
            sprintf("Index '%s' does not exist on table '%s'.", $indexName, $table),
            self::INDEX_DOESNT_EXIST
        );
    }

    /**
     * @param string $indexName
     * @param string $table
     */
    public static function indexAlreadyExists($indexName, $table): SchemaException
    {
        return new self(
            sprintf("An index with name '%s' was already defined on table '%s'.", $indexName, $table),
            self::INDEX_ALREADY_EXISTS
        );
    }

    /**
     * @param string $columnName
     * @param string $table
     */
    public static function columnDoesNotExist($columnName, $table): SchemaException
    {
        return new self(
            sprintf("There is no column with name '%s' on table '%s'.", $columnName, $table),
            self::COLUMN_DOESNT_EXIST
        );
    }

    /**
     * @param string $namespaceName
     */
    public static function namespaceAlreadyExists($namespaceName): SchemaException
    {
        return new self(
            sprintf("The namespace with name '%s' already exists.", $namespaceName),
            self::NAMESPACE_ALREADY_EXISTS
        );
    }

    /**
     * @param string $tableName
     */
    public static function tableAlreadyExists($tableName): SchemaException
    {
        return new self("The table with name '" . $tableName . "' already exists.", self::TABLE_ALREADY_EXISTS);
    }

    /**
     * @param string $tableName
     * @param string $columnName
     */
    public static function columnAlreadyExists($tableName, $columnName): SchemaException
    {
        return new self(
            "The column '" . $columnName . "' on table '" . $tableName . "' already exists.",
            self::COLUMN_ALREADY_EXISTS
        );
    }

    /**
     * @param string $name
     */
    public static function sequenceAlreadyExists($name): SchemaException
    {
        return new self("The sequence '" . $name . "' already exists.", self::SEQUENCE_ALREADY_EXISTS);
    }

    /**
     * @param string $name
     */
    public static function sequenceDoesNotExist($name): SchemaException
    {
        return new self("There exists no sequence with the name '" . $name . "'.", self::SEQUENCE_DOENST_EXIST);
    }

    /**
     * @param string $constraintName
     * @param string $table
     */
    public static function uniqueConstraintDoesNotExist($constraintName, $table): SchemaException
    {
        return new self(
            sprintf('There exists no unique constraint with the name "%s" on table "%s".', $constraintName, $table),
            self::CONSTRAINT_DOESNT_EXIST
        );
    }

    /**
     * @param string $fkName
     * @param string $table
     */
    public static function foreignKeyDoesNotExist($fkName, $table): SchemaException
    {
        return new self(
            sprintf("There exists no foreign key with the name '%s' on table '%s'.", $fkName, $table),
            self::FOREIGNKEY_DOESNT_EXIST
        );
    }

    public static function namedForeignKeyRequired(Table $localTable, ForeignKeyConstraint $foreignKey): SchemaException
    {
        return new self(
            'The performed schema operation on ' . $localTable->getName() . ' requires a named foreign key, ' .
            'but the given foreign key from (' . implode(', ', $foreignKey->getColumns()) . ') onto foreign table ' .
            "'" . $foreignKey->getForeignTableName() . "' (" . implode(', ', $foreignKey->getForeignColumns()) . ')' .
            ' is currently unnamed.'
        );
    }

    /**
     * @param string $changeName
     */
    public static function alterTableChangeNotSupported($changeName): SchemaException
    {
        return new self(
            sprintf("Alter table change not supported, given '%s'", $changeName)
        );
    }
}
