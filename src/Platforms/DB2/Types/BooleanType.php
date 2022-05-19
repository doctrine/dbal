<?php

namespace Doctrine\DBAL\Platforms\DB2\Types;

use Doctrine\DBAL\Platforms\Types\BooleanType as PlatformBooleanType;

class BooleanType extends PlatformBooleanType
{
    public function getSQLDeclaration(array $column)
    {
        return 'SMALLINT';
    }

    public function requiresSQLCommentHint()
    {
        return true;
    }
}
