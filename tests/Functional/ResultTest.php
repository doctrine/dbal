<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class ResultTest extends FunctionalTestCase
{
    /**
     * @param mixed $expected
     *
     * @dataProvider methodProvider
     */
    public function testExceptionHandling(callable $method, $expected): void
    {
        $result = $this->connection->executeQuery(
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL(),
        );
        $result->free();

        try {
            // some drivers will trigger a PHP error here which, if not suppressed,
            // would be converted to a PHPUnit exception prior to DBAL throwing its own one
            $value = @$method($result);
        } catch (Exception $e) {
            // The drivers that enforce the command sequencing internally will throw an exception
            $this->expectNotToPerformAssertions();

            return;
        }

        self::assertFalse(
            TestUtil::isDriverOneOf('mysqli', 'ibm_db2'),
            'We expect mysqli and ibm_db2 drivers to throw an exception.',
        );
        // Other drivers will silently return an empty result
        self::assertSame($expected, $value);
    }

    /** @return iterable<string, array{callable(Result):mixed, mixed}> */
    public static function methodProvider(): iterable
    {
        yield 'fetchNumeric' => [
            static fn (Result $result) => $result->fetchNumeric(),
            false,
        ];

        yield 'fetchAssociative' => [
            static fn (Result $result) => $result->fetchAssociative(),
            false,
        ];

        yield 'fetchOne' => [
            static fn (Result $result) => $result->fetchOne(),
            false,
        ];

        yield 'fetchAllNumeric' => [
            static fn (Result $result): array => $result->fetchAllNumeric(),
            [],
        ];

        yield 'fetchAllAssociative' => [
            static fn (Result $result): array => $result->fetchAllAssociative(),
            [],
        ];

        yield 'fetchFirstColumn' => [
            static fn (Result $result): array => $result->fetchFirstColumn(),
            [],
        ];
    }
}
