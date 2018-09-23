<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;
use function extension_loaded;

class PDOPgsqlConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not loaded.');
        }

        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            return;
        }

        $this->markTestSkipped('PDOPgsql only test.');
    }

    /**
     * @param string $charset
     *
     * @group DBAL-1183
     * @group DBAL-1189
     * @dataProvider getValidCharsets
     */
    public function testConnectsWithValidCharsetOption($charset)
    {
        $params            = $this->connection->getParams();
        $params['charset'] = $charset;

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertEquals(
            $charset,
            $connection->query('SHOW client_encoding')
                ->fetch(FetchMode::COLUMN)
        );
    }

    /**
     * @return mixed[][]
     */
    public function getValidCharsets()
    {
        return [
            ['UTF8'],
            ['LATIN1'],
        ];
    }
}
