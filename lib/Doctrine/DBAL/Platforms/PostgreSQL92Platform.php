<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides the behavior, features and SQL dialect of the PostgreSQL 9.2 database platform.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class PostgreSQL92Platform extends PostgreSQL91Platform
{
    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $field)
    {
        return 'JSON';
    }

    /**
     * {@inheritdoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        if ( ! empty($field['autoincrement'])) {
            return 'SMALLSERIAL';
        }

        return parent::getSmallIntTypeDeclarationSQL($field);
    }

    /**
     * {@inheritdoc}
     */
    public function hasNativeJsonType()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\PostgreSQL92Keywords';
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        parent::initializeDoctrineTypeMappings();
        $this->doctrineTypeMapping['json'] = 'json_array';
    }

    /**
     * {@inheritdoc}
     */
    public function getCloseActiveDatabaseConnectionsSQL($database)
    {
        $database = $this->quoteStringLiteral($database);

        return "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $database";
    }
}
