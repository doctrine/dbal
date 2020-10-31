<?php

namespace Doctrine\DBAL\Logging;

use function var_dump;

use const PHP_EOL;

/**
 * A SQL logger that logs to the standard output using echo/var_dump.
 *
 * @deprecated
 */
class EchoSQLLogger implements SQLLogger
{
    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, ?array $params = null, ?array $types = null)
    {
        echo $sql . PHP_EOL;

        if ($params) {
            var_dump($params);
        }

        if (! $types) {
            return;
        }

        var_dump($types);
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
    }
}
