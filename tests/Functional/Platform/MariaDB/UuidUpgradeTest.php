<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\MariaDB;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MariaDb1052Platform;
use Doctrine\DBAL\Platforms\MariaDb1070Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;

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
            $schemaManager->dropTable('legacy_child_uuid');
        } catch (Exception\TableNotFoundException $e) {
            // ignore
        }

        try {
            $schemaManager->dropTable('legacy_uuid');
        } catch (Exception\TableNotFoundException $e) {
            // ignore
        }

        $schemaManager->createSchemaObjects($this->getSchemaDefinition());

        $legacyConnection->insert(
            'legacy_uuid',
            ['id' => '94829370-f7ba-4536-bbb7-2e63b84492d4'],
            ['id' => new GuidType()],
        );

        $legacyConnection->insert(
            'legacy_child_uuid',
            ['id' => 1, 'legacy_uuid_id' => '94829370-f7ba-4536-bbb7-2e63b84492d4'],
            ['id' => new IntegerType(), 'legacy_uuid_id' => new GuidType()],
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

    public function testJoinWithLegacyTable(): void
    {
        $uuid = $this->connection->fetchOne(
            'SELECT u.id FROM legacy_child_uuid c JOIN legacy_uuid u ON (c.legacy_uuid_id = u.id) WHERE c.id = 1',
        );

        self::assertSame(
            '94829370-f7ba-4536-bbb7-2e63b84492d4',
            (new GuidType())->convertToPHPValue($uuid, $this->connection->getDatabasePlatform()),
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

        $diff = $comparator->compareSchemas(
            new Schema([
                $schemaManager->introspectTable('legacy_uuid'),
                $schemaManager->introspectTable('legacy_child_uuid'),
            ]),
            $this->getSchemaDefinition(),
        );

        self::assertFalse($diff->isEmpty());

        $schemaManager->alterSchema($diff);

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

        $uuid = $this->connection->fetchOne(
            'SELECT u.id FROM legacy_child_uuid c JOIN legacy_uuid u ON (c.legacy_uuid_id = u.id) WHERE c.id = 1',
        );

        self::assertSame(
            '94829370-f7ba-4536-bbb7-2e63b84492d4',
            $type->convertToPHPValue($uuid, $this->connection->getDatabasePlatform()),
        );
    }

    private function getSchemaDefinition(): Schema
    {
        return new Schema([
            new Table(
                'legacy_uuid',
                [new Column('id', new GuidType())],
                [new Index('PRIMARY', ['id'], false, true)],
            ),
            new Table(
                'legacy_child_uuid',
                [
                    new Column('id', new IntegerType()),
                    new Column('legacy_uuid_id', new GuidType()),
                ],
                [new Index('PRIMARY', ['id'], false, true)],
                [],
                [new ForeignKeyConstraint(['legacy_uuid_id'], 'legacy_uuid', ['id'])],
            ),
        ]);
    }
}
