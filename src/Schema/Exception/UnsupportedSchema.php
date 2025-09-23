<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use RuntimeException;

use function sprintf;

final class UnsupportedSchema extends RuntimeException implements SchemaException
{
    public static function sqliteMissingForeignKeyConstraintReferencedColumns(
        ?string $constraintName,
        string $referencingTableName,
        string $referencedTableName,
    ): self {
        if ($constraintName !== null) {
            $constraintReference = sprintf('"%s"', $constraintName);
        } else {
            $constraintReference = '<unnamed>';
        }

        return new self(sprintf(
            'Unable to introspect foreign key constraint %s on table "%s" because the referenced column names'
                . ' are omitted, and the referenced table "%s" does not exist or does not have a primary key.',
            $constraintReference,
            $referencingTableName,
            $referencedTableName,
        ));
    }
}
