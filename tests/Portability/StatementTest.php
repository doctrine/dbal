<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Portability\Converter;
use Doctrine\DBAL\Portability\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatementTest extends TestCase
{
    protected Statement $stmt;
    protected DriverStatement&MockObject $wrappedStmt;

    protected function setUp(): void
    {
        $this->wrappedStmt = $this->createMock(DriverStatement::class);
        $converter         = new Converter(false, false, null);
        $this->stmt        = new Statement($this->wrappedStmt, $converter);
    }

    public function testBindValue(): void
    {
        $param = 'myparam';
        $value = 'myvalue';
        $type  = ParameterType::STRING;

        $this->wrappedStmt->expects(self::once())
            ->method('bindValue')
            ->with($param, $value, $type);

        $this->stmt->bindValue($param, $value, $type);
    }

    public function testExecute(): void
    {
        $this->wrappedStmt->expects(self::once())
            ->method('execute');

        $this->stmt->execute();
    }
}
