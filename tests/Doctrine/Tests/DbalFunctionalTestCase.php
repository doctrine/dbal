<?php

namespace Doctrine\Tests;
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
     * @var \Doctrine\DBAL\Connection
     */
    private static $_sharedConn;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $_conn;

    /**
     * @var \Doctrine\DBAL\Logging\DebugStack
     */
    protected $_sqlLoggerStack;

    protected function resetSharedConn()
    {
        if (self::$_sharedConn) {
            self::$_sharedConn->close();
            self::$_sharedConn = null;
        }
    }

    protected function setUp()
    {
        if ( ! isset(self::$_sharedConn)) {
            self::$_sharedConn = TestUtil::getConnection();
        }
        $this->_conn = self::$_sharedConn;

        $this->_sqlLoggerStack = new \Doctrine\DBAL\Logging\DebugStack();
        $this->_conn->getConfiguration()->setSQLLogger($this->_sqlLoggerStack);
    }

    protected function tearDown()
    {
        if (! $this->_conn) {
            return;
        }

        while ($this->_conn->isTransactionActive()) {
            $this->_conn->rollBack();
        }
    }

    protected function onNotSuccessfulTest(\Throwable $t)
    {
        if ($t instanceof \PHPUnit\Framework\AssertionFailedError) {
            throw $t;
        }

        if(isset($this->_sqlLoggerStack->queries) && count($this->_sqlLoggerStack->queries)) {
            $queries = "";
            $i = count($this->_sqlLoggerStack->queries);
            foreach (array_reverse($this->_sqlLoggerStack->queries) as $query) {
                $params = array_map(function($p) {
                    if (is_object($p)) {
                        return get_class($p);
                    } elseif (is_scalar($p)) {
                        return "'".$p."'";
                    }

                    return var_export($p, true);
                }, $query['params'] ?: array());
                $queries .= $i.". SQL: '".$query['sql']."' Params: ".implode(", ", $params).PHP_EOL;
                $i--;
            }

            $trace = $t->getTrace();
            $traceMsg = "";
            foreach($trace as $part) {
                if(isset($part['file'])) {
                    if(strpos($part['file'], "PHPUnit/") !== false) {
                        // Beginning with PHPUnit files we don't print the trace anymore.
                        break;
                    }

                    $traceMsg .= $part['file'].":".$part['line'].PHP_EOL;
                }
            }

            $message = "[".get_class($t)."] ".$t->getMessage().PHP_EOL.PHP_EOL."With queries:".PHP_EOL.$queries.PHP_EOL."Trace:".PHP_EOL.$traceMsg;

            throw new \Exception($message, (int) $t->getCode(), $t);
        }
        throw $t;
    }
}
