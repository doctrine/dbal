<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\Common\Proxy\Exception\UnexpectedValueException;
use Doctrine\DBAL\Schema\Index;

/**
 * The SQLAnywhere16Platform provides the behavior, features and SQL dialect of the
 * SAP Sybase SQL Anywhere 16 database platform.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class SQLAnywhere16Platform extends SQLAnywhere12Platform
{
    /**
     * {@inheritdoc}
     */
    protected function getAdvancedIndexOptionsSQL(Index $index)
    {
        if ($index->hasFlag('with_nulls_distinct') && $index->hasFlag('with_nulls_not_distinct')) {
            throw new UnexpectedValueException(
                'An Index can either have a "with_nulls_distinct" or "with_nulls_not_distinct" flag but not both.'
            );
        }

        if ( ! $index->isPrimary() && $index->isUnique() && $index->hasFlag('with_nulls_distinct')) {
            return ' WITH NULLS DISTINCT' . parent::getAdvancedIndexOptionsSQL($index);
        }

        return parent::getAdvancedIndexOptionsSQL($index);
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return Keywords\SQLAnywhere16Keywords::class;
    }
}
