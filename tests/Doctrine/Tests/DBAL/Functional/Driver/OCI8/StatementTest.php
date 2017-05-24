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
        $this->assertEquals(
            $expected,
            $this->_conn->executeQuery($query, $params)->fetch()
        );
    }

    public static function queryConversionProvider()
    {
        return array(
            'simple' => array(
                'SELECT ? COL1 FROM DUAL',
                array(1),
                array(
                    'COL1' => 1,
                ),
            ),
            'literal-with-placeholder' => array(
                "SELECT '?' COL1, ? COL2 FROM DUAL",
                array(2),
                array(
                    'COL1' => '?',
                    'COL2' => 2,
                ),
            ),
            'literal-with-quotes' => array(
                "SELECT ? COL1, '?\"?''?' \"COL?\" FROM DUAL",
                array(3),
                array(
                    'COL1' => 3,
                    'COL?' => '?"?\'?',
                ),
            ),
            'placeholder-at-the-end' => array(
                'SELECT ? COL1 FROM DUAL WHERE 1 = ?',
                array(4, 1),
                array(
                    'COL1' => 4,
                ),
            ),
            'multi-line-literal' => array(
                "SELECT 'Hello,
World?!' COL1 FROM DUAL WHERE 1 = ?",
                array(1),
                array(
                    'COL1' => 'Hello,
World?!',
                ),
            ),
            'empty-literal' => array(
                "SELECT '' COL1 FROM DUAL",
                array(),
                array(
                    'COL1' => '',
                ),
            ),
        );
    }
}
