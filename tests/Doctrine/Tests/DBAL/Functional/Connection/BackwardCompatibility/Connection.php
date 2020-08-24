<?php

namespace Doctrine\Tests\DBAL\Functional\Connection\BackwardCompatibility;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection as BaseConnection;

use function func_get_args;

/**
 * Wraps statements in a non-forward-compatible wrapper.
 */
class Connection extends BaseConnection
{
    /**
     * {@inheritdoc}
     */
    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null)
    {
        return new Statement(parent::executeQuery($sql, $params, $types, $qcp));
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($statement)
    {
        return new Statement(parent::prepare($statement));
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        return new Statement(parent::query(...func_get_args()));
    }
}
