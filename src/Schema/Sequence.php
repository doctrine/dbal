<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;

/**
 * Sequence structure.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
class Sequence extends AbstractNamedObject
{
    protected int $allocationSize = 1;

    protected int $initialValue = 1;

    public function __construct(
        string $name,
        int $allocationSize = 1,
        int $initialValue = 1,
        protected ?int $cache = null,
    ) {
        parent::__construct($name);

        $this->setAllocationSize($allocationSize);
        $this->setInitialValue($initialValue);
    }

    protected function getNameParser(): OptionallyQualifiedNameParser
    {
        return Parsers::getOptionallyQualifiedNameParser();
    }

    public function getAllocationSize(): int
    {
        return $this->allocationSize;
    }

    public function getInitialValue(): int
    {
        return $this->initialValue;
    }

    public function getCache(): ?int
    {
        return $this->cache;
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

    public function setCache(int $cache): self
    {
        $this->cache = $cache;

        return $this;
    }
}
