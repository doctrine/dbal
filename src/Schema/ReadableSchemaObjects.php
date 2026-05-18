<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

/**
 * Read-only view of a {@see Schema}'s tables, sequences, and namespaces.
 *
 * @internal
 */
interface ReadableSchemaObjects
{
    public function hasTable(OptionallyQualifiedName $name): bool;

    public function getTable(OptionallyQualifiedName $name): ?Table;

    /** @return list<Table> */
    public function getTables(): array;

    public function hasSequence(OptionallyQualifiedName $name): bool;

    public function getSequence(OptionallyQualifiedName $name): ?Sequence;

    /** @return list<Sequence> */
    public function getSequences(): array;

    public function hasNamespace(UnqualifiedName $name): bool;

    /** @return list<non-empty-string> */
    public function getNamespaces(): array;
}
