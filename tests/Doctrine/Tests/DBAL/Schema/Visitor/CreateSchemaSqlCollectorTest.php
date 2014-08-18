<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use \Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;

class CreateSchemaSqlCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform|\PHPUnit_Framework_MockObject_MockObject
     */
    private $platformMock;

    /**
     * @var \Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector
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
                    'getCreateForeignKeySQL',
                    'getCreateSchemaSQL',
                    'getCreateSequenceSQL',
                    'getCreateTableSQL',
                    'supportsForeignKeyConstraints',
                    'supportsSchemas'
                )
            )
            ->getMockForAbstractClass();
        $this->visitor = new CreateSchemaSqlCollector($this->platformMock);

        foreach (array('getCreateSchemaSQL', 'getCreateTableSQL', 'getCreateForeignKeySQL', 'getCreateSequenceSQL') as $method) {
            $this->platformMock->expects($this->any())
                ->method($method)
                ->will($this->returnValue('foo'));
        }
    }

    public function testAcceptsNamespace()
    {
        $this->platformMock->expects($this->at(0))
            ->method('supportsSchemas')
            ->will($this->returnValue(false));

        $this->platformMock->expects($this->at(1))
            ->method('supportsSchemas')
            ->will($this->returnValue(true));

        $this->visitor->acceptNamespace('foo');

        $this->assertEmpty($this->visitor->getQueries());

        $this->visitor->acceptNamespace('foo');

        $this->assertSame(array('foo'), $this->visitor->getQueries());
    }

    public function testAcceptsTable()
    {
        $table = $this->createTableMock();

        $this->visitor->acceptTable($table);

        $this->assertSame(array('foo'), $this->visitor->getQueries());
    }

    public function testAcceptsForeignKey()
    {
        $this->platformMock->expects($this->at(0))
            ->method('supportsForeignKeyConstraints')
            ->will($this->returnValue(false));

        $this->platformMock->expects($this->at(1))
            ->method('supportsForeignKeyConstraints')
            ->will($this->returnValue(true));

        $table = $this->createTableMock();
        $foreignKey = $this->createForeignKeyConstraintMock();

        $this->visitor->acceptForeignKey($table, $foreignKey);

        $this->assertEmpty($this->visitor->getQueries());

        $this->visitor->acceptForeignKey($table, $foreignKey);

        $this->assertSame(array('foo'), $this->visitor->getQueries());
    }

    public function testAcceptsSequences()
    {
        $sequence = $this->createSequenceMock();

        $this->visitor->acceptSequence($sequence);

        $this->assertSame(array('foo'), $this->visitor->getQueries());
    }

    public function testResetsQueries()
    {
        foreach (array('supportsSchemas', 'supportsForeignKeys') as $method) {
            $this->platformMock->expects($this->any())
                ->method($method)
                ->will($this->returnValue(true));
        }

        $table = $this->createTableMock();
        $foreignKey = $this->createForeignKeyConstraintMock();
        $sequence = $this->createSequenceMock();

        $this->visitor->acceptNamespace('foo');
        $this->visitor->acceptTable($table);
        $this->visitor->acceptForeignKey($table, $foreignKey);
        $this->visitor->acceptSequence($sequence);

        $this->assertNotEmpty($this->visitor->getQueries());

        $this->visitor->resetQueries();

        $this->assertEmpty($this->visitor->getQueries());
    }

    /**
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createForeignKeyConstraintMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Schema\ForeignKeyConstraint')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \Doctrine\DBAL\Schema\Sequence|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createSequenceMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Schema\Sequence')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createTableMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Schema\Table')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
