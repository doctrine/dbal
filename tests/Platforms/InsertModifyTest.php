<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\UnsupportedInsertModifier;
use Doctrine\DBAL\InsertModify;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;

/** @see \Doctrine\DBAL\Connection::insert() */
final class InsertModifyTest extends TestCase
{
    private const INSERT = 'INSERT INTO t (a) VALUES (?)';

    public function testNoModifierLeavesStatementUnchanged(): void
    {
        foreach ($this->platforms() as $platform) {
            self::assertSame(
                self::INSERT,
                $platform->modifyInsertSQL(self::INSERT),
                $platform::class,
            );
        }
    }

    public function testMySqlUsesBareIgnore(): void
    {
        self::assertSame(
            'INSERT IGNORE INTO t (a) VALUES (?)',
            (new MySQLPlatform())->modifyInsertSQL(self::INSERT, InsertModify::Ignore),
        );
    }

    public function testMariaDbUsesBareIgnore(): void
    {
        self::assertSame(
            'INSERT IGNORE INTO t (a) VALUES (?)',
            (new MariaDBPlatform())->modifyInsertSQL(self::INSERT, InsertModify::Ignore),
        );
    }

    public function testSqliteUsesOrIgnore(): void
    {
        // SQLite spells the same modifier differently, which is the whole reason the
        // modifier lives behind the platform rather than in Connection::insert().
        self::assertSame(
            'INSERT OR IGNORE INTO t (a) VALUES (?)',
            (new SQLitePlatform())->modifyInsertSQL(self::INSERT, InsertModify::Ignore),
        );
    }

    public function testUnsupportedPlatformThrows(): void
    {
        $this->expectException(UnsupportedInsertModifier::class);
        $this->expectExceptionMessage('does not support the INSERT modifier "IGNORE"');

        (new PostgreSQLPlatform())->modifyInsertSQL(self::INSERT, InsertModify::Ignore);
    }

    /** @return list<AbstractPlatform> */
    private function platforms(): array
    {
        /** @var list<AbstractPlatform> $platforms */
        $platforms = [
            new MySQLPlatform(),
            new MariaDBPlatform(),
            new SQLitePlatform(),
            new PostgreSQLPlatform(),
        ];

        return $platforms;
    }
}
