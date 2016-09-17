<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;

/**
 * @covers Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector
 */
class DropSchemaSqlCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform|\PHPUnit_Framework_MockObject_MockObject
     */
    private $platformMock;

    /**
     * @var \Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector
     */
    private $visitor;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->platformMock = $this->getMockBuilder('Doctrine\DBAL\Platforms\AbstractPlatform')
            ->setMethods(
                array(
                    'getDropForeignKeySQL',
                    'getDropViewSQL',
                    'supportsSchemas'
                )
            )
            ->getMockForAbstractClass();
        $this->visitor = new DropSchemaSqlCollector($this->platformMock);

        foreach (array('getDropForeignKeySQL', 'getDropViewSQL') as $method) {
            $this->platformMock->expects($this->any())
                ->method($method)
                ->will($this->returnValue('foo'));
        }
    }

    public function testGetQueriesUsesAcceptedForeignKeys()
    {
        $tableOne = $this->getTableMock();
        $tableTwo = $this->getTableMock();

        $keyConstraintOne = $this->getStubKeyConstraint('first');
        $keyConstraintTwo = $this->getStubKeyConstraint('second');

        $this->platformMock->expects($this->exactly(2))
            ->method('getDropForeignKeySQL');

        $this->platformMock->expects($this->at(0))
            ->method('getDropForeignKeySQL')
            ->with($keyConstraintOne, $tableOne);

        $this->platformMock->expects($this->at(1))
            ->method('getDropForeignKeySQL')
            ->with($keyConstraintTwo, $tableTwo);

        $this->visitor->acceptForeignKey($tableOne, $keyConstraintOne);
        $this->visitor->acceptForeignKey($tableTwo, $keyConstraintTwo);

        $this->assertSame(array('foo', 'foo'), $this->visitor->getQueries());
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

    public function testAcceptsView()
    {
        $view = $this->createViewMock();

        $this->platformMock->expects($this->exactly(1))
            ->method('getDropViewSQL');

        $this->platformMock->expects($this->at(0))
            ->method('getDropViewSQL');

        $this->visitor->acceptView($view);

        $this->assertSame(array('foo'), $this->visitor->getQueries());
    }

    /**
     * @return \Doctrine\DBAL\Schema\View|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createViewMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Schema\View')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
