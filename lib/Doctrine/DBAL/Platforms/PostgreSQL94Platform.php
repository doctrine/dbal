<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides the behavior, features and SQL dialect of the PostgreSQL 9.4 database platform.
 *
 * @author Matteo Beccati <matteo@beccati.com>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
class PostgreSQL94Platform extends PostgreSQL92Platform
{
    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $field)
    {
        if (!empty($field['jsonb'])) {
            return 'JSONB';
        }

        return 'JSON';
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\PostgreSQL94Keywords';
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        parent::initializeDoctrineTypeMappings();
        $this->doctrineTypeMapping['jsonb'] = 'json_array';
    }
}
