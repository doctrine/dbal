<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\Types;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.2 (10.2.7 GA) database platform.
 *
 * Note: Should not be used with versions prior to 10.2.7.
 */
class MariaDb1070Platform extends MariaDb1027Platform
{
    /**
     * {@inheritDoc}
     */
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
