<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

/**
 * A row of metadata describing a sequence.
 */
final readonly class SequenceMetadataRow
{
    /**
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $sequenceName
     * @param ?non-negative-int $cacheSize
     */
    public function __construct(
        private ?string $schemaName,
        private string $sequenceName,
        private int $allocationSize,
        private int $initialValue,
        private ?int $cacheSize,
    ) {
    }

    /** @return ?non-empty-string */
    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /** @return non-empty-string */
    public function getSequenceName(): string
    {
        return $this->sequenceName;
    }

    public function getAllocationSize(): int
    {
        return $this->allocationSize;
    }

    public function getInitialValue(): int
    {
        return $this->initialValue;
    }

    /** @return ?non-negative-int */
    public function getCacheSize(): ?int
    {
        return $this->cacheSize;
    }
}
