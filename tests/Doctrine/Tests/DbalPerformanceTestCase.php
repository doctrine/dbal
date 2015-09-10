<?php

namespace Doctrine\Tests;

/**
 * Base class for all DBAL performance tests.
 * 
 * Tests implemented in this class must call startTiming at the beginning
 * and stopTiming at the end of all tests. Tests that do not start or stop
 * timing will fail.
 *
 * @package Doctrine\Tests\DBAL
 * @author Bill Schaller
 */
class DbalPerformanceTestCase extends DbalFunctionalTestCase
{
    /**
     * time the test started
     *
     * @var float
     */
    private $startTime;

    /**
     * elapsed run time of the last test
     *
     * @var float
     */
    private $lastRunTime;

    public function setUp()
    {
        parent::setUp();

        // Reset timing vars
        $this->startTime = $this->lastRunTime = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function assertPostConditions()
    {
        // If a perf test doesn't start or stop, it fails.
        $this->assertNotNull($this->startTime, "Test timing was started");
        $this->assertNotNull($this->lastRunTime, "Test timing was stopped");
    }

    /**
     * begin timing
     */
    protected function startTiming()
    {
        $this->startTime = microtime(true);
    }

    /**
     * end timing
     */
    protected function stopTiming()
    {
        $this->lastRunTime = microtime(true) - $this->startTime;
    }

    /**
     * @return float elapsed test execution time
     */
    public function getTime()
    {
        return $this->lastRunTime;
    }
}
