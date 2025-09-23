<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLite\SQLiteMetadataProvider;

/**
 * Represents intermediate results of SQLite foreign key constraint introspection obtained by parsing the DDL of the
 * table that owns the foreign key constraint.
 *
 * @internal This class can be used only by the SQLite metadata provider.
 */
final readonly class ForeignKeyConstraintDetails
{
    /** @param ?non-empty-string $name */
    public function __construct(
        private ?string $name,
        private bool $isDeferrable,
        private bool $isDeferred,
    ) {
    }

    /** @return ?non-empty-string */
    public function getName(): ?string
    {
        return $this->name;
    }

    public function isDeferrable(): bool
    {
        return $this->isDeferrable;
    }

    public function isDeferred(): bool
    {
        return $this->isDeferred;
    }
}
