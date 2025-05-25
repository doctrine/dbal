<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class MySQLSchemaTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new MySQLPlatform();
    }

    public function testGenerateForeignKeySQL(): void
    {
        $tableOld = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('foo_id')
                    ->setUnquotedReferencedTableName('test_foreign')
                    ->setUnquotedReferencedColumnNames('foo_id')
                    ->create(),
            )
            ->create();

        $sqls = [];
        foreach ($tableOld->getForeignKeys() as $fk) {
            $sqls[] = $this->platform->getCreateForeignKeySQL($fk, $tableOld->getObjectName()->toSQL($this->platform));
        }

        self::assertEquals(
            ['ALTER TABLE `test` ADD FOREIGN KEY (`foo_id`) REFERENCES `test_foreign` (`foo_id`)'],
            $sqls,
        );
    }

    public function testClobNoAlterTable(): void
    {
        $tableOld = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('description')
                    ->setTypeName(Types::STRING)
                    ->setLength(65536)
                    ->create(),
            )
            ->create();

        $tableNew = $tableOld->edit()
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $diff = $this->createComparator()
            ->compareTables($tableOld, $tableNew);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(
            ['ALTER TABLE `test` ADD PRIMARY KEY (`id`)'],
            $sql,
        );
    }

    private function createComparator(): Comparator
    {
        return new MySQL\Comparator(
            new MySQLPlatform(),
            self::createStub(CharsetMetadataProvider::class),
            self::createStub(CollationMetadataProvider::class),
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
            new ComparatorConfig(),
        );
    }
}
