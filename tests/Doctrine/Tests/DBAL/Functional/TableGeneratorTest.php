<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Id\TableGenerator;

/**
 * @group DDC-450
 */
class TableGeneratorTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    /**
     * @var TableGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $platform = $this->_conn->getDatabasePlatform();
        if ($platform->getName() == "sqlite") {
            $this->markTestSkipped('TableGenerator does not work with SQLite');
        }

        try {
            $schema = new \Doctrine\DBAL\Schema\Schema();
            $visitor = new \Doctrine\DBAL\Id\TableGeneratorSchemaVisitor();
            $schema->visit($visitor);

            foreach ($schema->toSql($platform) as $sql) {
                $this->_conn->exec($sql);
            }

        } catch(\Exception $e) {
        }
        $this->generator = new TableGenerator($this->_conn);
    }

    public function testNextVal()
    {
        $id1 = $this->generator->nextValue("tbl1");
        $id2 = $this->generator->nextValue("tbl1");
        $id3 = $this->generator->nextValue("tbl2");

        self::assertGreaterThan(0, $id1, "First id has to be larger than 0");
        self::assertEquals($id1 + 1, $id2, "Second id is one larger than first one.");
        self::assertEquals($id1, $id3, "First ids from different tables are equal.");
    }

    public function testNextValNotAffectedByOuterTransactions()
    {
        $this->_conn->beginTransaction();
        $id1 = $this->generator->nextValue("tbl1");
        $this->_conn->rollBack();
        $id2 = $this->generator->nextValue("tbl1");

        self::assertGreaterThan(0, $id1, "First id has to be larger than 0");
        self::assertEquals($id1 + 1, $id2, "Second id is one larger than first one.");
    }
}
