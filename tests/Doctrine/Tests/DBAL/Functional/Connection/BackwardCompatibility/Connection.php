<?php

namespace Doctrine\Tests\DBAL\Functional\Connection\BackwardCompatibility;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection as BaseConnection;
use Doctrine\DBAL\ForwardCompatibility;

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
        return new ForwardCompatibility\Result(
            new Statement(parent::executeQuery($sql, $params, $types, $qcp))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($sql)
    {
        return new Statement(parent::prepare($sql));
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        return new Statement(parent::query(...func_get_args()));
    }
}
