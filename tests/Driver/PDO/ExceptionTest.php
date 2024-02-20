<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO;

use Doctrine\DBAL\Driver\PDO\Exception;
use PDOException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('pdo')]
class ExceptionTest extends TestCase
{
    private const ERROR_CODE = 666;

    private const MESSAGE = 'PDO Exception';

    private const SQLSTATE = 'HY000';

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
