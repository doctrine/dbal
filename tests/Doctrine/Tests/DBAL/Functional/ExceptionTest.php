<?php
namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Schema\Table;

class ExceptionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ( !($this->_conn->getDriver() instanceof ExceptionConverterDriver)) {
            $this->markTestSkipped('Driver does not support special exception handling.');
        }
    }

    public function testPrimaryConstraintViolationException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("duplicatekey_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->insert("duplicatekey_table", array('id' => 1));

        $this->setExpectedException(
            '\Doctrine\DBAL\Exception\UniqueConstraintViolationException');
        $this->_conn->insert("duplicatekey_table", array('id' => 1));
    }

    public function testTableNotFoundException()
    {
        $sql = "SELECT * FROM unknown_table";

        $this->setExpectedException('\Doctrine\DBAL\Exception\TableNotFoundException');
        $this->_conn->executeQuery($sql);
    }

    public function testTableExistsException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("alreadyexist_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        $this->setExpectedException('\Doctrine\DBAL\Exception\TableExistsException');
        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
            $this->_conn->executeQuery($sql);
        }
        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
            $this->_conn->executeQuery($sql);
        }
    }

    public function testForeignKeyConstraintViolationExceptionOnInsert()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped("Only fails on platforms with foreign key constraints.");
        }

        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->_conn->insert("constraint_error_table", array('id' => 1));
            $this->_conn->insert("owning_table", array('id' => 1, 'constraint_id' => 1));
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->setExpectedException('\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException');

        try {
            $this->_conn->insert('owning_table', array('id' => 2, 'constraint_id' => 2));
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnUpdate()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped("Only fails on platforms with foreign key constraints.");
        }

        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->_conn->insert("constraint_error_table", array('id' => 1));
            $this->_conn->insert("owning_table", array('id' => 1, 'constraint_id' => 1));
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->setExpectedException('\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException');

        try {
            $this->_conn->update('constraint_error_table', array('id' => 2), array('id' => 1));
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnDelete()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped("Only fails on platforms with foreign key constraints.");
        }

        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->_conn->insert("constraint_error_table", array('id' => 1));
            $this->_conn->insert("owning_table", array('id' => 1, 'constraint_id' => 1));
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->setExpectedException('\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException');

        try {
            $this->_conn->delete('constraint_error_table', array('id' => 1));
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnTruncate()
    {
        $platform = $this->_conn->getDatabasePlatform();

        if (!$platform->supportsForeignKeyConstraints()) {
            $this->markTestSkipped("Only fails on platforms with foreign key constraints.");
        }

        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->_conn->insert("constraint_error_table", array('id' => 1));
            $this->_conn->insert("owning_table", array('id' => 1, 'constraint_id' => 1));
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->setExpectedException('\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException');

        try {
            $this->_conn->executeUpdate($platform->getTruncateTableSQL('constraint_error_table'));
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        } catch (\Exception $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testNotNullConstraintViolationException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("notnull_table");
        $table->addColumn('id', 'integer', array());
        $table->addColumn('value', 'integer', array('notnull' => true));
        $table->setPrimaryKey(array('id'));

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->setExpectedException('\Doctrine\DBAL\Exception\NotNullConstraintViolationException');
        $this->_conn->insert("notnull_table", array('id' => 1, 'value' => null));
    }

    public function testInvalidFieldNameException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("bad_fieldname_table");
        $table->addColumn('id', 'integer', array());

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->setExpectedException('\Doctrine\DBAL\Exception\InvalidFieldNameException');
        $this->_conn->insert("bad_fieldname_table", array('name' => 5));
    }

    public function testNonUniqueFieldNameException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("ambiguous_list_table");
        $table->addColumn('id', 'integer');

        $table2 = $schema->createTable("ambiguous_list_table_2");
        $table2->addColumn('id', 'integer');

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $sql = 'SELECT id FROM ambiguous_list_table, ambiguous_list_table_2';
        $this->setExpectedException('\Doctrine\DBAL\Exception\NonUniqueFieldNameException');
        $this->_conn->executeQuery($sql);
    }

    public function testUniqueConstraintViolationException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("unique_field_table");
        $table->addColumn('id', 'integer');
        $table->addUniqueIndex(array('id'));

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->insert("unique_field_table", array('id' => 5));
        $this->setExpectedException('\Doctrine\DBAL\Exception\UniqueConstraintViolationException');
        $this->_conn->insert("unique_field_table", array('id' => 5));
    }

    public function testSyntaxErrorException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("syntax_error_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $sql = 'SELECT id FRO syntax_error_table';
        $this->setExpectedException('\Doctrine\DBAL\Exception\SyntaxErrorException');
        $this->_conn->executeQuery($sql);
    }

    /**
     * @dataProvider getSqLiteOpenConnection
     */
    public function testConnectionExceptionSqLite($mode, $exceptionClass)
    {
        if ($this->_conn->getDatabasePlatform()->getName() != 'sqlite') {
            $this->markTestSkipped("Only fails this way on sqlite");
        }

        $filename = sprintf('%s/%s', sys_get_temp_dir(), 'doctrine_failed_connection.db');

        if (file_exists($filename)) {
            unlink($filename);
        }

        touch($filename);
        chmod($filename, $mode);

        $params = array(
            'driver' => 'pdo_sqlite',
            'path'   => $filename,
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($params);

        $schema = new \Doctrine\DBAL\Schema\Schema();
        $table = $schema->createTable("no_connection");
        $table->addColumn('id', 'integer');

        $this->setExpectedException($exceptionClass);
        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->executeQuery($sql);
        }
    }

    public function getSqLiteOpenConnection()
    {
        return array(
            array(0000, '\Doctrine\DBAL\Exception\ConnectionException'),
            array(0444, '\Doctrine\DBAL\Exception\ReadOnlyException'),
        );
    }

    /**
     * @dataProvider getConnectionParams
     */
    public function testConnectionException($params)
    {
        if ($this->_conn->getDatabasePlatform()->getName() == 'sqlite') {
            $this->markTestSkipped("Only skipped if platform is not sqlite");
        }

        if ($this->_conn->getDatabasePlatform()->getName() == 'drizzle') {
            $this->markTestSkipped("Drizzle does not always support authentication");
        }

        if ($this->_conn->getDatabasePlatform()->getName() == 'postgresql' && isset($params['password'])) {
            $this->markTestSkipped("Does not work on Travis");
        }

        $defaultParams = $this->_conn->getParams();
        $params = array_merge($defaultParams, $params);

        $conn = \Doctrine\DBAL\DriverManager::getConnection($params);

        $schema = new \Doctrine\DBAL\Schema\Schema();
        $table = $schema->createTable("no_connection");
        $table->addColumn('id', 'integer');

        $this->setExpectedException('Doctrine\DBAL\Exception\ConnectionException');

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->executeQuery($sql);
        }
    }

    public function getConnectionParams()
    {
        return array(
            array(array('user' => 'not_existing')),
            array(array('password' => 'really_not')),
            array(array('host' => 'localnope')),
        );
    }

    private function setUpForeignKeyConstraintViolationExceptionTest()
    {
        $schemaManager = $this->_conn->getSchemaManager();

        $table = new Table("constraint_error_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        $owningTable = new Table("owning_table");
        $owningTable->addColumn('id', 'integer', array());
        $owningTable->addColumn('constraint_id', 'integer', array());
        $owningTable->setPrimaryKey(array('id'));
        $owningTable->addForeignKeyConstraint($table, array('constraint_id'), array('id'));

        $schemaManager->createTable($table);
        $schemaManager->createTable($owningTable);
    }

    private function tearDownForeignKeyConstraintViolationExceptionTest()
    {
        $schemaManager = $this->_conn->getSchemaManager();

        $schemaManager->dropTable('owning_table');
        $schemaManager->dropTable('constraint_error_table');
    }
}
