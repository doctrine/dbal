<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidSequenceDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;

final class SequenceEditor
{
    private ?OptionallyQualifiedName $name = null;

    private int $allocationSize = 1;

    private int $initialValue = 1;

    /** @var ?non-negative-int */
    private ?int $cacheSize = null;

    /** @internal Use {@link Sequence::editor()} or {@link Sequence::edit()} to create an instance */
    public function __construct()
    {
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

    public function setAllocationSize(int $allocationSize): self
    {
        $this->allocationSize = $allocationSize;

        return $this;
    }

    public function setInitialValue(int $initialValue): self
    {
        $this->initialValue = $initialValue;

        return $this;
    }

    /** @param ?non-negative-int $cacheSize */
    public function setCacheSize(?int $cacheSize): self
    {
        if ($cacheSize < 0) {
            throw InvalidSequenceDefinition::fromNegativeCacheSize($cacheSize);
        }

        $this->cacheSize = $cacheSize;

        return $this;
    }

    public function create(): Sequence
    {
        if ($this->name === null) {
            throw InvalidSequenceDefinition::nameNotSet();
        }

        return new Sequence(
            $this->name->toString(),
            $this->allocationSize,
            $this->initialValue,
            $this->cacheSize,
        );
    }
}
