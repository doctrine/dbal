<?php

namespace Doctrine\DBAL\Driver\OCI8\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

use function filter_var;
use function sprintf;

use const FILTER_VALIDATE_BOOL;

class InitializeSession implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            /**
             * {@inheritDoc}
             */
            public function connect(
                #[SensitiveParameter]
                array $params
            ): Connection {
                $timestampSecondsSpecifier = filter_var(
                    $params['driverOptions']['high_precision_timestamps'] ?? false,
                    FILTER_VALIDATE_BOOL,
                )
                ? 'SS.FF6'
                : 'SS';

                $connection = parent::connect($params);

                $connection->exec(
                    'ALTER SESSION SET'
                        . " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                        . " NLS_TIME_FORMAT = 'HH24:MI:SS'"
                        . " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                        . sprintf(" NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:%s'", $timestampSecondsSpecifier)
                        . sprintf(
                            " NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:%s TZH:TZM'",
                            $timestampSecondsSpecifier,
                        )
                        . " NLS_NUMERIC_CHARACTERS = '.,'",
                );

                return $connection;
            }
        };
    }
}
