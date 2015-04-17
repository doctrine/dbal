<?php

namespace Doctrine\Tests;

/**
 * Base testcase class for all dbal testcases.
 */
class DbalTestCase extends DoctrineTestCase
{

    /**
     * Utility function to skip tests on specified platforms or drivers
     * 
     * @param string|array $driverOrPlatformName    Or more driver or platform names
     * @param string $message Log message           Reason if any
     * @param bool $markIncomplete                  Mark as incomplete instead of skipped
     * @param string|\Doctrine\DBAL\Driver|\Doctrine\DBAL\Platforms\AbstractPlatform|\Doctrine\DBAL\Connection null $checkbase
     *                                              Name or object to check against. If not specified, the configured
     *                                              standard test driver is checked
     * @return boolean
     */
    protected function skipOnDriverOrPlatform($driverOrPlatformName, $message = null, $markIncomplete = false, $checkbase = null)
    {
        $result = false;

        if (is_object($checkbase)) {
            $cb = $checkbase;

            if ($cb instanceof \Doctrine\DBAL\Connection) {
                $cb = $cb->getDriver();
            }

            if ($cb instanceof \Doctrine\DBAL\Driver) {
                if (in_array($cb->getName(), (array) $driverOrPlatformName)) {
                    $result = true;
                } else {
                    $cb = $cb->getDatabasePlatform();
                }
            }
            if (!$result && $cb instanceof \Doctrine\DBAL\Platforms\AbstractPlatform) {
                $result = in_array($cb->getName(), (array) $driverOrPlatformName);
            }
        } else {
            if (!$result) {
                if (!isset($checkbase))
                    $checkbase = isset($GLOBALS['db_type']) ? $checkbase : null;
                $result = in_array($checkbase, (array) $driverOrPlatformName);
            }
        }

        if ($result) {
            if ($markIncomplete) {
                $this->markTestIncomplete(
                        'Incomplete on ' . implode(', ', (array) $driverOrPlatformName) .
                        (isset($message) ? ' (' . $message . ')' : ''));
            } else {
                $this->markTestSkipped(
                        'Incomplete on ' . implode(', ', (array) $driverOrPlatformName) .
                        (isset($message) ? ' (' . $message . ')' : ''));
            }
        }

        return false;
    }

}
