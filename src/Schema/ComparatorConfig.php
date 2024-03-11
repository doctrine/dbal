<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

class ComparatorConfig
{
    protected bool $detectRenamedColumns = true;

    protected bool $detectRenamedIndexes = true;

    public function setDetectRenamedColumns(bool $detectRenamedColumns): void
    {
        $this->detectRenamedColumns = $detectRenamedColumns;
    }

    public function getDetectRenamedColumns(): bool
    {
        return $this->detectRenamedColumns;
    }

    public function setDetectRenamedIndexes(bool $detectRenamedIndexes): void
    {
        $this->detectRenamedIndexes = $detectRenamedIndexes;
    }

    public function getDetectRenamedIndexes(): bool
    {
        return $this->detectRenamedIndexes;
    }
}
