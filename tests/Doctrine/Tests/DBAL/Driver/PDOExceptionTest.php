<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\PDOException;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @requires extension pdo
 */
class PDOExceptionTest extends DbalTestCase
{
    public const ERROR_CODE = 666;

    public const MESSAGE = 'PDO Exception';

    public const SQLSTATE = 'HY000';

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
        parent::setUp();

        $this->wrappedException = new \PDOException(self::MESSAGE);

        $this->wrappedException->errorInfo = [self::SQLSTATE, self::ERROR_CODE];

        $this->exception = new PDOException($this->wrappedException);
    }

    public function testReturnsCode()
    {
        self::assertSame(self::ERROR_CODE, $this->exception->getCode());
    }

    public function testReturnsMessage()
    {
        self::assertSame(self::MESSAGE, $this->exception->getMessage());
    }

    public function testReturnsSQLState()
    {
        self::assertSame(self::SQLSTATE, $this->exception->getSQLState());
    }

    public function testOriginalExceptionIsInChain()
    {
        self::assertSame($this->wrappedException, $this->exception->getPrevious());
    }
}
