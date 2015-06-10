<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;

class PDOPgsqlConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        if ( ! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not loaded.');
        }

        parent::setUp();

        if ( ! $this->_conn->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            $this->markTestSkipped('PDOPgsql only test.');
        }
    }

    /**
     * @group DBAL-1183
     * @group DBAL-1189
     *
     * @dataProvider getValidCharsets
     *
     * @param string $charset
     */
    public function testConnectsWithValidCharsetOption($charset)
    {
        $params = $this->_conn->getParams();
        $params['charset'] = $charset;

        $connection = DriverManager::getConnection(
            $params,
            $this->_conn->getConfiguration(),
            $this->_conn->getEventManager()
        );

        $this->assertEquals($charset, $connection->query("SHOW client_encoding")->fetch(\PDO::FETCH_COLUMN));
    }

    /**
     * @return array
     */
    public function getValidCharsets()
    {
        return array(
           array("UTF8"),
           array("LATIN1")
        );
    }
}
