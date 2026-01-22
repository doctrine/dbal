<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Override;

/**
 * Type generating JSON objects values stored in JSONB columns.
 */
class JsonbType extends JsonType
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonbTypeDeclarationSQL($column);
    }
}
