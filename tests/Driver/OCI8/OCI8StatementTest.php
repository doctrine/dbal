<?php

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\OCI8Connection;
use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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
     * This scenario shows that when the first parameter is not null
     * it properly sets $hasZeroIndex to 1 and calls bindValue starting at 1.
     *
     * This also verifies that the statement will check with the connection to
     * see what the current execution mode is.
     *
     * The expected exception is due to oci_execute failing due to no valid connection.
     *
     * @param mixed[] $params
     *
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $params) : void
    {
        $statement = $this->getMockBuilder(OCI8Statement::class)
            ->onlyMethods(['bindValue'])
            ->disableOriginalConstructor()
            ->getMock();

        $statement->expects(self::at(0))
            ->method('bindValue')
            ->with(
                self::equalTo(1),
                self::equalTo($params[0])
            );
        $statement->expects(self::at(1))
            ->method('bindValue')
            ->with(
                self::equalTo(2),
                self::equalTo($params[1])
            );
        $statement->expects(self::at(2))
            ->method('bindValue')
            ->with(
                self::equalTo(3),
                self::equalTo($params[2])
            );

        // can't pass to constructor since we don't have a real database handle,
        // but execute must check the connection for the executeMode
        $conn = $this->createMock(OCI8Connection::class);
        $conn->expects(self::once())
            ->method('getExecuteMode');

        $reflProperty = new ReflectionProperty($statement, '_conn');
        $reflProperty->setAccessible(true);
        $reflProperty->setValue($statement, $conn);

        $this->expectException(OCI8Exception::class);
        $statement->execute($params);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function executeDataProvider() : iterable
    {
        return [
            // $hasZeroIndex = isset($params[0]); == true
            [
                [0 => 'test', 1 => null, 2 => 'value'],
            ],
            // $hasZeroIndex = isset($params[0]); == false
            [
                [0 => null, 1 => 'test', 2 => 'value'],
            ],
        ];
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
