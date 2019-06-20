<?php

namespace Doctrine\Tests;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use function get_class;
use function printf;
use function str_replace;

/**
 * Listener for collecting and reporting results of performance tests
 */
class DbalPerformanceTestListener implements TestListener
{
    use TestListenerDefaultImplementation;

    /** @var string[][] */
    private $timings = [];

    /**
     * {@inheritdoc}
     */
    public function endTest(Test $test, float $time) : void
    {
        // This listener only applies to performance tests.
        if (! ($test instanceof DbalPerformanceTestCase)) {
            return;
        }

        // we identify perf tests by class, method, and dataset
        $class = str_replace('\\Doctrine\\Tests\\DBAL\\Performance\\', '', get_class($test));

        if (! isset($this->timings[$class])) {
            $this->timings[$class] = [];
        }

        // Store timing data for each test in the order they were run.
        $this->timings[$class][$test->getName(true)] = $test->getTime();
    }

    /**
     * Report performance test timings.
     *
     * Note: __destruct is used here because PHPUnit doesn't have a
     * 'All tests over' hook.
     */
    public function __destruct()
    {
        if (empty($this->timings)) {
            return;
        }

        // Report timings.
        print "\nPerformance test results:\n\n";

        foreach ($this->timings as $class => $tests) {
            printf("%s:\n", $class);
            foreach ($tests as $test => $time) {
                printf("\t%s: %.3f seconds\n", $test, $time);
            }
        }
    }
}
