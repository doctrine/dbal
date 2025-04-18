<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * Contains platform-specific parameters used for creating and managing objects scoped to a {@see Table}.
 */
final readonly class TableConfiguration
{
    /**
     * @internal The configuration can be only instantiated by a {@see SchemaConfig}.
     *
     * @param positive-int $maxIdentifierLength
     */
    public function __construct(private int $maxIdentifierLength)
    {
    }

    /**
     * Returns the maximum length of identifiers to be generated for the objects scoped to the table.
     *
     * @return positive-int
     */
    public function getMaxIdentifierLength(): int
    {
        return $this->maxIdentifierLength;
    }
}
