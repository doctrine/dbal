<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLServerDriverTestCase;
use Override;

class DriverTest extends AbstractSQLServerDriverTestCase
{
    #[Override]
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
