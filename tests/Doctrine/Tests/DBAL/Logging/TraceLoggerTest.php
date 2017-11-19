<?php

namespace Doctrine\Tests\DBAL\Logging;

require_once __DIR__ . '/../../TestInit.php';

class TraceLoggerTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Logging\TraceLogger
     */
    private $logger;

    public function setUp()
    {
        $this->logger = new \Doctrine\DBAL\Logging\TraceLogger();
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
                    'trace' => array(
                        "ReflectionMethod::invokeArgs",
                        "PHPUnit_Framework_TestCase::runTest (L905)",
                        "PHPUnit_Framework_TestCase::runBare (L775)",
                        "PHPUnit_Framework_TestResult::run (L643)",
                        "PHPUnit_Framework_TestCase::run (L711)",
                        "PHPUnit_Framework_TestSuite::run (L751)",
                        "PHPUnit_Framework_TestSuite::run (L751)",
                        "PHPUnit_TextUI_TestRunner::doRun (L423)",
                        "PHPUnit_TextUI_Command::run (L186)",
                        "PHPUnit_TextUI_Command::run (L138)"
                    )
                ),
            ),
            $this->logger->queries
        );

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
