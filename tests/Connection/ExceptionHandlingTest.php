<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Exception as DriverException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function chr;
use function fopen;

final class ExceptionHandlingTest extends TestCase
{
    /** @var Connection */
    private $connection;

    /** @var ExceptionConverter&MockObject */
    private $exceptionConverter;

    protected function setUp(): void
    {
        $this->exceptionConverter = $this->createMock(ExceptionConverter::class);

        $this->connection = new Connection([], $this->createConfiguredMock(Driver::class, [
            'getExceptionConverter' => $this->exceptionConverter,
        ]));
    }

    public function testDriverExceptionDuringQueryAcceptsBinaryData(): void
    {
        $this->exceptionConverter->expects(self::once())
            ->method('convert')
            ->with(self::stringContains('with params ["ABC", "\x80"]'));

        $this->connection->convertExceptionDuringQuery(
            $this->createMock(DriverException::class),
            '',
            ['ABC', chr(128)]
        );
    }

    public function testDriverExceptionDuringQueryAcceptsResource(): void
    {
        $this->exceptionConverter->expects(self::once())
            ->method('convert')
            ->with(self::stringContains('Resource'));

        $this->connection->convertExceptionDuringQuery(
            $this->createMock(DriverException::class),
            'INSERT INTO file (`content`) VALUES (?)',
            [
                1 => fopen(__FILE__, 'r'),
            ]
        );
    }
}
