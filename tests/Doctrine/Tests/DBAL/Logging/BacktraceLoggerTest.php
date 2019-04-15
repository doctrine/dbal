<?php

namespace Doctrine\Tests\DBAL\Logging;

use Doctrine\DBAL\Logging\BacktraceLogger;
use Doctrine\Tests\DbalTestCase;
use function current;

class BacktraceLoggerTest extends DbalTestCase
{
    public function testBacktraceLogged()
    {
        $logger = new BacktraceLogger();

        $logger->startQuery('SELECT column FROM table');

        $currentQuery = current($logger->queries);

        self::assertSame('SELECT column FROM table', $currentQuery['sql']);
        self::assertNull($currentQuery['params']);
        self::assertNull($currentQuery['types']);
        self::assertIsArray($currentQuery['backtrace']);
    }
}
