<?php

namespace Doctrine\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Exception;
use PHPUnit\Framework\AssertionFailedError;
use Throwable;

use function array_map;
use function array_reverse;
use function count;
use function get_class;
use function implode;
use function is_object;
use function is_scalar;
use function strpos;
use function var_export;

use const PHP_EOL;

abstract class DbalFunctionalTestCase extends DbalTestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     *
     * @var Connection|null
     */
    private static $sharedConnection;

    /** @var Connection */
    protected $connection;

    /** @var DebugStack|null */
    protected $sqlLoggerStack;

    /**
     * Whether the shared connection could be reused by subsequent tests.
     *
     * @var bool
     */
    private $isConnectionReusable = true;

    /**
     * Mark shared connection not reusable for subsequent tests.
     *
     * Should be called by the tests that modify configuration configuration
     * or alter the connection state in another way that may impact other tests.
     */
    protected function markConnectionNotReusable(): void
    {
        $this->isConnectionReusable = false;
    }

    protected function setUp(): void
    {
        if (self::$sharedConnection === null) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        $this->connection = self::$sharedConnection;

        $this->sqlLoggerStack = new DebugStack();
        $this->connection->getConfiguration()->setSQLLogger($this->sqlLoggerStack);
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        if ($this->isConnectionReusable) {
            return;
        }

        if (self::$sharedConnection !== null) {
            self::$sharedConnection->close();
            self::$sharedConnection = null;
        }

        // Make sure the connection is no longer available to the test.
        // Otherwise, there is a chance that a teardown method of the test will reconnect
        // (e.g. to drop a table), and then this reopened connection will remain open and attached to the PHPUnit result
        // until the end of the suite leaking connection resources, while subsequent tests will use
        // the newly established shared connection.
        unset($this->connection);

        $this->isConnectionReusable = true;
    }

    protected function onNotSuccessfulTest(Throwable $t): void
    {
        if ($t instanceof AssertionFailedError) {
            throw $t;
        }

        if ($this->sqlLoggerStack !== null && count($this->sqlLoggerStack->queries) > 0) {
            $queries = '';
            $i       = count($this->sqlLoggerStack->queries);
            foreach (array_reverse($this->sqlLoggerStack->queries) as $query) {
                $params = array_map(
                    /**
                     * @param mixed $p
                     */
                    static function ($p) {
                        if (is_object($p)) {
                            return get_class($p);
                        }

                        if (is_scalar($p)) {
                            return "'" . $p . "'";
                        }

                        return var_export($p, true);
                    },
                    $query['params'] ?: []
                );

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

            $message = '[' . get_class($t) . '] ' . $t->getMessage() . PHP_EOL . PHP_EOL
                . 'With queries:' . PHP_EOL . $queries . PHP_EOL . 'Trace:' . PHP_EOL . $traceMsg;

            throw new Exception($message, (int) $t->getCode(), $t);
        }

        throw $t;
    }
}
