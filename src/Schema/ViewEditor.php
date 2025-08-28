<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidViewDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;

final class ViewEditor
{
    private ?OptionallyQualifiedName $name = null;

    private ?string $sql = null;

    /** @internal Use {@link View::editor()} or {@link View::edit()} to create an instance */
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

    public function setSQL(string $sql): self
    {
        $this->sql = $sql;

        return $this;
    }

    public function create(): View
    {
        if ($this->name === null) {
            throw InvalidViewDefinition::nameNotSet();
        }

        if ($this->sql === null) {
            throw InvalidViewDefinition::sqlNotSet($this->name);
        }

        return new View(
            $this->name->toString(),
            $this->sql,
        );
    }
}
