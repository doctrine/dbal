<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Driver\Encodings;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class VarCharType extends StringType
{
    public function getSQLDeclaration(array $field, AbstractPlatform $platform): string
    {
        $length = $field['length'] ?? null;

        if (empty($field['fixed'])) {
            return sprintf('VARCHAR(%d)', $length);
        }

        return sprintf('CHAR(%d)', $length);
    }

    public function getBindingType(): int
    {
        return ParameterType::STRING | Encodings::ASCII;
    }

    public function getName(): string
    {
        return Types::VARCHAR;
    }
}
