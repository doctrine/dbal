<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Schema\Column;

require_once __DIR__ . '/../../../TestInit.php';

class InformixSchemaManagerTest extends SchemaManagerFunctionalTestCase
{

    /**
     * The detection of the data type of columns in Informix is tricky, it 
     * depends on a code which changes when the “not null” constraint is set 
     * so it's necessary test these cases. We test also other platform 
     * specific data as the minimum length of types VARCHAR and NVARCHAR.
     *
     * @dataProvider dataProviderListTableColumnsInformixSpecific
     */
    public function testListTableColumnsInformixSpecific(array $testData)
    {
        $tableName    = 'list_table_columns_informix_specific';
        $doctrineType = $testData['doctrine_type'];
        $sqlSnippet   = $testData['sql_snippet'];
        $testName     = $testData['test_name'];

        unset($testData['doctrine_type'], $testData['sql_snippet'], 
            $testData['test_name']);

        $nullTests = isset($testData['notnull'])
            ? array($testData['notnull']) : array(false, true);

        foreach ( $nullTests as $withoutNulls ) {

            if ( $withoutNulls ) {
                $columnName = $columnSql = $testName . '_without_nulls';
                $columnSql .= ' ' . $sqlSnippet . ' NOT NULL';
            }
            else {
                $columnName = $columnSql = $testName . '_with_nulls';
                $columnSql .= ' ' . $sqlSnippet;
            }

            $testData['notnull'] = $withoutNulls;

            $expectedColumn = new Column($columnName,
                \Doctrine\DBAL\Types\Type::getType($doctrineType), $testData);

            $this->createListTableColumnsInformixSpecific($tableName, $columnSql);

            $columns = $this->_sm->listTableColumns($tableName);

            $actualColumn = $columns[$columnName];

            $this->assertEquals($expectedColumn, $actualColumn);
        }

    }

    protected function createListTableColumnsInformixSpecific($tableName, $columnSql)
    {

        $sql = "CREATE TABLE $tableName ( $columnSql )";

        $this->_sm->tryMethod('dropTable', $tableName);

        $this->_conn->exec($sql);

    }

    public function dataProviderListTableColumnsInformixSpecific()
    {

      $data = array();

      $data[][] = array(
          'doctrine_type' => 'bigint',
          'sql_snippet'   => 'BIGINT',
          'test_name'     => 'bigint',
      );

      $data[][] = array(
          'autoincrement' => true,
          'doctrine_type' => 'bigint',
          'notnull'       => true,         // bigserial never can be null
          'sql_snippet'   => 'BIGSERIAL',
          'test_name'     => 'bigserial',
      );

      $data[][] = array(
          'doctrine_type' => 'boolean',
          'default'       => true,
          'sql_snippet'   => 'BOOLEAN DEFAULT \'T\'',
          'test_name'     => 'boolean',
      );

      $data[][] = array(
          'doctrine_type' => 'blob',
          'sql_snippet'   => 'BYTE',
          'test_name'     => 'byte',
      );

      $data[][] = array(
          'doctrine_type' => 'string',
          'default'       => 'DEFAULT VALUE',
          'fixed'         => true,
          'length'        => 20,
          'sql_snippet'   => 'CHAR(20) DEFAULT \'DEFAULT VALUE\'',
          'test_name'     => 'char',
      );

      $data[][] = array(
          'doctrine_type' => 'date',
          'default'       => 'TODAY',
          'sql_snippet'   => 'DATE DEFAULT TODAY',
          'test_name'     => 'date',
      );

      $data[][] = array(
          'doctrine_type' => 'datetime',
          'default'       => 'CURRENT',
          'sql_snippet'   => 'DATETIME YEAR TO SECOND DEFAULT CURRENT YEAR TO SECOND',
          'test_name'     => 'datetime',
      );

      $data[][] = array(
          'doctrine_type' => 'decimal',
          'precision'     => 6,
          'scale'         => 3,
          'sql_snippet'   => 'DECIMAL(6, 3)',
          'test_name'     => 'decimal',
      );

      $data[][] = array(
          'doctrine_type' => 'float',
          'sql_snippet'   => 'FLOAT',
          'test_name'     => 'float',
      );

      $data[][] = array(
          'doctrine_type' => 'bigint',
          'sql_snippet'   => 'INT8',
          'test_name'     => 'int8',
      );

      $data[][] = array(
          'doctrine_type' => 'integer',
          'default'       => '1',
          'sql_snippet'   => 'INTEGER DEFAULT 1',
          'test_name'     => 'integer',
      );

      $data[][] = array(
          'doctrine_type' => 'text',
          'length'        => 2000,
          'sql_snippet'   => 'LVARCHAR(2000)',
          'test_name'     => 'lvarchar',
      );

      $data[][] = array(
          'doctrine_type' => 'decimal',
          'precision'     => 16,
          'scale'         => 2,
          'sql_snippet'   => 'MONEY(16,2)',
          'test_name'     => 'money',
      );

      $data[][] = array(
          'doctrine_type' => 'string',
          'fixed'         => true,
          'length'        => 1,
          'sql_snippet'   => 'NCHAR(1)',
          'test_name'     => 'nchar',
      );

      $data[][] = array(
          'doctrine_type'   => 'string',
          'fixed'           => false,
          'length'          => 240,
          'platformOptions' => array('minlength' => 210, 'maxlength' => 240),
          'sql_snippet'     => 'NVARCHAR(240,210)',
          'test_name'       => 'nvarchar',
      );

      $data[][] = array(
          'autoincrement' => true,
          'doctrine_type' => 'bigint',
          'notnull'       => true,       // serial8 never can be null
          'sql_snippet'   => 'SERIAL8',
          'test_name'     => 'serial8',
      );

      $data[][] = array(
          'autoincrement' => true,
          'doctrine_type' => 'integer',
          'notnull'       => true,       // serial never can be null
          'sql_snippet'   => 'SERIAL',
          'test_name'     => 'serial',
      );

      $data[][] = array(
          'doctrine_type' => 'float',
          'sql_snippet'   => 'SMALLFLOAT',
          'test_name'     => 'smallfloat',
      );

      $data[][] = array(
          'doctrine_type' => 'smallint',
          'sql_snippet'   => 'SMALLINT',
          'test_name'     => 'smallint',
      );

      $data[][] = array(
          'doctrine_type' => 'text',
          'sql_snippet'   => 'TEXT',
          'test_name'     => 'text',
      );

      $data[][] = array(
          'doctrine_type' => 'time',
          'default'       => 'CURRENT',
          'sql_snippet'   => 'DATETIME HOUR TO SECOND DEFAULT CURRENT HOUR TO SECOND',
          'test_name'     => 'time',
      );

      $data[][] = array(
          'doctrine_type'   => 'string',
          'fixed'           => false,
          'length'          => 240,
          'platformOptions' => array('minlength' => 210, 'maxlength' => 240),
          'sql_snippet'     => 'VARCHAR(240,210)',
          'test_name'       => 'varchar',
      );

      return $data;

    }

    public function testListTableWithBinary()
    {
        $tableName = 'test_binary_table';

        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', array());
        $table->addColumn('column_binary', 'binary', array('fixed' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $table = $this->_sm->listTableDetails($tableName);

        $this->assertInstanceOf('Doctrine\DBAL\Types\BlobType', $table->getColumn('column_varbinary')->getType());
        $this->assertFalse($table->getColumn('column_varbinary')->getFixed());

        $this->assertInstanceOf('Doctrine\DBAL\Types\BlobType', $table->getColumn('column_binary')->getType());
        $this->assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testMaxColsCompositeIndex() {

        $tableA = $this->getTestMaxColsTable('test_max_cols_composite_index_a');

        $colsExpected = $tableA->getPrimaryKey()->getColumns();

        $this->_sm->dropAndCreateTable($tableA);

        $colsActual = $this->_sm->listTableDetails($tableA->getName())
            ->getPrimaryKey()->getColumns();

        $this->assertEquals($colsExpected, $colsActual);

        $tableB = $this->getTestMaxColsTable('test_max_cols_composite_index_b');

        $tableB->addForeignKeyConstraint(
            $tableA->getName(), $colsExpected, $colsExpected, array(), 'ctr_max_cols'
        );

        $this->_sm->dropAndCreateTable($tableB);

        $colsActual = $this->_sm->listTableDetails($tableB->getName())
          ->getForeignKey('ctr_max_cols')->getColumns();

        $this->assertEquals($colsExpected, $colsActual);

    }

    protected function getTestMaxColsTable($name, $options = array()) {

        $maxCols = 16;

        $table = new \Doctrine\DBAL\Schema\Table($name, array(), array(), array(), false, $options);
        $table->setSchemaConfig($this->_sm->createSchemaConfig());

        $columnNames = array();

        for ( $i = 0; $i < $maxCols; $i++ ) {
            $columnName = 'col' . $i;
            $table->addColumn($columnName, 'integer');
            $columnNames[] = $columnName;
        }

        $table->setPrimaryKey($columnNames);

        return $table;

    }

    public function testListDatabases()
    {
        $databases = $this->_sm->listDatabases();

        $this->assertContains('sysmaster', $databases);
        $this->assertContains($this->_conn->getDatabase(), $databases);
    }

}
