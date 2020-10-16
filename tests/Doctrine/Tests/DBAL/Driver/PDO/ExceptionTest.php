<?php

namespace Doctrine\Tests\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\Tests\DbalTestCase;
use PDOException;

/**
 * @requires extension pdo
 */
class ExceptionTest extends DbalTestCase
{
    public const ERROR_CODE = 666;

    public const MESSAGE = 'PDO Exception';

    public const SQLSTATE = 28000;

    /**
     * The PDO exception wrapper under test.
     *
     * @var Exception
     */
    private $exception;

    /**
     * The wrapped PDO exception mock.
     *
     * @var PDOException
     */
    private $wrappedException;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wrappedException = new PDOException(self::MESSAGE, self::SQLSTATE);

        $this->wrappedException->errorInfo = [self::SQLSTATE, self::ERROR_CODE];

        $this->exception = new Exception($this->wrappedException);
    }

    public function testReturnsCode(): void
    {
        self::assertSame(self::SQLSTATE, $this->exception->getCode());
    }

    public function testReturnsErrorCode(): void
    {
        self::assertSame(self::ERROR_CODE, $this->exception->getErrorCode());
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
