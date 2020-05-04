<?php

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function iterator_to_array;

class StatementTest extends TestCase
{
    /** @var Connection|MockObject */
    protected $conn;

    /** @var Statement */
    protected $stmt;

    /** @var DriverStatement|MockObject */
    protected $wrappedStmt;

    protected function setUp() : void
    {
        $this->wrappedStmt = $this->createMock(DriverStatement::class);
        $this->conn        = $this->createConnection();
        $this->stmt        = $this->createStatement($this->wrappedStmt, $this->conn);
    }

    /**
     * @group DBAL-726
     */
    public function testBindParam() : void
    {
        $column   = 'mycolumn';
        $variable = 'myvalue';
        $type     = ParameterType::STRING;
        $length   = 666;

        $this->wrappedStmt->expects(self::once())
            ->method('bindParam')
            ->with($column, $variable, $type, $length)
            ->will(self::returnValue(true));

        self::assertTrue($this->stmt->bindParam($column, $variable, $type, $length));
    }

    public function testBindValue() : void
    {
        $param = 'myparam';
        $value = 'myvalue';
        $type  = ParameterType::STRING;

        $this->wrappedStmt->expects(self::once())
            ->method('bindValue')
            ->with($param, $value, $type)
            ->will(self::returnValue(true));

        self::assertTrue($this->stmt->bindValue($param, $value, $type));
    }

    public function testCloseCursor() : void
    {
        $this->wrappedStmt->expects(self::once())
            ->method('closeCursor')
            ->will(self::returnValue(true));

        self::assertTrue($this->stmt->closeCursor());
    }

    public function testColumnCount() : void
    {
        $columnCount = 666;

        $this->wrappedStmt->expects(self::once())
            ->method('columnCount')
            ->will(self::returnValue($columnCount));

        self::assertSame($columnCount, $this->stmt->columnCount());
    }

    public function testErrorCode() : void
    {
        $errorCode = '666';

        $this->wrappedStmt->expects(self::once())
            ->method('errorCode')
            ->will(self::returnValue($errorCode));

        self::assertSame($errorCode, $this->stmt->errorCode());
    }

    public function testErrorInfo() : void
    {
        $errorInfo = ['666', 'Evil error.'];

        $this->wrappedStmt->expects(self::once())
            ->method('errorInfo')
            ->will(self::returnValue($errorInfo));

        self::assertSame($errorInfo, $this->stmt->errorInfo());
    }

    public function testExecute() : void
    {
        $params = [
            'foo',
            'bar',
        ];

        $this->wrappedStmt->expects(self::once())
            ->method('execute')
            ->with($params)
            ->will(self::returnValue(true));

        self::assertTrue($this->stmt->execute($params));
    }

    public function testGetIterator() : void
    {
        $this->wrappedStmt->expects(self::exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls('foo', 'bar', false);

        self::assertSame(['foo', 'bar'], iterator_to_array($this->stmt->getIterator()));
    }

    public function testRowCount() : void
    {
        $rowCount = 666;

        $this->wrappedStmt->expects(self::once())
            ->method('rowCount')
            ->will(self::returnValue($rowCount));

        self::assertSame($rowCount, $this->stmt->rowCount());
    }

    /**
     * @return Connection|MockObject
     */
    protected function createConnection()
    {
        return $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function createStatement(DriverStatement $wrappedStatement, Connection $connection) : Statement
    {
        return new Statement($wrappedStatement, $connection);
    }
}
