<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\Deprecations\Deprecation;

/**
 * Sequence structure.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
final class Sequence extends AbstractNamedObject
{
    private int $allocationSize = 1;

    private int $initialValue = 1;

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
        private ?int $cache = null,
    ) {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $parsedName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        parent::__construct($parsedName);

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

    /** @param non-negative-int $cache */
    public function setCache(int $cache): self
    {
        $this->cache = $cache;

        return $this;
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
