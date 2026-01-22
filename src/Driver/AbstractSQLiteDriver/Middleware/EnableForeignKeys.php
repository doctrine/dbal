<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

final readonly class EnableForeignKeys implements Middleware
{
    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new readonly class ($driver) extends AbstractDriverMiddleware {
            /**
             * {@inheritDoc}
             */
            #[Override]
            public function connect(
                #[SensitiveParameter]
                array $params,
            ): Connection {
                $connection = parent::connect($params);

                $connection->exec('PRAGMA foreign_keys=ON');

                return $connection;
            }
        };
    }
}
