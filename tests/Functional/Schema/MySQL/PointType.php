<?php

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class PointType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'point';
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'POINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform)
    {
        return ['point'];
    }
}
