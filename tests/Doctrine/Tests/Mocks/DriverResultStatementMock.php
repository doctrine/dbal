<?php

declare(strict_types=1);

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver\ResultStatement;
use IteratorAggregate;

interface DriverResultStatementMock extends ResultStatement, IteratorAggregate
{
}
