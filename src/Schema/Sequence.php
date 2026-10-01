<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\Deprecations\Deprecation;

use function count;
use function sprintf;

/**
 * Sequence structure.
 *
 * @final
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
class Sequence extends AbstractNamedObject
{
    protected int $allocationSize = 1;

    protected int $initialValue = 1;

    /**
     * @internal Use {@link Sequence::editor()} to instantiate an editor and {@link SequenceEditor::create()} to create
     *           a sequence.
     *
     * @param ?non-negative-int $cache
     */
    public function __construct(
        string $name,
        int $allocationSize = 1,
        int $initialValue = 1,
        protected ?int $cache = null,
    ) {
        parent::__construct($name);

        if ($cache < 0) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7108',
                'Passing a negative value as sequence cache size is deprecated.',
            );
        }

        $this->setAllocationSize($allocationSize);
        $this->setInitialValue($initialValue);
    }

    protected function getNameParser(): OptionallyQualifiedNameParser
    {
        return Parsers::getOptionallyQualifiedNameParser();
    }

    public function getObjectName(): OptionallyQualifiedName
    {
        if (! $this->isNameInitialized) {
            throw InvalidState::objectNameNotInitialized();
        }

        return $this->name;
    }

    public function getAllocationSize(): int
    {
        return $this->allocationSize;
    }

    public function getInitialValue(): int
    {
        return $this->initialValue;
    }

    /**
     * @deprecated Use {@see getCacheSize()} instead.
     *
     * @return ?non-negative-int
     */
    public function getCache(): ?int
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7108',
            '%s is deprecated, use `getCacheSize()` instead.',
            __METHOD__,
        );

        return $this->cache;
    }

    /** @return ?non-negative-int */
    public function getCacheSize(): ?int
    {
        return $this->getCache();
    }

    /** @deprecated Use {@see edit()} and {@see SequenceEditor::setAllocationSize()} instead. */
    public function setAllocationSize(int $allocationSize): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7115',
            '%s is deprecated. Use Sequence::edit() and SequenceEditor::setAllocationSize() instead.',
            __METHOD__,
        );

        $this->allocationSize = $allocationSize;

        return $this;
    }

    /** @deprecated Use {@see edit()} and {@see SequenceEditor::setInitialValue()} instead. */
    public function setInitialValue(int $initialValue): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7115',
            '%s is deprecated. Use Sequence::edit() and SequenceEditor::setInitialValue() instead.',
            __METHOD__,
        );

        $this->initialValue = $initialValue;

        return $this;
    }

    /**
     * @deprecated Use {@see edit()} and {@see SequenceEditor::setCacheSize()} instead.
     *
     * @param non-negative-int $cache
     */
    public function setCache(int $cache): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7115',
            '%s is deprecated. Use Sequence::edit() and SequenceEditor::setCacheSize() instead.',
            __METHOD__,
        );

        $this->cache = $cache;

        return $this;
    }

    /**
     * Checks if this sequence is an autoincrement sequence for a given table.
     *
     * This is used inside the comparator to not report sequences as missing,
     * when the "from" schema implicitly creates the sequences.
     *
     * @deprecated
     */
    public function isAutoIncrementsFor(Table $table): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6654',
            '%s is deprecated and will be removed in 5.0.',
            __METHOD__,
        );

        $primaryKey = $table->getPrimaryKey();

        if ($primaryKey === null) {
            return false;
        }

        $pkColumns = $primaryKey->getColumns();

        if (count($pkColumns) !== 1) {
            return false;
        }

        $column = $table->getColumn($pkColumns[0]);

        if (! $column->getAutoincrement()) {
            return false;
        }

        $sequenceName      = $this->getShortestName($table->getNamespaceName());
        $tableName         = $table->getShortestName($table->getNamespaceName());
        $tableSequenceName = sprintf('%s_%s_seq', $tableName, $column->getShortestName($table->getNamespaceName()));

        return $tableSequenceName === $sequenceName;
    }

    /**
     * Instantiates a new sequence editor.
     */
    public static function editor(): SequenceEditor
    {
        return new SequenceEditor();
    }

    /**
     * Instantiates a new sequence editor and initializes it with the sequence's properties.
     */
    public function edit(): SequenceEditor
    {
        return self::editor()
            ->setName($this->getObjectName())
            ->setAllocationSize($this->getAllocationSize())
            ->setInitialValue($this->getInitialValue())
            ->setCacheSize($this->getCacheSize());
    }
}
