<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use PDO;
use function extension_loaded;

class PDOStatementTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (! extension_loaded('pdo')) {
            $this->markTestSkipped('PDO is not installed');
        }

        parent::setUp();

        if (! $this->connection->getWrappedConnection() instanceof PDOConnection) {
            $this->markTestSkipped('PDO-only test');
        }

        $table = new Table('stmt_test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string');
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
    }

    /**
     * @group legacy
     * @expectedDeprecation Using a PDO fetch mode or their combination (%d given) is deprecated and will cause an error in Doctrine 3.0
     */
    public function testPDOSpecificModeIsAccepted()
    {
        $this->connection->insert('stmt_test', [
            'id' => 1,
            'name' => 'Alice',
        ]);
        $this->connection->insert('stmt_test', [
            'id' => 2,
            'name' => 'Bob',
        ]);

        $data = $this->connection->query('SELECT id, name FROM stmt_test ORDER BY id')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertSame([
            1 => 'Alice',
            2 => 'Bob',
        ], $data);
    }
}
