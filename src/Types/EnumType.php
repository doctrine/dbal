<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use ReflectionClass;
use Throwable;
use UnitEnum;

use function array_map;
use function class_exists;
use function enum_exists;
use function implode;
use function is_object;
use function is_string;
use function sprintf;

final class EnumType extends Type
{
    public string $name = 'enum';

    public ?string $enumClassname = null;

    /** @var array<int, string> */
    public array $members = [];

    /**
     * {@inheritDoc}
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [$this->name];
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = implode(
            ', ',
            array_map(
                static fn (string $value) => sprintf('\'%s\'', $value),
                $column['members'] ?: $column['type']->members,
            ),
        );

        return match (true) {
            $platform instanceof SQLitePlatform => sprintf('TEXT CHECK(%s IN (%s))', $column['name'], $values),
            $platform instanceof PostgreSQLPlatform, $platform instanceof SQLServerPlatform => sprintf('VARCHAR(255) CHECK(%s IN (%s))', $column['name'], $values),
            default => sprintf('ENUM(%s)', $values),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($this->enumClassname === null) {
            if (! is_string($value)) {
                throw InvalidType::new($value, $this->name, ['null', 'string']);
            }

            if (! in_array($value, $this->members)) {
                throw InvalidType::new($value, $this->name, ['null', 'string']);
            }

            return $value;
        }

        if (enum_exists($this->enumClassname)) {
            if (! $value instanceof UnitEnum) {
                throw InvalidType::new($value, $this->name, ['null', $this->enumClassname]);
            }

            if ($value instanceof \BackedEnum) {
                return $value->value;
            }

            return $value->name;
        }

        if (! (is_object($value) && $value::class === $this->enumClassname)) {
            throw InvalidType::new($value, $this->name, ['null', $this->enumClassname]);
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidFormat::new($value, $this->name, 'string');
        }

        if ($this->enumClassname === null) {
            if (! in_array($value, $this->members)) {
                throw ValueNotConvertible::new($value, $this->name);
            }

            return $value;
        }

        if (enum_exists($this->enumClassname)) {
            foreach ($this->enumClassname::cases() as $case) {
                if (($case instanceof BackedEnum && $value === $case->value) || $value === $case->name) {
                    return $case;
                }
            }

            throw ValueNotConvertible::new($value, $this->getInternalDocrineType());
        }

        if (class_exists($this->enumClassname)) {
            $refl = new ReflectionClass($this->enumClassname);

            try {
                return $refl->newInstance($value);
            } catch (Throwable $e) {
                throw ValueNotConvertible::new($value, $this->getInternalDocrineType(), $e->getMessage(), $e);
            }
        }

        throw new ConversionException(sprintf('Class %s does not exists', $this->enumClassname));
    }

    private function getInternalDocrineType(): string
    {
        if ($this->enumClassname) {
            return sprintf('%s(%s)', $this->name, $this->enumClassname);
        }

        return sprintf('%s(%s)', $this->name, 'string');
    }
}
