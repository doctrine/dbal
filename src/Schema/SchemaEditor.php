<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Exception\InvalidSchemaModification;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function strcasecmp;
use function strtolower;

final class SchemaEditor
{
    /** @var ?non-empty-string */
    private ?string $defaultNamespaceName = null;

    /** @var array<string, Table> */
    private array $tables = [];

    /** @var array<string, Sequence> */
    private array $sequences = [];

    /** @internal Use {@link Schema::editor()} or {@link Schema::edit()} to create an instance */
    public function __construct()
    {
    }

    /** @param ?non-empty-string $name */
    public function setDefaultNamespace(?string $name): self
    {
        if ($this->tables !== [] || $this->sequences !== []) {
            throw InvalidSchemaModification::defaultNamespaceCanOnlyBeSetOnEmptyEditor();
        }

        $this->defaultNamespaceName = $name;

        return $this;
    }

    public function setTables(Table ...$tables): self
    {
        $this->tables = [];

        foreach ($tables as $table) {
            $this->addTable($table);
        }

        return $this;
    }

    public function addTable(Table $table): self
    {
        $name = $table->getObjectName();
        $key  = $this->getKey($name);

        if (isset($this->tables[$key])) {
            throw InvalidSchemaModification::tableAlreadyExists($name);
        }

        $this->tables[$key] = $table;

        return $this;
    }

    /** @param callable(TableEditor): void $modification */
    public function modifyTable(OptionallyQualifiedName $tableName, callable $modification): self
    {
        $key = $this->getKey($tableName);

        if (! isset($this->tables[$key])) {
            throw InvalidSchemaModification::tableDoesNotExist($tableName);
        }

        $editor = $this->tables[$key]->edit();
        $modification($editor);
        $newTable = $editor->create();

        $newName     = $newTable->getObjectName();
        $oldResolved = $this->resolveName($tableName);
        $newResolved = $this->resolveName($newName);

        if (! $this->qualifiersEqual($oldResolved->getQualifier(), $newResolved->getQualifier())) {
            throw InvalidSchemaModification::cannotChangeTableQualifier($oldResolved, $newResolved);
        }

        $newKey = $this->getKey($newName);

        if ($newKey === $key) {
            $this->tables[$key] = $newTable;

            return $this;
        }

        if (isset($this->tables[$newKey])) {
            throw InvalidSchemaModification::tableAlreadyExists($newName);
        }

        unset($this->tables[$key]);
        $this->tables[$newKey] = $newTable;

        return $this;
    }

    private function qualifiersEqual(?Identifier $a, ?Identifier $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return strcasecmp($a->getValue(), $b->getValue()) === 0;
    }

    /**
     * @param non-empty-string            $tableName
     * @param callable(TableEditor): void $modification
     * @param ?non-empty-string           $qualifier
     */
    public function modifyTableByUnquotedName(
        string $tableName,
        callable $modification,
        ?string $qualifier = null,
    ): self {
        return $this->modifyTable(OptionallyQualifiedName::unquoted($tableName, $qualifier), $modification);
    }

    public function renameTable(OptionallyQualifiedName $oldTableName, UnqualifiedName $newTableName): self
    {
        return $this->modifyTable(
            $oldTableName,
            static function (TableEditor $editor) use ($oldTableName, $newTableName): void {
                $editor->setName(
                    new OptionallyQualifiedName($newTableName->getIdentifier(), $oldTableName->getQualifier()),
                );
            },
        );
    }

    /**
     * @param non-empty-string  $oldTableName
     * @param non-empty-string  $newTableName
     * @param ?non-empty-string $qualifier
     */
    public function renameTableByUnquotedName(
        string $oldTableName,
        string $newTableName,
        ?string $qualifier = null,
    ): self {
        return $this->renameTable(
            OptionallyQualifiedName::unquoted($oldTableName, $qualifier),
            UnqualifiedName::unquoted($newTableName),
        );
    }

    public function dropTable(OptionallyQualifiedName $tableName): self
    {
        $key = $this->getKey($tableName);

        if (! isset($this->tables[$key])) {
            throw InvalidSchemaModification::tableDoesNotExist($tableName);
        }

        unset($this->tables[$key]);

        return $this;
    }

    /**
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $qualifier
     */
    public function dropTableByUnquotedName(string $tableName, ?string $qualifier = null): self
    {
        return $this->dropTable(OptionallyQualifiedName::unquoted($tableName, $qualifier));
    }

    public function setSequences(Sequence ...$sequences): self
    {
        $this->sequences = [];

        foreach ($sequences as $sequence) {
            $this->addSequence($sequence);
        }

        return $this;
    }

    public function addSequence(Sequence $sequence): self
    {
        $name = $sequence->getObjectName();
        $key  = $this->getKey($name);

        if (isset($this->sequences[$key])) {
            throw InvalidSchemaModification::sequenceAlreadyExists($name);
        }

        $this->sequences[$key] = $sequence;

        return $this;
    }

    public function dropSequence(OptionallyQualifiedName $sequenceName): self
    {
        $key = $this->getKey($sequenceName);

        if (! isset($this->sequences[$key])) {
            throw InvalidSchemaModification::sequenceDoesNotExist($sequenceName);
        }

        unset($this->sequences[$key]);

        return $this;
    }

    /**
     * @param non-empty-string  $sequenceName
     * @param ?non-empty-string $qualifier
     */
    public function dropSequenceByUnquotedName(string $sequenceName, ?string $qualifier = null): self
    {
        return $this->dropSequence(OptionallyQualifiedName::unquoted($sequenceName, $qualifier));
    }

    public function create(): Schema
    {
        $this->ensureUniformQualification();

        $schemaConfig = new SchemaConfig();
        if ($this->defaultNamespaceName !== null) {
            $schemaConfig->setName($this->defaultNamespaceName);
        }

        return new Schema($this->tables, $this->sequences, $schemaConfig);
    }

    private function getKey(OptionallyQualifiedName $name): string
    {
        return $this->buildKey($this->resolveName($name));
    }

    private function buildKey(OptionallyQualifiedName $resolved): string
    {
        $qualifier       = $resolved->getQualifier();
        $unqualifiedName = $resolved->getUnqualifiedName()->getValue();

        if ($qualifier === null) {
            return strtolower($unqualifiedName);
        }

        return strtolower($qualifier->getValue()) . "\0" . strtolower($unqualifiedName);
    }

    /**
     * Returns the given name qualified with the default namespace if one is configured and the name is unqualified;
     * otherwise returns the name unchanged. Mirrors {@see Schema} resolution rules so that names added unqualified
     * can be looked up by either form.
     */
    private function resolveName(OptionallyQualifiedName $name): OptionallyQualifiedName
    {
        if ($name->getQualifier() !== null) {
            return $name;
        }

        if ($this->defaultNamespaceName === null) {
            return $name;
        }

        return new OptionallyQualifiedName(
            $name->getUnqualifiedName(),
            Identifier::quoted($this->defaultNamespaceName),
        );
    }

    /**
     * Throws if the editor's contents mix qualified and unqualified names after resolution against the default
     * namespace. Tables and sequences are checked together — the schema is either fully qualified or fully unqualified.
     */
    private function ensureUniformQualification(): void
    {
        $qualified   = false;
        $unqualified = false;

        foreach ($this->tables as $table) {
            $this->checkQualification($table->getObjectName(), $qualified, $unqualified);
        }

        foreach ($this->sequences as $sequence) {
            $this->checkQualification($sequence->getObjectName(), $qualified, $unqualified);
        }
    }

    private function checkQualification(OptionallyQualifiedName $name, bool &$qualified, bool &$unqualified): void
    {
        if ($this->resolveName($name)->getQualifier() !== null) {
            if ($unqualified) {
                throw ImproperlyQualifiedName::fromQualifiedName($name);
            }

            $qualified = true;
        } else {
            if ($qualified) {
                throw ImproperlyQualifiedName::fromUnqualifiedName($name);
            }

            $unqualified = true;
        }
    }
}
