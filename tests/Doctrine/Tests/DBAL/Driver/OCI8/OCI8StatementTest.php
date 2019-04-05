<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\OCI8Connection;
use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use const OCI_NO_AUTO_COMMIT;
use function extension_loaded;
use function fopen;

class OCI8StatementTest extends DbalTestCase
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
        /** @var OCI8Statement|MockObject $statement */
        $statement = $this->getMockBuilder(OCI8Statement::class)
            ->setMethods(['bindValue'])
            ->disableOriginalConstructor()
            ->getMock();

        foreach ($params as $index => $value) {
            $statement->expects($this->at($index))
                ->method('bindValue')
                ->with(
                    $this->equalTo($index + 1),
                    $this->equalTo($value)
                )
                ->willReturn(true);
        }

        // can't pass to constructor since we don't have a real database handle,
        // but execute must check the connection for the executeMode
        $conn = $this->createMock(OCI8Connection::class);
        $conn->expects($this->once())
            ->method('getExecuteMode')
            ->willReturn(OCI_NO_AUTO_COMMIT);

        $connectionReflection = new ReflectionProperty($statement, '_conn');
        $connectionReflection->setAccessible(true);
        $connectionReflection->setValue($statement, $conn);

        $handleReflection = new ReflectionProperty($statement, '_sth');
        $handleReflection->setAccessible(true);
        $handleReflection->setValue($statement, fopen('php://temp', 'r'));

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
        $this->expectExceptionMessageRegExp($message);
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
