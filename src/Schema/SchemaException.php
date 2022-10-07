<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;

/** @psalm-immutable */
class SchemaException extends \Exception implements Exception
{
    /** @deprecated Use {@see TableDoesNotExist} instead. */
    public const TABLE_DOESNT_EXIST = 10;

    /** @deprecated Use {@see TableAlreadyExists} instead. */
    public const TABLE_ALREADY_EXISTS = 20;

    /** @deprecated Use {@see ColumnDoesNotExist} instead. */
    public const COLUMN_DOESNT_EXIST = 30;

    /** @deprecated Use {@see ColumnAlreadyExists} instead. */
    public const COLUMN_ALREADY_EXISTS = 40;

    /** @deprecated Use {@see IndexDoesNotExist} instead. */
    public const INDEX_DOESNT_EXIST = 50;

    /** @deprecated Use {@see IndexAlreadyExists} instead. */
    public const INDEX_ALREADY_EXISTS = 60;

    /** @deprecated Use {@see SequenceDoesNotExist} instead. */
    public const SEQUENCE_DOENST_EXIST = 70;

    /** @deprecated Use {@see SequenceAlreadyExists} instead. */
    public const SEQUENCE_ALREADY_EXISTS = 80;

    /** @deprecated Use {@see IndexNameInvalid} instead. */
    public const INDEX_INVALID_NAME = 90;

    /** @deprecated Use {@see ForeignKeyDoesNotExist} instead. */
    public const FOREIGNKEY_DOESNT_EXIST = 100;

    /** @deprecated Use {@see UniqueConstraintDoesNotExist} instead. */
    public const CONSTRAINT_DOESNT_EXIST = 110;

    /** @deprecated Use {@see NamespaceAlreadyExists} instead. */
    public const NAMESPACE_ALREADY_EXISTS = 120;
}
