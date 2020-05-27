<?php

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use PHPUnit\Framework\TestCase;
use function extension_loaded;

class OCI8StatementTest extends TestCase
{
    protected function setUp() : void
    {
        if (! extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();
    }

    /**
     * @dataProvider nonTerminatedLiteralProvider
     */
    public function testConvertNonTerminatedLiteral(string $sql, string $message) : void
    {
        $this->expectException(OCI8Exception::class);
        $this->expectExceptionMessageMatches($message);
        OCI8Statement::convertPositionalToNamedPlaceholders($sql);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function nonTerminatedLiteralProvider() : iterable
    {
        return [
            'no-matching-quote' => [
                "SELECT 'literal FROM DUAL",
                '/offset 7/',
            ],
            'no-matching-double-quote' => [
                'SELECT 1 "COL1 FROM DUAL',
                '/offset 9/',
            ],
            'incorrect-escaping-syntax' => [
                "SELECT 'quoted \\'string' FROM DUAL",
                '/offset 23/',
            ],
        ];
    }
}
