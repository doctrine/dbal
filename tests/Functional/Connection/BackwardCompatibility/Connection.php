<?php

namespace Doctrine\DBAL\Tests\Functional\Connection\BackwardCompatibility;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection as BaseConnection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use function func_get_args;

/**
 * Wraps statements in a non-forward-compatible wrapper.
 */
class Connection extends BaseConnection
{
    /**
     * {@inheritdoc}
     */
    public function executeQuery(string $query, array $params = [], $types = [], ?QueryCacheProfile $qcp = null) : ResultStatement
    {
        return new Statement(parent::executeQuery($query, $params, $types, $qcp));
    }

    public function prepare(string $sql) : DriverStatement
    {
        return new Statement(parent::prepare($sql));
    }

    public function query(string $sql) : ResultStatement
    {
        return new Statement(parent::query(...func_get_args()));
    }
}
