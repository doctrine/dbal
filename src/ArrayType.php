<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Types\Type;

class ArrayType
{
    public function __construct(private readonly string|Type|ParameterType $baseType)
    {
    }

    public function getBaseType(): Type|string|ParameterType
    {
        return $this->baseType;
    }
}
