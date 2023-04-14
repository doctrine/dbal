<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function sprintf;

class DateTimeTzImmutableTypeTest extends FunctionalTestCase
{
    private const TEST_TABLE = 'datetimetz_test';

    protected function setUp(): void
    {
        $this->iniSet('date.timezone', 'UTC');

        $table = new Table(self::TEST_TABLE);
        $table->addColumn('id', Types::INTEGER);

        $table->addColumn('val', Types::DATETIMETZ_IMMUTABLE);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $platform                = $this->connection->getDatabasePlatform();
        $dateTimeTzImmutableType = Type::getType(Types::DATETIMETZ_IMMUTABLE);

        $id1    = 1;
        $value1 = new DateTimeImmutable('1986-03-22 19:45:30', new DateTimeZone('America/Argentina/Buenos_Aires'));

        $this->insert($id1, $value1);

        $res1 = $this->select($id1);

        $resultDateTimeTzValue = $dateTimeTzImmutableType
            ->convertToPHPValue($res1, $platform)
            ->setTimezone(new DateTimeZone('UTC'));

        self::assertInstanceOf(DateTimeImmutable::class, $resultDateTimeTzValue);
        self::assertSame($value1->getTimestamp(), $resultDateTimeTzValue->getTimestamp());
        self::assertSame($value1->getTimestamp(), $resultDateTimeTzValue->getTimestamp());
        self::assertSame('UTC', $resultDateTimeTzValue->format('T'));
        self::assertSame('1986-03-22T22:45:30+00:00', $resultDateTimeTzValue->format(DateTimeImmutable::ATOM));
    }

    private function insert(int $id, DateTimeImmutable $value): void
    {
        $result = $this->connection->insert(self::TEST_TABLE, [
            'id'  => $id,
            'val' => $value,
        ], [
            Types::INTEGER,
            Type::getType(Types::DATETIMETZ_IMMUTABLE),
        ]);

        self::assertSame(1, $result);
    }

    private function select(int $id): string
    {
        $value = $this->connection->fetchOne(
            sprintf('SELECT val FROM %s WHERE id = ?', self::TEST_TABLE),
            [$id],
            [Types::INTEGER],
        );

        self::assertIsString($value);

        return $value;
    }
}
