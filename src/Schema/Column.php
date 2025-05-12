<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Type;

use function array_merge;
use function method_exists;

/**
 * Object representation of a database column.
 *
 * @final
 * @extends AbstractNamedObject<UnqualifiedName>
 * @phpstan-type ColumnProperties = array{
 *     name: UnqualifiedName,
 *     type: Type,
 *     default: mixed,
 *     notnull?: bool,
 *     autoincrement: bool,
 *     columnDefinition: ?non-empty-string,
 *     comment: string,
 *     charset?: ?non-empty-string,
 *     collation?: ?non-empty-string,
 * }
 * @phpstan-type PlatformOptions = array{
 *     charset?: ?non-empty-string,
 *     collation?: ?non-empty-string,
 *     default_constraint_name?: non-empty-string,
 * }
 */
class Column extends AbstractNamedObject
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

    /** @var PlatformOptions */
    protected array $_platformOptions = [];

    /** @var ?non-empty-string */
    protected ?string $_columnDefinition = null;

    protected string $_comment = '';

    /**
     * @internal Use {@link Column::editor()} to instantiate an editor and {@link ColumnEditor::create()} to create a
     *           column.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, Type $type, array $options = [])
    {
        parent::__construct($name);

        $this->setType($type);
        $this->setOptions($options);
    }

    protected function getNameParser(): UnqualifiedNameParser
    {
        return Parsers::getUnqualifiedNameParser();
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

    /** @param PlatformOptions $platformOptions */
    public function setPlatformOptions(array $platformOptions): self
    {
        $this->_platformOptions = $platformOptions;

        return $this;
    }

    /** @param key-of<PlatformOptions> $name */
    public function setPlatformOption(string $name, mixed $value): self
    {
        $this->_platformOptions[$name] = $value;

        return $this;
    }

    /** @param  ?non-empty-string $value */
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

    /**
     * Returns the name of the character set to use with the column.
     *
     * @return ?non-empty-string
     */
    public function getCharset(): ?string
    {
        return $this->_platformOptions['charset'] ?? null;
    }

    /**
     * Returns the name of the collation to use with the column.
     *
     * @return ?non-empty-string
     */
    public function getCollation(): ?string
    {
        return $this->_platformOptions['collation'] ?? null;
    }

    /**
     * Returns the minimum value to enforce on the column.
     */
    public function getMinimumValue(): mixed
    {
        return $this->_platformOptions['min'] ?? null;
    }

    /**
     * Returns the maximum value to enforce on the column.
     */
    public function getMaximumValue(): mixed
    {
        return $this->_platformOptions['max'] ?? null;
    }

    /**
     * @internal Should be used only from within the {@see AbstractSchemaManager} class hierarchy.
     *
     * Returns the name of the DEFAULT constraint that implements the default value for the column on SQL Server.
     *
     * @return ?non-empty-string
     */
    public function getDefaultConstraintName(): ?string
    {
        return $this->_platformOptions[SQLServerPlatform::OPTION_DEFAULT_CONSTRAINT_NAME] ?? null;
    }

    /**
     * @deprecated Use {@see getCharset()}, {@see getCollation()}, {@see getMinimumValue()} or {@see getMaximumValue()}
     *             instead.
     *
     * @return PlatformOptions
     */
    public function getPlatformOptions(): array
    {
        return $this->_platformOptions;
    }

    /**
     * @deprecated Use {@see getCharset()}, {@see getCollation()}, {@see getMinimumValue()} or {@see getMaximumValue()}
     *             instead.
     *
     * @param key-of<PlatformOptions> $name
     */
    public function hasPlatformOption(string $name): bool
    {
        return isset($this->_platformOptions[$name]);
    }

    /**
     * @deprecated Use {@see getCharset()}, {@see getCollation()}, {@see getMinimumValue()} or {@see getMaximumValue()}
     *             instead.
     *
     * @param key-of<PlatformOptions> $name
     */
    public function getPlatformOption(string $name): mixed
    {
        /** @phpstan-ignore offsetAccess.notFound */
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

    /** @return ColumnProperties */
    public function toArray(): array
    {
        return array_merge([
            'name'             => $this->getObjectName(),
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

    public static function editor(): ColumnEditor
    {
        return new ColumnEditor();
    }

    public function edit(): ColumnEditor
    {
        return self::editor()
            ->setName($this->getObjectName())
            ->setType($this->_type)
            ->setLength($this->_length)
            ->setPrecision($this->_precision)
            ->setScale($this->_scale)
            ->setUnsigned($this->_unsigned)
            ->setFixed($this->_fixed)
            ->setNotNull($this->_notnull)
            ->setDefaultValue($this->_default)
            ->setAutoincrement($this->_autoincrement)
            ->setComment($this->_comment)
            ->setValues($this->_values)
            ->setColumnDefinition($this->_columnDefinition)
            ->setCharset($this->getCharset())
            ->setCollation($this->getCollation())
            ->setMinimumValue($this->getMinimumValue())
            ->setMaximumValue($this->getMaximumValue())
            ->setDefaultConstraintName($this->getDefaultConstraintName());
    }
}
