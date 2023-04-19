<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

/** @requires extension oci8 */
class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('oci8')) {
            return;
        }

        self::markTestSkipped('This test requires the oci8 driver.');
    }

    public function testLastInsertIdAcceptsFqn(): void
    {
        $table = new Table('DBAL2595');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO DBAL2595 (foo) VALUES (1)');

        $schema   = $this->connection->getDatabase();
        $platform = $this->connection->getDatabasePlatform();
        $sequence = $platform->getIdentitySequenceName($schema . '.DBAL2595', 'id');

        self::assertSame(1, $this->connection->lastInsertId($sequence));
    }
}
