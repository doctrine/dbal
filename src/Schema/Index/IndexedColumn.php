<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Index;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;

final class IndexedColumn
{
    /**
     * @internal
     *
     * @param ?positive-int $length
     */
    public function __construct(private readonly UnqualifiedName $columnName, private readonly ?int $length)
    {
    }

    public function getColumnName(): UnqualifiedName
    {
        return $this->columnName;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }
}
