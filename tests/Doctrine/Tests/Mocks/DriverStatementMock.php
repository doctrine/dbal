<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver\Statement;

interface DriverStatementMock extends Statement, \IteratorAggregate
{
}
