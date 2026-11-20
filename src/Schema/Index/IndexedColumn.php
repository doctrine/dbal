<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Index;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

final readonly class IndexedColumn
{
    /**
     * @internal
     *
     * @param ?positive-int        $length
     * @param array<string, mixed> $options Platform-specific options of the indexed column, which are passed as is to
     *                                      the platform.
     */
    public function __construct(
        private UnqualifiedName $columnName,
        private ?int $length,
        private array $options = [],
    ) {
        if ($length !== null && $length <= 0) {
            throw InvalidIndexDefinition::fromNonPositiveColumnLength($columnName, $length);
        }
    }

    public function getColumnName(): UnqualifiedName
    {
        return $this->columnName;
    }

    /** @return ?positive-int */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * Returns the platform-specific options of the indexed column.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
