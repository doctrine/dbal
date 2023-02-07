<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension mysqli */
class ResultTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('mysqli')) {
            return;
        }

        self::markTestSkipped('This test requires the mysqli driver.');
    }

    public function testRowCount(): void
    {
        $result = $this->connection->executeQuery('SELECT 1 FROM nonexisting_table;');

        self::assertSame(-1, $result->rowCount());
    }
}
