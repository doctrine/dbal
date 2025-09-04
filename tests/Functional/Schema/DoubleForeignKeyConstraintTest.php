<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class DoubleForeignKeyConstraintTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->createSchemaManager();
    }

    public function testDoubleForeignKeyConstraint(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform || $platform instanceof OraclePlatform) {
            self::markTestSkipped('DB2 and Oracle do not allow multiple FKs with the same columns.');
        }

        $articles = Table::editor()
            ->setUnquotedName('articles')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $orders = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk_2')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $this->connection->createSchemaManager()
            ->createTable($articles);
        $this->connection->createSchemaManager()
            ->createTable($orders);

        $ordersActual = $this->schemaManager->introspectTable('orders');

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($ordersActual, $orders)
                ->isEmpty(),
        );
    }

    public function testDoubleForeignKeyConstraintComparedToSingle(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform || $platform instanceof OraclePlatform) {
            self::markTestSkipped('DB2 and Oracle do not allow multiple FKs with the same columns.');
        }

        $articles = Table::editor()
            ->setUnquotedName('articles')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $orders = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk_2')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $ordersCompare = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $this->connection->createSchemaManager()
            ->createTable($articles);
        $this->connection->createSchemaManager()
            ->createTable($orders);

        $ordersActual = $this->schemaManager->introspectTable('orders');

        $diff = $this->schemaManager->createComparator()
            ->compareTables($ordersActual, $ordersCompare);

        self::assertFalse($diff->isEmpty());
        self::assertCount(0, $diff->getAddedForeignKeys());
        self::assertCount(1, $diff->getDroppedForeignKeys());
        self::assertSame('articles_fk_2', $diff->getDroppedForeignKeys()[0]->getName());
    }
}
