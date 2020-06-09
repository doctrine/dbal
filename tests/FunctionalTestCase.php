<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Exception;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
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

abstract class FunctionalTestCase extends TestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     *
     * @var Connection|null
     */
    private static $sharedConnection;

    /** @var Connection */
    protected $connection;

    /** @var DebugStack */
    protected $sqlLoggerStack;

    protected function resetSharedConn(): void
    {
        if (self::$sharedConnection === null) {
            return;
        }

        self::$sharedConnection->close();
        self::$sharedConnection = null;
    }

    protected function setUp(): void
    {
        $this->sqlLoggerStack = new DebugStack();

        if (! isset(self::$sharedConnection)) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        $this->connection = self::$sharedConnection;

        $this->connection->getConfiguration()->setSQLLogger($this->sqlLoggerStack);
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    protected function onNotSuccessfulTest(Throwable $t): void
    {
        if ($t instanceof AssertionFailedError) {
            throw $t;
        }

        if (count($this->sqlLoggerStack->queries) > 0) {
            $queries = '';
            $i       = count($this->sqlLoggerStack->queries);
            foreach (array_reverse($this->sqlLoggerStack->queries) as $query) {
                $params   = array_map(static function ($p): string {
                    if (is_object($p)) {
                        return get_class($p);
                    }

                    if (is_scalar($p)) {
                        return "'" . $p . "'";
                    }

                    return var_export($p, true);
                }, $query['params'] ?? []);
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

            throw new Exception($message, (int) $t->getCode(), $t);
        }

        throw $t;
    }
}
