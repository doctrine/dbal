<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function extension_loaded;

class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('oci8')) {
            self::markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('oci8 only test.');
    }

    /**
     * @param mixed[] $params
     * @param mixed[] $expected
     *
     * @dataProvider queryConversionProvider
     */
    public function testQueryConversion(string $query, array $params, array $expected): void
    {
        self::assertEquals(
            $expected,
            $this->connection->executeQuery($query, $params)->fetchAssociative()
        );
    }

    /**
     * Low-level approach to working with parameter binding
     *
     * @param mixed[] $params
     * @param mixed[] $expected
     *
     * @dataProvider queryConversionProvider
     */
    public function testStatementBindParameters(string $query, array $params, array $expected): void
    {
        self::assertEquals(
            $expected,
            $this->connection->prepare($query)
                ->execute($params)
                ->fetchAssociative()
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function queryConversionProvider(): iterable
    {
        return [
            'positional' => [
                'SELECT ? COL1 FROM DUAL',
                [1],
                ['COL1' => 1],
            ],
            'named' => [
                'SELECT :COL COL1 FROM DUAL',
                [':COL' => 1],
                ['COL1' => 1],
            ],
            'literal-with-placeholder' => [
                "SELECT '?' COL1, ? COL2 FROM DUAL",
                [2],
                [
                    'COL1' => '?',
                    'COL2' => 2,
                ],
            ],
            'literal-with-quotes' => [
                "SELECT ? COL1, '?\"?''?' \"COL?\" FROM DUAL",
                [3],
                [
                    'COL1' => 3,
                    'COL?' => '?"?\'?',
                ],
            ],
            'placeholder-at-the-end' => [
                'SELECT ? COL1 FROM DUAL WHERE 1 = ?',
                [4, 1],
                ['COL1' => 4],
            ],
            'multi-line-literal' => [
                "SELECT 'Hello,
World?!' COL1 FROM DUAL WHERE 1 = ?",
                [1],
                [
                    'COL1' => 'Hello,
World?!',
                ],
            ],
            'empty-literal' => [
                "SELECT '' COL1 FROM DUAL",
                [],
                ['COL1' => ''],
            ],
        ];
    }
}
