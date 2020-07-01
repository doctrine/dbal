<?php

namespace Doctrine\Tests\DBAL\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\DefaultExceptionConverter;
use Doctrine\DBAL\Driver\Exception as DriverException;
use PHPUnit\Framework\TestCase;

use function chr;
use function fopen;

final class ExceptionHandlingTest extends TestCase
{
    /** @var Connection */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection([], $this->createConfiguredMock(Driver::class, [
            'getExceptionConverter' => new DefaultExceptionConverter(),
        ]));
    }

    public function testDriverExceptionDuringQueryAcceptsBinaryData(): void
    {
        $e = $this->connection->convertExceptionDuringQuery(
            $this->createMock(DriverException::class),
            '',
            ['ABC', chr(128)]
        );

        self::assertStringContainsString('with params ["ABC", "\x80"]', $e->getMessage());
    }

    public function testDriverExceptionDuringQueryAcceptsResource(): void
    {
        $e = $this->connection->convertExceptionDuringQuery(
            $this->createMock(DriverException::class),
            'INSERT INTO file (`content`) VALUES (?)',
            [
                1 => fopen(__FILE__, 'r'),
            ]
        );

        self::assertStringContainsString('Resource', $e->getMessage());
    }
}
