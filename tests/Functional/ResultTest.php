<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Driver\IBMDB2;
use Doctrine\DBAL\Driver\Mysqli;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ResultTest extends FunctionalTestCase
{
    /**
     * @dataProvider methodProvider
     */
    public function testExceptionHandling(callable $method): void
    {
        $driver = $this->connection->getDriver();

        if (! $driver instanceof Mysqli\Driver && ! $driver instanceof IBMDB2\Driver) {
            self::markTestSkipped('This test works only with the mysqli and ibm_db2 drivers.');
        }

        $result = $this->connection->executeQuery(
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL()
        );
        $result->free();

        $this->expectException(Exception::class);
        $method($result);
    }

    /**
     * @return iterable<string,array{callable(Result):void}>
     */
    public static function methodProvider(): iterable
    {
        yield 'fetchNumeric' => [
            static function (Result $result): void {
                $result->fetchNumeric();
            },
        ];

        yield 'fetchAssociative' => [
            static function (Result $result): void {
                $result->fetchAssociative();
            },
        ];

        yield 'fetchOne' => [
            static function (Result $result): void {
                $result->fetchOne();
            },
        ];

        yield 'fetchAllNumeric' => [
            static function (Result $result): void {
                $result->fetchAllNumeric();
            },
        ];

        yield 'fetchAllAssociative' => [
            static function (Result $result): void {
                $result->fetchAllAssociative();
            },
        ];

        yield 'fetchFirstColumn' => [
            static function (Result $result): void {
                $result->fetchFirstColumn();
            },
        ];
    }
}
