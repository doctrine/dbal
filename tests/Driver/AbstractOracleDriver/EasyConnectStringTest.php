<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\AbstractOracleDriver;

use Doctrine\DBAL\Driver\AbstractOracleDriver\EasyConnectString;
use Doctrine\DBAL\Driver\AbstractOracleDriver\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EasyConnectStringTest extends TestCase
{
    /** @param array<string, mixed> $params */
    #[DataProvider('connectionParametersProvider')]
    public function testFromConnectionParameters(array $params, string $expected): void
    {
        $string = EasyConnectString::fromConnectionParameters($params);

        self::assertSame($expected, (string) $string);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function connectionParametersProvider(): iterable
    {
        return [
            'sid' => [
                [
                    'host' => 'oracle.example.com',
                    'port' => 1521,
                    'sid' => 'XE',
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=oracle.example.com)(PORT=1521))(CONNECT_DATA=(SID=XE)))',
            ],
            'no-service-name-or-sid' => [
                ['host' => 'localhost'],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521)))',
            ],
            'service-name' => [
                [
                    'host' => 'localhost',
                    'port' => 1521,
                    'servicename' => 'BILLING',
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))'
                    . '(CONNECT_DATA=(SERVICE_NAME=BILLING)))',
            ],
            'advanced-params' => [
                [
                    'host' => 'localhost',
                    'port' => 41521,
                    'sid' => 'XE',
                    'instancename' => 'SALES',
                    'pooled' => true,
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=41521))'
                    . '(CONNECT_DATA=(SID=XE)(INSTANCE_NAME=SALES)(SERVER=POOLED)))',
            ],
            'tcps-params' => [
                [
                    'host' => 'localhost',
                    'port' => 41521,
                    'sid' => 'XE',
                    'instancename' => 'SALES',
                    'pooled' => true,
                    'driverOptions' => ['protocol' => 'TCPS'],
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCPS)(HOST=localhost)(PORT=41521))'
                . '(CONNECT_DATA=(SID=XE)(INSTANCE_NAME=SALES)(SERVER=POOLED)))',
            ],
        ];
    }

    public function testNoHostOrConnectStringSpecified(): void
    {
        $this->expectException(InvalidConfiguration::class);

        EasyConnectString::fromConnectionParameters([]);
    }
}
