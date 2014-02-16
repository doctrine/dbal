<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver\ResultStatement;

interface DriverResultStatementMock extends ResultStatement, \IteratorAggregate
{
}
