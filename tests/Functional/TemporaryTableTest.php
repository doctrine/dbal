<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Throwable;

class TemporaryTableTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->connection->exec($this->connection->getDatabasePlatform()->getDropTableSQL('nontemporary'));
        } catch (Throwable $e) {
        }
    }

    protected function tearDown(): void
    {
        try {
            $tempTable = $this->connection->getDatabasePlatform()->getTemporaryTableName('my_temporary');
            $this->connection->exec($this->connection->getDatabasePlatform()->getDropTemporaryTableSQL($tempTable));
        } catch (Throwable $e) {
        }

        parent::tearDown();
    }

    /**
     * @group DDC-1337
     */
    public function testDropTemporaryTableNotAutoCommitTransaction(): void
    {
        if (
            $this->connection->getDatabasePlatform()->getName() === 'sqlanywhere' ||
            $this->connection->getDatabasePlatform()->getName() === 'oracle'
        ) {
            self::markTestSkipped('Test does not work on Oracle and SQL Anywhere.');
        }

        $platform          = $this->connection->getDatabasePlatform();
        $columnDefinitions = [
            [
                'name' => 'id',
                'type' => Type::getType('integer'),
                'notnull' => true,
            ],
        ];

        $tempTable = $platform->getTemporaryTableName('my_temporary');

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';
        $this->connection->executeUpdate($createTempTableSQL);

        $table = new Table('nontemporary');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $this->connection->getSchemaManager()->createTable($table);

        $this->connection->beginTransaction();
        $this->connection->insert('nontemporary', ['id' => 1]);
        $this->connection->exec($platform->getDropTemporaryTableSQL($tempTable));
        $this->connection->insert('nontemporary', ['id' => 2]);

        $this->connection->rollBack();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM nontemporary');
        self::assertEquals([], $rows, 'In an event of an error this result has one row, because of an implicit commit.');
    }

    /**
     * @group DDC-1337
     */
    public function testCreateTemporaryTableNotAutoCommitTransaction(): void
    {
        if (
            $this->connection->getDatabasePlatform()->getName() === 'sqlanywhere' ||
            $this->connection->getDatabasePlatform()->getName() === 'oracle'
        ) {
            self::markTestSkipped('Test does not work on Oracle and SQL Anywhere.');
        }

        $platform          = $this->connection->getDatabasePlatform();
        $columnDefinitions = [
            [
                'name' => 'id',
                'type' => Type::getType('integer'),
                'notnull' => true,
            ],
        ];

        $tempTable = $platform->getTemporaryTableName('my_temporary');

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';

        $table = new Table('nontemporary');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $this->connection->getSchemaManager()->createTable($table);

        $this->connection->beginTransaction();
        $this->connection->insert('nontemporary', ['id' => 1]);

        $this->connection->exec($createTempTableSQL);
        $this->connection->insert('nontemporary', ['id' => 2]);

        $this->connection->rollBack();

        try {
            $this->connection->exec($platform->getDropTemporaryTableSQL($tempTable));
        } catch (Throwable $e) {
        }

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM nontemporary');
        self::assertEquals([], $rows, 'In an event of an error this result has one row, because of an implicit commit.');
    }
}
