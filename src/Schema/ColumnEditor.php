<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Exception\InvalidColumnDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;

final class ColumnEditor
{
    private ?UnqualifiedName $name = null;

    private ?Type $type = null;

    private ?int $length = null;

    private ?int $precision = null;

    private int $scale = 0;

    private bool $unsigned = false;

    private bool $fixed = false;

    private bool $notNull = true;

    private mixed $defaultValue = null;

    private mixed $minimumValue = null;

    private mixed $maximumValue = null;

    private bool $autoincrement = false;

    private string $comment = '';

    /** @var list<string> */
    private array $values = [];

    /** @var ?non-empty-string */
    private ?string $charset = null;

    /** @var ?non-empty-string */
    private ?string $collation = null;

    /** @var ?non-empty-string */
    private ?string $defaultConstraintName = null;

    /** @var ?non-empty-string */
    private ?string $columnDefinition = null;

    /** @internal Use {@link Column::editor()} or {@link Column::edit()} to create an instance */
    public function __construct()
    {
    }

    public function setName(UnqualifiedName $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @param non-empty-string $name */
    public function setUnquotedName(string $name): self
    {
        $this->name = UnqualifiedName::unquoted($name);

        return $this;
    }

    /** @param non-empty-string $name */
    public function setQuotedName(string $name): self
    {
        $this->name = UnqualifiedName::quoted($name);

        return $this;
    }

    public function setType(Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    /** @throws TypesException */
    public function setTypeName(string $typeName): self
    {
        $this->type = Type::getType($typeName);

        return $this;
    }

    public function setLength(?int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function setPrecision(?int $precision): self
    {
        $this->precision = $precision;

        return $this;
    }

    public function setScale(int $scale): self
    {
        $this->scale = $scale;

        return $this;
    }

    public function setUnsigned(bool $unsigned): self
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    public function setFixed(bool $fixed): self
    {
        $this->fixed = $fixed;

        return $this;
    }

    public function setNotNull(bool $notNull): self
    {
        $this->notNull = $notNull;

        return $this;
    }

    public function setDefaultValue(mixed $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function setMinimumValue(mixed $minimumValue): self
    {
        $this->minimumValue = $minimumValue;

        return $this;
    }

    public function setMaximumValue(mixed $maximumValue): self
    {
        $this->maximumValue = $maximumValue;

        return $this;
    }

    public function setAutoincrement(bool $flag): self
    {
        $this->autoincrement = $flag;

        return $this;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /** @param list<string> $values */
    public function setValues(array $values): self
    {
        $this->values = $values;

        return $this;
    }

    /** @param ?non-empty-string $charset */
    public function setCharset(?string $charset): self
    {
        $this->charset = $charset;

        return $this;
    }

    /** @param ?non-empty-string $collation */
    public function setCollation(?string $collation): self
    {
        $this->collation = $collation;

        return $this;
    }

    /**
     * @internal Should be used only from within the {@see AbstractSchemaManager} class hierarchy.
     *
     * @param ?non-empty-string $defaultConstraintName
     */
    public function setDefaultConstraintName(?string $defaultConstraintName): self
    {
        $this->defaultConstraintName = $defaultConstraintName;

        return $this;
    }

    /** @param ?non-empty-string $columnDefinition */
    public function setColumnDefinition(?string $columnDefinition): self
    {
        $this->columnDefinition = $columnDefinition;

        return $this;
    }

    public function create(): Column
    {
        if ($this->name === null) {
            throw InvalidColumnDefinition::nameNotSpecified();
        }

        if ($this->type === null) {
            throw InvalidColumnDefinition::dataTypeNotSpecified($this->name);
        }

        $platformOptions = [];

        if ($this->charset !== null) {
            $platformOptions['charset'] = $this->charset;
        }

        if ($this->collation !== null) {
            $platformOptions['collation'] = $this->collation;
        }

        if ($this->minimumValue !== null) {
            $platformOptions['min'] = $this->minimumValue;
        }

        if ($this->maximumValue !== null) {
            $platformOptions['max'] = $this->maximumValue;
        }

        if ($this->defaultConstraintName !== null) {
            $platformOptions[SQLServerPlatform::OPTION_DEFAULT_CONSTRAINT_NAME] = $this->defaultConstraintName;
        }

        return new Column(
            $this->name->toString(),
            $this->type,
            [
                'length' => $this->length,
                'precision' => $this->precision,
                'scale' => $this->scale,
                'unsigned' => $this->unsigned,
                'fixed' => $this->fixed,
                'notnull' => $this->notNull,
                'default' => $this->defaultValue,
                'autoincrement' => $this->autoincrement,
                'comment' => $this->comment,
                'values' => $this->values,
                'platformOptions' => $platformOptions,
                'columnDefinition' => $this->columnDefinition,
            ],
        );
    }
}
