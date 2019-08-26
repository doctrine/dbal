<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector
 */
class DropSchemaSqlCollectorTest extends TestCase
{
    public function testGetQueriesUsesAcceptedForeignKeys() : void
    {
        $tableOne = $this->createMock(Table::class);
        $tableTwo = $this->createMock(Table::class);

        $keyConstraintOne = $this->getStubKeyConstraint('first');
        $keyConstraintTwo = $this->getStubKeyConstraint('second');

        $platform = $this->getMockBuilder(AbstractPlatform::class)
            ->onlyMethods(['getDropForeignKeySQL'])
            ->getMockForAbstractClass();

        $collector = new DropSchemaSqlCollector($platform);

        $platform->expects($this->exactly(2))
            ->method('getDropForeignKeySQL');

        $platform->expects($this->at(0))
            ->method('getDropForeignKeySQL')
            ->with($keyConstraintOne, $tableOne);

        $platform->expects($this->at(1))
            ->method('getDropForeignKeySQL')
            ->with($keyConstraintTwo, $tableTwo);

        $collector->acceptForeignKey($tableOne, $keyConstraintOne);
        $collector->acceptForeignKey($tableTwo, $keyConstraintTwo);

        $collector->getQueries();
    }

    private function getStubKeyConstraint(string $name) : ForeignKeyConstraint
    {
        $constraint = $this->createMock(ForeignKeyConstraint::class);

        $constraint->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        $constraint->expects($this->any())
            ->method('getForeignColumns')
            ->will($this->returnValue([]));

        $constraint->expects($this->any())
            ->method('getColumns')
            ->will($this->returnValue([]));

        return $constraint;
    }

    public function testGivenForeignKeyWithZeroLengthAcceptForeignKeyThrowsException() : void
    {
        $collector = new DropSchemaSqlCollector(
            $this->getMockForAbstractClass(AbstractPlatform::class)
        );

        $this->expectException(SchemaException::class);
        $collector->acceptForeignKey(
            $this->createMock(Table::class),
            $this->getStubKeyConstraint('')
        );
    }
}
