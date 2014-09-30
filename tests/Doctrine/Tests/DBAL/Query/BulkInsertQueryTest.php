<?php

namespace Doctrine\Tests\DBAL\Query;

use Doctrine\DBAL\Query\BulkInsertQuery;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

/**
 * @group DBAL-218
 */
class BulkInsertQueryTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->will($this->returnValue(new MockPlatform()));
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You need to add at least one set of values before generating the SQL.
     */
    public function testGetSQLWithoutSpecifiedValuesThrowsException()
    {
        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->getSQL();
    }

    public function testEmptyInsertWithoutColumnSpecification()
    {
        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->addValues(array());

        $this->assertSame("INSERT INTO foo VALUES ()", (string) $query);
        $this->assertSame(array(), $query->getParameters());
        $this->assertSame(array(), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->addValues(array(), array(\PDO::PARAM_BOOL));

        $this->assertSame("INSERT INTO foo VALUES ()", (string) $query);
        $this->assertSame(array(), $query->getParameters());
        $this->assertSame(array(), $query->getParameterTypes());
    }

    public function testSingleInsertWithoutColumnSpecification()
    {
        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->addValues(array('bar', 'baz', 'named' => 'bloo'));

        $this->assertSame("INSERT INTO foo VALUES (?, ?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz', 'bloo'), $query->getParameters());
        $this->assertSame(array(null, null, null), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->addValues(
            array('bar', 'baz', 'named' => 'bloo'),
            array('named' => \PDO::PARAM_BOOL, null, \PDO::PARAM_INT)
        );

        $this->assertSame("INSERT INTO foo VALUES (?, ?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz', 'bloo'), $query->getParameters());
        $this->assertSame(array(null, \PDO::PARAM_INT, \PDO::PARAM_BOOL), $query->getParameterTypes());
    }

    public function testMultiInsertWithoutColumnSpecification()
    {
        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->addValues(array());
        $query->addValues(array('bar', 'baz'));
        $query->addValues(array('bar', 'baz', 'bloo'));
        $query->addValues(array('bar', 'baz', 'named' => 'bloo'));

        $this->assertSame("INSERT INTO foo VALUES (), (?, ?), (?, ?, ?), (?, ?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz', 'bar', 'baz', 'bloo', 'bar', 'baz', 'bloo'), $query->getParameters());
        $this->assertSame(array(null, null, null, null, null, null, null, null), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo');

        $query->addValues(array(), array(\PDO::PARAM_INT));
        $query->addValues(array('bar', 'baz'), array(1 => \PDO::PARAM_BOOL));
        $query->addValues(array('bar', 'baz', 'bloo'), array(\PDO::PARAM_INT, null, \PDO::PARAM_BOOL));
        $query->addValues(
            array('bar', 'baz', 'named' => 'bloo'),
            array('named' => \PDO::PARAM_INT, null, \PDO::PARAM_BOOL)
        );

        $this->assertSame("INSERT INTO foo VALUES (), (?, ?), (?, ?, ?), (?, ?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz', 'bar', 'baz', 'bloo', 'bar', 'baz', 'bloo'), $query->getParameters());
        $this->assertSame(
            array(null, \PDO::PARAM_BOOL, \PDO::PARAM_INT, null, \PDO::PARAM_BOOL, null, \PDO::PARAM_BOOL, \PDO::PARAM_INT),
            $query->getParameterTypes()
        );
    }

    public function testSingleInsertWithColumnSpecificationAndPositionalTypeValues()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('bar', 'baz'));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz'), $query->getParameters());
        $this->assertSame(array(null, null), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('bar', 'baz'), array(1 => \PDO::PARAM_BOOL));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz'), $query->getParameters());
        $this->assertSame(array(null, \PDO::PARAM_BOOL), $query->getParameterTypes());
    }

    public function testSingleInsertWithColumnSpecificationAndNamedTypeValues()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('baz' => 'baz', 'bar' => 'bar'));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz'), $query->getParameters());
        $this->assertSame(array(null, null), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('baz' => 'baz', 'bar' => 'bar'), array(null, \PDO::PARAM_INT));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz'), $query->getParameters());
        $this->assertSame(array(null, \PDO::PARAM_INT), $query->getParameterTypes());
    }

    public function testSingleInsertWithColumnSpecificationAndMixedTypeValues()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array(1 => 'baz', 'bar' => 'bar'));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz'), $query->getParameters());
        $this->assertSame(array(null, null), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array(1 => 'baz', 'bar' => 'bar'), array(\PDO::PARAM_INT, \PDO::PARAM_BOOL));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz'), $query->getParameters());
        $this->assertSame(array(\PDO::PARAM_INT, \PDO::PARAM_BOOL), $query->getParameterTypes());
    }

    public function testMultiInsertWithColumnSpecification()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('bar', 'baz'));
        $query->addValues(array(1 => 'baz', 'bar' => 'bar'));
        $query->addValues(array('bar', 'baz' => 'baz'));
        $query->addValues(array('bar' => 'bar', 'baz' => 'baz'));

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?), (?, ?), (?, ?), (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz', 'bar', 'baz', 'bar', 'baz', 'bar', 'baz'), $query->getParameters());
        $this->assertSame(array(null, null, null, null, null, null, null, null), $query->getParameterTypes());

        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('bar', 'baz'), array('baz' => \PDO::PARAM_BOOL, 'bar' => \PDO::PARAM_INT));
        $query->addValues(array(1 => 'baz', 'bar' => 'bar'), array(1 => \PDO::PARAM_BOOL, 'bar' => \PDO::PARAM_INT));
        $query->addValues(array('bar', 'baz' => 'baz'), array(null, null));
        $query->addValues(
            array('bar' => 'bar', 'baz' => 'baz'),
            array('bar' => \PDO::PARAM_INT, 'baz' => \PDO::PARAM_BOOL)
        );

        $this->assertSame("INSERT INTO foo (bar, baz) VALUES (?, ?), (?, ?), (?, ?), (?, ?)", (string) $query);
        $this->assertSame(array('bar', 'baz', 'bar', 'baz', 'bar', 'baz', 'bar', 'baz'), $query->getParameters());
        $this->assertSame(
            array(\PDO::PARAM_INT, \PDO::PARAM_BOOL, \PDO::PARAM_INT, \PDO::PARAM_BOOL, null, null, \PDO::PARAM_INT, \PDO::PARAM_BOOL),
            $query->getParameterTypes()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No value specified for column bar (index 0).
     */
    public function testEmptyInsertWithColumnSpecificationThrowsException()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Multiple values specified for column baz (index 1).
     */
    public function testInsertWithColumnSpecificationAndMultipleValuesForColumnThrowsException()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('bar', 'baz', 'baz' => 666));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Multiple types specified for column baz (index 1).
     */
    public function testInsertWithColumnSpecificationAndMultipleTypesForColumnThrowsException()
    {
        $query = new BulkInsertQuery($this->connection, 'foo', array('bar', 'baz'));

        $query->addValues(array('bar', 'baz'), array(\PDO::PARAM_INT, \PDO::PARAM_INT, 'baz' => \PDO::PARAM_STR));
    }

    public function testExecuteWithMaxInsertRowsPerStatementExceededThrowsException()
    {
        $platform = $this->connection->getDatabasePlatform();
        $insertMaxRows = $platform->getInsertMaxRows();

        $this->setExpectedException(
            '\LogicException',
            sprintf(
                'You can only insert %d rows in a single INSERT statement with platform "%s".',
                $insertMaxRows,
                $platform->getName()
            )
        );

        $query = new BulkInsertQuery($this->connection, 'foo');

        for ($i = 0; $i <= $insertMaxRows; $i++) {
            $query->addValues(array());
        }

        $query->execute();
    }
}
