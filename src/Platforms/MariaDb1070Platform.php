<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;

class MariaDb1070Platform extends MariaDb1052Platform
{
    /**
     * {@inheritdoc}
     */
    public function getGuidTypeDeclarationSQL(array $column)
    {
        return 'UUID';
    }

    /**
     * Does this platform have native guid type.
     *
     * @deprecated
     *
     * @return bool
     */
    public function hasNativeGuidType()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5509',
            '%s is deprecated.',
            __METHOD__,
        );

        return true;
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['uuid'] = Types::GUID;
    }
}
