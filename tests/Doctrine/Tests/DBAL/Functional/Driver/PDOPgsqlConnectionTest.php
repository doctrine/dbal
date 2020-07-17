<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @requires extension pdo_pgsql
 */
class PDOPgsqlConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            return;
        }

        $this->markTestSkipped('PDOPgsql only test.');
    }

    /**
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
            $connection->query('SHOW client_encoding')
                ->fetch(FetchMode::COLUMN)
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
