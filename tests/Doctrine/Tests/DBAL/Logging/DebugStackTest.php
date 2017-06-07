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
        $this->assertTrue(is_array($this->logger->queries));
        $this->assertTrue(is_array($this->logger->queries[1]));
        $this->assertEquals('SELECT column FROM table', $this->logger->queries[1]['sql']);
        $this->assertNull($this->logger->queries[1]['params']);
        $this->assertNull($this->logger->queries[1]['types']);
        $this->assertEquals(0, $this->logger->queries[1]['executionMS']);
        $this->assertTrue(is_array($this->logger->queries[1]['stacktrace']));
        $this->assertNotEmpty($this->logger->queries[1]['stacktrace']);

        $this->logger->stopQuery();
        $this->assertGreaterThan(0, $this->logger->queries[1]['executionMS']);
    }

    public function testLoggedQueryDisabled()
    {
        $this->logger->enabled = false;
        $this->logger->startQuery('SELECT column FROM table');
        $this->assertEquals(array(), $this->logger->queries);

        $this->logger->stopQuery();
        $this->assertEquals(array(), $this->logger->queries);
    }
}
