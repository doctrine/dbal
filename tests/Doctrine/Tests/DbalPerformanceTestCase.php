<?php

namespace Doctrine\Tests;

use function microtime;

/**
 * Base class for all DBAL performance tests.
 *
 * Tests implemented in this class must call startTiming at the beginning
 * and stopTiming at the end of all tests. Tests that do not start or stop
 * timing will fail.
 */
abstract class DbalPerformanceTestCase extends DbalFunctionalTestCase
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
    private $runTime;

    /**
     * {@inheritdoc}
     */
    protected function assertPostConditions() : void
    {
        // If a perf test doesn't start or stop, it fails.
        self::assertNotNull($this->startTime, 'Test timing was started');
        self::assertNotNull($this->runTime, 'Test timing was stopped');
    }

    /**
     * begin timing
     */
    protected function startTiming() : void
    {
        $this->startTime = microtime(true);
    }

    /**
     * end timing
     */
    protected function stopTiming() : void
    {
        $this->runTime = microtime(true) - $this->startTime;
    }

    /**
     * @return float elapsed test execution time
     */
    public function getTime() : float
    {
        return $this->runTime;
    }
}
