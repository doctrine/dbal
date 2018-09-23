<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

class CreateSchemaSqlCollectorTest extends TestCase
{
    /** @var AbstractPlatform|PHPUnit_Framework_MockObject_MockObject */
    private $platformMock;

    /** @var CreateSchemaSqlCollector */
    private $visitor;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->platformMock = $this->getMockBuilder(AbstractPlatform::class)
            ->setMethods(
                [
                    'getCreateForeignKeySQL',
                    'getCreateSchemaSQL',
                    'getCreateSequenceSQL',
                    'getCreateTableSQL',
                    'supportsForeignKeyConstraints',
                    'supportsSchemas',
                ]
            )
            ->getMockForAbstractClass();
        $this->visitor      = new CreateSchemaSqlCollector($this->platformMock);

        foreach (['getCreateSchemaSQL', 'getCreateTableSQL', 'getCreateForeignKeySQL', 'getCreateSequenceSQL'] as $method) {
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

        self::assertEmpty($this->visitor->getQueries());

        $this->visitor->acceptNamespace('foo');

        self::assertSame(['foo'], $this->visitor->getQueries());
    }

    public function testAcceptsTable()
    {
        $table = $this->createTableMock();

        $this->visitor->acceptTable($table);

        self::assertSame(['foo'], $this->visitor->getQueries());
    }

    public function testAcceptsForeignKey()
    {
        $this->platformMock->expects($this->at(0))
            ->method('supportsForeignKeyConstraints')
            ->will($this->returnValue(false));

        $this->platformMock->expects($this->at(1))
            ->method('supportsForeignKeyConstraints')
            ->will($this->returnValue(true));

        $table      = $this->createTableMock();
        $foreignKey = $this->createForeignKeyConstraintMock();

        $this->visitor->acceptForeignKey($table, $foreignKey);

        self::assertEmpty($this->visitor->getQueries());

        $this->visitor->acceptForeignKey($table, $foreignKey);

        self::assertSame(['foo'], $this->visitor->getQueries());
    }

    public function testAcceptsSequences()
    {
        $sequence = $this->createSequenceMock();

        $this->visitor->acceptSequence($sequence);

        self::assertSame(['foo'], $this->visitor->getQueries());
    }

    public function testResetsQueries()
    {
        foreach (['supportsSchemas', 'supportsForeignKeyConstraints'] as $method) {
            $this->platformMock->expects($this->any())
                ->method($method)
                ->will($this->returnValue(true));
        }

        $table      = $this->createTableMock();
        $foreignKey = $this->createForeignKeyConstraintMock();
        $sequence   = $this->createSequenceMock();

        $this->visitor->acceptNamespace('foo');
        $this->visitor->acceptTable($table);
        $this->visitor->acceptForeignKey($table, $foreignKey);
        $this->visitor->acceptSequence($sequence);

        self::assertNotEmpty($this->visitor->getQueries());

        $this->visitor->resetQueries();

        self::assertEmpty($this->visitor->getQueries());
    }

    /**
     * @return ForeignKeyConstraint|PHPUnit_Framework_MockObject_MockObject
     */
    private function createForeignKeyConstraintMock()
    {
        return $this->getMockBuilder(ForeignKeyConstraint::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Sequence|PHPUnit_Framework_MockObject_MockObject
     */
    private function createSequenceMock()
    {
        return $this->getMockBuilder(Sequence::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Table|PHPUnit_Framework_MockObject_MockObject
     */
    private function createTableMock()
    {
        return $this->getMockBuilder(Table::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
