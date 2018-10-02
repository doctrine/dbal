<?php

namespace Doctrine\Tests\DBAL\Portability;

use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Statement;
use Doctrine\Tests\DbalTestCase;
use Doctrine\Tests\Mocks\DriverStatementMock;
use PHPUnit_Framework_MockObject_MockObject;
use function iterator_to_array;

class StatementTest extends DbalTestCase
{
    /** @var Connection|PHPUnit_Framework_MockObject_MockObject */
    protected $conn;

    /** @var Statement */
    protected $stmt;

    /** @var DriverStatement|PHPUnit_Framework_MockObject_MockObject */
    protected $wrappedStmt;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->wrappedStmt = $this->createWrappedStatement();
        $this->conn        = $this->createConnection();
        $this->stmt        = $this->createStatement($this->wrappedStmt, $this->conn);
    }

    /**
     * @group DBAL-726
     */
    public function testBindParam()
    {
        $column   = 'mycolumn';
        $variable = 'myvalue';
        $type     = ParameterType::STRING;
        $length   = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('bindParam')
            ->with($column, $variable, $type, $length)
            ->will($this->returnValue(true));

        self::assertTrue($this->stmt->bindParam($column, $variable, $type, $length));
    }

    public function testBindValue()
    {
        $param = 'myparam';
        $value = 'myvalue';
        $type  = ParameterType::STRING;

        $this->wrappedStmt->expects($this->once())
            ->method('bindValue')
            ->with($param, $value, $type)
            ->will($this->returnValue(true));

        self::assertTrue($this->stmt->bindValue($param, $value, $type));
    }

    public function testCloseCursor()
    {
        $this->wrappedStmt->expects($this->once())
            ->method('closeCursor')
            ->will($this->returnValue(true));

        self::assertTrue($this->stmt->closeCursor());
    }

    public function testColumnCount()
    {
        $columnCount = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('columnCount')
            ->will($this->returnValue($columnCount));

        self::assertSame($columnCount, $this->stmt->columnCount());
    }

    public function testErrorCode()
    {
        $errorCode = '666';

        $this->wrappedStmt->expects($this->once())
            ->method('errorCode')
            ->will($this->returnValue($errorCode));

        self::assertSame($errorCode, $this->stmt->errorCode());
    }

    public function testErrorInfo()
    {
        $errorInfo = ['666', 'Evil error.'];

        $this->wrappedStmt->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue($errorInfo));

        self::assertSame($errorInfo, $this->stmt->errorInfo());
    }

    public function testExecute()
    {
        $params = [
            'foo',
            'bar',
        ];

        $this->wrappedStmt->expects($this->once())
            ->method('execute')
            ->with($params)
            ->will($this->returnValue(true));

        self::assertTrue($this->stmt->execute($params));
    }

    public function testSetFetchMode()
    {
        $fetchMode = FetchMode::CUSTOM_OBJECT;
        $arg1      = 'MyClass';
        $arg2      = [1, 2];

        $this->wrappedStmt->expects($this->once())
            ->method('setFetchMode')
            ->with($fetchMode, $arg1, $arg2)
            ->will($this->returnValue(true));

        self::assertAttributeSame(FetchMode::MIXED, 'defaultFetchMode', $this->stmt);
        self::assertTrue($this->stmt->setFetchMode($fetchMode, $arg1, $arg2));
        self::assertAttributeSame($fetchMode, 'defaultFetchMode', $this->stmt);
    }

    public function testGetIterator()
    {
        $this->wrappedStmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls('foo', 'bar', false);

        self::assertSame(['foo', 'bar'], iterator_to_array($this->stmt->getIterator()));
    }

    public function testRowCount()
    {
        $rowCount = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue($rowCount));

        self::assertSame($rowCount, $this->stmt->rowCount());
    }

    /**
     * @return Connection|PHPUnit_Framework_MockObject_MockObject
     */
    protected function createConnection()
    {
        return $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Statement
     */
    protected function createStatement(DriverStatement $wrappedStatement, Connection $connection)
    {
        return new Statement($wrappedStatement, $connection);
    }

    /**
     * @return DriverStatement|PHPUnit_Framework_MockObject_MockObject
     */
    protected function createWrappedStatement()
    {
        return $this->createMock(DriverStatementMock::class);
    }
}
