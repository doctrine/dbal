<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;

/**
 * @covers Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector
 */
class DropSchemaSqlCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetQueriesUsesAcceptedForeignKeys()
    {
        $tableOne = $this->getTableMock();
        $tableTwo = $this->getTableMock();

        $keyConstraintOne = $this->getStubKeyConstraint('first');
        $keyConstraintTwo = $this->getStubKeyConstraint('second');

        $platform = $this->getMockBuilder('Doctrine\DBAL\Platforms\AbstractPlatform')
            ->setMethods(array('getDropForeignKeySQL'))
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
        return $this->getMockWithoutArguments('Doctrine\DBAL\Schema\Table');
    }

    private function getMockWithoutArguments($className)
    {
        return $this->getMockBuilder($className)->disableOriginalConstructor()->getMock();
    }

    private function getStubKeyConstraint($name)
    {
        $constraint = $this->getMockWithoutArguments('Doctrine\DBAL\Schema\ForeignKeyConstraint');

        $constraint->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        $constraint->expects($this->any())
            ->method('getForeignColumns')
            ->will($this->returnValue(array()));

        $constraint->expects($this->any())
            ->method('getColumns')
            ->will($this->returnValue(array()));

        return $constraint;
    }

    public function testGivenForeignKeyWithZeroLength_acceptForeignKeyThrowsException()
    {
        $collector = new DropSchemaSqlCollector(
            $this->getMockForAbstractClass('Doctrine\DBAL\Platforms\AbstractPlatform')
        );

        $this->setExpectedException( 'Doctrine\DBAL\Schema\SchemaException' );
        $collector->acceptForeignKey($this->getTableMock(), $this->getStubKeyConstraint(''));
    }
}
