<?php

namespace Doctrine\Tests;

class DbalFunctionalTestCase extends DbalTestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     *
     * @var \Doctrine\DBAL\Connection
     */
    private static $sharedConn;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * @var \Doctrine\DBAL\Logging\DebugStack
     */
    protected $sqlLoggerStack;

    protected function resetSharedConn()
    {
        if (self::$sharedConn) {
            self::$sharedConn->close();
            self::$sharedConn = null;
        }
    }

    protected function setUp()
    {
        if ( ! isset(self::$sharedConn)) {
            self::$sharedConn = TestUtil::getConnection();
        }
        $this->conn = self::$sharedConn;

        $this->sqlLoggerStack = new \Doctrine\DBAL\Logging\DebugStack();
        $this->conn->getConfiguration()->setSQLLogger($this->sqlLoggerStack);
    }

    protected function tearDown()
    {
        while ($this->conn->isTransactionActive()) {
            $this->conn->rollBack();
        }
    }

    protected function onNotSuccessfulTest(\Throwable $t)
    {
        if ($t instanceof \PHPUnit\Framework\AssertionFailedError) {
            throw $t;
        }

        if(isset($this->sqlLoggerStack->queries) && count($this->sqlLoggerStack->queries)) {
            $queries = "";
            $i = count($this->sqlLoggerStack->queries);
            foreach (array_reverse($this->sqlLoggerStack->queries) as $query) {
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

            throw new \Exception($message, (int)$t->getCode(), $t);
        }
        throw $t;
    }
}
