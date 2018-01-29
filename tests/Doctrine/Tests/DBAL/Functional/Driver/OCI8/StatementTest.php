<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\Tests\DbalFunctionalTestCase;

class StatementTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (! extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('oci8 only test.');
        }
    }

    /**
     * @dataProvider queryConversionProvider
     */
    public function testQueryConversion($query, array $params, array $expected)
    {
        self::assertEquals(
            $expected,
            $this->_conn->executeQuery($query, $params)->fetch()
        );
    }

    public static function queryConversionProvider()
    {
        return [
            'simple' => [
                'SELECT ? COL1 FROM DUAL',
                [1],
                [
                    'COL1' => 1,
                ],
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
                [
                    'COL1' => 4,
                ],
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
                [
                    'COL1' => '',
                ],
            ],
        ];
    }
}
