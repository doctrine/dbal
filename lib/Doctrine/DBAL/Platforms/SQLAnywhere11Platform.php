<?php

namespace Doctrine\DBAL\Platforms;

/**
 * The SQLAnywhere11Platform provides the behavior, features and SQL dialect of the
 * SAP Sybase SQL Anywhere 11 database platform.
 *
 * @deprecated Support for SQLAnywhere will be removed in 3.0.
 */
class SQLAnywhere11Platform extends SQLAnywherePlatform
{
    /**
     * {@inheritdoc}
     */
    public function getRegexpExpression()
    {
        return 'REGEXP';
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return Keywords\SQLAnywhere11Keywords::class;
    }
}
