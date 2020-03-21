<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Logging;

use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

class LoggerChainTest extends TestCase
{
    public function testStartQuery() : void
    {
        $sql    = 'SELECT ?';
        $params = [1];
        $types  = [ParameterType::INTEGER];

        $listener = $this->createChain('startQuery', $sql, $params, $types);
        $listener->startQuery($sql, $params, $types);
    }

    public function testStopQuery() : void
    {
        $listener = $this->createChain('stopQuery');
        $listener->stopQuery();
    }

    /**
     * @param mixed ...$args
     */
    private function createChain(string $method, ...$args) : LoggerChain
    {
        $chain = new LoggerChain([
            $this->createLogger($method, ...$args),
        ]);

        $chain->addLogger($this->createLogger($method, ...$args));

        return $chain;
    }

    /**
     * @param mixed ...$args
     */
    private function createLogger(string $method, ...$args) : SQLLogger
    {
        $logger = $this->createMock(SQLLogger::class);
        $logger->expects($this->once())
            ->method($method)
            ->with(...$args);

        return $logger;
    }
}
