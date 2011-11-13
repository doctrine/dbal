<?php

namespace Doctrine\Tests;

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

    protected function onNotSuccessfulTest(\Exception $e)
    {
        if ($e instanceof \PHPUnit_Framework_AssertionFailedError) {
            throw $e;
        }

        if(isset($this->_sqlLoggerStack->queries) && count($this->_sqlLoggerStack->queries)) {
            $queries = "";
            $i = count($this->_sqlLoggerStack->queries);
            foreach (array_reverse($this->_sqlLoggerStack->queries) AS $query) {
                $params = array_map(function($p) { if (is_object($p)) return get_class($p); else return "'".$p."'"; }, $query['params'] ?: array());
                $queries .= ($i+1).". SQL: '".$query['sql']."' Params: ".implode(", ", $params).PHP_EOL;
                $i--;
            }

            $trace = $e->getTrace();
            $traceMsg = "";
            foreach($trace AS $part) {
                if(isset($part['file'])) {
                    if(strpos($part['file'], "PHPUnit/") !== false) {
                        // Beginning with PHPUnit files we don't print the trace anymore.
                        break;
                    }

                    $traceMsg .= $part['file'].":".$part['line'].PHP_EOL;
                }
            }

            $message = "[".get_class($e)."] ".$e->getMessage().PHP_EOL.PHP_EOL."With queries:".PHP_EOL.$queries.PHP_EOL."Trace:".PHP_EOL.$traceMsg;

            throw new \Exception($message, (int)$e->getCode(), $e);
        }
        throw $e;
    }
}
