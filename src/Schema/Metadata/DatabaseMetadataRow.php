<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

/**
 * A row of metadata describing a database.
 */
final readonly class DatabaseMetadataRow
{
    /** @param non-empty-string $databaseName */
    public function __construct(private string $databaseName)
    {
    }

    /** @return non-empty-string */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }
}
