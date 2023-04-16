<?php

namespace Doctrine\DBAL\Driver\OCI8\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\Deprecations\Deprecation;
use SensitiveParameter;

class InitializeSession implements Middleware
{
    /** @var bool */
    private $useProperTzFormat = false;

    public function __construct(bool $useProperTzFormat = false)
    {
        if (! $useProperTzFormat) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6012',
                'Not passing `true` in argument 1 for "%s()" is deprecated, as this value ensures the proper format for'
                . ' the "NLS_TIMESTAMP_TZ_FORMAT" session option. This argument will be removed in 4.0 as the proper'
                . ' behavior will be used by default.',
                __METHOD__,
            );
        }

        $this->useProperTzFormat = $useProperTzFormat;
    }

    public function wrap(Driver $driver): Driver
    {
        return new class ($driver, $this->useProperTzFormat) extends AbstractDriverMiddleware {
            /** @var bool */
            private $useProperTzFormat = false;

            public function __construct(Driver $driver, bool $useProperTzFormat = false)
            {
                parent::__construct($driver);

                $this->useProperTzFormat = $useProperTzFormat;
            }

            /**
             * {@inheritDoc}
             */
            public function connect(
                #[SensitiveParameter]
                array $params
            ): Connection {
                $connection = parent::connect($params);

                // Use "YYYY-MM-DD HH24:MI:SSTZH:TZM" in 4.0.
                $tzFormat = $this->useProperTzFormat ? 'YYYY-MM-DD HH24:MI:SSTZH:TZM' : 'YYYY-MM-DD HH24:MI:SS TZH:TZM';

                $connection->exec(
                    'ALTER SESSION SET'
                        . " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                        . " NLS_TIME_FORMAT = 'HH24:MI:SS'"
                        . " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                        . " NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"
                        . " NLS_TIMESTAMP_TZ_FORMAT = '" . $tzFormat . "'"
                        . " NLS_NUMERIC_CHARACTERS = '.,'",
                );

                return $connection;
            }
        };
    }
}
