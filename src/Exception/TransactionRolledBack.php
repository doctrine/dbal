<?php

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Driver\Exception as TheDriverException;
use Doctrine\DBAL\Query;

/** @psalm-immutable */
class TransactionRolledBack extends DriverException
{
    private DriverException $why;

    public function __construct(DriverException $why, TheDriverException $driverException, ?Query $query)
    {
        parent::__construct($driverException, $query);

        $this->why = $why;
    }

    public function why(): DriverException
    {
        return $this->why;
    }
}
