<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tests\FunctionalTestCase;

/**
 * @requires extension pdo_pgsql
 */
class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('pdo_pgsql only test.');
    }

    /**
     * @group DBAL-1183
     * @group DBAL-1189
     * @dataProvider getValidCharsets
     */
    public function testConnectsWithValidCharsetOption(string $charset): void
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
            $connection->fetchOne('SHOW client_encoding')
        );
    }

    /**
     * @return mixed[][]
     */
    public static function getValidCharsets(): iterable
    {
        return [
            ['UTF8'],
            ['LATIN1'],
        ];
    }
}
