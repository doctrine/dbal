<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

final readonly class SchemaEditor
{
    private SchemaObjects $objects;

    /** @internal Use {@link Schema::editor()} or {@link Schema::edit()} to create an instance */
    public function __construct()
    {
        $this->objects = new SchemaObjects();
    }

    /** @param ?non-empty-string $name */
    public function setDefaultNamespace(?string $name): self
    {
        $this->objects->setDefaultNamespaceName($name);

        return $this;
    }

    public function setTables(Table ...$tables): self
    {
        $this->objects->clearTables();

        foreach ($tables as $table) {
            $this->objects->addTable($table);
        }

        return $this;
    }

    public function addTable(Table $table): self
    {
        $this->objects->addTable($table);

        return $this;
    }

    /** @param callable(TableEditor): void $modification */
    public function modifyTable(OptionallyQualifiedName $tableName, callable $modification): self
    {
        $this->objects->modifyTable($tableName, static function (Table $table) use ($modification): Table {
            $editor = $table->edit();
            $modification($editor);

            return $editor->create();
        });

        return $this;
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
        $this->objects->removeTable($tableName);

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
        $this->objects->clearSequences();

        foreach ($sequences as $sequence) {
            $this->objects->addSequence($sequence);
        }

        return $this;
    }

    public function addSequence(Sequence $sequence): self
    {
        $this->objects->addSequence($sequence);

        return $this;
    }

    public function dropSequence(OptionallyQualifiedName $sequenceName): self
    {
        $this->objects->removeSequence($sequenceName);

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
        return new Schema($this->objects->copy(), $this->objects->getDefaultNamespaceName());
    }
}
