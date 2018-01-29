<?php

namespace Doctrine\Tests\DBAL\Logging;

class DebugStackTest extends \Doctrine\Tests\DbalTestCase
{
    protected function setUp()
    {
        $this->logger = new \Doctrine\DBAL\Logging\DebugStack();
    }

    protected function tearDown()
    {
        unset($this->logger);
    }

    public function testLoggedQuery()
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
            $this->logger->queries
        );

        $this->logger->stopQuery();
        self::assertGreaterThan(0, $this->logger->queries[1]['executionMS']);
    }

    public function testLoggedQueryDisabled()
    {
        $this->logger->enabled = false;
        $this->logger->startQuery('SELECT column FROM table');
        self::assertEquals([], $this->logger->queries);

        $this->logger->stopQuery();
        self::assertEquals([], $this->logger->queries);
    }
}
