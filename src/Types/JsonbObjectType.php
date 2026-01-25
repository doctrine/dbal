<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Override;

/**
 * Type generating json objects values
 */
class JsonbObjectType extends Type
{
    use JsonTypeConvert;

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonbTypeDeclarationSQL($column);
    }

    #[Override]
    protected function isAssociative(): bool
    {
        return false;
    }
}
