<?php

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use function interface_exists;

class PsrAdapterTest extends TestCase
{
    public function testLogging()
    {
        if (! interface_exists(LoggerInterface::class)) {
            $this->markTestSkipped('PSR-3 LoggerInterface is unavailable');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('SELECT name FROM users WHERE id = ?', [
                'params' => [1],
                'types' => [ParameterType::INTEGER],
            ]);

        $adapter = new PsrAdapter($logger);
        $adapter->startQuery('SELECT name FROM users WHERE id = ?', [1], [ParameterType::INTEGER]);
    }
}
