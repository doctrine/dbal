<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Connection as ConnectionPortability;
use PDO;

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
    private function getPortableConnection($portabilityMode = \Doctrine\DBAL\Portability\Connection::PORTABILITY_ALL, $case = \PDO::CASE_LOWER)
    {
        if (!$this->portableConnection) {
            $params = $this->_conn->getParams();
            $params['wrapperClass'] = 'Doctrine\DBAL\Portability\Connection';
            $params['portability'] = $portabilityMode;
            $params['fetch_case'] = $case;
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
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        foreach ($stmt as $row) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $this->getPortableConnection()->query('SELECT * FROM portability_table');
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $this->getPortableConnection()->prepare('SELECT * FROM portability_table');
        $stmt->execute();
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
            $this->assertFetchResultRow($row);
        }
    }

    public function testConnFetchMode()
    {
        $conn = $this->getPortableConnection();
        $conn->setFetchMode(\PDO::FETCH_ASSOC);

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
        self::assertEquals(2, count($rows));
        foreach ($rows as $row) {
            $this->assertFetchResultRow($row);
        }
    }

    public function assertFetchResultRow($row)
    {
        self::assertTrue(in_array($row['test_int'], array(1, 2)), "Primary key test_int should either be 1 or 2.");
        self::assertArrayHasKey('test_string', $row, "Case should be lowered.");
        self::assertEquals(3, strlen($row['test_string']), "test_string should be rtrimed to length of three for CHAR(32) column.");
        self::assertNull($row['test_null']);
        self::assertArrayNotHasKey(0, $row, "PDO::FETCH_ASSOC should not return numerical keys.");
    }

    public function testPortabilitySqlServer()
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

        $column = $stmt->fetchAll(PDO::FETCH_COLUMN);
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

        $column = $stmt->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(array(null, null), $column);
    }
}
