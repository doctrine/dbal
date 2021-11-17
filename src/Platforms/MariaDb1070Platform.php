<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\Types;

/**
 * Provides the native GUID type from MariaDB 10.7 database platform.
 *
 * Note: Should not be used with versions prior to 10.7.
 */
class MariaDb1070Platform extends MariaDb1027Platform
{
    public function hasNativeGuidType(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        return 'UUID';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['uuid'] = Types::GUID;
    }
}
