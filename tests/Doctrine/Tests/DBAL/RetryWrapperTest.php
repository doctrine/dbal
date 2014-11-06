<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\RetryWrapper;

class RetryWrapperTest extends \Doctrine\Tests\DbalTestCase
{
    public function testConstructor()
    {
        $retryWrapper = new RetryWrapper($this->getRetryCallable());
        $this->assertNull($retryWrapper->getRetries());
    }

    public function testWithoutRetry()
    {
        $retryWrapper = new RetryWrapper($this->getRetryCallable(0));
        $this->assertSame('return-value', $retryWrapper());
        $this->assertSame(0, $retryWrapper->getRetries());
    }

    public function testExecuteOnceWithZeroMaxRetries()
    {
        $retryWrapper = new RetryWrapper($this->getRetryCallable(0), 0);
        $this->assertSame('return-value', $retryWrapper(), 'Callable must be executed when max retries is 0');
        $this->assertSame(0, $retryWrapper->getRetries());
    }

    public function testExecuteOnceWithNegativeMaxRetries()
    {
        $retryWrapper = new RetryWrapper($this->getRetryCallable(0), -3);
        $this->assertSame('return-value', $retryWrapper(), 'Callable must be executed when max retries is negative');
        $this->assertSame(0, $retryWrapper->getRetries());
    }

    public function testRetrySucceedsWithDefaultMaxRetries()
    {
        $retryWrapper = new RetryWrapper($this->getRetryCallable(2));
        $this->assertSame('return-value', $retryWrapper());
        $this->assertSame(2, $retryWrapper->getRetries());
    }

    public function testRetryFailsAfterMaxRetries()
    {
        $retryWrapper = new RetryWrapper($this->getRetryCallable(2), 1);

        try {
            $retryWrapper();
            $this->fail('Wrapper should rethrow exception when max retries has been reached.');
        } catch (RetryableException $e) {
            $this->assertSame('Deadlock', $e->getMessage());
            $this->assertSame(1, $retryWrapper->getRetries());
        }
    }

    public function testNoRetryOnGenericError()
    {
        $retryWrapper = new RetryWrapper(array(new RetryCallableExample(), 'retryableErrorFollowedByGenericError'), 5);

        try {
            $retryWrapper();
            $this->fail('Wrapper should rethrow exception when not retryable.');
        } catch (DBALException $e) {
            $this->assertSame('Constraint violation', $e->getMessage());
            $this->assertSame(1, $retryWrapper->getRetries(), 'One retry, then abort');
        }
    }

    public function testInvokeWithParams()
    {
        $retryWrapper = new RetryWrapper(__NAMESPACE__ . '\RetryCallableExample::staticMethodWithParams');
        $this->assertSame('foobar', $retryWrapper('foo', 'bar'));
    }

    private function getRetryCallable($succeedAfterCalls = 0)
    {
        $callableObject = new RetryCallableExample($succeedAfterCalls);

        return array($callableObject, 'succeedAsConfigured');
    }
}

class RetryCallableExample
{
    private $succeedAfterCalls;
    private $executionCount = 0;

    public function __construct($succeedAfterCalls = 0)
    {
        $this->succeedAfterCalls = $succeedAfterCalls;
    }

    public function succeedAsConfigured()
    {
        $this->executionCount++;

        if ($this->executionCount > $this->succeedAfterCalls) {
            return 'return-value';
        }

        throw new DeadlockException(
            'Deadlock',
            new DummyDriverException()
        );
    }

    public function retryableErrorFollowedByGenericError()
    {
        $this->executionCount++;

        if ($this->executionCount > 1) {
            throw new DBALException(
                'Constraint violation'
            );
        }

        throw new DeadlockException(
            'Deadlock',
            new DummyDriverException()
        );
    }

    public static function staticMethodWithParams($param1, $param2)
    {
        return $param1 . $param2;
    }
}

class DummyDriverException implements DriverException
{
    public function getErrorCode()
    {
        return 'code';
    }

    public function getMessage()
    {
        return 'message';
    }

    public function getSQLState()
    {
        return 'state';
    }
}
