<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\Deprecations\Deprecation;

final readonly class ComparatorConfig
{
    public function __construct(
        private bool $detectRenamedColumns = true,
        private bool $detectRenamedIndexes = true,
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

    /** @deprecated Reporting of modified indexes cannot be enabled anymore. */
    public function withReportModifiedIndexes(bool $reportModifiedIndexes): self
    {
        if ($reportModifiedIndexes) {
            throw new InvalidArgumentException('Reporting of modified indexes cannot be enabled anymore.');
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6898',
            '%s is deprecated and has no effect on the comparator behavior.',
            __METHOD__,
        );

        return $this;
    }
}
