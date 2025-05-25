<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Collections\OptionallyUnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidTableModification;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function strcasecmp;

final class TableEditor
{
    private ?OptionallyQualifiedName $name = null;

    /** @var UnqualifiedNamedObjectSet<Column> */
    private readonly UnqualifiedNamedObjectSet $columns;

    /** @var UnqualifiedNamedObjectSet<Index> */
    private UnqualifiedNamedObjectSet $indexes;

    private ?PrimaryKeyConstraint $primaryKeyConstraint = null;

    /** @var OptionallyUnqualifiedNamedObjectSet<UniqueConstraint> */
    private readonly OptionallyUnqualifiedNamedObjectSet $uniqueConstraints;

    /** @var OptionallyUnqualifiedNamedObjectSet<ForeignKeyConstraint> */
    private readonly OptionallyUnqualifiedNamedObjectSet $foreignKeyConstraints;

    /** @var array<string, mixed> */
    private array $options = [];

    private string $comment = '';

    private ?TableConfiguration $configuration = null;

    /** @internal Use {@link Table::editor()} or {@link Table::edit()} to create an instance */
    public function __construct()
    {
        // @phpstan-ignore assign.propertyType (PHPStan doesn't infer the element type)
        $this->columns = new UnqualifiedNamedObjectSet();

        // @phpstan-ignore assign.propertyType
        $this->indexes = new UnqualifiedNamedObjectSet();

        // @phpstan-ignore assign.propertyType
        $this->uniqueConstraints = new OptionallyUnqualifiedNamedObjectSet();

        // @phpstan-ignore assign.propertyType
        $this->foreignKeyConstraints = new OptionallyUnqualifiedNamedObjectSet();
    }

    public function setName(OptionallyQualifiedName $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    public function setUnquotedName(string $unqualifiedName, ?string $qualifier = null): self
    {
        $this->name = OptionallyQualifiedName::unquoted($unqualifiedName, $qualifier);

        return $this;
    }

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    public function setQuotedName(string $unqualifiedName, ?string $qualifier = null): self
    {
        $this->name = OptionallyQualifiedName::quoted($unqualifiedName, $qualifier);

        return $this;
    }

    public function setColumns(Column $firstColumn, Column ...$otherColumns): self
    {
        $this->columns->clear();

        foreach ([$firstColumn, ...$otherColumns] as $column) {
            $this->addColumn($column);
        }

        return $this;
    }

    public function addColumn(Column $column): self
    {
        try {
            $this->columns->add($column);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::columnAlreadyExists($this->name, $e);
        }

        return $this;
    }

    /** @param callable(ColumnEditor): void $modification */
    public function modifyColumn(UnqualifiedName $columnName, callable $modification): self
    {
        try {
            $this->columns->modify($columnName, static function (Column $column) use ($modification): Column {
                $editor = $column->edit();

                $modification($editor);

                return $editor->create();
            });
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::columnDoesNotExist($this->name, $e);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::columnAlreadyExists($this->name, $e);
        }

        return $this;
    }

    /**
     * @param non-empty-string             $columnName
     * @param callable(ColumnEditor): void $modification
     */
    public function modifyColumnByUnquotedName(string $columnName, callable $modification): self
    {
        return $this->modifyColumn(UnqualifiedName::unquoted($columnName), $modification);
    }

    public function renameColumn(UnqualifiedName $oldColumnName, UnqualifiedName $newColumnName): self
    {
        $this->modifyColumn($oldColumnName, static function (ColumnEditor $editor) use ($newColumnName): void {
            $editor->setName($newColumnName);
        });

        $this->renameColumnInIndexes($oldColumnName, $newColumnName);
        $this->renameColumnInPrimaryKeyConstraint($oldColumnName, $newColumnName);
        $this->renameColumnInForeignKeyConstraints($oldColumnName, $newColumnName);
        $this->renameColumnInUniqueConstraints($oldColumnName, $newColumnName);

        return $this;
    }

    private function renameColumnInIndexes(UnqualifiedName $oldColumnName, UnqualifiedName $newColumnName): void
    {
        foreach ($this->indexes->toList() as $index) {
            $modified = false;
            $columns  = [];

            foreach ($index->getIndexedColumns() as $column) {
                $columnName = $column->getColumnName();
                if ($this->namesEqual($columnName, $oldColumnName)) {
                    $columns[] = new Index\IndexedColumn($newColumnName, $column->getLength());
                    $modified  = true;
                } else {
                    $columns[] = $column;
                }
            }

            if (! $modified) {
                continue;
            }

            $this->indexes->modify($index->getObjectName(), static function (Index $index) use ($columns): Index {
                return $index->edit()
                    ->setColumns(...$columns)
                    ->create();
            });
        }
    }

    private function renameColumnInPrimaryKeyConstraint(
        UnqualifiedName $oldColumnName,
        UnqualifiedName $newColumnName,
    ): void {
        if ($this->primaryKeyConstraint === null) {
            return;
        }

        $modified    = false;
        $columnNames = [];

        foreach ($this->primaryKeyConstraint->getColumnNames() as $columnName) {
            if ($this->namesEqual($columnName, $oldColumnName)) {
                $columnNames[] = $newColumnName;
                $modified      = true;
            } else {
                $columnNames[] = $columnName;
            }
        }

        if (! $modified) {
            return;
        }

        $this->primaryKeyConstraint = $this->primaryKeyConstraint->edit()
            ->setColumnNames(...$columnNames)
            ->create();
    }

    private function renameColumnInUniqueConstraints(
        UnqualifiedName $oldColumnName,
        UnqualifiedName $newColumnName,
    ): void {
        $this->renameColumnInConstraints(
            $this->uniqueConstraints,
            $oldColumnName,
            $newColumnName,
            static fn (UniqueConstraint $constraint): array => $constraint->getColumnNames(),
            static function (UniqueConstraint $constraint, array $columnNames): UniqueConstraint {
                return $constraint->edit()
                    ->setColumnNames(...$columnNames)
                    ->create();
            },
        );
    }

    private function renameColumnInForeignKeyConstraints(
        UnqualifiedName $oldColumnName,
        UnqualifiedName $newColumnName,
    ): void {
        $this->renameColumnInConstraints(
            $this->foreignKeyConstraints,
            $oldColumnName,
            $newColumnName,
            static fn (ForeignKeyConstraint $constraint): array => $constraint->getReferencingColumnNames(),
            static function (ForeignKeyConstraint $constraint, array $columnNames): ForeignKeyConstraint {
                return $constraint->edit()
                    ->setReferencingColumnNames(...$columnNames)
                    ->create();
            },
        );
    }

    /**
     * Generic method to rename a column in constraints
     *
     * @param OptionallyUnqualifiedNamedObjectSet<T> $collection
     * @param callable(T): list<UnqualifiedName>     $getColumnNames
     * @param callable(T, list<UnqualifiedName>): T  $modify
     *
     * @template T of OptionallyNamedObject<UnqualifiedName>
     */
    private function renameColumnInConstraints(
        OptionallyUnqualifiedNamedObjectSet $collection,
        UnqualifiedName $oldColumnName,
        UnqualifiedName $newColumnName,
        callable $getColumnNames,
        callable $modify,
    ): void {
        $constraints = [];
        $anyModified = false;

        foreach ($collection->toList() as $constraint) {
            $newColumnNames = [];
            $modified       = false;

            foreach ($getColumnNames($constraint) as $columnName) {
                if ($this->namesEqual($columnName, $oldColumnName)) {
                    $newColumnNames[] = $newColumnName;
                    $modified         = true;
                } else {
                    $newColumnNames[] = $columnName;
                }
            }

            if ($modified) {
                $constraint  = $modify($constraint, $newColumnNames);
                $anyModified = true;
            }

            $constraints[] = $constraint;
        }

        if (! $anyModified) {
            return;
        }

        $collection->clear();

        foreach ($constraints as $constraint) {
            $collection->add($constraint);
        }
    }

    /**
     * @param non-empty-string $oldColumnName
     * @param non-empty-string $newColumnName
     */
    public function renameColumnByUnquotedName(string $oldColumnName, string $newColumnName): self
    {
        return $this->renameColumn(
            UnqualifiedName::unquoted($oldColumnName),
            UnqualifiedName::unquoted($newColumnName),
        );
    }

    public function dropColumn(UnqualifiedName $columnName): self
    {
        try {
            $this->columns->remove($columnName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::columnDoesNotExist($this->name, $e);
        }

        return $this;
    }

    /** @param non-empty-string $columnName */
    public function dropColumnByUnquotedName(string $columnName): self
    {
        return $this->dropColumn(UnqualifiedName::unquoted($columnName));
    }

    public function setIndexes(Index ...$indexes): self
    {
        $this->indexes->clear();

        foreach ($indexes as $index) {
            $this->addIndex($index);
        }

        return $this;
    }

    public function addIndex(Index $index): self
    {
        try {
            $this->indexes->add($index);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::indexAlreadyExists($this->name, $e);
        }

        return $this;
    }

    public function renameIndex(UnqualifiedName $oldIndexName, UnqualifiedName $newIndexName): self
    {
        try {
            $this->indexes->modify($oldIndexName, static function (Index $index) use ($newIndexName): Index {
                return $index->edit()
                    ->setName($newIndexName)
                    ->create();
            });
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::indexDoesNotExist($this->name, $e);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::indexAlreadyExists($this->name, $e);
        }

        return $this;
    }

    /**
     * @param non-empty-string $oldIndexName
     * @param non-empty-string $newIndexName
     */
    public function renameIndexByUnquotedName(string $oldIndexName, string $newIndexName): self
    {
        return $this->renameIndex(
            UnqualifiedName::unquoted($oldIndexName),
            UnqualifiedName::unquoted($newIndexName),
        );
    }

    public function dropIndex(UnqualifiedName $indexName): self
    {
        try {
            $this->indexes->remove($indexName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::indexDoesNotExist($this->name, $e);
        }

        return $this;
    }

    /** @param non-empty-string $indexName */
    public function dropIndexByUnquotedName(string $indexName): self
    {
        return $this->dropIndex(UnqualifiedName::unquoted($indexName));
    }

    public function setPrimaryKeyConstraint(?PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        $this->primaryKeyConstraint = $primaryKeyConstraint;

        return $this;
    }

    public function addPrimaryKeyConstraint(PrimaryKeyConstraint $primaryKeyConstraint): self
    {
        if ($this->primaryKeyConstraint !== null) {
            throw InvalidTableModification::primaryKeyConstraintAlreadyExists($this->name);
        }

        return $this->setPrimaryKeyConstraint($primaryKeyConstraint);
    }

    public function dropPrimaryKeyConstraint(): self
    {
        if ($this->primaryKeyConstraint === null) {
            throw InvalidTableModification::primaryKeyConstraintDoesNotExist($this->name);
        }

        return $this->setPrimaryKeyConstraint(null);
    }

    public function setUniqueConstraints(UniqueConstraint ...$uniqueConstraints): self
    {
        $this->uniqueConstraints->clear();

        foreach ($uniqueConstraints as $uniqueConstraint) {
            $this->addUniqueConstraint($uniqueConstraint);
        }

        return $this;
    }

    public function addUniqueConstraint(UniqueConstraint $uniqueConstraint): self
    {
        try {
            $this->uniqueConstraints->add($uniqueConstraint);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::uniqueConstraintAlreadyExists($this->name, $e);
        }

        return $this;
    }

    public function dropUniqueConstraint(UnqualifiedName $constraintName): self
    {
        try {
            $this->uniqueConstraints->remove($constraintName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::uniqueConstraintDoesNotExist($this->name, $e);
        }

        return $this;
    }

    /** @param non-empty-string $constraintName */
    public function dropUniqueConstraintByUnquotedName(string $constraintName): self
    {
        return $this->dropUniqueConstraint(UnqualifiedName::unquoted($constraintName));
    }

    public function setForeignKeyConstraints(ForeignKeyConstraint ...$foreignKeyConstraints): self
    {
        $this->foreignKeyConstraints->clear();

        foreach ($foreignKeyConstraints as $foreignKeyConstraint) {
            $this->addForeignKeyConstraint($foreignKeyConstraint);
        }

        return $this;
    }

    public function addForeignKeyConstraint(ForeignKeyConstraint $foreignKeyConstraint): self
    {
        try {
            $this->foreignKeyConstraints->add($foreignKeyConstraint);
        } catch (ObjectAlreadyExists $e) {
            throw InvalidTableModification::foreignKeyConstraintAlreadyExists($this->name, $e);
        }

        return $this;
    }

    public function dropForeignKeyConstraint(UnqualifiedName $constraintName): self
    {
        try {
            $this->foreignKeyConstraints->remove($constraintName);
        } catch (ObjectDoesNotExist $e) {
            throw InvalidTableModification::foreignKeyConstraintDoesNotExist($this->name, $e);
        }

        return $this;
    }

    /** @param non-empty-string $constraintName */
    public function dropForeignKeyConstraintByUnquotedName(string $constraintName): self
    {
        return $this->dropForeignKeyConstraint(UnqualifiedName::unquoted($constraintName));
    }

    private function namesEqual(UnqualifiedName $name1, UnqualifiedName $name2): bool
    {
        return strcasecmp($name1->getIdentifier()->getValue(), $name2->getIdentifier()->getValue()) === 0;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function setConfiguration(TableConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function create(): Table
    {
        if ($this->name === null) {
            throw InvalidTableDefinition::nameNotSet();
        }

        if ($this->columns->isEmpty()) {
            throw InvalidTableDefinition::columnsNotSet($this->name);
        }

        $options = $this->options;

        if ($this->comment !== '') {
            $options['comment'] = $this->comment;
        }

        return new Table(
            $this->name->toString(),
            $this->columns->toList(),
            $this->indexes->toList(),
            $this->uniqueConstraints->toList(),
            $this->foreignKeyConstraints->toList(),
            $options,
            $this->configuration,
            $this->primaryKeyConstraint,
        );
    }
}
