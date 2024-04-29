<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider\ConnectionCharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\ConnectionCollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\Attributes\DataProvider;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(
            new MySQLPlatform(),
            self::createStub(CharsetMetadataProvider::class),
            self::createStub(CollationMetadataProvider::class),
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
        );
    }

    #[DataProvider('utf8AndUtf8mb3MismatchesProvider')]
    public function testUtf8AndUtf8mb3Mismatches(bool $useUtf8mb3, string $defaultCharset): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = new MySQLPlatform();

        $utf8Comparator = new Comparator(
            $platform,
            new ConnectionCharsetMetadataProvider($connection, $useUtf8mb3),
            new ConnectionCollationMetadataProvider($connection, $useUtf8mb3),
            new DefaultTableOptions($defaultCharset, $defaultCharset . '_unicode_ci'),
        );

        $table1 = new Table('t1', [
            new Column('c', new StringType(), [
                'length' => 8,
                'platformOptions' => ['collation' => 'utf8_unicode_ci'],
            ]),
        ]);
        $table2 = new Table('t2', [
            new Column('c', new StringType(), [
                'length' => 8,
                'platformOptions' => ['collation' => 'utf8mb3_unicode_ci'],
            ]),
        ]);
        $table3 = new Table('t3', [
            new Column('c', new StringType(), [
                'length' => 8,
                'platformOptions' => ['collation' => 'utf8mb4_unicode_ci'],
            ]),
        ]);

        self::assertEmpty($utf8Comparator->compareTables($table1, $table2)->getModifiedColumns());
        self::assertEmpty($utf8Comparator->compareTables($table2, $table1)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table1, $table3)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table3, $table1)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table2, $table3)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table3, $table2)->getModifiedColumns());

        $table4 = new Table('t4', [
            new Column('c', new StringType(), [
                'length' => 8,
                'platformOptions' => ['charset' => 'utf8'],
            ]),
        ]);
        $table5 = new Table('t5', [
            new Column('c', new StringType(), [
                'length' => 8,
                'platformOptions' => ['charset' => 'utf8mb3'],
            ]),
        ]);
        $table6 = new Table('t6', [
            new Column('c', new StringType(), [
                'length' => 8,
                'platformOptions' => ['charset' => 'utf8mb4'],
            ]),
        ]);

        self::assertEmpty($utf8Comparator->compareTables($table4, $table5)->getModifiedColumns());
        self::assertEmpty($utf8Comparator->compareTables($table5, $table4)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table4, $table6)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table6, $table4)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table5, $table6)->getModifiedColumns());
        self::assertNotEmpty($utf8Comparator->compareTables($table6, $table5)->getModifiedColumns());
    }

    /** @return iterable<array{bool,string}> */
    public static function utf8AndUtf8mb3MismatchesProvider(): iterable
    {
        yield [false, 'utf8'];
        yield [true, 'utf8'];
        yield [false, 'utf8mb3'];
        yield [true, 'utf8mb3'];
        yield [false, 'utf8mb4'];
        yield [true, 'utf8mb4'];
    }
}
