<?php

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Converter;
use Doctrine\DBAL\Portability\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatementTest extends TestCase
{
    protected Statement $stmt;

    /** @var DriverStatement&MockObject */
    protected DriverStatement $wrappedStmt;

    protected function setUp(): void
    {
        $this->wrappedStmt = $this->createMock(DriverStatement::class);
        $converter         = new Converter(false, false, null);
        $this->stmt        = new Statement($this->wrappedStmt, $converter);
    }

    public function testBindParam(): void
    {
        $column   = 'mycolumn';
        $variable = 'myvalue';
        $type     = ParameterType::STRING;
        $length   = 666;

        $this->wrappedStmt->expects(self::once())
            ->method('bindParam')
            ->with($column, $variable, $type, $length)
            ->willReturn(true);

        self::assertTrue($this->stmt->bindParam($column, $variable, $type, $length));
    }

    public function testBindValue(): void
    {
        $param = 'myparam';
        $value = 'myvalue';
        $type  = ParameterType::STRING;

        $this->wrappedStmt->expects(self::once())
            ->method('bindValue')
            ->with($param, $value, $type)
            ->willReturn(true);

        self::assertTrue($this->stmt->bindValue($param, $value, $type));
    }

    public function testExecute(): void
    {
        $params = [
            'foo',
            'bar',
        ];

        $this->wrappedStmt->expects(self::once())
            ->method('execute')
            ->with($params);

        $this->stmt->execute($params);
    }

    /** @return Connection&MockObject */
    protected function createConnection()
    {
        return $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
