<?php

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\PDO;

use const CASE_LOWER;
use const CASE_UPPER;

final class Driver extends AbstractDriverMiddleware
{
    /** @var int */
    private $mode;

    /** @var int */
    private $case;

    public function __construct(DriverInterface $driver, int $mode, int $case)
    {
        parent::__construct($driver);

        $this->mode = $mode;
        $this->case = $case;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(array $params)
    {
        $connection = parent::connect($params);

        $portability = (new OptimizeFlags())(
            $this->getDatabasePlatform(),
            $this->mode
        );

        $case = 0;

        if ($this->case !== 0 && ($portability & Connection::PORTABILITY_FIX_CASE) !== 0) {
            if ($connection instanceof PDO\Connection) {
                // make use of c-level support for case handling
                $portability &= ~Connection::PORTABILITY_FIX_CASE;
                $connection->getWrappedConnection()->setAttribute(\PDO::ATTR_CASE, $this->case);
            } else {
                $case = $this->case === ColumnCase::LOWER ? CASE_LOWER : CASE_UPPER;
            }
        }

        $convertEmptyStringToNull = ($portability & Connection::PORTABILITY_EMPTY_TO_NULL) !== 0;
        $rightTrimString          = ($portability & Connection::PORTABILITY_RTRIM) !== 0;

        if (! $convertEmptyStringToNull && ! $rightTrimString && $case === 0) {
            return $connection;
        }

        return new Connection(
            $connection,
            new Converter($convertEmptyStringToNull, $rightTrimString, $case)
        );
    }
}
