<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Collections\QualifiedSchemaObjectSet;
use Doctrine\DBAL\Schema\Collections\SchemaObjectSet;
use Doctrine\DBAL\Schema\Collections\UnqualifiedSchemaObjectSet;
use Doctrine\DBAL\Schema\Exception\InvalidSchemaModification;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;

use function array_column;
use function array_values;
use function assert;
use function strcasecmp;
use function strtolower;

/**
 * The mutable contents of a {@see Schema}: tables, sequences, and their namespaces. Mutators
 * reject input that would violate the schema's invariants, so any existing instance represents
 * a consistent set of contents.
 *
 * @internal
 */
final class SchemaObjects implements ReadableSchemaObjects
{
    /** @var ?SchemaObjectSet<Table> */
    private ?SchemaObjectSet $tables = null;

    /** @var ?SchemaObjectSet<Sequence> */
    private ?SchemaObjectSet $sequences = null;

    /**
     * Ref-counted map of namespaces in use. The key is a lookup form derived from the namespace
     * name; the value is a tuple of the namespace name and the number of contained objects
     * qualified by it. The default namespace is intentionally excluded.
     *
     * @var array<string, array{non-empty-string, int}>
     */
    private array $namespaces = [];

    /** @var ?non-empty-string */
    private ?string $defaultNamespaceName = null;

    /** @return ?non-empty-string */
    public function getDefaultNamespaceName(): ?string
    {
        return $this->defaultNamespaceName;
    }

    /** @param ?non-empty-string $name */
    public function setDefaultNamespaceName(?string $name): void
    {
        if (! $this->isEmpty()) {
            throw InvalidSchemaModification::defaultNamespaceCanOnlyBeSetOnEmptyEditor();
        }

        $this->defaultNamespaceName = $name;
    }

    #[Override]
    public function hasTable(OptionallyQualifiedName $name): bool
    {
        return $this->tables?->has($this->resolve($name)) ?? false;
    }

    #[Override]
    public function getTable(OptionallyQualifiedName $name): ?Table
    {
        return $this->tables?->get($this->resolve($name));
    }

    /** @return list<Table> */
    #[Override]
    public function getTables(): array
    {
        return $this->tables?->toList() ?? [];
    }

    #[Override]
    public function hasSequence(OptionallyQualifiedName $name): bool
    {
        return $this->sequences?->has($this->resolve($name)) ?? false;
    }

    #[Override]
    public function getSequence(OptionallyQualifiedName $name): ?Sequence
    {
        return $this->sequences?->get($this->resolve($name));
    }

    /** @return list<Sequence> */
    #[Override]
    public function getSequences(): array
    {
        return $this->sequences?->toList() ?? [];
    }

    #[Override]
    public function hasNamespace(UnqualifiedName $name): bool
    {
        return isset($this->namespaces[strtolower($name->getIdentifier()->getValue())]);
    }

    /** @return list<non-empty-string> */
    #[Override]
    public function getNamespaces(): array
    {
        return array_column(array_values($this->namespaces), 0);
    }

    public function isEmpty(): bool
    {
        return $this->tables === null && $this->sequences === null;
    }

    public function addTable(Table $table): void
    {
        $resolved = $this->resolve($table->getObjectName());
        $this->ensureSets($resolved);
        assert($this->tables !== null);

        try {
            $this->tables->add($table, $resolved);
        } catch (ObjectAlreadyExists) {
            throw InvalidSchemaModification::tableAlreadyExists($resolved);
        }

        $this->registerQualifier($resolved->getQualifier());
    }

    /** @param callable(Table): Table $modification */
    public function modifyTable(OptionallyQualifiedName $name, callable $modification): void
    {
        if ($this->tables === null) {
            throw InvalidSchemaModification::tableDoesNotExist($name);
        }

        $oldResolved = $this->resolve($name);

        $oldTable = $this->tables->get($oldResolved);
        if ($oldTable === null) {
            throw InvalidSchemaModification::tableDoesNotExist($name);
        }

        $newTable    = $modification($oldTable);
        $newResolved = $this->resolve($newTable->getObjectName());

        if (! $this->qualifiersEqual($oldResolved->getQualifier(), $newResolved->getQualifier())) {
            throw InvalidSchemaModification::cannotChangeTableQualifier($oldResolved, $newResolved);
        }

        try {
            $this->tables->replace($oldResolved, $newResolved, $newTable);
        } catch (ObjectAlreadyExists $e) {
            $collidingName = $e->getObjectName();
            assert($collidingName instanceof OptionallyQualifiedName);

            throw InvalidSchemaModification::tableAlreadyExists($collidingName);
        }
    }

    private function qualifiersEqual(?Identifier $a, ?Identifier $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return strcasecmp($a->getValue(), $b->getValue()) === 0;
    }

    public function removeTable(OptionallyQualifiedName $name): void
    {
        if ($this->tables === null) {
            throw InvalidSchemaModification::tableDoesNotExist($name);
        }

        $resolved = $this->resolve($name);

        try {
            $this->tables->remove($resolved);
        } catch (ObjectDoesNotExist) {
            throw InvalidSchemaModification::tableDoesNotExist($name);
        }

        $this->unregisterQualifier($resolved->getQualifier());
        $this->maybeDestroySets();
    }

    public function clearTables(): void
    {
        $tables = $this->tables;
        if ($tables === null) {
            return;
        }

        foreach ($tables->toList() as $table) {
            $resolved = $this->resolve($table->getObjectName());
            $this->unregisterQualifier($resolved->getQualifier());
        }

        $tables->clear();
        $this->maybeDestroySets();
    }

    public function addSequence(Sequence $sequence): void
    {
        $resolved = $this->resolve($sequence->getObjectName());
        $this->ensureSets($resolved);
        assert($this->sequences !== null);

        try {
            $this->sequences->add($sequence, $resolved);
        } catch (ObjectAlreadyExists) {
            throw InvalidSchemaModification::sequenceAlreadyExists($resolved);
        }

        $this->registerQualifier($resolved->getQualifier());
    }

    public function removeSequence(OptionallyQualifiedName $name): void
    {
        if ($this->sequences === null) {
            throw InvalidSchemaModification::sequenceDoesNotExist($name);
        }

        $resolved = $this->resolve($name);

        try {
            $this->sequences->remove($resolved);
        } catch (ObjectDoesNotExist) {
            throw InvalidSchemaModification::sequenceDoesNotExist($name);
        }

        $this->unregisterQualifier($resolved->getQualifier());
        $this->maybeDestroySets();
    }

    public function clearSequences(): void
    {
        $sequences = $this->sequences;
        if ($sequences === null) {
            return;
        }

        foreach ($sequences->toList() as $sequence) {
            $resolved = $this->resolve($sequence->getObjectName());
            $this->unregisterQualifier($resolved->getQualifier());
        }

        $sequences->clear();
        $this->maybeDestroySets();
    }

    /** Returns a copy with independent storage and shared element references. */
    public function copy(): self
    {
        $copy                       = new self();
        $copy->defaultNamespaceName = $this->defaultNamespaceName;
        $copy->tables               = $this->tables?->copy();
        $copy->sequences            = $this->sequences?->copy();
        $copy->namespaces           = $this->namespaces;

        return $copy;
    }

    public function __clone()
    {
        if ($this->tables !== null) {
            $this->tables = clone $this->tables;
        }

        if ($this->sequences === null) {
            return;
        }

        $this->sequences = clone $this->sequences;
    }

    private function resolve(OptionallyQualifiedName $name): OptionallyQualifiedName
    {
        if ($name->getQualifier() !== null || $this->defaultNamespaceName === null) {
            return $name;
        }

        return new OptionallyQualifiedName(
            $name->getUnqualifiedName(),
            Identifier::quoted($this->defaultNamespaceName),
        );
    }

    private function ensureSets(OptionallyQualifiedName $firstName): void
    {
        if (! $this->isEmpty()) {
            return;
        }

        if ($firstName->getQualifier() !== null) {
            /** @var QualifiedSchemaObjectSet<Table> $tables */
            $tables = new QualifiedSchemaObjectSet();
            /** @var QualifiedSchemaObjectSet<Sequence> $sequences */
            $sequences = new QualifiedSchemaObjectSet();
        } else {
            /** @var UnqualifiedSchemaObjectSet<Table> $tables */
            $tables = new UnqualifiedSchemaObjectSet();
            /** @var UnqualifiedSchemaObjectSet<Sequence> $sequences */
            $sequences = new UnqualifiedSchemaObjectSet();
        }

        $this->tables    = $tables;
        $this->sequences = $sequences;
    }

    private function maybeDestroySets(): void
    {
        if ($this->tables === null || $this->sequences === null) {
            return;
        }

        if (! $this->tables->isEmpty() || ! $this->sequences->isEmpty()) {
            return;
        }

        $this->tables    = null;
        $this->sequences = null;
    }

    private function registerQualifier(?Identifier $qualifier): void
    {
        if ($qualifier === null) {
            return;
        }

        $namespaceName = $qualifier->getValue();

        if ($namespaceName === $this->defaultNamespaceName) {
            return;
        }

        $key = strtolower($namespaceName);

        if (! isset($this->namespaces[$key])) {
            $this->namespaces[$key] = [$namespaceName, 1];
        } else {
            $this->namespaces[$key][1]++;
        }
    }

    private function unregisterQualifier(?Identifier $qualifier): void
    {
        if ($qualifier === null) {
            return;
        }

        $namespaceName = $qualifier->getValue();

        if ($namespaceName === $this->defaultNamespaceName) {
            return;
        }

        $key = strtolower($namespaceName);

        [$name, $count] = $this->namespaces[$key];

        if ($count > 1) {
            $this->namespaces[$key] = [$name, $count - 1];

            return;
        }

        unset($this->namespaces[$key]);
    }
}
