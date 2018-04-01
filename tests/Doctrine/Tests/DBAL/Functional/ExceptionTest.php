<?php
namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Table;
use function array_merge;
use function chmod;
use function defined;
use function file_exists;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function unlink;

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

        $this->_conn->getSchemaManager()->createTable($table);

        $this->_conn->insert("duplicatekey_table", array('id' => 1));

        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->_conn->insert("duplicatekey_table", array('id' => 1));
    }

    public function testTableNotFoundException()
    {
        $sql = "SELECT * FROM unknown_table";

        $this->expectException(Exception\TableNotFoundException::class);
        $this->_conn->executeQuery($sql);
    }

    public function testTableExistsException()
    {
        $schemaManager = $this->_conn->getSchemaManager();
        $table = new \Doctrine\DBAL\Schema\Table("alreadyexist_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        $this->expectException(Exception\TableExistsException::class);
        $schemaManager->createTable($table);
        $schemaManager->createTable($table);
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

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->_conn->insert('owning_table', array('id' => 2, 'constraint_id' => 2));
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
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

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->_conn->update('constraint_error_table', array('id' => 2), array('id' => 1));
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
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

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->_conn->delete('constraint_error_table', array('id' => 1));
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
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

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->_conn->executeUpdate($platform->getTruncateTableSQL('constraint_error_table'));
        } catch (Exception\ForeignKeyConstraintViolationException $exception) {
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
            $this->_conn->exec($sql);
        }

        $this->expectException(Exception\NotNullConstraintViolationException::class);
        $this->_conn->insert("notnull_table", array('id' => 1, 'value' => null));
    }

    public function testInvalidFieldNameException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("bad_fieldname_table");
        $table->addColumn('id', 'integer', array());

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) as $sql) {
            $this->_conn->exec($sql);
        }

        $this->expectException(Exception\InvalidFieldNameException::class);
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
            $this->_conn->exec($sql);
        }

        $sql = 'SELECT id FROM ambiguous_list_table, ambiguous_list_table_2';
        $this->expectException(Exception\NonUniqueFieldNameException::class);
        $this->_conn->executeQuery($sql);
    }

    public function testUniqueConstraintViolationException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("unique_field_table");
        $table->addColumn('id', 'integer');
        $table->addUniqueIndex(array('id'));

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) as $sql) {
            $this->_conn->exec($sql);
        }

        $this->_conn->insert("unique_field_table", array('id' => 5));
        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->_conn->insert("unique_field_table", array('id' => 5));
    }

    public function testSyntaxErrorException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("syntax_error_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        $this->_conn->getSchemaManager()->createTable($table);

        $sql = 'SELECT id FRO syntax_error_table';
        $this->expectException(Exception\SyntaxErrorException::class);
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

        $filename = sprintf('%s/%s', sys_get_temp_dir(), 'doctrine_failed_connection_'.$mode.'.db');

        if (file_exists($filename)) {
            chmod($filename, 0200); // make the file writable again, so it can be removed on Windows
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

        $this->expectException($exceptionClass);
        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->exec($sql);
        }
    }

    public function getSqLiteOpenConnection()
    {
        return array(
            // mode 0 is considered read-only on Windows
            array(0000, defined('PHP_WINDOWS_VERSION_BUILD') ? Exception\ReadOnlyException::class : Exception\ConnectionException::class),
            array(0444, Exception\ReadOnlyException::class),
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

        $this->expectException(Exception\ConnectionException::class);

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->exec($sql);
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
