<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * Configuration for a Schema.
 */
class SchemaConfig
{
    protected int $maxIdentifierLength = 63;

    protected ?string $name = null;

    /** @var array<string, mixed> */
    protected array $defaultTableOptions = [];

    public function setMaxIdentifierLength(int $length): void
    {
        $this->maxIdentifierLength = $length;
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
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the default options that are passed to Table instances created with
     * Schema#createTable().
     *
     * @return array<string, mixed>
     */
    public function getDefaultTableOptions(): array
    {
        return $this->defaultTableOptions;
    }

    /** @param array<string, mixed> $defaultTableOptions */
    public function setDefaultTableOptions(array $defaultTableOptions): void
    {
        $this->defaultTableOptions = $defaultTableOptions;
    }
}
