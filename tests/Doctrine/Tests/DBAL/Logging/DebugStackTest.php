<?php

namespace Doctrine\Tests\DBAL\Logging;

require_once __DIR__ . '/../../TestInit.php';

class DebugStackTest extends \Doctrine\Tests\DbalTestCase
{
    private $logger;

    public function setUp()
    {
        $this->logger = new \Doctrine\DBAL\Logging\DebugStack();
    }

    public function tearDown()
    {
        unset($this->logger);
    }

    public function testLoggedQuery()
    {
        $this->logger->startQuery('SELECT column FROM table');
        $this->assertEquals(
            array(
                1 => array(
                    'sql' => 'SELECT column FROM table',
                    'params' => null,
                    'types' => null,
                    'executionMS' => 0,
                ),
            ),
            $this->logger->getQueries()
        );

        $this->logger->stopQuery();

        $queries = $this->logger->getQueries();
        $this->assertGreaterThan(0, $queries[1]['executionMS']);
    }

    public function testLoggedQueryDisabled()
    {
        $this->logger->setEnabled(false);
        $this->logger->startQuery('SELECT column FROM table');
        $this->assertEquals(array(), $this->logger->getQueries());

        $this->logger->stopQuery();
        $this->assertEquals(array(), $this->logger->getQueries());
    }
}
