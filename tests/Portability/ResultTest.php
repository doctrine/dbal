<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Portability\Converter;
use Doctrine\DBAL\Portability\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testRowCount(): void
    {
        $driverResult = $this->createMock(DriverResult::class);
        $driverResult->expects(self::once())
            ->method('rowCount')
            ->willReturn(666);

        $result = $this->newResult($driverResult);

        self::assertSame(666, $result->rowCount());
    }

    public function testColumnCount(): void
    {
        $driverResult = $this->createMock(DriverResult::class);
        $driverResult->expects(self::once())
            ->method('columnCount')
            ->willReturn(666);

        $result = $this->newResult($driverResult);

        self::assertSame(666, $result->columnCount());
    }

    public function testFree(): void
    {
        $driverResult = $this->createMock(DriverResult::class);
        $driverResult->expects(self::once())
            ->method('free');

        $this->newResult($driverResult)->free();
    }

    private function newResult(DriverResult $driverResult): Result
    {
        return new Result(
            $driverResult,
            new Converter(false, false, null)
        );
    }
}
