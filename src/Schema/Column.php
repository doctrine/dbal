<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Types\Type;

use function array_merge;
use function method_exists;

/**
 * Object representation of a database column.
 */
class Column extends AbstractAsset
{
    protected Type $_type;

    protected ?int $_length = null;

    protected ?int $_precision = null;

    protected int $_scale = 0;

    protected bool $_unsigned = false;

    protected bool $_fixed = false;

    protected bool $_notnull = true;

    protected mixed $_default = null;

    protected bool $_autoincrement = false;

    /** @var list<string> */
    protected array $_values = [];

    /** @var array<string, mixed> */
    protected array $_platformOptions = [];

    protected ?string $_columnDefinition = null;

    protected string $_comment = '';

    /**
     * Creates a new Column.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, Type $type, array $options = [])
    {
        $this->_setName($name);
        $this->setType($type);
        $this->setOptions($options);
    }

    /** @param array<string, mixed> $options */
    public function setOptions(array $options): self
    {
        foreach ($options as $name => $value) {
            $method = 'set' . $name;

            if (! method_exists($this, $method)) {
                throw UnknownColumnOption::new($name);
            }

            $this->$method($value);
        }

        return $this;
    }

    public function setType(Type $type): self
    {
        $this->_type = $type;

        return $this;
    }

    public function setLength(?int $length): self
    {
        $this->_length = $length;

        return $this;
    }

    public function setPrecision(?int $precision): self
    {
        $this->_precision = $precision;

        return $this;
    }

    public function setScale(int $scale): self
    {
        $this->_scale = $scale;

        return $this;
    }

    public function setUnsigned(bool $unsigned): self
    {
        $this->_unsigned = $unsigned;

        return $this;
    }

    public function setFixed(bool $fixed): self
    {
        $this->_fixed = $fixed;

        return $this;
    }

    public function setNotnull(bool $notnull): self
    {
        $this->_notnull = $notnull;

        return $this;
    }

    public function setDefault(mixed $default): self
    {
        $this->_default = $default;

        return $this;
    }

    /** @param array<string, mixed> $platformOptions */
    public function setPlatformOptions(array $platformOptions): self
    {
        $this->_platformOptions = $platformOptions;

        return $this;
    }

    public function setPlatformOption(string $name, mixed $value): self
    {
        $this->_platformOptions[$name] = $value;

        return $this;
    }

    public function setColumnDefinition(?string $value): self
    {
        $this->_columnDefinition = $value;

        return $this;
    }

    public function getType(): Type
    {
        return $this->_type;
    }

    public function getLength(): ?int
    {
        return $this->_length;
    }

    public function getPrecision(): ?int
    {
        return $this->_precision;
    }

    public function getScale(): int
    {
        return $this->_scale;
    }

    public function getUnsigned(): bool
    {
        return $this->_unsigned;
    }

    public function getFixed(): bool
    {
        return $this->_fixed;
    }

    public function getNotnull(): bool
    {
        return $this->_notnull;
    }

    public function getDefault(): mixed
    {
        return $this->_default;
    }

    /** @return array<string, mixed> */
    public function getPlatformOptions(): array
    {
        return $this->_platformOptions;
    }

    public function hasPlatformOption(string $name): bool
    {
        return isset($this->_platformOptions[$name]);
    }

    public function getPlatformOption(string $name): mixed
    {
        return $this->_platformOptions[$name];
    }

    public function getColumnDefinition(): ?string
    {
        return $this->_columnDefinition;
    }

    public function getAutoincrement(): bool
    {
        return $this->_autoincrement;
    }

    public function setAutoincrement(bool $flag): self
    {
        $this->_autoincrement = $flag;

        return $this;
    }

    public function setComment(string $comment): self
    {
        $this->_comment = $comment;

        return $this;
    }

    public function getComment(): string
    {
        return $this->_comment;
    }

    /**
     * @param list<string> $values
     *
     * @return $this
     */
    public function setValues(array $values): static
    {
        $this->_values = $values;

        return $this;
    }

    /** @return list<string> */
    public function getValues(): array
    {
        return $this->_values;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge([
            'name'             => $this->_name,
            'type'             => $this->_type,
            'default'          => $this->_default,
            'notnull'          => $this->_notnull,
            'length'           => $this->_length,
            'precision'        => $this->_precision,
            'scale'            => $this->_scale,
            'fixed'            => $this->_fixed,
            'unsigned'         => $this->_unsigned,
            'autoincrement'    => $this->_autoincrement,
            'columnDefinition' => $this->_columnDefinition,
            'comment'          => $this->_comment,
            'values'           => $this->_values,
        ], $this->_platformOptions);
    }
}
