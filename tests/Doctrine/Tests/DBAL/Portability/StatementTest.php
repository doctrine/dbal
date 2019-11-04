<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Portability;

use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Statement;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use function iterator_to_array;

class StatementTest extends DbalTestCase
{
    /** @var Connection|MockObject */
    protected $conn;

    /** @var Statement */
    protected $stmt;

    /** @var DriverStatement|MockObject */
    protected $wrappedStmt;

    /**
     * {@inheritdoc}
     */
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

        $this->wrappedStmt->expects($this->once())
            ->method('bindParam')
            ->with($column, $variable, $type, $length);

        $this->stmt->bindParam($column, $variable, $type, $length);
    }

    public function testBindValue() : void
    {
        $param = 'myparam';
        $value = 'myvalue';
        $type  = ParameterType::STRING;

        $this->wrappedStmt->expects($this->once())
            ->method('bindValue')
            ->with($param, $value, $type);

        $this->stmt->bindValue($param, $value, $type);
    }

    public function testCloseCursor() : void
    {
        $this->wrappedStmt->expects($this->once())
            ->method('closeCursor');

        $this->stmt->closeCursor();
    }

    public function testColumnCount() : void
    {
        $columnCount = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('columnCount')
            ->will($this->returnValue($columnCount));

        self::assertSame($columnCount, $this->stmt->columnCount());
    }

    public function testExecute() : void
    {
        $params = [
            'foo',
            'bar',
        ];

        $this->wrappedStmt->expects($this->once())
            ->method('execute')
            ->with($params);

        $this->stmt->execute($params);
    }

    public function testSetFetchMode() : void
    {
        $fetchMode = FetchMode::CUSTOM_OBJECT;
        $arg1      = 'MyClass';
        $arg2      = [1, 2];

        $this->wrappedStmt->expects($this->once())
            ->method('setFetchMode')
            ->with($fetchMode, $arg1, $arg2)
            ->will($this->returnValue(true));

        $re = new ReflectionProperty($this->stmt, 'defaultFetchMode');
        $re->setAccessible(true);

        self::assertSame(FetchMode::MIXED, $re->getValue($this->stmt));
        $this->stmt->setFetchMode($fetchMode, $arg1, $arg2);
        self::assertSame($fetchMode, $re->getValue($this->stmt));
    }

    public function testGetIterator() : void
    {
        $this->wrappedStmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls('foo', 'bar', false);

        self::assertSame(['foo', 'bar'], iterator_to_array($this->stmt->getIterator()));
    }

    public function testRowCount() : void
    {
        $rowCount = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue($rowCount));

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
