<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\Types;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.7 (10.7.0 GA) database platform.
 */
class MariaDB1070Platform extends MariaDB1060Platform
{
    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['uuid'] = Types::GUID;
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        return 'UUID';
    }
}
