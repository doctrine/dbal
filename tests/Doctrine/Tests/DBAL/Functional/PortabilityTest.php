<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Portability\Connection as ConnectionPortability;

/**
 * @group DBAL-56
 */
class PortabilityTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    private $portableConnection;

    protected function tearDown()
    {
        if ($this->portableConnection) {
            $this->portableConnection->close();
        }

        parent::tearDown();
    }

    /**
     * @param   integer     $portabilityMode
     * @param   integer     $case
     * @return  Connection
     */
    private function getPortableConnection(
        $portabilityMode = ConnectionPortability::PORTABILITY_ALL,
        $case = ColumnCase::LOWER
    ) {
        if (!$this->portableConnection) {
            $params = $this->_conn->getParams();

            $params['wrapperClass'] = ConnectionPortability::class;
            $params['portability']  = $portabilityMode;
            $params['fetch_case']   = $case;

            $this->portableConnection = DriverManager::getConnection($params, $this->_conn->getConfiguration(), $this->_conn->getEventManager());

            try {
                /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
                $table = new \Doctrine\DBAL\Schema\Table("portability_table");
                $table->addColumn('Test_Int', 'integer');
                $table->addColumn('Test_String', 'string', array('fixed' => true, 'length' => 32));
                $table->addColumn('Test_Null', 'string', array('notnull' => false));
                $table->setPrimaryKey(array('Test_Int'));

                $sm = $this->portableConnection->getSchemaManager();
                $sm->createTable($table);

                $this->portableConnection->insert('portability_table', array('Test_Int' => 1, 'Test_String' => 'foo', 'Test_Null' => ''));
                $this->portableConnection->insert('portability_table', array('Test_Int' => 2, 'Test_String' => 'foo  ', 'Test_Null' => null));
            } catch(\Exception $e) {

            }
        }

        return $this->portableConnection;
    }

    public function testFullFetchMode()
    {
        $rows = $this->getPortableConnection()->fetchAll('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $stmt = $this->getPortableConnection()->query('SELECT * FROM portability_table');
        $stmt->setFetchMode(FetchMode::ASSOCIATIVE);

        foreach ($stmt as $row) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $this->getPortableConnection()->query('SELECT * FROM portability_table');

        while (($row = $stmt->fetch(FetchMode::ASSOCIATIVE))) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $this->getPortableConnection()->prepare('SELECT * FROM portability_table');
        $stmt->execute();

        while (($row = $stmt->fetch(FetchMode::ASSOCIATIVE))) {
            $this->assertFetchResultRow($row);
        }
    }

    public function testConnFetchMode()
    {
        $conn = $this->getPortableConnection();
        $conn->setFetchMode(FetchMode::ASSOCIATIVE);

        $rows = $conn->fetchAll('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $stmt = $conn->query('SELECT * FROM portability_table');
        foreach ($stmt as $row) {
          $this->assertFetchResultRow($row);
        }

        $stmt = $conn->query('SELECT * FROM portability_table');
        while (($row = $stmt->fetch())) {
          $this->assertFetchResultRow($row);
        }

        $stmt = $conn->prepare('SELECT * FROM portability_table');
        $stmt->execute();
        while (($row = $stmt->fetch())) {
          $this->assertFetchResultRow($row);
        }
    }

    public function assertFetchResultRows($rows)
    {
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertFetchResultRow($row);
        }
    }

    public function assertFetchResultRow($row)
    {
        self::assertContains($row['test_int'], array(1, 2), "Primary key test_int should either be 1 or 2.");
        self::assertArrayHasKey('test_string', $row, "Case should be lowered.");
        self::assertEquals(3, strlen($row['test_string']), "test_string should be rtrimed to length of three for CHAR(32) column.");
        self::assertNull($row['test_null']);
        self::assertArrayNotHasKey(0, $row, "The row should not contain numerical keys.");
    }

    /**
     * @requires extension pdo
     */
    public function testPortabilityPdoSqlServer()
    {
        $portability = ConnectionPortability::PORTABILITY_SQLSRV;
        $params = array(
            'portability' => $portability
        );

        $driverMock = $this->getMockBuilder('Doctrine\\DBAL\\Driver\\PDOSqlsrv\\Driver')
            ->setMethods(array('connect'))
            ->getMock();

        $driverMock->expects($this->once())
                   ->method('connect')
                   ->will($this->returnValue(null));

        $connection = new ConnectionPortability($params, $driverMock);

        $connection->connect($params);

        self::assertEquals($portability, $connection->getPortability());
    }

    /**
     * @dataProvider fetchAllColumnProvider
     */
    public function testFetchAllColumn($field, array $expected)
    {
        $conn = $this->getPortableConnection();
        $stmt = $conn->query('SELECT ' . $field . ' FROM portability_table');

        $column = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertEquals($expected, $column);
    }

    public static function fetchAllColumnProvider()
    {
        return array(
            'int' => array(
                'Test_Int',
                array(1, 2),
            ),
            'string' => array(
                'Test_String',
                array('foo', 'foo'),
            ),
        );
    }

    public function testFetchAllNullColumn()
    {
        $conn = $this->getPortableConnection();
        $stmt = $conn->query('SELECT Test_Null FROM portability_table');

        $column = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertSame(array(null, null), $column);
    }
}
