<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Override;

class TypeWithConstructor extends Type
{
    public function __construct(public bool $requirement)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return '';
    }
}
