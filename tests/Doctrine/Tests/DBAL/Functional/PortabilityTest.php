<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

/**
 * @group DBAL-56
 */
class PortabilityTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    static private $hasTable = false;
    
    private $portableConnection;
    
    public function tearDown()
    {
        if ($this->portableConnection) {
            $this->portableConnection->close();
        }
    }
    
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

                $sm = $this->portableConnection->getSchemaManager();
                $sm->createTable($table);

                $this->portableConnection->insert('portability_table', array('Test_Int' => 1, 'Test_String' => 'foo', 'Test_Null' => ''));
                $this->portableConnection->insert('portability_table', array('Test_Int' => 1, 'Test_String' => 'foo  ', 'Test_Null' => null));
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
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->assertFetchResultRow($row);
        }
        
        $stmt = $this->getPortableConnection()->prepare('SELECT * FROM portability_table');
        $stmt->execute();
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->assertFetchResultRow($row);
        }
    }
    
    public function assertFetchResultRows($rows)
    {
        $this->assertEquals(2, count($rows));
        foreach ($rows AS $row) {
            $this->assertFetchResultRow($row);
        }
    }
    
    public function assertFetchResultRow($row)
    {
        $this->assertEquals(1, $row['test_int']);
        $this->assertArrayHasKey('test_string', $row, "Case should be lowered.");
        $this->assertEquals(3, strlen($row['test_string']), "test_string should be rtrimed to length of three for CHAR(32) column.");
        $this->assertNull($row['test_null']);
    }
}