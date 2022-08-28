<?php

namespace Doctrine\DBAL\Tests\Logging;

use Doctrine\DBAL\Logging\DebugStack;
use PHPUnit\Framework\TestCase;

class DebugStackTest extends TestCase
{
    private DebugStack $logger;

    protected function setUp(): void
    {
        $this->logger = new DebugStack();
    }

    protected function tearDown(): void
    {
        unset($this->logger);
    }

    public function testLoggedQuery(): void
    {
        $this->logger->startQuery('SELECT column FROM table');
        self::assertEquals(
            [
                1 => [
                    'sql' => 'SELECT column FROM table',
                    'params' => null,
                    'types' => null,
                    'executionMS' => 0,
                ],
            ],
            $this->logger->queries,
        );

        $this->logger->stopQuery();
        self::assertGreaterThan(0, $this->logger->queries[1]['executionMS']);
    }

    public function testLoggedQueryDisabled(): void
    {
        $this->logger->enabled = false;
        $this->logger->startQuery('SELECT column FROM table');
        self::assertEquals([], $this->logger->queries);

        $this->logger->stopQuery();
        self::assertEquals([], $this->logger->queries);
    }
}
