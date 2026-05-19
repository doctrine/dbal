<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Type;
use Override;

use function array_merge;

/**
 * Object representation of a database column.
 *
 * @implements NamedObject<UnqualifiedName>
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
 *     enumType?: class-string,
 * }
 */
final readonly class Column implements NamedObject
{
    /**
     * @internal Use {@link Column::editor()} to instantiate an editor and {@link ColumnEditor::create()} to create a
     *           column.
     *
     * @param list<string>      $values
     * @param PlatformOptions   $platformOptions
     * @param ?non-empty-string $columnDefinition
     */
    public function __construct(
        private UnqualifiedName $name,
        private Type $type,
        private ?int $length,
        private ?int $precision,
        private int $scale,
        private bool $unsigned,
        private bool $fixed,
        private bool $notnull,
        private mixed $default,
        private bool $autoincrement,
        private array $values,
        private array $platformOptions,
        private ?string $columnDefinition,
        private string $comment,
    ) {
    }

    #[Override]
    public function getObjectName(): UnqualifiedName
    {
        return $this->name;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function getUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function getFixed(): bool
    {
        return $this->fixed;
    }

    public function getNotnull(): bool
    {
        return $this->notnull;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Returns the name of the character set to use with the column.
     *
     * @return ?non-empty-string
     */
    public function getCharset(): ?string
    {
        return $this->platformOptions['charset'] ?? null;
    }

    /**
     * Returns the name of the collation to use with the column.
     *
     * @return ?non-empty-string
     */
    public function getCollation(): ?string
    {
        return $this->platformOptions['collation'] ?? null;
    }

    /**
     * Returns the minimum value to enforce on the column.
     */
    public function getMinimumValue(): mixed
    {
        return $this->platformOptions['min'] ?? null;
    }

    /**
     * Returns the maximum value to enforce on the column.
     */
    public function getMaximumValue(): mixed
    {
        return $this->platformOptions['max'] ?? null;
    }

    /**
     * Returns the enum type used by the column.
     *
     * @return ?class-string
     */
    public function getEnumType(): ?string
    {
        return $this->platformOptions['enumType'] ?? null;
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
        return $this->platformOptions[SQLServerPlatform::OPTION_DEFAULT_CONSTRAINT_NAME] ?? null;
    }

    public function getColumnDefinition(): ?string
    {
        return $this->columnDefinition;
    }

    public function getAutoincrement(): bool
    {
        return $this->autoincrement;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    /** @return list<string> */
    public function getValues(): array
    {
        return $this->values;
    }

    /** @return ColumnProperties */
    public function toArray(): array
    {
        return array_merge([
            'name'             => $this->getObjectName(),
            'type'             => $this->type,
            'default'          => $this->default,
            'notnull'          => $this->notnull,
            'length'           => $this->length,
            'precision'        => $this->precision,
            'scale'            => $this->scale,
            'fixed'            => $this->fixed,
            'unsigned'         => $this->unsigned,
            'autoincrement'    => $this->autoincrement,
            'columnDefinition' => $this->columnDefinition,
            'comment'          => $this->comment,
            'values'           => $this->values,
        ], $this->platformOptions);
    }

    public static function editor(): ColumnEditor
    {
        return new ColumnEditor();
    }

    public function edit(): ColumnEditor
    {
        return self::editor()
            ->setName($this->getObjectName())
            ->setType($this->type)
            ->setLength($this->length)
            ->setPrecision($this->precision)
            ->setScale($this->scale)
            ->setUnsigned($this->unsigned)
            ->setFixed($this->fixed)
            ->setNotNull($this->notnull)
            ->setDefaultValue($this->default)
            ->setAutoincrement($this->autoincrement)
            ->setComment($this->comment)
            ->setValues($this->values)
            ->setColumnDefinition($this->columnDefinition)
            ->setCharset($this->getCharset())
            ->setCollation($this->getCollation())
            ->setMinimumValue($this->getMinimumValue())
            ->setMaximumValue($this->getMaximumValue())
            ->setEnumType($this->getEnumType())
            ->setDefaultConstraintName($this->getDefaultConstraintName());
    }
}
