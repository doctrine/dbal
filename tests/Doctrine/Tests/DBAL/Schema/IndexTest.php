<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Index;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    /**
     * @param mixed[] $options
     */
    private function createIndex(bool $unique = false, bool $primary = false, array $options = []) : Index
    {
        return new Index('foo', ['bar', 'baz'], $unique, $primary, [], $options);
    }

    public function testCreateIndex() : void
    {
        $idx = $this->createIndex();
        self::assertEquals('foo', $idx->getName());
        $columns = $idx->getColumns();
        self::assertCount(2, $columns);
        self::assertEquals(['bar', 'baz'], $columns);
        self::assertFalse($idx->isUnique());
        self::assertFalse($idx->isPrimary());
    }

    public function testCreatePrimary() : void
    {
        $idx = $this->createIndex(false, true);
        self::assertTrue($idx->isUnique());
        self::assertTrue($idx->isPrimary());
    }

    public function testCreateUnique() : void
    {
        $idx = $this->createIndex(true, false);
        self::assertTrue($idx->isUnique());
        self::assertFalse($idx->isPrimary());
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByUnique() : void
    {
        $idx1 = $this->createIndex(true, false);
        $idx2 = $this->createIndex(true, false);
        $idx3 = $this->createIndex();

        self::assertTrue($idx1->isFullfilledBy($idx2));
        self::assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByPrimary() : void
    {
        $idx1 = $this->createIndex(true, true);
        $idx2 = $this->createIndex(true, true);
        $idx3 = $this->createIndex(true, false);

        self::assertTrue($idx1->isFullfilledBy($idx2));
        self::assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFulfilledByIndex() : void
    {
        $idx1 = $this->createIndex();
        $idx2 = $this->createIndex();
        $pri  = $this->createIndex(true, true);
        $uniq = $this->createIndex(true);

        self::assertTrue($idx1->isFullfilledBy($idx2));
        self::assertTrue($idx1->isFullfilledBy($pri));
        self::assertTrue($idx1->isFullfilledBy($uniq));
    }

    public function testFulfilledWithPartial() : void
    {
        $without = new Index('without', ['col1', 'col2'], true, false, [], []);
        $partial = new Index('partial', ['col1', 'col2'], true, false, [], ['where' => 'col1 IS NULL']);
        $another = new Index('another', ['col1', 'col2'], true, false, [], ['where' => 'col1 IS NULL']);

        self::assertFalse($partial->isFullfilledBy($without));
        self::assertFalse($without->isFullfilledBy($partial));

        self::assertTrue($partial->isFullfilledBy($partial));

        self::assertTrue($partial->isFullfilledBy($another));
        self::assertTrue($another->isFullfilledBy($partial));
    }

    public function testOverrulesWithPartial() : void
    {
        $without = new Index('without', ['col1', 'col2'], true, false, [], []);
        $partial = new Index('partial', ['col1', 'col2'], true, false, [], ['where' => 'col1 IS NULL']);
        $another = new Index('another', ['col1', 'col2'], true, false, [], ['where' => 'col1 IS NULL']);

        self::assertFalse($partial->overrules($without));
        self::assertFalse($without->overrules($partial));

        self::assertTrue($partial->overrules($partial));

        self::assertTrue($partial->overrules($another));
        self::assertTrue($another->overrules($partial));
    }

    /**
     * @param string[]     $columns
     * @param int[]|null[] $lengths1
     * @param int[]|null[] $lengths2
     *
     * @dataProvider indexLengthProvider
     */
    public function testFulfilledWithLength(array $columns, array $lengths1, array $lengths2, bool $expected) : void
    {
        $index1 = new Index('index1', $columns, false, false, [], ['lengths' => $lengths1]);
        $index2 = new Index('index2', $columns, false, false, [], ['lengths' => $lengths2]);

        self::assertSame($expected, $index1->isFullfilledBy($index2));
        self::assertSame($expected, $index2->isFullfilledBy($index1));
    }

    /**
     * @return mixed[][]
     */
    public static function indexLengthProvider() : iterable
    {
        return [
            'empty' => [['column'], [], [], true],
            'same' => [['column'], [64], [64], true],
            'different' => [['column'], [32], [64], false],
            'sparse-different-positions' => [['column1', 'column2'], [0 => 32], [1 => 32], false],
            'sparse-same-positions' => [['column1', 'column2'], [null, 32], [1 => 32], true],
        ];
    }

    /**
     * @group DBAL-220
     */
    public function testFlags() : void
    {
        $idx1 = $this->createIndex();
        self::assertFalse($idx1->hasFlag('clustered'));
        self::assertEmpty($idx1->getFlags());

        $idx1->addFlag('clustered');
        self::assertTrue($idx1->hasFlag('clustered'));
        self::assertTrue($idx1->hasFlag('CLUSTERED'));
        self::assertSame(['clustered'], $idx1->getFlags());

        $idx1->removeFlag('clustered');
        self::assertFalse($idx1->hasFlag('clustered'));
        self::assertEmpty($idx1->getFlags());
    }

    /**
     * @group DBAL-285
     */
    public function testIndexQuotes() : void
    {
        $index = new Index('foo', ['`bar`', '`baz`']);

        self::assertTrue($index->spansColumns(['bar', 'baz']));
        self::assertTrue($index->hasColumnAtPosition('bar', 0));
        self::assertTrue($index->hasColumnAtPosition('baz', 1));

        self::assertFalse($index->hasColumnAtPosition('bar', 1));
        self::assertFalse($index->hasColumnAtPosition('baz', 0));
    }

    public function testOptions() : void
    {
        $idx1 = $this->createIndex();
        self::assertFalse($idx1->hasOption('where'));
        self::assertEmpty($idx1->getOptions());

        $idx2 = $this->createIndex(false, false, ['where' => 'name IS NULL']);
        self::assertTrue($idx2->hasOption('where'));
        self::assertTrue($idx2->hasOption('WHERE'));
        self::assertSame('name IS NULL', $idx2->getOption('where'));
        self::assertSame('name IS NULL', $idx2->getOption('WHERE'));
        self::assertSame(['where' => 'name IS NULL'], $idx2->getOptions());
    }
}
