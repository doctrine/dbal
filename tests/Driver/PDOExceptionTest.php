<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Driver\PDOException;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension pdo
 */
class PDOExceptionTest extends TestCase
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
     * @var \PDOException
     */
    private $wrappedException;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wrappedException = new \PDOException(self::MESSAGE);

        $this->wrappedException->errorInfo = [self::SQLSTATE, self::ERROR_CODE];

        $this->exception = new PDOException($this->wrappedException);
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
