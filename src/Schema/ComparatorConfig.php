<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

final class ComparatorConfig
{
    public function __construct(
        private readonly bool $detectRenamedColumns = true,
        private readonly bool $detectRenamedIndexes = true,
    ) {
    }

    public function withDetectRenamedColumns(bool $detectRenamedColumns): self
    {
        return new self(
            $detectRenamedColumns,
            $this->detectRenamedIndexes,
        );
    }

    public function getDetectRenamedColumns(): bool
    {
        return $this->detectRenamedColumns;
    }

    public function withDetectRenamedIndexes(bool $detectRenamedIndexes): self
    {
        return new self(
            $this->detectRenamedColumns,
            $detectRenamedIndexes,
        );
    }

    public function getDetectRenamedIndexes(): bool
    {
        return $this->detectRenamedIndexes;
    }
}
