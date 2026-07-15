<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Metadata;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Metadata\UniqueConstraintColumnMetadataRow;
use PHPUnit\Framework\TestCase;

class UniqueConstraintColumnMetadataRowTest extends TestCase
{
    public function testNeitherIdNorName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UniqueConstraintColumnMetadataRow(
            schemaName: null,
            tableName: 'orders',
            id: null,
            name: null,
            columnName: 'user_id',
        );
    }
}
