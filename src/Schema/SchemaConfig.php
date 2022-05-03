<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\Deprecations\Deprecation;

/**
 * Configuration for a Schema.
 */
class SchemaConfig
{
    /**
     * @deprecated
     *
     * @var bool
     */
    protected $hasExplicitForeignKeyIndexes = false;

    /** @var int */
    protected $maxIdentifierLength = 63;

    /** @var string|null */
    protected $name;

    /** @var mixed[] */
    protected $defaultTableOptions = [];

    /**
     * @deprecated
     */
    public function hasExplicitForeignKeyIndexes(): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4822',
            'SchemaConfig::hasExplicitForeignKeyIndexes() is deprecated.'
        );

        return $this->hasExplicitForeignKeyIndexes;
    }

    /**
     * @deprecated
     *
     * @param bool $flag
     */
    public function setExplicitForeignKeyIndexes($flag): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4822',
            'SchemaConfig::setExplicitForeignKeyIndexes() is deprecated.'
        );

        $this->hasExplicitForeignKeyIndexes = (bool) $flag;
    }

    /**
     * @param int $length
     */
    public function setMaxIdentifierLength($length): void
    {
        $this->maxIdentifierLength = (int) $length;
    }

    public function getMaxIdentifierLength(): int
    {
        return $this->maxIdentifierLength;
    }

    /**
     * Gets the default namespace of schema objects.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Sets the default namespace name of schema objects.
     *
     * @param string $name The value to set.
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the default options that are passed to Table instances created with
     * Schema#createTable().
     *
     * @return mixed[]
     */
    public function getDefaultTableOptions(): array
    {
        return $this->defaultTableOptions;
    }

    /**
     * @param mixed[] $defaultTableOptions
     */
    public function setDefaultTableOptions(array $defaultTableOptions): void
    {
        $this->defaultTableOptions = $defaultTableOptions;
    }
}
