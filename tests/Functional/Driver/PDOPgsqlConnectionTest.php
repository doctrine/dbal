<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function extension_loaded;

class PDOPgsqlConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('pdo_pgsql is not loaded.');
        }

        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            return;
        }

        self::markTestSkipped('PDOPgsql only test.');
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
            $connection->query('SHOW client_encoding')
                ->fetchOne()
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
