<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Driver\PDOException;
use Doctrine\Tests\DbalTestCase;

class PDOExceptionTest extends DbalTestCase
{
    const ERROR_CODE = 666;

    const MESSAGE = 'PDO Exception';

    const SQLSTATE = 28000;

    /**
     * The PDO exception wrapper under test.
     *
     * @var \Doctrine\DBAL\Driver\PDOException
     */
    private $exception;

    /**
     * The wrapped PDO exception mock.
     *
     * @var \PDOException|\PHPUnit_Framework_MockObject_MockObject
     */
    private $wrappedExceptionMock;

    protected function setUp()
    {
        if ( ! extension_loaded('PDO')) {
            $this->markTestSkipped('PDO is not installed.');
        }

        parent::setUp();

        $this->wrappedExceptionMock = $this->getMockBuilder('\PDOException')
            ->setConstructorArgs(array(self::MESSAGE, self::SQLSTATE))
            ->getMock();

        $this->wrappedExceptionMock->errorInfo = array(self::SQLSTATE, self::ERROR_CODE);

        $this->exception = new PDOException($this->wrappedExceptionMock);
    }

    public function testReturnsCode()
    {
        $this->assertSame(self::SQLSTATE, $this->exception->getCode());
    }

    public function testReturnsErrorCode()
    {
        $this->assertSame(self::ERROR_CODE, $this->exception->getErrorCode());
    }

    public function testReturnsMessage()
    {
        $this->assertSame(self::MESSAGE, $this->exception->getMessage());
    }

    public function testReturnsSQLState()
    {
        $this->assertSame(self::SQLSTATE, $this->exception->getSQLState());
    }

    public function testOriginalExceptionIsInChain()
    {
        $this->assertSame($this->wrappedExceptionMock, $this->exception->getPrevious());
    }
}
