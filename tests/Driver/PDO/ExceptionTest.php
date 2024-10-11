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

    public function testExposesUnderlyingErrorOnOracle(): void
    {
        $pdoException = new PDOException(<<<'TEXT'
OCITransCommit: ORA-02091: transaction rolled back
ORA-00001: unique constraint (DOCTRINE.C1_UNIQUE) violated
 (/private/tmp/php-20211003-35441-1sggrmq/php-8.0.11/ext/pdo_oci/oci_driver.c:410)
TEXT);

        $pdoException->errorInfo = [self::SQLSTATE, 2091,

        ];

        $exception = Exception::new($pdoException);

        self::assertSame(1, $exception->getCode());
        self::assertStringContainsString(
            'unique constraint (DOCTRINE.C1_UNIQUE) violated',
            $exception->getMessage(),
        );
    }
}
