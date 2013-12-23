<?php

namespace Doctrine\Tests\DBAL\Portability;

use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Statement;

require_once __DIR__ . '/../../TestInit.php';

class StatementTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Portability\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $conn;

    /**
     * @var \Doctrine\DBAL\Portability\Statement
     */
    protected $stmt;

    /**
     * @var \Doctrine\DBAL\Driver\Statement|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $wrappedStmt;

    /**
     * {@inheritdoc}
     */
    public function setUp()
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
        $type     = \PDO::PARAM_STR;
        $length   = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('bindParam')
            ->with($column, $variable, $type, $length)
            ->will($this->returnValue(true));

        $this->assertTrue($this->stmt->bindParam($column, $variable, $type, $length));
    }

    public function testBindValue()
    {
        $param = 'myparam';
        $value = 'myvalue';
        $type  = \PDO::PARAM_STR;

        $this->wrappedStmt->expects($this->once())
            ->method('bindValue')
            ->with($param, $value, $type)
            ->will($this->returnValue(true));

        $this->assertTrue($this->stmt->bindValue($param, $value, $type));
    }

    public function testCloseCursor()
    {
        $this->wrappedStmt->expects($this->once())
            ->method('closeCursor')
            ->will($this->returnValue(true));

        $this->assertTrue($this->stmt->closeCursor());
    }

    public function testColumnCount()
    {
        $columnCount = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('columnCount')
            ->will($this->returnValue($columnCount));

        $this->assertSame($columnCount, $this->stmt->columnCount());
    }

    public function testErrorCode()
    {
        $errorCode = '666';

        $this->wrappedStmt->expects($this->once())
            ->method('errorCode')
            ->will($this->returnValue($errorCode));

        $this->assertSame($errorCode, $this->stmt->errorCode());
    }

    public function testErrorInfo()
    {
        $errorInfo = array('666', 'Evil error.');

        $this->wrappedStmt->expects($this->once())
            ->method('errorInfo')
            ->will($this->returnValue($errorInfo));

        $this->assertSame($errorInfo, $this->stmt->errorInfo());
    }

    public function testExecute()
    {
        $params = array(
            'foo',
            'bar'
        );

        $this->wrappedStmt->expects($this->once())
            ->method('execute')
            ->with($params)
            ->will($this->returnValue(true));

        $this->assertTrue($this->stmt->execute($params));
    }

    public function testSetFetchMode()
    {
        $fetchMode = \PDO::FETCH_CLASS;
        $arg1      = 'MyClass';
        $arg2      = array(1, 2);

        $this->wrappedStmt->expects($this->once())
            ->method('setFetchMode')
            ->with($fetchMode, $arg1, $arg2)
            ->will($this->returnValue(true));

        $this->assertAttributeSame(\PDO::FETCH_BOTH, 'defaultFetchMode', $this->stmt);
        $this->assertTrue($this->stmt->setFetchMode($fetchMode, $arg1, $arg2));
        $this->assertAttributeSame($fetchMode, 'defaultFetchMode', $this->stmt);
    }

    public function testGetIterator()
    {
        $data = array(
            'foo' => 'bar',
            'bar' => 'foo'
        );

        $this->wrappedStmt->expects($this->once())
            ->method('fetchAll')
            ->will($this->returnValue($data));

        $this->assertEquals(new \ArrayIterator($data), $this->stmt->getIterator());
    }

    public function testRowCount()
    {
        $rowCount = 666;

        $this->wrappedStmt->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue($rowCount));

        $this->assertSame($rowCount, $this->stmt->rowCount());
    }

    /**
     * @return \Doctrine\DBAL\Portability\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createConnection()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Portability\Connection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param \Doctrine\DBAL\Driver\Statement       $wrappedStatement
     * @param \Doctrine\DBAL\Portability\Connection $connection
     *
     * @return \Doctrine\DBAL\Portability\Statement
     */
    protected function createStatement(\Doctrine\DBAL\Driver\Statement $wrappedStatement, Connection $connection)
    {
        return new Statement($wrappedStatement, $connection);
    }

    /**
     * @return \Doctrine\DBAL\Driver\Statement|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createWrappedStatement()
    {
        return $this->getMock('Doctrine\Tests\Mocks\DriverStatementMock');
    }
}
