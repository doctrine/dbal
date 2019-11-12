<?php

declare(strict_types=1);

namespace Doctrine\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use function strtoupper;

class MySqlPointType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getName() : string
    {
        return 'point';
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        return strtoupper($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform) : array
    {
        return ['point'];
    }
}
