<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;

/**
 * Represents the rename of an index.
 */
final readonly class IndexRename
{
    /** @internal The rename can be only instantiated by a {@see Comparator}. */
    public function __construct(private UnqualifiedName $oldName, private Index $newIndex)
    {
    }

    public function getOldName(): UnqualifiedName
    {
        return $this->oldName;
    }

    public function getNewIndex(): Index
    {
        return $this->newIndex;
    }
}
