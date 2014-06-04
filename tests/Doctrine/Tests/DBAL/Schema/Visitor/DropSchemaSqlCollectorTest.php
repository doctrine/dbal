<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use \Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;

class DropSchemaSqlCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetQueriesUsesAcceptedForeignKeys()
    {
        $tableOne = $this->getMockWithoutArguments('Doctrine\DBAL\Schema\Table');
        $tableTwo = $this->getMockWithoutArguments('Doctrine\DBAL\Schema\Table');

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

        return $constraint;
    }
}
