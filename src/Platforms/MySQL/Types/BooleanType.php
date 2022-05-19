<?php

namespace Doctrine\DBAL\Platforms\MySQL\Types;

use Doctrine\DBAL\Platforms\Types\BooleanType as PlatformBooleanType;

class BooleanType extends PlatformBooleanType
{
    public function getSQLDeclaration(array $column)
    {
        return 'TINYINT(1)';
    }
}
