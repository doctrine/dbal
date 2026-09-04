<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\Deprecations\Deprecation;
use TypeError;

use function array_merge;
use function func_get_arg;
use function func_num_args;
use function get_debug_type;
use function is_bool;
use function method_exists;
use function sprintf;

/**
 * Object representation of a database column.
 *
 * @final
 * @extends AbstractNamedObject<UnqualifiedName>
 * @phpstan-type ColumnProperties = array{
 *     name: string,
 *     type?: Type,
 *     typeName: string,
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
 *     enumType?: class-string,
 *     jsonb?: bool,
 *     version?: bool,
 * }
 */
class Column extends AbstractNamedObject
{
    /** @deprecated use $_typeName instead */
    protected Type $_type;

    protected string $_typeName;

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

    private ?TypeProvider $typeRegistry = null;

    /**
     * @internal Use {@link Column::editor()} to instantiate an editor and {@link ColumnEditor::create()} to create a
     *           column.
     *
     * @param Type|string          $type    Passing a {@see Type} instance is deprecated; pass the type name instead.
     * @param array<string, mixed> $options
     *
     * @throws TypesException
     */
    public function __construct(string $name, Type|string $type, array $options = [])
    {
        parent::__construct($name);

        if ($type instanceof Type) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7490',
                'Passing a %s instance to %s() is deprecated, pass the type name instead.',
                Type::class,
                __METHOD__,
            );

            $this->setType($type);
        } else {
            $this->setTypeName($type);
        }

        $this->setOptions($options);
    }

    protected function getNameParser(): UnqualifiedNameParser
    {
        return Parsers::getUnqualifiedNameParser();
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} instead.
     *
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() instead.',
            __METHOD__,
        );

        foreach ($options as $name => $value) {
            $method = 'set' . $name;

            if (! method_exists($this, $method)) {
                throw UnknownColumnOption::new($name);
            }

            $this->$method($value);
        }

        return $this;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setTypeName()} instead.
     *
     * @throws TypesException
     */
    public function setType(Type $type): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setTypeName() instead.',
            __METHOD__,
        );

        $this->_type = $type;
        if ($this->typeRegistry === null || $this->typeRegistry instanceof TypeRegistry) {
            $this->_typeName = ($this->typeRegistry ?? Type::getTypeRegistry())->lookupName($type);

            return $this;
        }

        foreach ($this->typeRegistry as $name => $candidate) {
            if ($candidate === $type) {
                $this->_typeName = $name;

                return $this;
            }
        }

        throw TypeNotRegistered::new($type);
    }

    public function setTypeName(string $typeName): self
    {
        $this->_typeName = $typeName;
        unset($this->_type);

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setLength()} instead. */
    public function setLength(?int $length): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setLength() instead.',
            __METHOD__,
        );

        $this->_length = $length;

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setPrecision()} instead. */
    public function setPrecision(?int $precision): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setPrecision() instead.',
            __METHOD__,
        );

        $this->_precision = $precision;

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setScale()} instead. */
    public function setScale(int $scale): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setScale() instead.',
            __METHOD__,
        );

        $this->_scale = $scale;

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setUnsigned()} instead. */
    public function setUnsigned(bool $unsigned): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setUnsigned() instead.',
            __METHOD__,
        );

        $this->_unsigned = $unsigned;

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setFixed()} instead. */
    public function setFixed(bool $fixed): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setFixed() instead.',
            __METHOD__,
        );

        $this->_fixed = $fixed;

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setNotNull()} instead. */
    public function setNotnull(bool $notnull): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setNotNull() instead.',
            __METHOD__,
        );

        $this->_notnull = $notnull;

        return $this;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setDefaultValue()}
     *             instead.
     */
    public function setDefault(mixed $default): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setDefaultValue() instead.',
            __METHOD__,
        );

        $this->_default = $default;

        return $this;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and the option-specific {@see ColumnEditor}
     *             methods ({@see ColumnEditor::setCharset()}, {@see ColumnEditor::setCollation()},
     *             {@see ColumnEditor::setMinimumValue()}, {@see ColumnEditor::setMaximumValue()},
     *             {@see ColumnEditor::setEnumType()}) instead.
     *
     * @param PlatformOptions $platformOptions
     */
    public function setPlatformOptions(array $platformOptions): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and the option-specific ColumnEditor methods'
                . ' (setCharset(), setCollation(), setMinimumValue(), setMaximumValue(), setEnumType()) instead.',
            __METHOD__,
        );

        if (isset($platformOptions['jsonb']) && $platformOptions['jsonb']) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6939',
                'The "jsonb" column platform option is deprecated. Use the "JSONB" type instead.',
            );
        }

        $this->_platformOptions = $platformOptions;

        return $this;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and the option-specific {@see ColumnEditor}
     *             methods ({@see ColumnEditor::setCharset()}, {@see ColumnEditor::setCollation()},
     *             {@see ColumnEditor::setMinimumValue()}, {@see ColumnEditor::setMaximumValue()},
     *             {@see ColumnEditor::setEnumType()}) instead.
     *
     * @param key-of<PlatformOptions> $name
     */
    public function setPlatformOption(string $name, mixed $value): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and the option-specific ColumnEditor methods'
                . ' (setCharset(), setCollation(), setMinimumValue(), setMaximumValue(), setEnumType()) instead.',
            __METHOD__,
        );

        if ($name === 'jsonb' && (bool) $value === true) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6939',
                'The "jsonb" column platform option is deprecated. Use the "JSONB" type instead.',
            );
        }

        $this->_platformOptions[$name] = $value;

        return $this;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setColumnDefinition()}
     *             instead.
     *
     * @param ?non-empty-string $value
     */
    public function setColumnDefinition(?string $value): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setColumnDefinition() instead.',
            __METHOD__,
        );

        $this->_columnDefinition = $value;

        return $this;
    }

    /**
     * @deprecated Use {@see getTypeName()} to obtain the type name, or resolve the {@see Type}
     *             instance via {@see Configuration::getTypeRegistry()} when needed.
     *
     * @throws TypesException
     */
    public function getType(): Type
    {
        // Called from toArray() when $skipType is false, which already triggers its own
        // deprecation, so triggering unconditionally here would report the same call twice.
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7490',
            '%s is deprecated. Use Column::getTypeName() instead.',
            __METHOD__,
        );

        return ($this->typeRegistry ?? Type::getTypeRegistry())->get($this->_typeName);
    }

    /**
     * Returns the name of the DBAL type of this column.
     */
    public function getTypeName(): string
    {
        return $this->_typeName;
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
     * Returns the enum type used by the column.
     *
     * @return ?class-string
     */
    public function getEnumType(): ?string
    {
        return $this->_platformOptions['enumType'] ?? null;
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
     * @deprecated Use {@see getCharset()}, {@see getCollation()}, {@see getMinimumValue()}, {@see getMaximumValue()}
     *             or {@see getEnumType()} instead.
     *
     * @return PlatformOptions
     */
    public function getPlatformOptions(): array
    {
        return $this->_platformOptions;
    }

    /**
     * @deprecated Use {@see getCharset()}, {@see getCollation()}, {@see getMinimumValue()}, {@see getMaximumValue()}
     *             or {@see getEnumType()} instead.
     *
     * @param key-of<PlatformOptions> $name
     */
    public function hasPlatformOption(string $name): bool
    {
        return isset($this->_platformOptions[$name]);
    }

    /**
     * @deprecated Use {@see getCharset()}, {@see getCollation()}, {@see getMinimumValue()}, {@see getMaximumValue()}
     *             or {@see getEnumType()} instead.
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

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setAutoincrement()}
     *             instead.
     */
    public function setAutoincrement(bool $flag): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setAutoincrement() instead.',
            __METHOD__,
        );

        $this->_autoincrement = $flag;

        return $this;
    }

    /** @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setComment()} instead. */
    public function setComment(string $comment): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setComment() instead.',
            __METHOD__,
        );

        $this->_comment = $comment;

        return $this;
    }

    public function getComment(): string
    {
        return $this->_comment;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see Column::editor()} and {@see ColumnEditor::setValues()} instead.
     *
     * @param list<string> $values
     *
     * @return $this
     */
    public function setValues(array $values): static
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7381',
            '%s is deprecated. Use Column::editor() and ColumnEditor::setValues() instead.',
            __METHOD__,
        );

        $this->_values = $values;

        return $this;
    }

    /** @return list<string> */
    public function getValues(): array
    {
        return $this->_values;
    }

    /**
     * Pass `true` as the first (virtual) argument to omit the resolved {@see Type} instance from the returned array
     * and rely on the `typeName` key instead. Omitting the argument is deprecated.
     *
     * @return ColumnProperties
     */
    public function toArray(/* bool $skipType = false */): array
    {
        $skipType = func_num_args() > 0 ? func_get_arg(0) : false;
        if (! is_bool($skipType)) {
            // @phpstan-ignore missingType.checkedException
            throw new TypeError(sprintf(
                'Argument 1 passed to %s must be a boolean, %s given',
                __METHOD__,
                get_debug_type($skipType),
            ));
        }

        if (! $skipType) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7490',
                'Calling %s() without the $skipType argument is deprecated. Pass true to omit the Type instance from '
                . 'the returned array and read the "typeName" key instead.',
                __METHOD__,
            );
        }

        return array_merge([
            'name'             => $this->_name,
            'typeName'         => $this->_typeName,
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
        // @phpstan-ignore missingType.checkedException
        ], $skipType ? [] : ['type' => $this->getType()], $this->_platformOptions);
    }

    public static function editor(): ColumnEditor
    {
        return new ColumnEditor();
    }

    public function edit(): ColumnEditor
    {
        return self::editor()
            ->setName($this->getObjectName())
            ->setTypeName($this->_typeName)
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
            ->setEnumType($this->getEnumType())
            ->setDefaultConstraintName($this->getDefaultConstraintName());
    }

    /**
     * @internal This method if necessary for ensuring backward compatibility
     * for the deprecated {@see getType } method.
     */
    public function setTypeRegistry(?TypeProvider $typeRegistry): void
    {
        $this->typeRegistry = $typeRegistry;
    }
}
