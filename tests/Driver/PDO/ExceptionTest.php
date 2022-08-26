<?php

namespace Doctrine\DBAL\Tests\Driver\PDO;

use Doctrine\DBAL\Driver\PDO\Exception;
use PDOException;
use PHPUnit\Framework\TestCase;

/** @requires extension pdo */
class ExceptionTest extends TestCase
{
    public const ERROR_CODE = 666;

    public const MESSAGE = 'PDO Exception';

    public const SQLSTATE = 'HY000';

    /**
     * The PDO exception wrapper under test.
     */
    private Exception $exception;

    /**
     * The wrapped PDO exception mock.
     */
    private PDOException $wrappedException;

    protected function setUp(): void
    {
        $this->wrappedException = new PDOException(self::MESSAGE);

        $this->wrappedException->errorInfo = [self::SQLSTATE, self::ERROR_CODE];

        $this->exception = Exception::new($this->wrappedException);
    }

    public function testReturnsCode(): void
    {
        self::assertSame(self::ERROR_CODE, $this->exception->getCode());
    }

    public function testReturnsMessage(): void
    {
        self::assertSame(self::MESSAGE, $this->exception->getMessage());
    }

    public function testReturnsSQLState(): void
    {
        self::assertSame(self::SQLSTATE, $this->exception->getSQLState());
    }

    public function testOriginalExceptionIsInChain(): void
    {
        self::assertSame($this->wrappedException, $this->exception->getPrevious());
    }
}
