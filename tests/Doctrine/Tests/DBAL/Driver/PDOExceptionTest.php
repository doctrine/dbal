<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\PDOException;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use function extension_loaded;

class PDOExceptionTest extends DbalTestCase
{
    public const ERROR_CODE = 666;

    public const MESSAGE = 'PDO Exception';

    public const SQLSTATE = 28000;

    /**
     * The PDO exception wrapper under test.
     *
     * @var PDOException
     */
    private $exception;

    /**
     * The wrapped PDO exception mock.
     *
     * @var \PDOException|MockObject
     */
    private $wrappedException;

    protected function setUp() : void
    {
        if (! extension_loaded('PDO')) {
            $this->markTestSkipped('PDO is not installed.');
        }

        parent::setUp();

        $this->wrappedException = new \PDOException(self::MESSAGE, self::SQLSTATE);

        $this->wrappedException->errorInfo = [self::SQLSTATE, self::ERROR_CODE];

        $this->exception = new PDOException($this->wrappedException);
    }

    public function testReturnsCode() : void
    {
        self::assertSame(self::SQLSTATE, $this->exception->getCode());
    }

    public function testReturnsErrorCode() : void
    {
        self::assertSame(self::ERROR_CODE, $this->exception->getErrorCode());
    }

    public function testReturnsMessage() : void
    {
        self::assertSame(self::MESSAGE, $this->exception->getMessage());
    }

    public function testReturnsSQLState() : void
    {
        self::assertSame(self::SQLSTATE, $this->exception->getSQLState());
    }

    public function testOriginalExceptionIsInChain() : void
    {
        self::assertSame($this->wrappedException, $this->exception->getPrevious());
    }
}
