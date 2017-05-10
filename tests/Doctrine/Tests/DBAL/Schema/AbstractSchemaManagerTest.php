<?php
namespace Doctrine\Tests\DBAL\Schema;

abstract class AbstractSchemaManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    protected $schemaManager;

    /**
     * @var \Doctrine\DBAL\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $connection;
    
    /**
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $platform;
    
    /**
     *
     * @var \Doctrine\DBAL\Schema\Table 
     */
    protected $table;

    protected function setUp()
    {
        $this->connection->expects($this->any())->method('fetchAll')->willReturnCallback(function(){
            $columns = array();
            foreach($this->table->getColumns() as $name => $column) {
                $parts = $this->platform->getColumnDeclarationValues($column->toArray());
                preg_match('/(?P<type>[A-Z0-9 ]+)\(?(?P<precision>[0-9]*)[, ]*(?P<scale>[0-9]*)\)?(?P<extra>[A-Z ]*)/', $parts['typeDecl'], $matches);
                $type = isset($matches['type']) ? $matches['type']: null;
                $precision = isset($matches['precision']) ? $matches['precision'] : null;
                $scale = isset($matches['scale']) ? $matches['scale'] : null;
                $extra = isset($matches['extra']) ? $matches['extra'] : null;
                $columns[] = array(
                    'data_type' => $type.$extra,
                    'column_name' => $name,
                    'data_default' => null,
                    'data_precision' => (int) $precision,
                    'data_scale' => (int) $scale,
                    'char_length' => (int) $precision,
                    'nullable' => 'N',
                    'comments' => null,
                );
            }
            return $columns;
        });
    }

    /**
     * Test that creating a column with a doctrine type results in the same column type from schema manager
     * 
     * @param string $type
     * @dataProvider doctrineTypeProvider
     */
    public function testGenerateColumnTypeMatchesExtractColumnType($type)
    {
        $platformName = $this->platform->getName();
        $fromTable = new \Doctrine\DBAL\Schema\Table('test');
        $fromTable->addColumn('col_'.$type, $type);
        $this->table = $fromTable;
        $fromCreateSql = $this->platform->getCreateTableSQL($fromTable);
        try {
            $dbColumns = $this->schemaManager->listTableColumns('test');
        }
        catch (\Doctrine\DBAL\DBALException $e){
            if (strpos($e->getMessage(), 'Unknown database type') !== false){
                $this->markTestSkipped(sprintf('Type not supported by %s driver: %s', $platformName, $type));
                return;
            }
            throw $e;
        }
        
        $toTable = new \Doctrine\DBAL\Schema\Table('test', $dbColumns);
        $toCreateSql = $this->platform->getCreateTableSQL($toTable);
        
        $fromCreate = $fromCreateSql[0];
        $toCreate = str_replace('"', '', $toCreateSql[0]);
        $this->assertEquals($fromCreate, $toCreate, sprintf('Create statement from doctrine type equals create statement from (%s) database type', $platformName));
    }
    
    public function doctrineTypeProvider()
    {
        $types = array();
        foreach (array_keys(\Doctrine\DBAL\Types\Type::getTypesMap()) as $type) {
            $types[] = array($type);
        }
        return $types;
    }
}