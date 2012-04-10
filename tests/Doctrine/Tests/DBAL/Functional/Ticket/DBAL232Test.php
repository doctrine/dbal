<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Platforms\AbstractPlatform;

require_once __DIR__ . '/../../../TestInit.php';

/**
 * Test for [DBAL-232]
 * @group DBAL-232
 */
class DBAL232Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
	const TYPE_NAME = 'dbal232';

	const TABLE_NAME = 'dbal232';

	public function setUp()
    {
        parent::setUp();

		if ( ! Type::hasType(self::TYPE_NAME)) {
			Type::addType(self::TYPE_NAME, __NAMESPACE__ . '\DBAL232TestType');
			$this->_conn->getDatabasePlatform()->markDoctrineTypeCommented(Type::getType(self::TYPE_NAME));
		}

        try {
            $table = new Table(self::TABLE_NAME);
            $table->addColumn('id', 'integer');
            $table->addColumn('value', self::TYPE_NAME);
            $table->setPrimaryKey(array('id'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch(\Exception $e) {

        }
        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL(self::TABLE_NAME));
    }
	
	protected function tearDown()
	{
		$sm = $this->_conn->getSchemaManager();
		$sm->dropTable(self::TABLE_NAME);
	}

	public function testTypeRemoval()
	{
		$sm = $this->_conn->getSchemaManager();

		$columns = $sm->listTableColumns(self::TABLE_NAME);
		$type = $columns['value']->getType();
		self::assertInstanceOf(__NAMESPACE__ . '\DBAL232TestType', $type);

		// This simulates the type removal
		Type::overrideType(self::TYPE_NAME, null);

		// Will throw an "unknown type" exception without the fix, string type 
		// with it.
		$columns = $sm->listTableColumns(self::TABLE_NAME);
		$type = $columns['value']->getType();
		self::assertEquals(Type::getType(Type::STRING), $type);
	}
}

class DBAL232TestType extends Type
{
	public function getName()
	{
		return DBAL232Test::TYPE_NAME;
	}

	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
	{
		return $platform->getVarcharTypeDeclarationSQL($fieldDeclaration);
	}
}
