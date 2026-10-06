<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        return new Comparator(
            new MySQLPlatform(),
            self::createStub(CharsetMetadataProvider::class),
            self::createStub(CollationMetadataProvider::class),
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
            $config,
        );
    }

    public function testIndexPrefixLengthEqualToColumnLengthIsIgnored(): void
    {
        // MySQL silently drops such a prefix, so the introspected table comes back without it
        $introspectedTable = new Table('my_table');
        $introspectedTable->addColumn('my_col', 'string', ['length' => 20]);
        $introspectedTable->addIndex(['my_col'], 'idx_col');

        $declaredTable = new Table('my_table');
        $declaredTable->addColumn('my_col', 'string', ['length' => 20]);
        $declaredTable->addIndex(['my_col'], 'idx_col', [], ['lengths' => [20]]);

        self::assertTrue($this->createComparator(new ComparatorConfig())->compareTables($introspectedTable, $declaredTable)->isEmpty());
        self::assertTrue($this->createComparator(new ComparatorConfig())->compareTables($declaredTable, $introspectedTable)->isEmpty());
    }

    public function testIndexPrefixLengthSmallerThanColumnLengthIsPreserved(): void
    {
        $introspectedTable = new Table('my_table');
        $introspectedTable->addColumn('my_col', 'string', ['length' => 20]);
        $introspectedTable->addIndex(['my_col'], 'idx_col');

        $declaredTable = new Table('my_table');
        $declaredTable->addColumn('my_col', 'string', ['length' => 20]);
        $declaredTable->addIndex(['my_col'], 'idx_col', [], ['lengths' => [5]]);

        self::assertFalse($this->createComparator(new ComparatorConfig())->compareTables($introspectedTable, $declaredTable)->isEmpty());
    }

    public function testUniqueIndexPrefixLengthEqualToColumnLengthIsIgnored(): void
    {
        $introspectedTable = new Table('my_table');
        $introspectedTable->addColumn('my_col', 'string', ['length' => 20]);
        $introspectedTable->addUniqueIndex(['my_col'], 'uniq_col');

        $declaredTable = new Table('my_table');
        $declaredTable->addColumn('my_col', 'string', ['length' => 20]);
        $declaredTable->addUniqueIndex(['my_col'], 'uniq_col', ['lengths' => [20]]);

        self::assertTrue($this->createComparator(new ComparatorConfig())->compareTables($introspectedTable, $declaredTable)->isEmpty());
    }

    public function testCompositeIndexOnlyEqualLengthsAreDropped(): void
    {
        $introspectedTable = new Table('my_table');
        $introspectedTable->addColumn('col_a', 'string', ['length' => 20]);
        $introspectedTable->addColumn('col_b', 'string', ['length' => 30]);
        $introspectedTable->addIndex(['col_a', 'col_b'], 'idx_composite', [], ['lengths' => [null, 10]]);

        $declaredTable = new Table('my_table');
        $declaredTable->addColumn('col_a', 'string', ['length' => 20]);
        $declaredTable->addColumn('col_b', 'string', ['length' => 30]);
        $declaredTable->addIndex(['col_a', 'col_b'], 'idx_composite', [], ['lengths' => [20, 10]]);

        self::assertTrue($this->createComparator(new ComparatorConfig())->compareTables($introspectedTable, $declaredTable)->isEmpty());
    }
}
