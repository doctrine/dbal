<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

final class EnumType extends Type
{
    public string $name = 'enum';

    public ?string $enumClassname = null;

    public array $members = [];

    /**
     * Gets an array of database types that map to this Doctrine type.
     *
     * @return string[]
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [$this->name];
    }

    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param mixed[]          $column   The field declaration
     * @param AbstractPlatform $platform The currently used database platform
     */
    public function getSqlDeclaration(array $column, AbstractPlatform $platform): string
    {
        assert($column['type'] instanceof self::class);

        $values = implode(
            ', ',
            array_map(
                static fn (string $value) => "'{$value}'",
                $column['members'] ?: $column['type']->members
            )
        );

        $sqlDeclaration = match (true) {
            $platform instanceof SqlitePlatform => sprintf('TEXT CHECK(%s IN (%s))', $column['name'], $values),
            $platform instanceof PostgreSqlPlatform, $platform instanceof SQLServerPlatform => sprintf('VARCHAR(255) CHECK(%s IN (%s))', $column['name'], $values),
            default => sprintf('ENUM(%s)', $values),
        };

        return $sqlDeclaration;
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed the database representation of the value
     *
     * @throws \InvalidArgumentException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return (string) $value;
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed the PHP representation of the value
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, $this->name, ['null', 'string']);
        }

        $refl = new \ReflectionClass($this->enumClassname);

        try {
            return $refl->newInstance($value);
        } catch (\Throwable $e) {
            throw ValueNotConvertible::new($value, $this->name, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function addType(string $name, string $enumClassname): void
    {
        self::getTypeRegistry()->register($name, $me = new self());
        $me->name = $name;
        $me->enumClassname = $enumClassname;
        $me->members = $enumClassname::getAllowedValues();
    }
}
