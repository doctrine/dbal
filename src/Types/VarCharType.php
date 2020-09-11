<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Driver\Encodings;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function sprintf;

class VarCharType extends StringType
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $length = $column['length'] ?? null;

        if (! isset($column['fixed'])) {
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
