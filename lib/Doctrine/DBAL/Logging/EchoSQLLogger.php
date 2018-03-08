<?php

namespace Doctrine\DBAL\Logging;

use const PHP_EOL;
use function var_dump;

/**
 * A SQL logger that logs to the standard output using echo/var_dump.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 */
class EchoSQLLogger implements SQLLogger
{
    /**
     * {@inheritdoc}
     */
    public function startQuery(string $sql, array $params = [], array $types = []) : void
    {
        echo $sql . PHP_EOL;

        if ($params) {
            var_dump($params);
        }

        if ($types) {
            var_dump($types);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery() : void
    {
    }
}
