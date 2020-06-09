<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Internal;

use Doctrine\DBAL\Internal\DependencyOrderCalculator;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the commit order calculation.
 *
 * IMPORTANT: When writing tests here consider that a lot of graph constellations
 * can have many valid orderings, so you may want to build a graph that has only
 * 1 valid order to simplify your tests.
 */
class DependencyOrderCalculatorTest extends TestCase
{
    /** @var DependencyOrderCalculator */
    private $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DependencyOrderCalculator();
    }

    public function testCommitOrdering1(): void
    {
        $table1 = new Table('table1');
        $table2 = new Table('table2');
        $table3 = new Table('table3');
        $table4 = new Table('table4');
        $table5 = new Table('table5');

        self::assertFalse($this->calculator->hasNode($table1->getName()));

        $this->calculator->addNode($table1->getName(), $table1);
        $this->calculator->addNode($table2->getName(), $table2);
        $this->calculator->addNode($table3->getName(), $table3);
        $this->calculator->addNode($table4->getName(), $table4);
        $this->calculator->addNode($table5->getName(), $table5);

        self::assertTrue($this->calculator->hasNode($table1->getName()));

        $this->calculator->addDependency($table1->getName(), $table2->getName());
        $this->calculator->addDependency($table2->getName(), $table3->getName());
        $this->calculator->addDependency($table3->getName(), $table4->getName());
        $this->calculator->addDependency($table5->getName(), $table1->getName());

        $sorted = $this->calculator->sort();

        // There is only 1 valid ordering for this constellation
        $correctOrder = [$table5, $table1, $table2, $table3, $table4];

        self::assertSame($correctOrder, $sorted);
    }
}
