<?php

namespace Doctrine\Tests\DBAL\Driver\AbstractOracleDriver;

use Doctrine\DBAL\Driver\AbstractOracleDriver\EasyConnectString;
use PHPUnit\Framework\TestCase;

class EasyConnectStringTest extends TestCase
{
    /**
     * @param mixed[] $params
     *
     * @dataProvider connectionParametersProvider
     */
    public function testFromConnectionParameters(array $params, string $expected) : void
    {
        $string = EasyConnectString::fromConnectionParameters($params);

        $this->assertSame($expected, (string) $string);
    }

    /**
     * @return mixed[]
     */
    public static function connectionParametersProvider() : iterable
    {
        return [
            'empty-params' => [[],''],
            'common-params' => [
                [
                    'host' => 'oracle.example.com',
                    'port' => 1521,
                    'dbname' => 'XE',
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=oracle.example.com)(PORT=1521))(CONNECT_DATA=(SID=XE)))',
            ],
            'no-db-name' => [
                ['host' => 'localhost'],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521)))',
            ],
            'service' => [
                [
                    'host' => 'localhost',
                    'port' => 1521,
                    'service' => true,
                    'servicename' => 'BILLING',
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=BILLING)))',
            ],
            'advanced-params' => [
                [
                    'host' => 'localhost',
                    'port' => 41521,
                    'dbname' => 'XE',
                    'instancename' => 'SALES',
                    'pooled' => true,
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=41521))(CONNECT_DATA=(SID=XE)(INSTANCE_NAME=SALES)(SERVER=POOLED)))',
            ],
        ];
    }
}
