<?php

namespace Doctrine\DBAL\Platforms\SQLServer\Types;

use Doctrine\DBAL\Platforms\Types\BooleanType as PlatformBooleanType;

class BooleanType extends PlatformBooleanType
{
    public function getSQLDeclaration(array $column)
    {
        return 'BIT';
    }
}
