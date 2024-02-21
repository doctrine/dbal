<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\DataSourceName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataSourceNameTest extends TestCase
{
    /** @param mixed[] $params */
    #[DataProvider('connectionParametersProvider')]
    public function testFromConnectionParameters(array $params, string $expected): void
    {
        $dsn = DataSourceName::fromConnectionParameters($params);

        self::assertSame($expected, $dsn->toString());
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function connectionParametersProvider(): iterable
    {
        yield 'empty-params' => [[], ''];

        yield 'cataloged-database' => [
            [
                'host'     => 'localhost',
                'port'     => 50000,
                'dbname'   => 'doctrine',
                'user'     => 'db2inst1',
                'password' => 'Passw0rd',
            ],
            'HOSTNAME=localhost;PORT=50000;DATABASE=doctrine;UID=db2inst1;PWD=Passw0rd',
        ];

        yield 'uncataloged-database' => [
            ['dbname' => 'HOSTNAME=localhost;PORT=50000;DATABASE=doctrine;UID=db2inst1;PWD=Passw0rd'],
            'HOSTNAME=localhost;PORT=50000;DATABASE=doctrine;UID=db2inst1;PWD=Passw0rd',
        ];
    }
}
