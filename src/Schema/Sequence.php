<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidSequenceDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Override;

/**
 * Sequence structure.
 *
 * @implements NamedObject<OptionallyQualifiedName>
 */
final class Sequence implements NamedObject
{
    /**
     * @internal Use {@link Sequence::editor()} to instantiate an editor and {@link SequenceEditor::create()} to create
     *           a sequence.
     *
     * @param ?non-negative-int $cacheSize
     */
    public function __construct(
        private readonly OptionallyQualifiedName $name,
        private readonly int $allocationSize,
        private readonly int $initialValue,
        private readonly ?int $cacheSize = null,
    ) {
        if ($cacheSize < 0) {
            throw InvalidSequenceDefinition::fromNegativeCacheSize($cacheSize);
        }
    }

    #[Override]
    public function getObjectName(): OptionallyQualifiedName
    {
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

    /** @return ?non-negative-int */
    public function getCacheSize(): ?int
    {
        return $this->cacheSize;
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
            ->setName($this->name)
            ->setAllocationSize($this->allocationSize)
            ->setInitialValue($this->initialValue)
            ->setCacheSize($this->cacheSize);
    }
}
