<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\AbstractOracleDriver;

use Doctrine\DBAL\Driver\AbstractOracleDriver\EasyConnectString;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EasyConnectStringTest extends TestCase
{
    use VerifyDeprecations;

    /** @param mixed[] $params */
    #[DataProvider('connectionParametersProvider')]
    public function testFromConnectionParameters(array $params, string $expected): void
    {
        $string = EasyConnectString::fromConnectionParameters($params);

        self::assertSame($expected, (string) $string);
    }

    /** @return iterable<string, array<int, mixed>> */
    public static function connectionParametersProvider(): iterable
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
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))'
                    . '(CONNECT_DATA=(SERVICE_NAME=BILLING)))',
            ],
            'advanced-params' => [
                [
                    'host' => 'localhost',
                    'port' => 41521,
                    'dbname' => 'XE',
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
                    'dbname' => 'XE',
                    'instancename' => 'SALES',
                    'pooled' => true,
                    'driverOptions' => ['protocol' => 'TCPS'],
                ],
                '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCPS)(HOST=localhost)(PORT=41521))'
                . '(CONNECT_DATA=(SID=XE)(INSTANCE_NAME=SALES)(SERVER=POOLED)))',
            ],
        ];
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('getConnectionParameters')]
    public function testParameterDeprecation(
        array $parameters,
        string $expectedConnectString,
        bool $expectDeprecation,
    ): void {
        if ($expectDeprecation) {
            $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7042');
        } else {
            $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7042');
        }

        $string = EasyConnectString::fromConnectionParameters($parameters);

        self::assertSame($expectedConnectString, (string) $string);
    }

    /** @return iterable<string, array{array<string, mixed>, string, bool}> */
    public static function getConnectionParameters(): iterable
    {
        $serviceNameString = '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))'
            . '(CONNECT_DATA=(SERVICE_NAME=BILLING)))';

        yield 'dbname-and-service' => [
            [
                'host' => 'localhost',
                'port' => 1521,
                'dbname' => 'BILLING',
                'service' => true,
            ],
            $serviceNameString,
            true,
        ];

        yield 'servicename' => [
            [
                'host' => 'localhost',
                'port' => 1521,
                'servicename' => 'BILLING',
            ],
            $serviceNameString,
            false,
        ];
    }
}
