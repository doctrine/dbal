<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\AbstractDriverException;
use Exception;
use PHPUnit\Framework\TestCase;
use Throwable;

class AbstractDriverExceptionTest extends TestCase
{
    public function testCanSetPreviousException()
    {
        $previous = new Exception();

        $abstractDriverException = new class($previous) extends AbstractDriverException {
            public function __construct(Throwable $previous)
            {
                parent::__construct('', null, null, $previous);
            }
        };

        $this->assertSame($previous, $abstractDriverException->getPrevious());
    }
}
