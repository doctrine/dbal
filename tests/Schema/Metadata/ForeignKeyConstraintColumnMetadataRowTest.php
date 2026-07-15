<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Metadata;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use PHPUnit\Framework\TestCase;

class ForeignKeyConstraintColumnMetadataRowTest extends TestCase
{
    public function testNeitherIdNorName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ForeignKeyConstraintColumnMetadataRow(
            referencingSchemaName: null,
            referencingTableName: 'orders',
            id: null,
            name: null,
            referencedSchemaName: null,
            referencedTableName: 'customers',
            matchType: MatchType::SIMPLE,
            onUpdateAction: ReferentialAction::NO_ACTION,
            onDeleteAction: ReferentialAction::NO_ACTION,
            isDeferrable: false,
            isDeferred: false,
            referencingColumnName: 'customer_id',
            referencedColumnName: 'id',
        );
    }
}
