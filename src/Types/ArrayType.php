<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_resource;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_get_contents;
use function unserialize;

/**
 * Type that maps a PHP array to a clob SQL type.
 */
class ArrayType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        // @todo 3.0 - $value === null check to save real NULL in database
        return serialize($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_resource($value) ? stream_get_contents($value) : $value;

        set_error_handler(static function (int $code, string $message) use ($value): bool {
            throw ValueNotConvertible::new($value, 'array', $message);
        });

        try {
            return unserialize($value);
        } finally {
            restore_error_handler();
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
