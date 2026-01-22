<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Override;

final class EnumType extends Type
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getEnumDeclarationSQL($column);
    }
}
