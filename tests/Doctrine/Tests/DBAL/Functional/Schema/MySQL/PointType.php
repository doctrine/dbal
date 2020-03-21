<?php

namespace Doctrine\Tests\DBAL\Functional\Schema\MySQL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use function strtoupper;

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
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return strtoupper($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform)
    {
        return ['point'];
    }
}
