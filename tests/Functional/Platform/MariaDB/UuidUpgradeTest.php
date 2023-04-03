<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\MariaDB;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MariaDb1052Platform;
use Doctrine\DBAL\Platforms\MariaDb1070Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\GuidType;

final class UuidUpgradeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof MariaDb1070Platform) {
            self::markTestSkipped('This test requires MariDB 10.7 or newer');
        }

        $params             = $this->connection->getParams();
        $params['platform'] = new MariaDb1052Platform();

        $legacyConnection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
        );

        $schemaManager = $legacyConnection->createSchemaManager();
        try {
            $schemaManager->dropTable('legacy_uuid');
        } catch (Exception\TableNotFoundException $e) {
            // ignore
        }

        $schemaManager->createTable($this->getTableDefinition());

        $legacyConnection->insert(
            'legacy_uuid',
            ['id' => '94829370-f7ba-4536-bbb7-2e63b84492d4'],
            ['id' => new GuidType()],
        );
    }

    public function testReadFromLegacyTable(): void
    {
        $type = new GuidType();
        $uuid = $this->connection->fetchOne(
            'SELECT id FROM legacy_uuid WHERE id = ?',
            ['94829370-f7ba-4536-bbb7-2e63b84492d4'],
            [$type],
        );

        self::assertSame(
            '94829370-f7ba-4536-bbb7-2e63b84492d4',
            $type->convertToPHPValue($uuid, $this->connection->getDatabasePlatform()),
        );
    }

    public function testInsertIntoLegacyTable(): void
    {
        $type = new GuidType();
        $this->connection->insert(
            'legacy_uuid',
            ['id' => '8e63fb88-facd-4208-abd2-877720242507'],
            ['id' => new GuidType()],
        );

        $uuid = $this->connection->fetchOne(
            'SELECT id FROM legacy_uuid WHERE id = ?',
            ['8e63fb88-facd-4208-abd2-877720242507'],
            [$type],
        );

        self::assertSame(
            '8e63fb88-facd-4208-abd2-877720242507',
            $type->convertToPHPValue($uuid, $this->connection->getDatabasePlatform()),
        );
    }

    public function testUpgradeTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();

        $diff = $comparator->compareTables(
            $schemaManager->introspectTable('legacy_uuid'),
            $this->getTableDefinition(),
        );

        self::assertFalse($diff->isEmpty());

        $schemaManager->alterTable($diff);

        self::assertSame(
            'uuid',
            $this->connection->fetchOne(
                'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$this->connection->getDatabase(), 'legacy_uuid'],
            ),
        );

        $type = new GuidType();
        $uuid = $this->connection->fetchOne(
            'SELECT id FROM legacy_uuid WHERE id = ?',
            ['94829370-f7ba-4536-bbb7-2e63b84492d4'],
            [$type],
        );

        self::assertSame(
            '94829370-f7ba-4536-bbb7-2e63b84492d4',
            $type->convertToPHPValue($uuid, $this->connection->getDatabasePlatform()),
        );
    }

    private function getTableDefinition(): Table
    {
        return new Table(
            'legacy_uuid',
            [new Column('id', new GuidType())],
            [new Index('PRIMARY', ['id'], false, true)],
        );
    }
}
