<?php
namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\DBALException;

require_once __DIR__ . '/../../TestInit.php';

class ExceptionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testDuplicateKeyException()
    {
        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $table = new \Doctrine\DBAL\Schema\Table("duplicatekey_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->insert("duplicatekey_table", array('id' => 1));

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_DUPLICATE_KEY);
        $this->_conn->insert("duplicatekey_table", array('id' => 1));
    }

    public function testUnknownTableException()
    {
        $sql = "SELECT * FROM unknown_table";

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_UNKNOWN_TABLE);
        $this->_conn->executeQuery($sql);
    }
}
 