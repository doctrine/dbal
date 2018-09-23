<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use PHPUnit\Framework\TestCase;

/**
 * @covers Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector
 */
class DropSchemaSqlCollectorTest extends TestCase
{
    public function testGetQueriesUsesAcceptedForeignKeys()
    {
        $tableOne = $this->getTableMock();
        $tableTwo = $this->getTableMock();

        $keyConstraintOne = $this->getStubKeyConstraint('first');
        $keyConstraintTwo = $this->getStubKeyConstraint('second');

        $platform = $this->getMockBuilder(AbstractPlatform::class)
            ->setMethods(['getDropForeignKeySQL'])
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

    private function getTableMock()
    {
        return $this->getMockWithoutArguments(Table::class);
    }

    private function getMockWithoutArguments($className)
    {
        return $this->getMockBuilder($className)->disableOriginalConstructor()->getMock();
    }

    private function getStubKeyConstraint($name)
    {
        $constraint = $this->getMockWithoutArguments(ForeignKeyConstraint::class);

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

    public function testGivenForeignKeyWithZeroLengthAcceptForeignKeyThrowsException()
    {
        $collector = new DropSchemaSqlCollector(
            $this->getMockForAbstractClass(AbstractPlatform::class)
        );

        $this->expectException(SchemaException::class);
        $collector->acceptForeignKey($this->getTableMock(), $this->getStubKeyConstraint(''));
    }
}
