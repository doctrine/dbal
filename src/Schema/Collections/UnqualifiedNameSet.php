<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\SetAlreadyContainsName;
use Doctrine\DBAL\Schema\Collections\Exception\SetDoesNotContainName;
use Doctrine\DBAL\Schema\Name;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Traversable;

use function strtolower;

/**
 * A set of unique {@see UnqualifiedName}s
 *
 * @internal
 *
 * @template-implements NameSet<UnqualifiedName>
 */
final class UnqualifiedNameSet implements NameSet
{
    /** @var array<string, UnqualifiedName> */
    private array $elements = [];

    public function contains(Name $name): bool
    {
        $key = $this->getKey($name);

        return isset($this->elements[$key]);
    }

    public function add(Name $name): void
    {
        $key = $this->getKey($name);

        if (isset($this->elements[$key])) {
            throw SetAlreadyContainsName::new($name);
        }

        $this->elements[$key] = $name;
    }

    public function remove(Name $name): void
    {
        $key = $this->getKey($name);

        if (! isset($this->elements[$key])) {
            throw SetDoesNotContainName::new($name);
        }

        unset($this->elements[$key]);
    }

    /** @return Traversable<int, UnqualifiedName> */
    public function getIterator(): Traversable
    {
        foreach ($this->elements as $element) {
            yield $element;
        }
    }

    /** @param UnqualifiedName $name */
    private function getKey(Name $name): string
    {
        return strtolower($name->getIdentifier()->getValue());
    }
}
