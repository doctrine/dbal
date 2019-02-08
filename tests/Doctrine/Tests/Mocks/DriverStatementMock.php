<?php

declare(strict_types=1);

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver\Statement;
use IteratorAggregate;

interface DriverStatementMock extends Statement, IteratorAggregate
{
}
