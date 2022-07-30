<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class Middleware implements MiddlewareInterface
{
    public function __construct(private readonly int $mode, private readonly ?ColumnCase $case)
    {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        if ($this->mode !== 0) {
            return new Driver($driver, $this->mode, $this->case);
        }

        return $driver;
    }
}
