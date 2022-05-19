<?php

namespace Doctrine\DBAL\Platforms\Oracle\Types;

use Doctrine\DBAL\Platforms\Types\BooleanType as PlatformBooleanType;

class BooleanType extends PlatformBooleanType
{
    public function getSQLDeclaration(array $column)
    {
        return 'NUMBER(1)';
    }
}
