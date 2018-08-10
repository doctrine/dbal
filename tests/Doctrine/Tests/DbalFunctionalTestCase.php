<?php

namespace Doctrine\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use PHPUnit\Framework\AssertionFailedError;
use const PHP_EOL;
use function array_map;
use function array_reverse;
use function count;
use function get_class;
use function implode;
use function is_object;
use function is_scalar;
use function strpos;
use function var_export;

class DbalFunctionalTestCase extends DbalTestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     *
     * @var Connection|null
     */
    private static $_sharedConn;

    /** @var mixed */
    protected $_conn;

    /** @var DebugStack */
    protected $_sqlLoggerStack;

    protected function resetSharedConn()
    {
        if (! self::$_sharedConn) {
            return;
        }

        self::$_sharedConn->close();
        self::$_sharedConn = null;
    }

    protected function setUp()
    {
        if (! isset(self::$_sharedConn)) {
            self::$_sharedConn = TestUtil::getConnection();
        }
        $this->_conn = self::$_sharedConn;

        $this->_sqlLoggerStack = new DebugStack();
        $this->_conn->getConfiguration()->setSQLLogger($this->_sqlLoggerStack);
    }

    protected function tearDown()
    {
        while ($this->_conn->isTransactionActive()) {
            $this->_conn->rollBack();
        }
    }

    protected function onNotSuccessfulTest(\Throwable $t)
    {
        if ($t instanceof AssertionFailedError) {
            throw $t;
        }

        if (isset($this->_sqlLoggerStack->queries) && count($this->_sqlLoggerStack->queries)) {
            $queries = '';
            $i       = count($this->_sqlLoggerStack->queries);
            foreach (array_reverse($this->_sqlLoggerStack->queries) as $query) {
                $params = array_map(function ($p) {
                    if (is_object($p)) {
                        return get_class($p);
                    } elseif (is_scalar($p)) {
                        return "'" . $p . "'";
                    }

                    return var_export($p, true);
                }, $query['params'] ?: []);
                $queries .= $i . ". SQL: '" . $query['sql'] . "' Params: " . implode(', ', $params) . PHP_EOL;
                $i--;
            }

            $trace    = $t->getTrace();
            $traceMsg = '';
            foreach ($trace as $part) {
                if (! isset($part['file'])) {
                    continue;
                }

                if (strpos($part['file'], 'PHPUnit/') !== false) {
                    // Beginning with PHPUnit files we don't print the trace anymore.
                    break;
                }

                $traceMsg .= $part['file'] . ':' . $part['line'] . PHP_EOL;
            }

            $message = '[' . get_class($t) . '] ' . $t->getMessage() . PHP_EOL . PHP_EOL . 'With queries:' . PHP_EOL . $queries . PHP_EOL . 'Trace:' . PHP_EOL . $traceMsg;

            throw new \Exception($message, (int) $t->getCode(), $t);
        }
        throw $t;
    }
}
