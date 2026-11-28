<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Metadata;

use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class IndexColumnMetadataRowTest extends TestCase
{
    use VerifyDeprecations;

    public function testCreateWithoutOrigin(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7620');

        new IndexColumnMetadataRow(
            schemaName: null,
            tableName: 'orders',
            indexName: 'orders_user_id_idx',
            type: IndexType::REGULAR,
            isClustered: false,
            predicate: null,
            columnName: 'user_id',
            columnLength: null,
        );
    }
}
