<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;

class PostgreSQL100PlatformTest extends PostgreSQL94PlatformTest
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform() : AbstractPlatform
    {
        return new PostgreSQL100Platform();
    }

    public function testGetListSequencesSQL() : void
    {
        self::assertSame(
            "SELECT sequence_name AS relname,
                       sequence_schema AS schemaname,
                       minimum_value AS min_value, 
                       increment AS increment_by
                FROM   information_schema.sequences
                WHERE  sequence_catalog = 'test_db'
                AND    sequence_schema NOT LIKE 'pg\_%'
                AND    sequence_schema != 'information_schema'",
            $this->platform->getListSequencesSQL('test_db')
        );
    }
}
