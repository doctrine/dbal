<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Connection;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MultipleConnectionsWithDifferentTypeRegistriesTest extends TestCase
{
    private const SQL = 'SELECT * FROM table WHERE column = ?';

    public function testConnections(): void
    {
        $now = new DateTimeImmutable();

        $mockedTypeForSecondaryConnection = $this->createType();
        $mockedTypeForSecondaryConnection
            ->expects(self::atLeastOnce())
            ->method('getBindingType')
            ->willReturn('secondary_string');
        $mockedTypeForSecondaryConnection
            ->expects(self::atLeastOnce())
            ->method('convertToDatabaseValue')
            ->with($now)
            ->willReturn($now->format('Ymd H:i:s.000'));

        Type::getTypeRegistry('secondary')
            ->override(Types::DATE_IMMUTABLE, $mockedTypeForSecondaryConnection);

        $this->createConnection('default')
            ->executeQuery(self::SQL, [$now], [Types::DATE_IMMUTABLE]);

        $this->createConnection('secondary')
            ->executeQuery(self::SQL, [$now], [Types::DATE_IMMUTABLE]);
    }

    /** @psalm-param non-empty-string $registryName */
    public function createConnection(string $registryName): Connection
    {
        $driver = $this->createMock(Driver::class);
        $driver
            ->method('connect')
            ->willReturn($connection = $this->createMock(Driver\Connection::class));

        $driver
            ->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturn($platform = $this->getMockForAbstractClass(AbstractPlatform::class));

        $connection
            ->expects(self::once())
            ->method('prepare')
            ->with(self::SQL)
            ->willReturn($this->createMock(Driver\Statement::class));

        return new Connection(['type_registry_name' => $registryName], $driver);
    }

    /** @return Type&MockObject */
    private function createType(): Type
    {
        return $this->getMockForAbstractClass(Type::class, [], '', true, true, true, [
            'convertToDatabaseValue',
            'getBindingType',
        ]);
    }
}
