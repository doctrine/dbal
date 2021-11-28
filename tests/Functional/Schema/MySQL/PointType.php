<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class PointType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['point'];
    }
}
