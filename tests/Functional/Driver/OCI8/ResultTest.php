<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Generator;

use function ini_get;
use function sprintf;

use const E_ALL;
use const E_WARNING;

/** @requires extension oci8 */
class ResultTest extends FunctionalTestCase
{
    /**
     * Database connection parameters for functional test case
     *
     * @var array<string,mixed>
     */
    private array $connectionParams;

    protected function setUp(): void
    {
        $this->connectionParams = TestUtil::getConnectionParams();

        if (TestUtil::isDriverOneOf('oci8')) {
            return;
        }

        self::markTestSkipped('This test requires the oci8 driver.');
    }

    protected function tearDown(): void
    {
        $this->connection->executeQuery(sprintf(
            'DROP FUNCTION test_oracle_fetch_failure',
            $this->connectionParams['user'],
        ));
        $this->connection->executeQuery(sprintf(
            'DROP TYPE return_numbers',
            $this->connectionParams['user'],
        ));
    }

    /**
     * This test will recreate the case where a data set that is larger than the
     * oci8 default prefetch is invalidated on the database after a fetch has begun,
     * but before the fetch has completed.
     *
     * Note that this test requires 2 separate user connections so that the
     * pipelined function can be changed mid fetch.
     *
     * @dataProvider dataProviderForTestTruncatedFetch
     */
    public function testTruncatedFetch(
        bool $invalidateDataMidFetch
    ): void {
        if ($invalidateDataMidFetch) {
            // prevent the PHPUnit error handler from handling the warnings that oci_*() functions may trigger
            $this->iniSet('error_reporting', (string) (E_ALL & ~E_WARNING));

            $this->expectException(DriverException::class);
            $this->expectExceptionCode(4068);
        }

        // Create a pipelined funtion that returns 10 rows more than the
        // oci8 default prefetch
        $this->createReturnTypeNeededForPipelinedFunction();
        $expectedTotalRowCount = (int) ini_get('oci8.default_prefetch') + 10;
        $this->createOrReplacePipelinedFunction($expectedTotalRowCount);

        // Create a separate connection from that used to create/update the function
        // This must be a different user with permissions to change the given function
        $separateConnection = TestUtil::getPrivilegedConnection();

        // Query the pipelined function to get initial dataset
        $statement = $separateConnection->prepare(sprintf(
            'SELECT * FROM TABLE(%s.test_oracle_fetch_failure())',
            $this->connectionParams['user'],
        ));
        $result    = $statement->execute();

        // Access the first result to cause the first X rows to be prefetched
        // as defined by oci8.default_prefetch (often 100 rows)
        $result->fetchOne();

        if ($invalidateDataMidFetch) {
            // Invalidate the original dataset by changing the pipelined function
            // after the initial prefetch that caches locally the first X results
            $this->createOrReplacePipelinedFunction($expectedTotalRowCount + 10);
        }

        while ($result->fetchOne()) {
            // Attempt to access all remaining rows from the original fetch
            // The rows locally cached from the default prefetch will first be used
            // but when the result attempts to get the remaining 10 rows beyond
            // the first prefetch, nothing will be returned
            //
            // PHP oci8 oci_fetch_array will issue a PHP E_WARNING when the 2nd prefetch occurs
            // oci_fetch_array(): ORA-04068: existing state of packages has been discarded
            // ORA-04061: existing state of function "ROOT.TEST_ORACLE_FETCH_FAILURE" has been invalidated
            // ORA-04065: not executed, altered or dropped function "ROOT.TEST_ORACLE_FETCH_FAILURE"
            //
            // If there was no issue, this should have returned rows totalling 10
            // higher than the oci8 default prefetch
            continue;
        }

        $this->assertEquals(
            $expectedTotalRowCount,
            $result->rowCount(),
            sprintf(
                'Expected to have %s total rows fetched but only found %s rows fetched',
                $expectedTotalRowCount,
                $result->rowCount(),
            ),
        );
    }

    public static function dataProviderForTestTruncatedFetch(): Generator
    {
        yield 'it should return all rows if no data invalidation occurs'
            => [false];

        yield 'it should convert oci8 data invalidation error to DriverException'
            => [true];
    }

    private function createReturnTypeNeededForPipelinedFunction(): void
    {
        $this->connection->executeQuery(
            'CREATE TYPE return_numbers AS TABLE OF NUMBER(11)',
        );
    }

    /**
     * This will create a pipelined function that returns X rows with
     * each row returning a single column_value of that row's row number.
     * The total number of rows returned is equal to $totalRowCount.
     */
    private function createOrReplacePipelinedFunction(int $totalRowCount): void
    {
        $this->connection->executeQuery(sprintf(
            'CREATE OR REPLACE FUNCTION test_oracle_fetch_failure
            RETURN return_numbers PIPELINED
            AS
                v_number_list return_numbers;
            BEGIN
                SELECT ROWNUM r
                BULK COLLECT INTO v_number_list
                FROM DUAL
                CONNECT BY ROWNUM <= %d;

                FOR i IN 1 .. v_number_list.COUNT
                LOOP
                    PIPE ROW (v_number_list(i));
                END LOOP;

                RETURN;
            END;',
            $totalRowCount,
        ));
    }
}
