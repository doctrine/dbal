<?php

namespace Doctrine\Tests;

/**
 * Listener for collecting and reporting results of performance tests
 *
 * @author Bill Schaller
 */
class DbalPerformanceTestListener extends \PHPUnit_Framework_BaseTestListener
{
    private $timings = [];

    /**
     * {@inheritdoc}
     */
    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        // This listener only applies to performance tests.
        if ($test instanceof \Doctrine\Tests\DbalPerformanceTestCase)
        {
            // Identify perf tests by class, method, and dataset
            $class = str_replace('Doctrine\Tests\DBAL\Performance\\', '', get_class($test));

            // Store timing data for each test in the order they were run.
            $this->timings[$class . "::" . $test->getName(true)] = $test->getTime();
        }
    }

    /**
     * Report performance test timings.
     *
     * Note: __destruct is used here because PHPUnit doesn't have a
     * 'All tests over' hook.
     */
    public function __destruct()
    {
        if (!empty($this->timings)) {
            // Report timings.
            print("\n\nPerformance test results:\n\n");

            foreach($this->timings as $test => $time) {
                printf("%s: %.3f\n", $test, $time);
            }
        }
    }
}
