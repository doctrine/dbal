<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;

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
}
