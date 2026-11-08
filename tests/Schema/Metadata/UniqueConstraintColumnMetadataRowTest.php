<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Metadata;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Metadata\UniqueConstraintColumnMetadataRow;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;
use TypeError;

class UniqueConstraintColumnMetadataRowTest extends TestCase
{
    use VerifyDeprecations;

    public function testNeitherIdNorName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UniqueConstraintColumnMetadataRow(
            schemaName: null,
            tableName: 'orders',
            id: null,
            name: null,
            columnName: 'user_id',
            isClustered: false,
        );
    }

    public function testNotPassingIsClusteredIsDeprecated(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7591');

        new UniqueConstraintColumnMetadataRow(
            schemaName: null,
            tableName: 'orders',
            id: null,
            name: 'orders_user_id_uq',
            columnName: 'user_id',
        );
    }

    public function testNullIsClustered(): void
    {
        $this->expectException(TypeError::class);

        new UniqueConstraintColumnMetadataRow(
            schemaName: null,
            tableName: 'orders',
            id: null,
            name: 'orders_user_id_uq',
            columnName: 'user_id',
            /** @phpstan-ignore argument.type */
            isClustered: null,
        );
    }
}
