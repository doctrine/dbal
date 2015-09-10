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
        if ($test instanceof \Doctrine\Tests\DbalPerformanceTestCase)
        {
            $class = str_replace('Doctrine\Tests\DBAL\Performance\\', '', get_class($test));
            $this->timings[$class . "::" . $test->getName(true)] = $test->getTime();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function endTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        if (!empty($this->timings)) {
            print("\n");
            foreach($this->timings as $test => $time) {
                printf("%s: %6.3f\n", $test, $time);
            }
            $this->timings = [];
        }
    }
}
